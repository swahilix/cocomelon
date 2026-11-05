<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\WarrantySmsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WarrantyController extends Controller
{
    private const DURATION_LIMITS = ['days' => 3650, 'months' => 120, 'years' => 10];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $today = now()->toDateString();

        $warranties = DB::table('warranties')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('reference', 'like', '%'.$search.'%')
                        ->orWhere('customer_name', 'like', '%'.$search.'%')
                        ->orWhere('customer_phone', 'like', '%'.$search.'%')
                        ->orWhere('product_name', 'like', '%'.$search.'%')
                        ->orWhere('serial_number', 'like', '%'.$search.'%');
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('ends_on', '>=', $today))
            ->when($status === 'expired', fn ($query) => $query->where('ends_on', '<', $today))
            ->when($status === 'expiring', fn ($query) => $query->whereBetween('ends_on', [$today, now()->addDays(30)->toDateString()]))
            ->latest('id')
            ->limit(200)
            ->get();

        $messages = $this->latestMessages($warranties->pluck('id')->map(fn ($id) => (int) $id)->all());

        return Inertia::render('Admin/Warranties/Index', [
            'warranties' => $warranties->map(fn ($warranty) => $this->serialize($warranty, $messages->get((int) $warranty->id, collect())))->values(),
            'products' => Product::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'sku'])
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                ])
                ->values(),
            'filters' => ['search' => $search, 'status' => $status],
            'today' => $today,
            'summary' => [
                'total' => DB::table('warranties')->count(),
                'active' => DB::table('warranties')->where('ends_on', '>=', $today)->count(),
                'expiring' => DB::table('warranties')->whereBetween('ends_on', [$today, now()->addDays(30)->toDateString()])->count(),
                'expired' => DB::table('warranties')->where('ends_on', '<', $today)->count(),
            ],
        ]);
    }

    public function store(Request $request, WarrantySmsService $sms): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'serial_number' => ['nullable', 'string', 'max:190'],
            'duration_value' => ['required', 'integer', 'min:1'],
            'duration_unit' => ['required', 'in:days,months,years'],
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'starts_on' => ['required', 'date'],
            'sms_language' => ['nullable', 'in:en,sw'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $unit = $validated['duration_unit'];
        $value = (int) $validated['duration_value'];
        if ($value > self::DURATION_LIMITS[$unit]) {
            throw ValidationException::withMessages([
                'duration_value' => 'Warranty duration cannot be longer than '.self::DURATION_LIMITS[$unit].' '.$unit.'.',
            ]);
        }

        $phone = $this->normalizePhoneQuietly($validated['customer_phone']);
        if (! $phone) {
            throw ValidationException::withMessages([
                'customer_phone' => 'Enter a valid Tanzania phone number, for example 0712 345 678.',
            ]);
        }

        $startsOn = CarbonImmutable::parse($validated['starts_on'])->startOfDay();
        $endsOn = $this->endDate($startsOn, $value, $unit);

        if ($endsOn->lt(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'starts_on' => 'This warranty would already have ended on '.$endsOn->format('d M Y').'. Check the start date or duration.',
            ]);
        }

        $product = Product::query()->findOrFail((int) $validated['product_id']);
        $customerName = trim($validated['customer_name']);

        [$warrantyId, $customerCreated] = DB::transaction(function () use ($request, $validated, $product, $phone, $customerName, $value, $unit, $startsOn, $endsOn): array {
            [$customerId, $created] = $this->findOrCreateCustomer($customerName, $phone);
            $now = now();

            $id = DB::table('warranties')->insertGetId([
                'reference' => $this->newReference(),
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'serial_number' => $this->nullableTrim($validated['serial_number'] ?? null),
                'pos_customer_id' => $customerId,
                'customer_name' => $customerName,
                'customer_phone' => $phone,
                'duration_value' => $value,
                'duration_unit' => $unit,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn->toDateString(),
                'sms_language' => $validated['sms_language'] ?? 'en',
                'status' => 'active',
                'notes' => $this->nullableTrim($validated['notes'] ?? null),
                'created_by' => $request->user()?->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [$id, $created];
        });

        $warranty = DB::table('warranties')->where('id', $warrantyId)->first();

        $errors = collect(['thank_you', 'details'])
            ->map(fn (string $type) => $sms->send($warranty, $type))
            ->filter()
            ->unique();

        $message = 'Warranty '.$warranty->reference.' saved. It ends on '.CarbonImmutable::parse($warranty->ends_on)->format('d M Y').'.'
            .($customerCreated ? ' '.$customerName.' was added to Customers.' : '');

        if ($errors->isEmpty()) {
            return back()->with('success', $message.' Thank-you and warranty SMS sent.');
        }

        return back()
            ->with('success', $message)
            ->with('error', 'The warranty was saved, but SMS could not be sent: '.$errors->first().' You can resend it from the warranty list.');
    }

    public function resend(int $warranty, string $type, WarrantySmsService $sms): RedirectResponse
    {
        abort_unless(in_array($type, WarrantySmsService::TYPES, true), 404);

        $record = DB::table('warranties')->where('id', $warranty)->first();
        abort_unless($record, 404);

        if ($type === 'expired' && CarbonImmutable::parse($record->ends_on)->gte(now()->startOfDay())) {
            return back()->with('error', 'This warranty has not ended yet, so the expiry message cannot be sent.');
        }

        $error = $sms->send($record, $type);

        return $error
            ? back()->with('error', 'SMS could not be sent: '.$error)
            : back()->with('success', 'SMS sent to '.$record->customer_phone.'.');
    }

    /**
     * A 12-month warranty starting 5 Nov 2026 covers up to and including 4 Nov 2027.
     */
    public static function endDate(CarbonImmutable $startsOn, int $value, string $unit): CarbonImmutable
    {
        $end = match ($unit) {
            'days' => $startsOn->addDays($value),
            'years' => $startsOn->addYearsNoOverflow($value),
            default => $startsOn->addMonthsNoOverflow($value),
        };

        return $end->subDay();
    }

    /**
     * @return array{0: int, 1: bool} customer id and whether it was newly created
     */
    private function findOrCreateCustomer(string $name, string $phone): array
    {
        $existing = DB::table('pos_customers')
            ->whereNotNull('phone')
            ->get(['id', 'name', 'phone'])
            ->first(fn ($customer): bool => $this->normalizePhoneQuietly($customer->phone) === $phone);

        if ($existing) {
            if (blank($existing->name)) {
                DB::table('pos_customers')->where('id', $existing->id)->update([
                    'name' => $name,
                    'updated_at' => now(),
                ]);
            }

            return [(int) $existing->id, false];
        }

        $id = DB::table('pos_customers')->insertGetId([
            'name' => $name,
            'phone' => $phone,
            'email' => null,
            'tin' => null,
            'notes' => 'Added while registering a warranty.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [(int) $id, true];
    }

    private function latestMessages(array $warrantyIds): Collection
    {
        if ($warrantyIds === []) {
            return collect();
        }

        return DB::table('warranty_messages')
            ->whereIn('warranty_id', $warrantyIds)
            ->orderBy('id')
            ->get(['warranty_id', 'type', 'status', 'error', 'sent_at', 'created_at'])
            ->groupBy('warranty_id')
            ->map(fn ($rows) => $rows->keyBy('type'));
    }

    private function serialize(object $warranty, Collection $messages): array
    {
        $today = now()->startOfDay();
        $starts = CarbonImmutable::parse($warranty->starts_on);
        $ends = CarbonImmutable::parse($warranty->ends_on);
        $expired = $ends->lt($today);

        return [
            'id' => (int) $warranty->id,
            'reference' => $warranty->reference,
            'product_name' => $warranty->product_name,
            'sku' => $warranty->sku,
            'serial_number' => $warranty->serial_number,
            'customer_name' => $warranty->customer_name,
            'customer_phone' => $warranty->customer_phone,
            'duration' => app(WarrantySmsService::class)->durationLabel((int) $warranty->duration_value, (string) $warranty->duration_unit),
            'starts_on' => $starts->toDateString(),
            'ends_on' => $ends->toDateString(),
            'status' => $expired ? 'expired' : ($starts->gt($today) ? 'scheduled' : 'active'),
            'days_left' => $expired ? 0 : (int) $today->diffInDays($ends) + 1,
            'sms_language' => $warranty->sms_language,
            'notes' => $warranty->notes,
            'messages' => collect(WarrantySmsService::TYPES)->mapWithKeys(function (string $type) use ($messages): array {
                $message = $messages->get($type);

                return [$type => $message ? [
                    'status' => $message->status,
                    'error' => $message->error,
                    'sent_at' => $message->sent_at,
                ] : null];
            }),
        ];
    }

    private function newReference(): string
    {
        do {
            $reference = 'WAR-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        } while (DB::table('warranties')->where('reference', $reference)->exists());

        return $reference;
    }

    private function normalizePhoneQuietly(?string $input): ?string
    {
        if (! $input) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $input) ?? '';
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '255'.substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $digits = '255'.$digits;
        }

        return preg_match('/^255\d{9}$/', $digits) ? '+'.$digits : null;
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
