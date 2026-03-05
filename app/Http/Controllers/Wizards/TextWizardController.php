<?php

namespace App\Http\Controllers\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\AI\AIServiceManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TextWizardController extends Controller
{
    public function __construct(
        protected AIServiceManager $aiManager
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
            'format' => 'required|string|in:1:1,2:3,3:2,3:4,4:3,4:5,5:4,2:1,16:9,21:9,9:16,4:1,square,portrait,landscape,instagram-post,instagram-story,facebook-post,facebook-ad,linkedin-post,linkedin-banner,twitter-post,youtube-thumbnail',
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
            ],
        ]);

        // Get AI model name
        $aiModel = $this->aiManager->getActiveModelName();

        // Create generation history record (pending before job dispatch)
        $generation = $project->generationHistory()->create([
            'user_id' => Auth::id(),
            'prompt' => $validated['idea_description'],
            'ai_model' => $aiModel,
            'status' => 'pending',
            'parameters' => [
                'format' => $validated['format'],
                'wizard_type' => 'text',
            ],
        ]);

        $prompt = $validated['idea_description'];
        $format = $validated['format'];

        // Text wizard always generates directly from the prompt (no brand references)
        if (app()->environment('local')) {
            \App\Jobs\GenerateSingleImageJob::dispatchSync(
                project: $project,
                prompt: $prompt,
                format: $format,
                generationId: $generation->id,
                title: null,
                description: null,
                useSimplePrompt: false
            );
        } else {
            \App\Jobs\GenerateSingleImageJob::dispatch(
                project: $project,
                prompt: $prompt,
                format: $format,
                generationId: $generation->id,
                title: null,
                description: null,
                useSimplePrompt: false
            );
        }

        return redirect()->route('projects.show', $project->id)
            ->with('success', 'Project created! Your image will appear when complete.')
            ->with('generating', true);
    }
}
