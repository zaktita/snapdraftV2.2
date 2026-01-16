<?php

namespace App\Jobs;

use App\Models\GenerationHistory;
use App\Models\Image;
use App\Models\Project;
use App\Services\AI\OpenRouterImageTester;
use App\Services\AI\PromptGeneratorService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateSingleImageJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;

    public function __construct(
        public Project $project,
        public string $prompt = '',
        public string $format = '1:1',
        public bool $textAccurate = false,
        public ?int $generationId = null,
        public ?string $caption = null,
        public ?string $title = null,
        public ?string $description = null,
        public bool $useSimplePrompt = false,
    ) {
    }

    public function handle(
        PromptGeneratorService $promptGenerator,
        OpenRouterImageTester $imageTester
    ): void {
        $generation = null;
        try {
            if ($this->batch()?->cancelled()) {
                Log::info('GenerateSingleImageJob: Skipped (batch cancelled)', [
                    'project_id' => $this->project->id,
                    'generation_id' => $this->generationId,
                ]);
                return;
            }

            Log::info('GenerateSingleImageJob: Starting', [
                'project_id' => $this->project->id,
                'title' => $this->title,
                'caption' => $this->caption,
                'format' => $this->format,
                'generation_id' => $this->generationId,
                'text_accurate' => $this->textAccurate,
                'use_simple_prompt' => $this->useSimplePrompt,
            ]);

            if ($this->generationId) {
                $generation = GenerationHistory::query()->find($this->generationId);
                if ($generation) {
                    $generation->update(['status' => 'processing']);
                }
            }

            // Prefer explicit caption/description; fall back to prompt
            $primaryText = trim((string) ($this->caption ?: $this->description ?: $this->prompt));
            if ($primaryText === '') {
                throw new \RuntimeException('Prompt cannot be empty');
            }

            $finalPrompt = $primaryText;
            $promptResult = null;

            $brandAnalysis = $this->project->settings['brand_analysis'] ?? null;
            if ($this->useSimplePrompt && $brandAnalysis) {
                $promptResult = $promptGenerator->generateSimplePrompt($brandAnalysis, $primaryText);
                $finalPrompt = (string) ($promptResult['simple_prompt'] ?? $primaryText);

                // Backfill extracted fields if not explicitly provided
                $this->title = $this->title ?: ($promptResult['title'] ?? null);
                $this->description = $this->description ?: ($promptResult['description'] ?? null);
            }

            Log::info('GenerateSingleImageJob: Prompt prepared', [
                'project_id' => $this->project->id,
                'prompt' => $finalPrompt,
            ]);

            $formatInfo = $this->resolveFormat($this->format);
            $finalPromptWithFormat = $this->appendFormatInstruction($finalPrompt, $formatInfo);

            // Prepare reference images (selected cluster when available)
            $brandReferences = $this->project->brandReferences()->orderBy('order')->get();
            $referenceImagesBase64 = [];
            $referenceCluster = [];

            $selectedIndices = null;
            if (is_array($promptResult) && isset($promptResult['selected_images']) && is_array($promptResult['selected_images'])) {
                $selectedIndices = array_values(array_filter($promptResult['selected_images'], fn ($v) => is_int($v) || ctype_digit((string) $v)));
                $selectedIndices = array_map('intval', $selectedIndices);
            }

            $indicesToUse = $selectedIndices;
            if (!$indicesToUse || count($indicesToUse) === 0) {
                $indicesToUse = array_keys($brandReferences->all());
            }
            
            foreach ($indicesToUse as $index) {
                if (!isset($brandReferences[$index])) {
                    continue;
                }

                $ref = $brandReferences[$index];
                $path = str_replace('storage/', '', (string) $ref->url);
                
                if (Storage::disk('public')->exists($path)) {
                    $mimeType = Storage::disk('public')->mimeType($path);
                    $content = Storage::disk('public')->get($path);
                    $base64 = base64_encode($content);
                    
                    $referenceImagesBase64[] = [
                        'data' => $base64,
                        'mimeType' => $mimeType,
                    ];

                    $referenceCluster[] = [
                        'id' => $ref->id,
                        'url' => $ref->url,
                        'thumbnail_url' => $ref->thumbnail_url,
                        'order' => $ref->order,
                        'index' => $index,
                    ];
                }
            }

            if (empty($referenceImagesBase64)) {
                throw new \RuntimeException('No reference images could be loaded');
            }

            Log::info('GenerateSingleImageJob: Generating image', [
                'project_id' => $this->project->id,
                'reference_count' => count($referenceImagesBase64),
                'format_token' => $formatInfo['token'],
                'format_ratio' => $formatInfo['ratio'],
                'format_source' => $formatInfo['source'],
            ]);

            if ($generation) {
                $generation->update([
                    'prompt' => $finalPromptWithFormat,
                    'request_data' => array_merge($generation->request_data ?? [], [
                        'prompt_sent' => $finalPromptWithFormat,
                        'cluster_id' => $promptResult['cluster_id'] ?? null,
                        'selected_image_indices' => $selectedIndices,
                        'reference_cluster' => $referenceCluster,
                        'reference_count' => count($referenceCluster),
                    ]),
                ]);
            }

            // Generate image
            $generationResults = $imageTester->generateForAllModels(
                $referenceImagesBase64,
                $finalPromptWithFormat
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

            // Save to database
            $savedPath = $successfulResult['saved_path'];
            
            $image = Image::create([
                'project_id' => $this->project->id,
                'url' => $savedPath,
                'thumbnail_url' => $savedPath,
                'prompt' => $finalPromptWithFormat,
                'metadata' => [
                    'ai_generated' => true,
                    'format' => $formatInfo['token'],
                    'format_ratio' => $formatInfo['ratio'],
                    'format_source' => $formatInfo['source'],
                    'title' => $this->title,
                    'caption' => $this->caption,
                    'description' => $this->description,
                    'text_accurate' => $this->textAccurate,
                    'cluster_id' => $promptResult['cluster_id'] ?? null,
                    'selected_images' => $promptResult['selected_images'] ?? null,
                    'reference_cluster' => $referenceCluster,
                    'generation_duration_ms' => $successfulResult['duration_ms'] ?? 0,
                    'model' => $successfulResult['model'] ?? 'bytedance-seed/seedream-4.5',
                ],
                'order' => 0,
            ]);

            // Update project
            $this->project->update([
                'featured_image' => $savedPath,
                'images_count' => $this->project->images()->count(),
            ]);

            Log::info('GenerateSingleImageJob: Completed', [
                'project_id' => $this->project->id,
                'image_id' => $image->id,
            ]);

            if ($generation) {
                $generation->update([
                    'image_id' => $image->id,
                    'ai_model' => $successfulResult['model'] ?? ($generation->ai_model ?? 'bytedance-seed/seedream-4.5'),
                    'status' => 'completed',
                    'response_data' => [
                        'saved_path' => $savedPath,
                        'duration_ms' => $successfulResult['duration_ms'] ?? null,
                        'model' => $successfulResult['model'] ?? null,
                        'format_token' => $formatInfo['token'],
                        'format_ratio' => $formatInfo['ratio'],
                        'format_source' => $formatInfo['source'],
                    ],
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('GenerateSingleImageJob: Failed', [
                'project_id' => $this->project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($generation) {
                $generation->markAsFailed($e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Resolve a CSV format preset into a normalized token + ratio.
     * Blank format means "AI decides".
     *
     * @return array{token:string,ratio:?string,source:'user'|'ai'}
     */
    private function resolveFormat(?string $format): array
    {
        $format = trim((string) $format);
        if ($format === '') {
            return [
                'token' => 'auto',
                'ratio' => null,
                'source' => 'ai',
            ];
        }

        $map = [
            // Instagram
            'instagram_square' => '1:1',
            'instagram_story' => '9:16',
            'instagram_portrait' => '4:5',
            'instagram_landscape' => '16:9',

            // Facebook
            'facebook_square' => '1:1',
            'facebook_story' => '9:16',
            'facebook_landscape' => '16:9',
            'facebook_link' => '1.91:1',

            // LinkedIn
            'linkedin_square' => '1:1',
            'linkedin_landscape' => '1.91:1',

            // X (Twitter)
            'x_square' => '1:1',
            'x_landscape' => '16:9',

            // TikTok
            'tiktok_video' => '9:16',

            // YouTube
            'youtube_thumbnail' => '16:9',

            // Pinterest
            'pinterest_pin' => '2:3',
            'pinterest_square' => '1:1',

            // Generic aspect presets
            'square_1_1' => '1:1',
            'portrait_4_5' => '4:5',
            'portrait_3_4' => '3:4',
            'portrait_2_3' => '2:3',
            'story_9_16' => '9:16',
            'landscape_3_2' => '3:2',
            'landscape_4_3' => '4:3',
            'landscape_5_4' => '5:4',
            'wide_2_1' => '2:1',
            'landscape_16_9' => '16:9',
            'cinematic_21_9' => '21:9',
            'banner_4_1' => '4:1',
        ];

        if (isset($map[$format])) {
            return [
                'token' => $format,
                'ratio' => $map[$format],
                'source' => 'user',
            ];
        }

        // Fallback: treat it as a ratio-like string (legacy support)
        return [
            'token' => $format,
            'ratio' => $format,
            'source' => 'user',
        ];
    }

    /**
     * Add format guidance to the prompt so the model composes correctly.
     */
    private function appendFormatInstruction(string $prompt, array $formatInfo): string
    {
        $prompt = trim($prompt);
        $instruction = null;

        if (($formatInfo['token'] ?? '') === 'auto') {
            $instruction = 'Choose the best social format based on the content (Instagram Square 1:1, Portrait 4:5, Story 9:16, or Landscape 16:9).';
        } elseif (!empty($formatInfo['ratio'])) {
            $instruction = "Compose for {$formatInfo['token']} in {$formatInfo['ratio']} aspect ratio.";
        } else {
            $instruction = "Compose for {$formatInfo['token']} format.";
        }

        return $prompt . "\n\nOUTPUT FORMAT:\n- {$instruction}\n";
    }
}