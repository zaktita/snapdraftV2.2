<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoogleGeminiService implements AIServiceInterface
{
    protected string $apiKey;
    protected string $model = 'gemini-2.0-flash-exp';
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key', '');
        $this->model = config('services.gemini.model', 'gemini-2.0-flash-exp');
    }

    /**
     * Analyze brand style from reference images.
     */
    public function analyzeBrandStyle(array $imageUrls): array
    {
        if (!$this->isAvailable()) {
            throw new \Exception('Google Gemini API is not configured');
        }

        Log::info('GoogleGeminiService::analyzeBrandStyle called', ['image_count' => count($imageUrls)]);

        try {
            // Convert image paths to base64
            $imageParts = [];
            foreach (array_slice($imageUrls, 0, 5) as $imagePath) {
                $imageData = $this->fileToBase64($imagePath);
                if ($imageData) {
                    $imageParts[] = [
                        'inline_data' => [
                            'mime_type' => $imageData['mime_type'],
                            'data' => $imageData['data']
                        ]
                    ];
                }
            }

            if (empty($imageParts)) {
                throw new \Exception('No valid images found for brand analysis');
            }

            // Create analysis prompt
            $prompt = "Analyze these brand reference images and extract the visual style. Identify:\n\n";
            $prompt .= "1. Color palette (primary, secondary, accent colors with hex codes)\n";
            $prompt .= "2. Typography style (modern, classic, playful, professional)\n";
            $prompt .= "3. Composition patterns (layout, balance, whitespace)\n";
            $prompt .= "4. Overall mood and tone (elegant, bold, minimal, etc.)\n\n";
            $prompt .= "Return ONLY a valid JSON object with keys: colors, typography, composition, mood";

            // Build request
            $contents = [
                [
                    'role' => 'user',
                    'parts' => array_merge(
                        [['text' => $prompt]],
                        $imageParts
                    )
                ]
            ];

            // Call Gemini API
            $response = Http::timeout(60)
                ->post("{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}", [
                    'contents' => $contents,
                    'generationConfig' => [
                        'temperature' => 0.4,
                        'topK' => 32,
                        'topP' => 1,
                        'maxOutputTokens' => 2048,
                    ]
                ]);

            if (!$response->successful()) {
                throw new \Exception('Gemini API error: ' . $response->body());
            }

            $result = $response->json();
            
            // Extract text response
            $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
            
            if (!$text) {
                throw new \Exception('No text response from Gemini');
            }

            // Clean and parse JSON response
            $text = trim($text);
            $text = preg_replace('/```json\s*/', '', $text);
            $text = preg_replace('/```\s*$/', '', $text);
            $text = trim($text);

            $styleData = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Failed to parse JSON from Gemini, using default structure', ['text' => $text]);
                // Return default structure
                $styleData = [
                    'colors' => ['primary' => '#000000', 'secondary' => '#FFFFFF'],
                    'typography' => ['style' => 'modern'],
                    'composition' => ['layout' => 'balanced'],
                    'mood' => 'professional'
                ];
            }

            // Add metadata
            $styleData['analyzed_at'] = now()->toIso8601String();
            $styleData['model'] = $this->model;

            Log::info('Brand analysis completed', ['style' => $styleData]);

            return $styleData;

        } catch (\Exception $e) {
            Log::error('Brand analysis failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Generate an image from a prompt and style guide.
     */
    public function generateImage(string $prompt, ?array $styleGuide = null, string $format = 'square'): array
    {
        if (!$this->isAvailable()) {
            throw new \Exception('Google Gemini API is not configured');
        }

        Log::info('GoogleGeminiService::generateImage called', [
            'prompt' => $prompt,
            'format' => $format,
            'has_style_guide' => !is_null($styleGuide),
        ]);

        // This method is for text-to-image which Gemini doesn't directly support
        // Instead, we need to use generateWithReferences for image generation
        throw new \Exception('Use generateWithReferences method for image generation with Gemini');
    }

    /**
     * Generate image with reference images (Style Mirror approach from your Node.js code)
     */
    public function generateWithReferences(
        string $prompt,
        array $referenceImagePaths,
        array $productImagePaths = [],
        string $format = 'square'
    ): array {
        if (!$this->isAvailable()) {
            throw new \Exception('Google Gemini API is not configured');
        }

        Log::info('Generating image with Style Mirror approach', [
            'prompt' => substr($prompt, 0, 100),
            'references' => count($referenceImagePaths),
            'products' => count($productImagePaths)
        ]);

        try {
            // Use image generation model
            $imageModel = 'gemini-2.0-flash-exp';

            // Convert reference images to base64 (up to 5)
            $referenceImageParts = [];
            foreach (array_slice($referenceImagePaths, 0, 5) as $imagePath) {
                $imageData = $this->fileToBase64($imagePath);
                if ($imageData) {
                    $referenceImageParts[] = [
                        'inline_data' => [
                            'mime_type' => $imageData['mime_type'],
                            'data' => $imageData['data']
                        ]
                    ];
                }
            }

            // Convert product images to base64
            $productImageParts = [];
            $productNames = [];
            foreach ($productImagePaths as $imagePath) {
                $imageData = $this->fileToBase64($imagePath);
                if ($imageData) {
                    $productImageParts[] = [
                        'inline_data' => [
                            'mime_type' => $imageData['mime_type'],
                            'data' => $imageData['data']
                        ]
                    ];
                    // Extract filename without extension
                    $productNames[] = pathinfo($imagePath, PATHINFO_FILENAME);
                }
            }

            // Build the generation prompt (matching your Node.js logic)
            $fullPrompt = "generate a creative visual for this caption: {$prompt}\n\n";
            $fullPrompt .= "keep the branding, colors and style similar to the reference images\n";
            $fullPrompt .= "keep the text to a minimum on the visual.\n";
            $fullPrompt .= "no need to include the caption text on the image.\n";
            $fullPrompt .= "make sure any text is correct and properly spelled.";

            if (!empty($productNames)) {
                if (count($productNames) === 1) {
                    $fullPrompt .= "\n\nuse the image named {$productNames[0]} as the featured product.\n";
                    $fullPrompt .= "don't use any other products except the products provided.";
                } else {
                    $productList = implode(', ', $productNames);
                    $fullPrompt .= "\n\nuse the images named {$productList} as the featured products.\n";
                    $fullPrompt .= "don't use any other products except the products provided.";
                }
            }

            // Build request parts
            $parts = array_merge(
                [['text' => $fullPrompt]],
                $referenceImageParts,
                $productImageParts
            );

            // Call Gemini API
            $startTime = microtime(true);
            $response = Http::timeout(120)
                ->post("{$this->baseUrl}/models/{$imageModel}:generateContent?key={$this->apiKey}", [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => $parts
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 1.0,
                        'topK' => 40,
                        'topP' => 0.95,
                        'maxOutputTokens' => 8192,
                    ]
                ]);

            $generationTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            if (!$response->successful()) {
                throw new \Exception('Gemini API error: ' . $response->body());
            }

            $result = $response->json();

            // Extract generated image
            if (isset($result['candidates'][0]['content']['parts'])) {
                foreach ($result['candidates'][0]['content']['parts'] as $part) {
                    if (isset($part['inline_data']['data'])) {
                        $imageBase64 = $part['inline_data']['data'];
                        $mimeType = $part['inline_data']['mime_type'];

                        Log::info('Image generated successfully', [
                            'size_kb' => strlen($imageBase64) / 1024,
                            'mime_type' => $mimeType
                        ]);

                        return [
                            'image_data' => $imageBase64,
                            'mime_type' => $mimeType,
                            'model' => $imageModel,
                            'prompt' => $fullPrompt,
                            'metadata' => [
                                'format' => $format,
                                'style_mirror' => true,
                                'generation_time_ms' => round($generationTime),
                                'tokens_used' => $result['usageMetadata']['totalTokenCount'] ?? 0,
                                'prompt_tokens' => $result['usageMetadata']['promptTokenCount'] ?? 0,
                                'generated_at' => now()->toIso8601String(),
                            ],
                        ];
                    }
                }
            }

            throw new \Exception('No image data in Gemini response');

        } catch (\Exception $e) {
            Log::error('Image generation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Convert file path to base64 data
     */
    protected function fileToBase64(string $filePath): ?array
    {
        try {
            // Check if it's a storage path
            if (Storage::disk('public')->exists($filePath)) {
                $content = Storage::disk('public')->get($filePath);
                $mimeType = Storage::disk('public')->mimeType($filePath);
            } elseif (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                $mimeType = mime_content_type($filePath);
            } else {
                Log::warning('File not found for base64 conversion', ['path' => $filePath]);
                return null;
            }

            return [
                'data' => base64_encode($content),
                'mime_type' => $mimeType ?: 'image/png'
            ];
        } catch (\Exception $e) {
            Log::error('Error converting file to base64', ['path' => $filePath, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get the service name.
     */
    public function getServiceName(): string
    {
        return 'Google Gemini';
    }

    /**
     * Check if service is available.
     */
    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }
}
