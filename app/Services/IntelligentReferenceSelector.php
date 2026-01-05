<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class IntelligentReferenceSelector
{
    /**
     * Select the best reference images for generation based on caption requirements.
     *
     * @param array $brandAnalysis The full brand analysis with style_clusters and image_analysis
     * @param array $captionAnalysis The caption analysis with required_elements
     * @param int $maxReferences Maximum number of references to select (default: 5)
     * @return array ['selected' => [...], 'mismatch_warning' => bool, 'mismatch_details' => string]
     */
    public function selectBestReferences(array $brandAnalysis, array $captionAnalysis, int $maxReferences = 5): array
    {
        // Support both old 'images' and new 'image_analysis' keys
        $images = $brandAnalysis['image_analysis'] ?? $brandAnalysis['images'] ?? [];
        $styleClusters = $brandAnalysis['style_clusters'] ?? [];
        $requiredElements = $captionAnalysis['required_elements'] ?? [];
        $complexity = $captionAnalysis['layout_complexity'] ?? 'moderate';

        if (empty($images)) {
            return [
                'selected' => [],
                'mismatch_warning' => true,
                'mismatch_details' => 'No reference images available for analysis.',
            ];
        }

        // Score each image
        $scoredImages = [];
        foreach ($images as $image) {
            $score = $this->scoreImage($image, $requiredElements, $complexity, $styleClusters, $captionAnalysis);
            $scoredImages[] = array_merge($image, ['match_score' => $score]);
        }

        // Sort by score (highest first)
        usort($scoredImages, function ($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });

        // Select top N
        $selected = array_slice($scoredImages, 0, $maxReferences);

        // Check for mismatches
        $mismatchCheck = $this->detectMismatches($selected, $requiredElements, $complexity);

        Log::info('Intelligent reference selection completed', [
            'total_images' => count($images),
            'selected_count' => count($selected),
            'top_score' => $selected[0]['match_score'] ?? 0,
            'lowest_score' => end($selected)['match_score'] ?? 0,
            'mismatch_warning' => $mismatchCheck['warning'],
        ]);

        return [
            'selected' => $selected,
            'mismatch_warning' => $mismatchCheck['warning'],
            'mismatch_details' => $mismatchCheck['details'],
        ];
    }

    /**
     * Score an image based on how well it matches the caption requirements.
     *
     * Scoring weights:
     * - Style cluster match: 40%
     * - Element match: 30%
     * - Layout complexity match: 20%
     * - Image quality: 10%
     */
    private function scoreImage(array $image, array $requiredElements, string $complexity, array $styleClusters, array $captionAnalysis): float
    {
        $score = 0;

        // 1. Style cluster match (40 points)
        $styleScore = $this->scoreStyleMatch($image, $styleClusters, $captionAnalysis);
        $score += $styleScore * 0.4;

        // 2. Element match (30 points)
        $elementScore = $this->scoreElementMatch($image, $requiredElements);
        $score += $elementScore * 0.3;

        // 3. Layout complexity match (20 points)
        $complexityScore = $this->scoreComplexityMatch($image, $complexity);
        $score += $complexityScore * 0.2;

        // 4. Image quality (10 points)
        $qualityScore = $this->scoreQuality($image);
        $score += $qualityScore * 0.1;

        return round($score, 2);
    }

    /**
     * Score style cluster match.
     */
    private function scoreStyleMatch(array $image, array $styleClusters, array $captionAnalysis): float
    {
        $imageClusterId = $image['cluster_id'] ?? null;
        $stylePreference = $captionAnalysis['style_preference'] ?? null;

        if (empty($styleClusters)) {
            return 80; // Default if no clusters
        }

        // Find the cluster for this image
        $imageCluster = null;
        foreach ($styleClusters as $cluster) {
            if ($cluster['cluster_id'] === $imageClusterId) {
                $imageCluster = $cluster;
                break;
            }
        }

        if (!$imageCluster) {
            return 50; // Not in any cluster
        }

        // If user has style preference, match it
        if ($stylePreference) {
            $clusterName = strtolower($imageCluster['name'] ?? '');
            $preference = strtolower($stylePreference);
            if (str_contains($clusterName, $preference)) {
                return 100; // Perfect style match
            }
        }

        // Otherwise, prefer the largest/most coherent cluster
        return $imageCluster['coherence_score'] ?? 70;
    }

    /**
     * Score element match between image and caption requirements.
     */
    private function scoreElementMatch(array $image, array $requiredElements): float
    {
        $imageElements = $image['elements_detected'] ?? [];
        $matchCount = 0;
        $requiredCount = 0;
        $mismatchPenalty = 0;

        foreach ($requiredElements as $category => $elements) {
            if (!is_array($elements)) {
                continue;
            }

            $imageCategory = $imageElements[$category] ?? [];

            foreach ($elements as $elementKey => $required) {
                if ($required === true || $required > 0) {
                    $requiredCount++;
                    $hasElement = ($imageCategory[$elementKey] ?? false);

                    if ($hasElement === true || $hasElement > 0) {
                        $matchCount++;
                    }
                }

                // Penalty for having elements that aren't required (complexity mismatch)
                if (($required === false || $required === 0) && ($imageCategory[$elementKey] ?? false) === true) {
                    $mismatchPenalty += 2;
                }
            }
        }

        if ($requiredCount === 0) {
            return 100; // No specific requirements
        }

        $matchPercentage = ($matchCount / $requiredCount) * 100;

        // Apply penalty (max penalty: 30 points)
        return max(0, $matchPercentage - min($mismatchPenalty, 30));
    }

    /**
     * Score layout complexity match.
     */
    private function scoreComplexityMatch(array $image, string $requiredComplexity): float
    {
        $imageComplexity = $image['layout_complexity'] ?? 'moderate';

        $complexityMap = ['simple' => 1, 'moderate' => 2, 'complex' => 3];
        $imageLvl = $complexityMap[$imageComplexity] ?? 2;
        $requiredLvl = $complexityMap[$requiredComplexity] ?? 2;

        $diff = abs($imageLvl - $requiredLvl);

        // Perfect match: 100, 1 level off: 70, 2 levels off: 40
        return match ($diff) {
            0 => 100,
            1 => 70,
            2 => 40,
            default => 40,
        };
    }

    /**
     * Score image quality.
     */
    private function scoreQuality(array $image): float
    {
        $quality = $image['quality'] ?? 'good';

        return match ($quality) {
            'excellent' => 100,
            'good' => 80,
            'usable' => 60,
            'poor' => 30,
            default => 50,
        };
    }

    /**
     * Detect mismatches between selected references and caption requirements.
     */
    private function detectMismatches(array $selectedImages, array $requiredElements, string $complexity): array
    {
        $missingElements = [];
        $extraElements = [];
        $complexityMismatch = false;

        // Check element coverage
        foreach ($requiredElements as $category => $elements) {
            if (!is_array($elements)) {
                continue;
            }

            foreach ($elements as $elementKey => $required) {
                if ($required === true || $required > 0) {
                    // Check if ANY selected image has this element
                    $foundInAny = false;
                    foreach ($selectedImages as $image) {
                        $imageElements = $image['elements_detected'][$category] ?? [];
                        if (($imageElements[$elementKey] ?? false) === true || ($imageElements[$elementKey] ?? 0) > 0) {
                            $foundInAny = true;
                            break;
                        }
                    }

                    if (!$foundInAny) {
                        $missingElements[] = "$category.$elementKey";
                    }
                }
            }
        }

        // Check complexity mismatch
        $avgComplexity = $this->getAverageComplexity($selectedImages);
        if ($avgComplexity !== $complexity) {
            $complexityMismatch = true;
        }

        // Build warning message
        $warning = !empty($missingElements) || $complexityMismatch;
        $details = '';

        if (!empty($missingElements)) {
            $details .= 'Missing elements in references: ' . implode(', ', $missingElements) . '. ';
        }

        if ($complexityMismatch) {
            $details .= "Caption requires '$complexity' layout, but selected references are mostly '$avgComplexity'. ";
        }

        if ($warning) {
            $details .= 'The AI will attempt to generate the missing elements, but results may not match your brand style perfectly.';
        }

        return [
            'warning' => $warning,
            'details' => trim($details),
        ];
    }

    /**
     * Get the average complexity level of selected images.
     */
    private function getAverageComplexity(array $images): string
    {
        $complexityMap = ['simple' => 1, 'moderate' => 2, 'complex' => 3];
        $total = 0;
        $count = 0;

        foreach ($images as $image) {
            $complexity = $image['layout_complexity'] ?? 'moderate';
            $total += $complexityMap[$complexity] ?? 2;
            $count++;
        }

        if ($count === 0) {
            return 'moderate';
        }

        $avg = round($total / $count);

        return match ($avg) {
            1 => 'simple',
            2 => 'moderate',
            3 => 'complex',
            default => 'moderate',
        };
    }
}
