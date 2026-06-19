<?php

namespace App\Services\AI;

use App\Models\ProjectClusterImage;
use App\Services\Prompt\JsonPromptCompiler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GeminiCsvImageGenerator
{
    public function __construct(
        protected GeminiClient $client,
        protected JsonPromptCompiler $promptCompiler,
    ) {}

    public function model(): string
    {
        return (string) config('services.gemini.image_model', 'gemini-3.1-flash-image-preview');
    }

    /**
     * @param  array<string, mixed>  $promptJson
     * @param  Collection<int, ProjectClusterImage>  $clusterImages
     */
    public function generateFromPromptJson(
        array $promptJson,
        Collection $clusterImages,
        string $aspectRatio,
        int $resolutionMultiplier = 1,
        ?string $caption = null,
        ?string $imageRequestPrompt = null,
    ): string {
        if ($imageRequestPrompt !== null && trim($imageRequestPrompt) !== '') {
            return $this->generate($imageRequestPrompt, $clusterImages, $aspectRatio, $resolutionMultiplier);
        }

        $caption = trim($caption ?? (string) data_get($promptJson, 'post.caption', ''));
        $promptText = $this->promptCompiler->buildImageRequestPrompt(
            $promptJson,
            $clusterImages->count(),
            $caption !== '' ? $caption : null,
        );

        return $this->generate($promptText, $clusterImages, $aspectRatio, $resolutionMultiplier);
    }

    /**
     * @param  Collection<int, ProjectClusterImage>  $clusterImages
     */
    public function generate(
        string $promptText,
        Collection $clusterImages,
        string $aspectRatio,
        int $resolutionMultiplier = 1,
    ): string {
        if (! in_array($resolutionMultiplier, [1, 2, 4], true)) {
            $resolutionMultiplier = 1;
        }

        if ($resolutionMultiplier > 1) {
            $promptText .= "\n\nGenerate this image at {$resolutionMultiplier}x native resolution quality.";
        } else {
            $promptText .= "\n\nGenerate this image at native 1x resolution quality.";
        }

        $parts = [];

        foreach ($clusterImages as $image) {
            $reference = $image->brandReference;
            if (! $reference || ! Storage::disk('public')->exists($reference->url)) {
                continue;
            }

            try {
                $parts[] = $this->client->imageToInlinePart($reference->url);
            } catch (\Throwable) {
                continue;
            }
        }

        if ($parts === [] && $clusterImages->isNotEmpty()) {
            throw new RuntimeException('No valid reference images found for Gemini image generation.');
        }

        $parts[] = ['text' => $promptText];

        $base64 = $this->client->generateImage($this->model(), [['parts' => $parts]], $aspectRatio);
        $binary = base64_decode($base64, true);

        if ($binary === false) {
            throw new RuntimeException('Gemini returned invalid base64 image data.');
        }

        return $binary;
    }
}
