<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class InstallmentSmsLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_swahili_installment_language_is_saved_and_used_for_later_payment_sms(): void
    {
        $admin = User::create([
            'name' => 'SMS Language Admin',
            'email' => 'sms-language-admin@example.test',
            'phone' => '+255710000099',
            'password' => 'Secure1234',
            'is_admin' => true,
        ]);

        $product = Product::create([
            'name' => 'SMS Test Phone',
            'slug' => 'sms-test-phone',
            'sku' => 'SMS-LANG-001',
            'short_description' => 'SMS test phone',
            'description' => 'SMS test phone',
            'price' => 100000,
            'stock_quantity' => 3,
            'track_stock' => true,
            'condition' => 'new',
            'status' => 'active',
            'featured' => false,
            'merchant_sync_enabled' => false,
        ]);

        $cashId = (int) DB::table('payment_methods')->where('code', 'cash')->value('id');
        Log::spy();

        $this->actingAs($admin)->post('/admin/installments', [
            'customer_name' => 'Swahili Customer',
            'customer_phone' => '0712 345 678',
            'sms_language' => 'sw',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount_type' => 'none',
            'discount_value' => 0,
            'initial_payment' => 20000,
            'payment_method_id' => $cashId,
        ])->assertRedirect();

        $plan = DB::table('installment_plans')->first();
        $this->assertNotNull($plan);
        $this->assertSame('sw', $plan->sms_language);

        $this->post('/admin/installments/'.$plan->reference.'/payments', [
            'amount' => 30000,
            'payment_method_id' => $cashId,
        ])->assertRedirect();

        $expectedMessage = 'Prinx: Malipo ya Tsh 30,000 yamepokelewa kwa ajili ya '.$plan->reference
            .'. Jumla ya Kiasi kilicholipwa ni Tsh 50,000. Kiasi kilichosalia ni Tsh 50,000. Asante';

        Log::shouldHaveReceived('info')->with(
            'Customer SMS suppressed in testing.',
            Mockery::on(fn (array $context): bool => ($context['phone'] ?? null) === '+255712345678'
                && ($context['message'] ?? null) === $expectedMessage)
        )->atLeast()->once();
    }

    public function test_installment_sms_language_accepts_only_english_or_swahili(): void
    {
        $admin = User::create([
            'name' => 'SMS Language Admin',
            'email' => 'sms-language-validation@example.test',
            'phone' => '+255710000098',
            'password' => 'Secure1234',
            'is_admin' => true,
        ]);

        $this->actingAs($admin)->post('/admin/installments', [
            'customer_name' => 'Customer',
            'customer_phone' => '0712 345 678',
            'sms_language' => 'fr',
        ])->assertSessionHasErrors('sms_language');
    }
}
