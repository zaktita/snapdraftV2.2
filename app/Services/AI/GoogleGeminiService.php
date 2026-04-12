<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleGeminiService
{
    public function __construct(
        protected GeminiClient $client,
    ) {}

    /**
     * Generate an image with optional brand reference images.
     *
     * @param  string   $prompt
     * @param  string[] $referenceImages  Storage-relative paths to brand reference images
     * @param  string[] $productImages    Storage-relative paths to product overlay images
     * @param  string   $format           square|portrait|landscape
     * @return array{image_data: string, mime_type: string}
     */
    public function generateWithReferences(
        string $prompt,
        array  $referenceImages = [],
        array  $productImages   = [],
        string $format          = 'square',
    ): array {
        $model = config('services.gemini.image_model', 'gemini-2.0-flash-preview-image-generation');

        $parts = [];

        foreach ([...$referenceImages, ...$productImages] as $path) {
            try {
                $parts[] = $this->client->imageToInlinePart($path);
            } catch (\Throwable $e) {
                Log::warning('GoogleGeminiService: could not load reference image', [
                    'path'  => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $parts[] = ['text' => $prompt];

        $contents    = [['parts' => $parts]];
        $aspectRatio = $this->resolveAspectRatio($format);

        $base64 = $this->client->generateImage($model, $contents, $aspectRatio);

        return ['image_data' => $base64, 'mime_type' => 'image/png'];
    }

    /**
     * Analyse brand style from reference images and return a structured result.
     *
     * @param  string[] $imagePaths
     * @return array
     */
    public function analyzeBrandStyle(array $imagePaths): array
    {
        $model = config('services.gemini.vision_model', 'gemini-2.0-flash');

        $parts = [];
        foreach ($imagePaths as $path) {
            try {
                $parts[] = $this->client->imageToInlinePart($path);
            } catch (\Throwable $e) {
                Log::warning('GoogleGeminiService: could not load brand image', [
                    'path'  => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $parts[] = ['text' => 'Analyze the visual brand style of these images. Return a JSON object with keys: style_clusters (array), color_palette (array), typography_style (string), composition_notes (string).'];

        $schema = [
            'type'       => 'OBJECT',
            'properties' => [
                'style_clusters'    => ['type' => 'ARRAY', 'items' => ['type' => 'OBJECT']],
                'color_palette'     => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                'typography_style'  => ['type' => 'STRING'],
                'composition_notes' => ['type' => 'STRING'],
            ],
        ];

        return $this->client->generateContentWithSchema($model, [['parts' => $parts]], $schema);
    }

    /**
     * Outpaint / extend image content outside its current bounds.
     *
     * @param  string $imageBase64  Base64-encoded source image
     * @param  string $maskBase64   Base64-encoded mask (white = area to fill)
     * @return string  Base64-encoded result image
     */
    public function outpaint(string $imageBase64, string $maskBase64): string
    {
        $model = config('services.gemini.image_model', 'gemini-2.0-flash-preview-image-generation');

        $contents = [[
            'parts' => [
                ['inlineData' => ['mimeType' => 'image/png', 'data' => $imageBase64]],
                ['inlineData' => ['mimeType' => 'image/png', 'data' => $maskBase64]],
                ['text' => 'Seamlessly extend/outpaint the image into the masked (white) region, maintaining the existing style and composition.'],
            ],
        ]];

        return $this->client->generateImage($model, $contents);
    }

    /**
     * Erase green-highlighted areas from an image (replace with contextual content).
     *
     * @param  string $imageBase64  Base64-encoded image with green highlights marking areas to erase
     * @return string  Base64-encoded result image
     */
    public function eraseGreenHighlights(string $imageBase64): string
    {
        $model = config('services.gemini.image_model', 'gemini-2.0-flash-preview-image-generation');

        $contents = [[
            'parts' => [
                ['inlineData' => ['mimeType' => 'image/png', 'data' => $imageBase64]],
                ['text' => 'Remove the green-highlighted areas from this image and fill them with contextually appropriate content that blends naturally with the surrounding areas.'],
            ],
        ]];

        return $this->client->generateImage($model, $contents);
    }

    /**
     * Generate/inpaint an image region based on a text prompt and mask.
     *
     * @param  string $imageBase64  Base64-encoded source image
     * @param  string $maskBase64   Base64-encoded mask (white = area to generate)
     * @param  string $prompt       Description of what to generate
     * @return string  Base64-encoded result image
     */
    public function generateFromPrompt(string $imageBase64, string $maskBase64, string $prompt): string
    {
        $model = config('services.gemini.image_model', 'gemini-2.0-flash-preview-image-generation');

        $contents = [[
            'parts' => [
                ['inlineData' => ['mimeType' => 'image/png', 'data' => $imageBase64]],
                ['inlineData' => ['mimeType' => 'image/png', 'data' => $maskBase64]],
                ['text' => $prompt],
            ],
        ]];

        return $this->client->generateImage($model, $contents);
    }

    /**
     * Edit an image given a text prompt and an optional mask.
     *
     * @param  string      $imageBase64
     * @param  string      $prompt
     * @param  string|null $maskBase64
     * @return string  Base64-encoded result image
     */
    public function editBase64(string $imageBase64, string $prompt, ?string $maskBase64 = null): string
    {
        $model = config('services.gemini.image_model', 'gemini-2.0-flash-preview-image-generation');

        $parts = [
            ['inlineData' => ['mimeType' => 'image/png', 'data' => $imageBase64]],
        ];

        if ($maskBase64 !== null) {
            $parts[] = ['inlineData' => ['mimeType' => 'image/png', 'data' => $maskBase64]];
        }

        $parts[] = ['text' => $prompt];

        $contents = [['parts' => $parts]];

        return $this->client->generateImage($model, $contents);
    }

    /**
     * Return the name of the active generation model.
     */
    public function getModelName(): string
    {
        return config('services.gemini.image_model', 'gemini-2.0-flash-preview-image-generation');
    }

    // -------------------------------------------------------------------------

    private function resolveAspectRatio(string $format): string
    {
        return match (strtolower($format)) {
            'portrait'  => '9:16',
            'landscape' => '16:9',
            default     => '1:1',
        };
    }
}
