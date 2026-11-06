<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductCardStyleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Appearance Admin',
            'email' => 'appearance@example.test',
            'password' => 'Password123',
            'is_admin' => true,
        ]);
    }

    public function test_store_uses_the_clean_design_until_one_is_chosen(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/business-settings/storefront')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/BusinessSettings/StorefrontAppearance')
                ->where('available', true)
                ->where('productCardStyle', 'clean')
                ->has('styles', 4)
                ->where('store.product_card_style', 'clean'));
    }

    public function test_admin_can_switch_the_product_card_design(): void
    {
        foreach (['premium', 'trust', 'compact', 'clean'] as $style) {
            $this->actingAs($this->admin)
                ->put('/admin/business-settings/storefront', ['product_card_style' => $style])
                ->assertSessionHasNoErrors()
                ->assertRedirect();

            $this->assertSame($style, DB::table('business_settings')->where('id', 1)->value('product_card_style'));

            $this->actingAs($this->admin)
                ->get('/admin/business-settings/storefront')
                ->assertInertia(fn (Assert $page) => $page
                    ->where('productCardStyle', $style)
                    ->where('store.product_card_style', $style));
        }
    }

    public function test_unknown_designs_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/business-settings/storefront', ['product_card_style' => 'neon'])
            ->assertSessionHasErrors('product_card_style');

        $this->assertNull(DB::table('business_settings')->where('id', 1)->value('product_card_style'));
    }

    public function test_customers_cannot_change_the_design(): void
    {
        $customer = User::create([
            'name' => 'Shopper',
            'email' => 'shopper@example.test',
            'password' => 'Password123',
            'is_admin' => false,
        ]);

        $this->actingAs($customer)->put('/admin/business-settings/storefront', ['product_card_style' => 'premium']);

        $this->assertNull(DB::table('business_settings')->where('id', 1)->value('product_card_style'));
    }
}
