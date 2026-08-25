<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InstallmentPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_discounted_multi_item_plan_reserve_stock_and_receive_payments(): void
    {
        $admin = $this->admin();
        $customer = $this->customer('+255712345678');
        $phone = $this->product('Installment Phone', 'INS-PHONE', 100000, 4);
        $charger = $this->product('Fast Charger', 'INS-CHARGER', 30000, 6);
        $cashId = (int) DB::table('payment_methods')->where('code', 'cash')->value('id');

        $this->actingAs($admin)->post('/admin/installments', [
            'customer_name' => 'Installment Customer',
            'customer_phone' => '0712 345 678',
            'items' => [
                ['product_id' => $phone->id, 'quantity' => 1],
                ['product_id' => $charger->id, 'quantity' => 2],
            ],
            'discount_type' => 'fixed',
            'discount_value' => 10000,
            'initial_payment' => 50000,
            'payment_method_id' => $cashId,
            'external_reference' => 'CASH-001',
            'notes' => 'Customer will collect after full payment.',
        ])->assertRedirect();

        $plan = DB::table('installment_plans')->first();
        $this->assertNotNull($plan);
        $this->assertSame($customer->id, $plan->user_id);
        $this->assertSame('+255712345678', $plan->customer_phone);
        $this->assertSame(160000.0, (float) $plan->subtotal);
        $this->assertSame(10000.0, (float) $plan->discount_amount);
        $this->assertSame(150000.0, (float) $plan->net_total);
        $this->assertSame(50000.0, (float) $plan->paid_amount);
        $this->assertSame('active', $plan->status);

        $this->assertSame(3, $phone->fresh()->stock_quantity);
        $this->assertSame(4, $charger->fresh()->stock_quantity);
        $this->assertDatabaseCount('installment_plan_items', 2);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $phone->id,
            'type' => 'installment_reservation',
            'quantity' => -1,
            'source_type' => 'installment_plan',
            'source_id' => $plan->id,
        ]);
        $this->assertDatabaseHas('installment_payments', [
            'installment_plan_id' => $plan->id,
            'amount' => 50000,
            'payment_method_name' => 'Cash',
            'sms_status' => 'sent',
        ]);

        $this->actingAs($customer)
            ->get('/account/installments')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Account/Installments')
                ->where('plans.0.reference', $plan->reference)
                ->where('plans.0.remaining_amount', 100000));

        $this->get('/account/installments/'.$plan->reference)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Account/InstallmentShow')
                ->where('plan.reference', $plan->reference)
                ->where('plan.paid_amount', 50000)
                ->where('plan.remaining_amount', 100000)
                ->has('items', 2)
                ->has('payments', 1));

        $this->get('/account/payments')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Account/Payments')
                ->where('payments.0.source', 'Installment payment')
                ->where('payments.0.reference', $plan->reference)
                ->where('payments.0.amount', 50000));

        $this->actingAs($admin)->post('/admin/installments/'.$plan->reference.'/payments', [
            'amount' => 100000,
            'payment_method_id' => $cashId,
            'external_reference' => 'CASH-002',
        ])->assertRedirect();

        $plan = DB::table('installment_plans')->where('id', $plan->id)->first();
        $this->assertSame(150000.0, (float) $plan->paid_amount);
        $this->assertSame('paid', $plan->status);
        $this->assertNotNull($plan->paid_at);
        $this->assertDatabaseCount('installment_payments', 2);
        $this->assertDatabaseHas('installment_payments', [
            'installment_plan_id' => $plan->id,
            'amount' => 100000,
            'sms_status' => 'sent',
        ]);

        $this->post('/admin/installments/'.$plan->reference.'/release')
            ->assertRedirect();

        $released = DB::table('installment_plans')->where('id', $plan->id)->first();
        $this->assertSame('released', $released->status);
        $this->assertNotNull($released->released_at);
        $this->assertSame(3, $phone->fresh()->stock_quantity, 'Releasing already-reserved stock must not deduct inventory twice.');
    }

    public function test_unpaid_active_plan_can_be_cancelled_and_reserved_stock_is_restored(): void
    {
        $admin = $this->admin();
        $product = $this->product('Reserved Laptop', 'INS-LAPTOP', 800000, 2);

        $this->actingAs($admin)->post('/admin/installments', [
            'customer_name' => 'Walk In Customer',
            'customer_phone' => '0755 111 222',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount_type' => 'none',
            'discount_value' => 0,
            'initial_payment' => 0,
        ])->assertRedirect();

        $plan = DB::table('installment_plans')->first();
        $this->assertSame(1, $product->fresh()->stock_quantity);

        $this->delete('/admin/installments/'.$plan->reference)->assertRedirect('/admin/installments');

        $this->assertSame('cancelled', DB::table('installment_plans')->where('id', $plan->id)->value('status'));
        $this->assertSame(2, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'installment_cancellation',
            'quantity' => 1,
            'source_id' => $plan->id,
        ]);
    }

    public function test_plan_with_existing_payment_cannot_be_cancelled_or_released_early(): void
    {
        $admin = $this->admin();
        $product = $this->product('Tablet', 'INS-TABLET', 200000, 3);
        $cashId = (int) DB::table('payment_methods')->where('code', 'cash')->value('id');

        $this->actingAs($admin)->post('/admin/installments', [
            'customer_name' => 'Customer',
            'customer_phone' => '0719 000 001',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount_type' => 'none',
            'discount_value' => 0,
            'initial_payment' => 25000,
            'payment_method_id' => $cashId,
        ]);

        $plan = DB::table('installment_plans')->first();

        $this->delete('/admin/installments/'.$plan->reference)
            ->assertSessionHasErrors('cancel');
        $this->post('/admin/installments/'.$plan->reference.'/release')
            ->assertSessionHasErrors('release');

        $this->assertSame('active', DB::table('installment_plans')->where('id', $plan->id)->value('status'));
        $this->assertSame(2, $product->fresh()->stock_quantity);
    }

    public function test_customer_installment_pages_require_login_and_are_private(): void
    {
        $this->get('/account/installments')->assertRedirect('/login');

        $admin = $this->admin();
        $first = $this->customer('+255713000001');
        $second = $this->customer('+255713000002', 'second@example.test');
        $product = $this->product('Headphones', 'INS-HEAD', 50000, 3);

        $this->actingAs($admin)->post('/admin/installments', [
            'customer_name' => 'First Customer',
            'customer_phone' => '+255713000001',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount_type' => 'none',
            'discount_value' => 0,
            'initial_payment' => 0,
        ]);

        $plan = DB::table('installment_plans')->first();
        $this->actingAs($second)->get('/account/installments/'.$plan->reference)->assertNotFound();
        $this->actingAs($first)->get('/account/installments/'.$plan->reference)->assertOk();
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Installment Admin',
            'email' => 'installments-admin@example.test',
            'phone' => '+255710000001',
            'password' => 'Secure1234',
            'is_admin' => true,
        ]);
    }

    private function customer(string $phone, string $email = 'installment-customer@example.test'): User
    {
        return User::create([
            'name' => 'Installment Customer',
            'email' => $email,
            'phone' => $phone,
            'password' => 'unused-password',
            'is_admin' => false,
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
