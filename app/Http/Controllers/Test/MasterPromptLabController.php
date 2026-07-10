<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Test\MasterPromptLabService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MasterPromptLabController extends Controller
{
    public function __construct(
        protected MasterPromptLabService $lab,
    ) {}

    public function index(): Response
    {
        Gate::authorize('create', Project::class);

        return Inertia::render('test/master-prompt', [
            'aspect_ratios' => MasterPromptLabService::ASPECT_RATIOS,
        ]);
    }

    public function build(Request $request): JsonResponse
    {
        set_time_limit(0);
        Gate::authorize('create', Project::class);

        $validated = $request->validate([
            'reference_images' => 'required|array|size:3',
            'reference_images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
            'caption' => 'required|string|max:4000',
            'aspect_ratio' => 'nullable|string|in:'.implode(',', MasterPromptLabService::ASPECT_RATIOS),
        ]);

        try {
            $result = $this->lab->buildMasterPrompt(
                $request->file('reference_images'),
                $validated['caption'],
                $validated['aspect_ratio'] ?? '1:1',
            );

            return response()->json([
                'ok' => true,
                ...$result,
            ]);
        } catch (Throwable $e) {
            Log::error('MasterPromptLabController::build failed', [
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

        foreach ($validated['reference_paths'] as $path) {
            if (str_contains($path, '..') || ! str_starts_with($path, 'master-prompt-lab/')) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Invalid reference path.',
                ], 422);
            }
        }

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
            Log::error('MasterPromptLabController::generate failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
