<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Models\BrandReference;
use App\Models\Image;
use App\Models\Project;
use App\Models\ProjectCluster;
use App\Services\AI\CsvImageGenerationService;
use App\Services\Brand\ClusteringBrandAnalyzer;
use App\Services\Brand\CsvPostPromptBuilder;
use App\Services\Brand\ProjectClusterSelector;
use App\Services\FileUploadService;
use App\Services\FormatPresetMapper;
use App\Services\Prompt\JsonPromptCompiler;
use App\Services\Prompt\SkillPromptBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ClusteringTestController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUploadService,
        protected ClusteringBrandAnalyzer $analyzer,
        protected ProjectClusterSelector $clusterSelector,
        protected CsvPostPromptBuilder $postPromptBuilder,
        protected SkillPromptBuilder $skillPromptBuilder,
        protected JsonPromptCompiler $promptCompiler,
        protected CsvImageGenerationService $imageGenerator,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('create', Project::class);

        $project = null;
        if ($request->filled('project')) {
            $project = Project::with([
                'brandReferences',
                'clusters.images.brandReference',
            ])->find($request->integer('project'));

            if ($project) {
                Gate::authorize('view', $project);
            }
        }

        return Inertia::render('test/clustering', $this->pagePayload($project));
    }

    public function analyze(Request $request)
    {
        set_time_limit(0);
        Gate::authorize('create', Project::class);

        $validated = $request->validate([
            'brand_name' => 'nullable|string|min:2|max:120',
            'reference_images' => 'required|array|min:3|max:10',
            'reference_images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
        ]);

        $brandName = $validated['brand_name'] ?? 'Clustering Test Brand';

        try {
            $project = Project::create([
                'user_id' => auth()->id(),
                'name' => $brandName,
                'title' => $brandName,
                'description' => 'Clustering test lab',
                'format' => '1:1',
                'status' => 'processing',
                'settings' => [
                    'type' => 'clustering_test_lab',
                ],
            ]);

            $refPaths = [];
            $order = 0;

            foreach ($request->file('reference_images') as $file) {
                $uploaded = $this->fileUploadService->uploadImage(
                    $file,
                    "projects/{$project->id}/references"
                );

                $refPaths[] = $uploaded['url'];

                BrandReference::create([
                    'project_id' => $project->id,
                    'url' => $uploaded['url'],
                    'thumbnail_url' => $uploaded['thumbnail_url'],
                    'order' => $order++,
                ]);
            }

            $project->update([
                'settings' => array_merge($project->settings ?? [], [
                    'ref_paths' => $refPaths,
                ]),
            ]);

            Log::info('ClusteringTestController: starting analysis', [
                'project_id' => $project->id,
                'image_count' => count($refPaths),
            ]);

            $this->analyzer->analyze($project, $refPaths);

            $project->refresh()->load([
                'brandReferences',
                'clusters.images.brandReference',
            ]);

            Log::info('ClusteringTestController: analysis complete', [
                'project_id' => $project->id,
                'cluster_count' => $project->clusters->count(),
            ]);

            return redirect()
                ->route('test.clustering', ['project' => $project->id])
                ->with('success', 'Clustering and DNA analysis complete.');
        } catch (\Throwable $e) {
            Log::error('ClusteringTestController: analyze failed', [
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Clustering failed: '.$e->getMessage()]);
        }
    }

    public function matchCaption(Request $request, Project $project): RedirectResponse
    {
        set_time_limit(0);
        Gate::authorize('view', $project);

        $validated = $request->validate([
            'caption' => 'required|string|min:3|max:2000',
        ]);

        $project->loadMissing(['clusters.images.brandReference']);

        if ($project->clusters->isEmpty()) {
            return back()->withErrors(['error' => 'Run clustering analysis first - no clusters found.']);
        }

        try {
            $caption = $validated['caption'];
            $scores = $this->clusterSelector->scoreClusters($project, $caption);
            $selection = $this->clusterSelector->select($project, $caption);
            $cluster = $selection['cluster'];

            $selectedImages = $selection['images']
                ->map(function ($img) {
                    $ref = $img->brandReference;

                    return [
                        'cluster_image_id' => $img->id,
                        'brand_reference_id' => $ref?->id,
                        'order' => $ref?->order,
                        'url' => $ref ? $this->storageUrl($ref->url) : null,
                        'thumbnail_url' => $ref?->thumbnail_url
                            ? $this->storageUrl($ref->thumbnail_url)
                            : null,
                        'is_anchor' => (bool) $img->is_anchor,
                    ];
                })
                ->values()
                ->all();

            $matchResult = [
                'caption' => $caption,
                'selected_key' => $cluster->key,
                'selected_label' => $cluster->label,
                'scores' => $scores['scores'],
                'used_model_fallback' => $scores['used_model_fallback'],
                'top_score' => $scores['top_score'],
                'second_score' => $scores['second_score'],
                'selected_images' => $selectedImages,
                'matched_at' => now()->toIso8601String(),
            ];

            $lab = $project->settings['clustering_test_lab'] ?? [];
            $project->update([
                'settings' => array_merge($project->settings ?? [], [
                    'clustering_test_lab' => array_merge($lab, [
                        'caption_match' => $matchResult,
                    ]),
                ]),
            ]);

            Log::info('ClusteringTestController: caption matched', [
                'project_id' => $project->id,
                'selected_key' => $cluster->key,
                'used_model_fallback' => $scores['used_model_fallback'],
            ]);

            return redirect()
                ->route('test.clustering', ['project' => $project->id])
                ->with('success', 'Caption matched to cluster "'.$cluster->label.'".');
        } catch (\Throwable $e) {
            Log::error('ClusteringTestController: caption match failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Caption matching failed: '.$e->getMessage()]);
        }
    }

    public function generatePrompt(Request $request, Project $project): RedirectResponse
    {
        set_time_limit(0);
        Gate::authorize('view', $project);

        $lab = $project->settings['clustering_test_lab'] ?? [];
        $defaultCaption = $lab['caption_match']['caption'] ?? '';

        $validated = $request->validate([
            'caption' => 'nullable|string|min:3|max:2000',
            'format' => 'nullable|string|in:1:1,4:5,3:4,2:3,9:16,3:2,4:3,5:4,16:9,square,portrait,landscape',
        ]);

        $caption = trim($validated['caption'] ?? $defaultCaption);
        if ($caption === '') {
            return back()->withErrors(['error' => 'Enter a caption in Step 2 or provide one for prompt generation.']);
        }

        if (! is_array($project->dna_json) || $project->dna_json === []) {
            return back()->withErrors(['error' => 'Run clustering analysis first - no DNA JSON found.']);
        }

        if ($project->clusters()->count() === 0) {
            return back()->withErrors(['error' => 'Run clustering analysis first - no clusters found.']);
        }

        try {
            $format = $validated['format'] ?? '1:1';
            $promptGen = $this->postPromptBuilder->buildLabPrompt(
                $project,
                $caption,
                $format,
                $this->skillPromptBuilder,
            );

            $referenceCount = (int) ($promptGen['reference_count'] ?? 0);
            $promptGen['compiled_prompt'] = $this->promptCompiler->buildImageRequestPrompt(
                $promptGen['prompt_json'],
                $referenceCount,
                $caption,
            );

            $promptGen['selected_cluster_images'] = collect($promptGen['selected_cluster_images'] ?? [])
                ->map(function (array $img) {
                    if (! empty($img['url'])) {
                        $img['url'] = $this->storageUrl((string) $img['url']);
                    }

                    return $img;
                })
                ->values()
                ->all();

            $project->update([
                'settings' => array_merge($project->settings ?? [], [
                    'clustering_test_lab' => array_merge($lab, [
                        'prompt_generation' => $promptGen,
                    ]),
                ]),
            ]);

            Log::info('ClusteringTestController: prompt generated', [
                'project_id' => $project->id,
                'cluster_key' => $promptGen['cluster_key'] ?? null,
                'json_valid' => $promptGen['json_valid'] ?? false,
            ]);

            $label = $promptGen['cluster_label'] ?? $promptGen['cluster_key'] ?? 'cluster';

            return redirect()
                ->route('test.clustering', ['project' => $project->id])
                ->with('success', 'Post prompt generated for cluster "'.$label.'".');
        } catch (\Throwable $e) {
            Log::error('ClusteringTestController: prompt generation failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Prompt generation failed: '.$e->getMessage()]);
        }
    }

    public function generateImage(Request $request, Project $project): RedirectResponse
    {
        set_time_limit(0);
        Gate::authorize('view', $project);

        $lab = $project->settings['clustering_test_lab'] ?? [];
        $promptGen = $lab['prompt_generation'] ?? null;

        if (! is_array($promptGen)) {
            return back()->withErrors(['error' => 'Run Step 3 (Generate post prompt) first.']);
        }

        $promptJson = $promptGen['prompt_json'] ?? null;
        if (! is_array($promptJson) || trim((string) ($promptJson['post']['concept'] ?? '')) === '') {
            return back()->withErrors(['error' => 'Step 3 did not produce valid post prompt JSON.']);
        }

        $validated = $request->validate([
            'resolution_multiplier' => 'nullable|integer|in:1,2,4',
        ]);

        $resolutionMultiplier = (int) ($validated['resolution_multiplier'] ?? 1);
        $clusterKey = (string) ($promptGen['cluster_key'] ?? '');
        $format = FormatPresetMapper::normalize((string) ($promptGen['format'] ?? 'square'));
        $aspectRatio = (string) ($promptGen['aspect_ratio'] ?? FormatPresetMapper::aspectRatio($format));
        $caption = trim((string) ($promptGen['caption_used'] ?? ''));

        try {
            $cluster = $project->clusters()->where('key', $clusterKey)->first();
            if (! $cluster instanceof ProjectCluster) {
                throw new \RuntimeException("Cluster \"{$clusterKey}\" not found.");
            }

            $preferredImageIds = $promptGen['cluster_image_ids'] ?? null;
            $clusterImages = $this->clusterSelector->imagesForCluster(
                $cluster,
                is_array($preferredImageIds) && $preferredImageIds !== [] ? $preferredImageIds : null,
            );

            if ($clusterImages->isEmpty()) {
                throw new \RuntimeException("No reference images found for cluster \"{$clusterKey}\".");
            }

            $imageRequestPrompt = $this->promptCompiler->buildImageRequestPrompt(
                $promptJson,
                $clusterImages->count(),
                $caption !== '' ? $caption : null,
            );

            Log::info('ClusteringTestController: generating image', [
                'project_id' => $project->id,
                'cluster_key' => $clusterKey,
                'reference_count' => $clusterImages->count(),
                'model' => $this->imageGenerator->model(),
            ]);

            $binary = $this->imageGenerator->generateFromPromptJson(
                $promptJson,
                $clusterImages,
                $aspectRatio,
                $resolutionMultiplier,
                $caption !== '' ? $caption : null,
            );

            $directory = "projects/{$project->id}/images";
            $uuid = Str::uuid()->toString();
            $imagePath = "{$directory}/{$uuid}.png";
            $thumbPath = "{$directory}/{$uuid}_thumb.png";

            Storage::disk('public')->put($imagePath, $binary);
            $this->createThumbnail($imagePath, $thumbPath);

            $absolutePath = storage_path("app/public/{$imagePath}");
            [$width, $height] = getimagesize($absolutePath) ?: [null, null];

            $image = Image::create([
                'project_id' => $project->id,
                'url' => $imagePath,
                'thumbnail_url' => $thumbPath,
                'prompt' => json_encode($promptJson, JSON_UNESCAPED_UNICODE),
                'format' => $format,
                'width' => $width,
                'height' => $height,
                'metadata' => [
                    'cluster_key' => $clusterKey,
                    'wizard_type' => 'clustering_test_lab',
                    'resolution_multiplier' => $resolutionMultiplier,
                    'caption' => $caption,
                ],
            ]);

            $sentReferences = $clusterImages
                ->map(function ($img) {
                    $ref = $img->brandReference;

                    return [
                        'cluster_image_id' => $img->id,
                        'brand_reference_id' => $ref?->id,
                        'url' => $ref ? $this->storageUrl($ref->url) : null,
                        'is_anchor' => (bool) $img->is_anchor,
                    ];
                })
                ->values()
                ->all();

            $imageGeneration = [
                'status' => 'completed',
                'image_id' => $image->id,
                'image_url' => $this->storageUrl($imagePath),
                'thumbnail_url' => $this->storageUrl($thumbPath),
                'cluster_key' => $clusterKey,
                'cluster_label' => $promptGen['cluster_label'] ?? $cluster->label,
                'model' => $this->imageGenerator->model(),
                'aspect_ratio' => $aspectRatio,
                'format' => $format,
                'resolution_multiplier' => $resolutionMultiplier,
                'reference_count' => $clusterImages->count(),
                'sent_cluster_images' => $sentReferences,
                'image_request_prompt' => $imageRequestPrompt,
                'width' => $width,
                'height' => $height,
                'generated_at' => now()->toIso8601String(),
            ];

            $project->update([
                'settings' => array_merge($project->settings ?? [], [
                    'clustering_test_lab' => array_merge($lab, [
                        'image_generation' => $imageGeneration,
                    ]),
                ]),
                'status' => 'completed',
            ]);

            Log::info('ClusteringTestController: image generated', [
                'project_id' => $project->id,
                'image_id' => $image->id,
            ]);

            return redirect()
                ->route('test.clustering', ['project' => $project->id])
                ->with('success', 'Image generated successfully.');
        } catch (\Throwable $e) {
            Log::error('ClusteringTestController: image generation failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Image generation failed: '.$e->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function pagePayload(?Project $project): array
    {
        if (! $project) {
            return [
                'project' => null,
                'references' => [],
                'clusters' => [],
                'clusterResult' => null,
                'dnaJson' => null,
                'dnaSummary' => null,
                'captionMatch' => null,
                'promptGeneration' => null,
                'imageGeneration' => null,
                'generatedImage' => null,
            ];
        }

        $references = $project->brandReferences
            ->map(fn (BrandReference $ref) => [
                'id' => $ref->id,
                'url' => $this->storageUrl($ref->url),
                'thumbnail_url' => $ref->thumbnail_url ? $this->storageUrl($ref->thumbnail_url) : null,
                'order' => $ref->order,
            ])
            ->values()
            ->all();

        $referencesByOrder = collect($references)->keyBy('order');

        $clusters = $project->clusters
            ->map(function ($cluster) use ($referencesByOrder) {
                $images = $cluster->images
                    ->map(function ($img) use ($referencesByOrder) {
                        $ref = $img->brandReference;
                        $order = $ref?->order;

                        return [
                            'order' => $order,
                            'url' => $ref ? $this->storageUrl($ref->url) : null,
                            'thumbnail_url' => $ref?->thumbnail_url
                                ? $this->storageUrl($ref->thumbnail_url)
                                : null,
                            'is_anchor' => (bool) $img->is_anchor,
                        ];
                    })
                    ->filter(fn (array $img) => $img['url'] !== null)
                    ->values()
                    ->all();

                return [
                    'id' => $cluster->id,
                    'key' => $cluster->key,
                    'label' => $cluster->label,
                    'summary' => $cluster->summary,
                    'keywords' => $cluster->keywords_json ?? [],
                    'images' => $images,
                ];
            })
            ->values()
            ->all();

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'dna_extracted_at' => $project->dna_extracted_at?->toIso8601String(),
            ],
            'references' => $references,
            'clusters' => $clusters,
            'clusterResult' => $project->settings['cluster_result'] ?? null,
            'dnaJson' => $project->dna_json,
            'dnaSummary' => $project->dna_summary,
            'captionMatch' => $project->settings['clustering_test_lab']['caption_match'] ?? null,
            'promptGeneration' => $project->settings['clustering_test_lab']['prompt_generation'] ?? null,
            'imageGeneration' => $project->settings['clustering_test_lab']['image_generation'] ?? null,
            'generatedImage' => $this->resolveGeneratedImage($project),
        ];
    }

    /**
     * @return array{id: int, url: string, thumbnail_url: string|null}|null
     */
    protected function resolveGeneratedImage(Project $project): ?array
    {
        $labImage = $project->settings['clustering_test_lab']['image_generation'] ?? null;
        if (is_array($labImage) && ! empty($labImage['image_id'])) {
            $image = Image::find($labImage['image_id']);
            if ($image) {
                return [
                    'id' => $image->id,
                    'url' => $this->storageUrl($image->url),
                    'thumbnail_url' => $image->thumbnail_url
                        ? $this->storageUrl($image->thumbnail_url)
                        : null,
                ];
            }
        }

        return null;
    }

    protected function createThumbnail(string $imagePath, string $thumbPath): void
    {
        try {
            $absoluteSource = storage_path("app/public/{$imagePath}");
            $manager = new ImageManager(new Driver());
            $img = $manager->read($absoluteSource);
            $img->scale(width: 400);

            $absoluteThumb = storage_path("app/public/{$thumbPath}");
            $dir = dirname($absoluteThumb);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $img->toPng()->save($absoluteThumb);
        } catch (\Throwable $e) {
            Log::warning('ClusteringTestController: thumbnail creation failed', [
                'error' => $e->getMessage(),
                'path' => $imagePath,
            ]);
        }
    }

    protected function storageUrl(string $path): string
    {
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        if (str_starts_with($path, '/storage/')) {
            return $path;
        }

        return Storage::url($path);
    }
}
