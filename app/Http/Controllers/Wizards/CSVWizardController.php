<?php

namespace App\Http\Controllers\Wizards;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Models\Project;
use App\Services\AI\BrandReferenceAnalyzer;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
            'reference_images' => 'nullable|array|max:10',
            'reference_images.*' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'product_images' => 'nullable|array|max:5',
            'product_images.*' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'text_accurate' => 'nullable|boolean', // Text accuracy toggle
        ]);

        // Create the project
        $project = Auth::user()->projects()->create([
            'name' => $validated['project_name'],
            'title' => $validated['project_name'], // title is required, name is the newer field
            'description' => 'Created via CSV Wizard',
            'settings' => [
                'wizard_type' => 'csv',
                'has_reference_images' => $request->hasFile('reference_images'),
                'has_product_images' => $request->hasFile('product_images'),
                'text_accurate' => $validated['text_accurate'] ?? false,
            ],
        ]);

        // Upload and store brand reference images (if provided)
        $referencePaths = [];
        if ($request->hasFile('reference_images')) {
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
            if (!empty($referencePaths)) {
                $brandAnalysis = $this->brandAnalyzer->analyze($referencePaths);
                $project->update([
                    'settings' => array_merge($project->settings ?? [], [
                        'brand_analysis' => $brandAnalysis,
                    ]),
                ]);
            }
        }

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

        // Queue AI processing job (sync in local for immediate feedback)
        if (app()->environment('local')) {
            \App\Jobs\GenerateBatchImagesJob::dispatchSync($project);
        } else {
            \App\Jobs\GenerateBatchImagesJob::dispatch($project);
        }

        // Redirect with flash data for optimistic UI
        return redirect()->route('projects.show', [
            'project' => $project->id,
            'justCreated' => true,
            'expectedImages' => count($csvData),
        ])->with('success', 'Project created! Your images will appear as they complete.')
          ->with('generating', true);
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
            'text_accurate' => 'nullable|boolean', // Text accuracy toggle
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
                'text_accurate' => $validated['text_accurate'] ?? ($project->settings['text_accurate'] ?? false),
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

        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (!$header) {
                    // Sanitize header names
                    $header = array_map(function ($value, int $index) {
                        $value = trim(strip_tags((string) $value));

                        // Strip UTF-8 BOM from first header cell if present
                        if ($index === 0) {
                            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
                        }

                        // Ensure every column has a non-empty key
                        return $value !== '' ? $value : 'column_' . ($index + 1);
                    }, $row, array_keys($row));

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
                        if (strlen($value) > $maxCellLength) {
                            $value = substr($value, 0, $maxCellLength);
                        }

                        // Remove HTML tags and encode special characters
                        $value = strip_tags($value);
                        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

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
