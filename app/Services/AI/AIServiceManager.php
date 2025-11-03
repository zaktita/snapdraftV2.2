<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

class AIServiceManager
{
    protected AIServiceInterface $primaryService;
    protected ?AIServiceInterface $fallbackService;

    public function __construct(
        GoogleGeminiService $geminiService,
        OpenRouterService $openRouterService
    ) {
        // Primary service: Google Gemini
        $this->primaryService = $geminiService;

        // Fallback service: OpenRouter (if available)
        $this->fallbackService = $openRouterService->isAvailable() ? $openRouterService : null;
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
     */
    public function generateWithReferences(
        string $prompt,
        array $referenceImagePaths,
        array $productImagePaths = [],
        string $format = 'square'
    ): array {
        try {
            Log::info('AIServiceManager: Attempting Style Mirror generation with primary service');
            
            // Use GoogleGeminiService's generateWithReferences method
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
     * Get the name of the currently active service.
     */
    public function getActiveServiceName(): string
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