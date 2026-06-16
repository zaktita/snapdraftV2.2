<?php

namespace Tests\Unit\Services\Prompt;

use App\Services\Prompt\JsonPromptCompiler;
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
        $this->assertStringContainsString('```json', $prompt);
    }
}
