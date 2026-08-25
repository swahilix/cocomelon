<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_customers')) {
            return;
        }

        // Normalize phone numbers already saved by POS where possible.
        DB::table('pos_customers')->orderBy('id')->chunkById(200, function ($customers): void {
            foreach ($customers as $customer) {
                $phone = $this->normalizePhone($customer->phone ?? null);
                if ($phone && $phone !== $customer->phone) {
                    DB::table('pos_customers')->where('id', $customer->id)->update([
                        'phone' => $phone,
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        if (Schema::hasTable('users')) {
            DB::table('users')
                ->where('is_admin', false)
                ->whereNotNull('phone')
                ->orderBy('id')
                ->chunkById(200, function ($users): void {
                    foreach ($users as $user) {
                        $email = (string) ($user->email ?? '');
                        $this->ensureCustomer(
                            (string) ($user->name ?: 'Prinx Customer'),
                            $user->phone,
                            str_ends_with($email, '@phone.prinx.local') ? null : ($email ?: null),
                            null,
                        );
                    }
                });
        }

        if (Schema::hasTable('orders')) {
            DB::table('orders')
                ->whereNotNull('phone')
                ->orderBy('id')
                ->chunkById(200, function ($orders): void {
                    foreach ($orders as $order) {
                        $this->ensureCustomer(
                            (string) ($order->customer_name ?: 'Prinx Customer'),
                            $order->phone,
                            $order->email ?: null,
                            'Added from online order history.',
                        );
                    }
                });
        }

        if (Schema::hasTable('installment_plans')) {
            DB::table('installment_plans')
                ->whereNotNull('customer_phone')
                ->orderBy('id')
                ->chunkById(200, function ($plans): void {
                    foreach ($plans as $plan) {
                        $this->ensureCustomer(
                            (string) ($plan->customer_name ?: 'Prinx Customer'),
                            $plan->customer_phone,
                            null,
                            'Added from installment history.',
                        );
                    }
                });
        }
    }

    private function ensureCustomer(string $name, ?string $phone, ?string $email, ?string $notes): void
    {
        $normalized = $this->normalizePhone($phone);
        if (! $normalized) {
            return;
        }

        $existing = DB::table('pos_customers')
            ->whereNotNull('phone')
            ->get(['id', 'phone', 'name', 'email'])
            ->first(fn ($customer): bool => $this->normalizePhone($customer->phone) === $normalized);

        if ($existing) {
            $updates = [];
            if (blank($existing->name) && filled($name)) {
                $updates['name'] = $name;
            }
            if (blank($existing->email) && filled($email)) {
                $updates['email'] = $email;
            }
            if ($updates !== []) {
                $updates['updated_at'] = now();
                DB::table('pos_customers')->where('id', $existing->id)->update($updates);
            }
            return;
        }

        DB::table('pos_customers')->insert([
            'name' => trim($name) ?: 'Prinx Customer',
            'phone' => $normalized,
            'email' => $email ?: null,
            'tin' => null,
            'notes' => $notes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function normalizePhone(?string $input): ?string
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

    public function down(): void
    {
        // Data synchronization is intentionally not reversed. The customer
        // records may already be referenced by POS sales and documents.
    }
};
