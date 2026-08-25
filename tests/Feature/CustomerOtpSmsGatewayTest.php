<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CustomerOtpSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerOtpSmsGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_provider_controls_gateway_and_saved_credentials_are_reused_when_switching_back(): void
    {
        config(['services.customer_sms.force_http' => true]);

        Http::fake([
            'https://messaging-service.co.tz/api/sms/v1/text/single' => Http::response(['messages' => [['status' => 'SENT']]], 200),
            'https://business.momo.tz/api/v3/sms/send' => Http::response(['status' => 'success'], 202),
        ]);

        $admin = User::create([
            'name' => 'SMS Admin',
            'email' => 'sms-admin@example.test',
            'phone' => '+255710000001',
            'password' => 'Secure1234',
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
            ->put('/admin/business-settings', [
                'business_name' => 'Prinx Electronics',
                'sms_enabled' => true,
                'sms_provider' => 'nextsms',
                'sms_api_key' => 'next-basic-token',
                'sms_sender_id' => 'PRINX',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('nextsms', DB::table('business_settings')->where('id', 1)->value('sms_provider'));
        $this->assertSame('next-basic-token', Crypt::decryptString((string) DB::table('business_settings')->where('id', 1)->value('sms_api_key')));

        app(CustomerOtpSmsService::class)->send('+255712345678', '111111');

        $this->actingAs($admin)
            ->put('/admin/business-settings', [
                'business_name' => 'Prinx Electronics',
                'sms_enabled' => true,
                'sms_provider' => 'momo',
                'sms_bearer_token' => 'momo-api-token',
                'sms_sender_id' => 'PRINX',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('momo', DB::table('business_settings')->where('id', 1)->value('sms_provider'));
        $this->assertSame('momo-api-token', Crypt::decryptString((string) DB::table('business_settings')->where('id', 1)->value('sms_bearer_token')));
        $this->assertSame('next-basic-token', Crypt::decryptString((string) DB::table('business_settings')->where('id', 1)->value('sms_api_key')));

        app(CustomerOtpSmsService::class)->send('0712345678', '222222');

        $this->actingAs($admin)
            ->put('/admin/business-settings', [
                'business_name' => 'Prinx Electronics',
                'sms_enabled' => true,
                'sms_provider' => 'nextsms',
                'sms_sender_id' => 'PRINX',
            ])
            ->assertSessionHasNoErrors();

        app(CustomerOtpSmsService::class)->send('712345678', '333333');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://messaging-service.co.tz/api/sms/v1/text/single'
                && $request->hasHeader('Authorization', 'Basic next-basic-token')
                && $request['from'] === 'PRINX'
                && $request['to'] === '255712345678'
                && str_contains((string) $request['text'], '111111');
        });

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://business.momo.tz/api/v3/sms/send'
                && $request->hasHeader('Authorization', 'Bearer momo-api-token')
                && $request['recipient'] === '255712345678'
                && $request['sender_id'] === 'PRINX'
                && str_contains((string) $request['message'], '222222');
        });

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://messaging-service.co.tz/api/sms/v1/text/single'
                && $request->hasHeader('Authorization', 'Basic next-basic-token')
                && str_contains((string) $request['text'], '333333');
        });

        Http::assertSentCount(3);
    }
}
