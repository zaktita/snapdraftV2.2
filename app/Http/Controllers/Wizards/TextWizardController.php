<?php

namespace App\Http\Controllers\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

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
        Gate::authorize('create', Project::class);

        $validated = $request->validate([
            'project_name' => 'required|string|max:255',
            'idea_description' => 'required|string|max:5000',
            'format' => 'required|string|in:square,portrait,landscape',
            'reference_images' => 'nullable|array|max:5',
            'reference_images.*' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'text_accurate' => 'nullable|boolean',
        ]);

        // Create the project
        $project = Auth::user()->projects()->create([
            'name' => $validated['project_name'],
            'title' => $validated['project_name'],
            'description' => $validated['idea_description'],
            'format' => $validated['format'],
            'settings' => [
                'wizard_type' => 'text',
                'format' => $validated['format'],
                'has_references' => $request->hasFile('reference_images'),
                'text_accurate' => $validated['text_accurate'] ?? false,
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

        // Queue AI processing job with the description as the prompt
        $prompt = $validated['idea_description'];
        $format = $validated['format'];
        $textAccurate = $validated['text_accurate'] ?? false;
        \App\Jobs\GenerateSingleImageJob::dispatch($project, $prompt, $format, $textAccurate);

        return redirect()->route('projects.show', $project->id)
            ->with('success', 'Project created! AI generation will begin shortly.');
    }
}
