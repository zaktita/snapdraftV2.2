<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoogleGeminiService implements AIServiceInterface
{
    protected ?string $apiKey;
    // Text/analysis model
    protected string $textModel = 'gemini-2.0-flash-exp';
    // Image generation model (from server.js.example)
    protected string $imageModel = 'gemini-2.5-flash-image';
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->textModel = config('services.gemini.model', 'gemini-2.0-flash-exp');
        $this->imageModel = config('services.gemini.image_model', 'gemini-2.5-flash-image');
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
                        'inlineData' => [
                            'mimeType' => $imageData['mime_type'],
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
            $response = $this->http()->timeout(60)
                ->post("{$this->baseUrl}/models/{$this->textModel}:generateContent?key={$this->apiKey}", [
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
            $styleData['model'] = $this->textModel;

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
            // Generate a test/placeholder image when API is not configured
            Log::warning('Google Gemini API not configured, generating placeholder image');
            return $this->generatePlaceholderImage($prompt, $format);
        }

        Log::info('Generating image with Style Mirror approach', [
            'prompt' => substr($prompt, 0, 100),
            'references' => count($referenceImagePaths),
            'products' => count($productImagePaths)
        ]);

        try {
            // Use image generation model (matches server.js.example)
            $imageModel = $this->imageModel;

            // Convert reference images to base64 (up to 5)
            $referenceImageParts = [];
            foreach (array_slice($referenceImagePaths, 0, 5) as $imagePath) {
                $imageData = $this->fileToBase64($imagePath);
                if ($imageData) {
                    $referenceImageParts[] = [
                        'inlineData' => [
                            'mimeType' => $imageData['mime_type'],
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
                        'inlineData' => [
                            'mimeType' => $imageData['mime_type'],
                            'data' => $imageData['data']
                        ]
                    ];
                    // Extract filename without extension
                    $productNames[] = pathinfo($imagePath, PATHINFO_FILENAME);
                }
            }

            // Build the generation prompt (adapted from server.js.example)
            $fullPrompt = "generate a creative visual for this caption : {$prompt}\n\n";
            $fullPrompt .= "keep the branding, colors and style similar the reference images\n";
            $fullPrompt .= "keep the text to a minimum on the visual.\n";
            $fullPrompt .= "no need to include the caption text on the image.\n";
            $fullPrompt .= "make sure the text is correct and properly spelled.";

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
            $response = $this->http()->timeout(120)
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
                    if (isset($part['inlineData']['data'])) {
                        $imageBase64 = $part['inlineData']['data'];
                        $mimeType = $part['inlineData']['mimeType'];

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

    /**
     * Generate a placeholder image for testing when API is not configured.
     */
    protected function generatePlaceholderImage(string $prompt, string $format): array
    {
        // Determine image dimensions based on format
        $dimensions = match($format) {
            'square' => ['width' => 1080, 'height' => 1080],
            'portrait' => ['width' => 1080, 'height' => 1350],
            'landscape' => ['width' => 1200, 'height' => 628],
            'story' => ['width' => 1080, 'height' => 1920],
            default => ['width' => 1080, 'height' => 1080],
        };

        // Create a simple colored rectangle as placeholder
        $image = imagecreatetruecolor($dimensions['width'], $dimensions['height']);
        
        // Set a gradient-like background
        $colors = [
            imagecolorallocate($image, 66, 135, 245),  // Blue
            imagecolorallocate($image, 99, 102, 241),  // Indigo
            imagecolorallocate($image, 139, 92, 246),  // Purple
        ];
        
        $colorIndex = abs(crc32($prompt)) % count($colors);
        $bgColor = $colors[$colorIndex];
        imagefilledrectangle($image, 0, 0, $dimensions['width'], $dimensions['height'], $bgColor);
        
        // Add text overlay
        $white = imagecolorallocate($image, 255, 255, 255);
        $textColor = $white;
        
        // Add "TEST MODE" watermark
        $fontSize = 24;
        $testText = "TEST MODE - Configure GEMINI_API_KEY";
        imagettftext($image, $fontSize, 0, 50, 80, $textColor, $this->getSystemFont(), $testText);
        
        // Add prompt text (wrapped)
        $promptLines = $this->wrapText($prompt, 40);
        $y = 180;
        foreach ($promptLines as $line) {
            imagettftext($image, 32, 0, 50, $y, $textColor, $this->getSystemFont(), $line);
            $y += 50;
        }
        
        // Add format info
        $formatText = "Format: {$format} ({$dimensions['width']}x{$dimensions['height']})";
        imagettftext($image, 18, 0, 50, $dimensions['height'] - 50, $textColor, $this->getSystemFont(), $formatText);
        
        // Convert to PNG
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);
        
        return [
            'image_data' => base64_encode($imageData),
            'mime_type' => 'image/png',
            'model' => 'placeholder-generator',
            'metadata' => [
                'is_placeholder' => true,
                'prompt' => $prompt,
                'format' => $format,
                'tokens_used' => 0,
                'dimensions' => $dimensions,
            ],
        ];
    }

    /**
     * Get system font path for placeholder text.
     */
    protected function getSystemFont(): string
    {
        // Try to find a system font
        $fontPaths = [
            'C:/Windows/Fonts/arial.ttf',
            'C:/Windows/Fonts/Arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/System/Library/Fonts/Helvetica.ttc',
        ];

        foreach ($fontPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Fallback: use GD's built-in font (will be ignored by imagettftext, so we'd need imagestring)
        return '';
    }

    /**
     * Wrap text to fit within a certain width.
     */
    protected function wrapText(string $text, int $maxChars): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            if (strlen($currentLine . ' ' . $word) <= $maxChars) {
                $currentLine .= ($currentLine ? ' ' : '') . $word;
            } else {
                if ($currentLine) {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;
            }
        }

        if ($currentLine) {
            $lines[] = $currentLine;
        }

        return array_slice($lines, 0, 5); // Limit to 5 lines
    }

    /**
     * HTTP client with dev-friendly SSL settings on Windows/Laragon.
     */
    protected function http()
    {
        $client = Http::acceptJson();
        // On non-production environments, disable SSL verify to avoid local CA issues
        if (config('app.env') !== 'production') {
            $client = $client->withOptions(['verify' => false]);
        }
        return $client;
    }
}

