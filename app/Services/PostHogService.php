<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use PostHog\PostHog;

class PostHogService
{
    private bool $initialized = false;

    private function flushSafely(): void
    {
        try {
            PostHog::flush();
        } catch (\Throwable $e) {
            Log::warning('PostHog flush failed: ' . $e->getMessage());
        }
    }

    public function __construct()
    {
        $apiKey   = config('posthog.api_key', '');
        $host     = config('posthog.host', '');
        $disabled = config('posthog.disabled', false);

        if ($disabled || empty($apiKey)) {
            return;
        }

        try {
            PostHog::init($apiKey, ['host' => $host]);
            $this->initialized = true;
        } catch (\Throwable $e) {
            Log::warning('PostHog initialization failed: ' . $e->getMessage());
        }
    }

    public function capture(string $distinctId, string $event, array $properties = []): void
    {
        if (!$this->initialized) {
            return;
        }

        try {
            PostHog::capture([
                'distinctId' => $distinctId,
                'event'      => $event,
                'properties' => $properties,
            ]);

            // Flush immediately so event delivery is not delayed in long-running workers.
            $this->flushSafely();
        } catch (\Throwable $e) {
            Log::warning('PostHog capture failed: ' . $e->getMessage());
        }
    }

    public function identify(string $distinctId, array $properties = []): void
    {
        if (!$this->initialized) {
            return;
        }

        try {
            PostHog::identify([
                'distinctId'       => $distinctId,
                'properties'       => $properties,
            ]);

            // Keep identify calls near real-time for onboarding and installation checks.
            $this->flushSafely();
        } catch (\Throwable $e) {
            Log::warning('PostHog identify failed: ' . $e->getMessage());
        }
    }

    public function shutdown(): void
    {
        if (!$this->initialized) {
            return;
        }

        $this->flushSafely();
    }
}
