<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\AI\AIServiceManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AnalyzeBrandStyleJob implements ShouldQueue
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
    public function handle(AIServiceManager $aiService): void
    {
        Log::info('Starting brand style analysis for project', ['project_id' => $this->project->id]);

        try {
            // Get brand reference image URLs
            $brandReferences = $this->project->brandReferences;

            if ($brandReferences->isEmpty()) {
                Log::warning('No brand references found for project', ['project_id' => $this->project->id]);
                return;
            }

            // Convert storage paths to full URLs or file paths
            $imageUrls = $brandReferences->map(function ($reference) {
                return Storage::disk('public')->path($reference->url);
            })->toArray();

            // Call AI service to analyze brand style
            $styleAnalysis = $aiService->analyzeBrandStyle($imageUrls);

            // Store analysis results in each brand reference
            foreach ($brandReferences as $index => $reference) {
                $reference->update([
                    'analysis_data' => $styleAnalysis,
                ]);
            }

            // Also store in project settings for quick access
            $this->project->update([
                'settings' => array_merge($this->project->settings ?? [], [
                    'brand_style' => $styleAnalysis,
                    'brand_analyzed_at' => now()->toIso8601String(),
                ]),
            ]);

            Log::info('Brand style analysis completed', [
                'project_id' => $this->project->id,
                'style' => $styleAnalysis,
            ]);

            // Dispatch next job based on wizard type
            $wizardType = $this->project->settings['wizard_type'] ?? null;
            
            if ($wizardType === 'csv') {
                // CSV wizard: generate batch of images
                GenerateBatchImagesJob::dispatch($this->project);
            } else {
                // Images or Text wizard: generate single image
                GenerateSingleImageJob::dispatch($this->project);
            }

        } catch (\Exception $e) {
            Log::error('Brand style analysis failed', [
                'project_id' => $this->project->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzeBrandStyleJob failed permanently', [
            'project_id' => $this->project->id,
            'error' => $exception->getMessage(),
        ]);

        // Update project to indicate failure
        $this->project->update([
            'settings' => array_merge($this->project->settings ?? [], [
                'brand_analysis_failed' => true,
                'brand_analysis_error' => $exception->getMessage(),
            ]),
        ]);
    }
}
