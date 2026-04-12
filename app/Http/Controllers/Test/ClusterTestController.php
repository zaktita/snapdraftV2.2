<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Services\AI\BrandReferenceAnalyzer;
use App\Services\AI\GoogleGeminiService;
use App\Services\AI\PromptGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ClusterTestController extends Controller
{
    public function __construct(
        protected BrandReferenceAnalyzer $analyzer,
        protected PromptGeneratorService $promptGenerator,
        protected GoogleGeminiService $generator,
    ) {}

    public function index()
    {
        Gate::authorize('create', \App\Models\Project::class);

        return Inertia::render('test/cluster-generation', [
            'analysis'          => null,
            'references'        => [],
            'generation_result' => null,
        ]);
    }

    /**
     * Step 1: Upload images and run brand analysis (clustering + element detection).
     */
    public function analyze(Request $request)
    {
        set_time_limit(0); // Analysis sends up to 10 images inline; no fixed cap needed
        Gate::authorize('create', \App\Models\Project::class);

        $request->validate([
            'reference_images'   => 'required|array|min:2|max:10',
            'reference_images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
        ]);

        // Store uploaded images and record their index order
        $storedRefs = [];
        foreach ($request->file('reference_images') as $index => $file) {
            $path = $file->store('cluster-test', 'public');
            $storedRefs[$index] = [
                'path'  => $path,
                'url'   => Storage::url($path),
                'name'  => $file->getClientOriginalName(),
                'index' => $index,
            ];
        }

        // Run brand analysis — clustering + element detection — in a single Gemini call
        $paths    = array_column($storedRefs, 'path');
        $analysis = $this->analyzer->analyze($paths);

        // Annotate image_analysis entries with display URLs and original filenames
        $imageKey = isset($analysis['image_analysis']) ? 'image_analysis' : 'images';
        if (!empty($analysis[$imageKey])) {
            foreach ($analysis[$imageKey] as &$img) {
                $idx = $img['index'] ?? null;
                if ($idx !== null && isset($storedRefs[$idx])) {
                    $img['url']  = $storedRefs[$idx]['url'];
                    $img['name'] = $storedRefs[$idx]['name'];
                }
            }
            unset($img);
        }

        Log::info('ClusterTestController: analysis complete', [
            'image_count'    => count($storedRefs),
            'clusters_found' => count($analysis['style_clusters'] ?? []),
        ]);

        return Inertia::render('test/cluster-generation', [
            'analysis'          => $analysis,
            'references'        => array_values($storedRefs),
            'generation_result' => null,
        ]);
    }

    /**
     * Step 2: AI selects best cluster + reference images, then generates the image.
     */
    public function generate(Request $request)
    {
        set_time_limit(0);
        Gate::authorize('create', \App\Models\Project::class);

        $validated = $request->validate([
            'analysis_json'     => 'required|string',
            'reference_paths'   => 'required|array|min:1|max:10',
            'reference_paths.*' => 'required|string',
            'caption'           => 'required|string|max:2000',
            'format'            => 'nullable|string|in:square,portrait,landscape',
        ]);

        $analysis = json_decode($validated['analysis_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($analysis)) {
            return back()->withErrors(['analysis_json' => 'Invalid analysis JSON']);
        }

        $caption  = $validated['caption'];
        $format   = $validated['format'] ?? 'square';
        $allPaths = $validated['reference_paths'];

        // A: PromptGeneratorService asks Gemini to pick the best cluster + 1–3 image indices
        $promptResult    = $this->promptGenerator->generateSimplePrompt($analysis, $caption);
        $selectedIndices = array_map('intval', $promptResult['selected_images'] ?? []);

        $selectedPaths = [];
        foreach ($selectedIndices as $idx) {
            if (isset($allPaths[$idx])) {
                $selectedPaths[] = $allPaths[$idx];
            }
        }

        // Fallback: use first 3 images if AI selection returned nothing
        if (empty($selectedPaths)) {
            $selectedPaths   = array_slice($allPaths, 0, 3);
            $selectedIndices = range(0, count($selectedPaths) - 1);
        }

        // B: Build the full brand-locked generation prompt from brand DNA + extracted text
        $generationPrompt = $this->analyzer->buildGenerationPrompt(
            $analysis,
            $promptResult['simple_prompt']
        );

        // C: Generate the image with selected reference images
        $startTime  = microtime(true);
        $generation = $this->generator->generateWithReferences(
            $generationPrompt,
            $selectedPaths,
            [],
            $format
        );
        $generationMs = round((microtime(true) - $startTime) * 1000);

        // Gather selected image metadata for display
        $imageKey        = isset($analysis['image_analysis']) ? 'image_analysis' : 'images';
        $allImageMeta    = $analysis[$imageKey] ?? [];
        $selectedImgMeta = array_values(array_filter(
            $allImageMeta,
            fn($img) => in_array($img['index'] ?? -1, $selectedIndices, true)
        ));

        // Find the selected cluster object
        $selectedCluster = null;
        foreach ($analysis['style_clusters'] ?? [] as $cluster) {
            if (($cluster['cluster_id'] ?? null) == $promptResult['cluster_id']) {
                $selectedCluster = $cluster;
                break;
            }
        }

        // Reconstruct references array from annotated image_analysis (preserves URLs/names)
        $references = array_values(array_map(fn($img) => [
            'path'  => $allPaths[$img['index'] ?? 0] ?? '',
            'url'   => $img['url'] ?? Storage::url($allPaths[$img['index'] ?? 0] ?? ''),
            'name'  => $img['name'] ?? basename($allPaths[$img['index'] ?? 0] ?? ''),
            'index' => $img['index'] ?? 0,
        ], $allImageMeta));

        Log::info('ClusterTestController: generation complete', [
            'cluster_id'      => $promptResult['cluster_id'],
            'selected_images' => $selectedIndices,
            'model_used'      => $promptResult['model_used'],
            'generation_ms'   => $generationMs,
        ]);

        return Inertia::render('test/cluster-generation', [
            'analysis'   => $analysis,
            'references' => $references,
            'generation_result' => [
                'cluster_id'          => $promptResult['cluster_id'],
                'selected_cluster'    => $selectedCluster,
                'selected_indices'    => $selectedIndices,
                'selected_image_meta' => $selectedImgMeta,
                'simple_prompt'       => $promptResult['simple_prompt'],
                'generation_prompt'   => $generationPrompt,
                'model_used'          => $promptResult['model_used'],
                'image'               => $generation,
                'generation_ms'       => $generationMs,
                'format'              => $format,
                'caption'             => $caption,
            ],
        ]);
    }
}
