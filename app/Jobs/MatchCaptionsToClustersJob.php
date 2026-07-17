<?php

namespace App\Jobs;

use App\Models\CsvWizardSession;
use App\Models\GenerationHistory;
use App\Models\Project;
use App\Services\Brand\ProjectClusterSelector;
use App\Services\Wizards\ClusterCsvPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MatchCaptionsToClustersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 2;

    public function __construct(
        public readonly int $projectId,
        public readonly int $sessionId,
    ) {}

    public function handle(ProjectClusterSelector $clusterSelector): void
    {
        $project = Project::findOrFail($this->projectId);
        $session = CsvWizardSession::findOrFail($this->sessionId);

        try {
            if ($project->dna_json === null) {
                throw new \RuntimeException('dna_json missing - AnalyzeBrandJob may not have completed.');
            }

            ClusterCsvPipeline::setPhase($project, 'matching');
            $project->refresh();

            $csvData = $project->settings['csv_data'] ?? [];
            $historyIds = $project->settings['history_ids'] ?? [];

            if ($csvData === []) {
                throw new \RuntimeException('csv_data missing from project settings.');
            }

            $rowMatches = [];

            foreach ($csvData as $i => $row) {
                $caption = trim((string) ($row['caption'] ?? $row['title'] ?? ''));
                $scores = $clusterSelector->scoreClusters($project, $caption);
                $selection = $clusterSelector->select($project, $caption);
                $cluster = $selection['cluster'];

                $selectedImages = $selection['images']
                    ->map(function ($img) {
                        $ref = $img->brandReference;

                        return [
                            'cluster_image_id' => $img->id,
                            'brand_reference_id' => $ref?->id,
                            'order' => $ref?->order,
                            'is_anchor' => (bool) $img->is_anchor,
                        ];
                    })
                    ->values()
                    ->all();

                $rowMatches[$i] = [
                    'caption' => $caption,
                    'cluster_key' => $cluster->key,
                    'cluster_label' => $cluster->label,
                    'scores' => $scores['scores'],
                    'used_model_fallback' => $scores['used_model_fallback'],
                    'used_vision_fallback' => $scores['used_vision_fallback'] ?? false,
                    'top_score' => $scores['top_score'],
                    'second_score' => $scores['second_score'],
                    'cluster_image_ids' => $selection['images']->pluck('id')->values()->all(),
                    'selected_images' => $selectedImages,
                ];

                $historyId = $historyIds[$i] ?? null;
                if ($historyId) {
                    GenerationHistory::where('id', $historyId)->update([
                        'cluster_key' => $cluster->key,
                    ]);
                }

                Log::info('MatchCaptionsToClustersJob: row matched', [
                    'project_id' => $this->projectId,
                    'row_index' => $i,
                    'cluster_key' => $cluster->key,
                ]);
            }

            ClusterCsvPipeline::merge($project, [
                'phase' => 'matching_done',
                'row_matches' => $rowMatches,
            ]);

            Log::info('MatchCaptionsToClustersJob: completed', [
                'project_id' => $this->projectId,
                'row_count' => count($rowMatches),
            ]);
        } catch (\Throwable $e) {
            Log::error('MatchCaptionsToClustersJob: failed', [
                'project_id' => $this->projectId,
                'error' => $e->getMessage(),
            ]);
            ClusterCsvPipeline::markFailed($project, $e->getMessage());
            $session->markAsFailed('Caption matching failed: '.$e->getMessage());
            $project->generationHistory()
                ->whereIn('status', ['pending', 'processing'])
                ->update([
                    'status' => 'failed',
                    'error_message' => 'Pipeline aborted: caption matching failed.',
                ]);
            throw $e;
        }
    }
}
