<?php

namespace App\Jobs;

use App\Models\GenerationHistory;
use App\Models\Project;
use App\Services\AI\AIServiceManager;
use App\Services\FileUploadService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image as InterventionImage;

class GenerateSingleImageJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Project $project,
        public string $prompt,
        public string $format = 'square'
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(AIServiceManager $aiService, FileUploadService $fileUploadService): void
    {
        Log::info('Starting single image generation', [
            'project_id' => $this->project->id,
            'prompt' => $this->prompt,
            'format' => $this->format,
        ]);

        // Check if user has credits
        $user = $this->project->user;
        if (!$user->hasCredits()) {
            Log::warning('User has no credits remaining', [
                'user_id' => $user->id,
                'project_id' => $this->project->id,
            ]);
            
            GenerationHistory::create([
                'user_id' => $this->project->user_id,
                'project_id' => $this->project->id,
                'ai_model' => $aiService->getActiveServiceName(),
                'status' => 'failed',
                'error_message' => 'Insufficient credits',
            ]);
            
            return;
        }

        // Deduct credit
        $user->useCredit();

        // Create generation history record
        $generation = GenerationHistory::create([
            'user_id' => $this->project->user_id,
            'project_id' => $this->project->id,
            'ai_model' => $aiService->getActiveServiceName(),
            'status' => 'processing',
        ]);

        try {
            // Get brand reference image paths
            $referenceImagePaths = $this->project->brandReferences()
                ->pluck('url')
                ->map(fn($url) => Storage::disk('public')->path($url))
                ->toArray();

            // Get product images if any (from project settings or images with metadata type 'product_overlay')
            $productImagePaths = $this->project->images()
                ->whereJsonContains('metadata->type', 'product_overlay')
                ->pluck('url')
                ->map(fn($url) => Storage::disk('public')->path($url))
                ->toArray();

            // Generate image using AI service with Style Mirror approach
            $result = $aiService->generateWithReferences(
                $this->prompt,
                $referenceImagePaths,
                $productImagePaths,
                $this->format
            );

            // Save the generated image
            if (isset($result['image_data'])) {
                $imageData = base64_decode($result['image_data']);
                $extension = match($result['mime_type']) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    default => 'png'
                };

                $filename = 'generated_' . time() . '_' . uniqid() . '.' . $extension;
                $directory = 'projects/' . $this->project->id . '/images';
                $fullPath = $directory . '/' . $filename;

                // Save full size image
                Storage::disk('public')->put($fullPath, $imageData);

                // Generate thumbnail using Intervention Image
                try {
                    $interventionImage = InterventionImage::read($imageData);
                    $thumbnail = $interventionImage->cover(400, 400);
                    $thumbnailPath = $directory . '/thumbnails/' . $filename;
                    Storage::disk('public')->put($thumbnailPath, $thumbnail->toPng()->toString());
                } catch (\Exception $e) {
                    Log::warning('Failed to generate thumbnail', ['error' => $e->getMessage()]);
                    $thumbnailPath = $fullPath; // Use full image as fallback
                }

                // Create Image record
                $image = $this->project->images()->create([
                    'url' => $fullPath,
                    'thumbnail_url' => $thumbnailPath,
                    'prompt' => $this->prompt,
                    'order' => $this->project->images()->max('order') + 1,
                    'metadata' => array_merge($result['metadata'] ?? [], [
                        'ai_generated' => true,
                        'model' => $result['model'],
                        'format' => $this->format,
                    ]),
                ]);

                // Update generation history
                $generation->update([
                    'status' => 'completed',
                    'tokens_used' => $result['metadata']['tokens_used'] ?? 0,
                    'cost' => $this->calculateCost($result['metadata']['tokens_used'] ?? 0),
                ]);

                Log::info('Image generation completed', [
                    'project_id' => $this->project->id,
                    'image_id' => $image->id,
                    'generation_id' => $generation->id,
                    'size_kb' => strlen($imageData) / 1024,
                ]);
            } else {
                throw new \Exception('No image data in AI service response');
            }

        } catch (\Exception $e) {
            Log::error('Image generation failed', [
                'project_id' => $this->project->id,
                'error' => $e->getMessage(),
            ]);

            // Update generation history
            $generation->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Calculate cost based on token usage (approximate)
     */
    protected function calculateCost(int $tokens): float
    {
        // Gemini 2.0 Flash pricing (approximate): $0.00001 per token
        // Update these rates based on actual Gemini pricing
        return round(($tokens * 0.00001), 6);
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateSingleImageJob failed permanently', [
            'project_id' => $this->project->id,
            'prompt' => $this->prompt,
            'error' => $exception->getMessage(),
        ]);
    }
}
