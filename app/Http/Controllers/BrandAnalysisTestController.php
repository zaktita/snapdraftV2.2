<?php

namespace App\Http\Controllers;

use App\Services\AI\BrandReferenceAnalyzer;
use App\Services\CaptionAnalyzer;
use App\Services\IntelligentReferenceSelector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BrandAnalysisTestController extends Controller
{
    public function __construct(
        protected BrandReferenceAnalyzer $analyzer,
        protected CaptionAnalyzer $captionAnalyzer,
        protected IntelligentReferenceSelector $referenceSelector
    ) {
    }

    /**
     * Show the brand analysis test UI.
     */
    public function index()
    {
        return Inertia::render('test/brand-analysis', [
            'result' => null,
        ]);
    }

    /**
     * Analyze uploaded reference images and return clustering + element detection.
     */
    public function store(Request $request)
    {
        set_time_limit(300);

        $validated = $request->validate([
            'reference_images' => 'required|array|min:1|max:15',
            'reference_images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
        ]);

        $storedReferences = [];
        $paths = [];

        // Store uploaded images
        foreach ($request->file('reference_images') as $index => $file) {
            $storedPath = $file->store('brand-test', 'public');
            $paths[] = $storedPath;

            $storedReferences[$index] = [
                'path' => $storedPath,
                'url' => 'http://127.0.0.1:8000/storage/' . $storedPath,
                'name' => $file->getClientOriginalName(),
            ];
        }

        if (empty($paths)) {
            return back()->withErrors(['reference_images' => 'Could not read uploaded images']);
        }

        // Analyze brand DNA
        try {
            $analysis = $this->analyzer->analyze($paths);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Brand analysis failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['reference_images' => 'Analysis failed: ' . $e->getMessage()]);
        }

        // Annotate images with file info
        if (isset($analysis['image_analysis']) && is_array($analysis['image_analysis'])) {
            foreach ($analysis['image_analysis'] as &$imageMeta) {
                $index = $imageMeta['index'] ?? null;
                if ($index !== null && isset($storedReferences[$index])) {
                    $imageMeta['path'] = $storedReferences[$index]['path'];
                    $imageMeta['url'] = $storedReferences[$index]['url'];
                    $imageMeta['name'] = $storedReferences[$index]['name'];
                }
            }
            unset($imageMeta);
        }

        return Inertia::render('test/brand-analysis', [
            'result' => $analysis,
            'references' => array_values($storedReferences),
            'count' => count($paths),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Test caption matching against existing analysis.
     */
    public function testCaption(Request $request)
    {
        $validated = $request->validate([
            'caption' => 'required|string',
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'analysis_json' => 'required|string',
        ]);

        $analysis = json_decode($validated['analysis_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($analysis)) {
            return back()->withErrors(['analysis_json' => 'Invalid analysis data']);
        }

        // Analyze caption requirements
        $captionAnalysis = $this->captionAnalyzer->analyze(
            $validated['caption'],
            $validated['title'] ?? null,
            $validated['description'] ?? null,
            'square'
        );

        // Select best references
        $selection = $this->referenceSelector->selectBestReferences($analysis, $captionAnalysis, 5);

        return Inertia::render('test/brand-analysis', [
            'result' => $analysis,
            'caption_analysis' => $captionAnalysis,
            'selection_result' => $selection,
            'test_caption' => $validated['caption'],
        ]);
    }
}
