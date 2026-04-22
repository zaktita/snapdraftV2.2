<?php

use App\Jobs\CleanOrphanedFilesJob;
use App\Models\Project;
use App\Services\AI\AIServiceManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule orphaned files cleanup to run daily at 2 AM
Schedule::job(new CleanOrphanedFilesJob)->dailyAt('02:00');

// Shared hosting (e.g. Infomaniak without a persistent `queue:work` daemon):
// enable QUEUE_WORKER_VIA_SCHEDULE=true and cron `* * * * * php artisan schedule:run`.
if (config('queue.worker_via_schedule')) {
    $connection = (string) config('queue.default');

    if (in_array($connection, ['database', 'redis'], true)) {
        Schedule::command(
            "queue:work {$connection} --stop-when-empty --max-time=300 --max-jobs=20"
        )
            ->everyMinute()
            ->withoutOverlapping(6);
    }
}

// One-off test image generation command
Artisan::command('ai:test-generate {prompt : The prompt to generate an image}', function (string $prompt) {
    /** @var AIServiceManager $ai */
    $ai = app(AIServiceManager::class);

    $this->info('Generating image with prompt: "'.$prompt.'"');

    try {
        $result = $ai->generateWithReferences($prompt, [], [], 'square');

        if (! isset($result['image_data'], $result['mime_type'])) {
            $this->error('No image data returned from AI service');

            return;
        }

        $imageData = base64_decode($result['image_data']);
        $extension = match ($result['mime_type']) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'png',
        };

        $dir = 'tests';
        $filename = 'test_'.time().'_'.uniqid().'.'.$extension;
        $path = $dir.'/'.$filename;

        Storage::disk('public')->put($path, $imageData);

        $url = 'storage/'.$path;
        $this->info('Image saved to: '.$url);
    } catch (\Throwable $e) {
        $this->error('Generation failed: '.$e->getMessage());
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

// Generate homepage images using OpenRouter seedream-4.5
Artisan::command('home:generate-images {projectId? : Project ID for brand references} {--force : Overwrite existing images}', function () {
    $projectId = $this->argument('projectId');
    $force = (bool) $this->option('force');

    $project = $projectId
        ? Project::query()->find($projectId)
        : Project::query()->whereHas('brandReferences')->first();

    if (! $project) {
        $this->error('No project with brand references found. Provide a projectId.');

        return;
    }

    $brandReferences = $project->brandReferences()->orderBy('order')->get();
    if ($brandReferences->isEmpty()) {
        $this->error('Project has no brand references.');

        return;
    }

    $referenceImagePaths = [];
    foreach ($brandReferences as $ref) {
        $path = str_replace('storage/', '', (string) $ref->url);
        if (Storage::disk('public')->exists($path)) {
            $referenceImagePaths[] = $path;
        }
    }

    if (empty($referenceImagePaths)) {
        $this->error('No reference images could be loaded from storage.');

        return;
    }

    $targets = [
        [
            'file' => 'hero-reference.png',
            'ratio' => '1:1',
            'prompt' => 'Isometric illustration of a content production machine turning a content plan into on-brand social visuals. Clean white background, premium SaaS landing style, subtle orange accents, soft shadows, crisp lines.',
        ],
        [
            'file' => 'feature-1.png',
            'ratio' => '3:2',
            'prompt' => 'Clean spreadsheet-like dashboard card with brand colors, premium SaaS illustration style, soft shadows, minimal layout, orange accents.',
        ],
        [
            'file' => 'feature-2.png',
            'ratio' => '3:2',
            'prompt' => 'Minimal workflow pipeline illustration with cards and connectors, premium SaaS style, subtle orange accents, clean white background.',
        ],
        [
            'file' => 'feature-3.png',
            'ratio' => '3:2',
            'prompt' => 'Team collaboration illustration with simple avatars and cards, premium SaaS style, clean layout, soft shadows, orange accents.',
        ],
        [
            'file' => 'feature-4.png',
            'ratio' => '3:2',
            'prompt' => 'Grid of on-brand social visual tiles, premium SaaS illustration style, clean white background, orange accents.',
        ],
        [
            'file' => 'blog-featured.png',
            'ratio' => '3:2',
            'prompt' => 'Editorial photo-style scene with framed artwork and soft warm lighting. Minimal, premium, soft depth of field, orange accent tone, clean aesthetic.',
        ],
        [
            'file' => 'blog-1.png',
            'ratio' => '3:2',
            'prompt' => 'Minimal product studio scene with screens and devices, warm orange glow, clean background, premium SaaS blog thumbnail style.',
        ],
        [
            'file' => 'blog-2.png',
            'ratio' => '3:2',
            'prompt' => 'Soft, minimal scene with a single cloud or abstract form on a light background, calm tones with a hint of orange, premium editorial style.',
        ],
        [
            'file' => 'blog-3.png',
            'ratio' => '3:2',
            'prompt' => 'Minimal desk scene with a calculator or device, warm sunlight and orange accents, premium editorial blog thumbnail style.',
        ],
    ];

    $ai = app(AIServiceManager::class);
    $outputDir = public_path('images/landing');
    if (! is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    foreach ($targets as $target) {
        $outPath = $outputDir.DIRECTORY_SEPARATOR.$target['file'];
        if (file_exists($outPath) && ! $force) {
            $this->info("Skipping {$target['file']} (exists). Use --force to overwrite.");

            continue;
        }

        $prompt = $target['prompt']."\n\nOUTPUT FORMAT:\n- Compose for {$target['ratio']} aspect ratio.";
        $this->info("Generating {$target['file']}...");

        $format = match ($target['ratio']) {
            '1:1' => 'square',
            '3:2', '16:9' => 'landscape',
            '2:3', '9:16' => 'portrait',
            default => 'square',
        };

        try {
            $result = $ai->generateWithReferences($prompt, $referenceImagePaths, [], $format); // Default model (Seedream)

            if (! isset($result['image_base64'])) {
                $this->error("Failed to generate {$target['file']}: No image result.");

                continue;
            }

            $imageData = base64_decode($result['image_base64']);
            file_put_contents($outPath, $imageData);

            $this->info("Saved {$target['file']} to public/images/landing.");
        } catch (\Throwable $e) {
            $this->error("Error generating {$target['file']}: ".$e->getMessage());
        }
    }
})->purpose('Generate homepage images using OpenRouter seedream-4.5 and save to public/images/landing');
