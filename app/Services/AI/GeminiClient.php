<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiClient
{
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');

        if (empty($this->apiKey)) {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }
    }

    /**
     * Generate content with a JSON schema constraint (returns parsed array).
     */
    public function generateContentWithSchema(string $model, array $contents, array $schema): array
    {
        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema,
            ],
        ];

        $response = $this->withRetry(function () use ($model, $payload) {
            return $this->post($model, $payload);
        });

        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$text) {
            throw new RuntimeException("Gemini ({$model}) returned no text content.");
        }

        $decoded = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Gemini ({$model}) returned invalid JSON: " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Generate an image and return the raw base64 data string.
     */
    public function generateImage(string $model, array $contents, string $aspectRatio = '1:1'): string
    {
        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'responseModalities' => ['IMAGE'],
                'imageConfig' => [
                    'aspectRatio' => $aspectRatio,
                ],
            ],
        ];

        $response = $this->withRetry(function () use ($model, $payload) {
            return $this->post($model, $payload);
        });

        $imagePart = collect($response['candidates'][0]['content']['parts'] ?? [])
            ->first(fn($part) => isset($part['inlineData']['data']));

        if (!$imagePart) {
            throw new RuntimeException("Gemini ({$model}) did not return image data.");
        }

        return $imagePart['inlineData']['data'];
    }

    /**
     * Convert a stored image path to a Gemini inline data part.
     * $storagePath is relative to storage/app/public (e.g. "projects/1/refs/image.jpg").
     */
    public function imageToInlinePart(string $storagePath): array
    {
        $absolutePath = storage_path('app/public/' . $storagePath);

        if (!file_exists($absolutePath)) {
            throw new RuntimeException("Reference image not found at: {$absolutePath}");
        }

        $data = base64_encode(file_get_contents($absolutePath));
        $mimeType = mime_content_type($absolutePath) ?: 'image/jpeg';

        return [
            'inlineData' => [
                'data' => $data,
                'mimeType' => $mimeType,
            ],
        ];
    }

    /**
     * Retry wrapper with exponential back-off (mirrors test.ts withRetry()).
     */
    public function withRetry(callable $fn, int $maxAttempts = 3, int $baseDelayMs = 2000): mixed
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $fn();
            } catch (\Throwable $e) {
                $lastException = $e;

                Log::warning("GeminiClient: attempt {$attempt}/{$maxAttempts} failed", [
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $maxAttempts) {
                    usleep($baseDelayMs * $attempt * 1000);
                }
            }
        }

        throw $lastException;
    }

    private function post(string $model, array $payload): array
    {
        $url = "{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}";

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(180)
            ->connectTimeout(15)
            ->post($url, $payload);

        if (!$response->successful()) {
            throw new RuntimeException(
                "Gemini API error (HTTP {$response->status()}): " . $response->body()
            );
        }

        return $response->json();
    }
}
