<?php

namespace App\Jobs;

use App\Models\CsvWizardSession;
use App\Models\Project;
use App\Services\Brand\ClusteringBrandAnalyzer;
use App\Services\Wizards\ClusterCsvPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeBrandJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(
        public readonly int $projectId,
        public readonly int $sessionId,
    ) {}

    public function handle(ClusteringBrandAnalyzer $analyzer): void
    {
        $project = Project::findOrFail($this->projectId);
        $session = CsvWizardSession::findOrFail($this->sessionId);

        try {
            $session->update(['status' => 'analyzing']);

            if (ClusterCsvPipeline::isClusterCsvWizard($project)) {
                ClusterCsvPipeline::setPhase($project, 'clustering');
            }

            $refPaths = $project->settings['ref_paths'] ?? [];

            if (empty($refPaths)) {
                throw new \RuntimeException('No reference image paths found in project settings.');
            }

            Log::info('AnalyzeBrandJob: clustering brand references', [
                'project_id' => $this->projectId,
                'ref_count' => count($refPaths),
            ]);

            $analyzer->analyze($project, $refPaths);

            $project->refresh();
            $clusterCount = $project->clusters()->count();

            Log::info('AnalyzeBrandJob: completed', [
                'project_id' => $this->projectId,
                'cluster_count' => $clusterCount,
            ]);

            if (ClusterCsvPipeline::isClusterCsvWizard($project)) {
                ClusterCsvPipeline::setPhase($project->refresh(), 'clustering_done');
            }
        } catch (\Throwable $e) {
            Log::error('AnalyzeBrandJob: failed', [
                'project_id' => $this->projectId,
                'error' => $e->getMessage(),
            ]);
            if (ClusterCsvPipeline::isClusterCsvWizard($project)) {
                ClusterCsvPipeline::markFailed($project, $e->getMessage());
            }
            $session->markAsFailed('Brand analysis failed: '.$e->getMessage());
            $project->generationHistory()
                ->whereIn('status', ['pending', 'processing'])
                ->update([
                    'status' => 'failed',
                    'error_message' => 'Pipeline aborted: brand analysis failed.',
                ]);
            throw $e;
        }
    }
}
