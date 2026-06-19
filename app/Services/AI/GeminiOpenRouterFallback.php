<?php

namespace App\Services\AI;

use Throwable;

class GeminiOpenRouterFallback
{
    public static function shouldFallback(Throwable $error): bool
    {
        if (! config('services.ai.enable_fallback', true)) {
            return false;
        }

        if (! self::hasOpenRouterKey()) {
            return false;
        }

        $msg = strtolower($error->getMessage());

        return str_contains($msg, '403')
            || str_contains($msg, '401')
            || str_contains($msg, 'api key')
            || str_contains($msg, 'leaked')
            || str_contains($msg, 'permission_denied')
            || str_contains($msg, 'not configured')
            || str_contains($msg, 'invalid_api_key');
    }

    public static function hasOpenRouterKey(): bool
    {
        foreach ([
            config('openrouter.api_key'),
            config('services.openrouter.api_key'),
        ] as $apiKey) {
            if (is_string($apiKey) && $apiKey !== '') {
                return true;
            }
        }

        return false;
    }
}
