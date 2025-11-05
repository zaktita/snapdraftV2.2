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

// Test CSV wizard flow
Artisan::command('test:csv-wizard', function () {
    $this->info('Testing CSV wizard flow...');
    
    // Get or create test user
    $user = \App\Models\User::firstOrCreate(
        ['email' => 'test@example.com'],
        [
            'name' => 'Test User',
            'password' => bcrypt('password'),
            'credits_remaining' => 100,
            'credits_total' => 100,
        ]
    );
    
    // Create test project
    $project = $user->projects()->create([
        'title' => 'Test CSV Project',
        'description' => 'Testing CSV wizard flow',
        'settings' => [
            'wizard_type' => 'csv',
            'csv_data' => [
                ['title' => 'Summer Sale', 'description' => 'Amazing summer deals', 'format' => 'square'],
                ['title' => 'Winter Collection', 'description' => 'Cozy winter vibes', 'format' => 'portrait'],
            ],
            'csv_rows' => 2,
        ],
    ]);
    
    $this->info("Project created: {$project->id}");
    
    // Dispatch batch job
    \App\Jobs\GenerateBatchImagesJob::dispatch($project);
    
    $this->info('Batch job dispatched! Run queue worker to process: php artisan queue:work');
    $this->info("View results: http://localhost/projects/{$project->id}");
    
})->purpose('Test the CSV wizard generation flow');
