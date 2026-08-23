<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class PosDocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $type = $request->string('type')->toString();
        $search = trim((string) $request->string('search'));

        $documents = DB::table('pos_documents')
            ->leftJoin('users', 'users.id', '=', 'pos_documents.created_by')
            ->when(in_array($type, ['quotation', 'invoice'], true), fn ($query) => $query->where('pos_documents.type', $type))
            ->when($search !== '', fn ($query) => $query->where(function ($sub) use ($search): void {
                $sub->where('pos_documents.reference', 'like', "%{$search}%")
                    ->orWhere('pos_documents.customer_name', 'like', "%{$search}%")
                    ->orWhere('pos_documents.customer_phone', 'like', "%{$search}%");
            }))
            ->select('pos_documents.*', 'users.name as created_by_name')
            ->latest('pos_documents.id')
            ->limit(200)
            ->get();

        return Inertia::render('Admin/Pos/Documents/Index', [
            'documents' => $documents,
            'filters' => ['type' => $type, 'search' => $search],
            'summary' => [
                'quotations' => DB::table('pos_documents')->where('type', 'quotation')->count(),
                'invoices' => DB::table('pos_documents')->where('type', 'invoice')->count(),
                'quotation_value' => (float) DB::table('pos_documents')->where('type', 'quotation')->sum('total'),
                'invoice_value' => (float) DB::table('pos_documents')->where('type', 'invoice')->sum('total'),
            ],
        ]);
    }

    public function quotation(Request $request, AuditService $audit): RedirectResponse
    {
        return $this->storeDocument($request, 'quotation', $audit);
    }

    public function invoice(Request $request, AuditService $audit): RedirectResponse
    {
        return $this->storeDocument($request, 'invoice', $audit);
    }

    public function show(int $document): Response
    {
        $record = DB::table('pos_documents')
            ->leftJoin('users', 'users.id', '=', 'pos_documents.created_by')
            ->leftJoin('pos_customers', 'pos_customers.id', '=', 'pos_documents.customer_id')
            ->where('pos_documents.id', $document)
            ->select('pos_documents.*', 'users.name as created_by_name', 'pos_customers.tin as saved_customer_tin')
            ->first();

        abort_unless($record, 404);

        return Inertia::render('Admin/Pos/Documents/Show', [
            'document' => $record,
            'items' => DB::table('pos_document_items')->where('pos_document_id', $document)->orderBy('id')->get(),
        ]);
    }

    private function storeDocument(Request $request, string $type, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:pos_customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:60'],
            'customer_email' => ['nullable', 'email:rfc', 'max:255'],
            'customer_tin' => ['nullable', 'string', 'max:50'],
            'save_customer' => ['nullable', 'boolean'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'valid_until' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $documentId = DB::transaction(function () use ($validated, $request, $type, $audit): int {
                $customerId = $validated['customer_id'] ?? null;
                if (! $customerId && ! empty($validated['save_customer']) && ! empty($validated['customer_name'])) {
                    $customerId = DB::table('pos_customers')->insertGetId([
                        'name' => $validated['customer_name'],
                        'phone' => $validated['customer_phone'] ?? null,
                        'email' => $validated['customer_email'] ?? null,
                        'tin' => $validated['customer_tin'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $lines = [];
                $subtotal = 0.0;
                $priceOverrides = [];

                foreach ($validated['items'] as $item) {
                    $product = Product::query()->findOrFail($item['product_id']);
                    $quantity = (int) $item['quantity'];
                    $unitPrice = (float) $item['unit_price'];
                    $normalPrice = (float) $product->current_price;

                    if (abs($unitPrice - $normalPrice) > 0.005) {
                        if (! $request->user()?->hasPermission('override_pos_price')) {
                            throw new RuntimeException("You do not have permission to override the price for {$product->name}.");
                        }
                        $priceOverrides[] = [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'normal_price' => $normalPrice,
                            'document_price' => $unitPrice,
                        ];
                    }

                    $lineTotal = $quantity * $unitPrice;
                    $subtotal += $lineTotal;
                    $lines[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'sku' => $product->sku,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ];
                }

                $discount = min((float) ($validated['discount'] ?? 0), $subtotal);
                if ($discount > 0 && ! $request->user()?->hasPermission('discount_pos')) {
                    throw new RuntimeException('You do not have permission to apply a document discount.');
                }

                $total = max(0, $subtotal - $discount);
                $reference = $this->reference($type === 'quotation' ? 'QTN' : 'INV');
                $documentId = DB::table('pos_documents')->insertGetId([
                    'type' => $type,
                    'reference' => $reference,
                    'customer_id' => $customerId,
                    'customer_name' => $validated['customer_name'] ?? null,
                    'customer_phone' => $validated['customer_phone'] ?? null,
                    'customer_email' => $validated['customer_email'] ?? null,
                    'customer_tin' => $validated['customer_tin'] ?? null,
                    'document_date' => now()->toDateString(),
                    'valid_until' => $type === 'quotation'
                        ? ($validated['valid_until'] ?? now()->addDays(7)->toDateString())
                        : null,
                    'due_date' => $type === 'invoice'
                        ? ($validated['due_date'] ?? now()->toDateString())
                        : null,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total' => $total,
                    'status' => 'issued',
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $request->user()?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($lines as $line) {
                    DB::table('pos_document_items')->insert([
                        'pos_document_id' => $documentId,
                        ...$line,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $after = DB::table('pos_documents')->where('id', $documentId)->first();
                $audit->record(
                    $request,
                    $type === 'quotation' ? 'pos.quotation_created' : 'pos.invoice_created',
                    'pos_document',
                    $documentId,
                    $reference,
                    null,
                    $after,
                    [
                        'type' => $type,
                        'price_overrides' => $priceOverrides,
                        'stock_affected' => false,
                        'register_affected' => false,
                    ],
                );

                return $documentId;
            });
        } catch (Throwable $exception) {
            return back()->withErrors(['document' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.pos.documents.show', $documentId)
            ->with('success', ucfirst($type).' created successfully.');
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.now()->format('ymd-His').'-'.strtoupper(Str::random(4));
    }
}
