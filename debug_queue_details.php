<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$jobs = DB::table('jobs')->get();

foreach ($jobs as $job) {
    echo "Job ID: {$job->id}\n";
    echo "Queue: {$job->queue}\n";
    echo "Attempts: {$job->attempts}\n";
    echo "Reserved At: " . ($job->reserved_at ? date('Y-m-d H:i:s', $job->reserved_at) : 'NULL') . "\n";
    echo "Available At: " . date('Y-m-d H:i:s', $job->available_at) . "\n";
    echo "Payload: " . substr($job->payload, 0, 100) . "...\n";
    echo "-------------------\n";
}
