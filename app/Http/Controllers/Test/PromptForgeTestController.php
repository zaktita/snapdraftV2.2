<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Concerns\PresentsClusterCsvSession;
use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeBrandJob;
use App\Jobs\DispatchGenerationBatchJob;
use App\Jobs\GeneratePostPromptsJob;
use App\Jobs\MatchCaptionsToClustersJob;
use App\Models\BrandReference;
use App\Models\CsvWizardSession;
use App\Models\GenerationHistory;
use App\Models\Project;
use App\Services\FileUploadService;
use App\Services\Wizards\ClusterCsvPipeline;
use App\Services\Wizards\CsvRowParser;
use App\Services\Wizards\ImageGenerationDebugPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PromptForgeTestController extends Controller
{
    use PresentsClusterCsvSession;

    public function __construct(
        protected FileUploadService $fileUploadService,
        protected CsvRowParser $csvRowParser,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('create', Project::class);

        return Inertia::render('projects/wizards/csv', [
            'wizardMode' => 'prompt_forge_lab',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        set_time_limit(0);
        Gate::authorize('create', Project::class);

        $request->validate([
            'project_name' => ['required', 'string', 'max:255'],
            'csv_file' => ['required', 'file', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel'],
            'column_mappings' => ['required', 'string'],
            'reference_images' => ['required', 'array', 'min:3', 'max:10'],
            'reference_images.*' => ['required', 'file', 'image', 'max:10240'],
            'resolution_multiplier' => ['nullable', 'integer', 'in:1,2,4'],
        ]);

        $columnMappings = json_decode($request->input('column_mappings'), true) ?? [];
        $resolutionMultiplier = (int) $request->input('resolution_multiplier', 1);
        $csvRows = $this->csvRowParser->parse($request->file('csv_file'), $columnMappings);

        if ($csvRows === []) {
            return back()->withErrors(['csv_file' => 'The CSV file contains no valid rows.']);
        }

        try {
            $project = Project::create([
                'user_id' => auth()->id(),
                'name' => $request->input('project_name'),
                'title' => $request->input('project_name'),
                'status' => 'processing',
                'settings' => [
                    'type' => 'prompt_forge_lab',
                    'wizard_type' => 'prompt_forge_lab',
                    'csv_data' => $csvRows,
                    'resolution_multiplier' => $resolutionMultiplier,
                    'cluster_csv_pipeline' => [
                        'phase' => 'pending',
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

            $historyIds = [];

            foreach ($csvRows as $i => $row) {
                $caption = trim((string) ($row['caption'] ?? $row['title'] ?? ''));

                $history = GenerationHistory::create([
                    'user_id' => auth()->id(),
                    'project_id' => $project->id,
                    'ai_model' => config('services.gemini.image_model', 'gemini-3.1-flash-image-preview'),
                    'prompt' => $caption,
                    'status' => 'pending',
                    'parameters' => [
                        'csv_row_index' => $i,
                        'title' => $row['title'] ?? $caption,
                        'caption' => $caption,
                        'format' => $row['format'] ?? 'square',
                        'wizard_type' => 'prompt_forge_lab',
                        'resolution_multiplier' => $resolutionMultiplier,
                    ],
                ]);

                $historyIds[$i] = $history->id;
            }

            $project->update([
                'settings' => array_merge($project->settings ?? [], [
                    'ref_paths' => $refPaths,
                    'history_ids' => $historyIds,
                ]),
            ]);

            $session = CsvWizardSession::create([
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'status' => 'pending',
                'total_jobs' => count($csvRows),
            ]);

            Bus::chain([
                new AnalyzeBrandJob($project->id, $session->id),
                new MatchCaptionsToClustersJob($project->id, $session->id),
                new GeneratePostPromptsJob($project->id, $session->id),
                new DispatchGenerationBatchJob($project->id, $session->id),
            ])->dispatch();

            Log::info('PromptForgeTestController: pipeline dispatched', [
                'project_id' => $project->id,
                'session_id' => $session->id,
                'row_count' => count($csvRows),
            ]);

            return redirect()->route('test.prompt-forge.session', $session->id);
        } catch (\Throwable $e) {
            Log::error('PromptForgeTestController: store failed', [
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to start generation: '.$e->getMessage()]);
        }
    }

    public function show(CsvWizardSession $session): Response|RedirectResponse
    {
        Gate::authorize('view', $session->project);

        $project = $session->project()->with(['generationHistory'])->firstOrFail();
        $pipeline = $project->settings['cluster_csv_pipeline'] ?? [];

        if (($pipeline['phase'] ?? null) === 'complete' || $session->isCompleted()) {
            return redirect($this->projectRedirectUrl($session));
        }

        return Inertia::render('projects/wizards/csv-cluster-processing', [
            'session' => $this->sessionPayload($session),
            'pipeline' => $this->pipelinePayload($project),
            'rows' => $this->rowsPayload($project),
            'urls' => $this->urlPayload($session),
            'lab' => $this->labPayload(),
        ]);
    }

    public function status(CsvWizardSession $session): JsonResponse
    {
        Gate::authorize('view', $session->project);

        $session->refresh();
        $project = $session->project()->with(['generationHistory'])->firstOrFail();

        return response()->json([
            'session' => $this->sessionPayload($session),
            'pipeline' => $this->pipelinePayload($project),
            'rows' => $this->rowsPayload($project),
        ]);
    }

    public function result(CsvWizardSession $session): RedirectResponse
    {
        Gate::authorize('view', $session->project);

        return redirect($this->projectRedirectUrl($session));
    }

    public function rowDebug(CsvWizardSession $session, int $rowIndex): JsonResponse
    {
        Gate::authorize('view', $session->project);

        $project = $session->project()
            ->with(['generationHistory', 'clusters.images.brandReference'])
            ->firstOrFail();

        $csvData = $project->settings['csv_data'] ?? [];
        if (! array_key_exists($rowIndex, $csvData)) {
            abort(404, 'Row not found.');
        }

        $historyIds = $project->settings['history_ids'] ?? [];
        $historyId = $historyIds[$rowIndex] ?? null;
        $history = $historyId
            ? $project->generationHistory->firstWhere('id', $historyId)
            : null;

        if (! $history) {
            return response()->json([
                'row_index' => $rowIndex,
                'available' => false,
                'message' => 'Generation history not found for this row yet.',
            ]);
        }

        return response()->json(
            ImageGenerationDebugPayload::forHistory($project, $history, $rowIndex)
        );
    }

    /**
     * @return array<string, string>
     */
    protected function urlPayload(CsvWizardSession $session): array
    {
        $projectUrl = $this->projectRedirectUrl($session);

        return [
            'status' => route('test.prompt-forge.status', $session->id),
            'result' => $projectUrl,
            'project' => $projectUrl,
            'row_debug' => route('test.prompt-forge.row-debug', [
                'session' => $session->id,
                'rowIndex' => '__ROW__',
            ]),
        ];
    }

    protected function projectRedirectUrl(CsvWizardSession $session): string
    {
        return route('projects.show', $session->project_id)
            .'?justCreated=1&expectedImages='.$session->total_jobs;
    }

    /**
     * @return array<string, string>
     */
    protected function labPayload(): array
    {
        return [
            'title' => 'PromptForge CSV Lab',
            'setup_href' => route('test.prompt-forge'),
            'breadcrumb_label' => 'PromptForge Lab',
        ];
    }
}
