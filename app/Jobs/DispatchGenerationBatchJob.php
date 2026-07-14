<?php

namespace App\Jobs;

use App\Models\CsvWizardSession;
use App\Models\Project;
use App\Services\Wizards\ClusterCsvPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class DispatchGenerationBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 1;

    public function __construct(
        public readonly int $projectId,
        public readonly int $sessionId,
    ) {}

    public function handle(): void
    {
        $project = Project::findOrFail($this->projectId);
        $session = CsvWizardSession::findOrFail($this->sessionId);

        try {
            $promptBatch = $project->settings['prompt_batch'] ?? [];
            $resolutionMultiplier = (int) data_get($project->settings, 'resolution_multiplier', 1);

            if (empty($promptBatch)) {
                $this->failPendingHistories(
                    $project,
                    'Pipeline aborted: no generation prompts were produced (empty prompt batch).'
                );
                $message = 'prompt_batch missing from project settings — GeneratePostPromptsJob may not have completed.';
                $session->markAsFailed($message);
                throw new \RuntimeException($message);
            }

            $jobs = array_map(
                fn (array $item) => new GenerateSingleImageJob($this->projectId, $this->sessionId, $item),
                $promptBatch
            );

            $sessionId = $this->sessionId;
            $projectId = $this->projectId;

            if (ClusterCsvPipeline::isClusterCsvWizard($project)) {
                ClusterCsvPipeline::setPhase($project, 'images');
            }

            $session->markAsGenerating(null, count($jobs));

            $batch = Bus::batch($jobs)
                ->allowFailures()
                ->catch(function (\Illuminate\Bus\Batch $batch, \Throwable $e) use ($sessionId) {
                    Log::warning('DispatchGenerationBatchJob: a job in batch failed', [
                        'session_id' => $sessionId,
                        'batch_id' => $batch->id,
                        'failed_jobs' => $batch->failedJobs,
                        'error' => $e->getMessage(),
                    ]);
                })
                ->finally(function () use ($sessionId, $projectId) {
                    $s = CsvWizardSession::find($sessionId);
                    $s?->markAsCompleted();

                    $project = Project::find($projectId);
                    if ($project && ClusterCsvPipeline::isClusterCsvWizard($project)) {
                        ClusterCsvPipeline::setPhase($project, 'complete');
                    }
                })
                ->dispatch();

            // Store batch id only — do not reset status to "generating" after dispatch.
            // With QUEUE_CONNECTION=sync the batch (and finally callback) already finished.
            $session->update(['batch_id' => $batch->id]);

            Log::info('DispatchGenerationBatchJob: batch dispatched', [
                'project_id' => $this->projectId,
                'batch_id' => $batch->id,
                'job_count' => count($jobs),
                'resolution_multiplier' => $resolutionMultiplier,
            ]);
        } catch (\Throwable $e) {
            Log::error('DispatchGenerationBatchJob: failed', [
                'project_id' => $this->projectId,
                'error' => $e->getMessage(),
            ]);
            $this->failPendingHistories(
                $project,
                'Pipeline aborted: image batch could not be started.'
            );
            $session->markAsFailed('Generation dispatch failed: '.$e->getMessage());
            if (ClusterCsvPipeline::isClusterCsvWizard($project)) {
                ClusterCsvPipeline::markFailed($project, $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Ensure the project page stops showing an infinite "generating" state when
     * this job fails before per-image jobs update each history row.
     */
    private function failPendingHistories(Project $project, string $errorMessage): void
    {
        $project->generationHistory()
            ->whereIn('status', ['pending', 'processing'])
            ->update([
                'status' => 'failed',
                'error_message' => $errorMessage,
            ]);
    }
}
