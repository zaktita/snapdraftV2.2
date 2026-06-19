<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Services\AI\AIServiceManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ImageEditController extends Controller
{
    protected $aiService;

    public function __construct(AIServiceManager $aiService)
    {
        $this->aiService = $aiService;
    }
    /**
     * Replace text: accepts a green-highlighted composite image + text prompt.
     * Sends to Gemini then resizes the result back to the original dimensions.
     */
    public function generateWithMask(Request $request)
    {
        $validated = $request->validate([
            'image'    => 'required|string|max:14000000', // ~10 MB
            'prompt'   => 'required|string|max:2000',
            'image_id' => 'nullable|integer|exists:images,id',
        ]);

        if (!empty($validated['image_id'])) {
            $this->authorize('update', Image::findOrFail($validated['image_id']));
        }

        $user           = $request->user();
        $creditDeducted = false;

        try {
            Log::info('[replace-text] Starting', ['prompt' => $validated['prompt']]);

            $imageBase64 = self::extractBase64FromDataUrl($validated['image']);
            if (!$imageBase64) {
                return response()->json(['message' => 'Invalid image data'], 422);
            }

            // Record original dimensions so we can restore them after AI generation
            $originalData = base64_decode($imageBase64);
            $originalImg  = imagecreatefromstring($originalData);
            if (!$originalImg) {
                return response()->json(['message' => 'Could not read image dimensions'], 422);
            }
            $originalW = imagesx($originalImg);
            $originalH = imagesy($originalImg);
            imagedestroy($originalImg);

            Log::info('[replace-text] Original dimensions', ['w' => $originalW, 'h' => $originalH]);
            Log::info('[replace-text] Calling Gemini via editBase64');

            if ($user->hasActiveSubscription()) {
                $user->useCredit();
                $creditDeducted = true;
            }

            $resultBase64 = $this->aiService->editBase64(
                $imageBase64,
                $validated['prompt'],
                null
            );

            // Resize result back to original dimensions if they differ
            $resultBase64 = self::resizeToOriginal($resultBase64, $originalW, $originalH);

            Log::info('[replace-text] Success');

            return response()->json([
                'generatedImage' => 'data:image/png;base64,' . $resultBase64,
                'prompt'         => $validated['prompt'],
            ]);

        } catch (\Throwable $e) {
            if ($creditDeducted) {
                try { $user->refundCredit(); } catch (\Throwable) {}
            }
            Log::error('[replace-text] Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Replace text failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Expand/outpaint an image using AI to generate additional content around the edges.
     */
    public function expandImage(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|string|max:14000000', // ~10 MB
            'direction' => 'required|in:all,top,bottom,left,right',
            'expansionRatio' => 'nullable|numeric|min:1.2|max:2.0',
        ]);

        $user = $request->user();
        $creditDeducted = false;

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

            if ($user->hasActiveSubscription()) {
                $user->useCredit();
                $creditDeducted = true;
            }

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
            if ($creditDeducted) {
                try { $user->refundCredit(); } catch (\Throwable) {}
            }
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
            'image' => 'required|string|max:14000000', // ~10 MB
            'scale' => 'required|integer|in:2,4',
        ]);

        $user = $request->user();
        $creditDeducted = false;
        $creditCost = (int) $validated['scale'];

        try {
            Log::info('[upscale-image] Starting (Gemini prompt flow)', ['scale' => $validated['scale']]);

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
            $scale = (int) $validated['scale'];

            $newWidth = (int)($width * $scale);
            $newHeight = (int)($height * $scale);

            imagedestroy($img);

            // Gemini does not expose a dedicated upscale endpoint here; use image edit
            // with explicit upscale instruction, then enforce target dimensions.
            $imageBase64 = self::extractBase64FromDataUrl($validated['image']);
            if (!$imageBase64) {
                return response()->json(['message' => 'Invalid image data'], 422);
            }

            if ($user->hasActiveSubscription()) {
                $user->useCredit($creditCost);
                $creditDeducted = true;
            }

            $prompt = "Upscale this image by {$scale}x while preserving composition, text, branding, and details. Keep the output visually faithful to the original.";
            $resultBase64 = $this->aiService->editBase64($imageBase64, $prompt, null);
            $upscaledBase64 = self::resizeToDimensions($resultBase64, $newWidth, $newHeight);

            Log::info('[upscale-image] Success', [
                'originalSize' => "{$width}x{$height}",
                'newSize' => "{$newWidth}x{$newHeight}",
                'scale' => $scale,
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
            if ($creditDeducted) {
                try { $user->refundCredit($creditCost); } catch (\Throwable) {}
            }
            Log::error('[upscale-image] Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Image upscaling failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private static function resizeToDimensions(string $resultBase64, int $targetW, int $targetH): string
    {
        $data = base64_decode($resultBase64);
        $img  = imagecreatefromstring($data);
        if (!$img) {
            return $resultBase64;
        }

        $srcW = imagesx($img);
        $srcH = imagesy($img);

        if ($srcW === $targetW && $srcH === $targetH) {
            imagedestroy($img);
            return $resultBase64;
        }

        $out = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
        imagefilledrectangle($out, 0, 0, $targetW, $targetH, $transparent);
        imagealphablending($out, true);

        imagecopyresampled($out, $img, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);

        ob_start();
        imagepng($out, null, 9);
        $resized = ob_get_clean();

        imagedestroy($img);
        imagedestroy($out);

        return base64_encode($resized);
    }

    /**
     * Remove background from an image using AI.
     * This uses Gemini's image understanding to create a mask and remove the background.
     */
    public function removeBackground(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|string|max:14000000', // ~10 MB
        ]);

        $user = $request->user();
        $creditDeducted = false;

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

            if ($user->hasActiveSubscription()) {
                $user->useCredit();
                $creditDeducted = true;
            }

            Log::info('[remove-background] Using improved background removal algorithm');
            
            // Use improved local background removal that preserves transparency and dimensions
            return $this->improvedBackgroundRemoval($img, $width, $height);

        } catch (\Throwable $e) {
            if ($creditDeducted) {
                try { $user->refundCredit(); } catch (\Throwable) {}
            }
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
            'image' => 'required|string|max:14000000', // ~10 MB
            'targetWidth' => 'required|integer|min:1|max:8192',
            'targetHeight' => 'required|integer|min:1|max:8192',
            'offsetX' => 'required|integer',
            'offsetY' => 'required|integer',
        ]);

        $user = $request->user();
        $creditDeducted = false;

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

            if ($needsOutpaint && $user->hasActiveSubscription()) {
                $user->useCredit();
                $creditDeducted = true;
            }

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
            if ($creditDeducted) {
                try { $user->refundCredit(); } catch (\Throwable) {}
            }
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
            'image'  => 'required|string|max:14000000', // ~10 MB
            'mask'   => 'required|string|max:14000000', // ~10 MB
            'prompt' => 'required|string|max:2000',
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
        // SeedDream 4.5 can take 60-90 seconds — lift PHP's 30s limit for this action
        set_time_limit(180);

        $validated = $request->validate([
            'image'    => 'required|string|max:14000000', // ~10 MB
            'image_id' => 'nullable|integer|exists:images,id',
        ]);

        if (!empty($validated['image_id'])) {
            $this->authorize('update', Image::findOrFail($validated['image_id']));
        }

        $user           = $request->user();
        $creditDeducted = false;

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

            // Capture original dimensions before calling AI
            $originalImg = @imagecreatefromstring(base64_decode($imageBase64));
            $originalW = $originalImg ? imagesx($originalImg) : null;
            $originalH = $originalImg ? imagesy($originalImg) : null;
            if ($originalImg) imagedestroy($originalImg);
            if ($originalW) Log::info('[erase] Original dimensions', ['w' => $originalW, 'h' => $originalH]);

            // Call Gemini to erase green-highlighted areas
            if ($user->hasActiveSubscription()) {
                $user->useCredit();
                $creditDeducted = true;
            }
            $generatedBase64 = $this->aiService->eraseGreenHighlights($imageBase64);

            // Resize result back to original dimensions if Gemini changed them
            if ($originalW && $originalH) {
                $generatedBase64 = self::resizeToOriginal($generatedBase64, $originalW, $originalH);
            }

            Log::info('[erase] Success');

            return response()->json([
                'generatedImage' => 'data:image/png;base64,' . $generatedBase64,
            ]);
        } catch (\Throwable $e) {
            if ($creditDeducted) {
                try { $user->refundCredit(); } catch (\Throwable) {}
            }
            Log::error('[erase] Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erase operation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AI Edit: Apply a prompt to an image (Gemini).
     * Accepts either a plain image (full edit) or a green-highlighted composite (area edit).
     * Returns a generated image resized to the original dimensions.
     */
    public function aiEditImage(Request $request)
    {
        $validated = $request->validate([
            'image'         => 'required|string|max:14000000', // ~10 MB
            'prompt'        => 'required|string|max:2000',
            'image_id'      => 'nullable|integer|exists:images,id',
            'aspect_ratio'  => ['nullable', 'string', 'regex:/^\d+:\d+$/'],
        ]);

        if (!empty($validated['image_id'])) {
            $this->authorize('update', Image::findOrFail($validated['image_id']));
        }

        $user           = $request->user();
        $creditDeducted = false;

        try {
            $prompt = $validated['prompt'];
            Log::info('[ai-edit-image] Starting', ['prompt' => substr($prompt, 0, 200)]);

            $imageBase64 = self::extractBase64FromDataUrl($validated['image']);
            if (!$imageBase64) {
                return response()->json(['message' => 'Invalid image data'], 422);
            }

            // Record original dimensions so we can restore them after AI generation
            $originalData = base64_decode($imageBase64);
            $originalImg  = imagecreatefromstring($originalData);
            if (!$originalImg) {
                return response()->json(['message' => 'Could not read image dimensions'], 422);
            }
            $originalW = imagesx($originalImg);
            $originalH = imagesy($originalImg);
            imagedestroy($originalImg);

            Log::info('[ai-edit-image] Original dimensions', ['w' => $originalW, 'h' => $originalH]);

            if ($user->hasActiveSubscription()) {
                $user->useCredit();
                $creditDeducted = true;
            }

            $aspectRatio = $validated['aspect_ratio'] ?? null;

            $resultBase64 = $this->aiService->editBase64($imageBase64, $prompt, null, $aspectRatio);

            // Resize result back to original dimensions unless aspect ratio recompose was requested
            if ($aspectRatio === null) {
                $resultBase64 = self::resizeToOriginal($resultBase64, $originalW, $originalH);
            }

            Log::info('[ai-edit-image] Success');

            return response()->json([
                'generatedImage' => 'data:image/png;base64,' . $resultBase64,
                'prompt'         => $prompt,
            ]);
        } catch (\Throwable $e) {
            if ($creditDeducted) {
                try { $user->refundCredit(); } catch (\Throwable) {}
            }
            Log::error('[ai-edit-image] Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'AI edit failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resize a base64-encoded PNG to the given pixel dimensions.
     * If the result already matches, returns it unchanged.
     * Uses high-quality bicubic resampling (imagecopyresampled).
     */
    private static function resizeToOriginal(string $resultBase64, int $targetW, int $targetH): string
    {
        $data = base64_decode($resultBase64);
        $img  = imagecreatefromstring($data);
        if (!$img) {
            // Can't decode — return as-is
            return $resultBase64;
        }

        $srcW = imagesx($img);
        $srcH = imagesy($img);

        if ($srcW === $targetW && $srcH === $targetH) {
            imagedestroy($img);
            return $resultBase64;
        }

        Log::info('[resizeToOriginal] Resizing AI result', [
            'from' => "{$srcW}x{$srcH}",
            'to'   => "{$targetW}x{$targetH}",
        ]);

        // Preserve alpha/transparency
        $out = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
        imagefilledrectangle($out, 0, 0, $targetW, $targetH, $transparent);
        imagealphablending($out, true);

        imagecopyresampled($out, $img, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);

        ob_start();
        imagepng($out, null, 9);
        $resized = ob_get_clean();

        imagedestroy($img);
        imagedestroy($out);

        return base64_encode($resized);
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
