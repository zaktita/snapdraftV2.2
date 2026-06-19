<?php

namespace Tests\Unit\Services\AI;

use App\Models\ProjectClusterImage;
use App\Services\AI\CsvImageGenerationService;
use App\Services\AI\GeminiCsvImageGenerator;
use App\Services\AI\OpenRouter\OpenRouterCsvImageGenerator;
use Illuminate\Support\Collection;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CsvImageGenerationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_falls_back_to_openrouter_when_gemini_key_is_revoked(): void
    {
        config()->set('ai.image_driver', 'auto');
        config()->set('services.ai.enable_fallback', true);
        config()->set('openrouter.api_key', 'sk-test');

        $gemini = Mockery::mock(GeminiCsvImageGenerator::class);
        $gemini->shouldReceive('generateFromPromptJson')
            ->once()
            ->andThrow(new RuntimeException('Gemini API error (HTTP 403): leaked'));

        $openRouter = Mockery::mock(OpenRouterCsvImageGenerator::class);
        $openRouter->shouldReceive('generateFromPromptJson')
            ->once()
            ->andReturn('png-binary');

        $service = new CsvImageGenerationService($gemini, $openRouter);

        $result = $service->generateFromPromptJson(
            ['scene' => 'test'],
            new Collection(),
            '1:1',
            1,
        );

        $this->assertSame('png-binary', $result);
    }

    public function test_openrouter_driver_skips_gemini(): void
    {
        config()->set('ai.image_driver', 'openrouter');

        $gemini = Mockery::mock(GeminiCsvImageGenerator::class);
        $gemini->shouldNotReceive('generateFromPromptJson');

        $openRouter = Mockery::mock(OpenRouterCsvImageGenerator::class);
        $openRouter->shouldReceive('generateFromPromptJson')
            ->once()
            ->andReturn('from-openrouter');

        $service = new CsvImageGenerationService($gemini, $openRouter);

        $this->assertSame(
            'from-openrouter',
            $service->generateFromPromptJson(['scene' => 'x'], new Collection()),
        );
    }
}
