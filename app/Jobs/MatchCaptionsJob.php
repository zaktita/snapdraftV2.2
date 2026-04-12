<?php

namespace App\Jobs;

use App\Models\CsvWizardSession;
use App\Models\Project;
use App\Services\AI\CaptionMatcherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MatchCaptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;
    public int $tries = 2;

    public function __construct(
        public readonly int $projectId,
        public readonly int $sessionId,
    ) {}

    public function handle(CaptionMatcherService $matcherService): void
    {
        $project = Project::findOrFail($this->projectId);
        $session = CsvWizardSession::findOrFail($this->sessionId);

        try {
            $clusterResult = $project->settings['cluster_result'] ?? null;

            if (!$clusterResult) {
                throw new \RuntimeException('cluster_result missing from project settings — AnalyzeBrandJob may not have completed.');
            }

            $csvData = $project->settings['csv_data'] ?? [];

            if (empty($csvData)) {
                throw new \RuntimeException('csv_data missing from project settings.');
            }

            Log::info('MatchCaptionsJob: matching captions', [
                'project_id' => $this->projectId,
                'row_count'  => count($csvData),
            ]);

            $promptBatch = $matcherService->match($clusterResult, $csvData);

            $project->update([
                'settings' => array_merge($project->settings ?? [], [
                    'prompt_batch' => $promptBatch,
                ]),
            ]);

            Log::info('MatchCaptionsJob: completed', [
                'project_id' => $this->projectId,
                'batch_size' => count($promptBatch),
            ]);
        } catch (\Throwable $e) {
            Log::error('MatchCaptionsJob: failed', [
                'project_id' => $this->projectId,
                'error'      => $e->getMessage(),
            ]);
            $session->markAsFailed('Caption matching failed: ' . $e->getMessage());
            $project->generationHistory()
                ->whereIn('status', ['pending', 'processing'])
                ->update([
                    'status'        => 'failed',
                    'error_message' => 'Pipeline aborted: caption matching failed.',
                ]);
            throw $e;
        }
    }
}
