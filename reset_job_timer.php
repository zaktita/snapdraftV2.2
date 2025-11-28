<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Reset the available_at time for all pending jobs to now
DB::table('jobs')->update(['available_at' => time(), 'reserved_at' => null]);

echo "Reset pending jobs availability.\n";
