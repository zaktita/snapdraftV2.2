<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CanvasEditorService
{
    private function httpClient()
    {
        $apiKey = config('services.openrouter.api_key');
        return Http::withToken($apiKey)
            ->withoutVerifying()
            ->timeout(120)
            ->withHeaders([
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name'),
                'Content-Type' => 'application/json',
            ]);
    }

    /**
     * Perform inpainting using OpenRouter's GPT-5 Image model.
     * Modifies the masked area based on the prompt.
     *
     * @param string $imageBase64 Base64-encoded image data (without data URL prefix)
     * @param string $maskBase64 Base64-encoded mask data (white = edit area, black = keep)
     * @param string $prompt User's editing instructions
     * @return string Base64-encoded result image
     * @throws \Exception
     */
    public function inpaint(string $imageBase64, string $maskBase64, string $prompt): string
    {
        Log::info('[CanvasEditorService] Inpainting', ['prompt' => $prompt]);

        $apiKey = config('services.openrouter.api_key');
        $model = 'openai/gpt-5-image';

        if (!$apiKey) {
            throw new \Exception('OpenRouter API key not configured');
        }

        // Build image URL from base64
        $imageDataUrl = 'data:image/png;base64,' . $imageBase64;
        $maskDataUrl = 'data:image/png;base64,' . $maskBase64;

        $response = $this->httpClient()
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_image',
                                'image_url' => ['url' => $imageDataUrl],
                                'mask_url'  => ['url' => $maskDataUrl],
                            ],
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                        ]
                    ],
                ],
            ]);

        if (!$response->successful()) {
            Log::error('[CanvasEditorService] OpenRouter API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('OpenRouter API error: ' . $response->body());
        }

        $result = $response->json();
        return $this->extractImageBase64($result);
    }

    /**
     * Perform outpainting using OpenRouter's GPT-5 Image model.
     * Extends the image by filling masked areas naturally.
     *
     * @param string $expandedImageBase64 Base64-encoded expanded canvas with original image placed
     * @param string $maskBase64 Base64-encoded mask (white = areas to generate)
     * @param string $prompt Optional prompt for style guidance
     * @return string Base64-encoded result image
     * @throws \Exception
     */
    public function outpaint(string $expandedImageBase64, string $maskBase64, string $prompt = ''): string
    {
        Log::info('[CanvasEditorService] Outpainting', ['prompt' => $prompt]);

        $apiKey = config('services.openrouter.api_key');
        $model = 'openai/gpt-5-image';

        if (!$apiKey) {
            throw new \Exception('OpenRouter API key not configured');
        }

        $imageDataUrl = 'data:image/png;base64,' . $expandedImageBase64;
        $maskDataUrl = 'data:image/png;base64,' . $maskBase64;

        $defaultPrompt = "Fill the white masked areas so the scene extends naturally to match the original image's style, perspective, colors, and lighting.";
        $fullPrompt = $prompt ? $prompt . ' ' . $defaultPrompt : $defaultPrompt;

        $response = $this->httpClient()
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_image',
                                'image_url' => ['url' => $imageDataUrl],
                                'mask_url'  => ['url' => $maskDataUrl],
                            ],
                            [
                                'type' => 'text',
                                'text' => $fullPrompt,
                            ],
                        ]
                    ],
                ],
            ]);

        if (!$response->successful()) {
            Log::error('[CanvasEditorService] OpenRouter API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('OpenRouter API error: ' . $response->body());
        }

        $result = $response->json();
        return $this->extractImageBase64($result);
    }

    /**
     * Generate or modify image area based on text prompt.
     * Similar to inpainting but more prompt-driven.
     *
     * @param string $imageBase64 Base64-encoded image data
     * @param string $maskBase64 Base64-encoded mask (white = generation area)
     * @param string $prompt Generation/modification instructions
     * @return string Base64-encoded result image
     * @throws \Exception
     */
    public function generateFromPrompt(string $imageBase64, string $maskBase64, string $prompt): string
    {
        Log::info('[CanvasEditorService] Generate from prompt', ['prompt' => $prompt]);

        $apiKey = config('services.openrouter.api_key');
        $model = 'openai/gpt-5-image';

        if (!$apiKey) {
            throw new \Exception('OpenRouter API key not configured');
        }

        $imageDataUrl = 'data:image/png;base64,' . $imageBase64;
        $maskDataUrl = 'data:image/png;base64,' . $maskBase64;

        $response = $this->httpClient()
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_image',
                                'image_url' => ['url' => $imageDataUrl],
                                'mask_url'  => ['url' => $maskDataUrl],
                            ],
                            [
                                'type' => 'text',
                                'text' => $prompt . " Only modify the white areas in the mask. Blend naturally with the existing image.",
                            ],
                        ]
                    ],
                ],
            ]);

        if (!$response->successful()) {
            Log::error('[CanvasEditorService] OpenRouter API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('OpenRouter API error: ' . $response->body());
        }

        $result = $response->json();
        return $this->extractImageBase64($result);
    }

    /**
     * Extract base64 data from data URL string.
     *
     * @param string $dataUrl Data URL string (e.g., data:image/png;base64,...)
     * @return string Pure base64 string without prefix
     */
    private function extractBase64FromDataUrl(string $dataUrl): string
    {
        if (str_contains($dataUrl, 'base64,')) {
            return explode('base64,', $dataUrl)[1];
        }
        
        return $dataUrl;
    }

    private function extractImageBase64(array $result): string
    {
        // 1) Preferred: images array with data URL
        $url = $result['choices'][0]['message']['images'][0]['image_url']['url']
            ?? null;
        if (is_string($url)) {
            return $this->extractBase64FromDataUrl($url);
        }

        // 2) Look into message.content (array of parts or data URL string)
        $content = $result['choices'][0]['message']['content'] ?? null;
        if (is_string($content) && str_starts_with($content, 'data:image')) {
            return $this->extractBase64FromDataUrl($content);
        }
        if (is_array($content)) {
            foreach ($content as $part) {
                if (isset($part['image_url']['url']) && is_string($part['image_url']['url'])) {
                    return $this->extractBase64FromDataUrl($part['image_url']['url']);
                }
                if (isset($part['b64_json']) && is_string($part['b64_json'])) {
                    return $part['b64_json'];
                }
                if (isset($part['url']) && is_string($part['url']) && str_starts_with($part['url'], 'data:image')) {
                    return $this->extractBase64FromDataUrl($part['url']);
                }
            }
        }

        // 3) Legacy: data[0].b64_json
        if (isset($result['data'][0]['b64_json']) && is_string($result['data'][0]['b64_json'])) {
            return $result['data'][0]['b64_json'];
        }

        throw new \Exception('No image in OpenRouter response');
    }
}
