<?php

namespace App\Jobs;

use App\Models\Project;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class GenerateBatchImagesJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Project $project
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting batch image generation', ['project_id' => $this->project->id]);

        try {
            // Get CSV data from project settings
            $csvData = $this->project->settings['csv_data'] ?? [];

            if (empty($csvData)) {
                Log::warning('No CSV data found for batch generation', ['project_id' => $this->project->id]);
                return;
            }

            // Create array of generation jobs with rate limiting
            $jobs = [];
            foreach ($csvData as $index => $row) {
                $prompt = $this->buildPrompt($row);
                $format = $row['format'] ?? 'square';

                // Add delay between jobs (2 seconds per image for rate limiting)
                $job = (new GenerateSingleImageJob($this->project, $prompt, $format))
                    ->delay(now()->addSeconds($index * 2));

                $jobs[] = $job;
            }

            // Dispatch batch of jobs without callbacks (to avoid closure serialization issues)
            Bus::batch($jobs)
                ->name('Batch Generation - Project ' . $this->project->id)
                ->dispatch();

            Log::info('Batch jobs dispatched', [
                'project_id' => $this->project->id,
                'job_count' => count($jobs),
            ]);

        } catch (\Exception $e) {
            Log::error('Batch generation setup failed', [
                'project_id' => $this->project->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Build prompt from CSV row data.
     */
    protected function buildPrompt(array $row): string
    {
        $title = $row['title'] ?? '';
        $description = $row['description'] ?? '';

        return trim("{$title}. {$description}");
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateBatchImagesJob failed permanently', [
            'project_id' => $this->project->id,
            'error' => $exception->getMessage(),
        ]);

        $this->project->update([
            'settings' => array_merge($this->project->settings ?? [], [
                'batch_failed' => true,
                'batch_error' => $exception->getMessage(),
            ]),
        ]);
    }
}
