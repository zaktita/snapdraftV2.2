<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OpenRouterImageTester
{
    protected string $baseUrl = 'https://openrouter.ai/api/v1';
    protected ?string $apiKey;

    protected array $models = [
        'bytedance-seed/seedream-4.5',
        // 'openai/gpt-5-image',
    ];

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.api_key');
    }

    /**
     * Generate images for all models concurrently.
     *
     * @param array $referenceImages Array of base64-encoded image data with mimeType
     * @param string $prompt User's prompt
     * @return array Results keyed by model name with image_url, duration_ms, and error fields
     */
    public function generateForAllModels(array $referenceImages, string $prompt): array
    {
        $this->ensureConfigured();

        if (empty($referenceImages)) {
            throw new RuntimeException('No reference images provided');
        }

        if (empty(trim($prompt))) {
            throw new RuntimeException('Prompt cannot be empty');
        }

        // Build content parts: reference images + prompt
        $contentParts = $this->buildContentParts($referenceImages, $prompt);

        // Fire all requests concurrently using promises
        $client = $this->getAsyncClient();
        $promises = [];

        foreach ($this->models as $model) {
            // Build content with reference images + text prompt
            $content = [];
            
            // Add reference images
            foreach ($referenceImages as $image) {
                if (isset($image['data']) && isset($image['mimeType'])) {
                    $content[] = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => "data:{$image['mimeType']};base64,{$image['data']}",
                        ],
                    ];
                }
            }
            
            // Add text prompt
            $content[] = [
                'type' => 'text',
                'text' => $prompt,
            ];
            
            // For image generation models, use modalities to request images
            $payload = [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $content,
                    ],
                ],
                'modalities' => ['image', 'text'],
                'max_tokens' => 4096,
            ];

            $promises[$model] = $client->postAsync(
                "{$this->baseUrl}/chat/completions",
                ['json' => $payload]
            );
        }

        // Collect results as they complete
        $results = [];
        foreach ($promises as $model => $promise) {
            try {
                $start = microtime(true);
                $response = $promise->wait();
                $durationMs = round((microtime(true) - $start) * 1000);

                $results[$model] = $this->parseImageResponse($response, $durationMs, $model);

                Log::info('OpenRouterImageTester: generation complete', [
                    'model' => $model,
                    'duration_ms' => $durationMs,
                ]);
            } catch (\Exception $e) {
                Log::error('OpenRouterImageTester: generation failed', [
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);

                $results[$model] = [
                    'image_url' => null,
                    'duration_ms' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Build content array with reference images + text prompt.
     */
    protected function buildContentParts(array $referenceImages, string $prompt): array
    {
        $parts = [];

        // Add reference images
        foreach ($referenceImages as $image) {
            if (isset($image['data']) && isset($image['mimeType'])) {
                $parts[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $image['mimeType'],
                        'data' => $image['data'],
                    ],
                ];
            }
        }

        // Add text prompt
        $parts[] = [
            'type' => 'text',
            'text' => $prompt,
        ];

        return $parts;
    }

    /**
     * Parse image response from OpenRouter.
     * Handles both URL responses and raw image data (base64).
     * 
     * @param mixed $response API response
     * @param int $durationMs Generation duration in milliseconds
     * @param string $model Model name for file naming
     */
    protected function parseImageResponse($response, int $durationMs, string $model): array
    {
        // Handle Guzzle response objects
        if (method_exists($response, 'getStatusCode')) {
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                Log::error('OpenRouterImageTester: API response error', [
                    'status' => $statusCode,
                    'body' => $response->getBody()->getContents(),
                ]);
                throw new RuntimeException('OpenRouter API error: HTTP ' . $statusCode);
            }
            
            $body = json_decode($response->getBody()->getContents(), true);
        } else {
            // Handle Laravel Http response
            if (!$response->successful()) {
                Log::error('OpenRouterImageTester: API response error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new RuntimeException(
                    'OpenRouter API error: ' . ($response->json()['error']['message'] ?? 'Unknown error')
                );
            }

            $body = $response->json();
        }

        // Log full response for debugging (with detailed structure)
        Log::info('OpenRouterImageTester: Full response', [
            'body_keys' => array_keys($body ?? []),
            'choices_count' => isset($body['choices']) ? count($body['choices']) : 0,
            'message_keys' => isset($body['choices'][0]['message']) ? array_keys($body['choices'][0]['message']) : [],
            'has_images' => isset($body['choices'][0]['message']['images']),
            'images_count' => isset($body['choices'][0]['message']['images']) ? count($body['choices'][0]['message']['images']) : 0,
            'content_type' => isset($body['choices'][0]['message']['content']) ? gettype($body['choices'][0]['message']['content']) : 'none',
            'image_tokens' => $body['usage']['completion_tokens_details']['image_tokens'] ?? 0,
        ]);

        // Try various response formats
        $imageUrl = null;
        
        // Format 1: Direct data array with URL
        if (isset($body['data']) && is_array($body['data']) && !empty($body['data'])) {
            $imageUrl = $body['data'][0]['url'] ?? null;
            
            // Check for b64_json in data array
            if (!$imageUrl && isset($body['data'][0]['b64_json'])) {
                $rawImageData = $body['data'][0]['b64_json'];
                $dataUrl = $this->convertToDataUrl($rawImageData);
                return [
                    'image_url' => $dataUrl,
                    'saved_path' => $this->saveImageToStorage($dataUrl, $model),
                    'duration_ms' => $durationMs,
                    'error' => null,
                ];
            }
            
            // If data[0] is directly a base64 string
            if (!$imageUrl && is_string($body['data'][0])) {
                $rawImageData = $body['data'][0];
                $dataUrl = $this->convertToDataUrl($rawImageData);
                return [
                    'image_url' => $dataUrl,
                    'saved_path' => $this->saveImageToStorage($dataUrl, $model),
                    'duration_ms' => $durationMs,
                    'error' => null,
                ];
            }
        }
        
        // Format 2: Choices array (OpenRouter image generation)
        if (!$imageUrl && isset($body['choices']) && is_array($body['choices']) && !empty($body['choices'])) {
            $message = $body['choices'][0]['message'] ?? [];
            
            // Check for images array (OpenRouter format)
            if (isset($message['images']) && is_array($message['images']) && !empty($message['images'])) {
                $firstImage = $message['images'][0];
                
                // Check for image_url.url
                if (isset($firstImage['image_url']['url'])) {
                    $imageUrl = $firstImage['image_url']['url'];
                    Log::info('OpenRouterImageTester: Found image in message.images', [
                        'url_preview' => substr($imageUrl, 0, 100),
                    ]);
                    
                    // If it's already a data URL, save and return it
                    if (str_starts_with($imageUrl, 'data:')) {
                        return [
                            'image_url' => $imageUrl,
                            'saved_path' => $this->saveImageToStorage($imageUrl, $model),
                            'duration_ms' => $durationMs,
                            'error' => null,
                        ];
                    }
                    
                    // Otherwise return the URL as is (external URL)
                    return [
                        'image_url' => $imageUrl,
                        'saved_path' => null,
                        'duration_ms' => $durationMs,
                        'error' => null,
                    ];
                }
            }
            
            $content = $message['content'] ?? null;
            
            // Content is string (might be URL, base64, or text)
            if (is_string($content)) {
                // Check if it's a URL
                if (filter_var($content, FILTER_VALIDATE_URL)) {
                    $imageUrl = $content;
                }
                // Check if it's base64 image data (long string, base64 chars)
                elseif (strlen($content) > 1000 && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $content)) {
                    // Likely base64 encoded image
                    Log::info('OpenRouterImageTester: Found potential base64 in content', [
                        'length' => strlen($content),
                        'preview' => substr($content, 0, 100),
                    ]);
                    $dataUrl = $this->convertToDataUrl($content);
                    return [
                        'image_url' => $dataUrl,
                        'saved_path' => $this->saveImageToStorage($dataUrl, $model),
                        'duration_ms' => $durationMs,
                        'error' => null,
                    ];
                }
            }
            
            // Content is array
            if (is_array($content) && !empty($content)) {
                $imageUrl = $content[0]['image_url']['url'] ?? null;
                
                if (!$imageUrl && isset($content[0]['image'])) {
                    $dataUrl = $this->convertToDataUrl($content[0]['image']);
                    return [
                        'image_url' => $dataUrl,
                        'saved_path' => $this->saveImageToStorage($dataUrl, $model),
                        'duration_ms' => $durationMs,
                        'error' => null,
                    ];
                }
                
                if (!$imageUrl && isset($content[0]['b64_json'])) {
                    $dataUrl = $this->convertToDataUrl($content[0]['b64_json']);
                    return [
                        'image_url' => $dataUrl,
                        'saved_path' => $this->saveImageToStorage($dataUrl, $model),
                        'duration_ms' => $durationMs,
                        'error' => null,
                    ];
                }
            }
            
            // Check reasoning_details for base64 image data
            $reasoningDetails = $message['reasoning_details'] ?? [];
            if (is_array($reasoningDetails) && !empty($reasoningDetails)) {
                foreach ($reasoningDetails as $detail) {
                    // Check if data field contains base64
                    if (isset($detail['data']) && is_string($detail['data'])) {
                        $data = $detail['data'];
                        
                        // Skip if it looks like encrypted data (starts with gAAAAA - Fernet encryption)
                        if (str_starts_with($data, 'gAAAAA')) {
                            Log::info('OpenRouterImageTester: Skipping encrypted reasoning data');
                            continue;
                        }
                        
                        // Check if it's substantial base64 data (likely image)
                        if (strlen($data) > 1000 && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $data)) {
                            Log::info('OpenRouterImageTester: Found base64 in reasoning_details', [
                                'length' => strlen($data),
                                'preview' => substr($data, 0, 100),
                            ]);
                            $dataUrl = $this->convertToDataUrl($data);
                            return [
                                'image_url' => $dataUrl,
                                'saved_path' => $this->saveImageToStorage($dataUrl, $model),
                                'duration_ms' => $durationMs,
                                'error' => null,
                            ];
                        }
                    }
                }
            }
        }

        // If we found a URL, return it
        if ($imageUrl) {
            $savedPath = str_starts_with($imageUrl, 'data:') 
                ? $this->saveImageToStorage($imageUrl, $model) 
                : null;
            return [
                'image_url' => $imageUrl,
                'saved_path' => $savedPath,
                'duration_ms' => $durationMs,
                'error' => null,
            ];
        }

        // No image found in any expected format
        Log::error('OpenRouterImageTester: no image data in response', [
            'body' => $body,
            'checked_paths' => [
                'data[0][url]',
                'data[0][b64_json]',
                'data[0] (string)',
                'choices[0][message][content] (string/url)',
                'choices[0][message][content][0][image_url][url]',
                'choices[0][message][content][0][image]',
                'choices[0][message][content][0][b64_json]',
            ],
        ]);
        
        throw new RuntimeException('No image URL or data returned from OpenRouter');
    }

    /**
     * Convert raw image data (base64 or binary) to a data URL.
     */
    protected function convertToDataUrl(string $imageData): string
    {
        // Check if already a data URL
        if (str_starts_with($imageData, 'data:')) {
            return $imageData;
        }

        // Clean the data - remove any whitespace
        $imageData = preg_replace('/\s+/', '', $imageData);

        // Check if it looks like base64 (alphanumeric + /+= chars)
        if (preg_match('/^[A-Za-z0-9+\/=]+$/', $imageData)) {
            // Decode to check if it's valid base64 image data
            $decoded = base64_decode($imageData, true);
            if ($decoded !== false && strlen($decoded) > 0) {
                // Check if decoded data is actually image data
                $mimeType = $this->detectMimeTypeFromBinary($decoded);
                if (str_starts_with($mimeType, 'image/')) {
                    return "data:{$mimeType};base64,{$imageData}";
                }
            }
            
            // If decoding failed or not image, assume it's already base64 encoded image
            $mimeType = 'image/png'; // Default
            return "data:{$mimeType};base64,{$imageData}";
        }

        // Assume it's binary data, try to detect and encode
        $mimeType = $this->detectMimeTypeFromBinary($imageData);
        $encoded = base64_encode($imageData);
        return "data:{$mimeType};base64,{$encoded}";
    }

    /**
     * Detect MIME type from base64 string by decoding header bytes.
     */
    protected function detectMimeTypeFromBase64(string $base64): string
    {
        try {
            $binaryData = base64_decode($base64, true);
            if ($binaryData === false) {
                return 'image/png'; // Default fallback
            }
            return $this->detectMimeTypeFromBinary($binaryData);
        } catch (\Exception $e) {
            return 'image/png'; // Default fallback
        }
    }

    /**
     * Save base64 image to storage/app/testaimodel folder.
     * 
     * @param string $dataUrl The data URL (e.g., data:image/png;base64,...)
     * @param string $model Model name for filename
     * @return string|null Saved file path or null on failure
     */
    protected function saveImageToStorage(string $dataUrl, string $model): ?string
    {
        try {
            // Parse data URL
            if (!preg_match('/^data:image\/(\w+);base64,(.+)$/', $dataUrl, $matches)) {
                Log::warning('OpenRouterImageTester: Invalid data URL format', [
                    'model' => $model,
                    'preview' => substr($dataUrl, 0, 100),
                ]);
                return null;
            }

            $extension = $matches[1]; // png, jpeg, etc.
            $base64Data = $matches[2];

            // Decode base64
            $imageData = base64_decode($base64Data, true);
            if ($imageData === false) {
                Log::error('OpenRouterImageTester: Failed to decode base64', [
                    'model' => $model,
                ]);
                return null;
            }

            // Create filename: timestamp_modelname.ext
            $timestamp = now()->format('Y-m-d_His');
            $modelSlug = str_replace(['/', '.'], '_', $model);
            $filename = "{$timestamp}_{$modelSlug}.{$extension}";
            $path = "testaimodel/{$filename}";

            // Save to storage/app/testaimodel
            Storage::put($path, $imageData);

            Log::info('OpenRouterImageTester: Image saved to storage', [
                'model' => $model,
                'path' => $path,
                'size_kb' => round(strlen($imageData) / 1024, 2),
            ]);

            return $path;
        } catch (\Exception $e) {
            Log::error('OpenRouterImageTester: Failed to save image', [
                'model' => $model,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Detect MIME type from binary data by magic bytes.
     */
    protected function detectMimeTypeFromBinary(string $data): string
    {
        if (empty($data)) {
            return 'image/png';
        }

        // Check magic bytes
        $bytes = unpack('H*', substr($data, 0, 4))[1];

        return match (true) {
            str_starts_with($bytes, 'ffd8ff') => 'image/jpeg',      // JPEG
            str_starts_with($bytes, '89504e47') => 'image/png',      // PNG
            str_starts_with($bytes, '47494638') => 'image/gif',      // GIF
            str_starts_with($bytes, '52494646') => 'image/webp',     // WEBP
            default => 'image/png',  // Default fallback
        };
    }

    protected function ensureConfigured(): void
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('OpenRouter API Key is missing');
        }
    }

    /**
     * Get async-capable Guzzle client for concurrent requests.
     */
    protected function getAsyncClient()
    {
        return new \GuzzleHttp\Client([
            'timeout' => 120,
            'connect_timeout' => 15,
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url'),
            ],
            'verify' => config('app.env') === 'production',
        ]);
    }

    protected function http()
    {
        $client = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'HTTP-Referer' => config('app.url'),
        ])
            ->timeout(120)
            ->connectTimeout(15)
            ->retry(1, 2000);

        if (config('app.env') !== 'production') {
            $client = $client->withOptions(['verify' => false]);
        }

        return $client;
    }
}
