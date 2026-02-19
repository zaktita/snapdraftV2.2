<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

class AIServiceManager
{
    protected AIServiceInterface $primaryService;
    protected ?AIServiceInterface $fallbackService;

    protected OpenRouterService $openRouterService;

    public function __construct(
        GoogleGeminiService $geminiService,
        OpenRouterService $openRouterService
    ) {
        // Primary service: Google Gemini (gemini-3-pro-image-preview)
        $this->primaryService = $geminiService;

        // Fallback service: OpenRouter (if available)
        $this->fallbackService = $openRouterService->isAvailable() ? $openRouterService : null;

        // Keep a direct reference for canvas editor routing
        $this->openRouterService = $openRouterService;
    }

    // -------------------------------------------------------------------------
    // Canvas Editor Methods
    // -------------------------------------------------------------------------

    /**
     * Erase green-highlighted areas using Gemini.
     */
    public function eraseGreenHighlights(string $imageBase64): string
    {
        Log::info('AIServiceManager: eraseGreenHighlights → Gemini');
        return $this->primaryService->eraseGreenHighlights($imageBase64);
    }

    /**
     * Replace text in masked area using Gemini (green-highlight composite image).
     * The prompt is already embedded client-side; we delegate straight to Gemini editBase64.
     */
    public function inpaint(string $imageBase64, string $maskBase64, string $prompt): string
    {
        Log::info('AIServiceManager: inpaint (replace-text) → Gemini');
        return $this->primaryService->inpaint($imageBase64, $maskBase64, $prompt);
    }

    /**
     * AI Edit (full image or green-highlighted composite) using Gemini.
     */
    public function editBase64(string $imageBase64, string $prompt, ?string $maskBase64 = null): string
    {
        Log::info('AIServiceManager: editBase64 (ai-edit) → Gemini');
        return $this->primaryService->editBase64($imageBase64, $prompt, $maskBase64);
    }

    /**
     * Analyze brand style using available service.
     */
    public function analyzeBrandStyle(array $imageUrls): array
    {
        try {
            Log::info('AIServiceManager: Attempting brand analysis with primary service');
            return $this->primaryService->analyzeBrandStyle($imageUrls);
        } catch (\Exception $e) {
            Log::error('AIServiceManager: Primary service failed for brand analysis', [
                'error' => $e->getMessage(),
            ]);

            if ($this->fallbackService) {
                Log::info('AIServiceManager: Attempting brand analysis with fallback service');
                try {
                    return $this->fallbackService->analyzeBrandStyle($imageUrls);
                } catch (\Exception $fallbackError) {
                    Log::error('AIServiceManager: Fallback service also failed', [
                        'error' => $fallbackError->getMessage(),
                    ]);
                }
            }

            throw new \Exception('All AI services failed for brand analysis: ' . $e->getMessage());
        }
    }

    /**
     * Generate image using available service.
     */
    public function generateImage(string $prompt, ?array $styleGuide = null, string $format = 'square'): array
    {
        try {
            Log::info('AIServiceManager: Attempting image generation with primary service');
            return $this->primaryService->generateImage($prompt, $styleGuide, $format);
        } catch (\Exception $e) {
            Log::error('AIServiceManager: Primary service failed for image generation', [
                'error' => $e->getMessage(),
            ]);

            if ($this->fallbackService) {
                Log::info('AIServiceManager: Attempting image generation with fallback service');
                try {
                    return $this->fallbackService->generateImage($prompt, $styleGuide, $format);
                } catch (\Exception $fallbackError) {
                    Log::error('AIServiceManager: Fallback service also failed', [
                        'error' => $fallbackError->getMessage(),
                    ]);
                }
            }

            throw new \Exception('All AI services failed for image generation: ' . $e->getMessage());
        }
    }

    /**
     * Generate image with reference images (Style Mirror approach)
     * 
     * @param string $prompt User's generation prompt
     * @param array $referenceImagePaths Paths to brand reference images
     * @param array $productImagePaths Paths to product overlay images
     * @param string $format Image format (square, portrait, landscape, story)
     * @return array Generation result
     */
    public function generateWithReferences(
        string $prompt,
        array $referenceImagePaths,
        array $productImagePaths = [],
        string $format = 'square'
    ): array {
        try {
            Log::info('AIServiceManager: Attempting Style Mirror generation with primary service');
            
            // Use Primary Service's generateWithReferences method
            if (method_exists($this->primaryService, 'generateWithReferences')) {
                return $this->primaryService->generateWithReferences(
                    $prompt,
                    $referenceImagePaths,
                    $productImagePaths,
                    $format
                );
            }

            throw new \Exception('Primary service does not support generateWithReferences');
        } catch (\Exception $e) {
            Log::error('AIServiceManager: Primary service failed for Style Mirror generation', [
                'error' => $e->getMessage(),
            ]);

            if ($this->fallbackService && method_exists($this->fallbackService, 'generateWithReferences')) {
                Log::info('AIServiceManager: Attempting Style Mirror generation with fallback service');
                try {
                    return $this->fallbackService->generateWithReferences(
                        $prompt,
                        $referenceImagePaths,
                        $productImagePaths,
                        $format
                    );
                } catch (\Exception $fallbackError) {
                    Log::error('AIServiceManager: Fallback service also failed', [
                        'error' => $fallbackError->getMessage(),
                    ]);
                }
            }

            throw new \Exception('All AI services failed for Style Mirror generation: ' . $e->getMessage());
        }
    }

    /**
     * Edit image with mask (inpainting).
     */
    public function editWithMask(string $originalImagePath, string $maskImagePath, string $prompt): array
    {
        try {
            Log::info('AIServiceManager: Attempting masked image edit with primary service');
            
            // Use GoogleGeminiService's editWithMask method
            if (method_exists($this->primaryService, 'editWithMask')) {
                return $this->primaryService->editWithMask(
                    $originalImagePath,
                    $maskImagePath,
                    $prompt
                );
            }

            throw new \Exception('Primary service does not support editWithMask');
        } catch (\Exception $e) {
            Log::error('AIServiceManager: Primary service failed for masked image edit', [
                'error' => $e->getMessage(),
            ]);

            if ($this->fallbackService && method_exists($this->fallbackService, 'editWithMask')) {
                Log::info('AIServiceManager: Attempting masked image edit with fallback service');
                try {
                    return $this->fallbackService->editWithMask(
                        $originalImagePath,
                        $maskImagePath,
                        $prompt
                    );
                } catch (\Exception $fallbackError) {
                    Log::error('AIServiceManager: Fallback service also failed', [
                        'error' => $fallbackError->getMessage(),
                    ]);
                }
            }

            throw new \Exception('All AI services failed for masked image edit: ' . $e->getMessage());
        }
    }

    /**
     * Get the name of the active model that WILL be used for generation.
     * Centralizes logic for UI display and history tracking.
     */
    public function getActiveModelName(): string
    {
        // Always use Gemini 3 Pro Image Preview
        return config('services.gemini.text_accurate_model', 'gemini-3-pro-image-preview');
    }

    /**
     * Get the service name for the currently active service.
     * @deprecated Use getActiveModelName() instead
     */
    public function getServiceName(): string
    {
        return $this->primaryService->getServiceName();
    }

    /**
     * Check if any AI service is available.
     */
    public function isAvailable(): bool
    {
        return $this->primaryService->isAvailable() || ($this->fallbackService && $this->fallbackService->isAvailable());
    }
}