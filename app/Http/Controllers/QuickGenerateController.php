<?php

namespace App\Http\Controllers;

use App\Jobs\QuickGenerateVisualJob;
use App\Models\BrandReference;
use App\Models\Project;
use App\Models\QuickGenerateSession;
use App\Services\AI\BrandReferenceAnalyzer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class QuickGenerateController extends Controller
{
    public function __construct(
        protected BrandReferenceAnalyzer $analyzer
    ) {
    }

    /**
     * Show the quick generate upload form.
     */
    public function index()
    {
        return Inertia::render('quick-generate/index');
    }

    /**
     * Store uploaded references, analyze brand DNA, and show caption form.
     */
    public function store(Request $request)
    {
        Log::info('QuickGenerateController: store called', [
            'reference_count' => $request->hasFile('reference_images') ? count($request->file('reference_images')) : 0,
            'format' => $request->input('format'),
        ]);

        set_time_limit(300);
        Gate::authorize('create', Project::class);

        $validated = $request->validate([
            'reference_images' => 'nullable|array|min:1|max:10',
            'reference_images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
            'caption' => 'required|string|min:3|max:500',
            'format' => 'required|string|in:1:1,4:5,3:4,2:3,9:16,3:2,4:3,5:4,16:9',
        ]);

        Log::info('QuickGenerateController: validation passed');

        try {
            // Create project
            $projectName = 'Quick Generate - ' . now()->format('M d, Y g:i A');
            $project = Project::create([
                'user_id' => auth()->id(),
                'name' => $projectName,
                'title' => $projectName,
                'description' => 'Generated from caption: ' . $validated['caption'],
                'format' => $validated['format'],
                'status' => 'processing',
                'settings' => [
                    'type' => 'quick_generate',
                    'caption' => $validated['caption'],
                ],
            ]);

            // Store reference images
            $paths = [];
            $storedReferences = [];
            
            foreach ($request->file('reference_images') as $index => $file) {
                $storedPath = $file->store('brand-references', 'public');
                $paths[] = $storedPath;

                BrandReference::create([
                    'project_id' => $project->id,
                    'url' => 'storage/' . $storedPath,
                    'thumbnail_url' => 'storage/' . $storedPath,
                    'order' => $index,
                ]);

                $storedReferences[] = [
                    'path' => $storedPath,
                    'url' => 'storage/' . $storedPath,
                    'name' => $file->getClientOriginalName(),
                ];
            }

            // Analyze brand DNA
            $analysis = $this->analyzer->analyze($paths);

            // Create session
            $session = QuickGenerateSession::create([
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'caption' => $validated['caption'],
                'format' => $validated['format'],
                'status' => 'pending',
                'brand_analysis_data' => $analysis,
            ]);

            // Dispatch job
            QuickGenerateVisualJob::dispatch($session);

            Log::info('QuickGenerateController: Session created', [
                'session_id' => $session->id,
                'project_id' => $project->id,
            ]);

            return redirect()->route('quick-generate.show', $session->id);

        } catch (\Exception $e) {
            Log::error('QuickGenerateController: Failed to process', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to process: ' . $e->getMessage()]);
        }
    }

    /**
     * Show processing page with status polling.
     */
    public function show(QuickGenerateSession $session)
    {
        Gate::authorize('view', $session->project);

        // If completed, redirect to result page
        if ($session->isCompleted()) {
            return redirect()->route('quick-generate.result', $session->id);
        }

        // If failed, show error
        if ($session->isFailed()) {
            return Inertia::render('quick-generate/processing', [
                'session' => $session,
                'error' => $session->error_message,
            ]);
        }

        // Show processing page
        return Inertia::render('quick-generate/processing', [
            'session' => [
                'id' => $session->id,
                'status' => $session->status,
                'caption' => $session->caption,
            ],
        ]);
    }

    /**
     * Show result page with generated visual.
     */
    public function result(QuickGenerateSession $session)
    {
        Gate::authorize('view', $session->project);

        if (!$session->isCompleted()) {
            return redirect()->route('quick-generate.show', $session->id);
        }

        $project = $session->project->load('images', 'brandReferences');
        $generatedImage = $project->images()->first();

        return Inertia::render('quick-generate/result', [
            'session' => [
                'id' => $session->id,
                'caption' => $session->caption,
                'extracted_title' => $session->extracted_title,
                'extracted_description' => $session->extracted_description,
                'selected_cluster_id' => $session->selected_cluster_id,
                'selected_image_indices' => $session->selected_image_indices,
            ],
            'project' => $project,
            'image' => $generatedImage,
            'references' => $project->brandReferences,
        ]);
    }

    /**
     * Get session status for polling.
     */
    public function status(QuickGenerateSession $session)
    {
        Gate::authorize('view', $session->project);

        return response()->json([
            'status' => $session->status,
            'is_completed' => $session->isCompleted(),
            'is_failed' => $session->isFailed(),
            'error_message' => $session->error_message,
        ]);
    }
}
