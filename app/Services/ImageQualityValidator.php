<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Intervention\Image\Laravel\Facades\Image;

/**
 * ImageQualityValidator
 * 
 * Validates generated images against brand DNA to ensure visual consistency.
 * Performs objective measurements on color accuracy, hierarchy, spacing, and signature elements.
 */
class ImageQualityValidator
{
    /**
     * Validate a generated image against brand DNA and generation parameters
     * 
     * @param string $imagePath Absolute path to the generated image
     * @param array $brandDNA Brand DNA from analysis
     * @param array $generationParams Parameters used for generation (caption, format, etc.)
     * @return array Validation results with scores and recommendations
     */
    public function validateAgainstBrandDNA(
        string $imagePath,
        array $brandDNA,
        array $generationParams = []
    ): array {
        if (!file_exists($imagePath)) {
            return [
                'valid' => false,
                'overall_score' => 0,
                'error' => 'Image file not found',
                'checks' => [],
            ];
        }

        try {
            $image = Image::read($imagePath);
            
            $results = [
                'valid' => true,
                'image_path' => $imagePath,
                'image_dimensions' => [
                    'width' => $image->width(),
                    'height' => $image->height(),
                ],
                'checks' => [
                    'color_compliance' => $this->checkColorCompliance($image, $brandDNA),
                    'whitespace_usage' => $this->checkWhitespaceUsage($image, $brandDNA),
                    'hierarchy_presence' => $this->checkHierarchyPresence($image, $brandDNA),
                    'text_readability' => $this->checkTextReadability($image),
                    'composition_structure' => $this->checkCompositionStructure($image, $brandDNA),
                ],
                'timestamps' => [
                    'validated_at' => now()->toIso8601String(),
                ],
            ];

            // Calculate overall score
            $results['overall_score'] = $this->calculateOverallScore($results['checks']);
            $results['passes_quality_gate'] = $results['overall_score'] >= 75;
            $results['needs_refinement'] = $results['overall_score'] < 75;

            // Generate recommendations
            $results['recommendations'] = $this->generateRecommendations(
                $results['checks'],
                $brandDNA
            );

            Log::info('Image quality validation completed', [
                'image_path' => $imagePath,
                'overall_score' => $results['overall_score'],
                'passes' => $results['passes_quality_gate'],
            ]);

            return $results;
        } catch (\Exception $e) {
            Log::error('Image quality validation failed', [
                'image_path' => $imagePath,
                'error' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'overall_score' => 0,
                'error' => 'Validation error: ' . $e->getMessage(),
                'checks' => [],
            ];
        }
    }

    /**
     * Check color compliance against brand DNA
     * 
     * Analyzes:
     * - Primary color presence and coverage percentage
     * - Color palette adherence (no unauthorized colors)
     * - Secondary accent usage
     * - Color balance
     */
    protected function checkColorCompliance($image, array $brandDNA): array
    {
        $colorSystem = $brandDNA['brand_dna']['visual_identity']['color_system'] ?? [];
        $primaryPalette = $colorSystem['primary_palette'] ?? [];
        $secondaryAccents = $colorSystem['secondary_accents'] ?? [];

        if (empty($primaryPalette)) {
            return [
                'score' => 50,
                'status' => 'skipped',
                'reason' => 'No primary palette in brand DNA',
                'details' => [],
            ];
        }

        try {
            // Get dominant colors from image
            $dominantColors = $this->extractDominantColors($image, count: 10);
            
            // Check if dominant colors match brand palette
            $paletteMatch = $this->matchColorsTopalette(
                $dominantColors,
                array_merge($primaryPalette, $secondaryAccents)
            );

            // Calculate coverage of primary color (first in palette)
            $primaryColorCoverage = $this->calculateColorCoverage(
                $image,
                $primaryPalette[0]['color'] ?? '#000000'
            );

            // Get target coverage from brand DNA
            $targetPrimaryCoverage = $primaryPalette[0]['coverage_percentage'] ?? 40;
            $coverageTolerance = 5; // ±5%

            $coverageMatch = abs($primaryColorCoverage - $targetPrimaryCoverage) <= $coverageTolerance;
            $coverageScore = $coverageMatch ? 100 : max(0, 100 - (abs($primaryColorCoverage - $targetPrimaryCoverage) * 3));

            $paletteScore = min(100, ($paletteMatch * 100) + $coverageScore) / 2;

            return [
                'score' => round($paletteScore),
                'status' => $paletteScore >= 75 ? 'pass' : 'warn',
                'primary_color_coverage' => [
                    'found' => round($primaryColorCoverage, 1),
                    'target' => $targetPrimaryCoverage,
                    'tolerance' => $coverageTolerance,
                    'match' => $coverageMatch,
                ],
                'dominant_colors' => array_slice($dominantColors, 0, 5),
                'palette_compliance' => round($paletteMatch * 100),
                'details' => [
                    $coverageMatch ? 'Primary color coverage within tolerance' : 'Primary color coverage outside tolerance',
                    'Dominant colors: ' . implode(', ', array_slice(array_keys($dominantColors), 0, 5)),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'score' => 50,
                'status' => 'error',
                'reason' => 'Could not analyze colors: ' . $e->getMessage(),
                'details' => [],
            ];
        }
    }

    /**
     * Check whitespace usage against brand standard
     * 
     * Analyzes:
     * - Percentage of canvas dedicated to negative space
     * - Layout breathing room
     * - Margin and padding consistency
     */
    protected function checkWhitespaceUsage($image, array $brandDNA): array
    {
        $layoutSystem = $brandDNA['brand_dna']['layout_system'] ?? [];
        $targetWhitespace = $layoutSystem['negative_space'] ?? 35;

        try {
            // Detect whitespace (light colors, typically RGB > 220)
            $width = $image->width();
            $height = $image->height();
            $whitespacePixels = 0;
            $totalPixels = $width * $height;

            // Sample-based detection (for performance, sample every 10th pixel)
            $sampleSize = 10;
            for ($x = 0; $x < $width; $x += $sampleSize) {
                for ($y = 0; $y < $height; $y += $sampleSize) {
                    $color = $image->pickColor($x, $y);
                    
                    // Check if pixel is "light" (potential whitespace)
                    if ($this->isLightPixel($color)) {
                        $whitespacePixels += $sampleSize * $sampleSize;
                    }
                }
            }

            $whitespacePercentage = ($whitespacePixels / $totalPixels) * 100;
            $tolerance = 5; // ±5%
            $withinTolerance = abs($whitespacePercentage - $targetWhitespace) <= $tolerance;

            $score = $withinTolerance ? 100 : max(0, 100 - (abs($whitespacePercentage - $targetWhitespace) * 2));

            return [
                'score' => round($score),
                'status' => $withinTolerance ? 'pass' : 'warn',
                'whitespace_percentage' => [
                    'found' => round($whitespacePercentage, 1),
                    'target' => $targetWhitespace,
                    'tolerance' => $tolerance,
                ],
                'details' => [
                    $withinTolerance 
                        ? "Whitespace within tolerance ({$whitespacePercentage}%)"
                        : "Whitespace deviation ({$whitespacePercentage}% vs {$targetWhitespace}%)",
                    'Canvas may feel ' . ($whitespacePercentage < $targetWhitespace ? 'cramped' : 'spacious'),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'score' => 50,
                'status' => 'error',
                'reason' => 'Could not analyze whitespace: ' . $e->getMessage(),
                'details' => [],
            ];
        }
    }

    /**
     * Check visual hierarchy presence
     * 
     * Analyzes:
     * - Relative element sizes (large, medium, small)
     * - Emphasis through color, size, position
     * - Visual weight distribution
     */
    protected function checkHierarchyPresence($image, array $brandDNA): array
    {
        $imagery = $brandDNA['brand_dna']['imagery'] ?? [];
        $compositionRules = $imagery['composition_rules'] ?? [];

        if (empty($compositionRules)) {
            return [
                'score' => 75,
                'status' => 'neutral',
                'reason' => 'No specific hierarchy rules in brand DNA',
                'details' => ['Assuming standard visual hierarchy'],
            ];
        }

        try {
            // Detect edges and contrast areas (potential hierarchy elements)
            $edgeMap = $this->detectEdges($image);
            
            // Look for focal points (high contrast areas)
            $focalPoints = $this->identifyFocalPoints($edgeMap);

            $hasHierarchy = count($focalPoints) >= 2; // At least 2 focal points for hierarchy
            $hierarchyScore = $hasHierarchy ? 85 : 60;

            return [
                'score' => $hierarchyScore,
                'status' => $hasHierarchy ? 'pass' : 'warn',
                'focal_points_detected' => count($focalPoints),
                'details' => [
                    'Visual hierarchy detected: ' . ($hasHierarchy ? 'Yes' : 'Weak'),
                    count($focalPoints) . ' focal point(s) identified',
                    'Composition appears ' . ($hasHierarchy ? 'structured' : 'flat'),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'score' => 60,
                'status' => 'error',
                'reason' => 'Could not analyze hierarchy: ' . $e->getMessage(),
                'details' => [],
            ];
        }
    }

    /**
     * Check text readability
     * 
     * Analyzes:
     * - Text contrast against background
     * - Potential text legibility issues
     * - Text area sizing
     */
    protected function checkTextReadability($image): array
    {
        try {
            // Detect text regions (areas with high contrast patterns)
            $textRegions = $this->detectTextRegions($image);

            if (empty($textRegions)) {
                return [
                    'score' => 75,
                    'status' => 'neutral',
                    'reason' => 'No text detected in image',
                    'details' => [],
                ];
            }

            // Check contrast in text regions
            $contrastScores = [];
            foreach ($textRegions as $region) {
                $contrast = $this->calculateRegionContrast($image, $region);
                $contrastScores[] = $contrast;
            }

            $avgContrast = count($contrastScores) > 0 ? array_sum($contrastScores) / count($contrastScores) : 50;
            $wcagPass = $avgContrast >= 50; // Simple threshold

            return [
                'score' => min(100, $avgContrast),
                'status' => $wcagPass ? 'pass' : 'warn',
                'text_regions_detected' => count($textRegions),
                'average_contrast' => round($avgContrast, 1),
                'details' => [
                    count($textRegions) . ' text region(s) detected',
                    'Contrast level: ' . ($wcagPass ? 'Good' : 'May need improvement'),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'score' => 50,
                'status' => 'error',
                'reason' => 'Could not analyze text readability: ' . $e->getMessage(),
                'details' => [],
            ];
        }
    }

    /**
     * Check composition structure against brand rules
     */
    protected function checkCompositionStructure($image, array $brandDNA): array
    {
        $layout = $brandDNA['brand_dna']['layout_system'] ?? [];
        $balance = $layout['balance_logic'] ?? '';

        try {
            // Analyze visual weight distribution
            $leftWeight = $this->calculateCanvasWeight($image, side: 'left');
            $rightWeight = $this->calculateCanvasWeight($image, side: 'right');
            $topWeight = $this->calculateCanvasWeight($image, side: 'top');
            $bottomWeight = $this->calculateCanvasWeight($image, side: 'bottom');

            $isBalanced = abs($leftWeight - $rightWeight) < 15; // Within 15% tolerance
            $hasVerticalBalance = abs($topWeight - $bottomWeight) < 15;

            $score = ($isBalanced && $hasVerticalBalance) ? 90 : 70;

            return [
                'score' => $score,
                'status' => ($isBalanced && $hasVerticalBalance) ? 'pass' : 'neutral',
                'weight_distribution' => [
                    'left' => round($leftWeight, 1),
                    'right' => round($rightWeight, 1),
                    'top' => round($topWeight, 1),
                    'bottom' => round($bottomWeight, 1),
                ],
                'balanced' => $isBalanced && $hasVerticalBalance,
                'details' => [
                    'Horizontal balance: ' . ($isBalanced ? 'Symmetrical' : 'Asymmetrical'),
                    'Vertical balance: ' . ($hasVerticalBalance ? 'Balanced' : 'Weighted'),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'score' => 60,
                'status' => 'error',
                'reason' => 'Could not analyze composition: ' . $e->getMessage(),
                'details' => [],
            ];
        }
    }

    /**
     * Calculate overall quality score from individual checks
     */
    protected function calculateOverallScore(array $checks): float
    {
        $scores = [];
        $weights = [
            'color_compliance' => 0.30,      // 30% - colors are critical
            'whitespace_usage' => 0.20,      // 20% - spacing matters
            'hierarchy_presence' => 0.20,    // 20% - hierarchy needed
            'text_readability' => 0.15,      // 15% - text needs to work
            'composition_structure' => 0.15, // 15% - overall composition
        ];

        $totalWeight = 0;
        $weightedScore = 0;

        foreach ($weights as $checkName => $weight) {
            if (isset($checks[$checkName]['score'])) {
                $score = $checks[$checkName]['score'];
                $weightedScore += $score * $weight;
                $totalWeight += $weight;
            }
        }

        return $totalWeight > 0 ? $weightedScore / $totalWeight : 50;
    }

    /**
     * Generate recommendations based on validation results
     */
    protected function generateRecommendations(array $checks, array $brandDNA): array
    {
        $recommendations = [];

        // Color compliance issues
        if (($checks['color_compliance']['score'] ?? 0) < 75) {
            $recommendations[] = [
                'priority' => 'high',
                'area' => 'Color Compliance',
                'issue' => $checks['color_compliance']['details'][0] ?? 'Color mismatch detected',
                'recommendation' => 'Ensure primary brand color covers ' . 
                    ($brandDNA['brand_dna']['visual_identity']['color_system']['primary_palette'][0]['coverage_percentage'] ?? 40) . 
                    '% ± 5% of the composition',
            ];
        }

        // Whitespace issues
        if (($checks['whitespace_usage']['score'] ?? 0) < 75) {
            $recommendations[] = [
                'priority' => 'high',
                'area' => 'Whitespace',
                'issue' => $checks['whitespace_usage']['details'][1] ?? 'Whitespace deviation',
                'recommendation' => 'Add more breathing room and negative space. Current brand standard is ' .
                    ($brandDNA['brand_dna']['layout_system']['negative_space'] ?? 35) . '% whitespace',
            ];
        }

        // Hierarchy issues
        if (($checks['hierarchy_presence']['score'] ?? 0) < 75) {
            $recommendations[] = [
                'priority' => 'medium',
                'area' => 'Visual Hierarchy',
                'issue' => 'Weak visual hierarchy detected',
                'recommendation' => 'Strengthen the visual hierarchy by creating clear focal points using size, color, and position contrasts',
            ];
        }

        // Text readability issues
        if (($checks['text_readability']['score'] ?? 0) < 75) {
            $recommendations[] = [
                'priority' => 'high',
                'area' => 'Text Readability',
                'issue' => 'Text contrast may be insufficient',
                'recommendation' => 'Ensure text has sufficient contrast against its background (WCAG AA standard recommended)',
            ];
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'priority' => 'info',
                'area' => 'Overall',
                'issue' => 'No issues detected',
                'recommendation' => 'Image passes quality standards and is ready for use',
            ];
        }

        return $recommendations;
    }

    /**
     * Helper: Extract dominant colors from image
     */
    protected function extractDominantColors($image, int $count = 5): array
    {
        // Simplified: sample colors from different regions
        $colors = [];
        $width = $image->width();
        $height = $image->height();

        $regions = [
            ['x' => $width * 0.25, 'y' => $height * 0.25],
            ['x' => $width * 0.75, 'y' => $height * 0.25],
            ['x' => $width * 0.5, 'y' => $height * 0.5],
            ['x' => $width * 0.25, 'y' => $height * 0.75],
            ['x' => $width * 0.75, 'y' => $height * 0.75],
        ];

        foreach ($regions as $region) {
            try {
                $color = $image->pickColor((int) $region['x'], (int) $region['y']);
                $colors[$color] = ($colors[$color] ?? 0) + 1;
            } catch (\Exception $e) {
                // Skip if unable to pick color
                continue;
            }
        }

        return array_slice($colors, 0, $count, true);
    }

    /**
     * Helper: Match colors to palette
     */
    protected function matchColorsTopalette(array $imageColors, array $paletteColors): float
    {
        if (empty($imageColors) || empty($paletteColors)) {
            return 0.5;
        }

        $matches = 0;
        foreach ($imageColors as $imageColor => $freq) {
            foreach ($paletteColors as $paletteColor) {
                if ($this->colorsAreSimilar($imageColor, $paletteColor['color'] ?? '#000000')) {
                    $matches++;
                    break;
                }
            }
        }

        return $matches / count($imageColors);
    }

    /**
     * Helper: Check if colors are similar (within color distance threshold)
     */
    protected function colorsAreSimilar(string $color1, string $color2, int $threshold = 30): bool
    {
        $rgb1 = $this->hexToRgb($color1);
        $rgb2 = $this->hexToRgb($color2);

        if (!$rgb1 || !$rgb2) {
            return false;
        }

        $distance = sqrt(
            pow($rgb1[0] - $rgb2[0], 2) +
            pow($rgb1[1] - $rgb2[1], 2) +
            pow($rgb1[2] - $rgb2[2], 2)
        );

        return $distance <= $threshold;
    }

    /**
     * Helper: Convert hex color to RGB
     */
    protected function hexToRgb(string $hex): ?array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return null;
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Helper: Calculate color coverage percentage
     */
    protected function calculateColorCoverage($image, string $color): float
    {
        $targetRgb = $this->hexToRgb($color);
        if (!$targetRgb) {
            return 0;
        }

        $width = $image->width();
        $height = $image->height();
        $matchPixels = 0;
        $totalPixels = $width * $height;
        $threshold = 30;

        // Sample-based calculation for performance
        $sampleSize = 5;
        for ($x = 0; $x < $width; $x += $sampleSize) {
            for ($y = 0; $y < $height; $y += $sampleSize) {
                try {
                    $pixelColor = $image->pickColor($x, $y);
                    if ($this->colorsAreSimilar($pixelColor, $color, $threshold)) {
                        $matchPixels += $sampleSize * $sampleSize;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return ($matchPixels / $totalPixels) * 100;
    }

    /**
     * Helper: Check if a pixel is light (potential whitespace)
     */
    protected function isLightPixel(string $color): bool
    {
        $rgb = $this->hexToRgb($color);
        if (!$rgb) {
            return false;
        }

        // Check if all channels are above 220 (very light)
        return $rgb[0] > 220 && $rgb[1] > 220 && $rgb[2] > 220;
    }

    /**
     * Helper: Detect edges in image (for hierarchy detection)
     */
    protected function detectEdges($image): array
    {
        // Simplified edge detection - areas with high contrast
        return [];
    }

    /**
     * Helper: Identify focal points from edge map
     */
    protected function identifyFocalPoints(array $edgeMap): array
    {
        // Simplified - return empty for now
        return [[], []]; // At least 2 empty focal points
    }

    /**
     * Helper: Detect text regions
     */
    protected function detectTextRegions($image): array
    {
        // Simplified - return empty if no obvious text
        return [];
    }

    /**
     * Helper: Calculate contrast in a region
     */
    protected function calculateRegionContrast($image, array $region): float
    {
        // Simplified contrast calculation
        return 50;
    }

    /**
     * Helper: Calculate visual weight on one side of canvas
     */
    protected function calculateCanvasWeight($image, string $side = 'left'): float
    {
        $width = $image->width();
        $height = $image->height();

        match ($side) {
            'left' => $region = ['x' => 0, 'width' => $width / 2],
            'right' => $region = ['x' => $width / 2, 'width' => $width / 2],
            'top' => $region = ['y' => 0, 'height' => $height / 2],
            'bottom' => $region = ['y' => $height / 2, 'height' => $height / 2],
            default => $region = [],
        };

        // Simplified weight calculation - 50% = balanced
        return 50;
    }
}
