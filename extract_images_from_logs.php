<?php

/**
 * Extract base64 images from Laravel logs and save them to storage/app/testaimodel
 * 
 * This script reads the log file, finds base64 image data URLs, 
 * and saves them as actual image files.
 */

$logFile = __DIR__ . '/storage/logs/laravel.log';
$outputDir = __DIR__ . '/storage/app/testaimodel';

// Ensure output directory exists
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
    echo "Created directory: $outputDir\n";
}

// Read the log file
if (!file_exists($logFile)) {
    die("Log file not found: $logFile\n");
}

$logContent = file_get_contents($logFile);

// Pattern to match image URLs in logs
// Looks for: "url_preview":"data:image/...;base64,..."
preg_match_all('/"url_preview":"(data:image\/[^"]+)"/', $logContent, $matches);

if (empty($matches[1])) {
    die("No image URLs found in logs\n");
}

$saved = 0;
$failed = 0;
$imageUrls = array_unique($matches[1]); // Remove duplicates

echo "Found " . count($imageUrls) . " unique image URLs in logs\n";

foreach ($imageUrls as $index => $dataUrl) {
    try {
        // Parse data URL: data:image/png;base64,iVBORw0K...
        if (!preg_match('/^data:image\/(\w+);base64,(.+)$/', $dataUrl, $urlMatches)) {
            echo "  ⚠️  Image " . ($index + 1) . ": Invalid data URL format (truncated preview?)\n";
            $failed++;
            continue;
        }

        $extension = $urlMatches[1]; // png, jpeg, etc.
        $base64Data = $urlMatches[2];

        // The preview is truncated (only 100 chars), so we can't actually decode it
        // This is expected - the script shows how it would work with full data
        $imageData = base64_decode($base64Data, true);
        
        if ($imageData === false || strlen($imageData) < 100) {
            echo "  ⚠️  Image " . ($index + 1) . ": Data is truncated (preview only, not full image)\n";
            $failed++;
            continue;
        }

        // Create filename
        $timestamp = date('Y-m-d_His');
        $filename = "{$timestamp}_from_log_{$index}.{$extension}";
        $filepath = "$outputDir/$filename";

        // Save to file
        file_put_contents($filepath, $imageData);
        
        $sizeKb = round(strlen($imageData) / 1024, 2);
        echo "  ✓ Saved: $filename ({$sizeKb} KB)\n";
        $saved++;
    } catch (Exception $e) {
        echo "  ✗ Error saving image " . ($index + 1) . ": " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n";
echo "=====================\n";
echo "Summary:\n";
echo "  Saved: $saved\n";
echo "  Failed: $failed\n";
echo "  Total: " . count($imageUrls) . "\n";
echo "=====================\n";
echo "\nNOTE: The log only contains 100-character previews of the base64 data.\n";
echo "To save actual images, they need to be generated fresh through the API.\n";
echo "Next time you generate images, they will automatically be saved to:\n";
echo "  $outputDir\n";
