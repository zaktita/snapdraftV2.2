<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeBrandJob;
use App\Jobs\GenerateSingleImageJob;
use App\Models\BrandReference;
use App\Models\CsvWizardSession;
use App\Models\GenerationHistory;
use App\Models\Image;
use App\Models\Project;
use App\Services\Brand\CsvPostPromptBuilder;
use App\Services\FileUploadService;
use App\Services\Prompt\SkillPromptBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PromptForgeTestController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUploadService,
        protected CsvPostPromptBuilder $postPromptBuilder,
        protected SkillPromptBuilder $promptBuilder,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('create', Project::class);

        $session = null;
        if ($request->filled('session')) {
            $session = CsvWizardSession::with([
                'project.brandReferences',
                'project.clusters',
                'project.images',
                'project.generationHistory',
            ])->find($request->integer('session'));

            if ($session) {
                Gate::authorize('view', $session->project);
            }
        }

        return Inertia::render('test/prompt-forge', $this->pagePayload($session));
    }

    public function store(Request $request): RedirectResponse
    {
        set_time_limit(0);
        Gate::authorize('create', Project::class);

        $validated = $request->validate([
            'brand_name' => 'required|string|min:2|max:120',
            'reference_images' => 'required|array|min:2|max:10',
            'reference_images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
            'caption' => 'required|string|min:3|max:500',
            'format' => 'required|string|in:1:1,4:5,3:4,2:3,9:16,3:2,4:3,5:4,16:9',
        ]);

        try {
            $csvRow = [
                'title' => $validated['caption'],
                'caption' => $validated['caption'],
                'format' => $validated['format'],
            ];

            $project = Project::create([
                'user_id' => auth()->id(),
                'name' => $validated['brand_name'],
                'title' => $validated['brand_name'],
                'description' => 'PromptForge lab: '.$validated['caption'],
                'format' => $validated['format'],
                'status' => 'processing',
                'settings' => [
                    'type' => 'prompt_forge_lab',
                    'wizard_type' => 'csv',
                    'csv_data' => [$csvRow],
                    'resolution_multiplier' => 1,
                    'prompt_forge_lab' => [
                        'pipeline_step' => 'setup_complete',
                        'caption' => $validated['caption'],
                        'format' => $validated['format'],
                        'brand_name' => $validated['brand_name'],
                    ],
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

            $history = GenerationHistory::create([
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'ai_model' => config('services.gemini.image_model', 'gemini-3.1-flash-image-preview'),
                'prompt' => $validated['caption'],
                'status' => 'pending',
                'parameters' => [
                    'csv_row_index' => 0,
                    'title' => $validated['caption'],
                    'caption' => $validated['caption'],
                    'format' => $validated['format'],
                    'wizard_type' => 'prompt_forge_lab',
                    'resolution_multiplier' => 1,
                ],
            ]);

            $project->update([
                'settings' => array_merge($project->settings ?? [], [
                    'ref_paths' => $refPaths,
                    'history_ids' => [0 => $history->id],
                ]),
            ]);

            $session = CsvWizardSession::create([
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'status' => 'pending',
                'total_jobs' => 1,
            ]);

            return redirect()->route('test.prompt-forge', ['session' => $session->id])
                ->with('success', 'Session created. Run Step 1 (AnalyzeBrandJob) to extract DNA.');
        } catch (\Throwable $e) {
            Log::error('PromptForgeTestController: store failed', [
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to create session: '.$e->getMessage()]);
        }
    }

    public function extract(CsvWizardSession $session): RedirectResponse
    {
        set_time_limit(0);
        Gate::authorize('view', $session->project);

        try {
            AnalyzeBrandJob::dispatchSync($session->project_id, $session->id);

            $session->refresh();
            $project = $session->project()->with('clusters')->firstOrFail();

            if ($session->isFailed()) {
                return back()->withErrors([
                    'error' => $session->error_message ?? 'Brand analysis failed.',
                ]);
            }

            $this->mergeLabDebug($project, [
                'pipeline_step' => 'step1_complete',
                'step1' => [
                    'status' => 'completed',
                    'cluster_count' => $project->clusters->count(),
                    'dna_summary' => $project->dna_summary,
                    'dna_extracted_at' => $project->dna_extracted_at?->toIso8601String(),
                ],
            ]);

            return redirect()->route('test.prompt-forge', ['session' => $session->id])
                ->with('success', 'Step 1 complete — AnalyzeBrandJob finished.');
        } catch (\Throwable $e) {
            Log::error('PromptForgeTestController: extract failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'DNA extraction failed: '.$e->getMessage()]);
        }
    }

    public function generatePost(CsvWizardSession $session): RedirectResponse
    {
        set_time_limit(0);
        Gate::authorize('view', $session->project);

        $project = $session->project()->with(['clusters.images.brandReference'])->firstOrFail();

        if (! is_array($project->dna_json) || $project->dna_json === []) {
            return back()->withErrors(['error' => 'Run Step 1 (Extract DNA) first.']);
        }

        try {
            $built = $this->postPromptBuilder->buildLabBatch(
                $project,
                app(\App\Services\Brand\ProjectClusterSelector::class),
                $this->promptBuilder,
            );

            $project->update([
                'settings' => array_merge($project->settings ?? [], [
                    'prompt_batch' => $built['prompt_batch'],
                ]),
            ]);

            $step2 = $built['step2_debug'][0] ?? [];
            $invalid = ($step2['json_valid'] ?? false) === false;

            $this->mergeLabDebug($project, [
                'pipeline_step' => $invalid ? 'step2_failed' : 'step2_complete',
                'step2' => $step2,
            ]);

            if ($invalid) {
                $session->markAsFailed('Post JSON validation failed: '.implode('; ', $step2['json_validation_errors'] ?? []));

                return back()->withErrors([
                    'error' => 'Post JSON generation completed but validation failed.',
                ]);
            }

            return redirect()->route('test.prompt-forge', ['session' => $session->id])
                ->with('success', 'Step 2 complete — post JSON generated via CsvPostPromptBuilder.');
        } catch (\Throwable $e) {
            Log::error('PromptForgeTestController: generatePost failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            $session->markAsFailed($e->getMessage());

            return back()->withErrors(['error' => 'Post JSON generation failed: '.$e->getMessage()]);
        }
    }

    public function generateImage(CsvWizardSession $session): RedirectResponse
    {
        set_time_limit(0);
        Gate::authorize('view', $session->project);

        $project = $session->project()->firstOrFail();
        $promptBatch = $project->settings['prompt_batch'] ?? [];
        $promptItem = $promptBatch[0] ?? null;

        if ($promptItem === null) {
            return back()->withErrors(['error' => 'Run Step 2 (Generate post JSON) first.']);
        }

        $history = isset($promptItem['historyId'])
            ? GenerationHistory::find($promptItem['historyId'])
            : null;

        if (! $history || ! is_array($history->prompt_json)) {
            return back()->withErrors(['error' => 'No post JSON found on generation history.']);
        }

        try {
            $session->markAsGenerating();

            GenerateSingleImageJob::dispatchSync(
                $session->project_id,
                $session->id,
                $promptItem,
            );

            $history->refresh();
            $image = $this->resolveGeneratedImage($project, $history);
            $imageGenDebug = data_get($history->request_data, 'image_generation', []);
            $sentReferences = $this->resolveSentReferences($project, $history);

            $project->update(['status' => 'completed']);
            $session->markAsCompleted();

            $this->mergeLabDebug($project, [
                'pipeline_step' => 'step3_complete',
                'step3' => [
                    'status' => $history->status,
                    'cluster_key' => $history->cluster_key,
                    'prompt_json' => $history->prompt_json,
                    'compiled_prompt' => $history->compiled_prompt,
                    'image_request_prompt' => $imageGenDebug['image_request_prompt'] ?? null,
                    'reference_count' => $imageGenDebug['reference_count'] ?? count($sentReferences),
                    'sent_cluster_images' => $sentReferences,
                    'model_slug' => $imageGenDebug['model'] ?? config('services.gemini.image_model', 'gemini-3.1-flash-image-preview'),
                    'sent_to_model' => $imageGenDebug['sent_to_model'] ?? 'gemini/buildImageRequestPrompt',
                    'image_id' => $image?->id,
                    'image_url' => $image ? $this->storageUrl($image->url) : null,
                ],
            ]);

            return redirect()->route('test.prompt-forge', ['session' => $session->id])
                ->with('success', 'Step 3 complete — GenerateSingleImageJob finished.');
        } catch (\Throwable $e) {
            Log::error('PromptForgeTestController: generateImage failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            $session->markAsFailed($e->getMessage());
            $project->update(['status' => 'failed']);

            return back()->withErrors(['error' => 'Image generation failed: '.$e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    protected function mergeLabDebug(Project $project, array $patch): void
    {
        $lab = $project->settings['prompt_forge_lab'] ?? [];
        $project->update([
            'settings' => array_merge($project->settings ?? [], [
                'prompt_forge_lab' => array_merge($lab, $patch),
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function pagePayload(?CsvWizardSession $session): array
    {
        if (! $session) {
            return [
                'session' => null,
                'project' => null,
                'references' => [],
                'clusters' => [],
                'debug' => null,
                'generatedImage' => null,
                'generationHistory' => null,
            ];
        }

        $project = $session->project;
        $lab = $project?->settings['prompt_forge_lab'] ?? [];
        $history = $project?->generationHistory()->latest()->first();

        $generatedImage = $this->resolveGeneratedImage($project, $history);

        $references = $project?->brandReferences
            ->map(fn (BrandReference $ref) => [
                'id' => $ref->id,
                'url' => $this->storageUrl($ref->url),
                'thumbnail_url' => $ref->thumbnail_url ? $this->storageUrl($ref->thumbnail_url) : null,
                'order' => $ref->order,
            ])
            ->values()
            ->all() ?? [];

        $clusters = $project?->clusters
            ->map(fn ($cluster) => [
                'id' => $cluster->id,
                'key' => $cluster->key,
                'label' => $cluster->label,
                'summary' => $cluster->summary,
                'keywords' => $cluster->keywords_json ?? [],
            ])
            ->values()
            ->all() ?? [];

        $step2 = $lab['step2'] ?? null;
        $lab = array_merge($lab, [
            'step3' => $this->enrichStep3Debug($project, $history, $lab['step3'] ?? null, $step2),
        ]);

        return [
            'session' => [
                'id' => $session->id,
                'caption' => $lab['caption'] ?? $history?->parameters['caption'] ?? '',
                'format' => $lab['format'] ?? $history?->parameters['format'] ?? '1:1',
                'status' => $session->status,
                'error_message' => $session->error_message,
                'cluster_key' => $step2['cluster_key'] ?? $history?->cluster_key,
                'cluster_label' => $step2['cluster_label'] ?? null,
                'prompt_json' => $step2['prompt_json'] ?? $history?->prompt_json,
                'compiled_prompt' => $history?->compiled_prompt,
            ],
            'project' => $project ? [
                'id' => $project->id,
                'name' => $project->name,
                'dna_summary' => $project->dna_summary,
                'dna_extracted_at' => $project->dna_extracted_at?->toIso8601String(),
                'dna_json' => $project->dna_json,
            ] : null,
            'references' => $references,
            'clusters' => $clusters,
            'debug' => $lab,
            'generationHistory' => $history ? [
                'id' => $history->id,
                'status' => $history->status,
                'cluster_key' => $history->cluster_key,
                'json_valid' => $history->json_valid,
                'request_data' => $history->request_data,
            ] : null,
            'generatedImage' => $generatedImage ? [
                'id' => $generatedImage->id,
                'url' => $this->storageUrl($generatedImage->url),
                'thumbnail_url' => $generatedImage->thumbnail_url
                    ? $this->storageUrl($generatedImage->thumbnail_url)
                    : null,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $step2
     * @return array<string, mixed>|null
     */
    protected function enrichStep3Debug(
        ?Project $project,
        ?GenerationHistory $history,
        ?array $step3,
        ?array $step2,
    ): ?array {
        if (! $history && ! $step3) {
            return null;
        }

        $step3 = $step3 ?? [];
        $imageGen = data_get($history?->request_data, 'image_generation', []);

        if (empty($step3['image_request_prompt'])) {
            $step3['image_request_prompt'] = $imageGen['image_request_prompt']
                ?? $imageGen['prompt_excerpt']
                ?? null;
        }

        if (empty($step3['prompt_json']) && is_array($history?->prompt_json)) {
            $step3['prompt_json'] = $history->prompt_json;
        }

        if (empty($step3['cluster_key'])) {
            $step3['cluster_key'] = $history?->cluster_key ?? $step2['cluster_key'] ?? null;
        }

        if (empty($step3['sent_cluster_images'])) {
            $resolved = $this->resolveSentReferences($project, $history);
            if ($resolved !== []) {
                $step3['sent_cluster_images'] = $resolved;
            } elseif (! empty($step2['selected_cluster_images'])) {
                $step3['sent_cluster_images'] = collect($step2['selected_cluster_images'])
                    ->map(fn (array $img) => [
                        'cluster_image_id' => null,
                        'brand_reference_id' => $img['brand_reference_id'] ?? null,
                        'url' => isset($img['url']) ? $this->storageUrl((string) $img['url']) : null,
                        'is_anchor' => $img['is_anchor'] ?? false,
                    ])
                    ->values()
                    ->all();
            }
        }

        if (! isset($step3['reference_count']) && ! empty($step3['sent_cluster_images'])) {
            $step3['reference_count'] = count($step3['sent_cluster_images']);
        }

        return $step3 === [] ? null : $step3;
    }

    /**
     * @return list<array{cluster_image_id: ?int, brand_reference_id: ?int, url: ?string, is_anchor: bool}>
     */
    protected function resolveSentReferences(?Project $project, ?GenerationHistory $history): array
    {
        if (! $project || ! $history || ! $history->cluster_key) {
            return [];
        }

        $clusterImageIds = data_get($history->request_data, 'cluster_image_ids')
            ?? data_get($history->request_data, 'image_generation.cluster_image_ids');

        if (! is_array($clusterImageIds) || $clusterImageIds === []) {
            return [];
        }

        $cluster = $project->clusters()->where('key', $history->cluster_key)->first();
        if (! $cluster) {
            return [];
        }

        return $cluster->images()
            ->whereIn('id', $clusterImageIds)
            ->with('brandReference')
            ->get()
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
    }

    protected function resolveGeneratedImage(?Project $project, ?GenerationHistory $history): ?Image
    {
        if ($history?->image_id) {
            return Image::find($history->image_id);
        }

        $responseImageId = data_get($history?->response_data, 'image_id');
        if ($responseImageId) {
            return Image::find($responseImageId);
        }

        return $project?->images()->latest()->first();
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
