<?php

namespace Tests\Feature\Quality;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\TestHelpers\AITestHelper;

/**
 * User Scenario Tests
 * Tests real-world usage scenarios from different user types
 * (SOC: Social Media Manager, SMB: Small Business, AGY: Agency, ECO: E-commerce)
 */
class UserScenarioTest extends TestCase
{
    use RefreshDatabase, AITestHelper;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeStorage();
        $this->user = $this->createUserWithCredits(200);
    }

    /**
     * SOC-001: Weekly Content Creation
     * 
     * Social Media Manager creates 7 Instagram posts
     * 
     * Priority: High
     */
    public function test_weekly_content_creation(): void
    {
        $this->mockGeminiSuccess();

        // Social media manager uploads best brand posts as references
        $referenceImages = $this->createFakeReferenceImages(5);

        // Create 7 daily posts
        $weeklyPosts = [
            ['title' => 'Monday Motivation', 'description' => 'Inspirational quote about success'],
            ['title' => 'Product Tuesday', 'description' => 'Showcase our latest product'],
            ['title' => 'Wellness Wednesday', 'description' => 'Tips for healthy work-life balance'],
            ['title' => 'Throwback Thursday', 'description' => 'Company milestone achievement'],
            ['title' => 'Feature Friday', 'description' => 'Customer success story'],
            ['title' => 'Saturday Special', 'description' => 'Weekend offer announcement'],
            ['title' => 'Sunday Funday', 'description' => 'Behind the scenes team photo'],
        ];

        $csvFile = $this->createTestCSV(array_map(function($post) {
            return [
                'title' => $post['title'],
                'description' => $post['description'],
                'format' => 'square', // Instagram format
            ];
        }, $weeklyPosts));

        $response = $this->actingAs($this->user)
            ->post(route('projects.wizards.csv.store'), [
                'project_name' => 'Weekly Instagram Content',
                'csv_file' => $csvFile,
                'reference_images' => $referenceImages,
            ]);

        $response->assertRedirect();

        $project = Project::latest()->first();
        
        // Should have 7 consistent, on-brand posts ready for scheduling
        $this->assertEquals(7, $project->images()->count());
        $this->assertEquals(5, $project->brandReferences()->count());
        
        // All images should be square format (stored in metadata)
        $squareImages = $project->images()->get()->filter(fn($img) => ($img->metadata['format'] ?? null) === 'square')->count();
        $this->assertEquals(7, $squareImages);
    }

    /**
     * SOC-002: Carousel Content
     * 
     * Generate numbered carousel images (multi-part series)
     * 
     * Priority: Medium
     */
    public function test_carousel_content_generation(): void
    {
        $this->mockGeminiSuccess();

        $carouselParts = [
            ['title' => 'Part 1', 'description' => 'Introduction: Our 3 core services'],
            ['title' => 'Part 2', 'description' => 'Service details and benefits'],
            ['title' => 'Part 3', 'description' => 'Call to action and contact info'],
        ];

        $csvFile = $this->createTestCSV(array_map(function($part) {
            return [
                'title' => $part['title'],
                'description' => $part['description'],
                'format' => 'square',
            ];
        }, $carouselParts));

        $referenceImages = $this->createFakeReferenceImages(5);

        $response = $this->actingAs($this->user)
            ->post(route('projects.wizards.csv.store'), [
                'project_name' => '3-Part Carousel',
                'csv_file' => $csvFile,
                'reference_images' => $referenceImages,
            ]);

        $response->assertRedirect();

        $project = Project::latest()->first();
        
        // Should have 3 images that work as a sequence
        $this->assertEquals(3, $project->images()->count());
    }

    /**
     * SMB-001: Product to Ad Conversion
     * 
     * Small business turns product photos into professional ads
     * 
     * Priority: High
     */
    public function test_product_to_ad_conversion(): void
    {
        $this->mockGeminiSuccess();

        $referenceImages = $this->createFakeReferenceImages(5);

        $response = $this->actingAs($this->user)
            ->post(route('projects.wizards.images.store'), [
                'project_name' => 'Product Ad Campaign',
                'reference_images' => $referenceImages,
                'content_description' => 'Facebook ad with happy customers using the product',
                'format' => 'square',
            ]);

        $response->assertRedirect();

        $project = Project::latest()->first();
        
        // Should generate professional-looking ads with products featured
        $this->assertGreaterThan(0, $project->images()->count());
    }

    /**
     * SMB-002: Amateur to Pro
     * 
     * Small business improves personal photos to professional quality
     * 
     * Priority: Medium
     */
    public function test_amateur_to_professional(): void
    {
        $this->mockGeminiSuccess();

        $amateurPhotos = $this->createFakeReferenceImages(5, 'lowquality');

        $response = $this->actingAs($this->user)
            ->post(route('projects.wizards.text.store'), [
                'project_name' => 'Professional Upgrade',
                'reference_images' => $amateurPhotos,
                'idea_description' => 'Professional brand image with polished composition and lighting',
                'format' => 'landscape',
            ]);

        $response->assertRedirect();

        $project = Project::latest()->first();
        
        // Output should look professionally shot
        $this->assertEquals(1, $project->images()->count());
    }

    /**
     * AGY-001: Client Pitch Concepts
     * 
     * Agency creates 3 different campaign concepts
     * 
     * Priority: High
     */
    public function test_client_pitch_concepts(): void
    {
        $this->mockGeminiSuccess();

        $clientReferences = $this->createFakeReferenceImages(5);

        $concepts = [
            'Bold and energetic - vibrant colors, dynamic composition',
            'Elegant and sophisticated - muted tones, minimalist design',
            'Fun and playful - bright colors, whimsical elements',
        ];

        foreach ($concepts as $index => $concept) {
            $response = $this->actingAs($this->user)
                ->post(route('projects.wizards.text.store'), [
                    'project_name' => 'Client Concept ' . ($index + 1),
                    'reference_images' => $clientReferences,
                    'idea_description' => $concept,
                    'format' => 'landscape',
                ]);

            $response->assertRedirect();
        }

        // Should have 3 distinct but brand-appropriate concepts
        $this->assertEquals(3, Project::count());
    }

    /**
     * ECO-001: Lifestyle from Product Shots
     * 
     * E-commerce creates lifestyle images from product-only photos
     * 
     * Priority: High
     */
    public function test_lifestyle_from_product_shots(): void
    {
        $this->mockGeminiSuccess();

        $referenceImages = $this->createFakeReferenceImages(5);

        $response = $this->actingAs($this->user)
            ->post(route('projects.wizards.images.store'), [
                'project_name' => 'Lifestyle Product Images',
                'reference_images' => $referenceImages,
                'content_description' => 'Product realistically placed in modern home setting with natural lighting',
                'format' => 'square',
            ]);

        $response->assertRedirect();

        $project = Project::latest()->first();
        
        // Product should be realistically placed in lifestyle context
        $this->assertGreaterThan(0, $project->images()->count());
    }

    /**
     * ECO-002: Product Badges
     * 
     * Generate sale/promotional badges matching brand style
     * 
     * Priority: Medium
     */
    public function test_product_badges_generation(): void
    {
        $this->mockGeminiSuccess();

        $brandReferences = $this->createFakeReferenceImages(5);

        $badges = [
            'Sale badge - 30% OFF in bold text',
            'New arrival badge with star icon',
            'Limited edition label with premium feel',
        ];

        foreach ($badges as $badgePrompt) {
            $response = $this->actingAs($this->user)
                ->post(route('projects.wizards.text.store'), [
                    'project_name' => 'Badge: ' . substr($badgePrompt, 0, 20),
                    'reference_images' => $brandReferences,
                    'idea_description' => $badgePrompt,
                    'format' => 'square',
                ]);

            $response->assertRedirect();
        }

        // Should have 3 professional badges matching brand style
        $this->assertEquals(3, Project::count());
    }

    /**
     * Additional test: Multi-format campaign
     */
    public function test_multi_platform_campaign(): void
    {
        $this->mockGeminiSuccess();

        $referenceImages = $this->createFakeReferenceImages(5);

        $platforms = [
            ['format' => 'square', 'platform' => 'Instagram Post'],
            ['format' => 'portrait', 'platform' => 'Instagram Story'],
            ['format' => 'landscape', 'platform' => 'Facebook Cover'],
        ];

        $campaignRows = array_map(function($platform) {
            return [
                'title' => $platform['platform'],
                'description' => 'Product launch campaign for ' . $platform['platform'],
                'format' => $platform['format'],
            ];
        }, $platforms);

        $csvFile = $this->createTestCSV($campaignRows);

        $response = $this->actingAs($this->user)
            ->post(route('projects.wizards.csv.store'), [
                'project_name' => 'Multi-Platform Campaign',
                'csv_file' => $csvFile,
                'reference_images' => $referenceImages,
            ]);

        $response->assertRedirect();

        $project = Project::latest()->first();
        
        // Should have all 3 platform-specific formats (stored in metadata)
        $this->assertEquals(3, $project->images()->count());
        $images = $project->images()->get();
        $this->assertEquals(1, $images->filter(fn($img) => ($img->metadata['format'] ?? null) === 'square')->count());
        $this->assertEquals(1, $images->filter(fn($img) => ($img->metadata['format'] ?? null) === 'portrait')->count());
        $this->assertEquals(1, $images->filter(fn($img) => ($img->metadata['format'] ?? null) === 'landscape')->count());
    }
}
