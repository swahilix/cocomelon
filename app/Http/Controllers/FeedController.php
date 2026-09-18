<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Services\GoogleMerchantService;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class FeedController extends Controller
{
    public function merchant(GoogleMerchantService $merchant): Response
    {
        $products = Product::with(['brand','category'])->merchantEligible()->get();
        $esc = fn ($value) => htmlspecialchars((string)$value, ENT_XML1|ENT_QUOTES, 'UTF-8');
        $items = $products->map(function ($p) use ($esc, $merchant) {
            $availability = $p->in_stock ? 'in_stock' : 'out_of_stock';
            $sale = ($p->current_price < (float)$p->price) ? '<g:sale_price>'.$esc(number_format($p->current_price,2,'.','').' '.config('store.currency')).'</g:sale_price>' : '';
            $extra = collect($merchant->merchantGalleryUrls($p))->take(10)->map(fn ($image) => '<g:additional_image_link>'.$esc($image).'</g:additional_image_link>')->implode('');
            return '<item><g:id>'.$esc($p->sku).'</g:id><title>'.$esc($p->name).'</title><description>'.$esc(Str::limit($p->plain_description,5000,'')).'</description><link>'.$esc(route('products.show',$p)).'</link><g:image_link>'.$esc($merchant->merchantImageUrl($p)).'</g:image_link>'.$extra.'<g:availability>'.$availability.'</g:availability><g:condition>'.$esc($p->condition).'</g:condition><g:price>'.$esc(number_format((float)$p->price,2,'.','').' '.config('store.currency')).'</g:price>'.$sale.($p->brand?'<g:brand>'.$esc($p->brand->name).'</g:brand>':'').($p->gtin?'<g:gtin>'.$esc($p->gtin).'</g:gtin>':'').($p->mpn?'<g:mpn>'.$esc($p->mpn).'</g:mpn>':'').($p->google_product_category?'<g:google_product_category>'.$esc($p->google_product_category).'</g:google_product_category>':'').(($p->product_type || $p->category)?'<g:product_type>'.$esc($p->product_type ?: 'Electronics > '.$p->category->name).'</g:product_type>':'').'</item>';
        })->implode('');
        $xml = '<?xml version="1.0" encoding="UTF-8"?><rss xmlns:g="http://base.google.com/ns/1.0" version="2.0"><channel><title>Prinx Tech Products</title><link>'.e(config('app.url')).'</link><description>Prinx Tech product catalogue</description>'.$items.'</channel></rss>';
        return response($xml,200,['Content-Type'=>'application/xml; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $escape = fn ($value) => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $urls = collect([
            ['loc' => route('home'), 'lastmod' => now(), 'images' => []],
            ['loc' => route('catalog'), 'lastmod' => now(), 'images' => []],
            ['loc' => route('services'), 'lastmod' => now(), 'images' => []],
            ...collect(['about', 'contact', 'faq', 'shipping', 'returns', 'warranty', 'privacy', 'terms'])
                ->map(fn (string $page) => ['loc' => route('pages.show', $page), 'lastmod' => now(), 'images' => []])
                ->all(),
        ])
            ->merge(Category::productType()->active()->get()->map(fn (Category $category) => [
                'loc' => route('categories.products', $category),
                'lastmod' => $category->updated_at,
                'images' => [[
                    'loc' => $category->image_url,
                    'title' => $category->name.' product category',
                    'caption' => $category->description ?: 'Browse '.$category->name.' products at Prinx Tech.',
                ]],
            ]))
            ->merge(Category::serviceType()->active()->get()->map(fn (Category $category) => [
                'loc' => route('categories.services', $category),
                'lastmod' => $category->updated_at,
                'images' => [[
                    'loc' => $category->image_url,
                    'title' => $category->name.' service category',
                    'caption' => $category->description ?: 'Browse '.$category->name.' services at Prinx Tech.',
                ]],
            ]))
            ->merge(Product::active()->get()->map(fn (Product $product) => [
                'loc' => route('products.show', $product),
                'lastmod' => $product->updated_at,
                'images' => collect([$product->image_url, ...$product->gallery_urls])->filter()->unique()->take(10)->map(fn (string $image, int $index) => [
                    'loc' => $image,
                    'title' => $product->name.($index ? ' product image '.($index + 1) : ''),
                    'caption' => $product->meta_description ?: $product->short_description ?: Str::limit($product->plain_description, 180, ''),
                ])->values()->all(),
            ]))
            ->merge(Service::active()->get()->map(fn (Service $service) => [
                'loc' => route('services.show', $service),
                'lastmod' => $service->updated_at,
                'images' => [[
                    'loc' => $service->image_url,
                    'title' => $service->name.' service',
                    'caption' => $service->meta_description ?: $service->short_description ?: Str::limit(strip_tags((string) $service->description), 180, ''),
                ]],
            ]));

        $body = $urls->map(function (array $entry) use ($escape): string {
            $images = collect($entry['images'])->map(function (array $image) use ($escape): string {
                return '<image:image>'.
                    '<image:loc>'.$escape($image['loc']).'</image:loc>'.
                    '<image:title>'.$escape($image['title']).'</image:title>'.
                    '<image:caption>'.$escape($image['caption']).'</image:caption>'.
                    (config('store.image_rights_url') ? '<image:license>'.$escape(config('store.image_rights_url')).'</image:license>' : '').
                    '</image:image>';
            })->implode('');

            return '<url><loc>'.$escape($entry['loc']).'</loc><lastmod>'.$entry['lastmod']->toAtomString().'</lastmod>'.$images.'</url>';
        })->implode('');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'.
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'.
            $body.
            '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
