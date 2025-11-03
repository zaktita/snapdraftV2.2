<?php

namespace App\Http\Controllers\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TextWizardController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUploadService
    ) {}

    /**
     * Process the Text wizard form submission.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Project::class);

        $validated = $request->validate([
            'project_name' => 'required|string|max:255',
            'idea_description' => 'required|string|max:5000',
            'format' => 'required|string|in:square,portrait,landscape',
            'reference_images' => 'nullable|array|max:5',
            'reference_images.*' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
        ]);

        // Create the project
        $project = Auth::user()->projects()->create([
            'title' => $validated['project_name'],
            'description' => $validated['idea_description'],
            'settings' => [
                'wizard_type' => 'text',
                'format' => $validated['format'],
                'has_references' => $request->hasFile('reference_images'),
            ],
        ]);

        // Upload and store optional reference images
        if ($request->hasFile('reference_images')) {
            $referenceDir = 'projects/' . $project->id . '/references';
            foreach ($request->file('reference_images') as $index => $file) {
                $uploadResult = $this->fileUploadService->uploadImage($file, $referenceDir);
                
                $project->brandReferences()->create([
                    'url' => $uploadResult['url'],
                    'thumbnail_url' => $uploadResult['thumbnail_url'],
                    'order' => $index,
                ]);
            }
        }

        // Queue AI processing job
        if ($request->hasFile('reference_images')) {
            // With references: analyze style first, then generate
            \App\Jobs\AnalyzeBrandStyleJob::dispatch($project);
        } else {
            // No references: generate directly
            \App\Jobs\GenerateSingleImageJob::dispatch($project);
        }

        return redirect()->route('projects.show', $project->id)
            ->with('success', 'Project created! AI generation will begin shortly.');
    }
}
