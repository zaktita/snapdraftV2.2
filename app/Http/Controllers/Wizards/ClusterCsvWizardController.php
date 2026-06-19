<?php

namespace App\Http\Controllers\Wizards;

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
use App\Services\PostHogService;
use App\Services\Wizards\ClusterCsvPipeline;
use App\Services\Wizards\ImageGenerationDebugPayload;
use App\Services\Wizards\CsvRowParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ClusterCsvWizardController extends Controller
{
    use PresentsClusterCsvSession;

    public function __construct(
        protected FileUploadService $fileUploadService,
        protected CsvRowParser $csvRowParser,
    ) {}

    public function create(): Response
    {
        return Inertia::render('projects/wizards/csv-cluster');
    }

    public function store(Request $request)
    {
        $request->validate([
            'project_name' => ['required', 'string', 'max:255'],
            'csv_file' => ['required', 'file', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel'],
            'column_mappings' => ['required', 'string'],
            'reference_images' => ['required', 'array', 'min:3', 'max:10'],
            'reference_images.*' => ['required', 'file', 'image', 'max:10240'],
            'resolution_multiplier' => ['nullable', 'integer', 'in:1,2,4'],
        ]);

        $user = Auth::user();
        $columnMappings = json_decode($request->input('column_mappings'), true) ?? [];
        $resolutionMultiplier = (int) $request->input('resolution_multiplier', 1);

        $csvRows = $this->csvRowParser->parse($request->file('csv_file'), $columnMappings);

        if ($csvRows === []) {
            return back()->withErrors(['csv_file' => 'The CSV file contains no valid rows.']);
        }

        if (! $user->hasActiveSubscription()) {
            return redirect()->route('dashboard')
                ->with('error', 'You need a beta invite to generate images. Check your email for your invite code.');
        }

        $rowCount = count($csvRows);
        $requiredCredits = $rowCount * $resolutionMultiplier;
        if (! $user->hasCredits($requiredCredits)) {
            $remaining = $user->creditsRemaining();

            return back()->withErrors([
                'credits' => "Not enough credits. You selected {$rowCount} rows at {$resolutionMultiplier}x ({$requiredCredits} credits total), but only have {$remaining} credit(s) remaining.",
            ]);
        }

        $project = $user->projects()->create([
            'name' => $request->input('project_name'),
            'title' => $request->input('project_name'),
            'status' => 'processing',
            'settings' => [
                'wizard_type' => 'csv_cluster',
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

            BrandReference::create([
                'project_id' => $project->id,
                'url' => $uploaded['url'],
                'thumbnail_url' => $uploaded['thumbnail_url'],
                'order' => $order++,
            ]);

            $refPaths[] = $uploaded['url'];
        }

        $historyIds = [];

        foreach ($csvRows as $i => $row) {
            $history = GenerationHistory::create([
                'user_id' => $user->id,
                'project_id' => $project->id,
                'ai_model' => config('services.gemini.image_model', 'gemini-3.1-flash-image-preview'),
                'prompt' => $row['title'],
                'status' => 'pending',
                'parameters' => [
                    'csv_row_index' => $i,
                    'title' => $row['title'],
                    'caption' => $row['caption'],
                    'format' => $row['format'],
                    'wizard_type' => 'csv_cluster',
                    'resolution_multiplier' => $resolutionMultiplier,
                ],
            ]);

            $historyIds[$i] = $history->id;
        }

        $project->update([
            'settings' => array_merge($project->settings, [
                'ref_paths' => $refPaths,
                'history_ids' => $historyIds,
            ]),
        ]);

        $session = CsvWizardSession::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'status' => 'pending',
            'total_jobs' => count($csvRows),
        ]);

        try {
            Bus::chain([
                new AnalyzeBrandJob($project->id, $session->id),
                new MatchCaptionsToClustersJob($project->id, $session->id),
                new GeneratePostPromptsJob($project->id, $session->id),
                new DispatchGenerationBatchJob($project->id, $session->id),
            ])->dispatch();
        } catch (\Throwable $e) {
            Log::error('ClusterCsvWizardController: pipeline failed', [
                'project_id' => $project->id,
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('projects.wizards.csv-cluster.session', $session->id)
                ->with('error', 'Generation failed: '.$e->getMessage());
        }

        app(PostHogService::class)->capture((string) $user->id, 'csv_cluster_generation_started', [
            'project_id' => $project->id,
            'row_count' => count($csvRows),
            'ref_count' => count($refPaths),
            'resolution_multiplier' => $resolutionMultiplier,
            'credits_required' => $requiredCredits,
        ]);

        Log::info('ClusterCsvWizardController: pipeline dispatched', [
            'project_id' => $project->id,
            'session_id' => $session->id,
            'row_count' => count($csvRows),
        ]);

        return redirect()->route('projects.wizards.csv-cluster.session', $session->id);
    }

    public function show(CsvWizardSession $session): Response|RedirectResponse
    {
        $this->authorize('view', $session->project);

        $project = $session->project()->with(['generationHistory'])->firstOrFail();
        $pipeline = $project->settings['cluster_csv_pipeline'] ?? [];

        if (($pipeline['phase'] ?? null) === 'complete' || $session->isCompleted()) {
            return redirect()->route('projects.wizards.csv-cluster.result', $session->id);
        }

        return Inertia::render('projects/wizards/csv-cluster-processing', [
            'session' => $this->sessionPayload($session),
            'pipeline' => $this->pipelinePayload($project),
            'rows' => $this->rowsPayload($project),
            'urls' => $this->urlPayload($session),
        ]);
    }

    public function status(CsvWizardSession $session): JsonResponse
    {
        $this->authorize('view', $session->project);

        $session->refresh();
        $project = $session->project()->with(['generationHistory'])->firstOrFail();

        return response()->json([
            'session' => $this->sessionPayload($session),
            'pipeline' => $this->pipelinePayload($project),
            'rows' => $this->rowsPayload($project),
        ]);
    }

    public function result(CsvWizardSession $session): Response
    {
        $this->authorize('view', $session->project);

        $project = $session->project()->with(['generationHistory', 'images'])->firstOrFail();

        return Inertia::render('projects/wizards/csv-cluster-processing', [
            'session' => $this->sessionPayload($session),
            'pipeline' => $this->pipelinePayload($project),
            'rows' => $this->rowsPayload($project),
            'completed' => true,
            'urls' => $this->urlPayload($session),
        ]);
    }

    public function rowDebug(CsvWizardSession $session, int $rowIndex): JsonResponse
    {
        $this->authorize('view', $session->project);

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
        return [
            'status' => route('projects.wizards.csv-cluster.status', $session->id),
            'result' => route('projects.wizards.csv-cluster.result', $session->id),
            'project' => route('projects.show', $session->project_id),
            'row_debug' => route('projects.wizards.csv-cluster.row-debug', [
                'session' => $session->id,
                'rowIndex' => '__ROW__',
            ]),
        ];
    }
}
