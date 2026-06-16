<?php

namespace App\Http\Controllers;

use App\Jobs\QuickGenerateVisualJob;
use App\Models\BrandReference;
use App\Models\Project;
use App\Models\QuickGenerateSession;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class QuickGenerateController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUploadService,
    ) {}

    public function index()
    {
        return Inertia::render('quick-generate/index');
    }

    public function store(Request $request)
    {
        set_time_limit(600);
        Gate::authorize('create', Project::class);

        $validated = $request->validate([
            'reference_images' => 'required|array|min:1|max:10',
            'reference_images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
            'caption' => 'required|string|min:3|max:500',
            'format' => 'required|string|in:1:1,4:5,3:4,2:3,9:16,3:2,4:3,5:4,16:9',
        ]);

        try {
            $projectName = 'Quick Generate - '.now()->format('M d, Y g:i A');
            $project = Project::create([
                'user_id' => auth()->id(),
                'name' => $projectName,
                'title' => $projectName,
                'description' => 'Generated from caption: '.$validated['caption'],
                'format' => $validated['format'],
                'status' => 'processing',
                'settings' => [
                    'type' => 'quick_generate',
                    'caption' => $validated['caption'],
                ],
            ]);

            $refPaths = [];
            $order = 0;

            foreach ($request->file('reference_images') as $file) {
                $uploaded = $this->fileUploadService->uploadImage(
                    $file,
                    "projects/{$project->id}/references"
                );

                $refPaths[] = $uploaded['url'];

                BrandReference::create([
                    'project_id' => $project->id,
                    'url' => $uploaded['url'],
                    'thumbnail_url' => $uploaded['thumbnail_url'],
                    'order' => $order++,
                ]);
            }

            $project->update([
                'settings' => array_merge($project->settings ?? [], [
                    'ref_paths' => $refPaths,
                ]),
            ]);

            $session = QuickGenerateSession::create([
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'caption' => $validated['caption'],
                'format' => $validated['format'],
                'status' => 'pending',
            ]);

            try {
                QuickGenerateVisualJob::dispatchSync($session->id);
            } catch (\Throwable $e) {
                Log::error('QuickGenerateController: pipeline failed', [
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);

                return redirect()
                    ->route('quick-generate.show', $session->id)
                    ->with('error', 'Generation failed: '.$e->getMessage());
            }

            $session->refresh();

            if ($session->isCompleted()) {
                return redirect()->route('quick-generate.result', $session->id);
            }

            return redirect()->route('quick-generate.show', $session->id);
        } catch (\Exception $e) {
            Log::error('QuickGenerateController: failed', [
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to process: '.$e->getMessage()]);
        }
    }

    public function show(QuickGenerateSession $session)
    {
        Gate::authorize('view', $session->project);

        if ($session->isCompleted()) {
            return redirect()->route('quick-generate.result', $session->id);
        }

        return Inertia::render('quick-generate/processing', [
            'session' => [
                'id' => $session->id,
                'status' => $session->status,
                'caption' => $session->caption,
                'error_message' => $session->error_message,
            ],
            'error' => $session->error_message ?? session('error'),
            'debug' => $this->debugPayload($session),
        ]);
    }

    public function result(QuickGenerateSession $session)
    {
        Gate::authorize('view', $session->project);

        if (! $session->isCompleted()) {
            return redirect()->route('quick-generate.show', $session->id);
        }

        $project = $session->project->load('images', 'brandReferences');
        $generatedImage = $project->images()->latest()->first();

        return Inertia::render('quick-generate/result', [
            'session' => [
                'id' => $session->id,
                'caption' => $session->caption,
                'format' => $session->format,
                'extracted_title' => $session->extracted_title,
                'extracted_description' => $session->extracted_description,
                'cluster_key' => $session->cluster_key,
                'cluster_label' => $session->cluster_label,
            ],
            'project' => $project,
            'image' => $generatedImage,
            'references' => $project->brandReferences,
            'debug' => $this->debugPayload($session, $project),
        ]);
    }

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

    /**
     * @return array<string, mixed>
     */
    private function debugPayload(QuickGenerateSession $session, ?Project $project = null): array
    {
        $project ??= $session->project;

        return [
            'dna_summary' => $project?->dna_summary,
            'dna_extracted_at' => $project?->dna_extracted_at?->toIso8601String(),
            'cluster_key' => $session->cluster_key,
            'cluster_label' => $session->cluster_label,
            'prompt_json' => $session->prompt_json,
            'compiled_prompt' => $session->compiled_prompt,
            'selected_cluster_images' => $session->selected_cluster_images ?? [],
            'status' => $session->status,
            'error_message' => $session->error_message,
        ];
    }
}
