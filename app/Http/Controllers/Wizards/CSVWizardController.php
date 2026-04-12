<?php

namespace App\Http\Controllers\Wizards;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeBrandJob;
use App\Jobs\DispatchGenerationBatchJob;
use App\Jobs\MatchCaptionsJob;
use App\Models\BrandReference;
use App\Models\CsvWizardSession;
use App\Models\GenerationHistory;
use App\Models\Project;
use App\Services\FileUploadService;
use App\Services\PostHogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CSVWizardController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUploadService,
    ) {}

    /**
     * Handle the CSV wizard form submission.
     * Creates project, uploads refs, dispatches the 3-phase generation pipeline,
     * then redirects directly to the project show page.
     *
     * @route POST /projects/wizards/csv
     */
    public function store(Request $request)
    {
        $request->validate([
            'project_name'      => ['required', 'string', 'max:255'],
            'csv_file'          => ['required', 'file', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel'],
            'column_mappings'   => ['required', 'string'],
            'reference_images'  => ['required', 'array', 'min:3', 'max:10'],
            'reference_images.*' => ['required', 'file', 'image', 'max:10240'],
        ]);

        $user           = Auth::user();
        $columnMappings = json_decode($request->input('column_mappings'), true) ?? [];

        // ── 1. Parse CSV ─────────────────────────────────────────────────────
        $csvRows = $this->parseCsvRows($request->file('csv_file'), $columnMappings);

        if (empty($csvRows)) {
            return back()->withErrors(['csv_file' => 'The CSV file contains no valid rows.']);
        }

        // ── 1b. Credit gate — must have enough credits for every selected row ─
        if (!$user->hasActiveSubscription()) {
            return redirect()->route('dashboard')
                ->with('error', 'You need a beta invite to generate images. Check your email for your invite code.');
        }

        $rowCount = count($csvRows);
        if (!$user->hasCredits($rowCount)) {
            $remaining = $user->creditsRemaining();
            return back()->withErrors([
                'credits' => "Not enough credits. You selected {$rowCount} rows but only have {$remaining} credit(s) remaining.",
            ]);
        }

        // ── 2. Create project ─────────────────────────────────────────────────
        $project = $user->projects()->create([
            'name'        => $request->input('project_name'),
            'title'       => $request->input('project_name'),
            'settings'    => [
                'wizard_type' => 'csv',
                'csv_data'    => $csvRows,
            ],
        ]);

        // ── 3. Upload reference images ────────────────────────────────────────
        $refPaths = [];
        $order    = 0;

        foreach ($request->file('reference_images') as $file) {
            $uploaded = $this->fileUploadService->uploadImage(
                $file,
                "projects/{$project->id}/references"
            );

            BrandReference::create([
                'project_id'    => $project->id,
                'url'           => $uploaded['url'],
                'thumbnail_url' => $uploaded['thumbnail_url'],
                'order'         => $order++,
            ]);

            // Collect the path relative to storage/app/public (url field from FileUploadService)
            $refPaths[] = $uploaded['url'];
        }

        // ── 4. Create pending GenerationHistory records (one per CSV row) ─────
        $historyIds = [];

        foreach ($csvRows as $i => $row) {
            $history = GenerationHistory::create([
                'user_id'    => $user->id,
                'project_id' => $project->id,
                'ai_model'   => config('services.gemini.image_model', 'gemini-2.5-flash-image'),
                'prompt'     => $row['title'],
                'status'     => 'pending',
                'parameters' => [
                    'csv_row_index' => $i,
                    'title'         => $row['title'],
                    'format'        => $row['format'],
                    'wizard_type'   => 'csv',
                ],
            ]);

            $historyIds[$i] = $history->id;
        }

        // ── 5. Persist pipeline data in project settings ──────────────────────
        $project->update([
            'settings' => array_merge($project->settings, [
                'ref_paths'   => $refPaths,
                'history_ids' => $historyIds,
            ]),
        ]);

        // ── 6. Create CsvWizardSession ─────────────────────────────────────────
        $session = CsvWizardSession::create([
            'user_id'    => $user->id,
            'project_id' => $project->id,
            'status'     => 'pending',
            'total_jobs' => count($csvRows),
        ]);

        // ── 7. Dispatch the 3-phase pipeline chain ────────────────────────────
        Bus::chain([
            new AnalyzeBrandJob($project->id, $session->id),
            new MatchCaptionsJob($project->id, $session->id),
            new DispatchGenerationBatchJob($project->id, $session->id),
        ])->dispatch();

        app(PostHogService::class)->capture((string) $user->id, 'csv_generation_started', [
            'project_id' => $project->id,
            'row_count'  => count($csvRows),
            'ref_count'  => count($refPaths),
        ]);

        Log::info('CSVWizardController: pipeline dispatched', [
            'project_id'  => $project->id,
            'session_id'  => $session->id,
            'row_count'   => count($csvRows),
            'ref_count'   => count($refPaths),
        ]);

        // ── 8. Redirect to project show with optimistic UI flags ──────────────
        $redirectUrl = route('projects.show', $project->id)
            . '?justCreated=1&expectedImages=' . count($csvRows);

        return redirect($redirectUrl);
    }

    /**
     * Show CSV wizard session status (used by Wayfinder session/show route).
     *
     * @route GET /projects/wizards/csv/sessions/{session}
     */
    public function show(CsvWizardSession $session)
    {
        $this->authorize('view', $session->project);

        return Inertia::render('projects/wizards/csv-processing', [
            'session' => [
                'id'         => $session->id,
                'status'     => $session->status,
                'total_jobs' => $session->total_jobs,
                'project_id' => $session->project_id,
            ],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Parse CSV file into normalised rows using column mappings.
     * column_mappings: { csvColumnName => 'Product Title'|'Image Prompt'|'Format'|... }
     */
    private function parseCsvRows(\Illuminate\Http\UploadedFile $file, array $columnMappings): array
    {
        $content = file_get_contents($file->getRealPath());

        // Strip BOM if present
        $content = ltrim($content, "\xEF\xBB\xBF");

        $lines = array_filter(array_map('trim', explode("\n", $content)));
        $lines = array_values($lines);

        if (count($lines) < 2) {
            return [];
        }

        $headers = str_getcsv(array_shift($lines));

        // Build reverse map: semantic field → csv column name
        $titleCol   = $this->findColumn($columnMappings, 'Product Title');
        $captionCol = $this->findColumn($columnMappings, 'Image Prompt');
        $formatCol  = $this->findColumn($columnMappings, 'Format');

        if (!$titleCol) {
            // Fallback: use first column as title
            $titleCol = $headers[0] ?? null;
        }

        $rows = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $values = str_getcsv($line);
            $row    = array_combine($headers, array_pad($values, count($headers), ''));

            if (!$row) {
                continue;
            }

            $title   = trim($row[$titleCol] ?? '');
            $caption = $captionCol ? trim($row[$captionCol] ?? '') : $title;
            $format  = $formatCol  ? strtolower(trim($row[$formatCol] ?? 'square')) : 'square';

            if (empty($title)) {
                continue;
            }

            // Normalise format value
            if (!in_array($format, ['square', 'portrait', 'landscape'], true)) {
                $format = 'square';
            }

            $rows[] = [
                'title'   => $title,
                'caption' => $caption ?: $title,
                'format'  => $format,
            ];
        }

        return $rows;
    }

    private function findColumn(array $columnMappings, string $semanticName): ?string
    {
        foreach ($columnMappings as $csvColumn => $mapped) {
            if ($mapped === $semanticName) {
                return $csvColumn;
            }
        }
        return null;
    }
}
