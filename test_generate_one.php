<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Project;
use App\Jobs\GenerateBatchImagesJob;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

$user = App\Models\User::first();
if (!$user) {
    echo "No user found.\n"; exit(1);
}

if (!$user->hasCredits()) {
    echo "User has no credits. Please add credits and retry.\n"; exit(1);
}

$name = 'Wizard Smoke Test ' . date('H:i:s');

$project = $user->projects()->create([
    'name' => $name,
    'title' => $name,
    'description' => 'Automated single-row generation test',
    'settings' => [
        'wizard_type' => 'csv',
        'text_accurate' => false,
        'csv_data' => [
            [
                'title' => 'Test Promo',
                'description' => 'Modern bold ad with clean product focus and brand colors',
                'format' => 'square',
            ],
        ],
    ],
]);

echo "Created project #{$project->id}: {$name}\n";

// Dispatch batch job
GenerateBatchImagesJob::dispatch($project);
echo "Dispatched batch generation. Polling for image...\n";

$timeout = 90; // seconds
$interval = 3; // seconds
$elapsed = 0;

while ($elapsed < $timeout) {
    $count = $project->images()->count();
    if ($count > 0) {
        $image = $project->images()->latest('id')->first();
        echo "SUCCESS: Image created (ID {$image->id}) at {$image->url}\n";
        exit(0);
    }
    sleep($interval);
    $elapsed += $interval;
    // refresh project relation state
    $project->refresh();
    echo ".";
}

echo "\nTIMEOUT: No image was created within {$timeout}s. Check storage/logs/laravel.log for errors.\n";
exit(2);
