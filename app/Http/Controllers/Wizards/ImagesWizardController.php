<?php

namespace App\Http\Controllers\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImagesWizardController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUploadService
    ) {}

    /**
     * Process the Images wizard form submission.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Project::class);

        $validated = $request->validate([
            'project_name' => 'required|string|max:255',
            'reference_images' => 'required|array|min:5|max:10',
            'reference_images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
            'content_description' => 'required|string|max:5000',
            'format' => 'nullable|string|in:square,portrait,landscape',
        ]);

        // Create the project
        $project = Auth::user()->projects()->create([
            'title' => $validated['project_name'],
            'description' => $validated['content_description'],
            'settings' => [
                'wizard_type' => 'images',
                'format' => $validated['format'] ?? 'square',
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

        // Queue AI processing job
        \App\Jobs\AnalyzeBrandStyleJob::dispatch($project);

        return redirect()->route('projects.show', $project->id)
            ->with('success', 'Project created! AI generation will begin shortly.');
    }
}
