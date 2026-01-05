<?php

/**
 * Quick test script for CaptionAnalyzer service
 * 
 * Usage: php test_caption_analyzer.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Use array cache for testing (avoid DB connection)
config(['cache.default' => 'array']);

// Test cases
$testCases = [
    [
        'caption' => 'Welcome to SnapDraft',
        'title' => null,
        'description' => null,
        'expected_complexity' => 'simple',
    ],
    [
        'caption' => 'Join us at LCI Career Expo 2024 on March 15th at Convention Center',
        'title' => 'Career Networking Event',
        'description' => 'Meet industry leaders and explore opportunities',
        'expected_complexity' => 'moderate',
    ],
    [
        'caption' => 'Premium Wireless Headphones - $299 - 50% Off - 4.8 stars - Free Shipping',
        'title' => 'Tech Sale',
        'description' => 'High-quality audio equipment at unbeatable prices',
        'expected_complexity' => 'complex',
    ],
];

echo "\n=== CaptionAnalyzer Test Suite ===\n\n";

$analyzer = app(\App\Services\CaptionAnalyzer::class);

foreach ($testCases as $index => $test) {
    echo "Test Case " . ($index + 1) . ":\n";
    echo "  Caption: {$test['caption']}\n";
    echo "  Title: " . ($test['title'] ?? 'null') . "\n";
    echo "  Expected Complexity: {$test['expected_complexity']}\n";
    
    try {
        $result = $analyzer->analyze(
            $test['caption'],
            $test['title'],
            $test['description'],
            'square'
        );
        
        echo "  ✓ Analysis completed\n";
        echo "  → Detected Complexity: {$result['layout_complexity']}\n";
        echo "  → Intent: {$result['intent']}\n";
        echo "  → Tone: {$result['tone']}\n";
        
        // Count detected elements
        $elementCount = 0;
        foreach ($result['required_elements'] as $category => $elements) {
            if (is_array($elements)) {
                foreach ($elements as $element => $present) {
                    if ($present === true || $present > 0) {
                        $elementCount++;
                    }
                }
            }
        }
        echo "  → Elements Detected: $elementCount\n";
        
        // Show priority elements
        $priorities = implode(', ', $result['priority_elements'] ?? []);
        echo "  → Priority Elements: $priorities\n";
        
        $match = ($result['layout_complexity'] === $test['expected_complexity']) ? '✓' : '✗';
        echo "  $match Complexity matches expectation\n";
        
    } catch (\Exception $e) {
        echo "  ✗ Error: {$e->getMessage()}\n";
    }
    
    echo "\n";
}

echo "=== Test Suite Complete ===\n\n";
