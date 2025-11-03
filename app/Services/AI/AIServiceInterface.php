<?php

namespace App\Services\AI;

interface AIServiceInterface
{
    /**
     * Analyze brand style from reference images.
     *
     * @param array $imageUrls Array of image file paths
     * @return array Style analysis data (colors, typography, composition, etc.)
     */
    public function analyzeBrandStyle(array $imageUrls): array;

    /**
     * Generate an image from a prompt and style guide.
     *
     * @param string $prompt Text prompt for generation
     * @param array|null $styleGuide Brand style guide data from analyzeBrandStyle()
     * @param string $format Image format (square, portrait, landscape)
     * @return array Generated image data (url, metadata)
     */
    public function generateImage(string $prompt, ?array $styleGuide = null, string $format = 'square'): array;

    /**
     * Get the name of the AI service/model.
     *
     * @return string
     */
    public function getServiceName(): string;

    /**
     * Check if the service is available and configured.
     *
     * @return bool
     */
    public function isAvailable(): bool;
}
