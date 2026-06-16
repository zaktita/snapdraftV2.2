<?php

namespace App\Jobs;

use App\Models\Image;
use App\Models\Project;
use App\Models\QuickGenerateSession;
use App\Services\AI\OpenRouter\BrandDnaExtractor;
use App\Services\AI\GeminiCsvImageGenerator;
use App\Services\AI\OpenRouter\OpenRouterCsvPostGenerator;
use App\Services\Brand\ProjectBrandPersister;
use App\Services\Brand\ProjectClusterSelector;
use App\Services\FormatPresetMapper;
use App\Services\Prompt\JsonPromptCompiler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class QuickGenerateVisualJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public readonly int $sessionId,
    ) {}

    public function handle(
        BrandDnaExtractor $extractor,
        ProjectBrandPersister $persister,
        OpenRouterCsvPostGenerator $postGenerator,
        JsonPromptCompiler $compiler,
        GeminiCsvImageGenerator $imageGenerator,
        ProjectClusterSelector $clusterSelector,
    ): void {
        $session = QuickGenerateSession::findOrFail($this->sessionId);
        $project = Project::with('brandReferences')->findOrFail($session->project_id);

        try {
            $session->markAsAnalyzing();

            $refPaths = $project->settings['ref_paths'] ?? [];
            if ($refPaths === []) {
                throw new \RuntimeException('No reference image paths found on project.');
            }

            Log::info('QuickGenerateVisualJob: extracting DNA', [
                'session_id' => $this->sessionId,
                'project_id' => $project->id,
            ]);

            $dnaResult = $extractor->extract($project, $refPaths);

            if ($dnaResult->promptJson === null) {
                throw new \RuntimeException('Brand DNA extraction returned no JSON.');
            }

            if (! $dnaResult->jsonValid) {
                throw new \RuntimeException(
                    'Brand DNA validation failed: '.implode('; ', $dnaResult->jsonValidationErrors)
                );
            }

            $persister->persist($project, $dnaResult->promptJson, $dnaResult->summary);
            $project->refresh();

            $session->markAsGenerating();

            $aspectRatio = FormatPresetMapper::aspectRatio($session->format);

            Log::info('QuickGenerateVisualJob: generating post JSON', [
                'session_id' => $this->sessionId,
                'aspect_ratio' => $aspectRatio,
            ]);

            $postResult = $postGenerator->generateForRow($project, $session->caption, $aspectRatio);

            if ($postResult->promptJson === null || trim((string) ($postResult->promptJson['post']['concept'] ?? '')) === '') {
                throw new \RuntimeException('Post JSON generation failed: missing post.concept.');
            }

            $compiled = $compiler->compile($postResult->promptJson);

            $cluster = $project->clusters()->where('key', $postResult->clusterKey)->first()
                ?? $project->clusters()->first();

            if (! $cluster) {
                throw new \RuntimeException('No cluster available for image generation.');
            }

            $clusterImages = $clusterSelector->imagesForCluster($cluster);

            $selectedForDebug = $clusterImages->map(function ($img) {
                $ref = $img->brandReference;

                return [
                    'brand_reference_id' => $ref?->id,
                    'url' => $ref?->url,
                    'label' => $ref ? 'Reference '.($ref->order + 1) : null,
                    'is_anchor' => $img->is_anchor,
                ];
            })->values()->all();

            $session->update([
                'prompt_json' => $postResult->promptJson,
                'compiled_prompt' => $compiled,
                'final_prompt' => json_encode($postResult->promptJson, JSON_UNESCAPED_UNICODE),
                'cluster_key' => $cluster->key,
                'cluster_label' => $cluster->label,
                'selected_cluster_images' => $selectedForDebug,
                'selected_cluster_id' => $cluster->id,
                'selected_image_indices' => $clusterImages
                    ->map(fn ($img) => $img->brandReference?->order)
                    ->filter(fn ($i) => $i !== null)
                    ->values()
                    ->all(),
                'extracted_title' => $postResult->promptJson['post']['on_image_text'][0]['text']
                    ?? $postResult->promptJson['post']['concept']
                    ?? $session->caption,
                'extracted_description' => $postResult->promptJson['post']['caption'] ?? $session->caption,
            ]);

            Log::info('QuickGenerateVisualJob: generating image', [
                'session_id' => $this->sessionId,
                'cluster_key' => $cluster->key,
                'ref_count' => $clusterImages->count(),
            ]);

            $binary = $imageGenerator->generateFromPromptJson(
                $postResult->promptJson,
                $clusterImages,
                $aspectRatio,
                1,
                $session->caption,
            );

            $directory = "projects/{$project->id}/images";
            $uuid = Str::uuid()->toString();
            $imagePath = "{$directory}/{$uuid}.png";
            $thumbPath = "{$directory}/{$uuid}_thumb.png";

            Storage::disk('public')->put($imagePath, $binary);
            $this->createThumbnail($imagePath, $thumbPath);

            $absolutePath = storage_path("app/public/{$imagePath}");
            [$width, $height] = getimagesize($absolutePath) ?: [null, null];

            Image::create([
                'project_id' => $project->id,
                'url' => $imagePath,
                'thumbnail_url' => $thumbPath,
                'prompt' => json_encode($postResult->promptJson, JSON_UNESCAPED_UNICODE),
                'format' => $session->format,
                'width' => $width,
                'height' => $height,
                'metadata' => [
                    'wizard_type' => 'quick_generate',
                    'cluster_key' => $cluster->key,
                    'session_id' => $session->id,
                ],
            ]);

            $project->update(['status' => 'completed']);

            $session->markAsCompleted();

            Log::info('QuickGenerateVisualJob: completed', [
                'session_id' => $this->sessionId,
                'project_id' => $project->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('QuickGenerateVisualJob: failed', [
                'session_id' => $this->sessionId,
                'error' => $e->getMessage(),
            ]);

            $session->markAsFailed($e->getMessage());
            $project->update(['status' => 'failed']);

            throw $e;
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
            Log::warning('QuickGenerateVisualJob: thumbnail failed', ['error' => $e->getMessage()]);
        }
    }
}
