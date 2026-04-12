<?php

namespace App\Jobs;

use App\Models\BrandReference;
use App\Models\Image;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanOrphanedFilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function handle(): void
    {
        $deleted = 0;
        $disk    = Storage::disk('public');

        // ── Images ─────────────────────────────────────────────────────────
        // Collect all paths referenced in the images table
        $knownImagePaths = Image::withTrashed()
            ->whereNotNull('url')
            ->pluck('url')
            ->merge(
                Image::withTrashed()->whereNotNull('thumbnail_url')->pluck('thumbnail_url')
            )
            ->unique()
            ->flip(); // flip for O(1) key lookup

        // Scan the projects/ directory and remove files not tracked in DB
        foreach ($disk->allFiles('projects') as $file) {
            if (!isset($knownImagePaths[$file])) {
                $disk->delete($file);
                $deleted++;
                Log::info("CleanOrphanedFilesJob: deleted orphaned image file [{$file}]");
            }
        }

        // ── Brand references ───────────────────────────────────────────────
        $knownRefPaths = BrandReference::withTrashed()
            ->whereNotNull('url')
            ->pluck('url')
            ->map(fn ($url) => ltrim(str_replace('storage/', '', $url), '/'))
            ->merge(
                BrandReference::withTrashed()
                    ->whereNotNull('thumbnail_url')
                    ->pluck('thumbnail_url')
                    ->map(fn ($url) => ltrim(str_replace('storage/', '', $url), '/'))
            )
            ->unique()
            ->flip();

        foreach ($disk->allFiles('brand-references') as $file) {
            if (!isset($knownRefPaths[$file])) {
                $disk->delete($file);
                $deleted++;
                Log::info("CleanOrphanedFilesJob: deleted orphaned brand-reference file [{$file}]");
            }
        }

        Log::info("CleanOrphanedFilesJob: completed, deleted {$deleted} orphaned file(s)");
    }
}
