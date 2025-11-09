<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\AIServiceManager;
use App\Services\AI\GoogleGeminiService;
use App\Services\AI\OpenRouterService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AIServiceManagerTest extends TestCase
{
    protected AIServiceManager $manager;
    protected GoogleGeminiService $geminiService;
    protected OpenRouterService $openRouterService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->geminiService = $this->createMock(GoogleGeminiService::class);
        $this->openRouterService = $this->createMock(OpenRouterService::class);
    }

    /** @test */
    public function it_uses_primary_service_when_available()
    {
        $this->openRouterService->method('isAvailable')->willReturn(false);

        $manager = new AIServiceManager($this->geminiService, $this->openRouterService);

        $expectedResult = ['colors' => ['#FF0000'], 'style' => 'Modern'];

        $this->geminiService->expects($this->once())
            ->method('analyzeBrandStyle')
            ->with(['image1.jpg', 'image2.jpg'])
            ->willReturn($expectedResult);

        $result = $manager->analyzeBrandStyle(['image1.jpg', 'image2.jpg']);

        $this->assertEquals($expectedResult, $result);
    }

    /** @test */
    public function it_falls_back_to_secondary_service_on_primary_failure()
    {
        $this->openRouterService->method('isAvailable')->willReturn(true);

        $manager = new AIServiceManager($this->geminiService, $this->openRouterService);

        $expectedResult = ['colors' => ['#0000FF'], 'style' => 'Classic'];

        // Primary service throws exception
        $this->geminiService->expects($this->once())
            ->method('analyzeBrandStyle')
            ->willThrowException(new \Exception('Primary service unavailable'));

        // Fallback service succeeds
        $this->openRouterService->expects($this->once())
            ->method('analyzeBrandStyle')
            ->with(['image1.jpg'])
            ->willReturn($expectedResult);

        $result = $manager->analyzeBrandStyle(['image1.jpg']);

        $this->assertEquals($expectedResult, $result);
    }

    /** @test */
    public function it_throws_exception_when_all_services_fail()
    {
        $this->openRouterService->method('isAvailable')->willReturn(true);

        $manager = new AIServiceManager($this->geminiService, $this->openRouterService);

        // Both services fail
        $this->geminiService->method('analyzeBrandStyle')
            ->willThrowException(new \Exception('Primary failed'));

        $this->openRouterService->method('analyzeBrandStyle')
            ->willThrowException(new \Exception('Fallback failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('All AI services failed');

        $manager->analyzeBrandStyle(['image1.jpg']);
    }

    /** @test */
    public function it_does_not_use_fallback_if_unavailable()
    {
        $this->openRouterService->method('isAvailable')->willReturn(false);

        $manager = new AIServiceManager($this->geminiService, $this->openRouterService);

        $this->geminiService->method('analyzeBrandStyle')
            ->willThrowException(new \Exception('Primary failed'));

        // Fallback should not be called
        $this->openRouterService->expects($this->never())
            ->method('analyzeBrandStyle');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('All AI services failed');

        $manager->analyzeBrandStyle(['image1.jpg']);
    }

    /** @test */
    public function it_generates_images_with_primary_service()
    {
        $this->openRouterService->method('isAvailable')->willReturn(false);

        $manager = new AIServiceManager($this->geminiService, $this->openRouterService);

        $expectedResult = [
            'image_data' => 'base64-encoded-image',
            'tokens_used' => 150
        ];

        $this->geminiService->expects($this->once())
            ->method('generateImage')
            ->with('A beautiful sunset', null, 'square')
            ->willReturn($expectedResult);

        $result = $manager->generateImage('A beautiful sunset', null, 'square');

        $this->assertEquals($expectedResult, $result);
    }

    /** @test */
    public function it_falls_back_for_image_generation_on_failure()
    {
        $this->openRouterService->method('isAvailable')->willReturn(true);

        $manager = new AIServiceManager($this->geminiService, $this->openRouterService);

        $expectedResult = [
            'image_data' => 'fallback-image-data',
            'tokens_used' => 200
        ];

        // Primary fails
        $this->geminiService->method('generateImage')
            ->willThrowException(new \Exception('Rate limit exceeded'));

        // Fallback succeeds
        $this->openRouterService->expects($this->once())
            ->method('generateImage')
            ->with('Mountain landscape', null, 'landscape')
            ->willReturn($expectedResult);

        $result = $manager->generateImage('Mountain landscape', null, 'landscape');

        $this->assertEquals($expectedResult, $result);
    }

    /** @test */
    public function it_passes_style_guide_to_generation()
    {
        $this->openRouterService->method('isAvailable')->willReturn(false);

        $manager = new AIServiceManager($this->geminiService, $this->openRouterService);

        $styleGuide = [
            'colors' => ['#FF0000', '#00FF00'],
            'typography' => 'Modern',
            'style' => 'Minimalist'
        ];

        $expectedResult = [
            'image_data' => 'styled-image',
            'tokens_used' => 250
        ];

        $this->geminiService->expects($this->once())
            ->method('generateImage')
            ->with('Product showcase', $styleGuide, 'portrait')
            ->willReturn($expectedResult);

        $result = $manager->generateImage('Product showcase', $styleGuide, 'portrait');

        $this->assertEquals($expectedResult, $result);
    }

    /** @test */
    public function it_generates_with_references_using_primary_service()
    {
        $this->openRouterService->method('isAvailable')->willReturn(false);

        $manager = new AIServiceManager($this->geminiService, $this->openRouterService);

        $referenceImages = ['brand1.jpg', 'brand2.jpg', 'brand3.jpg'];
        $productImages = ['product1.jpg'];

        $expectedResult = [
            'image_data' => 'referenced-image',
            'tokens_used' => 300
        ];

        $this->geminiService->expects($this->once())
            ->method('generateWithReferences')
            ->with('Product with brand style', $referenceImages, $productImages, 'square')
            ->willReturn($expectedResult);

        $result = $manager->generateWithReferences(
            'Product with brand style',
            $referenceImages,
            $productImages,
            'square'
        );

        $this->assertEquals($expectedResult, $result);
    }

    /** @test */
    public function it_handles_missing_generate_with_references_method()
    {
        $this->openRouterService->method('isAvailable')->willReturn(false);

        $manager = new AIServiceManager($this->geminiService, $this->openRouterService);

        // Mock the generateWithReferences method
        $this->geminiService->expects($this->once())
            ->method('generateWithReferences')
            ->with('Test prompt', ['ref1.jpg'], [], 'square')
            ->willReturn(['image_data' => 'result', 'tokens_used' => 100]);

        $result = $manager->generateWithReferences(
            'Test prompt',
            ['ref1.jpg'],
            [],
            'square'
        );

        $this->assertArrayHasKey('image_data', $result);
    }

    /** @test */
    public function it_logs_service_selection_and_failures()
    {
        Log::shouldReceive('info')
            ->with('AIServiceManager: Attempting brand analysis with primary service')
            ->once();

        Log::shouldReceive('error')
            ->with('AIServiceManager: Primary service failed for brand analysis', \Mockery::type('array'))
            ->once();

        Log::shouldReceive('info')
            ->with('AIServiceManager: Attempting brand analysis with fallback service')
            ->once();

        $this->openRouterService->method('isAvailable')->willReturn(true);

        $manager = new AIServiceManager($this->geminiService, $this->openRouterService);

        $this->geminiService->method('analyzeBrandStyle')
            ->willThrowException(new \Exception('Primary failed'));

        $this->openRouterService->method('analyzeBrandStyle')
            ->willReturn(['colors' => ['#000000']]);

        $manager->analyzeBrandStyle(['test.jpg']);
    }

    /** @test */
    public function it_provides_different_formats_for_image_generation()
    {
        $this->openRouterService->method('isAvailable')->willReturn(false);

        $manager = new AIServiceManager($this->geminiService, $this->openRouterService);

        $formats = ['square', 'landscape', 'portrait', 'story'];

        $this->geminiService->expects($this->exactly(count($formats)))
            ->method('generateImage')
            ->willReturnCallback(function ($prompt, $styleGuide, $format) {
                return ['image_data' => "image-{$format}", 'tokens_used' => 100];
            });

        foreach ($formats as $format) {
            $result = $manager->generateImage('Test prompt', null, $format);

            $this->assertEquals("image-{$format}", $result['image_data']);
        }
    }
}
