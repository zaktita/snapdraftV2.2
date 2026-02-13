<?php

namespace App\Http\Controllers\Wizards;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Jobs\GenerateBatchImagesJob;
use App\Models\CsvWizardSession;
use App\Models\Project;
use App\Services\AI\BrandReferenceAnalyzer;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CSVWizardController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUploadService,
        protected BrandReferenceAnalyzer $brandAnalyzer
    ) {}

    /**
     * Process the CSV wizard form submission.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Project::class);

        $validated = $request->validate([
            'project_name' => 'required|string|max:255',
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // Max 10MB
            'reference_images' => 'required|array|min:3|max:10',
            'reference_images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
            'product_images' => 'nullable|array|max:5',
            'product_images.*' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
        ]);

        $project = null;

        try {
            // Create the project
            $project = Auth::user()->projects()->create([
                'name' => $validated['project_name'],
                'title' => $validated['project_name'], // title is required, name is the newer field
                'description' => 'Created via CSV Wizard',
                'settings' => [
                    'wizard_type' => 'csv',
                    'has_reference_images' => $request->hasFile('reference_images'),
                    'has_product_images' => $request->hasFile('product_images'),
                ],
            ]);

            // Upload and store brand reference images (required)
            $referencePaths = [];
            $referenceDir = 'projects/' . $project->id . '/references';
            foreach ($request->file('reference_images') as $index => $file) {
                $uploadResult = $this->fileUploadService->uploadImage($file, $referenceDir);

                $project->brandReferences()->create([
                    'url' => $uploadResult['url'],
                    'thumbnail_url' => $uploadResult['thumbnail_url'],
                    'order' => $index,
                ]);

                $referencePaths[] = $uploadResult['url'];
            }

            // Analyze brand DNA once and store in project settings
            $brandAnalysis = $this->brandAnalyzer->analyze($referencePaths);
            $project->update([
                'settings' => array_merge($project->settings ?? [], [
                    'brand_analysis' => $brandAnalysis,
                ]),
            ]);

            // Upload product images if provided
            if ($request->hasFile('product_images')) {
                $productDir = 'projects/' . $project->id . '/products';
                foreach ($request->file('product_images') as $index => $file) {
                    $uploadResult = $this->fileUploadService->uploadImage($file, $productDir);

                    // Store as regular images for now (or create separate product_images table)
                    $project->images()->create([
                        'url' => $uploadResult['url'],
                        'thumbnail_url' => $uploadResult['thumbnail_url'],
                        'order' => $index,
                        'prompt' => 'Product overlay image',
                        'metadata' => ['type' => 'product_overlay'],
                    ]);
                }
            }

            // Store CSV file
            $csvPath = $request->file('csv_file')->storeAs(
                'projects/' . $project->id . '/csv',
                'data.csv',
                'public'
            );

            // Parse CSV and store data in settings
            $csvData = $this->parseCSV($request->file('csv_file')->getRealPath());
            $project->update([
                'settings' => array_merge($project->settings ?? [], [
                    'csv_path' => $csvPath,
                    'csv_rows' => count($csvData),
                    'csv_data' => $csvData, // Store parsed data
                ]),
            ]);

            // Create wizard session (Quick Generate-style flow)
            $session = CsvWizardSession::create([
                'user_id' => Auth::id(),
                'project_id' => $project->id,
                'status' => 'pending',
                'total_jobs' => null,
            ]);

            // Queue AI processing job (sync in local for immediate feedback)
            if (app()->environment('local')) {
                GenerateBatchImagesJob::dispatchSync($project, $session->id);
            } else {
                GenerateBatchImagesJob::dispatch($project, $session->id);
            }

            return redirect()->route('projects.wizards.csv.session.show', $session->id);
        } catch (ValidationException $e) {
            if ($project) {
                $project->delete();
            }

            $firstMessage = collect($e->errors())->flatten()->first();

            return back()
                ->withErrors($e->errors())
                ->with('error', $firstMessage ?: 'Validation failed while processing CSV Wizard.')
                ->withInput();
        } catch (\Throwable $e) {
            if ($project) {
                $project->delete();
            }

            Log::error('CSVWizardController: Failed to process', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to process CSV Wizard: ' . $e->getMessage());
        }
    }

    /**
     * Show CSV wizard processing page with status polling.
     */
    public function showSession(CsvWizardSession $session)
    {
        Gate::authorize('view', $session->project);

        if ($session->isCompleted()) {
            return redirect()->route('projects.wizards.csv.session.result', $session->id);
        }

        if ($session->isFailed()) {
            return Inertia::render('projects/wizards/csv-processing', [
                'session' => [
                    'id' => $session->id,
                    'status' => $session->status,
                    'project_id' => $session->project_id,
                ],
                'urls' => [
                    'status' => route('projects.wizards.csv.session.status', $session->id),
                    'result' => route('projects.wizards.csv.session.result', $session->id),
                    'project' => route('projects.show', $session->project_id),
                ],
                'error' => $session->error_message,
            ]);
        }

        return Inertia::render('projects/wizards/csv-processing', [
            'session' => [
                'id' => $session->id,
                'status' => $session->status,
                'project_id' => $session->project_id,
            ],
            'urls' => [
                'status' => route('projects.wizards.csv.session.status', $session->id),
                'result' => route('projects.wizards.csv.session.result', $session->id),
                'project' => route('projects.show', $session->project_id),
            ],
        ]);
    }

    /**
     * CSV wizard result page.
     */
    public function resultSession(CsvWizardSession $session)
    {
        Gate::authorize('view', $session->project);

        if (!$session->isCompleted()) {
            return redirect()->route('projects.wizards.csv.session.show', $session->id);
        }

        $project = $session->project->load([
            'images',
            'generationHistory',
            'brandReferences' => fn ($q) => $q->orderBy('order'),
        ]);

        $batch = null;
        if ($session->batch_id) {
            $batch = Bus::findBatch($session->batch_id);
        }

        $summary = [
            'total' => $batch?->totalJobs ?? $session->total_jobs,
            'processed' => $batch?->processedJobs() ?? null,
            'pending' => $batch?->pendingJobs ?? null,
            'failed' => $batch?->failedJobs ?? null,
        ];

        $csvRows = (int) data_get($project->settings, 'csv_rows', 0);

        $historiesSince = $project->generationHistory
            ->where('created_at', '>=', $session->created_at)
            ->values();

        $autoFormatCount = $historiesSince
            ->filter(fn ($g) => data_get($g->parameters, 'wizard_type') === 'csv')
            ->filter(fn ($g) => data_get($g->parameters, 'format_source') === 'ai')
            ->count();

        $validationFailureCount = $historiesSince
            ->filter(fn ($g) => data_get($g->parameters, 'wizard_type') === 'csv')
            ->filter(fn ($g) => (bool) data_get($g->parameters, 'validation_error'))
            ->count();

        $failures = $project->generationHistory
            ->where('created_at', '>=', $session->created_at)
            ->where('status', 'failed')
            ->sortByDesc('id')
            ->take(25)
            ->values()
            ->map(function ($g) {
                return [
                    'id' => $g->id,
                    'csv_row_index' => data_get($g->parameters, 'csv_row_index'),
                    'title' => data_get($g->parameters, 'title'),
                    'caption' => data_get($g->parameters, 'caption'),
                    'description' => data_get($g->parameters, 'description'),
                    'format' => data_get($g->parameters, 'format'),
                    'error_message' => $g->error_message,
                ];
            });

        return Inertia::render('projects/wizards/csv-result', [
            'session' => [
                'id' => $session->id,
                'status' => $session->status,
                'project_id' => $session->project_id,
                'total_jobs' => $session->total_jobs,
            ],
            'project' => $project,
            'summary' => array_merge($summary, [
                'csv_rows' => $csvRows ?: null,
                'auto_format_count' => $autoFormatCount,
                'validation_failure_count' => $validationFailureCount,
            ]),
            'failures' => $failures,
            'urls' => [
                'project' => route('projects.show', $session->project_id),
            ],
        ]);
    }

    /**
     * Get CSV wizard session status (polling endpoint).
     */
    public function statusSession(CsvWizardSession $session)
    {
        Gate::authorize('view', $session->project);

        $batch = null;
        if ($session->batch_id) {
            $batch = Bus::findBatch($session->batch_id);
        }

        if (!$batch) {
            return response()->json([
                'status' => $session->status,
                'is_completed' => $session->isCompleted(),
                'is_failed' => $session->isFailed(),
                'error_message' => $session->error_message,
                'progress' => [
                    'total' => $session->total_jobs,
                    'processed' => 0,
                    'pending' => $session->total_jobs,
                    'failed' => 0,
                ],
            ]);
        }

        if ($batch->finished() && !$session->isCompleted() && !$session->isFailed()) {
            $session->markAsCompleted();
        }

        if ($batch->cancelled() && !$session->isFailed()) {
            $session->markAsFailed('Batch was cancelled');
        }

        return response()->json([
            'status' => $session->status,
            'is_completed' => $session->isCompleted(),
            'is_failed' => $session->isFailed(),
            'error_message' => $session->error_message,
            'progress' => [
                'total' => $batch->totalJobs,
                'processed' => $batch->processedJobs(),
                'pending' => $batch->pendingJobs,
                'failed' => $batch->failedJobs,
            ],
        ]);
    }

    /**
     * Upload/replace CSV for an existing project and trigger generation.
     * Reference images are assumed to already exist for the project.
     */
    public function storeForExistingProject(Request $request, string $projectId)
    {
        $project = Project::findOrFail($projectId);
        $this->authorize('update', $project);

        $validated = $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // Max 10MB
        ]);

        // Store CSV file
        $csvPath = $request->file('csv_file')->storeAs(
            'projects/' . $project->id . '/csv',
            'data.csv',
            'public'
        );

        // Parse CSV and store data in settings
        $csvData = $this->parseCSV($request->file('csv_file')->getRealPath());
        $project->update([
            'settings' => array_merge($project->settings ?? [], [
                'wizard_type' => 'csv',
                'csv_path' => $csvPath,
                'csv_rows' => count($csvData),
                'csv_data' => $csvData,
            ]),
        ]);

        // Queue AI processing job (sync in local for immediate feedback)
        if (app()->environment('local')) {
            \App\Jobs\GenerateBatchImagesJob::dispatchSync($project);
        } else {
            \App\Jobs\GenerateBatchImagesJob::dispatch($project);
        }

        return redirect()->route('projects.show', [
            'project' => $project->id,
            'expectedImages' => count($csvData),
        ])->with('success', 'Generation started! Images will appear as they complete.')
          ->with('generating', true);
    }

    /**
     * Parse CSV file and return structured data.
     * Sanitizes all cell content to prevent XSS attacks.
     */
    protected function parseCSV(string $filePath): array
    {
        $data = [];
        $header = null;
        $maxCellLength = 1000; // Maximum characters per cell
        $headerCount = 0;
        $required = ['title', 'caption', 'description', 'format'];

        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (!$header) {
                    // Sanitize + normalize header names to lowercase keys
                    $rawHeader = array_map(function ($value, int $index) {
                        $value = trim(strip_tags((string) $value));

                        // Strip UTF-8 BOM from first header cell if present
                        if ($index === 0) {
                            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
                        }

                        $value = mb_strtolower($value);
                        $value = preg_replace('/\s+/', '_', $value) ?? $value;
                        $value = preg_replace('/[^a-z0-9_]/', '', $value) ?? $value;

                        return $value !== '' ? $value : 'column_' . ($index + 1);
                    }, $row, array_keys($row));

                    // Ensure uniqueness of header keys
                    $counts = [];
                    $header = [];
                    foreach ($rawHeader as $key) {
                        $counts[$key] = ($counts[$key] ?? 0) + 1;
                        $header[] = $counts[$key] > 1 ? $key . '_' . $counts[$key] : $key;
                    }

                    // Enforce required schema
                    $missing = array_values(array_diff($required, $header));
                    if (!empty($missing)) {
                        fclose($handle);
                        throw ValidationException::withMessages([
                            'csv_file' => 'CSV must include columns: title, caption, description, format. Missing: ' . implode(', ', $missing),
                        ]);
                    }

                    $headerCount = count($header);
                } else {
                    if ($headerCount === 0) {
                        continue;
                    }

                    // Normalize row length to header length to avoid array_combine() ValueError
                    $rowCount = count($row);
                    if ($rowCount < $headerCount) {
                        $row = array_pad($row, $headerCount, '');
                    } elseif ($rowCount > $headerCount) {
                        $row = array_slice($row, 0, $headerCount);
                    }

                    // Combine header with row data
                    $rowData = array_combine($header, $row);

                    // Sanitize each cell value to prevent XSS
                    $rowData = array_map(function ($value) use ($maxCellLength) {
                        // Trim whitespace
                        $value = trim($value);

                        // Limit length
                        if (mb_strlen($value) > $maxCellLength) {
                            $value = mb_substr($value, 0, $maxCellLength);
                        }

                        // Remove HTML tags but preserve UTF-8 characters (emojis, accents)
                        $value = strip_tags($value);

                        return $value;
                    }, $rowData);

                    // Filter out empty rows (rows where all values are empty)
                    $hasData = false;
                    foreach ($rowData as $value) {
                        if (!empty($value)) {
                            $hasData = true;
                            break;
                        }
                    }

                    // Only add rows that have at least one non-empty value
                    if ($hasData) {
                        $data[] = $rowData;
                    }
                }
            }
            fclose($handle);
        }

        return $data;
    }
}
