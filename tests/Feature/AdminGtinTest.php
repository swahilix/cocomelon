<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminGtinTest extends TestCase
{
    use RefreshDatabase;

    public function test_gtin_queue_lists_only_products_without_gtin_and_saves_valid_codes(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.test',
            'password' => 'Password123',
            'is_admin' => true,
        ]);

        $category = Category::create(['name' => 'Phones', 'is_active' => true]);
        $missing = $this->product($category, 'Missing GTIN Phone', 'PHONE-MISSING');
        $complete = $this->product($category, 'Complete Phone', 'PHONE-COMPLETE', '5901234123457');

        $this->actingAs($admin)
            ->get('/admin/gtin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Products/Gtin')
                ->where('missingCount', 1)
                ->where('withGtinCount', 1)
                ->has('products', 1)
                ->where('products.0.id', $missing->id)
            );

        $this->actingAs($admin)
            ->patch('/admin/gtin/'.$missing->slug, ['gtin' => '4006381333931'])
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'id' => $missing->id,
            'gtin' => '4006381333931',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $complete->id,
            'gtin' => '5901234123457',
        ]);
    }

    public function test_gtin_scanner_rejects_bad_check_digits_and_duplicates(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin2@example.test',
            'password' => 'Password123',
            'is_admin' => true,
        ]);

        $category = Category::create(['name' => 'Accessories', 'is_active' => true]);
        $existing = $this->product($category, 'Existing Product', 'EXISTING', '5901234123457');
        $target = $this->product($category, 'Target Product', 'TARGET');

        $this->actingAs($admin)
            ->from('/admin/gtin')
            ->patch('/admin/gtin/'.$target->slug, ['gtin' => '4006381333932'])
            ->assertRedirect('/admin/gtin')
            ->assertSessionHasErrors('gtin');

        $this->assertNull($target->fresh()->gtin);

        $this->actingAs($admin)
            ->from('/admin/gtin')
            ->patch('/admin/gtin/'.$target->slug, ['gtin' => $existing->gtin])
            ->assertRedirect('/admin/gtin')
            ->assertSessionHasErrors('gtin');

        $this->assertNull($target->fresh()->gtin);
    }

    private function product(Category $category, string $name, string $sku, ?string $gtin = null): Product
    {
        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'sku' => $sku,
            'description' => 'Test product description.',
            'price' => 100000,
            'stock_quantity' => 1,
            'status' => 'active',
            'condition' => 'new',
            'merchant_sync_enabled' => false,
            'gtin' => $gtin,
        ]);
    }
}
