<?php

require __DIR__ . '/vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$apiKey = $_ENV['OPENROUTER_API_KEY'];

$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => 'openai/gpt-5-image',
    'messages' => [
        [
            'role' => 'user',
            'content' => 'a moroccan man standing on a surfboard in the desert'
        ]
    ]
]));

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (isset($result['error'])) {
    echo "❌ API Error: " . $result['error']['message'] . "\n";
    die();
}

echo "✅ Got response from API\n";
echo "Model: " . ($result['model'] ?? 'unknown') . "\n";

$encodedImage = $result['choices'][0]['message']['content'] ?? '';

if (empty($encodedImage)) {
    echo "Response structure:\n";
    print_r($result);
    die("❌ No content received\n");
}

// Decode the Base64 image
$imageData = base64_decode($encodedImage);

// Save as PNG
$outputPath = __DIR__ . '/moroccan_surfboard.png';
file_put_contents($outputPath, $imageData);

echo "✅ Image saved to: moroccan_surfboard.png\n";
echo "Decoded size: " . number_format(strlen($imageData)) . " bytes\n";
echo "Full path: $outputPath\n\n";
echo "👉 Open the file to view your generated image!\n";
