<?php
/**
 * Comprehensive Canvas Editor AI Functions Test
 * Tests all OpenRouter-powered endpoints with detailed error reporting
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\CanvasEditorService;
use Illuminate\Support\Facades\Log;

echo "=== Canvas Editor AI Functions Test ===\n\n";

// Helper function to create test images
function createTestImage($width = 256, $height = 256, $color = [255, 0, 0]) {
    $img = imagecreatetruecolor($width, $height);
    $bgColor = imagecolorallocate($img, $color[0], $color[1], $color[2]);
    imagefilledrectangle($img, 0, 0, $width, $height, $bgColor);
    
    // Add some visual elements
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefilledellipse($img, $width/2, $height/2, 50, 50, $white);
    
    ob_start();
    imagepng($img);
    $data = ob_get_clean();
    imagedestroy($img);
    return base64_encode($data);
}

// Helper function to create mask
function createMask($width = 256, $height = 256, $maskType = 'center') {
    $mask = imagecreatetruecolor($width, $height);
    $black = imagecolorallocate($mask, 0, 0, 0);
    $white = imagecolorallocate($mask, 255, 255, 255);
    
    imagefilledrectangle($mask, 0, 0, $width, $height, $black);
    
    switch ($maskType) {
        case 'center':
            imagefilledellipse($mask, $width/2, $height/2, 100, 100, $white);
            break;
        case 'border':
            imagefilledrectangle($mask, 0, 0, $width, $height, $white);
            imagefilledrectangle($mask, 20, 20, $width-20, $height-20, $black);
            break;
    }
    
    ob_start();
    imagepng($mask);
    $data = ob_get_clean();
    imagedestroy($mask);
    return base64_encode($data);
}

// Test 1: CanvasEditorService - Inpaint
echo "Test 1: CanvasEditorService::inpaint()\n";
echo "---------------------------------------\n";
try {
    $service = app(CanvasEditorService::class);
    $imageBase64 = createTestImage(384, 384, [100, 150, 200]);
    $maskBase64 = createMask(384, 384, 'center');
    
    echo "Calling inpaint with prompt: 'Replace the circle with a golden star'...\n";
    $result = $service->inpaint($imageBase64, $maskBase64, 'Replace the circle with a golden star');
    
    if ($result && strlen($result) > 100) {
        $outputPath = storage_path('app/tmp/test_inpaint_' . time() . '.png');
        @mkdir(dirname($outputPath), 0777, true);
        file_put_contents($outputPath, base64_decode($result));
        echo "✅ SUCCESS - Saved to: $outputPath\n";
        echo "   Output size: " . strlen($result) . " chars\n";
    } else {
        echo "⚠️  WARNING - Result too small or empty\n";
    }
} catch (\Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "   Stack: " . substr($e->getTraceAsString(), 0, 500) . "\n";
}
echo "\n";

// Test 2: CanvasEditorService - Outpaint
echo "Test 2: CanvasEditorService::outpaint()\n";
echo "---------------------------------------\n";
try {
    $service = app(CanvasEditorService::class);
    
    // Create expanded canvas
    $expandedImg = imagecreatetruecolor(512, 512);
    $white = imagecolorallocate($expandedImg, 255, 255, 255);
    imagefilledrectangle($expandedImg, 0, 0, 512, 512, $white);
    
    // Place original in center
    $originalData = base64_decode(createTestImage(256, 256, [50, 100, 150]));
    $original = imagecreatefromstring($originalData);
    imagecopy($expandedImg, $original, 128, 128, 0, 0, 256, 256);
    
    ob_start();
    imagepng($expandedImg);
    $expandedData = ob_get_clean();
    $expandedBase64 = base64_encode($expandedData);
    imagedestroy($expandedImg);
    imagedestroy($original);
    
    // Create outpaint mask
    $maskBase64 = createMask(512, 512, 'border');
    
    echo "Calling outpaint to extend borders...\n";
    $result = $service->outpaint($expandedBase64, $maskBase64, 'Extend with natural landscape');
    
    if ($result && strlen($result) > 100) {
        $outputPath = storage_path('app/tmp/test_outpaint_' . time() . '.png');
        file_put_contents($outputPath, base64_decode($result));
        echo "✅ SUCCESS - Saved to: $outputPath\n";
        echo "   Output size: " . strlen($result) . " chars\n";
    } else {
        echo "⚠️  WARNING - Result too small or empty\n";
    }
} catch (\Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "   Stack: " . substr($e->getTraceAsString(), 0, 500) . "\n";
}
echo "\n";

// Test 3: CanvasEditorService - GenerateFromPrompt
echo "Test 3: CanvasEditorService::generateFromPrompt()\n";
echo "---------------------------------------------------\n";
try {
    $service = app(CanvasEditorService::class);
    $imageBase64 = createTestImage(320, 320, [200, 100, 50]);
    $maskBase64 = createMask(320, 320, 'center');
    
    echo "Calling generateFromPrompt: 'A beautiful crystal gem'...\n";
    $result = $service->generateFromPrompt($imageBase64, $maskBase64, 'A beautiful crystal gem');
    
    if ($result && strlen($result) > 100) {
        $outputPath = storage_path('app/tmp/test_generate_' . time() . '.png');
        file_put_contents($outputPath, base64_decode($result));
        echo "✅ SUCCESS - Saved to: $outputPath\n";
        echo "   Output size: " . strlen($result) . " chars\n";
    } else {
        echo "⚠️  WARNING - Result too small or empty\n";
    }
} catch (\Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "   Stack: " . substr($e->getTraceAsString(), 0, 500) . "\n";
}
echo "\n";

// Test 4: Check API Configuration
echo "Test 4: Configuration Check\n";
echo "----------------------------\n";
$apiKey = config('services.openrouter.api_key');
$appUrl = config('app.url');
$appName = config('app.name');

echo "OpenRouter API Key: " . ($apiKey ? '✅ Set (' . substr($apiKey, 0, 10) . '...)' : '❌ Missing') . "\n";
echo "App URL: " . ($appUrl ?: '❌ Not set') . "\n";
echo "App Name: " . ($appName ?: '❌ Not set') . "\n";
echo "\n";

// Test 5: HTTP Client Test
echo "Test 5: Direct HTTP Client Test\n";
echo "--------------------------------\n";
try {
    $testImage = createTestImage(128, 128, [255, 0, 0]);
    $testMask = createMask(128, 128, 'center');
    
    $client = \Illuminate\Support\Facades\Http::withToken($apiKey)
        ->withoutVerifying()
        ->timeout(120)
        ->withHeaders([
            'HTTP-Referer' => $appUrl,
            'X-Title' => $appName,
            'Content-Type' => 'application/json',
        ]);
    
    echo "Sending test request to OpenRouter...\n";
    $response = $client->post('https://openrouter.ai/api/v1/chat/completions', [
        'model' => 'openai/gpt-5-image',
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'input_image',
                        'image_url' => ['url' => 'data:image/png;base64,' . $testImage],
                        'mask_url' => ['url' => 'data:image/png;base64,' . $testMask],
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Simple test prompt',
                    ],
                ]
            ],
        ],
    ]);
    
    if ($response->successful()) {
        $data = $response->json();
        echo "✅ HTTP Request successful\n";
        echo "   Model: " . ($data['model'] ?? 'unknown') . "\n";
        echo "   Has choices: " . (isset($data['choices']) ? 'Yes' : 'No') . "\n";
        
        if (isset($data['choices'][0]['message']['images'][0]['image_url']['url'])) {
            echo "   Image URL found in response\n";
        } else {
            echo "   Response structure:\n";
            echo "   " . json_encode(array_keys($data['choices'][0]['message'] ?? []), JSON_PRETTY_PRINT) . "\n";
        }
    } else {
        echo "❌ HTTP Request failed\n";
        echo "   Status: " . $response->status() . "\n";
        echo "   Body: " . substr($response->body(), 0, 500) . "\n";
    }
} catch (\Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

echo "=== Test Complete ===\n";
echo "Check storage/app/tmp/ for generated images\n";
