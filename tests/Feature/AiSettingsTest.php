<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'sk-test-abcdefghijklmnopqrstuvwxyz1234';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Settings Admin',
            'email' => 'ai-settings@example.test',
            'password' => 'Password123',
            'is_admin' => true,
        ]);
    }

    public function test_key_and_switches_saved_in_admin_panel_are_encrypted_and_applied(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/business-settings/ai', $this->settings([
                'openai_api_key' => self::KEY,
                'openai_model' => 'gpt-5.4',
                'ai_description_formatter_enabled' => true,
            ]))
            ->assertSessionHasNoErrors();

        $stored = DB::table('business_settings')->where('id', 1)->value('openai_api_key');
        $this->assertNotSame(self::KEY, $stored);
        $this->assertSame(self::KEY, Crypt::decryptString($stored));

        // Settings are applied when the app boots on each request.
        AiSettings::apply();
        $this->assertSame(self::KEY, config('services.openai.api_key'));
        $this->assertSame('gpt-5.4', config('services.openai.model'));
        $this->assertTrue(config('services.openai.product_formatter_enabled'));

        $this->actingAs($this->admin)
            ->get('/admin/business-settings/ai')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/BusinessSettings/AiSettings')
                ->where('openai.key_source', 'admin')
                ->where('openai.key_hint', '••••••••1234')
                ->where('openai.model', 'gpt-5.4'));
    }

    public function test_blank_key_keeps_the_saved_key_and_clear_removes_it(): void
    {
        $this->actingAs($this->admin)->put('/admin/business-settings/ai', $this->settings(['openai_api_key' => self::KEY]))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->put('/admin/business-settings/ai', $this->settings(['openai_api_key' => '']))->assertSessionHasNoErrors();

        $this->assertSame(self::KEY, Crypt::decryptString(DB::table('business_settings')->value('openai_api_key')));

        $this->actingAs($this->admin)->put('/admin/business-settings/ai', $this->settings(['clear_openai_api_key' => true]))->assertSessionHasNoErrors();
        $this->assertNull(DB::table('business_settings')->value('openai_api_key'));
    }

    public function test_switches_can_turn_features_off(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/business-settings/ai', $this->settings([
                'ai_product_intelligence_enabled' => false,
                'upcitemdb_enabled' => false,
            ]))
            ->assertSessionHasNoErrors();

        AiSettings::apply();
        $this->assertFalse(config('services.openai.product_intelligence_enabled'));
        $this->assertFalse(config('services.upcitemdb.enabled'));
    }

    public function test_connection_check_reports_a_working_key(): void
    {
        config(['services.openai.api_key' => self::KEY, 'services.openai.model' => 'gpt-5.4-mini', 'services.openai.base_url' => 'https://api.openai.com/v1']);
        Http::fake(['api.openai.com/v1/models/*' => Http::response(['id' => 'gpt-5.4-mini', 'object' => 'model'])]);

        $this->actingAs($this->admin)
            ->post('/admin/business-settings/ai/test')
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'gpt-5.4-mini'));

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://api.openai.com/v1/models/gpt-5.4-mini'
            && $request->hasHeader('Authorization', 'Bearer '.self::KEY));
    }

    public function test_connection_check_reports_a_rejected_key(): void
    {
        config(['services.openai.api_key' => self::KEY, 'services.openai.base_url' => 'https://api.openai.com/v1']);
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key']], 401)]);

        $this->actingAs($this->admin)
            ->post('/admin/business-settings/ai/test')
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'rejected the API key'));
    }

    public function test_non_admins_cannot_open_ai_settings(): void
    {
        $customer = User::create([
            'name' => 'Customer',
            'email' => 'ai-customer@example.test',
            'password' => 'Password123',
            'is_admin' => false,
        ]);

        $this->actingAs($customer)->get('/admin/business-settings/ai')->assertForbidden();
        $this->actingAs($customer)->put('/admin/business-settings/ai', $this->settings(['openai_api_key' => self::KEY]))->assertForbidden();
    }

    private function settings(array $overrides = []): array
    {
        return [
            'openai_api_key' => '',
            'clear_openai_api_key' => false,
            'openai_model' => '',
            'ai_product_intelligence_enabled' => true,
            'ai_description_formatter_enabled' => false,
            'upcitemdb_enabled' => true,
            'upcitemdb_key' => '',
            'clear_upcitemdb_key' => false,
            ...$overrides,
        ];
    }
}
