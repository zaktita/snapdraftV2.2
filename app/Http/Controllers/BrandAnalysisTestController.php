<?php

namespace App\Http\Controllers;

use App\Services\AI\BrandReferenceAnalyzer;
use App\Services\AI\OpenRouterImageTester;
use App\Services\AI\PromptGeneratorService;
use App\Services\CaptionAnalyzer;
use App\Services\IntelligentReferenceSelector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BrandAnalysisTestController extends Controller
{
    public function __construct(
        protected BrandReferenceAnalyzer $analyzer,
        protected PromptGeneratorService $promptGenerator,
        protected OpenRouterImageTester $imageTester,
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
     * Test caption matching with multi-model prompt generation.
     */
    public function testCaption(Request $request)
    {
        $validated = $request->validate([
            'caption' => 'required|string',
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'text_density' => 'required|in:light,standard,heavy',
            'analysis_json' => 'required|string',
        ]);

        $analysis = json_decode($validated['analysis_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($analysis)) {
            return back()->withErrors(['analysis_json' => 'Invalid analysis data']);
        }

        // Let each AI model analyze and select its own reference images
        try {
            $promptResults = $this->promptGenerator->generatePromptsMultiModel(
                $analysis,
                $validated['caption'],
                $validated['title'] ?? null,
                $validated['description'] ?? null,
                $validated['text_density'] ?? 'standard'
            );
        } catch (\Exception $e) {
            Log::error('Multi-model prompt generation failed', [
                'error' => $e->getMessage(),
                'caption' => $validated['caption'],
            ]);

            return back()->withErrors(['caption' => 'Prompt generation failed: ' . $e->getMessage()]);
        }

        return Inertia::render('test/brand-analysis', [
            'result' => $analysis,
            'prompt_results' => $promptResults,
            'test_caption' => $validated['caption'],
        ]);
    }

    /**
     * Generate images from the top 2 successful prompts using 2 image models each.
     * Returns 4 images total (2 prompts × 2 image models).
     */
    public function generateImages(Request $request)
    {
        set_time_limit(300);

        $validated = $request->validate([
            'prompt_results' => 'required|string',
            'selected_images' => 'required|string', // JSON array of selected image indices
            'analysis_json' => 'required|string',   // For reference image paths
        ]);

        // Decode request data
        $promptResults = json_decode($validated['prompt_results'], true);
        $selectedImages = json_decode($validated['selected_images'], true);
        $analysis = json_decode($validated['analysis_json'], true);

        if (!is_array($promptResults) || !is_array($selectedImages) || !is_array($analysis)) {
            return back()->withErrors(['data' => 'Invalid request data']);
        }

        // Get successful prompts
        $successfulPrompts = $promptResults['successful'] ?? [];
        if (empty($successfulPrompts)) {
            return back()->withErrors(['prompts' => 'No successful prompts to generate from']);
        }

        // Take first 2 successful prompts
        $selectedPrompts = array_slice($successfulPrompts, 0, 2);

        // Prepare reference images as base64
        $imageAnalysis = $analysis['image_analysis'] ?? [];
        $referenceImagesBase64 = [];

        foreach ($selectedImages as $imageIndex) {
            if (isset($imageAnalysis[$imageIndex])) {
                $imageMeta = $imageAnalysis[$imageIndex];
                $path = $imageMeta['path'] ?? null;
                
                if ($path && Storage::disk('public')->exists($path)) {
                    $mimeType = Storage::disk('public')->mimeType($path);
                    $content = Storage::disk('public')->get($path);
                    $base64 = base64_encode($content);
                    
                    $referenceImagesBase64[] = [
                        'data' => $base64,
                        'mimeType' => $mimeType,
                    ];
                }
            }
        }

        if (empty($referenceImagesBase64)) {
            return back()->withErrors(['images' => 'Could not load reference images']);
        }

        // Generate images for each of the 2 prompts
        $imageResults = [];

        try {
            foreach ($selectedPrompts as $promptResult) {
                $model = $promptResult['model'] ?? 'unknown';
                $prompt = $promptResult['prompt'] ?? '';

                if (empty($prompt)) {
                    $imageResults[$model] = [
                        'seedream' => [
                            'image_url' => null,
                            'duration_ms' => 0,
                            'error' => 'Empty prompt',
                        ],
                        'gpt5image' => [
                            'image_url' => null,
                            'duration_ms' => 0,
                            'error' => 'Empty prompt',
                        ],
                    ];
                    continue;
                }

                // Generate images using both image models
                $generationResults = $this->imageTester->generateForAllModels(
                    $referenceImagesBase64,
                    $prompt
                );

                // Map results to friendly names
                $imageResults[$model] = [];
                foreach ($generationResults as $imageModel => $result) {
                    $modelKey = match($imageModel) {
                        'bytedance-seed/seedream-4.5' => 'seedream',
                        'openai/gpt-5-image' => 'gpt5image',
                        default => str_replace(['/', '.'], '_', $imageModel),
                    };
                    
                    $imageResults[$model][$modelKey] = [
                        'image_url' => $result['image_url'] ?? null,
                        'duration_ms' => $result['duration_ms'] ?? 0,
                        'error' => $result['error'] ?? null,
                    ];
                }

                Log::info('BrandAnalysisTestController: Images generated', [
                    'prompt_model' => $model,
                    'image_models_count' => count($imageResults[$model]),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Image generation failed', [
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['generation' => 'Image generation failed: ' . $e->getMessage()]);
        }

        return Inertia::render('test/brand-analysis', [
            'result' => $analysis,
            'prompt_results' => $promptResults,
            'image_results' => $imageResults,
        ]);
    }
}
