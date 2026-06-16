<?php

namespace App\Services\AI\OpenRouter;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenRouterClient
{
    public function chat(array $payload): array
    {
        $response = $this->request()
            ->timeout(config('openrouter.timeout'))
            ->post('/chat/completions', $payload);

        return $this->decode($response, 'chat/completions');
    }

    public function request(): PendingRequest
    {
        $apiKey = $this->resolveApiKey();

        if ($apiKey === '') {
            throw new RuntimeException('OPENROUTER_API_KEY is not configured.');
        }

        return Http::baseUrl(rtrim((string) config('openrouter.base_url'), '/'))
            ->withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'HTTP-Referer' => (string) config('openrouter.site_url'),
                'X-Title' => (string) config('openrouter.app_name'),
            ])
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('openrouter.connect_timeout'));
    }

    protected function decode(Response $response, string $endpoint): array
    {
        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'OpenRouter %s failed (%s): %s',
                $endpoint,
                $response->status(),
                $response->body()
            ));
        }

        return $response->json() ?? [];
    }

    protected function resolveApiKey(): string
    {
        foreach ([
            config('openrouter.api_key'),
            config('services.openrouter.api_key'),
        ] as $apiKey) {
            if (is_string($apiKey) && $apiKey !== '') {
                return $apiKey;
            }
        }

        return '';
    }
}
