<?php

namespace App\Jobs;

use App\Models\BrandReference;
use App\Models\Image;
use App\Services\UserMediaStorage;
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

    public function handle(UserMediaStorage $media): void
    {
        $deleted = 0;

        $knownImagePaths = Image::withTrashed()
            ->whereNotNull('url')
            ->pluck('url')
            ->merge(
                Image::withTrashed()->whereNotNull('thumbnail_url')->pluck('thumbnail_url')
            )
            ->unique()
            ->flip();

        $knownRefPaths = BrandReference::withTrashed()
            ->whereNotNull('url')
            ->pluck('url')
            ->merge(
                BrandReference::withTrashed()->whereNotNull('thumbnail_url')->pluck('thumbnail_url')
            )
            ->unique()
            ->flip();

        foreach ([$media->diskName(), 'public'] as $diskName) {
            $disk = Storage::disk($diskName);

            foreach ($disk->allFiles('projects') as $file) {
                if (! isset($knownImagePaths[$file])) {
                    $disk->delete($file);
                    $deleted++;
                    Log::info("CleanOrphanedFilesJob: deleted orphaned image [{$diskName}:{$file}]");
                }
            }

            foreach ($disk->allFiles('brand-references') as $file) {
                if (! isset($knownRefPaths[$file])) {
                    $disk->delete($file);
                    $deleted++;
                    Log::info("CleanOrphanedFilesJob: deleted orphaned brand-reference [{$diskName}:{$file}]");
                }
            }
        }

        Log::info("CleanOrphanedFilesJob: completed, deleted {$deleted} orphaned file(s)");
    }
}
