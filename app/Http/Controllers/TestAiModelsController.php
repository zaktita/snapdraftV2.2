<?php

namespace App\Http\Controllers;

use App\Services\AI\OpenRouterImageTester;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

class TestAiModelsController extends Controller
{
    public function __construct(
        protected OpenRouterImageTester $tester,
    ) {}

    /**
     * Show test UI page.
     */
    public function index()
    {
        return Inertia::render('test-ai-models', [
            'models' => [
                'bytedance-seed/seedream-4.5',
                'black-forest-labs/flux.2-max',
                'sourceful/riverflow-v2-max-preview',
                'black-forest-labs/flux.2-flex',
                'google/gemini-3-pro-image-preview',
                'openai/gpt-5-image-mini',
                'openai/gpt-5-image',
            ],
        ]);
    }

    /**
     * Generate images for all models.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'reference_images' => 'required|array|min:1',
            'reference_images.*' => 'required|string',
            'prompt' => 'required|string|min:5',
        ]);

        try {
            // Parse reference images (they come as data URLs or base64 strings)
            $referenceImages = [];
            foreach ($request->input('reference_images') as $imageData) {
                $referenceImages[] = $this->parseImageData($imageData);
            }

            // Generate for all models
            $results = $this->tester->generateForAllModels(
                $referenceImages,
                $request->input('prompt')
            );

            return response()->json([
                'success' => true,
                'results' => $results,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse base64 or data URL image.
     */
    protected function parseImageData(string $imageData): array
    {
        // Handle data URL format: data:image/png;base64,...
        if (str_starts_with($imageData, 'data:')) {
            $matches = [];
            if (preg_match('/^data:([^;]+);base64,(.+)$/', $imageData, $matches)) {
                return [
                    'mimeType' => $matches[1],
                    'data' => $matches[2],
                ];
            }
        }

        // Assume it's raw base64
        return [
            'mimeType' => 'image/png',
            'data' => $imageData,
        ];
    }
}
