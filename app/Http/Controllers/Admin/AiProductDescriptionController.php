<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

class AiProductDescriptionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! config('services.openai.product_formatter_enabled') || blank(config('services.openai.api_key'))) {
            return response()->json([
                'message' => 'AI description formatting is not configured. Add OPENAI_API_KEY and enable OPENAI_PRODUCT_FORMATTER_ENABLED on the server.',
            ], 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'brand_name' => ['nullable', 'string', 'max:120'],
            'category_name' => ['nullable', 'string', 'max:120'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:15000'],
        ]);

        $instructions = <<<'PROMPT'
You are a senior ecommerce copy editor for Prinx Electronics in Tanzania. Rewrite rough product information into clear, factual, professional store copy.

Rules:
- Do not invent technical specifications, warranty terms, accessories, model numbers, features, compatibility, certifications or performance claims.
- Preserve every trustworthy fact supplied by the operator.
- Remove filler, repetition, awkward punctuation and unsupported promotional claims.
- Use readable Markdown only in description_markdown.
- description_markdown should usually contain a concise opening paragraph and, only when the source material supports them, headings such as "Key features", "Specifications" or "Ideal for".
- Use bullet lists for specifications/features rather than dense paragraphs.
- Do not repeat the product name in every bullet.
- short_description must be plain text, useful in product cards, and no more than 260 characters.
- meta_title should normally be 45-65 characters when enough context exists.
- meta_description should normally be 120-160 characters and must not contain Markdown.
- Return JSON only and exactly these keys: short_description, description_markdown, meta_title, meta_description.
PROMPT;

        $payload = [
            'product_name' => $data['name'],
            'brand' => $data['brand_name'] ?? null,
            'category' => $data['category_name'] ?? null,
            'short_description' => $data['short_description'] ?? null,
            'description' => $data['description'] ?? null,
        ];

        try {
            $response = Http::baseUrl(config('services.openai.base_url'))
                ->withToken(config('services.openai.api_key'))
                ->acceptJson()
                ->timeout(45)
                ->retry(1, 300)
                ->post('/responses', [
                    'model' => config('services.openai.model'),
                    'instructions' => $instructions,
                    'input' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'prinx_product_description',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => [
                                    'short_description' => ['type' => 'string'],
                                    'description_markdown' => ['type' => 'string'],
                                    'meta_title' => ['type' => 'string'],
                                    'meta_description' => ['type' => 'string'],
                                ],
                                'required' => ['short_description', 'description_markdown', 'meta_title', 'meta_description'],
                            ],
                        ],
                    ],
                ])
                ->throw()
                ->json();

            $output = data_get($response, 'output.0.content.0.text');

            if (! is_string($output)) {
                $output = collect(data_get($response, 'output', []))
                    ->flatMap(fn (array $item) => $item['content'] ?? [])
                    ->firstWhere('type', 'output_text')['text'] ?? null;
            }

            if (! is_string($output) || $output === '') {
                throw new RuntimeException('The AI service returned an empty response.');
            }

            $formatted = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

            return response()->json([
                'short_description' => Str::limit(trim((string) $formatted['short_description']), 500, ''),
                'description' => trim((string) $formatted['description_markdown']),
                'meta_title' => Str::limit(trim((string) $formatted['meta_title']), 190, ''),
                'meta_description' => Str::limit(trim((string) $formatted['meta_description']), 180, ''),
            ]);
        } catch (ConnectionException) {
            return response()->json(['message' => 'Could not connect to the AI service. Check the server connection and try again.'], 503);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => app()->isLocal() ? $exception->getMessage() : 'The description could not be formatted right now. Please try again.',
            ], 422);
        }
    }
}
