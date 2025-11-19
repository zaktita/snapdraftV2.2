<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;

function imgToDataUrl($path) {
    $type = pathinfo($path, PATHINFO_EXTENSION);
    $data = file_get_contents($path);
    $base64 = base64_encode($data);
    return "data:image/{$type};base64,{$base64}";
}

$apiKey = config('services.openrouter.api_key');
$prompt = "a moroccan man standing on a surfboard in the desert";
$editPrompt = "Replace the masked area with a red apple on the table.";

echo "Generating image for '$prompt'...\n";

$response = Http::withToken($apiKey)
    ->withoutVerifying()
    ->timeout(120) // Increase timeout to 120 seconds
    ->withHeaders([
        'HTTP-Referer' => config('app.url'),
        'X-Title' => config('app.name'),
    ])
    ->post('https://openrouter.ai/api/v1/chat/completions', [
        'model' => 'openai/gpt-5-image', // Using the model from your previous test
        'messages' => [
            ['role' => 'user', 'content' => $prompt],
        ],
    ]);

if ($response->failed()) {
    die("Error: " . $response->body() . "\n");
}

$data = $response->json();

// 1) Get the data URL
// Attempt to find it in the structure you provided, or fallback to content
$dataUrl = $data['choices'][0]['message']['images'][0]['image_url']['url'] 
    ?? $data['choices'][0]['message']['content'] 
    ?? null;

if (!$dataUrl) {
    echo "Could not find image URL in response. Response dump:\n";
    print_r($data);
    exit(1);
}

// 2) Strip the `data:image/...;base64,` prefix
$extension = 'png';
if (preg_match('/^data:image\/(\w+);base64,/', $dataUrl, $type)) {
    $dataUrl = substr($dataUrl, strpos($dataUrl, ',') + 1);
    $extension = strtolower($type[1]);
}

// 3) Decode base64
$imageBinary = base64_decode($dataUrl);

if ($imageBinary === false) {
    die("Failed to decode image.\n");
}

// 4) Save the file
$filename = 'gpt-image-' . time() . '.' . $extension;
$path = __DIR__ . '/' . $filename;

file_put_contents($path, $imageBinary);

echo "Saved image to: $path\n";

// --- Image editing with mask example ---
echo "\nNow testing image editing with mask...\n";

// Prepare image data URLs
$imageData = imgToDataUrl(storage_path('app/images/original.png'));
$maskData  = imgToDataUrl(storage_path('app/images/mask.png'));

$message = [
    "role" => "user",
    "content" => [
        [
            "type" => "input_image",
            "image_url" => ["url" => $imageData],
            "mask_url"  => ["url" => $maskData]  // <-- mask here
        ],
        [
            "type" => "text",
            "text" => $editPrompt
        ]
    ]
];

$editResponse = Http::withToken($apiKey)
    ->withoutVerifying()
    ->timeout(120)
    ->withHeaders([
        'HTTP-Referer' => config('app.url'),
        'X-Title' => config('app.name'),
    ])
    ->post('https://openrouter.ai/api/v1/chat/completions', [
        "model" => "openai/gpt-5-image",
        "messages" => [$message],
    ]);

if ($editResponse->failed()) {
    die("Error in edit request: " . $editResponse->body() . "\n");
}

$editData = $editResponse->json();

// Get edited image URL
$editedUrl = $editData['choices'][0]['message']['images'][0]['image_url']['url'] 
    ?? $editData['choices'][0]['message']['content'] 
    ?? null;

if (!$editedUrl) {
    echo "Could not find edited image URL. Response dump:\n";
    print_r($editData);
    exit(1);
}

// Decode and save edited image
$editExtension = 'png';
if (preg_match('/^data:image\/(\w+);base64,/', $editedUrl, $type)) {
    $editedUrl = substr($editedUrl, strpos($editedUrl, ',') + 1);
    $editExtension = strtolower($type[1]);
}

$editedBinary = base64_decode($editedUrl);

if ($editedBinary === false) {
    die("Failed to decode edited image.\n");
}

$editedFilename = 'gpt-edited-' . time() . '.' . $editExtension;
$editedPath = __DIR__ . '/' . $editedFilename;

file_put_contents($editedPath, $editedBinary);

echo "Saved edited image to: $editedPath\n";
