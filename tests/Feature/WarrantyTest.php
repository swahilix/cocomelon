<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CustomerOtpSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class WarrantyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_saving_a_warranty_adds_a_new_customer_and_sends_thank_you_and_details_sms(): void
    {
        Carbon::setTestNow('2026-11-05 10:00:00');
        $productId = $this->product('Samsung Galaxy A55');

        $this->actingAs($this->admin())
            ->post('/admin/warranties', [
                'product_id' => $productId,
                'serial_number' => 'IMEI-123',
                'duration_value' => 12,
                'duration_unit' => 'months',
                'customer_name' => 'Asha Juma',
                'customer_phone' => '0712 345 678',
                'starts_on' => '2026-11-05',
                'sms_language' => 'en',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $customer = DB::table('pos_customers')->where('phone', '+255712345678')->first();
        $this->assertNotNull($customer);
        $this->assertSame('Asha Juma', $customer->name);

        $warranty = DB::table('warranties')->first();
        $this->assertSame((int) $customer->id, (int) $warranty->pos_customer_id);
        $this->assertSame('Samsung Galaxy A55', $warranty->product_name);
        $this->assertSame('2026-11-05', substr((string) $warranty->starts_on, 0, 10));
        $this->assertSame('2027-11-04', substr((string) $warranty->ends_on, 0, 10));

        $messages = DB::table('warranty_messages')->where('warranty_id', $warranty->id)->orderBy('id')->get();
        $this->assertSame(['thank_you', 'details'], $messages->pluck('type')->all());
        $this->assertSame(['sent', 'sent'], $messages->pluck('status')->all());
        $this->assertStringContainsString('Thank you Asha', $messages[0]->message);
        $this->assertStringContainsString('initiated on 05 Nov 2026', $messages[1]->message);
        $this->assertStringContainsString('will end on 04 Nov 2027', $messages[1]->message);
        $this->assertStringContainsString('IMEI-123', $messages[1]->message);
    }

    public function test_existing_customer_is_reused_when_phone_matches_in_another_format(): void
    {
        Carbon::setTestNow('2026-11-05 10:00:00');
        $productId = $this->product('HP Laptop');
        DB::table('pos_customers')->insert([
            'name' => 'Baraka Mushi',
            'phone' => '+255754000111',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/warranties', [
                'product_id' => $productId,
                'duration_value' => 1,
                'duration_unit' => 'years',
                'customer_name' => 'Baraka',
                'customer_phone' => '754 000 111',
                'starts_on' => '2026-11-01',
                'sms_language' => 'sw',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('pos_customers')->count());
        $this->assertSame('Baraka Mushi', DB::table('pos_customers')->value('name'));

        $details = DB::table('warranty_messages')->where('type', 'details')->value('message');
        $this->assertStringContainsString('imeanza tarehe 01/11/2026', $details);
        $this->assertStringContainsString('itaisha tarehe 31/10/2027', $details);
    }

    public function test_warranty_that_has_already_ended_is_rejected(): void
    {
        Carbon::setTestNow('2026-11-05 10:00:00');

        $this->actingAs($this->admin())
            ->post('/admin/warranties', [
                'product_id' => $this->product('Old Phone'),
                'duration_value' => 3,
                'duration_unit' => 'months',
                'customer_name' => 'Late Entry',
                'customer_phone' => '0712000000',
                'starts_on' => '2026-01-01',
            ])
            ->assertSessionHasErrors('starts_on');

        $this->assertSame(0, DB::table('warranties')->count());
        $this->assertSame(0, DB::table('pos_customers')->count());
    }

    public function test_warranty_is_saved_even_when_sms_fails(): void
    {
        Carbon::setTestNow('2026-11-05 10:00:00');
        $this->mock(CustomerOtpSmsService::class)
            ->shouldReceive('sendMessage')
            ->andThrow(new RuntimeException('SMS is not configured yet.'));

        $this->actingAs($this->admin())
            ->post('/admin/warranties', [
                'product_id' => $this->product('Router'),
                'duration_value' => 6,
                'duration_unit' => 'months',
                'customer_name' => 'Neema',
                'customer_phone' => '0688111222',
                'starts_on' => '2026-11-05',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('error');

        $this->assertSame(1, DB::table('warranties')->count());
        $this->assertSame(['failed', 'failed'], DB::table('warranty_messages')->orderBy('id')->pluck('status')->all());
    }

    public function test_expiry_command_sends_warranty_ended_sms_once(): void
    {
        Carbon::setTestNow('2026-11-05 10:00:00');
        $this->actingAs($this->admin())
            ->post('/admin/warranties', [
                'product_id' => $this->product('Smart TV'),
                'duration_value' => 7,
                'duration_unit' => 'days',
                'customer_name' => 'Juma Ali',
                'customer_phone' => '0765123456',
                'starts_on' => '2026-11-05',
            ])
            ->assertSessionHasNoErrors();

        $warranty = DB::table('warranties')->first();
        $this->assertSame('2026-11-11', substr((string) $warranty->ends_on, 0, 10));

        // Last day of cover: nothing is sent yet.
        Carbon::setTestNow('2026-11-11 09:00:00');
        $this->artisan('warranties:notify-expired')->assertSuccessful();
        $this->assertSame(0, DB::table('warranty_messages')->where('type', 'expired')->count());

        // Day after it ends: the expiry SMS is sent and the warranty is marked expired.
        Carbon::setTestNow('2026-11-12 09:00:00');
        $this->artisan('warranties:notify-expired')->assertSuccessful();
        $this->artisan('warranties:notify-expired')->assertSuccessful();

        $expired = DB::table('warranty_messages')->where('type', 'expired')->get();
        $this->assertCount(1, $expired);
        $this->assertSame('sent', $expired[0]->status);
        $this->assertStringContainsString('ended on 11 Nov 2026', $expired[0]->message);
        $this->assertSame('expired', DB::table('warranties')->value('status'));
        $this->assertNotNull(DB::table('warranties')->value('expiry_notified_at'));
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Warranty Admin',
            'email' => 'warranty-admin@example.test',
            'password' => 'Password123',
            'is_admin' => true,
        ]);
    }

    private function product(string $name): int
    {
        return DB::table('products')->insertGetId([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'sku' => strtoupper(str($name)->slug()->toString()),
            'description' => $name,
            'price' => 100000,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
