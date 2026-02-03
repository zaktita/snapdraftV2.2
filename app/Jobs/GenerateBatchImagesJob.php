<?php

namespace App\Jobs;

use App\Models\CsvWizardSession;
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
     * Allowed per-row format presets (blank => AI decides).
     */
    private const FORMAT_PRESETS = [
        // Instagram
        'instagram_square',
        'instagram_story',
        'instagram_portrait',
        'instagram_landscape',

        // Facebook
        'facebook_square',
        'facebook_story',
        'facebook_landscape',
        'facebook_link',

        // LinkedIn
        'linkedin_square',
        'linkedin_landscape',

        // X (Twitter)
        'x_square',
        'x_landscape',

        // TikTok
        'tiktok_video',

        // YouTube
        'youtube_thumbnail',

        // Pinterest
        'pinterest_pin',
        'pinterest_square',
    ];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Project $project,
        public ?int $sessionId = null,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $batchStart = microtime(true);
        Log::info('🚀 STARTING GenerateBatchImagesJob', [
            'project_id' => $this->project->id,
            'timestamp' => now()->toDateTimeString(),
            'session_id' => $this->sessionId,
        ]);

        $session = null;
        if ($this->sessionId) {
            $session = CsvWizardSession::query()->find($this->sessionId);
        }

        try {
            // Get CSV data from project settings
            $csvData = $this->project->settings['csv_data'] ?? [];
            $textAccurate = $this->project->settings['text_accurate'] ?? false;
            
            Log::info('📊 CSV data loaded', [
                'total_rows' => count($csvData),
                'text_accurate' => $textAccurate,
            ]);

            if (empty($csvData)) {
                Log::warning('No CSV data found for batch generation', ['project_id' => $this->project->id]);
                return;
            }

            // Create array of generation jobs with rate limiting
            $jobs = [];
            $scheduledIndex = 0;
            foreach ($csvData as $rowIndex => $row) {
                // Only require caption OR description (title is optional, AI will extract)
                $title = trim($row['title'] ?? '');
                $caption = trim($row['caption'] ?? '');
                $description = trim($row['description'] ?? '');

                $formatRaw = trim($row['format'] ?? '');
                $formatPreset = $formatRaw !== '' ? $formatRaw : null;

                if ($formatPreset !== null && !in_array($formatPreset, self::FORMAT_PRESETS, true)) {
                    $generation = GenerationHistory::create([
                        'user_id' => $this->project->user_id,
                        'project_id' => $this->project->id,
                        'prompt' => $this->buildPrompt($caption, $description),
                        'ai_model' => 'bytedance-seed/seedream-4.5',
                        'status' => 'failed',
                        'error_message' => 'Invalid format. Allowed: ' . implode(', ', self::FORMAT_PRESETS) . ' (or leave blank to let AI decide).',
                        'parameters' => [
                            'format' => $formatRaw,
                            'format_source' => $formatRaw === '' ? 'ai' : 'user',
                            'text_accurate' => $textAccurate,
                            'wizard_type' => 'csv',
                            'csv_row_index' => $rowIndex,
                            'caption' => $caption,
                            'title' => $title,
                            'description' => $description,
                            'validation_error' => true,
                        ],
                    ]);

                    Log::debug('Skipping row: invalid format preset', [
                        'row_index' => $rowIndex,
                        'format' => $formatRaw,
                        'generation_id' => $generation->id,
                    ]);
                    continue;
                }

                if (empty($caption) && empty($description)) {
                    $generation = GenerationHistory::create([
                        'user_id' => $this->project->user_id,
                        'project_id' => $this->project->id,
                        'prompt' => '',
                        'ai_model' => 'bytedance-seed/seedream-4.5',
                        'status' => 'failed',
                        'error_message' => 'Caption or description is required (at least one must be non-empty).',
                        'parameters' => [
                            'format' => $formatPreset,
                            'format_source' => $formatPreset ? 'user' : 'ai',
                            'text_accurate' => $textAccurate,
                            'wizard_type' => 'csv',
                            'csv_row_index' => $rowIndex,
                            'caption' => $caption,
                            'title' => $title,
                            'description' => $description,
                            'validation_error' => true,
                        ],
                    ]);

                    Log::debug('Skipping row: caption or description required', [
                        'row_index' => $rowIndex,
                        'generation_id' => $generation->id,
                    ]);
                    continue;
                }

                $prompt = $this->buildPrompt($caption, $description);
                // If format is blank, let the AI decide (we'll instruct the model in the prompt).
                $format = $formatPreset ?? '';

                // Determine AI model based on text accuracy flag
                $aiModel = 'bytedance-seed/seedream-4.5';

                // Create generation history record for this item
                $generation = GenerationHistory::create([
                    'user_id' => $this->project->user_id,
                    'project_id' => $this->project->id,
                    'prompt' => $prompt,
                    'ai_model' => $aiModel,
                    'status' => 'pending',
                    'parameters' => [
                        'format' => $formatPreset,
                        'format_source' => $formatPreset ? 'user' : 'ai',
                        'text_accurate' => $textAccurate,
                        'wizard_type' => 'csv',
                        'csv_row_index' => $rowIndex,
                        'caption' => $caption,
                        'title' => $title,
                        'description' => $description,
                    ],
                ]);

                // Create job with delay to prevent API rate limiting (2 seconds between each generation)
                $delaySeconds = $scheduledIndex * 2; // 2 seconds between each job
                $job = (new GenerateSingleImageJob(
                    project: $this->project, 
                    prompt: $prompt, 
                    format: $format, 
                    textAccurate: $textAccurate, 
                    generationId: $generation->id,
                    caption: $caption ?: null,
                    title: $title ?: null,
                    description: $description ?: null,
                    useSimplePrompt: true  // Enable simple prompt logic for all CSV rows
                ))->delay(now()->addSeconds($delaySeconds));

                $jobs[] = $job;
                $scheduledIndex++;
            }

            // Dispatch batch of jobs without callbacks (to avoid closure serialization issues)
            $dispatchStart = microtime(true);

            $batch = Bus::batch($jobs)
                ->name('Batch Generation - Project ' . $this->project->id)
                ->dispatch();

            if ($session) {
                $session->markAsGenerating(batchId: $batch->id, totalJobs: count($jobs));
            }
            
            $dispatchTime = round((microtime(true) - $dispatchStart) * 1000);
            $totalBatchTime = round((microtime(true) - $batchStart) * 1000);
            
            Log::info('✅ Batch jobs dispatched', [
                'dispatch_duration_ms' => $dispatchTime,
                'total_batch_setup_ms' => $totalBatchTime,
                'project_id' => $this->project->id,
                'job_count' => count($jobs),
                'batch_id' => $batch->id ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Batch generation setup failed', [
                'project_id' => $this->project->id,
                'error' => $e->getMessage(),
            ]);

            if ($session) {
                $session->markAsFailed($e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Build prompt from caption/description.
     * Caption takes priority over description if both exist.
     */
    protected function buildPrompt(?string $caption, ?string $description): string
    {
        // Use caption if available, otherwise use description
        $primaryPrompt = $caption ?: $description;
        return trim($primaryPrompt ?? '');
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
