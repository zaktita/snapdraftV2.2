<?php

namespace App\Jobs;

use App\Models\CsvWizardSession;
use App\Models\Project;
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

            if (empty($promptBatch)) {
                throw new \RuntimeException('prompt_batch missing from project settings — MatchCaptionsJob may not have completed.');
            }

            $jobs = array_map(
                fn(array $item) => new GenerateSingleImageJob($this->projectId, $this->sessionId, $item),
                $promptBatch
            );

            $sessionId = $this->sessionId;

            $batch = Bus::batch($jobs)
                ->allowFailures()
                ->catch(function (\Illuminate\Bus\Batch $batch, \Throwable $e) use ($sessionId) {
                    Log::warning('DispatchGenerationBatchJob: a job in batch failed', [
                        'session_id'  => $sessionId,
                        'batch_id'    => $batch->id,
                        'failed_jobs' => $batch->failedJobs,
                        'error'       => $e->getMessage(),
                    ]);
                })
                ->finally(function () use ($sessionId) {
                    $s = CsvWizardSession::find($sessionId);
                    $s?->markAsCompleted();
                })
                ->dispatch();

            $session->markAsGenerating($batch->id, count($jobs));

            Log::info('DispatchGenerationBatchJob: batch dispatched', [
                'project_id' => $this->projectId,
                'batch_id'   => $batch->id,
                'job_count'  => count($jobs),
            ]);
        } catch (\Throwable $e) {
            Log::error('DispatchGenerationBatchJob: failed', [
                'project_id' => $this->projectId,
                'error'      => $e->getMessage(),
            ]);
            $session->markAsFailed('Generation dispatch failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
