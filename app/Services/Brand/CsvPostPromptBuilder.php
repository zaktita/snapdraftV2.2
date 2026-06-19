<?php

namespace App\Services\Brand;

use App\Models\GenerationHistory;
use App\Models\Project;
use App\Models\ProjectCluster;
use App\Services\AI\DTO\PostGenerationResult;
use App\Services\AI\OpenRouter\OpenRouterCsvPostGenerator;
use App\Services\FormatPresetMapper;
use App\Services\Prompt\SkillPromptBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CsvPostPromptBuilder
{
    public function __construct(
        protected OpenRouterCsvPostGenerator $postGenerator,
        protected ProjectClusterSelector $clusterSelector,
    ) {}

    /**
     * @return list<array{rowIndex: int, historyId: int, title: string, format: string, clusterKey: string, resolution_multiplier: int}>
     */
    public function buildBatch(Project $project): array
    {
        $csvData = $project->settings['csv_data'] ?? [];
        $historyIds = $project->settings['history_ids'] ?? [];
        $resolutionMultiplier = (int) data_get($project->settings, 'resolution_multiplier', 1);

        if ($csvData === []) {
            throw new RuntimeException('csv_data missing from project settings.');
        }

        $promptBatch = [];

        foreach ($csvData as $i => $row) {
            $history = $this->resolveHistory($historyIds, $i);
            if ($history === null) {
                continue;
            }

            try {
                $caption = trim((string) ($row['caption'] ?? $row['title'] ?? ''));
                $format = FormatPresetMapper::normalize((string) ($row['format'] ?? 'square'));
                $aspectRatio = FormatPresetMapper::aspectRatio($format);

                Log::info('CsvPostPromptBuilder: generating post JSON', [
                    'project_id' => $project->id,
                    'row_index' => $i,
                    'caption' => $caption,
                ]);

                $result = $this->postGenerator->generateForRow($project, $caption, $aspectRatio);
                $promptBatch[] = $this->finalizeRow(
                    $project,
                    $i,
                    $row,
                    $history,
                    $result,
                    $format,
                    $aspectRatio,
                    $resolutionMultiplier,
                );
            } catch (\Throwable $e) {
                $this->failRow($project->id, $i, $history, $e);
            }
        }

        return $promptBatch;
    }

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
                $format = FormatPresetMapper::normalize((string) ($row['format'] ?? 'square'));
                $aspectRatio = FormatPresetMapper::aspectRatio($format);

                $cluster = $project->clusters()->where('key', $match['cluster_key'])->first();
                if (! $cluster) {
                    throw new RuntimeException("Cluster \"{$match['cluster_key']}\" not found for row {$i}.");
                }

                Log::info('CsvPostPromptBuilder: generating post JSON from match', [
                    'project_id' => $project->id,
                    'row_index' => $i,
                    'cluster_key' => $cluster->key,
                ]);

                $result = $this->postGenerator->generateForRowWithCluster(
                    $project,
                    $caption,
                    $aspectRatio,
                    $cluster,
                );

                $preferredIds = $match['cluster_image_ids'] ?? null;
                $selectedClusterImages = $this->clusterSelector->imagesForCluster(
                    $cluster,
                    is_array($preferredIds) && $preferredIds !== [] ? $preferredIds : null,
                );

                $promptBatch[] = $this->finalizeRow(
                    $project,
                    $i,
                    $row,
                    $history,
                    $result,
                    $format,
                    $aspectRatio,
                    $resolutionMultiplier,
                    $cluster,
                    $selectedClusterImages,
                );
            } catch (\Throwable $e) {
                $this->failRow($project->id, $i, $history, $e);
            }
        }

        return $promptBatch;
    }

    /**
     * @return array{
     *     prompt_batch: list<array{rowIndex: int, historyId: int, title: string, format: string, clusterKey: string, resolution_multiplier: int}>,
     *     step2_debug: list<array<string, mixed>>
     * }
     */
    public function buildLabBatch(
        Project $project,
        ProjectClusterSelector $clusterSelector,
        SkillPromptBuilder $promptBuilder,
    ): array {
        $csvData = $project->settings['csv_data'] ?? [];
        $historyIds = $project->settings['history_ids'] ?? [];
        $resolutionMultiplier = (int) data_get($project->settings, 'resolution_multiplier', 1);

        if ($csvData === []) {
            throw new RuntimeException('csv_data missing from project settings.');
        }

        $promptBatch = [];
        $step2Debug = [];

        foreach ($csvData as $i => $row) {
            $history = $this->resolveHistory($historyIds, $i);
            if ($history === null) {
                continue;
            }

            try {
                $caption = trim((string) ($row['caption'] ?? $row['title'] ?? ''));
                $format = FormatPresetMapper::normalize((string) ($row['format'] ?? 'square'));
                $aspectRatio = FormatPresetMapper::aspectRatio($format);

                Log::info('CsvPostPromptBuilder: generating post JSON (lab)', [
                    'project_id' => $project->id,
                    'row_index' => $i,
                    'caption' => $caption,
                ]);

                $clusterScores = $clusterSelector->scoreClusters($project, $caption);
                $result = $this->postGenerator->generateForRow($project, $caption, $aspectRatio);

                $project->loadMissing(['clusters.images.brandReference']);
                $cluster = $project->clusters()->where('key', $result->clusterKey)->first()
                    ?? $project->clusters()->first();

                $selectedClusterImages = $cluster
                    ? $clusterSelector->imagesForCluster($cluster)
                    : collect();

                $promptBatch[] = $this->finalizeRow(
                    $project,
                    $i,
                    $row,
                    $history,
                    $result,
                    $format,
                    $aspectRatio,
                    $resolutionMultiplier,
                    $cluster,
                    $selectedClusterImages,
                );

                $history->refresh();

                $step2Debug[] = $this->buildLabDebugEntry(
                    $i,
                    $caption,
                    $format,
                    $aspectRatio,
                    $result,
                    $cluster,
                    $selectedClusterImages,
                    $clusterScores,
                    $promptBuilder,
                    is_array($history->prompt_json) ? $history->prompt_json : null,
                );
            } catch (\Throwable $e) {
                $this->failRow($project->id, $i, $history, $e);
                $step2Debug[] = [
                    'row_index' => $i,
                    'json_valid' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'prompt_batch' => $promptBatch,
            'step2_debug' => $step2Debug,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildLabPrompt(
        Project $project,
        string $caption,
        string $format,
        SkillPromptBuilder $promptBuilder,
    ): array {
        if (! is_array($project->dna_json) || $project->dna_json === []) {
            throw new RuntimeException('Project has no brand DNA. Run extraction first.');
        }

        $caption = trim($caption);
        if ($caption === '') {
            throw new RuntimeException('Caption is required for post prompt generation.');
        }

        $format = FormatPresetMapper::normalize($format);
        $aspectRatio = FormatPresetMapper::aspectRatio($format);

        Log::info('CsvPostPromptBuilder: generating lab post JSON', [
            'project_id' => $project->id,
            'caption' => $caption,
        ]);

        $clusterScores = $this->clusterSelector->scoreClusters($project, $caption);
        $result = $this->postGenerator->generateForRow($project, $caption, $aspectRatio);
        $this->assertRowResultValid($result, 0);

        $project->loadMissing(['clusters.images.brandReference']);
        $cluster = $project->clusters()->where('key', $result->clusterKey)->first()
            ?? $project->clusters()->first();

        $selectedClusterImages = $cluster
            ? $this->clusterSelector->imagesForCluster($cluster)
            : collect();

        $promptJson = $this->enrichPromptJson(
            $result->promptJson,
            $project,
            $cluster,
            $selectedClusterImages,
            $aspectRatio,
        );

        return [
            'raw_text' => $result->rawText,
            'analysis_prose' => $result->analysisProse,
            'tweaks' => $result->tweaks,
            'prompt_json' => $promptJson,
            'json_valid' => $result->jsonValid,
            'json_validation_errors' => $result->jsonValidationErrors,
            'cluster_key' => $result->clusterKey,
            'cluster_label' => $cluster?->label,
            'cluster_scores' => $clusterScores,
            'selected_cluster_images' => $this->clusterImagesForDebug($selectedClusterImages),
            'system_prompt' => $promptBuilder->systemPrompt('generate_post'),
            'model_slug' => config('ai.default_post_model_slug', 'gpt-4o'),
            'tokens_in' => $result->tokensIn,
            'tokens_out' => $result->tokensOut,
            'estimated_cost_usd' => $result->estimatedCostUsd,
            'latency_ms' => $result->latencyMs,
            'caption_used' => $caption,
            'format' => $format,
            'aspect_ratio' => $aspectRatio,
            'cluster_image_ids' => $selectedClusterImages->pluck('id')->values()->all(),
            'reference_count' => $selectedClusterImages->count(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  Collection<int, \App\Models\ProjectClusterImage>|null  $selectedClusterImages
     * @return array{rowIndex: int, historyId: int, title: string, format: string, clusterKey: string, resolution_multiplier: int}
     */
    protected function finalizeRow(
        Project $project,
        int $rowIndex,
        array $row,
        GenerationHistory $history,
        PostGenerationResult $result,
        string $format,
        string $aspectRatio,
        int $resolutionMultiplier,
        ?ProjectCluster $cluster = null,
        ?Collection $selectedClusterImages = null,
    ): array {
        $this->assertRowResultValid($result, $rowIndex);

        $project->loadMissing(['clusters.images.brandReference']);

        if ($cluster === null) {
            $cluster = $project->clusters()->where('key', $result->clusterKey)->first()
                ?? $project->clusters()->first();
        }

        if ($selectedClusterImages === null) {
            $selectedClusterImages = $cluster
                ? $this->clusterSelector->imagesForCluster($cluster)
                : collect();
        }

        $promptJson = $this->enrichPromptJson(
            $result->promptJson,
            $project,
            $cluster,
            $selectedClusterImages,
            $aspectRatio,
        );

        $history->update([
            'prompt_json' => $promptJson,
            'cluster_key' => $cluster?->key ?? $result->clusterKey,
            'json_valid' => $result->jsonValid,
            'ai_model' => config('services.gemini.image_model', 'gemini-3.1-flash-image-preview'),
            'request_data' => [
                'post_generation' => [
                    'analysis_prose' => $result->analysisProse,
                    'json_validation_errors' => $result->jsonValidationErrors,
                    'tweaks' => $result->tweaks,
                    'tokens_in' => $result->tokensIn,
                    'tokens_out' => $result->tokensOut,
                    'latency_ms' => $result->latencyMs,
                ],
                'cluster_image_ids' => $selectedClusterImages->pluck('id')->values()->all(),
            ],
        ]);

        return [
            'rowIndex' => $rowIndex,
            'historyId' => $history->id,
            'title' => $row['title'] ?? '',
            'format' => $format,
            'clusterKey' => $cluster?->key ?? $result->clusterKey,
            'resolution_multiplier' => $resolutionMultiplier,
        ];
    }

    protected function assertRowResultValid(PostGenerationResult $result, int $rowIndex): void
    {
        if ($result->promptJson === null || trim((string) ($result->promptJson['post']['concept'] ?? '')) === '') {
            throw new RuntimeException("Post JSON generation failed for row {$rowIndex}: missing post.concept.");
        }

        if (! $result->jsonValid) {
            throw new RuntimeException(
                "Post JSON validation failed for row {$rowIndex}: "
                .implode('; ', $result->jsonValidationErrors ?: ['unknown validation error'])
            );
        }
    }

    /**
     * @param  array<int, int>  $historyIds
     */
    protected function resolveHistory(array $historyIds, int $rowIndex): ?GenerationHistory
    {
        $historyId = $historyIds[$rowIndex] ?? null;

        if (! $historyId) {
            Log::error('CsvPostPromptBuilder: missing history for row', [
                'row_index' => $rowIndex,
            ]);

            return null;
        }

        $history = GenerationHistory::find($historyId);
        if (! $history) {
            Log::error('CsvPostPromptBuilder: history record not found', [
                'row_index' => $rowIndex,
                'history_id' => $historyId,
            ]);
        }

        return $history;
    }

    protected function failRow(int $projectId, int $rowIndex, GenerationHistory $history, \Throwable $e): void
    {
        Log::error('CsvPostPromptBuilder: row failed', [
            'project_id' => $projectId,
            'row_index' => $rowIndex,
            'error' => $e->getMessage(),
        ]);

        $history->markAsFailed('Post prompt generation failed: '.$e->getMessage());
    }

    /**
     * @param  array<string, mixed>|null  $promptJson
     * @param  Collection<int, \App\Models\ProjectClusterImage>  $selectedClusterImages
     * @return array<string, mixed>
     */
    protected function enrichPromptJson(
        ?array $promptJson,
        Project $project,
        ?ProjectCluster $cluster,
        Collection $selectedClusterImages,
        string $aspectRatio,
    ): array {
        $promptJson ??= [];

        if (! isset($promptJson['meta']['aspect_ratio'])) {
            $promptJson['meta'] = array_merge($promptJson['meta'] ?? [], [
                'aspect_ratio' => $aspectRatio,
                'target_generator' => 'nano-banana',
            ]);
        }

        if (! $cluster) {
            return $promptJson;
        }

        $count = $selectedClusterImages->count();
        $promptJson['cluster_context'] = $this->clusterSelector->clusterMetadata($project, $cluster);
        $promptJson['reference_usage'] = 'Use all '.$count.' attached reference images as the complete cluster template set for "'.$cluster->label.'". '
            .'Match layout, palette, typography, background treatment, composition, and text placement from this cluster family.';

        return $promptJson;
    }

    /**
     * @param  Collection<int, \App\Models\ProjectClusterImage>  $selectedClusterImages
     * @return list<array<string, mixed>>
     */
    protected function clusterImagesForDebug(Collection $selectedClusterImages): array
    {
        return $selectedClusterImages
            ->map(function ($img) {
                $ref = $img->brandReference;

                return [
                    'brand_reference_id' => $ref?->id,
                    'url' => $ref?->url,
                    'label' => $ref ? 'Reference '.($ref->order + 1) : null,
                    'is_anchor' => $img->is_anchor,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  Collection<int, \App\Models\ProjectClusterImage>  $selectedClusterImages
     * @param  array<string, mixed>  $clusterScores
     * @return array<string, mixed>
     */
    protected function buildLabDebugEntry(
        int $rowIndex,
        string $caption,
        string $format,
        string $aspectRatio,
        PostGenerationResult $result,
        ?ProjectCluster $cluster,
        Collection $selectedClusterImages,
        array $clusterScores,
        SkillPromptBuilder $promptBuilder,
        ?array $promptJson,
    ): array {
        return [
            'row_index' => $rowIndex,
            'raw_text' => $result->rawText,
            'analysis_prose' => $result->analysisProse,
            'tweaks' => $result->tweaks,
            'prompt_json' => $promptJson,
            'json_valid' => $result->jsonValid,
            'json_validation_errors' => $result->jsonValidationErrors,
            'cluster_key' => $result->clusterKey,
            'cluster_label' => $cluster?->label,
            'cluster_scores' => $clusterScores,
            'selected_cluster_images' => $this->clusterImagesForDebug($selectedClusterImages),
            'system_prompt' => $promptBuilder->systemPrompt('generate_post'),
            'prompt_config' => $promptBuilder->promptConfig('generate_post'),
            'model_slug' => config('ai.default_post_model_slug', 'gpt-4o'),
            'tokens_in' => $result->tokensIn,
            'tokens_out' => $result->tokensOut,
            'estimated_cost_usd' => $result->estimatedCostUsd,
            'latency_ms' => $result->latencyMs,
            'caption_used' => $caption,
            'format' => $format,
            'aspect_ratio' => $aspectRatio,
        ];
    }
}
