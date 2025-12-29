<?php

namespace App\Jobs;

use App\Models\GenerationHistory;
use App\Models\Project;
use App\Services\AI\AIServiceManager;
use App\Services\FileUploadService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image as InterventionImage;

class GenerateSingleImageJob implements ShouldQueue
{
    use Batchable, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [60, 300, 900]; // 1 minute, 5 minutes, 15 minutes

    /**
     * Allowed image formats
     */
    private const ALLOWED_FORMATS = [
        '1:1', '2:3', '3:2', '3:4', '4:3', '4:5', '5:4', '2:1', '16:9', '21:9', '9:16', '4:1',
        'square', 'portrait', 'landscape',
        'instagram-post', 'instagram-story', 'facebook-post', 'facebook-ad',
        'linkedin-post', 'linkedin-banner', 'twitter-post', 'youtube-thumbnail'
    ];

    /**
     * Create a new job instance.
     */
    public bool $textAccurate = false;

    public function __construct(
        public Project $project,
        public string $prompt,
        public string $format = 'square',
        bool $textAccurate = false,
        public ?int $generationId = null
    ) {
        // Validate inputs
        $this->validateInputs();
        
        $this->textAccurate = (bool) $textAccurate;
    }

    /**
     * Validate job input parameters
     */
    protected function validateInputs(): void
    {
        if (empty($this->prompt)) {
            throw new \InvalidArgumentException('Prompt cannot be empty');
        }

        if (!in_array($this->format, self::ALLOWED_FORMATS)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid format "%s". Allowed formats: %s', 
                    $this->format, 
                    implode(', ', self::ALLOWED_FORMATS)
                )
            );
        }

        if (!$this->project->exists) {
            throw new \InvalidArgumentException('Project does not exist');
        }

        // Sanitize prompt
        $this->prompt = trim(strip_tags($this->prompt));
        if (strlen($this->prompt) > 5000) {
            throw new \InvalidArgumentException('Prompt too long (max 5000 characters)');
        }
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
            'text_accurate' => $this->textAccurate,
        ]);

        // Determine credit cost (1 for normal, 4 for text-accurate)
        $creditCost = $this->textAccurate ? 4 : 1;

        // Use database transaction for atomic operations
        DB::transaction(function () use ($aiService, $fileUploadService, $creditCost) {
            $this->executeGeneration($aiService, $fileUploadService, $creditCost);
        });
    }

    /**
     * Execute the generation process within a transaction
     */
    protected function executeGeneration(AIServiceManager $aiService, FileUploadService $fileUploadService, int $creditCost): void
    {
        // Lock user record for update to prevent race conditions
        $user = $this->project->user()->lockForUpdate()->first();
        if (!$user) {
            throw new \Exception("User not found for project {$this->project->id}");
        }

        // Check if user has sufficient credits
        if (!$user->hasCredits($creditCost)) {
            Log::warning('User has insufficient credits', [
                'user_id' => $user->id,
                'project_id' => $this->project->id,
                'required_credits' => $creditCost,
                'available_credits' => $user->credits_remaining,
            ]);

            $errorMessage = "Insufficient credits (need {$creditCost}, have {$user->credits_remaining})";

            if ($this->generationId && $generation = GenerationHistory::find($this->generationId)) {
                $generation->update([
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                    'ai_model' => $aiService->getActiveServiceName(),
                ]);
            } else {
                GenerationHistory::create([
                    'user_id' => $this->project->user_id,
                    'project_id' => $this->project->id,
                    'ai_model' => $aiService->getActiveServiceName(),
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                ]);
            }

            return;
        }

        // Deduct credits (1 or 4 depending on text accuracy)
        $user->useCredit($creditCost);

        // Get or create generation history record
        if ($this->generationId) {
            $generation = GenerationHistory::find($this->generationId);
            if ($generation) {
                $generation->update([
                    'status' => 'processing',
                    'ai_model' => $aiService->getActiveServiceName(),
                ]);
            } else {
                // Fallback if ID provided but not found
                $generation = GenerationHistory::create([
                    'user_id' => $this->project->user_id,
                    'project_id' => $this->project->id,
                    'ai_model' => $aiService->getActiveServiceName(),
                    'status' => 'processing',
                    'prompt' => $this->prompt,
                ]);
            }
        } else {
            // Legacy behavior: create new record
            $generation = GenerationHistory::create([
                'user_id' => $this->project->user_id,
                'project_id' => $this->project->id,
                'ai_model' => $aiService->getActiveServiceName(),
                'status' => 'processing',
                'prompt' => $this->prompt,
            ]);
        }

        $imageData = null;
        $fullPath = null;
        $thumbnailPath = null;

        try {
            // Get brand reference image paths
            $referenceImagePaths = $this->getValidImagePaths(
                $this->project->brandReferences()->pluck('url')->toArray()
            );

            // Get product images if any
            $productImagePaths = $this->getValidImagePaths(
                $this->project->images()
                    ->whereJsonContains('metadata->type', 'product_overlay')
                    ->pluck('url')
                    ->toArray()
            );

            Log::info('Reference images found', [
                'brand_references' => count($referenceImagePaths),
                'product_overlays' => count($productImagePaths),
            ]);

            // Generate image using AI service
            $result = $aiService->generateWithReferences(
                $this->prompt,
                $referenceImagePaths,
                $productImagePaths,
                $this->format,
                $this->textAccurate
            );

            // Validate AI service response
            if (!isset($result['image_data'])) {
                throw new \Exception('No image data in AI service response');
            }

            // Decode and validate image data
            $imageData = base64_decode($result['image_data']);
            if ($imageData === false) {
                throw new \Exception('Invalid base64 image data received from AI service');
            }

            if (!$this->isValidImageData($imageData)) {
                throw new \Exception('Invalid image data format received from AI service');
            }

            // Save generated image and create thumbnail
            [$fullPath, $thumbnailPath, $fileSize] = $this->saveGeneratedImage($imageData, $result['mime_type'] ?? 'image/png');

            // Create Image record
            $image = $this->project->images()->create([
                'url' => $fullPath,
                'thumbnail_url' => $thumbnailPath,
                'prompt' => $this->prompt,
                'order' => $this->project->images()->max('order') + 1,
                'metadata' => array_merge($result['metadata'] ?? [], [
                    'ai_generated' => true,
                    'model' => $result['metadata']['model'] ?? 'gemini-2.5-flash-image',
                    'format' => $this->format,
                    'text_accurate' => $this->textAccurate,
                    'file_size' => $fileSize,
                ]),
            ]);

            // Update generation history
            $generation->update([
                'status' => 'completed',
                'tokens_used' => $result['metadata']['tokens_used'] ?? 0,
                'cost' => $this->calculateCost($result['metadata']['tokens_used'] ?? 0),
                'image_id' => $image->id,
            ]);

            Log::info('Image generation completed', [
                'project_id' => $this->project->id,
                'image_id' => $image->id,
                'generation_id' => $generation->id,
                'size_kb' => strlen($imageData) / 1024,
                'credits_used' => $creditCost,
            ]);

        } catch (\Exception $e) {
            // Clean up any created files on failure
            $this->cleanupFiles([$fullPath, $thumbnailPath]);

            Log::error('Image generation failed', [
                'project_id' => $this->project->id,
                'generation_id' => $generation->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Refund credits on failure (except for validation errors)
            if (!($e instanceof \InvalidArgumentException)) {
                $user->refundCredit($creditCost);
                Log::info('Credits refunded due to generation failure', [
                    'user_id' => $user->id,
                    'credits_refunded' => $creditCost,
                ]);
            }

            // Update generation history
            if (isset($generation)) {
                $generation->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Save generated image and create thumbnail
     */
    protected function saveGeneratedImage(string $imageData, string $mimeType): array
    {
        $extension = $this->getFileExtension($mimeType);
        $filename = 'generated_' . time() . '_' . uniqid() . '.' . $extension;
        $directory = 'projects/' . $this->project->id . '/images';
        $thumbnailDirectory = $directory . '/thumbnails';

        Storage::disk('public')->makeDirectory($directory);
        Storage::disk('public')->makeDirectory($thumbnailDirectory);

        $fullPath = $directory . '/' . $filename;

        $fileSize = Storage::disk('public')->put($fullPath, $imageData);
        if (!$fileSize) {
            throw new \Exception('Failed to save image to storage');
        }

        $thumbnailPath = $this->generateThumbnail($imageData, $thumbnailDirectory, $filename);
        
        return [$fullPath, $thumbnailPath, $fileSize];
    }

    /**
     * Get valid image paths from URLs
     */
    protected function getValidImagePaths(array $urls): array
    {
        return collect($urls)
            ->map(function ($url) {
                // Extract filename from URL
                $filename = basename($url);
                
                // Check if file exists in storage
                if (Storage::disk('public')->exists($filename)) {
                    return Storage::disk('public')->path($filename);
                }
                
                // Also check with full URL path
                if (Storage::disk('public')->exists($url)) {
                    return Storage::disk('public')->path($url);
                }
                
                Log::warning('Reference image not found', ['url' => $url, 'filename' => $filename]);
                return null;
            })
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Validate image data
     */
    protected function isValidImageData(string $imageData): bool
    {
        // Check minimum size (at least 100 bytes)
        if (strlen($imageData) < 100) {
            return false;
        }

        // Try to get image info
        $imageInfo = @getimagesizefromstring($imageData);
        return $imageInfo !== false && in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP]);
    }

    /**
     * Get file extension from MIME type
     */
    protected function getFileExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'png'
        };
    }

    /**
     * Generate thumbnail with memory optimization
     */
    protected function generateThumbnail(string $imageData, string $thumbnailDirectory, string $filename): string
    {
        try {
            // Use temporary file to reduce memory usage for large images
            $tempPath = tempnam(sys_get_temp_dir(), 'img_');
            file_put_contents($tempPath, $imageData);

            $interventionImage = InterventionImage::read($tempPath);
            $thumbnail = $interventionImage->cover(400, 400);
            
            $thumbnailPath = $thumbnailDirectory . '/' . $filename;
            $thumbnailData = $thumbnail->toPng()->toString();
            
            Storage::disk('public')->put($thumbnailPath, $thumbnailData);
            
            // Clean up temporary file
            unlink($tempPath);
            
            return $thumbnailPath;
            
        } catch (\Exception $e) {
            Log::warning('Failed to generate thumbnail', [
                'error' => $e->getMessage(),
                'filename' => $filename,
            ]);
            
            // Return original path as fallback (don't use the same path)
            return ''; // Empty string indicates no thumbnail
        }
    }

    /**
     * Clean up files on failure
     */
    protected function cleanupFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if ($path && Storage::disk('public')->exists($path)) {
                try {
                    Storage::disk('public')->delete($path);
                    Log::info('Cleaned up file after failed generation', ['path' => $path]);
                } catch (\Exception $e) {
                    Log::warning('Failed to cleanup file', ['path' => $path, 'error' => $e->getMessage()]);
                }
            }
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
            'prompt' => substr($this->prompt, 0, 100), // Log only first 100 chars
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'batch_id' => $this->batchId ?? null,
        ]);

        // Update generation history if ID is available
        if ($this->generationId && $generation = GenerationHistory::find($this->generationId)) {
             $generation->markAsFailed($this->getUserFriendlyError($exception));
        }

        // Send email notification to project owner
        try {
            \Illuminate\Support\Facades\Mail::to($this->project->user->email)
                ->send(new \App\Mail\JobFailedNotification(
                    jobType: 'Single Image Generation',
                    projectName: $this->project->name ?? $this->project->title,
                    projectId: $this->project->id,
                    errorMessage: $this->getUserFriendlyError($exception),
                    attemptNumber: $this->attempts()
                ));
        } catch (\Exception $mailException) {
            Log::error('Failed to send job failure notification email', [
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
        if (str_contains($message, '429') || str_contains($message, 'rate limit')) {
            return 'Our AI service is experiencing high demand. Please try again in a few minutes.';
        }

        if (str_contains($message, '401') || str_contains($message, 'authentication')) {
            return 'There was an authentication issue with our AI service. Our team has been notified.';
        }

        if (str_contains($message, '500') || str_contains($message, 'server error')) {
            return 'Our AI service encountered a temporary error. Please try again later.';
        }

        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return 'The image generation took too long and timed out. Please try again with a simpler prompt.';
        }

        if (str_contains($message, 'credit') || str_contains($message, 'insufficient')) {
            return 'You have insufficient credits to complete this generation. Please upgrade your plan.';
        }

        if (str_contains($message, 'Invalid image data')) {
            return 'The AI service returned invalid image data. Please try again with a different prompt.';
        }

        if (str_contains($message, 'empty') || str_contains($message, 'required')) {
            return 'There was a problem with your request. Please check your input and try again.';
        }

        // Generic fallback
        return 'An unexpected error occurred during image generation. Our team has been notified and is investigating.';
    }
}