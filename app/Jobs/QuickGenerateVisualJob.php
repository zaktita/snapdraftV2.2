<?php

namespace App\Jobs;

use App\Models\BrandReference;
use App\Models\Image;
use App\Models\QuickGenerateSession;
use App\Services\AI\OpenRouterImageTester;
use App\Services\AI\PromptGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class QuickGenerateVisualJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 1; // No retries for now

    /**
     * Create a new job instance.
     */
    public function __construct(
        public QuickGenerateSession $session
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(
        PromptGeneratorService $promptGenerator,
        OpenRouterImageTester $imageTester
    ): void {
        try {
            Log::info('QuickGenerateVisualJob: Starting', [
                'session_id' => $this->session->id,
                'caption' => $this->session->caption,
            ]);

            // Step 1: Extract title/description and generate simple prompt
            $this->session->markAsAnalyzing();
            
            $promptResult = $promptGenerator->generateSimplePrompt(
                $this->session->brand_analysis_data,
                $this->session->caption
            );

            // Update session with extracted data
            $this->session->update([
                'extracted_title' => $promptResult['title'],
                'extracted_description' => $promptResult['description'],
                'selected_cluster_id' => $promptResult['cluster_id'],
                'selected_image_indices' => $promptResult['selected_images'],
                'final_prompt' => $promptResult['simple_prompt'],
            ]);

            Log::info('QuickGenerateVisualJob: Prompt generated', [
                'session_id' => $this->session->id,
                'title' => $promptResult['title'],
                'cluster_id' => $promptResult['cluster_id'],
            ]);

            // Step 2: Prepare reference images (selected ones)
            $this->session->markAsGenerating();
            
            $selectedIndices = $promptResult['selected_images'];
            $brandReferences = $this->session->project->brandReferences()
                ->orderBy('order')
                ->get();

            $referenceImagesBase64 = [];
            foreach ($selectedIndices as $index) {
                if (isset($brandReferences[$index])) {
                    $ref = $brandReferences[$index];
                    $path = str_replace('storage/', '', $ref->url);
                    
                    if (Storage::disk('public')->exists($path)) {
                        $mimeType = Storage::disk('public')->mimeType($path);
                        $content = Storage::disk('public')->get($path);
                        $base64 = base64_encode($content);
                        
                        $referenceImagesBase64[] = [
                            'data' => $base64,
                            'mimeType' => $mimeType,
                        ];
                    }
                }
            }

            if (empty($referenceImagesBase64)) {
                throw new \RuntimeException('No reference images could be loaded');
            }

            Log::info('QuickGenerateVisualJob: Generating image', [
                'session_id' => $this->session->id,
                'reference_count' => count($referenceImagesBase64),
            ]);

            // Step 3: Generate image
            $generationResults = $imageTester->generateForAllModels(
                $referenceImagesBase64,
                $promptResult['simple_prompt']
            );

            // Get first successful result
            $successfulResult = null;
            foreach ($generationResults as $model => $result) {
                if (!isset($result['error']) && isset($result['saved_path'])) {
                    $successfulResult = $result;
                    break;
                }
            }

            if (!$successfulResult) {
                throw new \RuntimeException('Image generation failed for all models');
            }

            // Step 4: Save to database as Image (using CSV wizard structure)
            $savedPath = $successfulResult['saved_path'];
            
            $image = Image::create([
                'project_id' => $this->session->project_id,
                'url' => $savedPath, // Path relative to storage/app/public
                'thumbnail_url' => $savedPath, // Same for now (CSV wizard creates thumbnails separately)
                'prompt' => $promptResult['simple_prompt'],
                'metadata' => [
                    'ai_generated' => true,
                    'format' => $this->session->format,
                    'title' => $promptResult['title'],
                    'description' => $promptResult['description'],
                    'cluster_id' => $promptResult['cluster_id'],
                    'selected_images' => $promptResult['selected_images'],
                    'generation_duration_ms' => $successfulResult['duration_ms'] ?? 0,
                    'model' => $successfulResult['model'] ?? 'unknown',
                ],
                'order' => 0,
            ]);

            // Update project featured image and count
            $this->session->project->update([
                'featured_image' => $savedPath,
                'images_count' => $this->session->project->images()->count(),
            ]);

            $this->session->markAsCompleted();

            Log::info('QuickGenerateVisualJob: Completed', [
                'session_id' => $this->session->id,
                'image_id' => $image->id,
            ]);

        } catch (\Throwable $e) {
            Log::error('QuickGenerateVisualJob: Failed', [
                'session_id' => $this->session->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->session->markAsFailed($e->getMessage());
            throw $e;
        }
    }
}
