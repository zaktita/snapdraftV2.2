<?php

namespace App\Jobs;

use App\Models\GenerationHistory;
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
            $textAccurate = $this->project->settings['text_accurate'] ?? false;

            if (empty($csvData)) {
                Log::warning('No CSV data found for batch generation', ['project_id' => $this->project->id]);
                return;
            }

            // Create array of generation jobs with rate limiting
            $jobs = [];
            $jobIndex = 0;
            foreach ($csvData as $row) {
                // Skip rows with empty title AND description
                $title = trim($row['title'] ?? '');
                $description = trim($row['description'] ?? '');

                if (empty($title) && empty($description)) {
                    Log::debug('Skipping empty CSV row', ['row' => $row]);
                    continue;
                }

                $prompt = $this->buildPrompt($row);
                $format = $row['format'] ?? 'square';

                // Determine AI model based on text accuracy flag
                $aiModel = $textAccurate ? 'gemini-3-pro-image-preview' : 'gemini-2.5-flash-image';

                // Create generation history record for this item
                $generation = GenerationHistory::create([
                    'user_id' => $this->project->user_id,
                    'project_id' => $this->project->id,
                    'prompt' => $prompt,
                    'ai_model' => $aiModel,
                    'status' => 'pending',
                    'parameters' => [
                        'format' => $format,
                        'text_accurate' => $textAccurate,
                        'wizard_type' => 'csv',
                        'csv_row_index' => $jobIndex,
                    ],
                ]);

                // Create job with delay to prevent API rate limiting (2 seconds between each generation)
                $delaySeconds = $jobIndex * 2; // 2 seconds between each job
                $job = (new GenerateSingleImageJob($this->project, $prompt, $format, $textAccurate, $generation->id))
                    ->delay(now()->addSeconds($delaySeconds));

                $jobs[] = $job;
                $jobIndex++;
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

        // Send email notification to project owner
        try {
            \Illuminate\Support\Facades\Mail::to($this->project->user->email)
                ->send(new \App\Mail\JobFailedNotification(
                    jobType: 'Batch Image Generation',
                    projectName: $this->project->name ?? $this->project->title,
                    projectId: $this->project->id,
                    errorMessage: $this->getUserFriendlyError($exception),
                    attemptNumber: 1 // Batch jobs don't retry by default
                ));
        } catch (\Exception $mailException) {
            Log::error('Failed to send batch job failure notification email', [
                'project_id' => $this->project->id,
                'mail_error' => $mailException->getMessage(),
            ]);
        }
    }

    /**
     * Convert technical error to user-friendly message
     */
    protected function getUserFriendlyError(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        // Map common errors to user-friendly messages
        if (str_contains($message, 'CSV') || str_contains($message, 'data')) {
            return 'There was an issue processing your CSV data. Please check the file format and try again.';
        }

        if (str_contains($message, 'reference') || str_contains($message, 'brand')) {
            return 'There was an issue with your brand reference images. Please re-upload them and try again.';
        }

        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return 'The batch processing took too long and timed out. Try processing fewer items at once.';
        }

        // Generic fallback
        return 'An unexpected error occurred while setting up your batch generation. Our team has been notified.';
    }
}
