<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'category' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:posted,voided'],
        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : now()->endOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $base = DB::table('expenses')
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->leftJoin('users', 'users.id', '=', 'expenses.created_by')
            ->leftJoin('users as void_users', 'void_users.id', '=', 'expenses.voided_by')
            ->whereBetween('expenses.expense_date', [$from->toDateString(), $to->toDateString()])
            ->when(! empty($validated['category']), fn ($q) => $q->where('expenses.expense_category_id', $validated['category']))
            ->when(! empty($validated['status']), fn ($q) => $q->where('expenses.status', $validated['status']))
            ->when(! empty($validated['search']), function ($q) use ($validated): void {
                $search = '%'.trim($validated['search']).'%';
                $q->where(function ($inner) use ($search): void {
                    $inner->where('expenses.reference', 'like', $search)
                        ->orWhere('expenses.title', 'like', $search)
                        ->orWhere('expenses.description', 'like', $search)
                        ->orWhere('expenses.payee', 'like', $search)
                        ->orWhere('expenses.payment_reference', 'like', $search);
                });
            });

        $expenses = (clone $base)
            ->select(
                'expenses.*',
                DB::raw('COALESCE(expense_categories.name, expenses.category) as display_category'),
                'users.name as created_by_name',
                'void_users.name as voided_by_name',
            )
            ->orderByDesc('expenses.expense_date')
            ->orderByDesc('expenses.id')
            ->limit(500)
            ->get();

        $posted = (clone $base)->where('expenses.status', 'posted');
        $total = (float) (clone $posted)->sum('expenses.amount');
        $count = (clone $posted)->count();
        $average = $count > 0 ? $total / $count : 0;

        $categoryBreakdown = (clone $posted)
            ->selectRaw('COALESCE(expense_categories.name, expenses.category) as category_name, SUM(expenses.amount) as total, COUNT(expenses.id) as expense_count')
            ->groupBy('category_name')
            ->orderByDesc('total')
            ->get();

        $monthly = DB::table('expenses')
            ->where('status', 'posted')
            ->where('expense_date', '>=', now()->subMonths(11)->startOfMonth()->toDateString())
            ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as month_key, SUM(amount) as total")
            ->groupBy('month_key')
            ->orderBy('month_key')
            ->get();

        return Inertia::render('Admin/Expenses/Index', [
            'expenses' => $expenses,
            'categories' => DB::table('expense_categories')->orderBy('sort_order')->orderBy('name')->get(),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'category' => $validated['category'] ?? '',
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
            ],
            'summary' => [
                'total' => $total,
                'count' => $count,
                'average' => $average,
                'largest' => (float) ((clone $posted)->max('expenses.amount') ?? 0),
                'recurring' => (clone $posted)->where('expenses.is_recurring', true)->count(),
            ],
            'categoryBreakdown' => $categoryBreakdown,
            'monthly' => $monthly,
            'largestExpenses' => (clone $posted)
                ->select('expenses.id', 'expenses.reference', 'expenses.title', 'expenses.description', 'expenses.amount', 'expenses.expense_date', DB::raw('COALESCE(expense_categories.name, expenses.category) as display_category'))
                ->orderByDesc('expenses.amount')->limit(10)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateExpense($request);
        $category = DB::table('expense_categories')->where('id', $validated['expense_category_id'])->where('is_active', true)->first();
        abort_unless($category, 422, 'Selected expense category is inactive or unavailable.');

        $receiptPath = null;
        $receiptOriginalName = null;
        if ($request->hasFile('receipt')) {
            $receipt = $request->file('receipt');
            $receiptPath = $receipt->store('expense-receipts');
            $receiptOriginalName = $receipt->getClientOriginalName();
        }

        DB::table('expenses')->insert([
            'reference' => $this->reference(),
            'expense_date' => $validated['expense_date'],
            'category' => $category->name,
            'expense_category_id' => $category->id,
            'title' => $validated['title'],
            'description' => $validated['title'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $validated['payment_reference'] ?? null,
            'payee' => $validated['payee'] ?? null,
            'receipt_path' => $receiptPath,
            'receipt_original_name' => $receiptOriginalName,
            'is_recurring' => (bool) ($validated['is_recurring'] ?? false),
            'recurring_interval' => ! empty($validated['is_recurring']) ? ($validated['recurring_interval'] ?? null) : null,
            'recurring_until' => ! empty($validated['is_recurring']) ? ($validated['recurring_until'] ?? null) : null,
            'status' => 'posted',
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Expense recorded successfully.');
    }

    public function void(Request $request, int $expense): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $affected = DB::table('expenses')->where('id', $expense)->where('status', 'posted')->update([
            'status' => 'voided',
            'voided_at' => now(),
            'voided_by' => $request->user()?->id,
            'void_reason' => $validated['reason'],
            'updated_at' => now(),
        ]);

        if (! $affected) {
            return back()->withErrors(['void' => 'Expense was not found or has already been voided.']);
        }

        return back()->with('success', 'Expense voided. Original financial record was preserved.');
    }

    public function receipt(int $expense): BinaryFileResponse
    {
        $record = DB::table('expenses')->where('id', $expense)->first();
        abort_unless($record && $record->receipt_path, 404);
        abort_unless(Storage::exists($record->receipt_path), 404);

        return response()->download(
            Storage::path($record->receipt_path),
            $record->receipt_original_name ?: basename($record->receipt_path)
        );
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::table('expense_categories')->insert([
            'name' => trim($validated['name']),
            'slug' => Str::slug($validated['name']).'-'.strtolower(Str::random(4)),
            'description' => $validated['description'] ?? null,
            'is_active' => true,
            'sort_order' => (int) DB::table('expense_categories')->max('sort_order') + 1,
            'created_by' => $request->user()?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Expense category created.');
    }

    public function updateCategory(Request $request, int $category): RedirectResponse
    {
        $record = DB::table('expense_categories')->where('id', $category)->first();
        abort_unless($record, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')->ignore($category)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);

        DB::table('expense_categories')->where('id', $category)->update([
            'name' => trim($validated['name']),
            'slug' => Str::slug($validated['name']).'-'.$category,
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) $validated['is_active'],
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Expense category updated. Existing expense snapshots were preserved.');
    }

    private function validateExpense(Request $request): array
    {
        return $request->validate([
            'expense_date' => ['required', 'date'],
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,mobile_money,bank,card,other'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'payee' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'is_recurring' => ['nullable', 'boolean'],
            'recurring_interval' => ['nullable', 'required_if:is_recurring,1', 'in:weekly,monthly,quarterly,yearly'],
            'recurring_until' => ['nullable', 'date', 'after_or_equal:expense_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function reference(): string
    {
        return 'EXP-'.now()->format('ymd-His').'-'.strtoupper(Str::random(4));
    }
}
