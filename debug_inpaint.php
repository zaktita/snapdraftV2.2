<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;

// Helper function to create test image
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
function createMask($width = 256, $height = 256) {
    $mask = imagecreatetruecolor($width, $height);
    $black = imagecolorallocate($mask, 0, 0, 0);
    $white = imagecolorallocate($mask, 255, 255, 255);
    
    imagefilledrectangle($mask, 0, 0, $width, $height, $black);
    imagefilledellipse($mask, $width/2, $height/2, 100, 100, $white);
    
    ob_start();
    imagepng($mask);
    $data = ob_get_clean();
    imagedestroy($mask);
    return base64_encode($data);
}

$apiKey = config('services.openrouter.api_key');

// Create test images
$imageBase64 = createTestImage(384, 384, [100, 150, 200]);
$maskBase64 = createMask(384, 384);

// Prepare image data URLs
$imageData = 'data:image/png;base64,' . $imageBase64;
$maskData  = 'data:image/png;base64,' . $maskBase64;
$editPrompt = "Replace the circle with a golden star";

echo "=== Comparing Request Structures ===\n\n";

// Structure 1: simple_test.php style (WORKING)
echo "Structure 1: simple_test.php style\n";
echo "-----------------------------------\n";

$message1 = [
    "role" => "user",
    "content" => [
        [
            "type" => "input_image",
            "image_url" => ["url" => $imageData],
            "mask_url"  => ["url" => $maskData]
        ],
        [
            "type" => "text",
            "text" => $editPrompt
        ]
    ]
];

$payload1 = [
    "model" => "openai/gpt-5-image",
    "messages" => [$message1],
];

echo "Payload structure:\n";
echo json_encode($payload1, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

echo "Sending request...\n";
$response1 = Http::withToken($apiKey)
    ->withoutVerifying()
    ->timeout(120)
    ->withHeaders([
        'HTTP-Referer' => config('app.url'),
        'X-Title' => config('app.name'),
    ])
    ->post('https://openrouter.ai/api/v1/chat/completions', $payload1);

if ($response1->failed()) {
    echo "❌ ERROR: " . $response1->body() . "\n";
} else {
    $data1 = $response1->json();
    echo "Response keys: " . json_encode(array_keys($data1['choices'][0]['message'] ?? [])) . "\n";
    
    if (isset($data1['choices'][0]['message']['images'])) {
        echo "✅ Has images array!\n";
        echo "Images count: " . count($data1['choices'][0]['message']['images']) . "\n";
    } else {
        echo "❌ No images array\n";
        $content = $data1['choices'][0]['message']['content'] ?? null;
        if (is_string($content)) {
            echo "Content type: string\n";
            echo "Content preview: " . substr($content, 0, 100) . "...\n";
        }
    }
}

echo "\n=== End Debug ===\n";
