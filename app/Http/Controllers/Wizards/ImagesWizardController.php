<?php

namespace App\Http\Controllers\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\AI\BrandReferenceAnalyzer;
use App\Services\AI\AIServiceManager;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ImagesWizardController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUploadService,
        protected BrandReferenceAnalyzer $brandAnalyzer,
        protected AIServiceManager $aiManager
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
        ]);

        // Create the project
        $project = Auth::user()->projects()->create([
            'title' => $validated['project_name'],
            'description' => $validated['content_description'],
            'settings' => [
                'wizard_type' => 'images',
                'format' => $validated['format'] ?? '1:1',
            ],
        ]);

        // Upload and store brand reference images
        $referenceDir = 'projects/' . $project->id . '/references';
        $referencePaths = [];
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

        // Queue AI processing job with the description as the prompt
        $prompt = $validated['content_description'];
        $format = $validated['format'] ?? '1:1';

        // Get AI model name
        $aiModel = $this->aiManager->getActiveModelName();

        // Create generation history record (pending before job dispatch)
        $generation = $project->generationHistory()->create([
            'user_id' => Auth::id(),
            'prompt' => $prompt,
            'ai_model' => $aiModel,
            'status' => 'pending',
            'parameters' => [
                'format' => $format,
                'wizard_type' => 'images',
            ],
        ]);

        if (app()->environment('local')) {
            \App\Jobs\GenerateSingleImageJob::dispatchSync(
                project: $project, 
                prompt: $prompt, 
                format: $format, 
                generationId: $generation->id,
                title: null,  // AI will extract from prompt
                description: null,  // AI will extract from prompt
                useSimplePrompt: true
            );
        } else {
            \App\Jobs\GenerateSingleImageJob::dispatch(
                project: $project, 
                prompt: $prompt, 
                format: $format, 
                generationId: $generation->id,
                title: null,  // AI will extract from prompt
                description: null,  // AI will extract from prompt
                useSimplePrompt: true
            );
        }

        return redirect()->route('projects.show', $project->id)
            ->with('success', 'Project created! Your image will appear when complete.')
            ->with('generating', true);
    }
}
