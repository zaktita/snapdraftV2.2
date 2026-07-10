<?php

namespace Tests\Unit\Services\Prompt;

use App\Services\Prompt\JsonPromptCompiler;
use App\Services\Prompt\JsonSchemaValidator;
use PHPUnit\Framework\TestCase;

class JsonPromptCompilerTest extends TestCase
{
    public function test_compile_includes_concept_and_aspect_ratio(): void
    {
        $compiler = new JsonPromptCompiler();

        $compiled = $compiler->compile([
            'meta' => ['aspect_ratio' => '1:1'],
            'brand_locked' => [
                'layout_system' => 'Two-zone split',
                'palette' => [['hex' => '#4F6B27', 'role' => 'primary']],
            ],
            'post' => [
                'concept' => 'A square social post promoting evening courses.',
            ],
            'quality' => [
                'include' => ['photorealistic'],
                'avoid' => ['cartoon'],
            ],
            'reference_usage' => 'Match attached layout.',
        ]);

        $this->assertStringContainsString('evening courses', $compiled);
        $this->assertStringContainsString('1:1', $compiled);
        $this->assertStringContainsString('#4F6B27', $compiled);
        $this->assertStringContainsString('photorealistic', $compiled);
        $this->assertStringContainsString('cartoon', $compiled);
    }

    public function test_compile_includes_visual_form(): void
    {
        $compiler = new JsonPromptCompiler();

        $compiled = $compiler->compile([
            'post' => [
                'concept' => 'Sports graphic',
                'visual_form' => 'duotone sports graphic, subject cutouts over giant numeral',
            ],
            'quality' => ['include' => ['photorealistic'], 'avoid' => ['cartoon']],
        ]);

        $this->assertStringContainsString('duotone sports graphic', $compiled);
    }

    public function test_build_image_request_prompt_includes_references_and_on_image_text(): void
    {
        $compiler = new JsonPromptCompiler();

        $prompt = $compiler->buildImageRequestPrompt([
            'post' => [
                'concept' => 'Event announcement',
                'caption' => 'Full social caption here',
                'on_image_text' => [
                    ['role' => 'headline', 'text' => 'Inscrivez-vous'],
                ],
            ],
            'brand_locked' => ['layout_system' => 'Split layout'],
            'quality' => ['include' => ['photorealistic']],
        ], 2, 'Full social caption here');

        $this->assertStringContainsString('2 attached reference images', $prompt);
        $this->assertStringContainsString('ON-IMAGE TEXT', $prompt);
        $this->assertStringContainsString('Inscrivez-vous', $prompt);
        $this->assertStringContainsString('Full social caption here', $prompt);
        $this->assertStringContainsString('Visual constraints', $prompt);
        $this->assertStringNotContainsString('Full post prompt JSON', $prompt);
        $this->assertStringNotContainsString('"caption": "Full social caption here"', $prompt);
    }

    public function test_build_image_request_prompt_text_free_visual_has_no_text_block(): void
    {
        $compiler = new JsonPromptCompiler();

        $prompt = $compiler->buildImageRequestPrompt([
            'post' => [
                'concept' => 'Clean lifestyle product photograph, no text on image.',
                'visual_form' => 'text-free lifestyle photograph',
                'caption' => 'Product launch caption',
                'on_image_text' => [],
            ],
            'quality' => ['include' => ['photorealistic lifestyle photography']],
        ], 3, 'Product launch caption');

        $this->assertStringContainsString('Visual form: text-free lifestyle photograph', $prompt);
        $this->assertStringContainsString('NO on-image text', $prompt);
        $this->assertStringNotContainsString('ON-IMAGE TEXT', $prompt);
        $this->assertStringContainsString('do not render any text on the image', $prompt);
    }

    public function test_build_image_request_prompt_includes_subject_and_content_guard(): void
    {
        $compiler = new JsonPromptCompiler();

        $prompt = $compiler->buildImageRequestPrompt([
            'post' => [
                'concept' => 'Team collaboration post',
                'subject' => 'A diverse team collaborating around a meeting table with laptops',
                'on_image_text' => [
                    ['role' => 'headline', 'text' => 'The Project Meeting'],
                ],
            ],
            'quality' => ['include' => ['photorealistic']],
        ], 3);

        $this->assertStringContainsString('IMAGERY CONTENT (critical)', $prompt);
        $this->assertStringContainsString('Do NOT reuse the people, faces, objects, products, or scenes', $prompt);
        $this->assertStringContainsString('Subject to depict', $prompt);
        $this->assertStringContainsString('A diverse team collaborating around a meeting table', $prompt);
    }

    public function test_build_image_request_prompt_omits_content_guard_without_references(): void
    {
        $compiler = new JsonPromptCompiler();

        $prompt = $compiler->buildImageRequestPrompt([
            'post' => [
                'concept' => 'Simple post',
                'on_image_text' => ['Headline'],
            ],
            'quality' => ['include' => ['photorealistic']],
        ], 0);

        $this->assertStringNotContainsString('IMAGERY CONTENT', $prompt);
    }

    public function test_build_image_request_prompt_includes_visual_form(): void
    {
        $compiler = new JsonPromptCompiler();

        $prompt = $compiler->buildImageRequestPrompt([
            'post' => [
                'concept' => 'Editorial poster',
                'visual_form' => 'photo masked inside giant display typography',
                'on_image_text' => [
                    ['role' => 'headline', 'text' => 'POPULAR'],
                ],
            ],
            'quality' => ['include' => ['editorial poster style']],
        ], 2);

        $this->assertStringContainsString('Visual form: photo masked inside giant display typography', $prompt);
    }

    public function test_validate_post_prompt_allows_empty_on_image_text(): void
    {
        $validator = new JsonSchemaValidator();

        $valid = $validator->validate([
            'brand_locked' => [],
            'post' => [
                'concept' => 'A text-free lifestyle photograph.',
                'visual_form' => 'text-free lifestyle photograph',
                'on_image_text' => [],
            ],
            'quality' => ['include' => ['photorealistic']],
        ], 'generate_post');

        $this->assertTrue($valid['valid']);
    }

    public function test_validate_post_prompt_accepts_on_image_text(): void
    {
        $validator = new JsonSchemaValidator();

        $valid = $validator->validate([
            'brand_locked' => [],
            'post' => [
                'concept' => 'A concept',
                'on_image_text' => ['headline' => 'Bonjour'],
            ],
            'quality' => ['include' => ['photorealistic']],
        ], 'generate_post');

        $this->assertTrue($valid['valid']);
    }

    public function test_validate_post_prompt_rejects_invalid_on_image_text(): void
    {
        $validator = new JsonSchemaValidator();

        $invalid = $validator->validate([
            'brand_locked' => [],
            'post' => [
                'concept' => 'A concept',
                'on_image_text' => [['role' => 'headline', 'text' => '']],
            ],
            'quality' => ['include' => ['photorealistic']],
        ], 'generate_post');

        $this->assertFalse($invalid['valid']);
        $this->assertContains('Invalid post.on_image_text structure', $invalid['errors']);
    }
}
