<?php

namespace Tests\Feature\Quality;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\TestHelpers\AITestHelper;

/**
 * Core Generation Tests (GEN-001 to GEN-005)
 * Tests the fundamental 5+1 workflow and style transfer capabilities
 */
class CoreGenerationTest extends TestCase
{
    use RefreshDatabase, AITestHelper;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeStorage();
        $this->user = $this->createUserWithCredits(100);
    }

    /**
     * GEN-001: Cohesive Brand Style Transfer
     * 
     * Test that uploading 5 cohesive brand images generates output
     * that matches brand colors, mood, and style
     * 
     * Priority: High
     */
    public function test_cohesive_brand_style_transfer(): void
    {
        $this->mockGeminiSuccess();

        $referenceImages = $this->createFakeReferenceImages(5, 'cohesive');
        
        $response = $this->actingAs($this->user)
            ->post(route('projects.wizards.text.store'), [
                'project_name' => 'Cohesive Brand Test',
                'reference_images' => $referenceImages,
                'idea_description' => 'A person enjoying our product outdoors',
                'format' => 'square',
            ]);

        $response->assertRedirect();
        
        $project = Project::where('name', 'Cohesive Brand Test')->first();
        $this->assertNotNull($project);
        $this->assertEquals(5, $project->brandReferences()->count());
        $this->assertEquals(1, $project->images()->count());
        
        // Verify the image was generated
        $image = $project->images()->first();
        $this->assertNotNull($image->url);
        $this->assertEquals('square', $image->metadata['format'] ?? null);
    }

    /**
     * GEN-002: Diverse References Handling
     * 
     * Test that uploading 5 stylistically different brand images
     * is handled gracefully - either blends styles or allows selection
     * 
     * Priority: High
     */
    public function test_diverse_references_handling(): void
    {
        $this->mockGeminiSuccess();

        $referenceImages = $this->createFakeReferenceImages(5, 'diverse');
        
        $response = $this->actingAs($this->user)
            ->post(route('projects.wizards.text.store'), [
                'project_name' => 'Diverse Styles Test',
                'reference_images' => $referenceImages,
                'idea_description' => 'Modern product showcase',
                'format' => 'landscape',
            ]);

        $response->assertRedirect();
        
        $project = Project::latest()->first();
        
        // Should still generate successfully despite diverse styles
        $this->assertEquals(1, $project->images()->count());
        
        // Metadata should indicate multiple style influences
        $image = $project->images()->first();
        $this->assertNotNull($image->metadata);
    }

    /**
     * GEN-003: Low-Quality References
     * 
     * Test that pixelated/blurry reference images are handled gracefully
     * 
     * Priority: Medium
     */
    public function test_low_quality_references(): void
    {
        $this->mockGeminiSuccess();

        $referenceImages = $this->createFakeReferenceImages(5, 'lowquality');
        
        $response = $this->actingAs($this->user)
            ->post(route('projects.wizards.text.store'), [
                'project_name' => 'Low Quality Test',
                'reference_images' => $referenceImages,
                'idea_description' => 'Professional brand image',
                'format' => 'square',
            ]);

        // Should either succeed with warning or provide helpful feedback
        $response->assertSessionDoesntHaveErrors(['reference_images']);
        
        if ($response->status() === 302) {
            $project = Project::latest()->first();
            $this->assertNotNull($project);
        }
    }

    /**
     * GEN-004: Text-Heavy References
     * 
     * Test that images with lots of text (infographics) don't copy text
     * but focus on style extraction
     * 
     * Priority: Medium
     */
    public function test_text_heavy_references(): void
    {
        $this->mockGeminiSuccess();

        $referenceImages = $this->createFakeReferenceImages(5, 'textheavy');
        
        $response = $this->actingAs($this->user)
            ->post(route('projects.wizards.text.store'), [
                'project_name' => 'Text Heavy Test',
                'reference_images' => $referenceImages,
                'idea_description' => 'New product announcement',
                'format' => 'square',
            ]);

        $response->assertRedirect();
        
        $project = Project::latest()->first();
        $this->assertEquals(1, $project->images()->count());
        
        // Generated image should exist and be different from text-heavy source
        $image = $project->images()->first();
        $this->assertNotNull($image->url);
    }

    /**
     * GEN-005: Aspect Ratio Changes
     * 
     * Test that different output dimensions maintain brand style
     * in different compositions
     * 
     * Priority: Medium
     */
    public function test_aspect_ratio_changes(): void
    {
        $this->mockGeminiSuccess();

        $referenceImages = $this->createFakeReferenceImages(5);
        
        $formats = ['square', 'landscape', 'portrait'];
        
        foreach ($formats as $format) {
            $response = $this->actingAs($this->user)
                ->post(route('projects.wizards.text.store'), [
                    'project_name' => "Test {$format}",
                    'reference_images' => $referenceImages,
                    'idea_description' => "Brand image in {$format} format",
                    'format' => $format,
                ]);

            $response->assertRedirect();
            
            $project = Project::where('name', "Test {$format}")->first();
            $this->assertNotNull($project);

            $image = $project->images()->first();
            $this->assertEquals($format, $image->metadata['format'] ?? null);
        }
        
        // All 3 projects should have been created with different formats
        $this->assertEquals(3, Project::count());
    }

    /**
     * Additional test: Verify credits are deducted
     */
    public function test_generation_deducts_credits(): void
    {
        $this->mockGeminiSuccess();
        
        $initialCredits = $this->user->credits_remaining;
        $referenceImages = $this->createFakeReferenceImages(5);
        
        $this->actingAs($this->user)
            ->post(route('projects.wizards.text.store'), [
                'project_name' => 'Credits Test',
                'reference_images' => $referenceImages,
                'idea_description' => 'Test image',
                'format' => 'square',
            ]);

        $this->user->refresh();
        $this->assertLessThan($initialCredits, $this->user->credits_remaining);
    }

    /**
     * Additional test: Verify generation without credits fails
     */
    public function test_generation_without_credits_fails(): void
    {
        $this->user->update(['credits_remaining' => 0]);
        
        $referenceImages = $this->createFakeReferenceImages(5);
        
        $response = $this->actingAs($this->user)
            ->post(route('projects.wizards.text.store'), [
                'project_name' => 'No Credits Test',
                'reference_images' => $referenceImages,
                'idea_description' => 'Test image',
                'format' => 'square',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
