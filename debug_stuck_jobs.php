<?php

use App\Models\GenerationHistory;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$stuckGenerations = GenerationHistory::whereIn('status', ['pending', 'processing'])->get();

echo "Stuck Generations Count: " . $stuckGenerations->count() . "\n";

foreach ($stuckGenerations as $gen) {
    echo "ID: {$gen->id}, Status: {$gen->status}, Created: {$gen->created_at}\n";
}

$failedJobs = DB::table('failed_jobs')->count();
echo "Failed Jobs Count: " . $failedJobs . "\n";

$jobs = DB::table('jobs')->count();
echo "Pending Jobs Count: " . $jobs . "\n";
