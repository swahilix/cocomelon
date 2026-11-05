<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductIntelligence\MarketPriceService;
use App\Services\ProductIntelligence\ProductRecognitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Smart product recognition (barcode / photo) and market price checks.
 * All endpoints return suggestions only; nothing is saved.
 */
class ProductIntelligenceController extends Controller
{
    public function barcode(Request $request, ProductRecognitionService $recognition): JsonResponse
    {
        $validated = $request->validate(['gtin' => ['required', 'string', 'max:30']]);
        @set_time_limit(150);

        return $this->respond(fn () => $recognition->fromBarcode($validated['gtin']));
    }

    public function image(Request $request, ProductRecognitionService $recognition): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'image.mimes' => 'Use a JPG, PNG or WebP photo.',
            'image.max' => 'The photo must be 10 MB or smaller.',
        ]);
        @set_time_limit(150);

        return $this->respond(fn () => $recognition->fromImage($validated['image']));
    }

    public function marketPrice(Request $request, MarketPriceService $prices): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer'],
            'refresh' => ['boolean'],
        ]);
        @set_time_limit(150);

        $product = Product::query()->with('brand:id,name')->findOrFail($validated['product_id']);
        $variant = filled($validated['product_variant_id'] ?? null)
            ? ProductVariant::query()->where('product_id', $product->id)->find($validated['product_variant_id'])
            : null;

        return $this->respond(fn () => [
            ...$prices->check(
                $product->name,
                $product->brand?->name,
                $variant ? trim(collect([$variant->label, $variant->storage, $variant->color, $variant->size])->filter()->unique()->implode(' ')) : null,
                (string) ($product->condition ?: 'new'),
                (bool) ($validated['refresh'] ?? false),
            ),
            'current_price' => $variant?->price !== null ? (float) $variant->price : ((float) $product->price ?: null),
        ]);
    }

    private function respond(callable $callback): JsonResponse
    {
        try {
            return response()->json($callback());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }
}
