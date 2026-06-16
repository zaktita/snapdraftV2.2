<?php

namespace App\Jobs;

use App\Mail\JobFailedNotification;
use App\Models\GenerationHistory;
use App\Models\Image;
use App\Models\Project;
use App\Models\ProjectCluster;
use App\Services\AI\GeminiCsvImageGenerator;
use App\Services\Brand\ProjectClusterSelector;
use App\Services\FormatPresetMapper;
use App\Services\Prompt\JsonPromptCompiler;
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
        public readonly int $projectId,
        public readonly int $sessionId,
        public readonly array $promptItem,
    ) {}

    public function handle(
        JsonPromptCompiler $compiler,
        GeminiCsvImageGenerator $imageGenerator,
        ProjectClusterSelector $clusterSelector,
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $project = Project::findOrFail($this->projectId);
        $rowIndex = $this->promptItem['rowIndex'];
        $historyId = $this->promptItem['historyId']
            ?? ($project->settings['history_ids'][$rowIndex] ?? null);
        $history = $historyId ? GenerationHistory::find($historyId) : null;

        $user = $project->user;
        $creditDeducted = false;
        $resolutionMultiplier = (int) (
            data_get($this->promptItem, 'resolution_multiplier')
            ?? data_get($history?->parameters, 'resolution_multiplier')
            ?? data_get($project->settings, 'resolution_multiplier', 1)
        );
        if (! in_array($resolutionMultiplier, [1, 2, 4], true)) {
            $resolutionMultiplier = 1;
        }

        try {
            $history?->update(['status' => 'processing']);

            $promptJson = $history?->prompt_json;
            if (! is_array($promptJson) || trim((string) ($promptJson['post']['concept'] ?? '')) === '') {
                throw new \RuntimeException("Missing post JSON for row {$rowIndex}.");
            }

            $format = FormatPresetMapper::normalize((string) ($this->promptItem['format'] ?? 'square'));
            $aspectRatio = FormatPresetMapper::aspectRatio($format);

            $compiled = $compiler->compile($promptJson);
            $history?->update(['compiled_prompt' => $compiled]);

            $clusterKey = $this->promptItem['clusterKey'] ?? $history?->cluster_key;
            if (! is_string($clusterKey) || $clusterKey === '') {
                throw new \RuntimeException("Missing cluster key for row {$rowIndex}.");
            }

            $cluster = $project->clusters()->where('key', $clusterKey)->first();
            if (! $cluster instanceof ProjectCluster) {
                throw new \RuntimeException("Cluster \"{$clusterKey}\" not found for row {$rowIndex}.");
            }

            $preferredImageIds = data_get($history?->request_data, 'cluster_image_ids');
            $clusterImages = $clusterSelector->imagesForCluster($cluster);

            if ($clusterImages->count() < 1 && is_array($preferredImageIds) && $preferredImageIds !== []) {
                $clusterImages = $clusterSelector->imagesForCluster($cluster, $preferredImageIds);
            }

            if ($clusterImages->isEmpty()) {
                throw new \RuntimeException(
                    "No reference images found for cluster \"{$clusterKey}\" on row {$rowIndex}."
                );
            }

            $caption = trim((string) (
                $this->promptItem['title']
                ?? data_get($history?->parameters, 'caption')
                ?? data_get($promptJson, 'post.caption')
                ?? ''
            ));

            $imageRequestPrompt = $compiler->buildImageRequestPrompt(
                $promptJson,
                $clusterImages->count(),
                $caption !== '' ? $caption : null,
            );

            $history?->update([
                'request_data' => array_merge($history->request_data ?? [], [
                    'image_generation' => [
                        'cluster_key' => $clusterKey,
                        'cluster_image_ids' => $clusterImages->pluck('id')->values()->all(),
                        'reference_count' => $clusterImages->count(),
                        'sent_to_model' => 'gemini/buildImageRequestPrompt',
                        'model' => $imageGenerator->model(),
                        'image_request_prompt' => $imageRequestPrompt,
                    ],
                ]),
            ]);

            Log::info('GenerateSingleImageJob: generating image', [
                'project_id' => $this->projectId,
                'row_index' => $rowIndex,
                'cluster_key' => $clusterKey,
                'reference_count' => $clusterImages->count(),
                'resolution_multiplier' => $resolutionMultiplier,
            ]);

            if ($user->hasActiveSubscription()) {
                $user->useCredit($resolutionMultiplier);
                $creditDeducted = true;
            }

            $binary = $imageGenerator->generateFromPromptJson(
                $promptJson,
                $clusterImages,
                $aspectRatio,
                $resolutionMultiplier,
                $caption !== '' ? $caption : null,
            );

            $directory = "projects/{$this->projectId}/images";
            $uuid = Str::uuid()->toString();
            $imagePath = "{$directory}/{$uuid}.png";
            $thumbPath = "{$directory}/{$uuid}_thumb.png";

            Storage::disk('public')->put($imagePath, $binary);

            $this->createThumbnail($imagePath, $thumbPath);

            $absolutePath = storage_path("app/public/{$imagePath}");
            [$width, $height] = getimagesize($absolutePath) ?: [null, null];

            $image = Image::create([
                'project_id' => $this->projectId,
                'url' => $imagePath,
                'thumbnail_url' => $thumbPath,
                'prompt' => json_encode($promptJson, JSON_UNESCAPED_UNICODE),
                'format' => $format,
                'width' => $width,
                'height' => $height,
                'metadata' => [
                    'csv_row_index' => $rowIndex,
                    'title' => $this->promptItem['title'] ?? '',
                    'cluster_key' => $clusterKey,
                    'wizard_type' => 'csv',
                    'resolution_multiplier' => $resolutionMultiplier,
                ],
            ]);

            $history?->markAsCompleted([
                'image_id' => $image->id,
            ]);

            Log::info('GenerateSingleImageJob: image saved', [
                'project_id' => $this->projectId,
                'row_index' => $rowIndex,
                'image_id' => $image->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateSingleImageJob: failed', [
                'project_id' => $this->projectId,
                'row_index' => $rowIndex,
                'error' => $e->getMessage(),
            ]);

            if ($creditDeducted) {
                try {
                    $user->refundCredit($resolutionMultiplier);
                } catch (\Throwable $refundError) {
                    Log::warning('GenerateSingleImageJob: failed to refund credit', [
                        'project_id' => $this->projectId,
                        'error' => $refundError->getMessage(),
                    ]);
                }
            }

            $history?->markAsFailed($this->friendlyErrorMessage($e));
            throw $e;
        }
    }

    private function friendlyErrorMessage(\Throwable $e): string
    {
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

    public function failed(\Throwable $exception): void
    {
        try {
            $project = Project::find($this->projectId);
            $user = $project?->user;

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
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function createThumbnail(string $imagePath, string $thumbPath): void
    {
        try {
            $absoluteSource = storage_path("app/public/{$imagePath}");
            $manager = new ImageManager(new Driver());
            $img = $manager->read($absoluteSource);

            $img->scale(width: 400);

            $absoluteThumb = storage_path("app/public/{$thumbPath}");

            $dir = dirname($absoluteThumb);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $img->toPng()->save($absoluteThumb);
        } catch (\Throwable $e) {
            Log::warning('GenerateSingleImageJob: thumbnail creation failed', [
                'error' => $e->getMessage(),
                'path' => $imagePath,
            ]);
        }
    }
}
