<?php

namespace App\Http\Controllers\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

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
        Gate::authorize('create', Project::class);

        $validated = $request->validate([
            'project_name' => 'required|string|max:255',
            'reference_images' => 'required|array|min:5|max:10',
            'reference_images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
            'content_description' => 'required|string|max:5000',
            'format' => 'nullable|string|in:1:1,2:3,3:2,3:4,4:3,4:5,5:4,2:1,16:9,21:9,9:16,4:1,square,portrait,landscape,instagram-post,instagram-story,facebook-post,facebook-ad,linkedin-post,linkedin-banner,twitter-post,youtube-thumbnail',
            'text_accurate' => 'nullable|boolean',
        ]);

        // Create the project
        $project = Auth::user()->projects()->create([
            'title' => $validated['project_name'],
            'description' => $validated['content_description'],
            'settings' => [
                'wizard_type' => 'images',
                'format' => $validated['format'] ?? '1:1',
                'text_accurate' => $validated['text_accurate'] ?? false,
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

        // Queue AI processing job with the description as the prompt
        $prompt = $validated['content_description'];
        $format = $validated['format'] ?? '1:1';
        $textAccurate = $validated['text_accurate'] ?? false;

        // Determine AI model based on text accuracy flag
        $aiModel = $textAccurate ? 'gemini-3-pro-image-preview' : 'gemini-2.5-flash-image';

        // Create generation history record (pending before job dispatch)
        $generation = $project->generationHistory()->create([
            'user_id' => Auth::id(),
            'prompt' => $prompt,
            'ai_model' => $aiModel,
            'status' => 'pending',
            'parameters' => [
                'format' => $format,
                'text_accurate' => $textAccurate,
                'wizard_type' => 'images',
            ],
        ]);

        if (app()->environment('local')) {
            \App\Jobs\GenerateSingleImageJob::dispatchSync($project, $prompt, $format, $textAccurate, $generation->id);
        } else {
            \App\Jobs\GenerateSingleImageJob::dispatch($project, $prompt, $format, $textAccurate, $generation->id);
        }

        return redirect()->route('projects.show', $project->id)
            ->with('success', 'Project created! AI generation will begin shortly.')
            ->with('generating', true);
    }
}
