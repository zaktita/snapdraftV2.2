<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Project;
use Illuminate\Support\Facades\Storage;

$project = Project::find(9);
echo "Project: " . $project->title . "\n";
echo "Brand References:\n";

foreach ($project->brandReferences as $ref) {
    $path = str_replace('storage/', '', $ref->url);
    $exists = Storage::disk('public')->exists($path);
    echo "  URL: " . $ref->url . "\n";
    echo "  Path: " . $path . "\n";
    echo "  Exists: " . ($exists ? '✅' : '❌') . "\n";
    echo "  Full path: " . Storage::disk('public')->path($path) . "\n";
    echo "\n";
}

// Also check filesystem directly
echo "\nFilesystem check:\n";
$dir = storage_path('app/public/projects/9/references');
if (is_dir($dir)) {
    $files = scandir($dir);
    echo "Files in $dir:\n";
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..') {
            echo "  - $f\n";
        }
    }
} else {
    echo "Directory not found: $dir\n";
}
