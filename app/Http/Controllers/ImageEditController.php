<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\AI\AIServiceManager;

class ImageEditController extends Controller
{
    protected $aiService;

    public function __construct(AIServiceManager $aiService)
    {
        $this->aiService = $aiService;
    }
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

            // Extract base64 from data URLs (strip the data:image/png;base64, prefix)
            $originalBase64 = self::extractBase64FromDataUrl($validated['originalImage']);
            $maskBase64 = self::extractBase64FromDataUrl($validated['mask']);

            if (!$originalBase64 || !$maskBase64) {
                return response()->json(['message' => 'Invalid image data'], 422);
            }

            Log::info('[generate-with-mask] Calling Google Gemini');

            // Call GoogleGeminiService for inpainting
            $resultBase64 = $this->aiService->inpaint(
                $originalBase64,
                $maskBase64,
                $validated['prompt']
            );

            $dataUrl = 'data:image/png;base64,' . $resultBase64;

            Log::info('[generate-with-mask] Success');

            return response()->json([
                'generatedImage' => $dataUrl,
                'prompt' => $validated['prompt'],
            ]);

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

    /**
     * Expand/outpaint an image using AI to generate additional content around the edges.
     */
    public function expandImage(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|string',
            'direction' => 'required|in:all,top,bottom,left,right',
            'expansionRatio' => 'nullable|numeric|min:1.2|max:2.0',
        ]);

        try {
            Log::info('[expand-image] Starting', ['direction' => $validated['direction']]);

            // Decode the image
            $imageData = self::decodeDataUrl($validated['image']);
            if (!$imageData) {
                return response()->json(['message' => 'Invalid image data'], 422);
            }

            // Get image dimensions
            $img = imagecreatefromstring($imageData);
            if (!$img) {
                return response()->json(['message' => 'Invalid image format'], 422);
            }
            
            $width = imagesx($img);
            $height = imagesy($img);
            $ratio = $validated['expansionRatio'] ?? 1.5;

            // Calculate new dimensions based on direction
            $newWidth = $width;
            $newHeight = $height;
            $offsetX = 0;
            $offsetY = 0;

            switch ($validated['direction']) {
                case 'all':
                    $newWidth = (int)($width * $ratio);
                    $newHeight = (int)($height * $ratio);
                    $offsetX = (int)(($newWidth - $width) / 2);
                    $offsetY = (int)(($newHeight - $height) / 2);
                    break;
                case 'top':
                    $newHeight = (int)($height * $ratio);
                    $offsetY = $newHeight - $height;
                    break;
                case 'bottom':
                    $newHeight = (int)($height * $ratio);
                    $offsetY = 0;
                    break;
                case 'left':
                    $newWidth = (int)($width * $ratio);
                    $offsetX = $newWidth - $width;
                    break;
                case 'right':
                    $newWidth = (int)($width * $ratio);
                    $offsetX = 0;
                    break;
            }

            // Create new expanded canvas
            $expandedImg = imagecreatetruecolor($newWidth, $newHeight);
            $white = imagecolorallocate($expandedImg, 255, 255, 255);
            imagefilledrectangle($expandedImg, 0, 0, $newWidth, $newHeight, $white);
            
            // Copy original image to new canvas
            imagecopy($expandedImg, $img, $offsetX, $offsetY, 0, 0, $width, $height);
            
            // Create mask (white where we need to generate, black where original image is)
            $mask = imagecreatetruecolor($newWidth, $newHeight);
            $maskWhite = imagecolorallocate($mask, 255, 255, 255);
            $maskBlack = imagecolorallocate($mask, 0, 0, 0);
            imagefilledrectangle($mask, 0, 0, $newWidth, $newHeight, $maskWhite);
            imagefilledrectangle($mask, $offsetX, $offsetY, $offsetX + $width, $offsetY + $height, $maskBlack);

            // Convert to base64
            ob_start();
            imagepng($expandedImg);
            $expandedData = ob_get_clean();
            $expandedBase64 = base64_encode($expandedData);
            
            ob_start();
            imagepng($mask);
            $maskData = ob_get_clean();
            $maskBase64 = base64_encode($maskData);

            imagedestroy($img);
            imagedestroy($expandedImg);
            imagedestroy($mask);

            Log::info('[expand-image] Calling Google Gemini for outpainting');

            // Call GoogleGeminiService for outpainting
            $resultBase64 = $this->aiService->outpaint(
                $expandedBase64,
                $maskBase64
            );

            $dataUrl = 'data:image/png;base64,' . $resultBase64;

            Log::info('[expand-image] Success');

            return response()->json([
                'expandedImage' => $dataUrl,
                'newWidth' => $newWidth,
                'newHeight' => $newHeight,
            ]);

        } catch (\Throwable $e) {
            Log::error('[expand-image] Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Image expansion failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upscale an image using simple bicubic interpolation.
     * For better quality, you could integrate with services like Real-ESRGAN or similar.
     */
    public function upscaleImage(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|string',
            'scale' => 'nullable|numeric|min:1.5|max:4.0',
        ]);

        try {
            Log::info('[upscale-image] Starting', ['scale' => $validated['scale'] ?? 2]);

            // Decode the image
            $imageData = self::decodeDataUrl($validated['image']);
            if (!$imageData) {
                return response()->json(['message' => 'Invalid image data'], 422);
            }

            // Create image from string
            $img = imagecreatefromstring($imageData);
            if (!$img) {
                return response()->json(['message' => 'Invalid image format'], 422);
            }
            
            $width = imagesx($img);
            $height = imagesy($img);
            $scale = $validated['scale'] ?? 2.0;

            $newWidth = (int)($width * $scale);
            $newHeight = (int)($height * $scale);

            // Create new image with upscaled dimensions
            $upscaledImg = imagecreatetruecolor($newWidth, $newHeight);
            
            // Preserve transparency for PNG images
            imagealphablending($upscaledImg, false);
            imagesavealpha($upscaledImg, true);
            
            // Use bicubic interpolation for better quality
            imagecopyresampled(
                $upscaledImg,
                $img,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $width, $height
            );

            // Convert to base64
            ob_start();
            imagepng($upscaledImg, null, 9); // Maximum compression
            $upscaledData = ob_get_clean();
            $upscaledBase64 = base64_encode($upscaledData);

            imagedestroy($img);
            imagedestroy($upscaledImg);

            Log::info('[upscale-image] Success', [
                'originalSize' => "{$width}x{$height}",
                'newSize' => "{$newWidth}x{$newHeight}"
            ]);

            return response()->json([
                'upscaledImage' => 'data:image/png;base64,' . $upscaledBase64,
                'originalWidth' => $width,
                'originalHeight' => $height,
                'newWidth' => $newWidth,
                'newHeight' => $newHeight,
                'scale' => $scale,
            ]);

        } catch (\Throwable $e) {
            Log::error('[upscale-image] Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Image upscaling failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove background from an image using AI.
     * This uses Gemini's image understanding to create a mask and remove the background.
     */
    public function removeBackground(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|string',
        ]);

        try {
            Log::info('[remove-background] Starting');

            // Decode the image
            $imageData = self::decodeDataUrl($validated['image']);
            if (!$imageData) {
                return response()->json(['message' => 'Invalid image data'], 422);
            }

            // Get image dimensions
            $img = imagecreatefromstring($imageData);
            if (!$img) {
                return response()->json(['message' => 'Invalid image format'], 422);
            }
            
            $width = imagesx($img);
            $height = imagesy($img);

            Log::info('[remove-background] Using improved background removal algorithm');
            
            // Use improved local background removal that preserves transparency and dimensions
            return $this->improvedBackgroundRemoval($img, $width, $height);

        } catch (\Throwable $e) {
            Log::error('[remove-background] Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Background removal failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resize the canvas around the image. If target area extends beyond the original image
     * (expansion), fill the new space using AI outpainting (Gemini). If target area is smaller
     * (cropping), crop deterministically without AI.
     *
     * Request params:
     * - image: base64 data URL of the source image
     * - targetWidth: int target canvas width
     * - targetHeight: int target canvas height
     * - offsetX: int X position where the original image's left should be placed in target canvas
     * - offsetY: int Y position where the original image's top should be placed in target canvas
     */
    public function resizeCanvas(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|string',
            'targetWidth' => 'required|integer|min:1|max:8192',
            'targetHeight' => 'required|integer|min:1|max:8192',
            'offsetX' => 'required|integer',
            'offsetY' => 'required|integer',
        ]);

        try {
            Log::info('[resize-canvas] Starting', [
                'targetWidth' => $validated['targetWidth'],
                'targetHeight' => $validated['targetHeight'],
                'offsetX' => $validated['offsetX'],
                'offsetY' => $validated['offsetY'],
            ]);

            $imageData = self::decodeDataUrl($validated['image']);
            if (!$imageData) {
                return response()->json(['message' => 'Invalid image data'], 422);
            }

            $img = imagecreatefromstring($imageData);
            if (!$img) {
                return response()->json(['message' => 'Invalid image format'], 422);
            }

            $srcW = imagesx($img);
            $srcH = imagesy($img);
            $dstW = (int)$validated['targetWidth'];
            $dstH = (int)$validated['targetHeight'];
            $offsetX = (int)$validated['offsetX'];
            $offsetY = (int)$validated['offsetY'];

            // Determine if any area lies outside the original image bounds (expansion)
            $expandsLeft = $offsetX < 0;
            $expandsTop = $offsetY < 0;
            $expandsRight = ($offsetX + $srcW) > $dstW;
            $expandsBottom = ($offsetY + $srcH) > $dstH;

            $needsOutpaint = $dstW > $srcW || $dstH > $srcH || $expandsLeft || $expandsTop || $expandsRight || $expandsBottom;

            if (!$needsOutpaint) {
                // Pure cropping (no AI). Copy the intersecting region from source to target.
                // Create target with transparency
                $dst = imagecreatetruecolor($dstW, $dstH);
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $trans = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $trans);

                // Compute source and destination rectangles
                // We place the original at (offsetX, offsetY) on the destination.
                // If offset is positive, source starts at (0,0) and dest at (offsetX, offsetY)
                // If offset is negative, we shift source start accordingly.
                $srcX = max(0, -$offsetX);
                $srcY = max(0, -$offsetY);
                $dstX = max(0, $offsetX);
                $dstY = max(0, $offsetY);

                $copyW = min($srcW - $srcX, $dstW - $dstX);
                $copyH = min($srcH - $srcY, $dstH - $dstY);
                if ($copyW <= 0 || $copyH <= 0) {
                    imagedestroy($img);
                    imagedestroy($dst);
                    return response()->json(['message' => 'Crop area is empty'], 422);
                }

                imagecopy(
                    $dst,
                    $img,
                    $dstX, $dstY,
                    $srcX, $srcY,
                    $copyW, $copyH
                );

                ob_start();
                imagepng($dst, null, 9);
                $data = ob_get_clean();
                $base64 = base64_encode($data);

                imagedestroy($img);
                imagedestroy($dst);

                Log::info('[resize-canvas] Cropping success');
                return response()->json([
                    'resultImage' => 'data:image/png;base64,' . $base64,
                    'mode' => 'crop',
                    'width' => $dstW,
                    'height' => $dstH,
                ]);
            }

            // Expansion/outpainting path
            // Build expanded canvas of target size and place original at offset
            $expanded = imagecreatetruecolor($dstW, $dstH);
            // Fill background with white (or transparent). We'll use white to help the model.
            $white = imagecolorallocate($expanded, 255, 255, 255);
            imagefilledrectangle($expanded, 0, 0, $dstW, $dstH, $white);
            imagecopy($expanded, $img, max(0, $offsetX), max(0, $offsetY), max(0, -$offsetX), max(0, -$offsetY), $srcW - max(0, -$offsetX), $srcH - max(0, -$offsetY));

            // Create mask: white everywhere to generate, black where original pixels are placed
            $mask = imagecreatetruecolor($dstW, $dstH);
            $maskWhite = imagecolorallocate($mask, 255, 255, 255);
            $maskBlack = imagecolorallocate($mask, 0, 0, 0);
            imagefilledrectangle($mask, 0, 0, $dstW, $dstH, $maskWhite);
            imagefilledrectangle($mask, max(0, $offsetX), max(0, $offsetY), max(0, $offsetX) + $srcW, max(0, $offsetY) + $srcH, $maskBlack);

            ob_start();
            imagepng($expanded);
            $expandedData = ob_get_clean();
            $expandedBase64 = base64_encode($expandedData);

            ob_start();
            imagepng($mask);
            $maskData = ob_get_clean();
            $maskBase64 = base64_encode($maskData);

            imagedestroy($img);
            imagedestroy($expanded);
            imagedestroy($mask);

            Log::info('[resize-canvas] Calling Google Gemini for outpainting', ['target' => $dstW . 'x' . $dstH]);

            // Call GoogleGeminiService for outpainting
            $resultBase64 = $this->aiService->outpaint(
                $expandedBase64,
                $maskBase64
            );

            $dataUrl = 'data:image/png;base64,' . $resultBase64;
            Log::info('[resize-canvas] Outpaint success');
            
            return response()->json([
                'resultImage' => $dataUrl,
                'mode' => 'expand',
                'width' => $dstW,
                'height' => $dstH,
            ]);

        } catch (\Throwable $e) {
            Log::error('[resize-canvas] Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Resize failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Improved background removal using edge detection and intelligent transparency.
     */
    private function improvedBackgroundRemoval($img, $width, $height)
    {
        // Create a new image with transparency
        $transparent = imagecreatetruecolor($width, $height);
        imagealphablending($transparent, false);
        imagesavealpha($transparent, true);
        
        // Fill with transparent color
        $transColor = imagecolorallocatealpha($transparent, 0, 0, 0, 127);
        imagefilledrectangle($transparent, 0, 0, $width, $height, $transColor);
        
        // Create edge map to detect subject boundaries
        $edgeMap = [];
        $edgeThreshold = 30;
        
        for ($y = 1; $y < $height - 1; $y++) {
            for ($x = 1; $x < $width - 1; $x++) {
                $center = imagecolorat($img, $x, $y);
                $top = imagecolorat($img, $x, $y - 1);
                $left = imagecolorat($img, $x - 1, $y);
                
                $diffTop = abs((($center >> 16) & 0xFF) - (($top >> 16) & 0xFF)) +
                          abs((($center >> 8) & 0xFF) - (($top >> 8) & 0xFF)) +
                          abs(($center & 0xFF) - ($top & 0xFF));
                
                $diffLeft = abs((($center >> 16) & 0xFF) - (($left >> 16) & 0xFF)) +
                           abs((($center >> 8) & 0xFF) - (($left >> 8) & 0xFF)) +
                           abs(($center & 0xFF) - ($left & 0xFF));
                
                $edgeMap[$y][$x] = ($diffTop > $edgeThreshold || $diffLeft > $edgeThreshold);
            }
        }
        
        // Sample background colors from edges (assuming background is at borders)
        $bgSamples = [];
        $sampleSize = min(20, (int)($width * 0.05), (int)($height * 0.05));
        
        // Sample from all four edges
        for ($i = 0; $i < $sampleSize; $i++) {
            // Top edge
            $bgSamples[] = imagecolorat($img, (int)($width * ($i / $sampleSize)), 5);
            // Bottom edge
            $bgSamples[] = imagecolorat($img, (int)($width * ($i / $sampleSize)), $height - 5);
            // Left edge
            $bgSamples[] = imagecolorat($img, 5, (int)($height * ($i / $sampleSize)));
            // Right edge
            $bgSamples[] = imagecolorat($img, $width - 5, (int)($height * ($i / $sampleSize)));
        }
        
        // Calculate average background color
        $bgR = $bgG = $bgB = 0;
        foreach ($bgSamples as $color) {
            $bgR += ($color >> 16) & 0xFF;
            $bgG += ($color >> 8) & 0xFF;
            $bgB += $color & 0xFF;
        }
        $count = count($bgSamples);
        $bgR = (int)($bgR / $count);
        $bgG = (int)($bgG / $count);
        $bgB = (int)($bgB / $count);
        
        // Process each pixel with gradient transparency
        $tolerance = 40; // Base tolerance
        
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // Calculate distance from background color
                $distance = sqrt(
                    pow($r - $bgR, 2) +
                    pow($g - $bgG, 2) +
                    pow($b - $bgB, 2)
                );
                
                // Check if pixel is on an edge (likely part of subject)
                $isEdge = isset($edgeMap[$y][$x]) && $edgeMap[$y][$x];
                
                // Calculate alpha based on distance and edge detection
                if ($distance < $tolerance) {
                    // Similar to background - make transparent with gradient
                    $alpha = (int)(127 * ($distance / $tolerance));
                    if ($isEdge) {
                        // Keep edges more opaque
                        $alpha = max(0, $alpha - 40);
                    }
                } else {
                    // Different from background - keep opaque
                    $alpha = 0;
                }
                
                // Set pixel with alpha
                $newColor = imagecolorallocatealpha($transparent, $r, $g, $b, $alpha);
                imagesetpixel($transparent, $x, $y, $newColor);
            }
        }
        
        // Apply a slight blur to smooth edges
        imagefilter($transparent, IMG_FILTER_SMOOTH, 1);
        
        // Convert to base64
        ob_start();
        imagepng($transparent, null, 9); // Maximum compression
        $processedData = ob_get_clean();
        $processedBase64 = base64_encode($processedData);

        imagedestroy($img);
        imagedestroy($transparent);

        Log::info('[remove-background] Success with improved algorithm', [
            'dimensions' => "{$width}x{$height}"
        ]);

        return response()->json([
            'processedImage' => 'data:image/png;base64,' . $processedBase64,
            'method' => 'improved-algorithm',
            'originalWidth' => $width,
            'originalHeight' => $height,
        ]);
    }

    /**
     * Simple fallback background removal using edge detection and transparency.
     */
    private function simpleFallbackBackgroundRemoval($img, $width, $height)
    {
        // Create a new image with transparency
        $transparent = imagecreatetruecolor($width, $height);
        imagealphablending($transparent, false);
        imagesavealpha($transparent, true);
        
        // Fill with transparent color
        $transColor = imagecolorallocatealpha($transparent, 0, 0, 0, 127);
        imagefilledrectangle($transparent, 0, 0, $width, $height, $transColor);
        
        // Copy original image
        imagecopy($transparent, $img, 0, 0, 0, 0, $width, $height);
        
        // Make corners transparent (simple heuristic - assumes background is at corners)
        $cornerSampleSize = min(50, (int)($width * 0.1), (int)($height * 0.1));
        $bgColors = [];
        
        // Sample corner colors
        $corners = [
            ['x' => 0, 'y' => 0],
            ['x' => $width - $cornerSampleSize, 'y' => 0],
            ['x' => 0, 'y' => $height - $cornerSampleSize],
            ['x' => $width - $cornerSampleSize, 'y' => $height - $cornerSampleSize],
        ];
        
        foreach ($corners as $corner) {
            $rgb = imagecolorat($img, $corner['x'] + ($cornerSampleSize / 2), $corner['y'] + ($cornerSampleSize / 2));
            $bgColors[] = $rgb;
        }
        
        // Get most common corner color
        $bgColor = array_count_values($bgColors);
        arsort($bgColor);
        $dominantBg = key($bgColor);
        
        // Make similar colors transparent
        $tolerance = 30;
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                $bgR = ($dominantBg >> 16) & 0xFF;
                $bgG = ($dominantBg >> 8) & 0xFF;
                $bgB = $dominantBg & 0xFF;
                
                // Calculate color difference
                $diff = abs($r - $bgR) + abs($g - $bgG) + abs($b - $bgB);
                
                if ($diff < $tolerance) {
                    imagesetpixel($transparent, $x, $y, $transColor);
                }
            }
        }
        
        // Convert to base64
        ob_start();
        imagepng($transparent);
        $processedData = ob_get_clean();
        $processedBase64 = base64_encode($processedData);

        imagedestroy($img);
        imagedestroy($transparent);

        return response()->json([
            'processedImage' => 'data:image/png;base64,' . $processedBase64,
            'method' => 'fallback',
        ]);
    }

    /**
     * Generate or modify image area based on text prompt.
     * Uses OpenRouter GPT-5 Image for AI-powered editing.
     */
    public function generateFromPrompt(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|string',
            'mask' => 'required|string',
            'prompt' => 'required|string',
        ]);

        try {
            Log::info('[generate-from-prompt] Starting', ['prompt' => $validated['prompt']]);

            // Extract base64 from data URLs (strip the data:image/png;base64, prefix)
            $imageBase64 = self::extractBase64FromDataUrl($validated['image']);
            $maskBase64 = self::extractBase64FromDataUrl($validated['mask']);

            if (!$imageBase64 || !$maskBase64) {
                return response()->json(['message' => 'Invalid image data'], 422);
            }

            Log::info('[generate-from-prompt] Calling Google Gemini');

            // Call GoogleGeminiService for prompt-based generation
            $resultBase64 = $this->aiService->generateFromPrompt(
                $imageBase64,
                $maskBase64,
                $validated['prompt']
            );

            $dataUrl = 'data:image/png;base64,' . $resultBase64;

            Log::info('[generate-from-prompt] Success');

            return response()->json([
                'generatedImage' => $dataUrl,
                'prompt' => $validated['prompt'],
            ]);

        } catch (\Throwable $e) {
            Log::error('[generate-from-prompt] Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Prompt-based generation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Erase/inpaint areas marked with green highlights in the image.
     * The image should contain green (rgb 0,255,0) highlights where areas should be erased.
     * Returns both the generated image and a composite placing it 20px to the right
     * of the original image.
     */
    public function erase(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|string',
        ]);

        try {
            Log::info('[erase] Starting with green-highlighted image');

            // Extract base64 string from data URL
            $imageBase64 = self::extractBase64FromDataUrl($validated['image']);
            if (!$imageBase64) {
                return response()->json(['message' => 'Invalid image data'], 422);
            }

            Log::info('[erase] Payload pre-flight', [
                'image_len' => strlen($imageBase64),
                'image_head' => substr($imageBase64,0,60),
            ]);

            // Call Gemini service to erase green-highlighted areas
            $generatedBase64 = $this->aiService->eraseGreenHighlights($imageBase64);

            Log::info('[erase] Success');

            return response()->json([
                'generatedImage' => 'data:image/png;base64,' . $generatedBase64,
            ]);
        } catch (\Throwable $e) {
            Log::error('[erase] Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erase operation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AI Edit: Apply a prompt to an image (no mask).
     * Returns a generated image as a data URL.
     */
    public function aiEditImage(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|string',
            'prompt' => 'required|string',
        ]);

        try {
            $prompt = $validated['prompt'];
            Log::info('[ai-edit-image] Starting', ['prompt' => substr($prompt, 0, 200)]);

            $imageBase64 = self::extractBase64FromDataUrl($validated['image']);
            if (!$imageBase64) {
                return response()->json(['message' => 'Invalid image data'], 422);
            }

            $resultBase64 = $this->aiService->editBase64($imageBase64, $prompt, null);
            $dataUrl = 'data:image/png;base64,' . $resultBase64;

            Log::info('[ai-edit-image] Success');

            return response()->json([
                'generatedImage' => $dataUrl,
                'prompt' => $prompt,
            ]);
        } catch (\Throwable $e) {
            Log::error('[ai-edit-image] Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'AI edit failed',
                'error' => $e->getMessage(),
            ], 500);
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

    /**
     * Extract base64 string from data URL
     * Returns pure base64 string without the data:image/...;base64, prefix
     */
    private static function extractBase64FromDataUrl(string $dataUrl): ?string
    {
        if (!str_starts_with($dataUrl, 'data:')) {
            return null;
        }
        if (!preg_match('/^data:[^;]+;base64,(.*)$/', $dataUrl, $m)) {
            return null;
        }
        return $m[1]; // Return the base64 string directly
    }
}
