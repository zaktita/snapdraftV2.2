<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CsvWizardSession;
use App\Models\GenerationHistory;
use App\Models\Image;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;

trait PresentsClusterCsvSession
{
    /**
     * @return array<string, mixed>
     */
    protected function sessionPayload(CsvWizardSession $session): array
    {
        return [
            'id' => $session->id,
            'status' => $session->status,
            'total_jobs' => $session->total_jobs,
            'project_id' => $session->project_id,
            'error_message' => $session->error_message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function pipelinePayload(Project $project): array
    {
        $pipeline = $project->settings['cluster_csv_pipeline'] ?? [];
        $histories = $project->generationHistory;

        return [
            'phase' => $pipeline['phase'] ?? 'pending',
            'error' => $pipeline['error'] ?? null,
            'cluster_count' => $project->clusters()->count(),
            'dna_extracted' => $project->dna_json !== null,
            'progress' => [
                'total' => $histories->count(),
                'matched' => count($pipeline['row_matches'] ?? []),
                'prompted' => count($pipeline['prompt_batch'] ?? $project->settings['prompt_batch'] ?? []),
                'completed' => $histories->where('status', 'completed')->count(),
                'failed' => $histories->where('status', 'failed')->count(),
                'pending' => $histories->whereIn('status', ['pending', 'processing'])->count(),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function rowsPayload(Project $project): array
    {
        $csvData = $project->settings['csv_data'] ?? [];
        $historyIds = $project->settings['history_ids'] ?? [];
        $rowMatches = $project->settings['cluster_csv_pipeline']['row_matches'] ?? [];
        $promptBatch = $project->settings['prompt_batch'] ?? [];
        $promptBatchByIndex = collect($promptBatch)->keyBy('rowIndex');

        $rows = [];

        foreach ($csvData as $i => $row) {
            $historyId = $historyIds[$i] ?? null;
            $history = $historyId
                ? $project->generationHistory->firstWhere('id', $historyId)
                : null;

            $match = $rowMatches[$i] ?? null;
            $batchItem = $promptBatchByIndex->get($i);

            $imageUrl = null;
            $thumbnailUrl = null;
            if ($history?->image_id) {
                $image = Image::find($history->image_id);
                if ($image) {
                    $imageUrl = $this->storageUrl($image->url);
                    $thumbnailUrl = $image->thumbnail_url
                        ? $this->storageUrl($image->thumbnail_url)
                        : null;
                }
            }

            $rows[] = [
                'index' => $i,
                'title' => $row['title'] ?? '',
                'caption' => $row['caption'] ?? '',
                'format' => $row['format'] ?? 'square',
                'cluster_key' => $match['cluster_key'] ?? $history?->cluster_key,
                'cluster_label' => $match['cluster_label'] ?? null,
                'used_model_fallback' => $match['used_model_fallback'] ?? false,
                'top_score' => $match['top_score'] ?? null,
                'json_valid' => $history?->json_valid,
                'history_status' => $history?->status,
                'error_message' => $history?->error_message,
                'image_url' => $imageUrl,
                'thumbnail_url' => $thumbnailUrl,
                'has_prompt' => $batchItem !== null || $history?->prompt_json !== null,
            ];
        }

        return $rows;
    }

    protected function storageUrl(string $path): string
    {
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        if (str_starts_with($path, '/storage/')) {
            return $path;
        }

        return Storage::url($path);
    }
}
