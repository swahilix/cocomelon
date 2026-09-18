<?php

namespace Tests\Feature;

use App\Models\ConversionEvent;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_conversion_report_as_csv(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        ConversionEvent::create([
            'event_type' => 'checkout_start',
            'session_key' => hash('sha256', 'export-test'),
            'source' => 'instagram',
            'utm_source' => 'instagram',
            'utm_medium' => 'social',
            'utm_campaign' => 'launch_week',
            'value' => 250000,
            'quantity' => 1,
            'event_day' => now()->toDateString(),
        ]);

        $response = $this->actingAs($admin)->get('/admin/conversions/export?days=30');

        $response->assertOk();
        $this->assertStringContainsString(
            'attachment;',
            (string) $response->headers->get('content-disposition')
        );
        $this->assertStringContainsString('.csv', (string) $response->headers->get('content-disposition'));
    }

    public function test_admin_can_filter_online_orders_by_acquisition_source(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $instagram = $this->order('PX-IG-SOURCE', 'instagram');
        $this->order('PX-GOOGLE-SOURCE', 'google');

        $this->actingAs($admin)
            ->get('/admin/orders?source=instagram')
            ->assertOk()
            ->assertSee($instagram->order_number)
            ->assertDontSee('PX-GOOGLE-SOURCE');
    }

    public function test_system_monitor_launch_readiness_endpoint_still_loads(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/system-monitor')
            ->assertOk();
    }

    private function order(string $number, ?string $source): Order
    {
        return Order::create([
            'order_number' => $number,
            'acquisition_source' => $source,
            'customer_name' => 'Growth Test Customer',
            'email' => strtolower($number).'@example.test',
            'phone' => '+255700000000',
            'address_line_1' => 'Test Street',
            'city' => 'Dodoma',
            'country' => 'TZ',
            'subtotal' => 100000,
            'shipping_total' => 0,
            'discount_total' => 0,
            'grand_total' => 100000,
            'currency' => 'TZS',
            'fulfillment_method' => 'delivery',
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);
    }
}
