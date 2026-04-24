<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Jobs\AnalyzeBrandJob;
use App\Jobs\DispatchGenerationBatchJob;
use App\Jobs\MatchCaptionsJob;
use App\Models\CsvWizardSession;
use App\Models\GenerationHistory;
use App\Models\Project;
use App\Services\FileUploadService;
use App\Services\FormatPresetMapper;
use App\Services\PostHogService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected FileUploadService $fileUploadService
    ) {}

    /**
     * Display a listing of projects.
     */
    public function index(Request $request): Response
    {
        $query = Auth::user()->projects()->withCount('images');

        // Apply search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply format filter
        if ($request->has('format') && $request->format) {
            $query->where('format', $request->format);
        }

        // Apply filters
        if ($request->has('filter')) {
            switch ($request->filter) {
                case 'recent':
                    $query->recent();
                    break;
                case 'favorites':
                    $query->favorites();
                    break;
            }
        }

        // Apply sorting (default: favorites first, then most recent)
        $sortBy = $request->get('sort', 'default');
        switch ($sortBy) {
            case 'date-asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'date-desc':
                $query->orderBy('created_at', 'desc');
                break;
            case 'name-asc':
                $query->orderBy('title', 'asc');
                break;
            case 'name-desc':
                $query->orderBy('title', 'desc');
                break;
            case 'images-desc':
                $query->withCount('images')->orderBy('images_count', 'desc');
                break;
            default:
                // Default: favorites first, then most recent
                $query->orderBy('is_favorite', 'desc')
                    ->orderBy('created_at', 'desc');
        }

        // Paginate results (20 per page)
        $projects = $query->paginate(20)->through(function ($project) {
            return [
                'id' => $project->id,
                'name' => $project->name,
                'title' => $project->title,
                'format' => $project->format,
                'featured_image' => $project->featured_image,
                'created_at' => $project->created_at->toISOString(),
                'updated_at' => $project->updated_at->toISOString(),
                'images_count' => $project->images_count,
                'is_favorite' => $project->is_favorite,
            ];
        });

        return Inertia::render('projects/index', [
            'projects' => $projects,
        ]);
    }

    /**
     * Show the form for creating a new project.
     */
    public function create(): Response
    {
        return Inertia::render('projects/create');
    }

    /**
     * Store a newly created project.
     */
    public function store(StoreProjectRequest $request)
    {
        $validated = $request->validated();

        // Create the project
        $project = Auth::user()->projects()->create([
            'name' => $validated['name'],
            'title' => $validated['name'], // Use name as title for now
            'description' => $validated['description'] ?? null,
            'settings' => $validated['settings'] ?? null,
            'format' => $validated['format'] ?? null,
        ]);

        // Handle brand reference uploads if present
        if ($request->hasFile('brand_references')) {
            $directory = 'projects/'.$project->id.'/references';

            foreach ($request->file('brand_references') as $index => $file) {
                $uploadResult = $this->fileUploadService->uploadImage($file, $directory);

                $project->brandReferences()->create([
                    'url' => $uploadResult['url'],
                    'thumbnail_url' => $uploadResult['thumbnail_url'],
                    'order' => $index,
                ]);
            }
        }

        app(PostHogService::class)->capture((string) Auth::id(), 'project_created', [
            'project_id' => $project->id,
            'format' => $project->format,
        ]);

        return redirect()->route('projects.show', $project->id)
            ->with('success', 'Project created successfully!');
    }

    /**
     * Display the specified project.
     */
    public function show(Request $request, string $id): Response
    {
        $project = Project::with(['images' => function ($query) {
            $query->orderBy('order')->orderBy('created_at');
        }])->withCount('images')->findOrFail($id);

        // Authorize viewing the project
        $this->authorize('view', $project);

        // Check for pending generations
        $hasPendingGenerations = $project->generationHistory()
            ->whereIn('status', ['pending', 'processing'])
            ->exists();

        // Calculate progress using the current batch's history IDs.
        // Always compute when history_ids exist so the frontend can show
        // completion or failure states even after all jobs finish.
        $progress = null;
        $currentHistoryIds = array_values($project->settings['history_ids'] ?? []);

        if (! empty($currentHistoryIds)) {
            $currentGenerations = $project->generationHistory()
                ->whereIn('id', $currentHistoryIds)
                ->get();

            $failureReasons = $currentGenerations
                ->where('status', 'failed')
                ->values()
                ->map(fn ($h) => [
                    'title' => $h->parameters['title'] ?? null,
                    'message' => $h->error_message ?? 'Generation failed',
                ])
                ->toArray();

            $progress = [
                'total' => $currentGenerations->count(),
                'completed' => $currentGenerations->where('status', 'completed')->count(),
                'failed' => $currentGenerations->where('status', 'failed')->count(),
                'pending' => $currentGenerations->whereIn('status', ['pending', 'processing'])->count(),
                'failure_reasons' => $failureReasons,
            ];
        } elseif ($hasPendingGenerations) {
            // Fallback for legacy projects without history_ids
            $allGenerations = $project->generationHistory()->get();

            $failureReasons = $allGenerations
                ->where('status', 'failed')
                ->values()
                ->map(fn ($h) => [
                    'title' => $h->parameters['title'] ?? null,
                    'message' => $h->error_message ?? 'Generation failed',
                ])
                ->toArray();

            $progress = [
                'total' => $allGenerations->count(),
                'completed' => $allGenerations->where('status', 'completed')->count(),
                'failed' => $allGenerations->where('status', 'failed')->count(),
                'pending' => $allGenerations->whereIn('status', ['pending', 'processing'])->count(),
                'failure_reasons' => $failureReasons,
            ];
        }

        return Inertia::render('projects/show', [
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
                'wizard_type' => $project->settings['wizard_type'] ?? null,
                'brand_reference_count' => $project->brandReferences()->count(),
                'created_at' => $project->created_at->toISOString(),
                'updated_at' => $project->updated_at->toISOString(),
                'images_count' => $project->images_count,
                'is_favorite' => $project->is_favorite,
                'featured_image' => $project->featured_image,
                'images' => $project->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => $image->url,
                        'thumbnail_url' => $image->thumbnail_url ?: $image->url,
                        'prompt' => $image->prompt,
                        'is_favorite' => $image->is_favorite,
                        'metadata' => $image->metadata,
                    ];
                }),
            ],
            // Pass optimistic UI flags from query params
            'justCreated' => $request->query('justCreated', false),
            'expectedImages' => (int) $request->query('expectedImages', 0),
            'batchCompleted' => $request->boolean('batchCompleted'),
            'hasPendingGenerations' => $hasPendingGenerations,
            'progress' => $progress,
            'csvRowTitles' => ($hasPendingGenerations || ($progress && ($progress['pending'] > 0 || $progress['failed'] > 0)))
                ? array_values(array_column($project->settings['csv_data'] ?? [], 'title'))
                : null,
        ]);
    }

    /**
     * Show the form for editing the specified project.
     */
    public function edit(string $id): Response
    {
        $project = Project::findOrFail($id);
        $this->authorize('update', $project);

        return Inertia::render('projects/edit', [
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
            ],
        ]);
    }

    /**
     * Update the specified project.
     */
    public function update(UpdateProjectRequest $request, string $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('update', $project);

        $validated = $request->validated();

        $project->update($validated);

        return redirect()->back()->with('success', 'Project updated successfully!');
    }

    /**
     * Remove the specified project.
     */
    public function destroy(string $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('delete', $project);

        app(PostHogService::class)->capture((string) Auth::id(), 'project_deleted', [
            'project_id' => $project->id,
            'images_count' => $project->images()->count(),
        ]);

        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', 'Project deleted successfully!');
    }

    /**
     * Toggle favorite status of a project.
     */
    public function toggleFavorite(string $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('update', $project);

        $project->update(['is_favorite' => ! $project->is_favorite]);

        return back();
    }

    /**
     * Generate more images for a project.
     * Queues AI generation jobs based on project type.
     */
    public function generateMore(Request $request, string $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('update', $project);

        $user = Auth::user();

        // ── New CSV file uploaded from "Generate More" modal ──────────────
        if ($request->hasFile('csv_file')) {
            $request->validate([
                'csv_file' => ['required', 'file', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel'],
            ]);

            $columnMappings = json_decode($request->input('column_mappings', '{}'), true) ?? [];
            $csvRows = $this->parseCsvForGeneration($request->file('csv_file'), $columnMappings);

            if (empty($csvRows)) {
                return back()->withErrors(['csv_file' => 'The CSV file contains no valid rows.']);
            }

            return $this->dispatchCsvPipeline($project, $user, $csvRows);
        }

        // ── No CSV file: regenerate from existing project data ────────────
        $wizardType = $project->settings['wizard_type'] ?? null;

        if (! $wizardType) {
            return back()->with('error', 'Unable to determine project type for generation.');
        }

        switch ($wizardType) {
            case 'csv':
                if (! isset($project->settings['csv_data'])) {
                    return back()->with('error', 'CSV data not found for this project.');
                }

                return $this->dispatchCsvPipeline($project, $user, $project->settings['csv_data']);

            default:
                return back()->with('error', 'Unknown project type.');
        }
    }

    /**
     * Create GenerationHistory records, a CsvWizardSession, and dispatch
     * the generation pipeline for the given CSV rows.
     */
    private function dispatchCsvPipeline(Project $project, $user, array $csvRows)
    {
        $historyIds = [];
        foreach ($csvRows as $i => $row) {
            $history = GenerationHistory::create([
                'user_id' => $user->id,
                'project_id' => $project->id,
                'ai_model' => config('services.gemini.image_model', 'gemini-2.5-flash-image'),
                'prompt' => $row['title'],
                'status' => 'pending',
                'parameters' => [
                    'csv_row_index' => $i,
                    'title' => $row['title'],
                    'format' => $row['format'] ?? 'square',
                    'wizard_type' => 'csv',
                ],
            ]);
            $historyIds[$i] = $history->id;
        }

        // Ensure ref_paths are populated from brand references if missing
        $refPaths = $project->settings['ref_paths'] ?? [];
        if (empty($refPaths)) {
            $refPaths = $project->brandReferences()->pluck('url')->toArray();
        }

        if (empty($refPaths)) {
            return back()->with('error', 'No brand reference images found. Please upload reference images first.');
        }

        $project->update([
            'settings' => array_merge($project->settings ?? [], [
                'wizard_type' => 'csv',
                'csv_data' => $csvRows,
                'history_ids' => $historyIds,
                'ref_paths' => $refPaths,
            ]),
        ]);

        $session = CsvWizardSession::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'status' => 'pending',
            'total_jobs' => count($csvRows),
        ]);

        // Skip brand analysis if cluster_result already exists
        $hasCluster = ! empty($project->settings['cluster_result']);

        if ($hasCluster) {
            Bus::chain([
                new MatchCaptionsJob($project->id, $session->id),
                new DispatchGenerationBatchJob($project->id, $session->id),
            ])->dispatch();
        } else {
            Bus::chain([
                new AnalyzeBrandJob($project->id, $session->id),
                new MatchCaptionsJob($project->id, $session->id),
                new DispatchGenerationBatchJob($project->id, $session->id),
            ])->dispatch();
        }

        return back()->with(['success' => 'Batch generation started!', 'generating' => true]);
    }

    /**
     * Parse a CSV file into normalised rows for generation.
     */
    private function parseCsvForGeneration(\Illuminate\Http\UploadedFile $file, array $columnMappings): array
    {
        $content = file_get_contents($file->getRealPath());
        $content = ltrim($content, "\xEF\xBB\xBF");

        $lines = array_filter(array_map('trim', explode("\n", $content)));
        $lines = array_values($lines);

        if (count($lines) < 2) {
            return [];
        }

        $headers = str_getcsv(array_shift($lines));

        // Build reverse map from column mappings
        $titleCol = $this->findMappedColumn($columnMappings, 'Product Title');
        $captionCol = $this->findMappedColumn($columnMappings, 'Image Prompt');
        $formatCol = $this->findMappedColumn($columnMappings, 'Format');

        // Fallback: auto-detect columns by header name
        if (! $titleCol) {
            foreach ($headers as $h) {
                $lower = strtolower(trim($h));
                if (str_contains($lower, 'title') || str_contains($lower, 'name')) {
                    $titleCol = trim($h);
                    break;
                }
            }
            $titleCol = $titleCol ?: ($headers[0] ?? null);
        }
        if (! $captionCol) {
            foreach ($headers as $h) {
                $lower = strtolower(trim($h));
                if (str_contains($lower, 'description') || str_contains($lower, 'prompt') || str_contains($lower, 'caption')) {
                    $captionCol = trim($h);
                    break;
                }
            }
        }
        if (! $formatCol) {
            foreach ($headers as $h) {
                $lower = strtolower(trim($h));
                if (str_contains($lower, 'format')) {
                    $formatCol = trim($h);
                    break;
                }
            }
        }

        $rows = [];
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $values = str_getcsv($line);
            $row = array_combine($headers, array_pad($values, count($headers), ''));
            if (! $row) {
                continue;
            }

            $title = trim($row[$titleCol] ?? '');
            $caption = $captionCol ? trim($row[$captionCol] ?? '') : $title;
            $format = $formatCol ? strtolower(trim($row[$formatCol] ?? 'square')) : 'square';

            if (empty($title)) {
                continue;
            }

            $format = FormatPresetMapper::normalize($format);

            $rows[] = [
                'title' => $title,
                'caption' => $caption ?: $title,
                'format' => $format,
            ];
        }

        return $rows;
    }

    private function findMappedColumn(array $columnMappings, string $semanticName): ?string
    {
        foreach ($columnMappings as $csvColumn => $mapped) {
            if ($mapped === $semanticName) {
                return $csvColumn;
            }
        }

        return null;
    }

    /**
     * Get generation progress for a project.
     * Returns statistics about completed/pending generations.
     */
    public function generationProgress(string $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('view', $project);

        // Get generation history for this project
        $totalGenerations = $project->generationHistory()->count();
        $completedGenerations = $project->generationHistory()
            ->where('status', 'completed')
            ->count();
        $failedGenerations = $project->generationHistory()
            ->where('status', 'failed')
            ->count();
        $processingGenerations = $project->generationHistory()
            ->where('status', 'processing')
            ->count();

        // Calculate expected total from project settings
        $expectedTotal = 0;
        $wizardType = $project->settings['wizard_type'] ?? null;

        if ($wizardType === 'csv' && isset($project->settings['csv_rows'])) {
            $expectedTotal = $project->settings['csv_rows'];
        } else {
            $expectedTotal = 1; // Single image for images/text wizard
        }

        $doneGenerations = $completedGenerations + $failedGenerations;
        $pendingGenerations = max(0, $expectedTotal - $doneGenerations);
        $isComplete = $expectedTotal > 0
            ? ($doneGenerations >= $expectedTotal && $processingGenerations === 0)
            : ($processingGenerations === 0);

        // ── Phase detection (v2 pipeline) ────────────────────────────────────
        $settings = $project->settings ?? [];

        // Brand analysis phase
        if (! empty($settings['brand_analysis_failed'])) {
            $brandAnalysisPhase = 'failed';
        } elseif (! empty($settings['brand_analyzed_at'])) {
            $brandAnalysisPhase = 'completed';
        } else {
            $brandAnalysisPhase = 'pending';
        }

        // Image generation phase (derived from history counts)
        if ($totalGenerations === 0) {
            $imageGenerationPhase = 'pending';
        } elseif ($completedGenerations + $failedGenerations >= $expectedTotal) {
            $imageGenerationPhase = 'completed';
        } else {
            $imageGenerationPhase = 'processing';
        }

        // Pipeline version — detect from the most recent history item with parameters
        $pipelineVersion = null;
        $clusterValidated = null;
        $recentHistory = $project->generationHistory()
            ->whereNotNull('parameters')
            ->orderByDesc('created_at')
            ->value('parameters');
        if (is_array($recentHistory) && isset($recentHistory['pipeline_version'])) {
            $pipelineVersion = $recentHistory['pipeline_version'];
        }

        // Cluster validation status from brand style
        $brandStyle = $settings['brand_style'] ?? null;
        if (is_array($brandStyle) && isset($brandStyle['pipeline_validation'])) {
            $clusterValidated = (bool) ($brandStyle['pipeline_validation']['valid'] ?? false);
        }

        // Collect per-item failure reasons for the current batch
        $failureReasons = [];
        if ($failedGenerations > 0) {
            $failureReasons = $project->generationHistory()
                ->where('status', 'failed')
                ->select(['id', 'error_message', 'parameters'])
                ->get()
                ->map(fn ($h) => [
                    'title' => is_array($h->parameters) ? ($h->parameters['title'] ?? null) : null,
                    'message' => $h->error_message ?? 'Generation failed',
                ])
                ->values()
                ->toArray();
        }

        // Brand analysis error if present
        $brandAnalysisError = ! empty($settings['brand_analysis_error'])
            ? $settings['brand_analysis_error']
            : null;

        return response()->json([
            'project_id' => $project->id,
            'expected_total' => $expectedTotal,
            'completed' => $completedGenerations,
            'failed' => $failedGenerations,
            'processing' => $processingGenerations,
            'pending' => $pendingGenerations,
            'total' => $totalGenerations,
            'progress_percentage' => $expectedTotal > 0
                ? round(min(100, ($doneGenerations / $expectedTotal) * 100), 2)
                : 0,
            'is_complete' => $isComplete,
            'failure_reasons' => $failureReasons,
            'brand_analysis_error' => $brandAnalysisError,
            // v2 pipeline optional fields (null when not applicable)
            'pipeline_version' => $pipelineVersion,
            'cluster_validated' => $clusterValidated,
            'phases' => [
                'brand_analysis' => $brandAnalysisPhase,
                'image_generation' => $imageGenerationPhase,
            ],
        ]);
    }
}
