<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Project;
use App\Jobs\GenerateSingleImageJob;

$project = Project::find(9);
if ($project) {
    GenerateSingleImageJob::dispatch(
        project: $project,
        caption: "🎓 Professional MBA graduate wearing business attire",
        title: "MBA Success",
        description: null,
        format: 'square'
    );
    echo "✅ Job dispatched for Project #9\n";
} else {
    echo "❌ Project not found\n";
}
