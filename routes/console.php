<?php

use App\Jobs\CleanOrphanedFilesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use App\Services\AI\AIServiceManager;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule orphaned files cleanup to run daily at 2 AM
Schedule::job(new CleanOrphanedFilesJob)->dailyAt('02:00');

// One-off test image generation command
Artisan::command('ai:test-generate {prompt : The prompt to generate an image}', function (string $prompt) {
    /** @var AIServiceManager $ai */
    $ai = app(AIServiceManager::class);

    $this->info('Generating image with prompt: "' . $prompt . '"');

    try {
        $result = $ai->generateWithReferences($prompt, [], [], 'square');

        if (!isset($result['image_data'], $result['mime_type'])) {
            $this->error('No image data returned from AI service');
            return;
        }

        $imageData = base64_decode($result['image_data']);
        $extension = match($result['mime_type']) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'png',
        };

        $dir = 'tests';
        $filename = 'test_' . time() . '_' . uniqid() . '.' . $extension;
        $path = $dir . '/' . $filename;

        Storage::disk('public')->put($path, $imageData);

        $url = 'storage/' . $path;
        $this->info('Image saved to: ' . $url);
    } catch (\Throwable $e) {
        $this->error('Generation failed: ' . $e->getMessage());
        report($e);
    }
})->purpose('Generate a test image using the configured AI service');
