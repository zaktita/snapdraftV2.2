<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\PromptAssemblyService;
use Tests\TestCase;

class PromptAssemblyServiceTest extends TestCase
{
    private PromptAssemblyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PromptAssemblyService();
    }

    private function snakeCaseCluster(): array
    {
        return [
            'name' => 'Minimalist Dark',
            'background_treatment' => 'Deep navy gradient',
            'dominant_colors' => ['#0A0A2E', '#FFFFFF', '#FFD700'],
            'typography_style' => 'Bold sans-serif, all caps',
            'text_placement' => 'Centered, lower third',
            'layout_pattern' => 'Full-bleed hero',
        ];
    }

    private function camelCaseCluster(): array
    {
        return [
            'name' => 'Vibrant Pop',
            'backgroundTreatment' => 'Bright yellow solid',
            'dominantColor' => '#FFD700',
            'palette' => ['#FFD700', '#000000', '#FF5722'],
            'typographyStyle' => 'Impact font, italic',
            'textPlacement' => 'Top-left aligned',
            'compositionType' => 'Off-center rule of thirds',
        ];
    }

    /** @test */
    public function prompt_includes_overlay_text(): void
    {
        $prompt = $this->service->buildStyleAnchoredPrompt(
            'Summer Sale 50% Off',
            $this->snakeCaseCluster()
        );

        $this->assertStringContainsString('Summer Sale 50% Off', $prompt);
    }

    /** @test */
    public function prompt_resolves_snake_case_background_field(): void
    {
        $prompt = $this->service->buildStyleAnchoredPrompt(
            'Test headline',
            $this->snakeCaseCluster()
        );

        $this->assertStringContainsString('Deep navy gradient', $prompt);
    }

    /** @test */
    public function prompt_resolves_camel_case_background_field(): void
    {
        $prompt = $this->service->buildStyleAnchoredPrompt(
            'Test headline',
            $this->camelCaseCluster()
        );

        $this->assertStringContainsString('Bright yellow solid', $prompt);
    }

    /** @test */
    public function prompt_resolves_dominant_color_from_camel_case_key(): void
    {
        $prompt = $this->service->buildStyleAnchoredPrompt(
            'Test',
            $this->camelCaseCluster()
        );

        $this->assertStringContainsString('#FFD700', $prompt);
    }

    /** @test */
    public function prompt_resolves_dominant_color_from_dominant_colors_array(): void
    {
        $cluster = $this->snakeCaseCluster();  // uses dominant_colors array
        $prompt = $this->service->buildStyleAnchoredPrompt('Test', $cluster);

        $this->assertStringContainsString('#0A0A2E', $prompt);
    }

    /** @test */
    public function prompt_resolves_palette_from_snake_case_dominant_colors(): void
    {
        $prompt = $this->service->buildStyleAnchoredPrompt(
            'Test',
            $this->snakeCaseCluster()
        );

        $this->assertStringContainsString('#FFFFFF', $prompt);
        $this->assertStringContainsString('#FFD700', $prompt);
    }

    /** @test */
    public function prompt_resolves_palette_from_camel_case_palette_key(): void
    {
        $prompt = $this->service->buildStyleAnchoredPrompt(
            'Test',
            $this->camelCaseCluster()
        );

        $this->assertStringContainsString('#FF5722', $prompt);
    }

    /** @test */
    public function prompt_resolves_typography_from_snake_case_key(): void
    {
        $prompt = $this->service->buildStyleAnchoredPrompt(
            'Test',
            $this->snakeCaseCluster()
        );

        $this->assertStringContainsString('Bold sans-serif, all caps', $prompt);
    }

    /** @test */
    public function prompt_resolves_typography_from_camel_case_key(): void
    {
        $prompt = $this->service->buildStyleAnchoredPrompt(
            'Test',
            $this->camelCaseCluster()
        );

        $this->assertStringContainsString('Impact font, italic', $prompt);
    }

    /** @test */
    public function prompt_resolves_composition_from_layout_pattern_key(): void
    {
        $prompt = $this->service->buildStyleAnchoredPrompt(
            'Test',
            $this->snakeCaseCluster()  // uses layout_pattern
        );

        $this->assertStringContainsString('Full-bleed hero', $prompt);
    }

    /** @test */
    public function prompt_resolves_composition_from_composition_type_key(): void
    {
        $prompt = $this->service->buildStyleAnchoredPrompt(
            'Test',
            $this->camelCaseCluster()  // uses compositionType
        );

        $this->assertStringContainsString('Off-center rule of thirds', $prompt);
    }

    /** @test */
    public function prompt_includes_global_rules_when_provided(): void
    {
        $rules = ['Always use white text', 'Never use gradients'];

        $prompt = $this->service->buildStyleAnchoredPrompt(
            'Test headline',
            $this->snakeCaseCluster(),
            $rules
        );

        $this->assertStringContainsString('Always use white text', $prompt);
        $this->assertStringContainsString('Never use gradients', $prompt);
    }

    /** @test */
    public function prompt_uses_default_rules_block_when_no_global_rules(): void
    {
        $prompt = $this->service->buildStyleAnchoredPrompt(
            'Test',
            $this->snakeCaseCluster()
        );

        $this->assertStringContainsString('Maintain strict visual consistency', $prompt);
    }

    /** @test */
    public function prompt_includes_required_section_headers(): void
    {
        $prompt = $this->service->buildStyleAnchoredPrompt(
            'Test',
            $this->snakeCaseCluster()
        );

        $this->assertStringContainsString('STYLE ANCHOR:', $prompt);
        $this->assertStringContainsString('LAYOUT:', $prompt);
        $this->assertStringContainsString('CONTENT:', $prompt);
        $this->assertStringContainsString('GLOBAL BRAND RULES:', $prompt);
        $this->assertStringContainsString('EXCLUSIONS:', $prompt);
    }

    /** @test */
    public function prompt_falls_back_to_defaults_for_empty_cluster(): void
    {
        $prompt = $this->service->buildStyleAnchoredPrompt('Test heading', []);

        // Should not throw; should contain sensible defaults
        $this->assertStringContainsString('Test heading', $prompt);
        $this->assertStringContainsString('#000000', $prompt);  // default dominant color
    }
}
