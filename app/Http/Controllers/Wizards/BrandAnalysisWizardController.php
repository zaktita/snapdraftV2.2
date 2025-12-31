<?php

namespace App\Http\Controllers\Wizards;

use App\Http\Controllers\Controller;
use App\Services\AI\BrandReferenceAnalyzer;
use App\Services\AI\GoogleGeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BrandAnalysisWizardController extends Controller
{
    public function __construct(
        protected BrandReferenceAnalyzer $analyzer,
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
        Gate::authorize('create', \App\Models\Project::class);

        $validated = $request->validate([
            'reference_images' => 'required|array|min:1|max:10',
            'reference_images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
            'caption' => 'required|string|max:400',
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

        // Step 1: Analyze brand DNA
        $analysis = $this->analyzer->analyze($paths);

        // Annotate analysis images with file info
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

        // Step 2: Auto-select best references (quality="good" + role="style_ref")
        $selectedPaths = $this->selectBestReferences($analysis, $storedReferences);

        if (empty($selectedPaths)) {
            // Fallback: use all references if none marked as good style_ref
            $selectedPaths = array_values(array_map(fn ($ref) => $ref['path'], $storedReferences));
        }

        // Step 3: Build generation prompt from DNA + caption
        $prompt = $this->analyzer->buildGenerationPrompt($analysis, $validated['caption']);

        // Step 4: Generate image
        $format = $validated['format'] ?? 'square';
        $generation = $this->generator->generateWithReferences(
            $prompt,
            array_slice($selectedPaths, 0, 5),
            [],
            $format,
            false
        );

        return Inertia::render('projects/wizards/brand-analysis', [
            'result' => $analysis,
            'generated_prompt' => $prompt,
            'generated_image' => $generation,
            'count' => count($paths),
            'timestamp' => now()->toIso8601String(),
            'references' => array_values($storedReferences),
            'selected_reference_paths' => $selectedPaths,
            'caption' => $validated['caption'],
            'format' => $format,
        ]);
    }

    /**
     * Regenerate image with new caption (reuses existing analysis).
     */
    public function generate(Request $request)
    {
        Gate::authorize('create', \App\Models\Project::class);

        $validated = $request->validate([
            'caption' => 'required|string|max:400',
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
