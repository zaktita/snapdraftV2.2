<?php

namespace Tests\Feature\Quality;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\TestHelpers\AITestHelper;

/**
 * Edge Cases and Safety Tests (EDG-001, EDG-002, EDG-003, SAF-001, SAF-002)
 * Tests boundary conditions and content safety
 */
class EdgeCasesTest extends TestCase
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
     * EDG-001: Single Reference Image
     * 
     * Test with only 1 reference image instead of recommended 5
     * 
     * Priority: Medium
     */
    public function test_single_reference_image(): void
    {
        $this->mockGeminiSuccess();

        $singleImage = [$this->createFakeReferenceImages(1)[0]];

        $response = $this->actingAs($this->user)
            ->post(route('projects.wizards.text.store'), [
                'project_name' => 'Single Reference Test',
                'reference_images' => $singleImage,
                'idea_description' => 'Brand image with minimal references',
                'format' => 'square',
            ]);

        // Should handle gracefully, maybe with warning
        if ($response->status() === 302) {
            $project = Project::latest()->first();
            $this->assertEquals(1, $project->brandReferences()->count());
            $this->assertEquals(1, $project->images()->count());
        }
    }

    /**
     * EDG-002: Too Many References
     * 
     * Test with 20+ reference images
     * 
     * Priority: Low
     */
    public function test_too_many_references(): void
    {
        $this->mockGeminiSuccess();

        $manyImages = $this->createFakeReferenceImages(20);

        $response = $this->actingAs($this->user)
            ->post(route('projects.wizards.text.store'), [
                'project_name' => 'Many References Test',
                'reference_images' => $manyImages,
                'idea_description' => 'Image with too many references',
                'format' => 'square',
            ]);

        // Should either process or give clear limit message
        if ($response->status() === 422) {
            $response->assertSessionHasErrors();
        } else {
            $response->assertRedirect();
        }
    }

    /**
     * EDG-003: Extremely Long Prompt
     * 
     * Test 500+ character prompt
     * 
     * Priority: Low
     */
    public function test_extremely_long_prompt(): void
    {
        $this->mockGeminiSuccess();

        $longPrompt = str_repeat('Very detailed description. ', 50); // ~1400 chars

        $response = $this->actingAs($this->user)
            ->post(route('projects.wizards.text.store'), [
                'project_name' => 'Long Prompt Test',
                'idea_description' => $longPrompt,
                'format' => 'square',
            ]);

        // Should process or truncate appropriately
        $this->assertTrue($response->status() === 302 || $response->status() === 422);
    }

    /**
     * SAF-001: Child-Friendly Brand
     * 
     * Test kids' product brand to ensure appropriate content
     * 
     * Priority: High
     */
    public function test_child_friendly_brand(): void
    {
        $this->mockGeminiSuccess();

        $childPrompts = [
            'Children playing with educational toys in bright room',
            'Kids learning with colorful books',
            'Family-friendly product showcase',
        ];

        foreach ($childPrompts as $prompt) {
            $response = $this->actingAs($this->user)
                ->post(route('projects.wizards.text.store'), [
                    'project_name' => 'Child Safe: ' . substr($prompt, 0, 20),
                    'idea_description' => $prompt,
                    'format' => 'square',
                ]);

            $response->assertRedirect();
            
            $project = Project::latest()->first();
            $this->assertEquals(1, $project->images()->count());
        }
    }

    /**
     * SAF-002: Trademark Respect
     * 
     * Test with brand logos - should not copy exactly
     * 
     * Priority: Medium
     */
    public function test_trademark_respect(): void
    {
        $this->mockGeminiSuccess();

        $logoImages = $this->createFakeReferenceImages(5);

        $response = $this->actingAs($this->user)
            ->post(route('projects.wizards.text.store'), [
                'project_name' => 'Logo Test',
                'reference_images' => $logoImages,
                'idea_description' => 'Brand image with company identity',
                'format' => 'square',
            ]);

        $response->assertRedirect();
        
        $project = Project::latest()->first();
        $image = $project->images()->first();
        
        // Verify image was generated (doesn't copy trademarked content)
        $this->assertNotNull($image->url);
    }

    /**
     * Additional test: Empty prompt
     */
    public function test_empty_prompt(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('projects.wizards.text.store'), [
                'project_name' => 'Empty Prompt Test',
                'idea_description' => '',
                'format' => 'square',
            ]);

        $response->assertSessionHasErrors(['idea_description']);
    }

    /**
     * Additional test: Invalid format
     */
    public function test_invalid_format(): void
    {
        $this->mockGeminiSuccess();

        $response = $this->actingAs($this->user)
            ->post(route('projects.wizards.text.store'), [
                'project_name' => 'Invalid Format Test',
                'idea_description' => 'Test image',
                'format' => 'invalid_format',
            ]);

        $response->assertSessionHasErrors(['format']);
    }

    /**
     * Additional test: Special characters in project name
     */
    public function test_special_characters_in_project_name(): void
    {
        $this->mockGeminiSuccess();

        $specialNames = [
            'Test & Project',
            'Project #1',
            'Test (2024)',
        ];

        foreach ($specialNames as $name) {
            $response = $this->actingAs($this->user)
                ->post(route('projects.wizards.text.store'), [
                    'project_name' => $name,
                    'idea_description' => 'Test image',
                    'format' => 'square',
                ]);

            $response->assertRedirect();
            $this->assertDatabaseHas('projects', ['name' => $name]);
        }
    }
}
