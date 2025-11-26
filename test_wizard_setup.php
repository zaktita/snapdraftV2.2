<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CSV Wizard Generation Test ===\n\n";

// Check user
$user = App\Models\User::first();
echo "User: {$user->email}\n";
echo "Credits: {$user->credits_remaining}\n";
echo "Has credits: " . ($user->hasCredits() ? 'YES' : 'NO') . "\n\n";

// Check Gemini service
echo "=== Gemini Service Configuration ===\n";
$apiKey = config('services.gemini.api_key');
echo "API Key configured: " . (empty($apiKey) ? 'NO' : 'YES (' . substr($apiKey, 0, 10) . '...)') . "\n";
echo "Text Model: " . config('services.gemini.model') . "\n";
echo "Image Model: " . config('services.gemini.image_model') . "\n";
echo "Text-to-Image Model: " . config('services.gemini.text_to_image_model') . "\n";
echo "Text Accurate Model: " . config('services.gemini.text_accurate_model') . "\n\n";

// Test AI service availability
echo "=== Testing AI Service ===\n";
try {
    $aiService = app(App\Services\AI\AIServiceManager::class);
    echo "AI Service loaded: YES\n";
    echo "Active service: " . $aiService->getActiveServiceName() . "\n";
} catch (Exception $e) {
    echo "AI Service error: " . $e->getMessage() . "\n";
}

echo "\n=== Queue Configuration ===\n";
echo "Queue Connection: " . config('queue.default') . "\n";

if (config('queue.default') === 'sync') {
    echo "⚠️  WARNING: Jobs will run synchronously (may cause timeouts)\n";
    echo "   Consider setting QUEUE_CONNECTION=database in .env\n";
}

echo "\nAll checks complete!\n";
