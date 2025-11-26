<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$apiKey = 'AIzaSyAwI2OzaJVX9aFeT90uHnE3_ECc-NmLXFg';
$model = 'gemini-3-pro-image-preview';
$baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

echo "Testing Gemini API with model: {$model}\n";
echo "API Key: " . substr($apiKey, 0, 10) . "...\n\n";

// Test 1: List available models
echo "=== Test 1: Listing available models ===\n";
try {
    $response = file_get_contents("{$baseUrl}/models?key={$apiKey}");
    $models = json_decode($response, true);
    
    if (isset($models['models'])) {
        echo "Available models:\n";
        foreach ($models['models'] as $model) {
            $name = $model['name'] ?? 'unknown';
            $displayName = $model['displayName'] ?? 'N/A';
            $description = $model['description'] ?? 'N/A';
            
            // Only show image-related models
            if (stripos($name, 'image') !== false || stripos($name, 'imagen') !== false) {
                echo "  - {$name}\n";
                echo "    Display: {$displayName}\n";
                echo "    Desc: " . substr($description, 0, 100) . "...\n\n";
            }
        }
    } else {
        echo "Error: " . print_r($models, true) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test 2: Test text generation ===\n";
try {
    $testModel = 'gemini-1.5-flash';
    $url = "{$baseUrl}/models/{$testModel}:generateContent?key={$apiKey}";
    
    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => 'Hello, respond with just "working"']
                ]
            ]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: {$httpCode}\n";
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'No text';
        echo "Response: {$text}\n";
    } else {
        echo "Error response: " . substr($response, 0, 500) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
