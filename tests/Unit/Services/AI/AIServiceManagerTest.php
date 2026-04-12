<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\AIServiceManager;
use App\Services\AI\GoogleGeminiService;
use App\Services\AI\OpenRouterService;
use RuntimeException;
use Tests\TestCase;

class AIServiceManagerTest extends TestCase
{
    public function test_it_uses_primary_service_for_generation(): void
    {
        config()->set('services.ai.preferred', 'gemini');
        config()->set('services.ai.enable_fallback', true);

        $gemini = $this->createMock(GoogleGeminiService::class);
        $openRouter = $this->createMock(OpenRouterService::class);

        $gemini->expects($this->once())
            ->method('generateWithReferences')
            ->with('Prompt', ['a.jpg'], [], 'square')
            ->willReturn(['image_base64' => 'abc']);

        $openRouter->expects($this->never())
            ->method('generateWithReferences');

        $manager = new AIServiceManager($gemini, $openRouter);

        $result = $manager->generateWithReferences('Prompt', ['a.jpg'], [], 'square');

        $this->assertSame('abc', $result['image_base64']);
    }

    public function test_it_falls_back_when_primary_fails(): void
    {
        config()->set('services.ai.preferred', 'gemini');
        config()->set('services.ai.enable_fallback', true);

        $gemini = $this->createMock(GoogleGeminiService::class);
        $openRouter = $this->createMock(OpenRouterService::class);

        $gemini->expects($this->once())
            ->method('analyzeBrandStyle')
            ->willThrowException(new RuntimeException('Gemini down'));

        $openRouter->expects($this->once())
            ->method('analyzeBrandStyle')
            ->with(['img1.png'])
            ->willReturn(['style_clusters' => []]);

        $manager = new AIServiceManager($gemini, $openRouter);
        $result = $manager->analyzeBrandStyle(['img1.png']);

        $this->assertArrayHasKey('style_clusters', $result);
    }

    public function test_it_throws_when_both_providers_fail(): void
    {
        config()->set('services.ai.preferred', 'gemini');
        config()->set('services.ai.enable_fallback', true);

        $gemini = $this->createMock(GoogleGeminiService::class);
        $openRouter = $this->createMock(OpenRouterService::class);

        $gemini->method('outpaint')->willThrowException(new RuntimeException('Gemini failed'));
        $openRouter->method('outpaint')->willThrowException(new RuntimeException('OpenRouter failed'));

        $manager = new AIServiceManager($gemini, $openRouter);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Both AI providers failed');

        $manager->outpaint('base64-image', 'base64-mask');
    }

    public function test_it_does_not_fallback_when_disabled(): void
    {
        config()->set('services.ai.preferred', 'gemini');
        config()->set('services.ai.enable_fallback', false);

        $gemini = $this->createMock(GoogleGeminiService::class);
        $openRouter = $this->createMock(OpenRouterService::class);

        $gemini->expects($this->once())
            ->method('eraseGreenHighlights')
            ->willThrowException(new RuntimeException('Gemini failed'));

        $openRouter->expects($this->never())
            ->method('eraseGreenHighlights');

        $manager = new AIServiceManager($gemini, $openRouter);

        $this->expectException(RuntimeException::class);
        $manager->eraseGreenHighlights('image-base64');
    }
}
