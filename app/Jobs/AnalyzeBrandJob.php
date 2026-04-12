<?php

namespace App\Jobs;

use App\Models\CsvWizardSession;
use App\Models\Project;
use App\Services\AI\ClusteringService;
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

    public function handle(ClusteringService $clusteringService): void
    {
        $project = Project::findOrFail($this->projectId);
        $session = CsvWizardSession::findOrFail($this->sessionId);

        try {
            $session->update(['status' => 'analyzing']);

            $refPaths = $project->settings['ref_paths'] ?? [];

            if (empty($refPaths)) {
                throw new \RuntimeException('No reference image paths found in project settings.');
            }

            Log::info('AnalyzeBrandJob: clustering reference images', [
                'project_id' => $this->projectId,
                'ref_count'  => count($refPaths),
            ]);

            $clusterResult = $clusteringService->cluster($refPaths);

            $project->update([
                'settings' => array_merge($project->settings ?? [], [
                    'cluster_result' => $clusterResult,
                ]),
            ]);

            Log::info('AnalyzeBrandJob: completed', [
                'project_id'    => $this->projectId,
                'cluster_count' => count($clusterResult['clusters']),
            ]);
        } catch (\Throwable $e) {
            Log::error('AnalyzeBrandJob: failed', [
                'project_id' => $this->projectId,
                'error'      => $e->getMessage(),
            ]);
            $session->markAsFailed('Brand analysis failed: ' . $e->getMessage());
            // Mark all pending GenerationHistory records as failed so the
            // frontend detects the pipeline died and stops polling.
            $project->generationHistory()
                ->whereIn('status', ['pending', 'processing'])
                ->update([
                    'status'        => 'failed',
                    'error_message' => 'Pipeline aborted: brand analysis failed.',
                ]);
            throw $e;
        }
    }
}