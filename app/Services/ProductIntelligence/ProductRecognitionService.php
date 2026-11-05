<?php

namespace App\Services\ProductIntelligence;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\Gtin;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Recognises products from a barcode or a photo and returns a suggestion the
 * admin can review and apply to the Add product form. Nothing is saved here.
 */
class ProductRecognitionService
{
    private const UPCITEMDB_TRIAL = 'https://api.upcitemdb.com/prod/trial/lookup';

    private const UPCITEMDB_PAID = 'https://api.upcitemdb.com/prod/v1/lookup';

    public function __construct(private readonly OpenAiResponses $ai)
    {
    }

    public function aiEnabled(): bool
    {
        return $this->ai->enabled();
    }

    /**
     * @return array<string, mixed>
     */
    public function fromBarcode(string $input): array
    {
        $gtin = Gtin::normalize($input);

        if (! Gtin::isValid($gtin)) {
            throw new RuntimeException('That is not a valid barcode. Scan again or check the digits (8, 12, 13 or 14 digits).');
        }

        $existing = Product::withTrashed()->where('gtin', $gtin)->first(['id', 'name', 'slug', 'deleted_at']);
        $notes = [];

        $suggestion = Cache::remember('product-lookup:barcode:'.$gtin, now()->addDays(7), function () use ($gtin, &$notes): ?array {
            $fromDatabase = $this->lookupUpcItemDb($gtin, $notes);
            if ($fromDatabase) {
                return $fromDatabase;
            }

            if (! $this->ai->enabled()) {
                return null;
            }

            return $this->lookupBarcodeWithAi($gtin);
        });

        if (! $suggestion) {
            $message = 'No product details were found for barcode '.$gtin.'.';
            if (! $this->ai->enabled()) {
                $message .= ' Add an OpenAI API key in Settings → AI & product lookup to also search the web.';
            } else {
                $message .= ' Try identifying it from a photo instead.';
            }
            if ($notes) {
                $message .= ' ('.implode(' ', $notes).')';
            }
            throw new RuntimeException($message);
        }

        $suggestion['gtin'] = $gtin;

        return $this->finish($suggestion, $existing);
    }

    /**
     * @return array<string, mixed>
     */
    public function fromImage(UploadedFile $image): array
    {
        $bytes = (string) file_get_contents($image->getRealPath());
        $mime = $image->getMimeType() ?: 'image/jpeg';

        $result = $this->ai->json(
            $this->instructions('a photo of the product taken in the shop')
                .' First identify the exact product (brand, model, variant/capacity/colour) from the photo: read any visible text, logos and model numbers. Then use web search to confirm specifications. If you cannot identify the exact model, set confidence to "low" and describe what you can see without guessing a model.',
            [
                ['type' => 'input_text', 'text' => 'Identify this product and fill in its details. '.$this->categoryHint()],
                $this->ai->imagePart($bytes, $mime),
            ],
            'prinx_product_from_image',
            $this->schema(),
            true,
        );

        if (! ($result['data']['found'] ?? false)) {
            throw new RuntimeException('The product could not be recognised from this photo. Try a clearer photo of the front, the box or the label.');
        }

        $suggestion = $this->fromAiData($result['data'], $result['sources'], 'photo');

        $existing = filled($suggestion['gtin'] ?? null)
            ? Product::withTrashed()->where('gtin', $suggestion['gtin'])->first(['id', 'name', 'slug', 'deleted_at'])
            : null;

        return $this->finish($suggestion, $existing);
    }

    /**
     * @param  array<int, string>  $notes
     */
    private function lookupUpcItemDb(string $gtin, array &$notes): ?array
    {
        if (! config('services.upcitemdb.enabled', true)) {
            return null;
        }

        $key = config('services.upcitemdb.key');

        try {
            $request = Http::acceptJson()->timeout(12);
            if (filled($key)) {
                $request = $request->withHeaders(['user_key' => $key, 'key_type' => config('services.upcitemdb.key_type', '3scale')]);
            }
            $response = $request->get(filled($key) ? self::UPCITEMDB_PAID : self::UPCITEMDB_TRIAL, ['upc' => $gtin]);
        } catch (ConnectionException) {
            $notes[] = 'The barcode database could not be reached.';
            return null;
        }

        if ($response->status() === 429) {
            $notes[] = 'The free barcode database limit for today was reached.';
            return null;
        }

        if (! $response->successful()) {
            if ($response->status() !== 404 && $response->status() !== 400) {
                Log::info('UPCitemdb lookup failed.', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);
            }
            return null;
        }

        $item = $response->json('items.0');
        if (! is_array($item) || blank($item['title'] ?? null)) {
            return null;
        }

        $specifications = collect([
            'Model' => $item['model'] ?? null,
            'Color' => $item['color'] ?? null,
            'Size' => $item['size'] ?? null,
            'Dimensions' => $item['dimension'] ?? null,
            'Weight' => $item['weight'] ?? null,
        ])->filter(fn ($value) => filled($value))->map(fn ($value, $label) => ['label' => $label, 'value' => trim((string) $value)])->values()->all();

        $offers = collect($item['offers'] ?? [])->pluck('link')->filter()->take(3)
            ->map(fn ($url) => ['title' => (string) (parse_url($url, PHP_URL_HOST) ?: $url), 'url' => (string) $url]);

        return [
            'source' => 'barcode_database',
            'confidence' => 'high',
            'name' => Str::limit(trim((string) $item['title']), 150, ''),
            'brand' => trim((string) ($item['brand'] ?? '')),
            'model' => trim((string) ($item['model'] ?? '')),
            'category' => trim((string) ($item['category'] ?? '')),
            'short_description' => '',
            'description' => trim(strip_tags((string) ($item['description'] ?? ''))),
            'specifications' => $specifications,
            'color' => trim((string) ($item['color'] ?? '')),
            'image_urls' => collect($item['images'] ?? [])->filter(fn ($url) => is_string($url) && str_starts_with($url, 'http'))->unique()->take(6)->values()->all(),
            'sources' => [['title' => 'UPCitemdb', 'url' => 'https://www.upcitemdb.com/upc/'.$gtin], ...$offers->all()],
            'notes' => '',
        ];
    }

    private function lookupBarcodeWithAi(string $gtin): ?array
    {
        $result = $this->ai->json(
            $this->instructions('a product barcode (GTIN/EAN/UPC)')
                .' Search the web for this exact barcode number. Only report a product when a page clearly ties this exact barcode to it; otherwise set found to false.',
            [['type' => 'input_text', 'text' => 'Barcode: '.$gtin.'. '.$this->categoryHint()]],
            'prinx_product_from_barcode',
            $this->schema(),
            true,
        );

        if (! ($result['data']['found'] ?? false)) {
            return null;
        }

        return $this->fromAiData($result['data'], $result['sources'], 'web_search');
    }

    private function fromAiData(array $data, array $sources, string $source): array
    {
        return [
            'source' => $source,
            'confidence' => in_array($data['confidence'] ?? '', ['high', 'medium', 'low'], true) ? $data['confidence'] : 'low',
            'name' => Str::limit(trim((string) ($data['name'] ?? '')), 150, ''),
            'brand' => trim((string) ($data['brand'] ?? '')),
            'model' => trim((string) ($data['model'] ?? '')),
            'gtin' => Gtin::isValid(Gtin::normalize($data['gtin'] ?? '')) ? Gtin::normalize($data['gtin']) : null,
            'category' => trim((string) ($data['category'] ?? '')),
            'short_description' => Str::limit(trim((string) ($data['short_description'] ?? '')), 500, ''),
            'description' => trim((string) ($data['description'] ?? '')),
            'specifications' => collect($data['specifications'] ?? [])
                ->filter(fn ($row) => filled($row['label'] ?? null) && filled($row['value'] ?? null))
                ->map(fn ($row) => ['label' => trim((string) $row['label']), 'value' => trim((string) $row['value'])])
                ->take(40)->values()->all(),
            'color' => trim((string) ($data['color'] ?? '')),
            'image_urls' => collect($data['image_urls'] ?? [])->filter(fn ($url) => is_string($url) && str_starts_with($url, 'https://'))->unique()->take(6)->values()->all(),
            'sources' => array_slice($sources, 0, 8),
            'notes' => trim((string) ($data['notes'] ?? '')),
        ];
    }

    /**
     * Attach matching brand/category ids and any existing product with the same barcode.
     */
    private function finish(array $suggestion, ?Product $existing): array
    {
        $suggestion['brand_id'] = $this->matchBrand($suggestion['brand'] ?? '');
        $suggestion['category_id'] = $this->matchCategory($suggestion['category'] ?? '');
        $suggestion['existing_product'] = $existing ? [
            'id' => $existing->id,
            'name' => $existing->name,
            'slug' => $existing->slug,
            'trashed' => $existing->deleted_at !== null,
        ] : null;

        return $suggestion;
    }

    private function matchBrand(string $brand): ?int
    {
        $brand = Str::lower(trim($brand));
        if ($brand === '') {
            return null;
        }

        $brands = Brand::query()->where('is_active', true)->get(['id', 'name']);
        $match = $brands->first(fn (Brand $item) => Str::lower($item->name) === $brand)
            ?? $brands->first(fn (Brand $item) => Str::contains($brand, Str::lower($item->name)) || Str::contains(Str::lower($item->name), $brand));

        return $match?->id;
    }

    private function matchCategory(string $category): ?int
    {
        $category = Str::lower(trim($category));
        if ($category === '') {
            return null;
        }

        $categories = $this->categories();
        $exact = $categories->first(fn (Category $item) => Str::lower($item->name) === $category);
        if ($exact) {
            return $exact->id;
        }

        // Barcode databases return paths like "Electronics > Cell Phones"; use the most specific part that matches.
        foreach (array_reverse(preg_split('/\s*[>|\/]\s*/', $category) ?: []) as $part) {
            $part = Str::singular(trim($part));
            $match = $categories->first(fn (Category $item) => $part !== '' && (Str::contains(Str::lower($item->name), $part) || Str::contains($part, Str::singular(Str::lower($item->name)))));
            if ($match) {
                return $match->id;
            }
        }

        return null;
    }

    private function categories(): Collection
    {
        return Category::productType()->active()->get(['id', 'name']);
    }

    private function categoryHint(): string
    {
        $names = $this->categories()->pluck('name')->take(80)->implode(', ');

        return $names !== '' ? 'For "category", choose the closest of these store categories, or leave it empty: '.$names.'.' : '';
    }

    private function instructions(string $input): string
    {
        return 'You help an electronics shop in Tanzania (Prinx Tech) add products to its catalogue from '.$input.'. '
            .'Return factual product information only. Never invent specifications, prices, warranty terms or model numbers: leave a field empty when unsure. '
            .'"name" should be a clear store product name such as "Samsung Galaxy A15 4GB/128GB Blue". '
            .'"description" is 1-3 short factual paragraphs in plain text. "short_description" is one plain sentence under 200 characters. '
            .'"specifications" lists key specs as label/value pairs (e.g. RAM, Storage, Screen, Battery, Processor, Camera, Connectivity). '
            .'"image_urls" may list up to 3 direct https image links of the official product photos if you found them, otherwise an empty list. '
            .'"gtin" is the barcode number only if you found it, otherwise empty. Keep "notes" short, e.g. what could not be confirmed.';
    }

    private function schema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'found' => ['type' => 'boolean'],
                'confidence' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                'name' => $string,
                'brand' => $string,
                'model' => $string,
                'gtin' => $string,
                'category' => $string,
                'short_description' => $string,
                'description' => $string,
                'specifications' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => ['label' => $string, 'value' => $string],
                        'required' => ['label', 'value'],
                    ],
                ],
                'color' => $string,
                'image_urls' => ['type' => 'array', 'items' => $string],
                'notes' => $string,
            ],
            'required' => ['found', 'confidence', 'name', 'brand', 'model', 'gtin', 'category', 'short_description', 'description', 'specifications', 'color', 'image_urls', 'notes'],
        ];
    }
}
