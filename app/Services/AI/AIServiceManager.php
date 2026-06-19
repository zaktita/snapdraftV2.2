<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Manages primary/fallback AI provider routing.
 *
 * Configuration (config/services.php under 'ai' key):
 *   preferred       – 'gemini' | 'openrouter'
 *   enable_fallback – bool
 */
class AIServiceManager
{
    public function __construct(
        protected GoogleGeminiService $gemini,
        protected OpenRouterService   $openRouter,
    ) {}

    /**
     * Generate an image with optional brand reference images.
     *
     * @param  string   $prompt
     * @param  string[] $referenceImages
     * @param  string[] $productImages
     * @param  string   $format  square|portrait|landscape
     * @return array{image_data: string, mime_type: string}
     */
    public function generateWithReferences(
        string $prompt,
        array  $referenceImages = [],
        array  $productImages   = [],
        string $format          = 'square',
    ): array {
        return $this->callWithFallback(
            fn ($svc) => $svc->generateWithReferences($prompt, $referenceImages, $productImages, $format),
            'generateWithReferences',
        );
    }

    /**
     * Analyse brand style from reference images.
     *
     * @param  string[] $imagePaths
     * @return array
     */
    public function analyzeBrandStyle(array $imagePaths): array
    {
        return $this->callWithFallback(
            fn ($svc) => $svc->analyzeBrandStyle($imagePaths),
            'analyzeBrandStyle',
        );
    }

    /**
     * Outpaint / extend image content.
     *
     * @param  string $imageBase64
     * @param  string $maskBase64
     * @return string  Base64-encoded result image
     */
    public function outpaint(string $imageBase64, string $maskBase64): string
    {
        return $this->callWithFallback(
            fn ($svc) => $svc->outpaint($imageBase64, $maskBase64),
            'outpaint',
        );
    }

    /**
     * Erase green-highlighted areas from an image.
     *
     * @param  string $imageBase64
     * @return string  Base64-encoded result image
     */
    public function eraseGreenHighlights(string $imageBase64): string
    {
        return $this->callWithFallback(
            fn ($svc) => $svc->eraseGreenHighlights($imageBase64),
            'eraseGreenHighlights',
        );
    }

    /**
     * Edit an image given a text prompt and optional mask.
     *
     * @param  string      $imageBase64
     * @param  string      $prompt
     * @param  string|null $maskBase64
     * @return string  Base64-encoded result image
     */
    public function editBase64(
        string $imageBase64,
        string $prompt,
        ?string $maskBase64 = null,
        ?string $aspectRatio = null,
    ): string {
        return $this->callWithFallback(
            fn ($svc) => $svc->editBase64($imageBase64, $prompt, $maskBase64, $aspectRatio),
            'editBase64',
        );
    }

    /**
     * Generate/inpaint an image region based on a text prompt and mask.
     *
     * @param  string $imageBase64
     * @param  string $maskBase64
     * @param  string $prompt
     * @return string  Base64-encoded result image
     */
    public function generateFromPrompt(string $imageBase64, string $maskBase64, string $prompt): string
    {
        return $this->callWithFallback(
            fn ($svc) => $svc->generateFromPrompt($imageBase64, $maskBase64, $prompt),
            'generateFromPrompt',
        );
    }

    /**
     * Return the configured primary model name.
     */
    public function getActiveModelName(): string
    {
        return $this->primaryService()->getModelName();
    }

    // -------------------------------------------------------------------------

    /**
     * Resolve primary → fallback service order and invoke $callback on them.
     */
    private function callWithFallback(callable $callback, string $method): mixed
    {
        [$primary, $fallback] = $this->serviceOrder();

        try {
            return $callback($primary);
        } catch (\Throwable $primaryError) {
            Log::warning("AIServiceManager: primary provider failed for {$method}", [
                'provider' => config('services.ai.preferred', 'gemini'),
                'error'    => $primaryError->getMessage(),
            ]);

            if (!config('services.ai.enable_fallback', true)) {
                throw new \App\Exceptions\AIServiceUnavailableException(
                    $this->classifyError($primaryError),
                );
            }

            try {
                return $callback($fallback);
            } catch (\Throwable $fallbackError) {
                Log::error("AIServiceManager: both providers failed for {$method}", [
                    'primary_error'  => $primaryError->getMessage(),
                    'fallback_error' => $fallbackError->getMessage(),
                ]);

                throw new \App\Exceptions\AIServiceUnavailableException(
                    $this->classifyError($primaryError, $fallbackError),
                );
            }
        }
    }

    /**
     * Return a short, user-facing message describing the failure cause.
     */
    private function classifyError(\Throwable $primary, ?\Throwable $fallback = null): string
    {
        $combined = strtolower($primary->getMessage() . ($fallback ? ' ' . $fallback->getMessage() : ''));

        if (str_contains($combined, 'rate limit') || str_contains($combined, 'quota') || str_contains($combined, '429')) {
            return 'The AI service is currently rate-limited. Please try again in a few minutes.';
        }

        if (str_contains($combined, 'unauthorized') || str_contains($combined, '401') || str_contains($combined, 'invalid api key') || str_contains($combined, 'api_key')) {
            return 'AI service authentication failed. Please contact support.';
        }

        if (str_contains($combined, 'timed out') || str_contains($combined, 'timeout') || str_contains($combined, 'deadline')) {
            return 'The AI service timed out. Please try again.';
        }

        if (str_contains($combined, 'unavailable') || str_contains($combined, '503') || str_contains($combined, '502') || str_contains($combined, '500')) {
            return 'The AI service is temporarily unavailable. Please try again shortly.';
        }

        if (str_contains($combined, 'content') || str_contains($combined, 'safety') || str_contains($combined, 'policy')) {
            return 'This content was blocked by the AI service safety filter. Try adjusting the description.';
        }

        return 'The AI service failed to generate the image. Please try again.';
    }

    /**
     * @return array{0: GoogleGeminiService|OpenRouterService, 1: GoogleGeminiService|OpenRouterService}
     */
    private function serviceOrder(): array
    {
        $preferred = config('services.ai.preferred', 'gemini');

        return $preferred === 'openrouter'
            ? [$this->openRouter, $this->gemini]
            : [$this->gemini, $this->openRouter];
    }

    private function primaryService(): GoogleGeminiService|OpenRouterService
    {
        return $this->serviceOrder()[0];
    }
}
