<?php

namespace App\Jobs;

use App\Models\BrandReference;
use App\Models\Image;
use App\Models\QuickGenerateSession;
use App\Services\AI\AIServiceManager;
use App\Services\AI\PromptGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        AIServiceManager $aiService
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

            $referenceImagePaths = [];
            foreach ($selectedIndices as $index) {
                if (isset($brandReferences[$index])) {
                    $ref = $brandReferences[$index];
                    $path = str_replace('storage/', '', $ref->url);
                    
                    if (Storage::disk('public')->exists($path)) {
                        $referenceImagePaths[] = $path;
                    }
                }
            }

            if (empty($referenceImagePaths)) {
                throw new \RuntimeException('No reference images could be loaded');
            }

            Log::info('QuickGenerateVisualJob: Generating image', [
                'session_id' => $this->session->id,
                'reference_count' => count($referenceImagePaths),
                'service' => $aiService->getServiceName(),
            ]);

            $startTime = microtime(true);

            // Step 3: Generate image
            $result = $aiService->generateWithReferences(
                $promptResult['simple_prompt'],
                $referenceImagePaths,
                [], 
                $this->session->format ?? 'square'
            );

            $durationMs = (int)((microtime(true) - $startTime) * 1000);

            // Step 4: Save to database as Image (using CSV wizard structure)
            $imageData = base64_decode($result['image_base64']);
            $randomName = Str::random(40);
            $filename = 'generated/' . $randomName . '.png';
            Storage::disk('public')->put($filename, $imageData);
            
            $savedPath = 'storage/' . $filename;
            
            // Create a thumbnail as well (for now just copy, optimizing later)
            // Ideally we should resize this, but for MVP strict replacement:
            $thumbnailFilename = 'generated/thumbs/' . $randomName . '.png';
            Storage::disk('public')->put($thumbnailFilename, $imageData);
            $thumbnailPath = 'storage/' . $thumbnailFilename;

            $image = Image::create([
                'project_id' => $this->session->project_id,
                'url' => $savedPath,
                'thumbnail_url' => $thumbnailPath,
                'prompt' => $promptResult['simple_prompt'],
                'metadata' => [
                    'ai_generated' => true,
                    'format' => $this->session->format,
                    'title' => $promptResult['title'],
                    'description' => $promptResult['description'],
                    'cluster_id' => $promptResult['cluster_id'],
                    'selected_images' => $promptResult['selected_images'],
                    'generation_duration_ms' => $durationMs,
                    'model' => $aiService->getServiceName(),
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
