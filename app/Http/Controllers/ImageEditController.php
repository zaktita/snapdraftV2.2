<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ImageEditController extends Controller
{
    /**
     * Simple inpainting using Gemini Flash Image model.
     */
    public function generateWithMask(Request $request)
    {
        $validated = $request->validate([
            'originalImage' => 'required|string',
            'mask' => 'required|string',
            'prompt' => 'required|string',
        ]);

        try {
            Log::info('[generate-with-mask] Starting', ['prompt' => $validated['prompt']]);

            // Decode base64 data URLs to get just the base64 data
            $originalData = self::decodeDataUrl($validated['originalImage']);
            $maskData = self::decodeDataUrl($validated['mask']);

            if (!$originalData || !$maskData) {
                return response()->json(['message' => 'Invalid image data'], 422);
            }

            

            // Re-encode as base64 for Gemini
            $originalBase64 = base64_encode($originalData);
            $maskBase64 = base64_encode($maskData);

            // Build prompt: "inpaint the masked area and [user prompt]"
            $fullPrompt = "You are an expert image editor. The user has provided an image and a mask (white areas show where to edit). " . 
                          $validated['prompt'] . ". Only modify the white areas in the mask. Keep the rest of the image unchanged.";
            
            Log::info('[generate-with-mask] Calling Gemini', ['model' => config('services.gemini.image_model')]);

            // Call Gemini API with image + mask + prompt
            $apiKey = config('services.gemini.api_key');
            $imageModel = config('services.gemini.image_model', 'gemini-2.5-flash-image-preview');
            
            $response = Http::withoutVerifying()->timeout(120)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$imageModel}:generateContent?key={$apiKey}", [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                // Pass the exact prompt received from client (already formatted)
                                ['text' => $validated['prompt']],
                                [
                                    'inlineData' => [
                                        'mimeType' => 'image/png',
                                        'data' => $originalBase64
                                    ]
                                ],
                                [
                                    'inlineData' => [
                                        'mimeType' => 'image/png',
                                        'data' => $maskBase64
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 1.0,
                        'topK' => 40,
                        'topP' => 0.95,
                        'maxOutputTokens' => 8192,
                    ]
                ]);

            if (!$response->successful()) {
                Log::error('[generate-with-mask] Gemini API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('Gemini API error: ' . $response->body());
            }

            $result = $response->json();
            
            // Extract generated image from Gemini response
            if (isset($result['candidates'][0]['content']['parts'])) {
                foreach ($result['candidates'][0]['content']['parts'] as $part) {
                    if (isset($part['inlineData']['data'])) {
                        $generatedBase64 = $part['inlineData']['data'];
                        $dataUrl = 'data:image/png;base64,' . $generatedBase64;

                        Log::info('[generate-with-mask] Success');

                        return response()->json([
                            'generatedImage' => $dataUrl,
                            'prompt' => $validated['prompt'],
                        ]);
                    }
                }
            }

            throw new \Exception('No image in Gemini response');

        } catch (\Throwable $e) {
            Log::error('[generate-with-mask] Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Generation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test Gemini inpainting with simple example.
     */
    public function testInpaint()
    {
        Log::info('[test-inpaint] Starting test');
        
        try {
            // Create test image (red square)
            $img = imagecreatetruecolor(512, 512);
            $red = imagecolorallocate($img, 255, 0, 0);
            imagefilledrectangle($img, 0, 0, 512, 512, $red);
            ob_start();
            imagepng($img);
            $imgData = ob_get_clean();
            imagedestroy($img);
            
            // Create mask (white circle in center)
            $mask = imagecreatetruecolor(512, 512);
            $black = imagecolorallocate($mask, 0, 0, 0);
            $white = imagecolorallocate($mask, 255, 255, 255);
            imagefilledrectangle($mask, 0, 0, 512, 512, $black);
            imagefilledellipse($mask, 256, 256, 200, 200, $white);
            ob_start();
            imagepng($mask);
            $maskData = ob_get_clean();
            imagedestroy($mask);
            
            $originalBase64 = base64_encode($imgData);
            $maskBase64 = base64_encode($maskData);
            
            $prompt = "Edit this image: replace the circular area (shown in white in the mask) with a beautiful blue flower. Keep the red background unchanged.";
            
            Log::info('[test-inpaint] Calling Gemini');
            
            $apiKey = config('services.gemini.api_key');
            $imageModel = config('services.gemini.image_model', 'gemini-2.5-flash-image-preview');
            
            $response = Http::timeout(120)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$imageModel}:generateContent?key={$apiKey}", [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt],
                                [
                                    'inlineData' => [
                                        'mimeType' => 'image/png',
                                        'data' => $originalBase64
                                    ]
                                ],
                                [
                                    'inlineData' => [
                                        'mimeType' => 'image/png',
                                        'data' => $maskBase64
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 1.0,
                        'topK' => 40,
                        'topP' => 0.95,
                        'maxOutputTokens' => 8192,
                    ]
                ]);

            if (!$response->successful()) {
                Log::error('[test-inpaint] API error', ['body' => $response->body()]);
                return response()->json([
                    'success' => false,
                    'error' => $response->body(),
                    'status' => $response->status()
                ]);
            }

            $result = $response->json();
            
            // Extract generated image
            if (isset($result['candidates'][0]['content']['parts'])) {
                foreach ($result['candidates'][0]['content']['parts'] as $part) {
                    if (isset($part['inlineData']['data'])) {
                        Log::info('[test-inpaint] Success!');
                        return response()->json([
                            'success' => true,
                            'original' => 'data:image/png;base64,' . $originalBase64,
                            'mask' => 'data:image/png;base64,' . $maskBase64,
                            'generated' => 'data:image/png;base64,' . $part['inlineData']['data'],
                            'prompt' => $prompt
                        ]);
                    }
                }
            }
            
            Log::error('[test-inpaint] No image in response', ['result' => $result]);
            return response()->json([
                'success' => false,
                'error' => 'No image in response',
                'response' => $result
            ]);
            
        } catch (\Throwable $e) {
            Log::error('[test-inpaint] Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private static function decodeDataUrl(string $dataUrl): ?string
    {
        if (!str_starts_with($dataUrl, 'data:')) {
            return null;
        }
        if (!preg_match('/^data:[^;]+;base64,(.*)$/', $dataUrl, $m)) {
            return null;
        }
        $data = base64_decode($m[1], true);
        return $data === false ? null : $data;
    }
}
