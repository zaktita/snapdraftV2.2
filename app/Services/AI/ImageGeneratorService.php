<?php

namespace App\Services\AI;

use RuntimeException;

class ImageGeneratorService
{
    public function __construct(
        protected GeminiClient $client,
    ) {}

    public function model(): string
    {
        return config('services.gemini.image_model', 'gemini-2.5-flash-image');
    }

    /**
     * Phase 3: Generate a single image for a prompt batch item.
     *
     * @param  array   $promptItem    One item from CaptionMatcherService::match() output
     * @param  string[] $refImagePaths Storage paths of all brand reference images
     * @return string  Raw base64-encoded PNG data
     */
    public function generate(array $promptItem, array $refImagePaths): string
    {
        // Pick reference images for this cluster; fall back to all refs if indices not specified
        $indices = $promptItem['referenceImageIndices'] ?? array_keys($refImagePaths);
        $refParts = [];
        foreach ($indices as $idx) {
            if (isset($refImagePaths[$idx])) {
                $refParts[] = $this->client->imageToInlinePart($refImagePaths[$idx]);
            }
        }

        // If still empty (e.g. no reference images at all), just proceed without them
        if (empty($refParts) && !empty($refImagePaths)) {
            throw new RuntimeException(
                "No valid reference images found for prompt item row {$promptItem['rowIndex']}."
            );
        }

        $contents = [[
            'parts' => [...$refParts, ['text' => $promptItem['generationPrompt']]],
        ]];

        $aspectRatio = $this->resolveAspectRatio($promptItem['format'] ?? 'square');

        return $this->client->generateImage($this->model(), $contents, $aspectRatio);
    }

    private function resolveAspectRatio(string $format): string
    {
        return match (strtolower($format)) {
            'portrait'  => '9:16',
            'landscape' => '16:9',
            default     => '1:1', // square
        };
    }
}
