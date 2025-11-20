<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

function createTestImage($width = 128, $height = 128) {
    $img = imagecreatetruecolor($width, $height);
    $red = imagecolorallocate($img, 255, 0, 0);
    imagefilledrectangle($img, 0, 0, $width, $height, $red);
    ob_start();
    imagepng($img);
    $data = ob_get_clean();
    imagedestroy($img);
    return base64_encode($data);
}

function createMask($width = 128, $height = 128) {
    $mask = imagecreatetruecolor($width, $height);
    $black = imagecolorallocate($mask, 0, 0, 0);
    $white = imagecolorallocate($mask, 255, 255, 255);
    imagefilledrectangle($mask, 0, 0, $width, $height, $black);
    imagefilledellipse($mask, $width/2, $height/2, 50, 50, $white);
    ob_start();
    imagepng($mask);
    $data = ob_get_clean();
    imagedestroy($mask);
    return base64_encode($data);
}

$apiKey = config('services.openrouter.api_key');
$imageBase64 = createTestImage();
$maskBase64 = createMask();

echo "Calling OpenRouter API...\n\n";

$response = \Illuminate\Support\Facades\Http::withToken($apiKey)
    ->withoutVerifying()
    ->timeout(120)
    ->withHeaders([
        'HTTP-Referer' => config('app.url'),
        'X-Title' => config('app.name'),
        'Content-Type' => 'application/json',
    ])
    ->post('https://openrouter.ai/api/v1/chat/completions', [
        'model' => 'openai/gpt-5-image',
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'input_image',
                        'image_url' => ['url' => 'data:image/png;base64,' . $imageBase64],
                        'mask_url' => ['url' => 'data:image/png;base64,' . $maskBase64],
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Test prompt',
                    ],
                ]
            ],
        ],
    ]);

if (!$response->successful()) {
    die("Error: " . $response->body() . "\n");
}

$data = $response->json();

echo "Response structure:\n";
echo "==================\n";
echo "Model: " . ($data['model'] ?? 'N/A') . "\n";
echo "Has choices: " . (isset($data['choices']) ? 'Yes' : 'No') . "\n";
echo "Has message: " . (isset($data['choices'][0]['message']) ? 'Yes' : 'No') . "\n";
echo "Message keys: " . json_encode(array_keys($data['choices'][0]['message'] ?? [])) . "\n";
echo "Has images array: " . (isset($data['choices'][0]['message']['images']) ? 'Yes' : 'No') . "\n\n";

if (isset($data['choices'][0]['message']['images'])) {
    echo "Images array structure:\n";
    echo "Number of images: " . count($data['choices'][0]['message']['images']) . "\n";
    $firstImage = $data['choices'][0]['message']['images'][0] ?? null;
    if ($firstImage) {
        echo "First image keys: " . json_encode(array_keys($firstImage)) . "\n";
        if (isset($firstImage['image_url']['url'])) {
            $url = $firstImage['image_url']['url'];
            echo "Image URL found!\n";
            echo "URL length: " . strlen($url) . "\n";
            echo "Starts with 'data:image': " . (str_starts_with($url, 'data:image') ? 'Yes' : 'No') . "\n";
            
            if (preg_match('/^data:image\/(\w+);base64,/', $url, $matches)) {
                $base64 = substr($url, strpos($url, ',') + 1);
                $decoded = base64_decode($base64);
                echo "✅ Successfully extracted and decoded base64\n";
                echo "Decoded size: " . strlen($decoded) . " bytes\n";
                
                $outputPath = __DIR__ . '/debug_output.png';
                file_put_contents($outputPath, $decoded);
                echo "✅ Saved to: $outputPath\n";
            }
        }
    }
    exit;
}

$content = $data['choices'][0]['message']['content'] ?? null;
echo "Content type: " . gettype($content) . "\n";

if (is_string($content)) {
    echo "Content length: " . strlen($content) . " chars\n";
    echo "Starts with 'data:image': " . (str_starts_with($content, 'data:image') ? 'Yes' : 'No') . "\n";
    echo "Starts with 'iVBOR' (PNG base64): " . (str_starts_with($content, 'iVBOR') ? 'Yes' : 'No') . "\n";
    echo "First 100 chars: " . substr($content, 0, 100) . "\n";
    echo "Last 50 chars: " . substr($content, -50) . "\n\n";
    
    // Try to decode as base64
    $decoded = base64_decode($content, true);
    if ($decoded !== false) {
        echo "✅ Successfully decoded as base64\n";
        echo "Decoded size: " . strlen($decoded) . " bytes\n";
        
        // Check magic bytes
        $magic = bin2hex(substr($decoded, 0, 8));
        echo "Magic bytes: " . $magic . "\n";
        
        if (str_starts_with($magic, '89504e47')) {
            echo "✅ Valid PNG header detected!\n";
            $outputPath = __DIR__ . '/debug_output.png';
            file_put_contents($outputPath, $decoded);
            echo "Saved to: $outputPath\n";
        }
    } else {
        echo "❌ Not valid base64\n";
    }
}

if (is_array($content)) {
    echo "Content is array with " . count($content) . " items\n";
    echo json_encode($content, JSON_PRETTY_PRINT) . "\n";
}
