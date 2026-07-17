<?php

namespace App\Jobs;

use App\Mail\JobFailedNotification;
use App\Models\GenerationHistory;
use App\Models\Image;
use App\Models\Project;
use App\Services\UserMediaStorage;
use App\Models\ProjectCluster;
use App\Services\AI\CsvImageGenerationService;
use App\Services\Brand\ProjectClusterSelector;
use App\Services\FormatPresetMapper;
use App\Services\Prompt\JsonPromptCompiler;
use App\Services\Test\MasterPromptLabService;
use App\Services\Wizards\CreativityLevel;
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
        CsvImageGenerationService $imageGenerator,
        ProjectClusterSelector $clusterSelector,
        MasterPromptLabService $masterPromptLab,
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
            if ($history?->image_id || $history?->status === 'completed') {
                Log::info('GenerateSingleImageJob: skipping already completed row', [
                    'project_id' => $this->projectId,
                    'row_index' => $rowIndex,
                    'history_id' => $history->id,
                ]);

                return;
            }

            $history?->update(['status' => 'processing']);

            $promptJson = $history?->prompt_json;
            $useMasterPrompt = CreativityLevel::usesMasterPromptPipeline($project)
                || data_get($promptJson, 'pipeline') === 'master_prompt_lab';

            $format = FormatPresetMapper::normalize((string) ($this->promptItem['format'] ?? 'square'));
            $aspectRatio = FormatPresetMapper::aspectRatio($format);

            $clusterKey = $this->promptItem['clusterKey'] ?? $history?->cluster_key;
            if (! is_string($clusterKey) || $clusterKey === '') {
                throw new \RuntimeException("Missing cluster key for row {$rowIndex}.");
            }

            $cluster = $project->clusters()->where('key', $clusterKey)->first();
            if (! $cluster instanceof ProjectCluster) {
                throw new \RuntimeException("Cluster \"{$clusterKey}\" not found for row {$rowIndex}.");
            }

            $preferredImageIds = data_get($history?->request_data, 'cluster_image_ids');
            $clusterImages = $clusterSelector->imagesForCluster(
                $cluster,
                is_array($preferredImageIds) && $preferredImageIds !== [] ? $preferredImageIds : null,
            );

            if ($clusterImages->isEmpty()) {
                throw new \RuntimeException(
                    "No reference images found for cluster \"{$clusterKey}\" on row {$rowIndex}."
                );
            }

            $minClusterImages = (int) config('ai.cluster_selection.min_images_per_cluster', 3);
            if ($clusterImages->count() < $minClusterImages) {
                throw new \RuntimeException(
                    "Cluster \"{$clusterKey}\" has {$clusterImages->count()} reference image(s) but {$minClusterImages} are required for generation."
                );
            }

            // Lab credit skips are local-only and require an explicit server-set flag
            // (never trust client-writable wizard_type alone).
            $skipCredits = app()->environment('local')
                && (($project->settings['skip_credits'] ?? false) === true);

            if ($user->hasActiveSubscription() && ! $skipCredits) {
                $user->useCredit($resolutionMultiplier);
                $creditDeducted = true;
            }

            if ($useMasterPrompt) {
                $binary = $this->generateWithMasterPrompt(
                    $history,
                    $promptJson,
                    $clusterImages,
                    $clusterKey,
                    $aspectRatio,
                    $resolutionMultiplier,
                    $rowIndex,
                    $masterPromptLab,
                );
            } else {
                $binary = $this->generateWithPostJson(
                    $history,
                    $promptJson,
                    $clusterImages,
                    $clusterKey,
                    $aspectRatio,
                    $resolutionMultiplier,
                    $rowIndex,
                    CreativityLevel::fromProject($project),
                    $compiler,
                    $imageGenerator,
                );
            }

            $directory = "projects/{$this->projectId}/images";
            $uuid = Str::uuid()->toString();
            $imagePath = "{$directory}/{$uuid}.png";
            $thumbPath = "{$directory}/{$uuid}_thumb.png";

            $media = app(UserMediaStorage::class);
            $media->put($imagePath, $binary);

            $this->createThumbnail($imagePath, $thumbPath, $media);

            $absolutePath = $media->path($imagePath);
            [$width, $height] = getimagesize($absolutePath) ?: [null, null];

            $image = Image::create([
                'project_id' => $this->projectId,
                'url' => $imagePath,
                'thumbnail_url' => $thumbPath,
                'prompt' => is_array($promptJson)
                    ? json_encode($promptJson, JSON_UNESCAPED_UNICODE)
                    : (string) ($history?->compiled_prompt ?? ''),
                'format' => $format,
                'width' => $width,
                'height' => $height,
                'metadata' => [
                    'csv_row_index' => $rowIndex,
                    'title' => $this->promptItem['title'] ?? '',
                    'cluster_key' => $clusterKey,
                    'wizard_type' => $project->settings['wizard_type'] ?? 'csv',
                    'resolution_multiplier' => $resolutionMultiplier,
                    'pipeline' => $useMasterPrompt ? 'master_prompt_lab' : 'post_json',
                ],
            ]);

            $history?->markAsCompleted([
                'image_id' => $image->id,
            ]);

            Log::info('GenerateSingleImageJob: image saved', [
                'project_id' => $this->projectId,
                'row_index' => $rowIndex,
                'image_id' => $image->id,
                'pipeline' => $useMasterPrompt ? 'master_prompt_lab' : 'post_json',
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

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\ProjectClusterImage>  $clusterImages
     * @param  array<string, mixed>|null  $promptJson
     */
    private function generateWithMasterPrompt(
        ?GenerationHistory $history,
        ?array $promptJson,
        $clusterImages,
        string $clusterKey,
        string $aspectRatio,
        int $resolutionMultiplier,
        int $rowIndex,
        MasterPromptLabService $masterPromptLab,
    ): string {
        $masterPrompt = trim((string) (
            data_get($promptJson, 'master_prompt')
            ?? $history?->compiled_prompt
            ?? ''
        ));

        if ($masterPrompt === '') {
            throw new \RuntimeException("Missing master prompt for row {$rowIndex}.");
        }

        $referencePaths = [];
        foreach ($clusterImages->take(3) as $image) {
            $ref = $image->brandReference;
            if ($ref && is_string($ref->url) && $ref->url !== '' && app(UserMediaStorage::class)->exists($ref->url)) {
                $referencePaths[] = $ref->url;
            }
        }

        if (count($referencePaths) < 3) {
            throw new \RuntimeException(
                "Cluster \"{$clusterKey}\" needs 3 usable reference images for master-prompt generation on row {$rowIndex}."
            );
        }

        $history?->update([
            'compiled_prompt' => $masterPrompt,
            'request_data' => array_merge($history->request_data ?? [], [
                'image_generation' => [
                    'pipeline' => 'master_prompt_lab',
                    'cluster_key' => $clusterKey,
                    'cluster_image_ids' => $clusterImages->take(3)->pluck('id')->values()->all(),
                    'reference_count' => 3,
                    'reference_paths' => $referencePaths,
                    'sent_to_model' => 'gemini/master_prompt',
                    'model' => config('services.gemini.image_model', 'gemini-3.1-flash-image-preview'),
                    'image_request_prompt' => $masterPrompt,
                ],
            ]),
        ]);

        Log::info('GenerateSingleImageJob: generating image (master prompt)', [
            'project_id' => $this->projectId,
            'row_index' => $rowIndex,
            'cluster_key' => $clusterKey,
            'reference_count' => 3,
            'resolution_multiplier' => $resolutionMultiplier,
        ]);

        return $masterPromptLab->generateBinary(
            $referencePaths,
            $masterPrompt,
            $aspectRatio,
            $resolutionMultiplier,
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\ProjectClusterImage>  $clusterImages
     * @param  array<string, mixed>|null  $promptJson
     */
    private function generateWithPostJson(
        ?GenerationHistory $history,
        ?array $promptJson,
        $clusterImages,
        string $clusterKey,
        string $aspectRatio,
        int $resolutionMultiplier,
        int $rowIndex,
        string $creativityLevel,
        JsonPromptCompiler $compiler,
        CsvImageGenerationService $imageGenerator,
    ): string {
        if (! is_array($promptJson) || trim((string) ($promptJson['post']['concept'] ?? '')) === '') {
            throw new \RuntimeException("Missing post JSON for row {$rowIndex}.");
        }

        $compiled = $compiler->compile($promptJson);
        $history?->update(['compiled_prompt' => $compiled]);

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
            $creativityLevel,
        );

        $history?->update([
            'request_data' => array_merge($history->request_data ?? [], [
                'image_generation' => [
                    'pipeline' => 'post_json',
                    'cluster_key' => $clusterKey,
                    'cluster_image_ids' => $clusterImages->pluck('id')->values()->all(),
                    'reference_count' => $clusterImages->count(),
                    'creativity_level' => $creativityLevel,
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

        return $imageGenerator->generateFromPromptJson(
            $promptJson,
            $clusterImages,
            $aspectRatio,
            $resolutionMultiplier,
            $caption !== '' ? $caption : null,
            $imageRequestPrompt,
        );
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

        if (str_contains($msg, 'leaked')) {
            return 'Gemini API key was revoked. Set a new GEMINI_API_KEY or AI_IMAGE_DRIVER=openrouter in .env.';
        }

        if (str_contains($msg, 'unauthorized') || str_contains($msg, '401') || str_contains($msg, '403') || str_contains($msg, 'api key')) {
            return 'AI service authentication failed. Check GEMINI_API_KEY / OPENROUTER_API_KEY in .env.';
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

    private function createThumbnail(string $imagePath, string $thumbPath, UserMediaStorage $media): void
    {
        try {
            $manager = new ImageManager(new Driver());
            $img = $manager->read($media->get($imagePath));
            $img->scale(width: 400);
            $media->put($thumbPath, $img->toPng()->toString());
        } catch (\Throwable $e) {
            Log::warning('GenerateSingleImageJob: thumbnail creation failed', [
                'error' => $e->getMessage(),
                'path' => $imagePath,
            ]);
        }
    }
}
