<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Http;

try {
    $response = Http::withoutVerifying()
        ->withHeaders([
            'Authorization' => 'Bearer ' . env('OPENROUTER_API_KEY'),
            'Content-Type' => 'application/json',
        ])
        ->post('https://openrouter.ai/api/v1/chat/completions', [
            'model' => 'openai/gpt-5-image',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'a moroccan man standing on a surfboard in the desert'
                ]
                ], 'response_format' => 'png'
        ]);

    if (!$response->successful()) {
        echo "❌ API Error\n";
        exit(1);
    }

    $result = $response->json();
    $content = $result['choices'][0]['message']['content'] ?? '';
    
    if (empty($content)) {
        echo "❌ No content\n";
        exit(1);
    }
    
    // Check if it's a data URL
    if (strpos($content, 'data:image') === 0) {
        // Extract base64 part
        $parts = explode(',', $content, 2);
        $imageData = base64_decode($parts[1]);
    } else {
        // Try as raw binary
        $imageData = $content;
    }
    
    // Save as PNG
    $outputPath = __DIR__ . '/moroccan_surfboard.png';
    file_put_contents($outputPath, $imageData);
    
    echo "✅ Image saved to: moroccan_surfboard.png\n";
    echo "File size: " . number_format(strlen($imageData)) . " bytes\n";
    echo "Full path: $outputPath\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
