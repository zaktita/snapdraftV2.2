<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Test\ClusteredMasterPromptLabService;
use App\Services\Test\MasterPromptLabService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ClusteredMasterPromptLabController extends Controller
{
    public function __construct(
        protected ClusteredMasterPromptLabService $lab,
    ) {}

    public function index(): Response
    {
        Gate::authorize('create', Project::class);

        return Inertia::render('test/clustered-master-prompt', [
            'aspect_ratios' => MasterPromptLabService::ASPECT_RATIOS,
        ]);
    }

    public function cluster(Request $request): JsonResponse
    {
        set_time_limit(0);
        Gate::authorize('create', Project::class);

        $request->validate([
            'reference_images' => 'required|array|min:3|max:20',
            'reference_images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
        ]);

        try {
            $result = $this->lab->clusterUploads($request->file('reference_images'));

            return response()->json([
                'ok' => true,
                ...$result,
            ]);
        } catch (Throwable $e) {
            Log::error('ClusteredMasterPromptLabController::cluster failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function match(Request $request): JsonResponse
    {
        set_time_limit(0);
        Gate::authorize('create', Project::class);

        $validated = $request->validate([
            'caption' => 'required|string|max:4000',
            'clusters' => 'required|array|min:1',
            'clusters.*.name' => 'required|string',
            'clusters.*.imageIndices' => 'required|array|min:1',
            'clusters.*.imageIndices.*' => 'integer',
            'clusters.*.tags' => 'nullable|array',
            'clusters.*.reason' => 'nullable|string',
        ]);

        try {
            $match = $this->lab->matchCaption(
                $validated['clusters'],
                $validated['caption'],
            );

            return response()->json([
                'ok' => true,
                ...$match,
            ]);
        } catch (Throwable $e) {
            Log::error('ClusteredMasterPromptLabController::match failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function matchBatch(Request $request): JsonResponse
    {
        set_time_limit(0);
        Gate::authorize('create', Project::class);

        $validated = $request->validate([
            'captions' => 'required|array|min:1|max:50',
            'captions.*' => 'required|string|max:4000',
            'clusters' => 'required|array|min:1',
            'clusters.*.name' => 'required|string',
            'clusters.*.imageIndices' => 'required|array|min:1',
            'clusters.*.imageIndices.*' => 'integer',
            'clusters.*.tags' => 'nullable|array',
            'clusters.*.reason' => 'nullable|string',
        ]);

        try {
            $rows = $this->lab->matchCaptions(
                $validated['clusters'],
                $validated['captions'],
            );

            return response()->json([
                'ok' => true,
                'rows' => $rows,
            ]);
        } catch (Throwable $e) {
            Log::error('ClusteredMasterPromptLabController::matchBatch failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function runRow(Request $request): JsonResponse
    {
        set_time_limit(0);
        Gate::authorize('create', Project::class);

        $validated = $request->validate([
            'reference_paths' => 'required|array|size:3',
            'reference_paths.*' => 'required|string',
            'caption' => 'required|string|max:4000',
            'aspect_ratio' => 'nullable|string|in:'.implode(',', MasterPromptLabService::ASPECT_RATIOS),
        ]);

        try {
            $result = $this->lab->runRow(
                $validated['reference_paths'],
                $validated['caption'],
                $validated['aspect_ratio'] ?? '1:1',
            );

            return response()->json([
                'ok' => true,
                ...$result,
            ]);
        } catch (Throwable $e) {
            Log::error('ClusteredMasterPromptLabController::runRow failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function build(Request $request): JsonResponse
    {
        set_time_limit(0);
        Gate::authorize('create', Project::class);

        $validated = $request->validate([
            'reference_paths' => 'required|array|size:3',
            'reference_paths.*' => 'required|string',
            'caption' => 'required|string|max:4000',
            'aspect_ratio' => 'nullable|string|in:'.implode(',', MasterPromptLabService::ASPECT_RATIOS),
        ]);

        try {
            $result = $this->lab->buildMasterPrompt(
                $validated['reference_paths'],
                $validated['caption'],
                $validated['aspect_ratio'] ?? '1:1',
            );

            return response()->json([
                'ok' => true,
                ...$result,
            ]);
        } catch (Throwable $e) {
            Log::error('ClusteredMasterPromptLabController::build failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function generate(Request $request): JsonResponse
    {
        set_time_limit(0);
        Gate::authorize('create', Project::class);

        $validated = $request->validate([
            'reference_paths' => 'required|array|size:3',
            'reference_paths.*' => 'required|string',
            'master_prompt' => 'required|string|max:20000',
            'aspect_ratio' => 'nullable|string|in:'.implode(',', MasterPromptLabService::ASPECT_RATIOS),
        ]);

        try {
            $result = $this->lab->generateImage(
                $validated['reference_paths'],
                $validated['master_prompt'],
                $validated['aspect_ratio'] ?? '1:1',
            );

            return response()->json([
                'ok' => true,
                ...$result,
            ]);
        } catch (Throwable $e) {
            Log::error('ClusteredMasterPromptLabController::generate failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
