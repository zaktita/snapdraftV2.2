<?php

namespace App\Services\AI;

use App\Services\FormatPresetMapper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OpenRouterService
{
    protected string $baseUrl = 'https://openrouter.ai/api/v1';

    public function __construct()
    {
        // API key is read from config on each request so it can be swapped at runtime.
    }

    /**
     * Generate an image with optional brand reference images.
     *
     * @param  string[]  $referenceImages  Storage-relative paths to brand reference images
     * @param  string[]  $productImages  Storage-relative paths to product overlay images
     * @param  string  $format  square|portrait|landscape
     * @return array{image_data: string, mime_type: string}
     */
    public function generateWithReferences(
        string $prompt,
        array $referenceImages = [],
        array $productImages = [],
        string $format = 'square',
    ): array {
        $model = config('services.openrouter.image_model', 'bytedance-seed/seedream-4.5');
        $aspectRatio = FormatPresetMapper::aspectRatio($format);

        $messages = [];

        $imageParts = [];
        foreach ([...$referenceImages, ...$productImages] as $path) {
            try {
                $base64 = $this->encodeImageToBase64($path);
                $mimeType = $this->guessMimeType($path);
                $imageParts[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => "data:{$mimeType};base64,{$base64}"],
                ];
            } catch (\Throwable $e) {
                Log::warning('OpenRouterService: could not load reference image', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $content = [...$imageParts, ['type' => 'text', 'text' => $prompt]];

        $messages[] = ['role' => 'user', 'content' => $content];

        $apiKey = config('services.openrouter.api_key');
        if (empty($apiKey)) {
            throw new RuntimeException('OPENROUTER_API_KEY is not configured.');
        }

        $response = Http::timeout(180)
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name', 'SnapDraft'),
            ])
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $model,
                'messages' => $messages,
                'extra_body' => [
                    'image_config' => ['aspect_ratio' => $aspectRatio],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenRouter API request failed: '.$response->body());
        }

        $data = $response->json();

        // OpenRouter image models return base64 inside a data-URL or as raw base64.
        $content = $data['choices'][0]['message']['content'] ?? null;
        if (! $content) {
            throw new RuntimeException('OpenRouter returned no content.');
        }

        // Strip data-URI prefix if present
        if (str_starts_with($content, 'data:')) {
            [, $base64] = explode(',', $content, 2);
        } else {
            $base64 = $content;
        }

        return ['image_data' => $base64, 'mime_type' => 'image/png'];
    }

    /**
     * Analyse brand style from reference images and return a structured result.
     *
     * @param  string[]  $imagePaths
     */
    public function analyzeBrandStyle(array $imagePaths): array
    {
        $promptModel = $this->getPromptModel();
        $apiKey = config('services.openrouter.api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('OPENROUTER_API_KEY is not configured.');
        }

        $imageParts = [];
        foreach ($imagePaths as $path) {
            try {
                $base64 = $this->encodeImageToBase64($path);
                $mimeType = $this->guessMimeType($path);
                $imageParts[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => "data:{$mimeType};base64,{$base64}"],
                ];
            } catch (\Throwable $e) {
                Log::warning('OpenRouterService: could not load brand image', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $text = 'Analyze the visual brand style of these images. Return a JSON object with keys: style_clusters (array of objects), color_palette (array of hex strings), typography_style (string), composition_notes (string).';

        $response = Http::timeout(60)
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'HTTP-Referer' => config('app.url'),
            ])
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $promptModel,
                'response_format' => ['type' => 'json_object'],
                'messages' => [[
                    'role' => 'user',
                    'content' => [...$imageParts, ['type' => 'text', 'text' => $text]],
                ]],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenRouter brand analysis failed: '.$response->body());
        }

        $raw = $response->json('choices.0.message.content', '{}');

        return json_decode($raw, true) ?? ['style_clusters' => []];
    }

    /**
     * Outpaint / extend image content outside its current bounds.
     */
    public function outpaint(string $imageBase64, string $maskBase64): array
    {
        // OpenRouter does not natively support inpainting; delegate to generation.
        return $this->generateWithReferences(
            'Seamlessly extend/outpaint the image into the masked (white) region, maintaining the existing style.',
            [],
            [],
            'square',
        );
    }

    /**
     * Erase green-highlighted areas from an image.
     */
    public function eraseGreenHighlights(string $imageBase64): array
    {
        $content = [
            ['type' => 'image_url', 'image_url' => ['url' => "data:image/png;base64,{$imageBase64}"]],
            ['type' => 'text', 'text' => 'Remove the green-highlighted areas from this image and fill them with contextually appropriate content that blends naturally.'],
        ];

        $model = config('services.openrouter.image_model', 'bytedance-seed/seedream-4.5');
        $apiKey = config('services.openrouter.api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('OPENROUTER_API_KEY is not configured.');
        }

        $response = Http::timeout(180)
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'HTTP-Referer' => config('app.url'),
            ])
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => $content]],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenRouter erase request failed: '.$response->body());
        }

        $raw = $response->json('choices.0.message.content', '');
        if (str_starts_with($raw, 'data:')) {
            [, $raw] = explode(',', $raw, 2);
        }

        return ['image_data' => $raw, 'mime_type' => 'image/png'];
    }

    /**
     * Edit an image given a text prompt and an optional mask.
     */
    public function editBase64(
        string $imageBase64,
        string $prompt,
        ?string $maskBase64 = null,
        ?string $aspectRatio = null,
    ): string {
        $content = [
            ['type' => 'image_url', 'image_url' => ['url' => "data:image/png;base64,{$imageBase64}"]],
        ];

        if ($maskBase64 !== null) {
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => "data:image/png;base64,{$maskBase64}"]];
        }

        $content[] = ['type' => 'text', 'text' => $prompt];

        $model = config('services.openrouter.image_model', 'bytedance-seed/seedream-4.5');
        $apiKey = config('services.openrouter.api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('OPENROUTER_API_KEY is not configured.');
        }

        $payload = [
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $content]],
        ];

        if ($aspectRatio !== null) {
            $payload['image_config'] = ['aspect_ratio' => $aspectRatio];
        }

        $response = Http::timeout(180)
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'HTTP-Referer' => config('app.url'),
            ])
            ->post("{$this->baseUrl}/chat/completions", $payload);

        if ($response->failed()) {
            throw new RuntimeException('OpenRouter edit request failed: '.$response->body());
        }

        $raw = $response->json('choices.0.message.content', '');
        if (str_starts_with($raw, 'data:')) {
            [, $raw] = explode(',', $raw, 2);
        }

        return $raw;
    }

    /**
     * Return the name of the active generation model.
     */
    public function getModelName(): string
    {
        return config('services.openrouter.image_model', 'bytedance-seed/seedream-4.5');
    }

    // -------------------------------------------------------------------------

    private function getPromptModel(): string
    {
        $models = config('services.openrouter.prompt_models', 'google/gemini-flash-1.5,openai/gpt-4o-mini');

        return explode(',', $models)[0];
    }

    private function encodeImageToBase64(string $storagePath): string
    {
        $absolutePath = Storage::disk('public')->exists($storagePath)
            ? Storage::disk('public')->path($storagePath)
            : (file_exists($storagePath) ? $storagePath : null);

        if ($absolutePath === null) {
            throw new RuntimeException("Image not found at path: {$storagePath}");
        }

        return base64_encode(file_get_contents($absolutePath));
    }

    private function guessMimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }
}
