<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GoogleGeminiService implements AIServiceInterface
{
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    protected ?string $apiKey;
    protected string $model = 'gemini-2.5-flash-image';
    protected string $textAccurateModel = 'gemini-3-pro-image-preview';
    protected ?string $currentFormat = null;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.image_model', 'gemini-2.5-flash-image');
        $this->textAccurateModel = config('services.gemini.text_accurate_model', 'gemini-3-pro-image-preview');
    }

    /**
     * Generate an image directly from a prompt + optional references.
     * No separate analysis step needed.
     * 
     * @param string $prompt User's generation prompt
     * @param array $referencePaths Paths to brand reference images
     * @param array $productImagePaths Paths to product overlay images
     * @param string $format Image format (square, portrait, landscape, story)
     * @param bool $textAccurate Use text-accurate model (4x credits)
     * @return array Generation result with image_data, mime_type, model, metadata
     */
    public function generateWithReferences(
        string $prompt,
        array $referencePaths,
        array $productImagePaths = [],
        string $format = 'square',
        bool $textAccurate = false
    ): array {
        // Prepend aspect ratio instruction at the beginning for highest priority
        $aspectRatioInstruction = $this->getAspectRatioInstruction($format);
        $enhancedPrompt = $aspectRatioInstruction . "\n\n---\n\n" . $prompt;
        
        // Pass format through for context in logging
        $this->currentFormat = $format;
        
        return $this->generate($enhancedPrompt, array_merge($referencePaths, $productImagePaths), $textAccurate, $format);
    }

    /**
     * Get aspect ratio instruction based on format
     */
    private function getAspectRatioInstruction(string $format): string
    {
        $ratio = $this->normalizeAspectRatio($format);
        [$w, $h] = array_map('intval', explode(':', $ratio));
        $orientation = $w === $h ? 'square' : ($w > $h ? 'landscape' : 'portrait');
        $multiplier = $h > 0 ? round($w / $h, 2) : $ratio;

        return "[ASPECT RATIO CONSTRAINT - NON-NEGOTIABLE] Output MUST be {$ratio} aspect ratio ({$orientation}). Width:Height = {$ratio} (≈ {$multiplier}:1 when width>=height). Zero tolerance for deviation. NOT any other ratio.";
    }

    /**
     * Generate an image directly from a prompt + optional references.
     * No separate analysis step needed.
     */
    public function generate(
        string $prompt,
        array $referencePaths = [],
        bool $textAccurate = false,
        string $format = 'square'
    ): array {
        $this->ensureConfigured();
        $this->currentFormat = $format;

        // Select model based on text accuracy requirement
        $selectedModel = $textAccurate ? $this->textAccurateModel : $this->model;

        // 1. Build the request parts
        // Text prompt goes first
        $parts = [
            ['text' => $prompt]
        ];

        // 2. Add reference images directly to the payload
        // The model "sees" these and uses them for style/content context automatically
        foreach (array_slice($referencePaths, 0, 5) as $path) {
            if ($img = $this->fileToPart($path)) {
                $parts[] = $img;
            }
        }

        Log::info('Gemini Direct Generation', [
            'model' => $selectedModel,
            'references' => count($referencePaths),
            'text_accurate' => $textAccurate,
            'credits_multiplier' => $textAccurate ? 4 : 1,
        ]);

        // 3. Fire the request
        $startTime = microtime(true);
        
        // For Imagen 3 (gemini-3-pro-preview), we might need to use the imagen-3.0-generate-001 endpoint structure
        // But if it's a Gemini model, it should use generateContent.
        // Let's try removing responseModalities for the preview model if it's causing issues, 
        // or check if the model name is correct.
        
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => $parts
                ]
            ],
            'generationConfig' => [
                'responseModalities' => ['IMAGE'],
                'imageConfig' => [
                    'aspectRatio' => $this->getAspectRatioForAPI($this->currentFormat),

                ]                
            ]
        ];

        // If textAccurate is true, we are using the configured text accurate model (e.g. gemini-2.0-flash-exp)
        // We ensure responseModalities is set to IMAGE to force image generation.
        if ($textAccurate) {
             $payload['generationConfig']['responseModalities'] = ['IMAGE'];
        }

        $response = $this->http()->post(
            "{$this->baseUrl}/models/{$selectedModel}:generateContent?key={$this->apiKey}",
            $payload
        );
        $generationTime = (microtime(true) - $startTime) * 1000;

        $result = $this->parseResponse($response);
        
        // Add metadata for credits tracking and debugging
        $result['metadata'] = [
            'model' => $selectedModel,
            'text_accurate' => $textAccurate,
            'credits_multiplier' => $textAccurate ? 4 : 1,
            'generation_time_ms' => round($generationTime),
            'generated_at' => now()->toIso8601String(),
        ];

        return $result;
    }

    /**
     * Get API-compatible aspect ratio for Gemini's imageConfig
     */
    private function getAspectRatioForAPI(?string $format): string
    {
        return $this->normalizeAspectRatio($format ?? '');
    }

    /**
     * Normalize various format labels to an aspect ratio string.
     */
    private function normalizeAspectRatio(string $format): string
    {
        return match(strtolower($format)) {
            'square', '1:1', 'instagram-post' => '1:1',
            'portrait', '9:16', 'instagram-story' => '9:16',
            'landscape', '16:9', 'facebook-post', 'facebook-ad', 'linkedin-post', 'twitter-post', 'youtube-thumbnail' => '16:9',
            'linkedin-banner' => '4:1',
            default => preg_match('/^\d+:\d+$/', $format) ? $format : '1:1',
        };
    }

    /**
     * Edit an image (Inpainting/Outpainting) from file path
     * You just pass the image + "Change X to Y"
     */
    public function edit(string $imagePath, string $prompt, ?string $maskPath = null): array
    {
        $this->ensureConfigured();

        $imagePart = $this->fileToPart($imagePath);
        if (!$imagePart) {
            throw new RuntimeException("Image file not found: $imagePath");
        }

        // Gemini 2.5 follows natural language instructions for editing
        $parts = [
            ['text' => $prompt], 
            $imagePart
        ];

        // Add mask if provided
        if ($maskPath) {
            $maskPart = $this->fileToPart($maskPath);
            if ($maskPart) {
                $parts[] = $maskPart;
            }
        }

        Log::info('Gemini Image Edit', [
            'has_mask' => !is_null($maskPath),
            'prompt' => substr($prompt, 0, 100),
        ]);

        $startTime = microtime(true);
        $response = $this->http()->post(
            "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [['role' => 'user', 'parts' => $parts]],
                'generationConfig' => [
                    'responseModalities' => ['IMAGE'],
                    'temperature' => 0.4,
                ]
            ]
        );
        $generationTime = (microtime(true) - $startTime) * 1000;

        $result = $this->parseResponse($response);
        
        $result['metadata'] = [
            'model' => $this->model,
            'operation' => 'edit',
            'generation_time_ms' => round($generationTime),
            'generated_at' => now()->toIso8601String(),
        ];

        return $result;
    }
/**
     * Edit an image using base64 data (for Canvas Editor)
     * Supports inpainting with mask
     */
    public function editBase64(string $imageBase64, string $prompt, ?string $maskBase64 = null): string
    {
        $this->ensureConfigured();

        $parts = [
            ['text' => $prompt],
            [
                'inlineData' => [
                    'mimeType' => 'image/png',
                    'data' => $imageBase64
                ]
            ]
        ];

        // Add mask if provided
        if ($maskBase64) {
            $parts[] = [
                'inlineData' => [
                    'mimeType' => 'image/png',
                    'data' => $maskBase64
                ]
            ];
        }

        Log::info('Gemini Base64 Edit', [
            'has_mask' => !is_null($maskBase64),
            'prompt' => substr($prompt, 0, 100),
        ]);

        $response = $this->http()->post(
            "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [['role' => 'user', 'parts' => $parts]],
                'generationConfig' => [
                    'responseModalities' => ['IMAGE'],
                    'temperature' => 0.4,
                ]
            ]
        );

        $result = $this->parseResponse($response);

        // Back-compat: some callers expect image_base64, others image_data
        return $result['image_data'] ?? $result['image_base64'];
    }

    /**
     * Erase green-highlighted areas from an image.
     * The image should contain green (rgb 0,255,0) highlights where areas should be removed.
     * Returns the edited image with those areas naturally filled in.
     */
    public function eraseGreenHighlights(string $imageBase64): string
    {
        $this->ensureConfigured();

        $prompt = "Remove all green-highlighted areas from this image and fill them naturally with appropriate content that matches the surrounding context. The green highlights (bright green color RGB 0,255,0) indicate areas that should be erased and seamlessly replaced with background that fits the image style, perspective, colors, and lighting. Do not leave any green color or artifacts in the final result.";

        $parts = [
            ['text' => $prompt],
            [
                'inlineData' => [
                    'mimeType' => 'image/png',
                    'data' => $imageBase64
                ]
            ]
        ];

        Log::info('Gemini Erase Green Highlights', [
            'image_length' => strlen($imageBase64),
        ]);

        $response = $this->http()->post(
            "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [['role' => 'user', 'parts' => $parts]],
                'generationConfig' => [
                    'responseModalities' => ['IMAGE'],
                    'temperature' => 0.4,
                ]
            ]
        );

        $result = $this->parseResponse($response);

        // Back-compat: some callers expect image_base64, others image_data
        return $result['image_data'] ?? $result['image_base64'];
    }

    /**
     * Inpaint: Edit specific area of image (Canvas Editor)
     */
    public function inpaint(string $imageBase64, string $maskBase64, string $prompt): string
    {
        return $this->editBase64($imageBase64, $prompt, $maskBase64);
    }

    /**
     * Outpaint: Extend image beyond original boundaries (Canvas Editor)
     */
    public function outpaint(string $expandedImageBase64, string $maskBase64, string $prompt = ''): string
    {
        $defaultPrompt = "Fill the white masked areas so the scene extends naturally to match the original image's style, perspective, colors, and lighting.";
        $fullPrompt = $prompt ? $prompt . ' ' . $defaultPrompt : $defaultPrompt;
        
        return $this->editBase64($expandedImageBase64, $fullPrompt, $maskBase64);
    }

    /**
     * Generate from prompt in masked area (Canvas Editor)
     */
    public function generateFromPrompt(string $imageBase64, string $maskBase64, string $prompt): string
    {
        $enhancedPrompt = $prompt . " 

Positive Prompt: seamless inpainting, background reconstruction, fill masked area with surrounding context, realistic, high quality, coherent

Negative Prompt: artifacts, blur, distortion, mismatched, ugly, text, watermark, signature";

        return $this->editBase64($imageBase64, $enhancedPrompt, $maskBase64);
    }

    /**
     * Parse the binary image data from the response
     */
    protected function parseResponse($response): array
    {
        if (!$response->successful()) {
            Log::error('Gemini API returned non-200 status', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Gemini API Error (' . $response->status() . '): ' . $response->body());
        }

        $json = $response->json();

        // Avoid logging raw responses (they can contain multi-MB base64 images)
        Log::info('Gemini API Response Structure', [
            'has_candidates' => isset($json['candidates']),
            'candidate_count' => isset($json['candidates']) ? count($json['candidates']) : 0,
            'finish_reason' => $json['candidates'][0]['finishReason'] ?? null,
            'has_content_parts' => isset($json['candidates'][0]['content']['parts']),
        ]);

        // Check for NO_IMAGE finish reason
        if (isset($json['candidates'][0]['finishReason']) && $json['candidates'][0]['finishReason'] === 'NO_IMAGE') {
            // Check if there is text content explaining why
            $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if ($text) {
                Log::error('Gemini returned text instead of image', ['text' => $text]);
                throw new RuntimeException("Model refused to generate image. Reason: " . $text);
            }

            Log::error('Gemini refused to generate image', [
                'finish_reason' => $json['candidates'][0]['finishReason'],
                'response' => $json,
            ]);
            throw new RuntimeException("Gemini refused to generate an image. The prompt may contain content that violates safety filters or is not suitable for image generation.");
        }

        // The image data is nested in inlineData
        $candidate = $json['candidates'][0]['content']['parts'][0] ?? null;

        // Check if we got an image back
        if (isset($candidate['inlineData']['data'])) {
            $data = $candidate['inlineData']['data'];
            return [
                'image_data' => $data,
                'image_base64' => $data,
                'mime_type' => $candidate['inlineData']['mimeType'] ?? 'image/png',
            ];
        }

        // Check alternate structure (sometimes images are in different part indices)
        if (isset($json['candidates'][0]['content']['parts'])) {
            foreach ($json['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['inlineData']['data'])) {
                    $data = $part['inlineData']['data'];
                    return [
                        'image_data' => $data,
                        'image_base64' => $data,
                        'mime_type' => $part['inlineData']['mimeType'] ?? 'image/png',
                    ];
                }
            }
        }

        // Handle refusal (sometimes it returns text if safety filters trip)
        if (isset($candidate['text'])) {
            Log::warning('Gemini refused to generate image', ['reason' => $candidate['text']]);
            throw new RuntimeException("Model refused to generate image. Reason: " . $candidate['text']);
        }

        Log::error('Unexpected response format from Gemini', [
            'response_structure' => json_encode($json, JSON_PRETTY_PRINT),
        ]);
        throw new RuntimeException("Unexpected response format from Gemini. Check logs for details.");
    }

    protected function fileToPart(string $path): ?array
    {
        // Handle both storage paths and absolute paths
        if (Storage::disk('public')->exists($path)) {
            $mime = Storage::disk('public')->mimeType($path);
            $content = Storage::disk('public')->get($path);
        } elseif (file_exists($path)) {
            $mime = mime_content_type($path);
            $content = file_get_contents($path);
        } else {
            return null;
        }

        return [
            'inlineData' => [
                'mimeType' => $mime,
                'data' => base64_encode($content)
            ]
        ];
    }

    protected function ensureConfigured(): void
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('Gemini API Key is missing');
        }
    }

    protected function http()
    {
        $client = Http::withHeaders(['Content-Type' => 'application/json']);
        
        // Disable SSL verification in non-production (Laragon/Windows dev environments)
        if (config('app.env') !== 'production') {
            $client = $client->withOptions(['verify' => false]);
        }
        
        return $client;
    }

    /**
     * Check if service is available
     */
    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Get service name
     */
    public function getServiceName(): string
    {
        return 'Google Gemini';
    }

    /**
     * Analyze brand style from reference images (stub for interface compatibility)
     * The simplified approach doesn't need separate analysis - references are passed directly
     */
    public function analyzeBrandStyle(array $imageUrls): array
    {
        // Return minimal structure for compatibility
        // In the simplified approach, we don't analyze separately
        return [
            'analyzed_at' => now()->toIso8601String(),
            'model' => $this->model,
            'note' => 'Simplified approach - style mirroring happens during generation',
        ];
    }

    /**
     * Generate image from prompt and style guide (legacy interface method)
     * Redirects to generateWithReferences for compatibility
     */
    public function generateImage(string $prompt, ?array $styleGuide = null, string $format = 'square'): array
    {
        // For compatibility, call generateWithReferences with empty references
        return $this->generateWithReferences($prompt, [], [], $format, false);
    }
}





