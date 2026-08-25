<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CustomerOtpSmsService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstallmentStoreController extends Controller
{
    public function __invoke(
        Request $request,
        InstallmentController $installments,
        InventoryService $inventory,
        CustomerOtpSmsService $sms,
    ): RedirectResponse {
        $selection = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:pos_customers,id'],
        ]);

        $customerId = (int) ($selection['customer_id'] ?? 0);
        if ($customerId > 0) {
            $customer = DB::table('pos_customers')->where('id', $customerId)->first();
            abort_unless($customer, 404);

            if (blank($customer->phone)) {
                throw ValidationException::withMessages([
                    'customer_id' => 'This customer has no phone number. Add a phone in Customers before creating an installment plan.',
                ]);
            }

            $phone = $this->normalizePhone((string) $customer->phone, 'customer_id');
            $request->merge([
                'customer_name' => $customer->name,
                'customer_phone' => $phone,
            ]);

            return $installments->store($request, $inventory, $sms);
        }

        $newCustomer = $request->validate([
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_phone' => ['required', 'string', 'max:30'],
        ]);

        $phone = $this->normalizePhone($newCustomer['customer_phone'], 'customer_phone');
        $existing = DB::table('pos_customers')
            ->whereNotNull('phone')
            ->get(['id', 'phone'])
            ->first(fn ($customer): bool => $this->normalizePhoneQuietly($customer->phone) === $phone);

        if (! $existing) {
            DB::table('pos_customers')->insert([
                'name' => trim($newCustomer['customer_name']),
                'phone' => $phone,
                'email' => null,
                'tin' => null,
                'notes' => 'Added while creating an installment plan.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $request->merge(['customer_phone' => $phone]);

        return $installments->store($request, $inventory, $sms);
    }

    private function normalizePhone(string $input, string $field): string
    {
        $phone = $this->normalizePhoneQuietly($input);
        if (! $phone) {
            throw ValidationException::withMessages([
                $field => 'Enter a valid Tanzania phone number, for example 0712 345 678.',
            ]);
        }

        return $phone;
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
}
