<?php

namespace App\Http\Controllers;

use App\Services\AI\AIServiceManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SimpleTextWizardController extends Controller
{
    public function index()
    {
        return Inertia::render('simple-wizard/index');
    }

    public function generate(Request $request, AIServiceManager $aiService)
    {
        $request->validate([
            'prompt' => 'required|string|max:5000',
        ]);

        try {
            Log::info('SimpleWizard: Starting synchronous generation', ['prompt' => $request->prompt]);

            // 1. Call AI Service Synchronously
            // We use the same service as the job, but wait for the result directly
            $result = $aiService->generateWithReferences(
                $request->prompt,
                [], // No references
                [], // No product images
                'square',
                true // Text accurate (Gemini 3 Pro)
            );

            // 2. Process Image
            if (!isset($result['image_data'])) {
                throw new \Exception('No image data received from AI service');
            }

            $imageData = base64_decode($result['image_data']);
            if ($imageData === false) {
                throw new \Exception('Failed to decode image data');
            }

            // Save to public storage
            $filename = 'generated/simple_' . Str::random(20) . '.png';
            Storage::disk('public')->put($filename, $imageData);

            Log::info('SimpleWizard: Generation successful', ['filename' => $filename]);

            // 3. Return to frontend with the image URL
            return Inertia::render('simple-wizard/index', [
                'generatedImage' => $filename,
                'prompt' => $request->prompt,
                'success' => 'Image generated successfully!',
            ]);

        } catch (\Exception $e) {
            Log::error('SimpleWizard: Generation failed', ['error' => $e->getMessage()]);
            
            return Inertia::render('simple-wizard/index', [
                'error' => $e->getMessage(),
                'prompt' => $request->prompt,
            ]);
        }
    }
}
