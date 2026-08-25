<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_view_search_and_edit_customers(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/customers', [
            'name' => 'Asha Mushi',
            'phone' => '0712 345 678',
            'email' => 'asha@example.test',
            'tin' => 'TIN-1001',
            'notes' => 'Prefers WhatsApp follow-up.',
        ])->assertRedirect();

        $customer = DB::table('pos_customers')->where('phone', '+255712345678')->first();
        $this->assertNotNull($customer);
        $this->assertSame('Asha Mushi', $customer->name);

        $this->get('/admin/customers?search=Asha')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Customers/Index')
                ->where('customers.0.name', 'Asha Mushi')
                ->where('customers.0.phone', '+255712345678')
                ->where('summary.customers', 1));

        $this->patch('/admin/customers/'.$customer->id, [
            'name' => 'Asha Mushi Updated',
            'phone' => '0712 345 678',
            'email' => 'asha@example.test',
            'tin' => 'TIN-1001',
            'notes' => 'Updated customer note.',
        ])->assertRedirect();

        $this->assertDatabaseHas('pos_customers', [
            'id' => $customer->id,
            'name' => 'Asha Mushi Updated',
            'phone' => '+255712345678',
        ]);
    }

    public function test_installment_can_select_existing_customer_or_create_new_customer(): void
    {
        $admin = $this->admin();
        $product = $this->product('Installment Laptop', 'CUS-LAPTOP', 900000, 3);
        $existingId = DB::table('pos_customers')->insertGetId([
            'name' => 'Existing Customer',
            'phone' => '+255755111222',
            'email' => 'existing@example.test',
            'tin' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post('/admin/installments', [
            'customer_id' => $existingId,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount_type' => 'none',
            'discount_value' => 0,
            'initial_payment' => 0,
        ])->assertRedirect();

        $firstPlan = DB::table('installment_plans')->oldest('id')->first();
        $this->assertSame('Existing Customer', $firstPlan->customer_name);
        $this->assertSame('+255755111222', $firstPlan->customer_phone);
        $this->assertSame(1, DB::table('pos_customers')->where('phone', '+255755111222')->count());

        $this->post('/admin/installments', [
            'customer_id' => null,
            'customer_name' => 'New Installment Customer',
            'customer_phone' => '0766 123 456',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'initial_payment' => 0,
        ])->assertRedirect();

        $this->assertDatabaseHas('pos_customers', [
            'name' => 'New Installment Customer',
            'phone' => '+255766123456',
        ]);
        $this->assertDatabaseHas('installment_plans', [
            'customer_name' => 'New Installment Customer',
            'customer_phone' => '+255766123456',
            'net_total' => 850000,
        ]);
    }

    public function test_customer_phone_login_claims_existing_installment_and_customer_master_record(): void
    {
        $admin = $this->admin();
        $product = $this->product('Reserved Phone', 'CUS-PHONE', 250000, 2);

        $this->actingAs($admin)->post('/admin/installments', [
            'customer_name' => 'Neema Customer',
            'customer_phone' => '0744 555 666',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount_type' => 'none',
            'discount_value' => 0,
            'initial_payment' => 0,
        ])->assertRedirect();

        $plan = DB::table('installment_plans')->first();
        $this->assertNull($plan->user_id);
        $this->post('/logout')->assertRedirect('/');

        $phone = '+255744555666';
        Cache::put('customer-login-otp:'.sha1($phone), [
            'hash' => Hash::make('123456'),
            'attempts' => 0,
        ], now()->addMinutes(5));

        $this->withSession(['customer_login_phone' => $phone])
            ->post('/login/verify', ['code' => '123456'])
            ->assertRedirect('/account');

        $customer = User::query()->where('phone', $phone)->where('is_admin', false)->firstOrFail();
        $this->assertSame('Neema Customer', $customer->name);
        $this->assertSame($customer->id, DB::table('installment_plans')->where('id', $plan->id)->value('user_id'));
        $this->assertSame(1, DB::table('pos_customers')->where('phone', $phone)->count());

        $this->get('/account/installments')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Account/Installments')
                ->where('plans.0.reference', $plan->reference));
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Customer Admin',
            'email' => 'customer-admin@example.test',
            'phone' => '+255710000010',
            'password' => 'Secure1234',
            'is_admin' => true,
        ]);
    }

    private function product(string $name, string $sku, float $price, int $stock): Product
    {
        return Product::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.strtolower($sku),
            'sku' => $sku,
            'short_description' => $name,
            'description' => $name.' description',
            'price' => $price,
            'stock_quantity' => $stock,
            'track_stock' => true,
            'condition' => 'new',
            'status' => 'active',
            'featured' => false,
            'merchant_sync_enabled' => false,
        ]);
    }
}
