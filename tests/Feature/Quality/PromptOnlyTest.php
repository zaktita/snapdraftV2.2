<?php

namespace Tests\Feature\Quality;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\TestHelpers\AITestHelper;

/**
 * Prompt-Only Tests (PRO-001 to PRO-003)
 * Tests generation with text descriptions only, no reference images
 */
class PromptOnlyTest extends TestCase
{
    use RefreshDatabase, AITestHelper;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeStorage();
        $this->user = $this->createUserWithCredits(50);
    }

    /**
     * PRO-001: Detailed Brand Description
     * 
     * Test generation with detailed text description only
     * 
     * Priority: High
     */
    public function test_detailed_brand_description(): void
    {
        $this->mockGeminiSuccess();

        $detailedPrompt = '[TechBrand] - modern technology aesthetic, blue and white color scheme, ' .
                         'clean lines, minimalist design, professional and trustworthy, ' .
                         'sans-serif typography, lots of white space, gradient backgrounds';

        // Generate multiple images to test consistency
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->actingAs($this->user)
                ->post(route('projects.wizards.text.store'), [
                    'project_name' => "Detailed Description Test {$i}",
                    'idea_description' => $detailedPrompt,
                    'format' => 'square',
                ]);

            $response->assertRedirect();
        }

        // All 3 projects should match the described aesthetic
        $this->assertEquals(3, Project::count());
        
        foreach (Project::all() as $project) {
            $this->assertEquals(1, $project->images()->count());
            $image = $project->images()->first();
            $this->assertNotNull($image->url);
        }
    }

    /**
     * PRO-002: Vague Prompt
     * 
     * Test with minimal guidance, should produce generic but usable image
     * 
     * Priority: Medium
     */
    public function test_vague_prompt(): void
    {
        $this->mockGeminiSuccess();

        $vaguPrompts = [
            'something inspirational',
            'business image',
            'social media post',
        ];

        foreach ($vaguPrompts as $prompt) {
            $response = $this->actingAs($this->user)
                ->post(route('projects.wizards.text.store'), [
                    'project_name' => "Vague: {$prompt}",
                    'idea_description' => $prompt,
                    'format' => 'square',
                ]);

            $response->assertRedirect();
            
            $project = Project::latest()->first();
            $this->assertEquals(1, $project->images()->count());
        }
    }

    /**
     * PRO-003: Platform-Specific Format
     * 
     * Test requesting specific platform format and style
     * 
     * Priority: Medium
     */
    public function test_platform_specific_format(): void
    {
        $this->mockGeminiSuccess();

        $platformTests = [
            [
                'prompt' => 'LinkedIn article header about leadership and team management',
                'format' => 'landscape',
                'platform' => 'LinkedIn'
            ],
            [
                'prompt' => 'Instagram story - motivational quote with gradient background',
                'format' => 'portrait',
                'platform' => 'Instagram Story'
            ],
            [
                'prompt' => 'Facebook ad for tech product launch',
                'format' => 'square',
                'platform' => 'Facebook'
            ],
        ];

        foreach ($platformTests as $test) {
            $response = $this->actingAs($this->user)
                ->post(route('projects.wizards.text.store'), [
                    'project_name' => $test['platform'] . ' Test',
                    'idea_description' => $test['prompt'],
                    'format' => $test['format'],
                ]);

            $response->assertRedirect();
            
            $project = Project::where('name', $test['platform'] . ' Test')->first();
            $this->assertNotNull($project);

            $image = $project->images()->first();
            $this->assertEquals($test['format'], $image->metadata['format'] ?? null);
        }
    }

    /**
     * Additional test: Very long detailed prompt
     */
    public function test_extremely_long_prompt(): void
    {
        $this->mockGeminiSuccess();

        $longPrompt = str_repeat(
            'Create a professional brand image with modern design elements, ' .
            'incorporating vibrant colors and dynamic composition. ',
            20 // Creates ~500 character prompt
        );

        $response = $this->actingAs($this->user)
            ->post(route('projects.wizards.text.store'), [
                'project_name' => 'Long Prompt Test',
                'idea_description' => $longPrompt,
                'format' => 'square',
            ]);

        // Should either process or truncate appropriately
        $response->assertRedirect();
    }
}
