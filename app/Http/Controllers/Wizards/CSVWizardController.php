<?php

namespace App\Http\Controllers\Wizards;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Models\Project;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CSVWizardController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUploadService
    ) {}

    /**
     * Process the CSV wizard form submission.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Project::class);

        $validated = $request->validate([
            'project_name' => 'required|string|max:255',
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // Max 10MB
            'reference_images' => 'required|array|min:5|max:10',
            'reference_images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
            'product_images' => 'nullable|array|max:5',
            'product_images.*' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
        ]);

        // Create the project
        $project = Auth::user()->projects()->create([
            'title' => $validated['project_name'],
            'description' => 'Created via CSV Wizard',
            'settings' => [
                'wizard_type' => 'csv',
                'has_product_images' => $request->hasFile('product_images'),
            ],
        ]);

        // Upload and store brand reference images
        $referenceDir = 'projects/' . $project->id . '/references';
        foreach ($request->file('reference_images') as $index => $file) {
            $uploadResult = $this->fileUploadService->uploadImage($file, $referenceDir);
            
            $project->brandReferences()->create([
                'url' => $uploadResult['url'],
                'thumbnail_url' => $uploadResult['thumbnail_url'],
                'order' => $index,
            ]);
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

        // Queue AI processing job
        \App\Jobs\GenerateBatchImagesJob::dispatch($project);

        return redirect()->route('projects.show', $project->id)
            ->with('success', 'Project created! AI generation will begin shortly.');
    }

    /**
     * Parse CSV file and return structured data.
     */
    protected function parseCSV(string $filePath): array
    {
        $data = [];
        $header = null;

        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (!$header) {
                    $header = $row;
                } else {
                    $data[] = array_combine($header, $row);
                }
            }
            fclose($handle);
        }

        return $data;
    }
}
