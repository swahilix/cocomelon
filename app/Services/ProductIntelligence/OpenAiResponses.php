<?php

namespace App\Services\ProductIntelligence;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

/**
 * Thin wrapper around the OpenAI Responses API for structured JSON answers,
 * optionally with web search (limited to Tanzania) and image input.
 */
class OpenAiResponses
{
    public function enabled(): bool
    {
        return (bool) config('services.openai.product_intelligence_enabled', true)
            && filled(config('services.openai.api_key'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $content  Responses API user content parts
     * @param  array<string, mixed>  $schema  JSON schema for the answer
     * @return array{data: array<string, mixed>, sources: array<int, array{title: string, url: string}>}
     */
    public function json(string $instructions, array $content, string $schemaName, array $schema, bool $webSearch = false): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('AI features are not set up. Add an OpenAI API key in Settings → AI & product lookup to use photo recognition and price checks.');
        }

        $payload = [
            'model' => config('services.openai.search_model') ?: config('services.openai.model'),
            'instructions' => $instructions,
            'input' => [['role' => 'user', 'content' => $content]],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $schemaName,
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ];

        if ($webSearch) {
            $payload['tools'] = [[
                'type' => 'web_search',
                'user_location' => [
                    'type' => 'approximate',
                    'country' => 'TZ',
                    'city' => 'Dodoma',
                    'region' => 'Dodoma',
                    'timezone' => 'Africa/Dar_es_Salaam',
                ],
            ]];
            $payload['tool_choice'] = 'auto';
            $payload['include'] = ['web_search_call.action.sources'];
            // Reasoning models (GPT-5 family, o-series) need more than minimal
            // reasoning to search; other models reject the setting.
            if (preg_match('/^(gpt-[5-9]|o\d)/i', (string) $payload['model'])) {
                $payload['reasoning'] = ['effort' => 'low'];
            }
        }

        try {
            $response = Http::baseUrl((string) config('services.openai.base_url'))
                ->withToken((string) config('services.openai.api_key'))
                ->acceptJson()
                ->timeout($webSearch ? 110 : 60)
                ->post('/responses', $payload)
                ->throw()
                ->json();
        } catch (ConnectionException) {
            throw new RuntimeException('Could not reach the AI service. Check the internet connection and try again.');
        } catch (RequestException $exception) {
            Log::warning('OpenAI product intelligence request failed.', [
                'status' => $exception->response?->status(),
                'body' => mb_substr((string) $exception->response?->body(), 0, 1500),
            ]);
            throw new RuntimeException('The AI service could not complete the request. Please try again.');
        }

        [$text, $sources] = $this->extract((array) $response);

        if ($text === '') {
            throw new RuntimeException('The AI service returned an empty answer. Please try again.');
        }

        try {
            $data = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('The AI service returned an answer that could not be read. Please try again.');
        }

        return ['data' => is_array($data) ? $data : [], 'sources' => $sources];
    }

    /**
     * Build an image content part from raw bytes.
     */
    public function imagePart(string $bytes, string $mime): array
    {
        return [
            'type' => 'input_image',
            'image_url' => 'data:'.$mime.';base64,'.base64_encode($bytes),
            'detail' => 'high',
        ];
    }

    /**
     * @return array{0: string, 1: array<int, array{title: string, url: string}>}
     */
    private function extract(array $response): array
    {
        $text = '';
        $sources = [];

        foreach ((array) ($response['output'] ?? []) as $item) {
            if (($item['type'] ?? null) === 'web_search_call') {
                foreach ((array) data_get($item, 'action.sources', []) as $source) {
                    if (filled($source['url'] ?? null)) {
                        $sources[$source['url']] = ['title' => (string) ($source['title'] ?? $source['url']), 'url' => (string) $source['url']];
                    }
                }
            }

            if (($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ((array) ($item['content'] ?? []) as $part) {
                if (($part['type'] ?? null) !== 'output_text') {
                    continue;
                }

                $text .= (string) ($part['text'] ?? '');

                foreach ((array) ($part['annotations'] ?? []) as $annotation) {
                    if (($annotation['type'] ?? null) === 'url_citation' && filled($annotation['url'] ?? null)) {
                        $sources[$annotation['url']] = ['title' => (string) ($annotation['title'] ?? $annotation['url']), 'url' => (string) $annotation['url']];
                    }
                }
            }
        }

        return [trim($text), array_values($sources)];
    }
}
