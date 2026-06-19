<?php

namespace App\Services\AI;

use App\Models\ProjectClusterImage;
use App\Services\AI\OpenRouter\OpenRouterCsvImageGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Routes CSV / PromptForge image generation across Gemini and OpenRouter.
 *
 * Driver (config ai.image_driver):
 *   auto       – Gemini first, OpenRouter on auth/config failures when fallback enabled
 *   gemini     – Gemini only
 *   openrouter – OpenRouter only
 */
class CsvImageGenerationService
{
    public function __construct(
        protected GeminiCsvImageGenerator $gemini,
        protected OpenRouterCsvImageGenerator $openRouter,
    ) {}

    /**
     * @param  array<string, mixed>  $promptJson
     * @param  Collection<int, ProjectClusterImage>  $clusterImages
     */
    public function generateFromPromptJson(
        array $promptJson,
        Collection $clusterImages,
        string $aspectRatio = '1:1',
        int $resolutionMultiplier = 1,
        ?string $caption = null,
        ?string $imageRequestPrompt = null,
    ): string {
        $driver = config('ai.image_driver', 'auto');

        if ($driver === 'openrouter') {
            return $this->openRouter->generateFromPromptJson(
                $promptJson,
                $clusterImages,
                $aspectRatio,
                $resolutionMultiplier,
                $caption,
                $imageRequestPrompt,
            );
        }

        if ($driver === 'gemini') {
            return $this->gemini->generateFromPromptJson(
                $promptJson,
                $clusterImages,
                $aspectRatio,
                $resolutionMultiplier,
                $caption,
                $imageRequestPrompt,
            );
        }

        try {
            return $this->gemini->generateFromPromptJson(
                $promptJson,
                $clusterImages,
                $aspectRatio,
                $resolutionMultiplier,
                $caption,
                $imageRequestPrompt,
            );
        } catch (Throwable $primaryError) {
            if (! GeminiOpenRouterFallback::shouldFallback($primaryError)) {
                throw $primaryError;
            }

            Log::warning('CsvImageGenerationService: Gemini failed, falling back to OpenRouter', [
                'error' => $primaryError->getMessage(),
            ]);

            return $this->openRouter->generateFromPromptJson(
                $promptJson,
                $clusterImages,
                $aspectRatio,
                $resolutionMultiplier,
                $caption,
                $imageRequestPrompt,
            );
        }
    }

    public function model(): string
    {
        $driver = config('ai.image_driver', 'auto');

        if ($driver === 'openrouter') {
            return $this->openRouter->model();
        }

        return $this->gemini->model();
    }
}
