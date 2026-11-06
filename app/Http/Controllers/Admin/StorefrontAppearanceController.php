<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\ProductCardStyle;
use App\Support\StorefrontFont;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontAppearanceController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/BusinessSettings/StorefrontAppearance', [
            'available' => ProductCardStyle::available(),
            'productCardStyle' => ProductCardStyle::current(),
            'styles' => collect(ProductCardStyle::OPTIONS)
                ->map(fn (array $style, string $key): array => ['value' => $key, ...$style])
                ->values()
                ->all(),
            'sampleProducts' => $this->sampleProducts(),
            'fontsAvailable' => StorefrontFont::available(),
            'storefrontFont' => StorefrontFont::current(),
            'fonts' => StorefrontFont::forAdmin(),
            'fontsStylesheet' => StorefrontFont::stylesheet(...StorefrontFont::keys()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_card_style' => ['sometimes', 'required', 'string', Rule::in(ProductCardStyle::keys())],
            'storefront_font' => ['sometimes', 'required', 'string', Rule::in(StorefrontFont::keys())],
        ], [
            'product_card_style.in' => 'Choose one of the card designs shown.',
            'storefront_font.in' => 'Choose one of the fonts shown.',
        ]);

        if ($validated === []) {
            throw ValidationException::withMessages(['product_card_style' => 'Choose a card design or a font to save.']);
        }

        if ((isset($validated['product_card_style']) && ! ProductCardStyle::available())
            || (isset($validated['storefront_font']) && ! StorefrontFont::available())) {
            throw ValidationException::withMessages(['product_card_style' => 'Run the latest database migrations before changing the storefront appearance.']);
        }

        $payload = [...$validated, 'updated_at' => now()];

        if (DB::table('business_settings')->where('id', 1)->exists()) {
            DB::table('business_settings')->where('id', 1)->update($payload);
        } else {
            DB::table('business_settings')->insert(['id' => 1, ...$payload, 'created_at' => now()]);
        }

        $saved = collect([
            isset($validated['product_card_style']) ? 'card design "'.ProductCardStyle::OPTIONS[$validated['product_card_style']]['name'].'"' : null,
            isset($validated['storefront_font']) ? 'font "'.StorefrontFont::OPTIONS[$validated['storefront_font']]['name'].'"' : null,
        ])->filter()->implode(' and ');

        return back()->with('success', 'Saved. The store now uses the '.$saved.'.');
    }

    /**
     * Two real products for the live preview (one on sale if possible), so the
     * admin sees the designs with the store's own photos and prices.
     */
    private function sampleProducts(): array
    {
        $query = fn () => Product::query()
            ->active()
            ->with(['brand', 'category'])
            ->withCount(['variants as active_variants_count' => fn ($variants) => $variants->active()])
            ->withCount(['reviews as approved_reviews_count' => fn ($reviews) => $reviews->approved()])
            ->withAvg(['reviews as approved_reviews_average' => fn ($reviews) => $reviews->approved()], 'rating');

        $onSale = $query()
            ->whereNotNull('sale_price')
            ->where('sale_price', '>', 0)
            ->whereColumn('sale_price', '<', 'price')
            ->latest('id')
            ->first();

        $others = $query()
            ->when($onSale, fn ($products) => $products->whereKeyNot($onSale->id))
            ->orderByDesc('featured')
            ->latest('id')
            ->take($onSale ? 1 : 2)
            ->get();

        return collect([$onSale])->filter()->concat($others)
            ->map(fn (Product $product): array => $product->toArray())
            ->values()
            ->all();
    }
}
