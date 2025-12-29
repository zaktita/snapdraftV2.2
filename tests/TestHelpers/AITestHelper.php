<?php

namespace Tests\TestHelpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

/**
 * Helper trait for AI-powered tests
 * Provides utilities for mocking AI services and generating test images
 */
trait AITestHelper
{
    /**
     * Mock successful Gemini API responses
     */
    protected function mockGeminiSuccess(array $options = []): void
    {
        // Create a real PNG image data
        $img = imagecreatetruecolor(100, 100);
        $bgColor = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $bgColor);
        
        ob_start();
        imagepng($img);
        $pngData = ob_get_clean();
        imagedestroy($img);
        
        $imageData = base64_encode($pngData);
        $tokensUsed = $options['tokensUsed'] ?? 200;
        
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => 'image/png',
                                        'data' => $imageData
                                    ]
                                ]
                            ]
                        ],
                        'finishReason' => 'STOP'
                    ]
                ],
                'usageMetadata' => [
                    'totalTokenCount' => $tokensUsed
                ]
            ], 200)
        ]);
    }

    /**
     * Mock Gemini brand analysis response
     */
    protected function mockGeminiBrandAnalysis(array $styleData = []): void
    {
        $defaultStyle = [
            'colors' => ['#FF0000', '#0000FF', '#00FF00'],
            'typography' => 'Modern sans-serif, bold headers',
            'style' => 'Minimalist, clean lines',
            'composition' => 'Centered layout with negative space',
            'mood' => 'Professional and trustworthy'
        ];
        
        $style = array_merge($defaultStyle, $styleData);
        
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode($style)
                                ]
                            ]
                        ],
                        'finishReason' => 'STOP'
                    ]
                ]
            ], 200)
        ]);
    }

    /**
     * Mock API rate limit error
     */
    protected function mockGeminiRateLimit(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 429,
                    'message' => 'Rate limit exceeded',
                    'status' => 'RESOURCE_EXHAUSTED'
                ]
            ], 429)
        ]);
    }

    /**
     * Mock API authentication error
     */
    protected function mockGeminiAuthError(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 401,
                    'message' => 'Invalid API key',
                    'status' => 'UNAUTHENTICATED'
                ]
            ], 401)
        ]);
    }

    /**
     * Create fake reference images
     * 
     * @param int $count Number of images to create
     * @param string $style Style identifier (cohesive, diverse, lowquality, textheavy)
     * @return array Array of UploadedFile instances
     */
    protected function createFakeReferenceImages(int $count = 5, string $style = 'cohesive'): array
    {
        $images = [];
        
        for ($i = 0; $i < $count; $i++) {
            $filename = "reference_{$style}_{$i}.jpg";
            
            // Create an actual image file using GD
            $width = $style === 'lowquality' ? 100 : 1920;
            $height = $style === 'lowquality' ? 100 : 1080;
            
            $im = imagecreatetruecolor($width, $height);
            if (!$im) {
                throw new \RuntimeException('Failed to create test image');
            }
            
            // Fill with a color based on index
            $colors = [
                imagecolorallocate($im, 255, 0, 0), // Red
                imagecolorallocate($im, 0, 255, 0), // Green
                imagecolorallocate($im, 0, 0, 255), // Blue
                imagecolorallocate($im, 255, 255, 0), // Yellow
                imagecolorallocate($im, 255, 0, 255), // Magenta
            ];
            imagefill($im, 0, 0, $colors[$i % 5]);
            
            // Save to temporary file
            $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
            imagejpeg($im, $tempPath, 90);
            imagedestroy($im);
            
            // Create UploadedFile from the actual file
            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $tempPath,
                $filename,
                'image/jpeg',
                null,
                true // Mark as test file
            );
            
            $images[] = $uploadedFile;
        }
        
        return $images;
    }
    
    /**
     * Legacy method for backward compatibility - creates single fake reference image
     */
    protected function createSingleFakeReferenceImage(string $filename = 'reference.jpg', int $width = 1920, int $height = 1080): \Illuminate\Http\UploadedFile
    {
        $images = $this->createFakeReferenceImages(1, 'cohesive');
        return $images[0];
    }

    /**
     * Create CSV file with test data
     * 
     * @param array $rows Array of rows, each with title, description, format
     * @param bool $malformed Whether to create malformed CSV
     * @return UploadedFile
     */
    protected function createTestCSV(array $rows, bool $malformed = false): UploadedFile
    {
        $content = "title,description,format\n";
        
        foreach ($rows as $row) {
            if ($malformed && rand(0, 1)) {
                // Randomly add empty or malformed rows
                $content .= "\n";
            } else {
                $title = $row['title'] ?? 'Test Title';
                $description = $row['description'] ?? 'Test Description';
                $format = $row['format'] ?? 'square';
                $content .= "\"{$title}\",\"{$description}\",{$format}\n";
            }
        }
        
        return UploadedFile::fake()->createWithContent('test.csv', $content);
    }

    /**
     * Create product images for testing
     */
    protected function createProductImages(int $count = 3): array
    {
        $images = [];
        for ($i = 0; $i < $count; $i++) {
            $images[] = UploadedFile::fake()->image("product_{$i}.png", 1000, 1000);
        }
        return $images;
    }

    /**
     * Assert that an image was generated with expected properties
     */
    protected function assertImageGenerated(array $imageData, string $format = 'square'): void
    {
        $this->assertArrayHasKey('image_data', $imageData);
        $this->assertArrayHasKey('mime_type', $imageData);
        $this->assertNotEmpty($imageData['image_data']);
        
        // Verify format-specific dimensions if present
        if (isset($imageData['metadata']['dimensions'])) {
            $dimensions = $imageData['metadata']['dimensions'];
            
            switch ($format) {
                case 'square':
                    $this->assertEquals($dimensions['width'], $dimensions['height']);
                    break;
                case 'landscape':
                    $this->assertGreaterThan($dimensions['height'], $dimensions['width']);
                    break;
                case 'portrait':
                    $this->assertGreaterThan($dimensions['width'], $dimensions['height']);
                    break;
            }
        }
    }

    /**
     * Assert brand consistency in generated output
     */
    protected function assertBrandConsistency(array $styleGuide, array $generatedMetadata): void
    {
        // Check if style guide elements are referenced in metadata
        if (isset($styleGuide['colors']) && isset($generatedMetadata['colors_used'])) {
            $this->assertNotEmpty(
                array_intersect($styleGuide['colors'], $generatedMetadata['colors_used']),
                'Generated image should use brand colors'
            );
        }
    }

    /**
     * Create a test user with credits
     */
    protected function createUserWithCredits(int $credits = 100): \App\Models\User
    {
        return \App\Models\User::factory()->create([
            'credits_remaining' => $credits
        ]);
    }

    /**
     * Fake storage for tests
     */
    protected function fakeStorage(): void
    {
        Storage::fake('public');
        Storage::fake('local');
    }
}
