<?php

namespace App\Jobs;

use App\Mail\JobFailedNotification;
use App\Models\GenerationHistory;
use App\Models\Image;
use App\Models\Project;
use App\Services\AI\ImageGeneratorService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class GenerateSingleImageJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 2;

    public function __construct(
        public readonly int   $projectId,
        public readonly int   $sessionId,
        public readonly array $promptItem,
    ) {}

    public function handle(ImageGeneratorService $generatorService): void
    {
        // Respect batch cancellation
        if ($this->batch()?->cancelled()) {
            return;
        }

        $project   = Project::findOrFail($this->projectId);
        $rowIndex  = $this->promptItem['rowIndex'];
        $historyId = $this->promptItem['historyId']
            ?? ($project->settings['history_ids'][$rowIndex] ?? null);
        $history   = $historyId ? GenerationHistory::find($historyId) : null;

        $user           = $project->user;
        $creditDeducted = false;

        try {
            $history?->update(['status' => 'processing']);

            $refPaths = $project->settings['ref_paths'] ?? [];

            Log::info('GenerateSingleImageJob: generating image', [
                'project_id' => $this->projectId,
                'row_index'  => $rowIndex,
                'title'      => $this->promptItem['title'] ?? '',
            ]);

            // Deduct credit before generation (skips if user has no active subscription)
            if ($user->hasActiveSubscription()) {
                $user->useCredit();
                $creditDeducted = true;
            }

            $base64Data = $generatorService->generate($this->promptItem, $refPaths);

            // Save image to storage
            $directory  = "projects/{$this->projectId}/images";
            $uuid       = Str::uuid()->toString();
            $imagePath  = "{$directory}/{$uuid}.png";
            $thumbPath  = "{$directory}/{$uuid}_thumb.png";

            Storage::disk('public')->put($imagePath, base64_decode($base64Data));

            // Generate thumbnail via Intervention Image
            $this->createThumbnail($imagePath, $thumbPath);

            // Determine dimensions
            $absolutePath = storage_path("app/public/{$imagePath}");
            [$width, $height] = getimagesize($absolutePath) ?: [null, null];

            // Persist Image model
            $image = Image::create([
                'project_id'    => $this->projectId,
                'url'           => $imagePath,
                'thumbnail_url' => $thumbPath,
                'prompt'        => $this->promptItem['generationPrompt'],
                'format'        => $this->promptItem['format'] ?? 'square',
                'width'         => $width,
                'height'        => $height,
                'metadata'      => [
                    'csv_row_index' => $rowIndex,
                    'title'         => $this->promptItem['title'] ?? '',
                    'cluster_index' => $this->promptItem['clusterIndex'] ?? null,
                    'overlay_text'  => $this->promptItem['overlayText'] ?? '',
                    'wizard_type'   => 'csv',
                ],
            ]);

            $history?->markAsCompleted([
                'image_id' => $image->id,
            ]);

            Log::info('GenerateSingleImageJob: image saved', [
                'project_id' => $this->projectId,
                'row_index'  => $rowIndex,
                'image_id'   => $image->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateSingleImageJob: failed', [
                'project_id' => $this->projectId,
                'row_index'  => $rowIndex,
                'error'      => $e->getMessage(),
            ]);

            // Refund credit if it was deducted before the failure
            if ($creditDeducted) {
                try {
                    $user->refundCredit();
                } catch (\Throwable $refundError) {
                    Log::warning('GenerateSingleImageJob: failed to refund credit', [
                        'project_id' => $this->projectId,
                        'error'      => $refundError->getMessage(),
                    ]);
                }
            }

            $history?->markAsFailed($this->friendlyErrorMessage($e));
            throw $e;
        }
    }

    /**
     * Convert a raw exception into a short, user-facing failure reason.
     * Raw technical messages stay in the logs; only friendly text is stored in the DB.
     */
    private function friendlyErrorMessage(\Throwable $e): string
    {
        // AIServiceUnavailableException already has a classified human-readable message
        if ($e instanceof \App\Exceptions\AIServiceUnavailableException) {
            return $e->getMessage();
        }

        $msg = strtolower($e->getMessage());

        if (str_contains($msg, 'rate limit') || str_contains($msg, 'quota') || str_contains($msg, '429')) {
            return 'AI service rate-limited. Please try again in a few minutes.';
        }

        if (str_contains($msg, 'timed out') || str_contains($msg, 'timeout') || str_contains($msg, 'deadline')) {
            return 'Generation timed out. Please try again.';
        }

        if (str_contains($msg, 'unauthorized') || str_contains($msg, '401') || str_contains($msg, 'api key')) {
            return 'AI service authentication failed. Please contact support.';
        }

        if (str_contains($msg, 'content') || str_contains($msg, 'safety') || str_contains($msg, 'policy')) {
            return 'Content blocked by AI safety filter. Try adjusting the description.';
        }

        return 'Generation failed due to a technical error. Credits have been refunded.';
    }

    /**
     * Send a notification email to the user when the job permanently fails.
     */
    public function failed(\Throwable $exception): void
    {
        try {
            $project = Project::find($this->projectId);
            $user    = $project?->user;

            if ($user && $user->email) {
                Mail::to($user->email)->queue(new JobFailedNotification(
                    jobType: 'Image Generation',
                    projectName: $project->title ?? $project->name ?? 'Unknown Project',
                    projectId: $this->projectId,
                    errorMessage: $exception->getMessage(),
                    attemptNumber: $this->attempts(),
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('GenerateSingleImageJob: could not send failure notification', [
                'project_id' => $this->projectId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function createThumbnail(string $imagePath, string $thumbPath): void
    {
        try {
            $absoluteSource = storage_path("app/public/{$imagePath}");
            $manager        = new ImageManager(new Driver());
            $img            = $manager->read($absoluteSource);

            $img->scale(width: 400);

            $absoluteThumb = storage_path("app/public/{$thumbPath}");

            // Ensure directory exists
            $dir = dirname($absoluteThumb);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $img->toPng()->save($absoluteThumb);
        } catch (\Throwable $e) {
            Log::warning('GenerateSingleImageJob: thumbnail creation failed', [
                'error' => $e->getMessage(),
                'path'  => $imagePath,
            ]);
            // Non-fatal: main image is already saved
        }
    }
}
