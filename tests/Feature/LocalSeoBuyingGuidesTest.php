<?php

namespace Tests\Feature;

use App\Models\BuyingGuide;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalSeoBuyingGuidesTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_store_and_published_buying_guides_are_public_and_in_sitemap(): void
    {
        $category = Category::create([
            'name' => 'Power Banks',
            'type' => 'product',
            'description' => 'Portable power products.',
            'is_active' => true,
            'meta_title' => 'Power Banks in Dodoma | Prinx Tech',
            'meta_description' => 'Shop power banks in Dodoma from Prinx Tech.',
        ]);

        $guide = BuyingGuide::create([
            'category_id' => $category->id,
            'title' => 'How to choose a power bank',
            'excerpt' => 'A practical guide to capacity, charging speed and compatibility.',
            'body' => str_repeat('Choose capacity and charging standards based on the devices you use every day. ', 3),
            'status' => 'published',
        ]);

        $this->get('/electronics-shop-dodoma')
            ->assertOk()
            ->assertSee('Store/LocalStore', false);

        $this->get('/guides')
            ->assertOk()
            ->assertSee('How to choose a power bank');

        $this->get('/guides/'.$guide->slug)
            ->assertOk()
            ->assertSee('capacity, charging speed and compatibility');

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('/electronics-shop-dodoma', false)
            ->assertSee('/guides/'.$guide->slug, false);
    }

    public function test_draft_buying_guide_is_not_public(): void
    {
        $guide = BuyingGuide::create([
            'title' => 'Unpublished guide',
            'body' => str_repeat('Draft content that should not appear publicly. ', 3),
            'status' => 'draft',
        ]);

        $this->get('/guides/'.$guide->slug)->assertNotFound();
    }
}
