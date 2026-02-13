<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class OpenRouterService implements AIServiceInterface
{
    protected string $apiKey;
    protected string $baseUrl = 'https://openrouter.ai/api/v1';
    protected string $model;
    protected ?string $currentFormat = null;

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.api_key', '');
        $this->model = config('services.openrouter.image_model', 'bytedance-seed/seedream-4.5');
    }

    public function generateWithReferences(
        string $prompt,
        array $referencePaths,
        array $productImagePaths = [],
        string $format = 'square'
    ): array {
        $aspectRatioInstruction = $this->getAspectRatioInstruction($format);
        $enhancedPrompt = $aspectRatioInstruction . "\n\n---\n\n" . $prompt;
        $this->currentFormat = $format;
        return $this->generate($enhancedPrompt, array_merge($referencePaths, $productImagePaths), $format);
    }

    private function getAspectRatioInstruction(string $format): string
    {
        $ratio = $this->normalizeAspectRatio($format);
        [$w, $h] = array_map('intval', explode(':', $ratio));
        $orientation = $w === $h ? 'square' : ($w > $h ? 'landscape' : 'portrait');
        return "Aspect Ratio: {$ratio} ({$orientation})";
    }

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

    public function generate(
        string $prompt,
        array $referencePaths = [],
        string $format = 'square'
    ): array {
        $this->ensureConfigured();
        $this->currentFormat = $format;
        $content = [];
        foreach (array_slice($referencePaths, 0, 5) as $path) {
            if ($imgData = $this->fileToPart($path)) {
                $content[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => "data:{$imgData['mimeType']};base64,{$imgData['data']}"
                    ]
                ];
            }
        }
        $content[] = ['type' => 'text', 'text' => $prompt];

        Log::info('OpenRouter Generation', [
            'model' => $this->model,
            'references' => count($referencePaths),
        ]);

        $startTime = microtime(true);
        $payload = [
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $content]],
        ];
        
        $response = $this->http()->post("{$this->baseUrl}/chat/completions", $payload);
        $generationTime = (microtime(true) - $startTime) * 1000;

        $result = $this->parseResponse($response);
        $result['metadata'] = [
            'model' => $this->model,
            'generation_time_ms' => round($generationTime),
            'generated_at' => now()->toIso8601String(),
        ];
        return $result;
    }

    public function editBase64(string $imageBase64, string $prompt, ?string $maskBase64 = null): string
    {
        $this->ensureConfigured();
        $content = [];
        $content[] = [
            'type' => 'image_url',
            'image_url' => ['url' => "data:image/png;base64,{$imageBase64}"]
        ];
        $content[] = ['type' => 'text', 'text' => $prompt];
        
        if ($maskBase64) {
             $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => "data:image/png;base64,{$maskBase64}"]
            ];
             $content[] = ['type' => 'text', 'text' => "Use the second image as a mask for inpainting."];
        }

        Log::info('OpenRouter Base64 Edit', [
            'has_mask' => !is_null($maskBase64),
            'prompt' => substr($prompt, 0, 100),
        ]);

        $response = $this->http()->post(
            "{$this->baseUrl}/chat/completions",
            [
                'model' => $this->model,
                'messages' => [['role' => 'user', 'content' => $content]]
            ]
        );

        $result = $this->parseResponse($response);
        return $result['image_data'];
    }

    public function inpaint(string $imageBase64, string $maskBase64, string $prompt): string
    {
        return $this->editBase64($imageBase64, $prompt, $maskBase64);
    }

    public function outpaint(string $expandedImageBase64, string $maskBase64, string $prompt = ''): string
    {
        $defaultPrompt = "Fill the masked areas so the scene extends naturally.";
        $fullPrompt = $prompt ? $prompt . ' ' . $defaultPrompt : $defaultPrompt;
        return $this->editBase64($expandedImageBase64, $fullPrompt, $maskBase64);
    }

    public function generateFromPrompt(string $imageBase64, string $maskBase64, string $prompt): string
    {
        return $this->editBase64($imageBase64, $prompt, $maskBase64);
    }

    public function eraseGreenHighlights(string $imageBase64): string
    {
        $prompt = "Remove all green-highlighted areas from this image and fill them naturally with appropriate content. The green highlights (bright green color RGB 0,255,0) indicate areas that should be erased and seamlessly replaced with background that fits the image style, perspective, colors, and lighting. Do not leave any green color or artifacts in the final result.";
        return $this->editBase64($imageBase64, $prompt);
    }

    protected function parseResponse($response): array
    {
        if (!$response->successful()) {
            throw new RuntimeException('OpenRouter API Error (' . $response->status() . '): ' . $response->body());
        }

        $json = $response->json();

        // Check for direct data response (standard OpenAI image format)
        if (isset($json['data'][0]['url'])) {
             return $this->processImageUrl($json['data'][0]['url']);
        }
        if (isset($json['data'][0]['b64_json'])) {
             $b64 = $json['data'][0]['b64_json'];
             return [
                 'image_data' => $b64, 
                 'image_base64' => $b64, 
                 'mime_type' => 'image/png'
             ];
        }

        $message = $json['choices'][0]['message'] ?? null;
        if (!$message) {
            throw new RuntimeException('Invalid response structure from OpenRouter');
        }

        // Check OpenRouter specialized message.images format
        if (isset($message['images'][0]['image_url']['url'])) {
             return $this->processImageUrl($message['images'][0]['image_url']['url']);
        }

        $content = $message['content'] ?? '';
        
        // Check markdown image syntax
        if (preg_match('/!\[.*?\]\((.*?)\)/', $content, $matches)) {
            return $this->processImageUrl($matches[1]);
        }
        
        // Check if content is a raw URL
        if (filter_var(trim($content), FILTER_VALIDATE_URL)) {
             return $this->processImageUrl(trim($content));
        }

        // Check if content is raw base64
        if (strlen($content) > 1000 && preg_match('/^[A-Za-z0-9+\/=\s]+$/', trim($content))) {
             $b64 = trim($content);
             return [
                 'image_data' => $b64, 
                 'image_base64' => $b64, 
                 'mime_type' => 'image/png'
             ];
        }

        // Check reasoning_details for base64 (some experimental models)
        $reasoningDetails = $message['reasoning_details'] ?? [];
        if (is_array($reasoningDetails)) {
            foreach ($reasoningDetails as $detail) {
                 if (isset($detail['data']) && is_string($detail['data']) && strlen($detail['data']) > 1000) {
                     // Check if not encrypted
                     if (!str_starts_with($detail['data'], 'gAAAAA')) {
                         $b64 = $detail['data'];
                         return [
                             'image_data' => $b64,
                             'image_base64' => $b64,
                             'mime_type' => 'image/png'
                         ];
                     }
                 }
            }
        }
        
        Log::error('OpenRouter: No image found in response content', ['content' => Str::limit($content, 200)]);
        throw new RuntimeException("No image found in OpenRouter response. Content: " . Str::limit($content, 100));
    }

    protected function processImageUrl(string $url): array
    {
        $content = file_get_contents($url);
        if ($content === false) {
             throw new RuntimeException("Failed to download generated image from URL: $url");
        }
        $base64 = base64_encode($content);
        $mime = 'image/png';
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->buffer($content);
        if ($detectedMime) $mime = $detectedMime;

        return [
            'image_data' => $base64,
            'image_base64' => $base64,
            'mime_type' => $mime,
        ];
    }

    protected function fileToPart(string $path): ?array
    {
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
            'mimeType' => $mime,
            'data' => base64_encode($content)
        ];
    }

    protected function ensureConfigured(): void
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('OpenRouter API Key is missing');
        }
    }

    protected function http()
    {
        $client = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => config('app.url'), 
            'X-Title' => config('app.name'),
        ]);
        if (config('app.env') !== 'production') {
            $client = $client->withOptions(['verify' => false]);
        }
        return $client;
    }

    public function analyzeBrandStyle(array $imageUrls): array
    {
        return [
            'analyzed_at' => now()->toIso8601String(),
            'model' => $this->model,
            'note' => 'Simplified approach - style mirroring happens during generation',
        ];
    }

    public function generateImage(string $prompt, ?array $styleGuide = null, string $format = 'square'): array
    {
        return $this->generateWithReferences($prompt, [], [], $format);
    }

    public function getServiceName(): string
    {
        return 'OpenRouter (' . $this->model . ')';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }
}