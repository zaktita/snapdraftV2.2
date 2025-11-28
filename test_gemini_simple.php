<?php

use App\Services\AI\GoogleGeminiService;

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = $app->make(GoogleGeminiService::class);

$prompt = "A red apple on a wooden table";
echo "Testing with prompt: '$prompt'...\n";

try {
    // textAccurate = true to use gemini-3-pro-preview
    $result = $service->generateWithReferences($prompt, [], [], 'square', true);
    
    if (isset($result['image_data'])) {
        echo "Success! Image data received.\n";
        echo "Mime Type: " . $result['mime_type'] . "\n";
        echo "Data Length: " . strlen(base64_decode($result['image_data'])) . " bytes\n";
    } else {
        echo "Failed: No image data in response.\n";
        print_r($result);
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
