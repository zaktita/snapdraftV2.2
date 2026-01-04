<?php

namespace App\Http\Controllers\Wizards;

use App\Http\Controllers\Controller;
use App\Services\AI\BrandReferenceAnalyzer;
use App\Services\AI\FalBrandAnalyzer;
use App\Services\AI\GoogleGeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BrandAnalysisWizardController extends Controller
{
    public function __construct(
        protected BrandReferenceAnalyzer $analyzer,
        protected FalBrandAnalyzer $gpt52Analyzer,
        protected GoogleGeminiService $generator
    ) {
    }

    /**
     * Show the brand analysis test wizard.
     */
    public function index()
    {
        Gate::authorize('create', \App\Models\Project::class);

        return Inertia::render('projects/wizards/brand-analysis', [
            'result' => null,
        ]);
    }

    /**
     * Analyze uploaded reference images + generate image from caption in one flow.
     */
    public function store(Request $request)
    {
        set_time_limit(300);

        Gate::authorize('create', \App\Models\Project::class);

        $validated = $request->validate([
            'reference_images' => 'required|array|min:1|max:10',
            'reference_images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
            'caption' => 'required|string',
            'format' => 'nullable|string|in:square,portrait,landscape',
        ]);

        $storedReferences = [];
        $paths = [];

        foreach ($request->file('reference_images') as $index => $file) {
            $storedPath = $file->store('brand-wizard', 'public');
            $paths[] = $storedPath;

            $storedReferences[$index] = [
                'path' => $storedPath,
                'url' => Storage::disk('public')->url($storedPath),
                'name' => $file->getClientOriginalName(),
            ];
        }

        if (empty($paths)) {
            return back()->withErrors(['reference_images' => 'Could not read uploaded images']);
        }

        // Step 1: Analyze brand DNA with both models
        $geminiAnalysis = $this->analyzer->analyze($paths);
        
        try {
            $gpt52Analysis = $this->gpt52Analyzer->analyze($paths);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('GPT-5.2 analysis failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Fallback: use Gemini analysis for GPT-5.2 slot
            $gpt52Analysis = $geminiAnalysis;
        }

        // Annotate both analyses with file info
        foreach ([$geminiAnalysis, $gpt52Analysis] as &$analysis) {
            if (isset($analysis['images']) && is_array($analysis['images'])) {
                foreach ($analysis['images'] as &$imageMeta) {
                    $index = $imageMeta['index'] ?? null;
                    if ($index !== null && isset($storedReferences[$index])) {
                        $imageMeta['path'] = $storedReferences[$index]['path'];
                        $imageMeta['url'] = $storedReferences[$index]['url'];
                        $imageMeta['name'] = $storedReferences[$index]['name'];
                    }
                }
                unset($imageMeta);
            }
        }
        unset($analysis);

        // Step 2: Auto-select best references from BOTH analyses
        $geminiSelectedPaths = $this->selectBestReferences($geminiAnalysis, $storedReferences);
        if (empty($geminiSelectedPaths)) {
            $geminiSelectedPaths = array_values(array_map(fn ($ref) => $ref['path'], $storedReferences));
        }

        $falSelectedPaths = $this->selectBestReferences($gpt52Analysis, $storedReferences);
        if (empty($falSelectedPaths)) {
            $falSelectedPaths = array_values(array_map(fn ($ref) => $ref['path'], $storedReferences));
        }

        // Step 3: Build generation prompts from BOTH analyses
        $geminiPrompt = $this->analyzer->buildGenerationPrompt($geminiAnalysis, $validated['caption']);
        $falPrompt = $this->gpt52Analyzer->buildGenerationPrompt($gpt52Analysis, $validated['caption']);

        // Step 4: Generate images with BOTH prompts using Gemini Flash Image
        $format = $validated['format'] ?? 'square';
        
        $geminiGeneration = $this->generator->generateWithReferences(
            $geminiPrompt,
            array_slice($geminiSelectedPaths, 0, 5),
            [],
            $format,
            false
        );

        $falGeneration = $this->generator->generateWithReferences(
            $falPrompt,
            array_slice($falSelectedPaths, 0, 5),
            [],
            $format,
            false
        );

        return Inertia::render('projects/wizards/brand-analysis', [
            'result' => $geminiAnalysis,
            'fal_result' => $gpt52Analysis,
            'gemini_prompt' => $geminiPrompt,
            'fal_prompt' => $falPrompt,
            'gemini_selected_paths' => $geminiSelectedPaths,
            'fal_selected_paths' => $falSelectedPaths,
            'gemini_generation' => $geminiGeneration,
            'fal_generation' => $falGeneration,
            'count' => count($paths),
            'timestamp' => now()->toIso8601String(),
            'references' => array_values($storedReferences),
            'caption' => $validated['caption'],
            'format' => $format,
        ]);
    }

    /**
     * Regenerate image with new caption (reuses existing analysis).
     */
    public function generate(Request $request)
    {
        set_time_limit(300);

        Gate::authorize('create', \App\Models\Project::class);

        $validated = $request->validate([
            'caption' => 'required|string',
            'analysis_json' => 'required|string',
            'reference_paths' => 'required|array|min:1|max:5',
            'reference_paths.*' => 'required|string',
            'format' => 'nullable|string|in:square,portrait,landscape',
        ]);

        $analysis = json_decode($validated['analysis_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($analysis)) {
            return back()->withErrors(['analysis_json' => 'Could not read brand analysis JSON'])->withInput();
        }

        $referencePaths = collect($validated['reference_paths'] ?? [])
            ->filter(function ($path) {
                return Storage::disk('public')->exists($path) || file_exists($path);
            })
            ->take(5)
            ->values()
            ->all();

        if (empty($referencePaths)) {
            return back()->withErrors(['reference_paths' => 'No valid reference images available'])->withInput();
        }

        $prompt = $this->analyzer->buildGenerationPrompt($analysis, $validated['caption']);

        $format = $validated['format'] ?? 'square';
        $generation = $this->generator->generateWithReferences(
            $prompt,
            $referencePaths,
            [],
            $format,
            false
        );

        $references = collect($referencePaths)->map(function ($path) {
            return [
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'name' => basename($path),
            ];
        })->all();

        return Inertia::render('projects/wizards/brand-analysis', [
            'result' => $analysis,
            'generated_prompt' => $prompt,
            'generated_image' => $generation,
            'count' => count($referencePaths),
            'timestamp' => now()->toIso8601String(),
            'references' => $references,
            'selected_reference_paths' => $referencePaths,
            'caption' => $validated['caption'],
            'format' => $format,
        ]);
    }

    /**
     * Select best references for generation based on AI analysis.
     * Prefers images marked as quality="good" and role="style_ref".
     */
    protected function selectBestReferences(array $analysis, array $storedReferences): array
    {
        $images = $analysis['images'] ?? [];
        $selected = [];

        // First pass: collect good style references
        foreach ($images as $img) {
            $index = $img['index'] ?? null;
            $quality = $img['quality'] ?? null;
            $role = $img['role'] ?? null;

            if ($index !== null && isset($storedReferences[$index])) {
                if ($quality === 'good' && $role === 'style_ref') {
                    $selected[] = $storedReferences[$index]['path'];
                }
            }
        }

        // If we have enough, return them
        if (count($selected) >= 1) {
            return array_slice($selected, 0, 5);
        }

        // Second pass: collect any good quality images
        foreach ($images as $img) {
            $index = $img['index'] ?? null;
            $quality = $img['quality'] ?? null;

            if ($index !== null && isset($storedReferences[$index]) && $quality === 'good') {
                $selected[] = $storedReferences[$index]['path'];
            }
        }

        return array_slice(array_unique($selected), 0, 5);
    }
}
