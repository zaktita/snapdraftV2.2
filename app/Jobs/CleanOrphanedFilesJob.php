<?php

namespace App\Jobs;

use App\Models\BrandReference;
use App\Models\Image;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanOrphanedFilesJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting orphaned files cleanup');

        $deletedCount = 0;
        $errors = 0;

        // Get all files in the public disk under 'projects' directory
        $allFiles = Storage::disk('public')->allFiles('projects');

        // Get all image URLs from database
        $imageUrls = Image::pluck('url')->toArray();
        $imageThumbnails = Image::pluck('thumbnail_url')->toArray();
        
        // Get all brand reference URLs
        $brandReferenceUrls = BrandReference::pluck('url')->toArray();
        $brandReferenceThumbnails = BrandReference::pluck('thumbnail_url')->toArray();

        // Combine all valid file paths
        $validFiles = array_merge(
            $imageUrls,
            $imageThumbnails,
            $brandReferenceUrls,
            $brandReferenceThumbnails
        );

        // Also include CSV files (they're stored in settings, harder to track)
        // For now, we'll keep all CSV files

        foreach ($allFiles as $file) {
            // Skip CSV files and directories
            if (str_ends_with($file, '.csv') || str_ends_with($file, '/')) {
                continue;
            }

            // Check if file is in database
            if (!in_array($file, $validFiles)) {
                try {
                    // Additional check: don't delete files modified in last 24 hours (safety buffer)
                    $lastModified = Storage::disk('public')->lastModified($file);
                    $oneDayAgo = now()->subDay()->timestamp;

                    if ($lastModified < $oneDayAgo) {
                        Storage::disk('public')->delete($file);
                        $deletedCount++;
                        Log::info("Deleted orphaned file: {$file}");
                    }
                } catch (\Exception $e) {
                    $errors++;
                    Log::error("Error deleting orphaned file {$file}: " . $e->getMessage());
                }
            }
        }

        // Clean empty directories
        $this->cleanEmptyDirectories('projects');

        Log::info("Orphaned files cleanup completed. Deleted: {$deletedCount}, Errors: {$errors}");
    }

    /**
     * Recursively clean empty directories.
     */
    protected function cleanEmptyDirectories(string $path): void
    {
        $directories = Storage::disk('public')->directories($path);

        foreach ($directories as $directory) {
            // Recursively check subdirectories first
            $this->cleanEmptyDirectories($directory);

            // Check if directory is empty after cleaning subdirectories
            $files = Storage::disk('public')->files($directory);
            $subdirs = Storage::disk('public')->directories($directory);

            if (empty($files) && empty($subdirs)) {
                try {
                    Storage::disk('public')->deleteDirectory($directory);
                    Log::info("Deleted empty directory: {$directory}");
                } catch (\Exception $e) {
                    Log::error("Error deleting empty directory {$directory}: " . $e->getMessage());
                }
            }
        }
    }
}
