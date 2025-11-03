<?php

use App\Jobs\CleanOrphanedFilesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule orphaned files cleanup to run daily at 2 AM
Schedule::job(new CleanOrphanedFilesJob)->dailyAt('02:00');
