<?php

namespace App\Services\Brand;

use App\Models\GenerationHistory;
use App\Models\Project;
use App\Models\ProjectCluster;
use App\Services\FormatPresetMapper;
use App\Services\Test\MasterPromptLabService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Build per-row master prompts from matched cluster refs + caption (production CSV pipelines).
 */
class CsvMasterPromptBuilder
{
    public function __construct(
        protected MasterPromptLabService $masterPromptLab,
        protected ProjectClusterSelector $clusterSelector,
    ) {}

    /**
     * @return list<array{rowIndex: int, historyId: int, title: string, format: string, clusterKey: string, resolution_multiplier: int}>
     */
    public function buildBatchFromMatches(Project $project): array
    {
        $csvData = $project->settings['csv_data'] ?? [];
        $historyIds = $project->settings['history_ids'] ?? [];
        $rowMatches = $project->settings['cluster_csv_pipeline']['row_matches'] ?? [];
        $resolutionMultiplier = (int) data_get($project->settings, 'resolution_multiplier', 1);

        if ($csvData === []) {
            throw new RuntimeException('csv_data missing from project settings.');
        }

        if ($rowMatches === []) {
            throw new RuntimeException('row_matches missing — run caption matching first.');
        }

        $project->loadMissing(['clusters.images.brandReference']);
        $promptBatch = [];

        foreach ($csvData as $i => $row) {
            $history = $this->resolveHistory($historyIds, $i);
            if ($history === null) {
                continue;
            }

            try {
                $match = $rowMatches[$i] ?? null;
                if (! is_array($match) || empty($match['cluster_key'])) {
                    throw new RuntimeException("No cluster match for CSV row {$i}.");
                }

                $caption = trim((string) ($row['caption'] ?? $row['title'] ?? ''));
                if ($caption === '') {
                    throw new RuntimeException("Caption is empty for CSV row {$i}.");
                }

                $format = FormatPresetMapper::normalize((string) ($row['format'] ?? 'square'));
                $aspectRatio = FormatPresetMapper::aspectRatio($format);

                $cluster = $project->clusters()->where('key', $match['cluster_key'])->first();
                if (! $cluster instanceof ProjectCluster) {
                    throw new RuntimeException("Cluster \"{$match['cluster_key']}\" not found for row {$i}.");
                }

                $preferredIds = $match['cluster_image_ids'] ?? null;
                $clusterImages = $this->clusterSelector->imagesForCluster(
                    $cluster,
                    is_array($preferredIds) && $preferredIds !== [] ? $preferredIds : null,
                );

                $storagePaths = $this->storagePathsFromClusterImages($clusterImages);
                if (count($storagePaths) < 3) {
                    throw new RuntimeException(
                        "Cluster \"{$cluster->key}\" has ".count($storagePaths)
                        .' usable reference image(s) but 3 are required for master prompt build.'
                    );
                }

                Log::info('CsvMasterPromptBuilder: building master prompt', [
                    'project_id' => $project->id,
                    'row_index' => $i,
                    'cluster_key' => $cluster->key,
                ]);

                $built = $this->masterPromptLab->buildFromStoragePaths(
                    array_slice($storagePaths, 0, 3),
                    $caption,
                    $aspectRatio,
                );

                $masterPrompt = trim((string) ($built['master_prompt'] ?? ''));
                if ($masterPrompt === '') {
                    throw new RuntimeException("Empty master prompt for row {$i}.");
                }

                $imageIds = $clusterImages->take(3)->pluck('id')->values()->all();

                $promptJson = [
                    'pipeline' => 'master_prompt_lab',
                    'master_prompt' => $masterPrompt,
                    'slots_detected' => $built['slots_detected'] ?? [],
                    'copy' => $built['copy'] ?? [],
                    'visual_lock_summary' => $built['visual_lock_summary'] ?? '',
                    'meta' => [
                        'aspect_ratio' => $aspectRatio,
                        'cluster_key' => $cluster->key,
                        'model_used' => $built['model_used'] ?? null,
                    ],
                ];

                $history->update([
                    'prompt_json' => $promptJson,
                    'compiled_prompt' => $masterPrompt,
                    'cluster_key' => $cluster->key,
                    'json_valid' => true,
                    'ai_model' => config('services.gemini.image_model', 'gemini-3.1-flash-image-preview'),
                    'request_data' => array_merge($history->request_data ?? [], [
                        'master_prompt_generation' => [
                            'model_used' => $built['model_used'] ?? null,
                            'slots_detected' => $built['slots_detected'] ?? [],
                            'copy' => $built['copy'] ?? [],
                            'visual_lock_summary' => $built['visual_lock_summary'] ?? '',
                            'reference_paths' => array_slice($storagePaths, 0, 3),
                        ],
                        'cluster_image_ids' => $imageIds,
                    ]),
                ]);

                $promptBatch[] = [
                    'rowIndex' => $i,
                    'historyId' => $history->id,
                    'title' => $row['title'] ?? $caption,
                    'format' => $format,
                    'clusterKey' => $cluster->key,
                    'resolution_multiplier' => $resolutionMultiplier,
                ];
            } catch (\Throwable $e) {
                Log::error('CsvMasterPromptBuilder: row failed', [
                    'project_id' => $project->id,
                    'row_index' => $i,
                    'error' => $e->getMessage(),
                ]);

                $history->update([
                    'status' => 'failed',
                    'error_message' => 'Master prompt build failed: '.$e->getMessage(),
                    'json_valid' => false,
                ]);
            }
        }

        return $promptBatch;
    }

    /**
     * @param  Collection<int, \App\Models\ProjectClusterImage>  $clusterImages
     * @return list<string>
     */
    protected function storagePathsFromClusterImages(Collection $clusterImages): array
    {
        $paths = [];

        foreach ($clusterImages as $image) {
            $ref = $image->brandReference;
            if (! $ref || ! is_string($ref->url) || $ref->url === '') {
                continue;
            }

            if (! Storage::disk('public')->exists($ref->url)) {
                continue;
            }

            $paths[] = $ref->url;
        }

        return $paths;
    }

    /**
     * @param  array<int, int>  $historyIds
     */
    protected function resolveHistory(array $historyIds, int $rowIndex): ?GenerationHistory
    {
        $historyId = $historyIds[$rowIndex] ?? null;
        if (! $historyId) {
            Log::error('CsvMasterPromptBuilder: missing history for row', [
                'row_index' => $rowIndex,
            ]);

            return null;
        }

        return GenerationHistory::find($historyId);
    }
}
