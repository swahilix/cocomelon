<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_signs_in_with_phone_code_and_claims_existing_guest_orders(): void
    {
        $order = $this->order(null, '0712 345 678', 'PX-CUSTOMER-001');

        $this->post('/login', ['phone' => '0712 345 678'])
            ->assertRedirect('/login/verify')
            ->assertSessionHas('customer_login_phone', '+255712345678');

        $key = 'customer-login-otp:'.sha1('+255712345678');
        Cache::put($key, ['hash' => Hash::make('123456'), 'attempts' => 0], now()->addMinutes(5));

        $this->withSession(['customer_login_phone' => '+255712345678'])
            ->post('/login/verify', ['code' => '123456'])
            ->assertRedirect('/account');

        $customer = User::query()->where('phone', '+255712345678')->firstOrFail();
        $this->assertFalse($customer->is_admin);
        $this->assertAuthenticatedAs($customer);
        $this->assertSame($customer->id, $order->fresh()->user_id);

        $this->get('/account')->assertInertia(fn (Assert $page) => $page
            ->component('Store/Account/Dashboard')
            ->where('summary.orders', 1)
            ->where('recentOrders.0.order_number', 'PX-CUSTOMER-001'));

        $this->get('/account/orders')->assertInertia(fn (Assert $page) => $page
            ->component('Store/Account/Orders')
            ->where('orders.0.order_number', 'PX-CUSTOMER-001'));
    }

    public function test_customer_can_only_open_their_own_order_and_account_pages(): void
    {
        $customer = User::create([
            'name' => 'Customer One',
            'email' => 'one@example.test',
            'phone' => '+255713000001',
            'password' => 'unused-password',
            'is_admin' => false,
        ]);
        $other = User::create([
            'name' => 'Customer Two',
            'email' => 'two@example.test',
            'phone' => '+255713000002',
            'password' => 'unused-password',
            'is_admin' => false,
        ]);

        $mine = $this->order($customer->id, '+255713000001', 'PX-MINE-001');
        $theirs = $this->order($other->id, '+255713000002', 'PX-THEIRS-001');

        $this->actingAs($customer);
        $this->get('/account/orders/'.$mine->order_number)->assertOk();
        $this->get('/account/orders/'.$theirs->order_number)->assertNotFound();
        $this->get('/account/payments')->assertInertia(fn (Assert $page) => $page
            ->component('Store/Account/Payments')
            ->where('payments.0.reference', 'PX-MINE-001'));
        $this->get('/account/invoices')->assertInertia(fn (Assert $page) => $page
            ->component('Store/Account/Invoices')
            ->where('invoices.0.reference', 'PX-MINE-001'));
        $this->get('/account/invoices/order/'.$mine->order_number)->assertInertia(fn (Assert $page) => $page
            ->component('Store/Account/InvoiceShow')
            ->where('invoice.reference', 'PX-MINE-001')
            ->where('invoice.prepared_by_name', 'Online Store')
            ->where('invoice.customer_address', 'Test Street, Dodoma, Dodoma, TZ')
            ->where('invoice.order_status', 'confirmed')
            ->where('invoice.payment_method', 'mobile_money'));
        $this->get('/admin')->assertForbidden();
    }

    public function test_staff_email_login_is_separate_from_customer_phone_login(): void
    {
        $admin = User::create([
            'name' => 'Store Admin',
            'email' => 'admin@example.test',
            'phone' => '+255714000001',
            'password' => 'Secure1234',
            'is_admin' => true,
        ]);

        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk();

        $this->post('/admin/login', [
            'email' => 'admin@example.test',
            'password' => 'Secure1234',
            'remember' => false,
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_non_admin_cannot_use_staff_email_login(): void
    {
        User::create([
            'name' => 'Customer',
            'email' => 'customer@example.test',
            'phone' => '+255715000001',
            'password' => 'Secure1234',
            'is_admin' => false,
        ]);

        $this->post('/admin/login', [
            'email' => 'customer@example.test',
            'password' => 'Secure1234',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    private function order(?int $userId, string $phone, string $number): Order
    {
        return Order::create([
            'order_number' => $number,
            'user_id' => $userId,
            'customer_name' => 'Test Customer',
            'email' => 'buyer@example.test',
            'phone' => $phone,
            'address_line_1' => 'Test Street',
            'city' => 'Dodoma',
            'region' => 'Dodoma',
            'country' => 'TZ',
            'subtotal' => 100000,
            'shipping_total' => 0,
            'discount_total' => 0,
            'grand_total' => 100000,
            'currency' => 'TZS',
            'payment_method' => 'mobile_money',
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);
    }
}
