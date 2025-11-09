<?php

namespace Tests\Unit\Services\AI;

use App\Exceptions\AIServiceUnavailableException;
use App\Services\AI\GoogleGeminiService;
use App\Services\PromptService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoogleGeminiServiceTest extends TestCase
{
    protected GoogleGeminiService $service;
    protected PromptService $promptService;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up config BEFORE creating the service
        Config::set('services.gemini.api_key', 'test-api-key-123');
        Config::set('services.gemini.model', 'gemini-2.0-flash-exp');
        Config::set('services.gemini.image_model', 'gemini-2.5-flash-image-preview');

        $this->promptService = $this->createMock(PromptService::class);
        $this->service = new GoogleGeminiService($this->promptService);
    }

    /** @test */
    public function it_checks_if_service_is_available()
    {
        $this->assertTrue($this->service->isAvailable());

        // Create new service with no API key
        Config::set('services.gemini.api_key', null);
        $serviceWithoutKey = new GoogleGeminiService($this->promptService);
        $this->assertFalse($serviceWithoutKey->isAvailable());
    }

    /** @test */
    public function it_throws_exception_when_api_key_not_configured()
    {
        Config::set('services.gemini.api_key', null);

        $this->expectException(AIServiceUnavailableException::class);
        $this->expectExceptionMessage('Google Gemini API is not configured');

        $this->service->analyzeBrandStyle(['test.jpg']);
    }

    /** @test */
    public function it_analyzes_brand_style_successfully()
    {
        Storage::fake('public');

        // Create a fake image file
        $imagePath = 'test/image.jpg';
        Storage::disk('public')->put($imagePath, 'fake-image-content');

        // Mock the prompt service
        $this->promptService->method('brandAnalysis')
            ->willReturn('Analyze this brand style');

        // Mock successful API response
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'colors' => ['#FF0000', '#00FF00'],
                                        'typography' => 'Modern sans-serif',
                                        'style' => 'Minimalist',
                                        'composition' => 'Clean layout'
                                    ])
                                ]
                            ]
                        ],
                        'finishReason' => 'STOP'
                    ]
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 100,
                    'candidatesTokenCount' => 50,
                    'totalTokenCount' => 150
                ]
            ], 200)
        ]);

        $result = $this->service->analyzeBrandStyle([
            Storage::disk('public')->path($imagePath)
        ]);

        $this->assertArrayHasKey('colors', $result);
        $this->assertArrayHasKey('typography', $result);
        $this->assertArrayHasKey('style', $result);
        $this->assertIsArray($result['colors']);
    }

    /** @test */
    public function it_handles_api_rate_limiting()
    {
        Storage::fake('public');
        $imagePath = 'test/image.jpg';
        Storage::disk('public')->put($imagePath, 'fake-image-content');

        $this->promptService->method('brandAnalysis')
            ->willReturn('Analyze this brand style');

        // Mock 429 rate limit response
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 429,
                    'message' => 'Rate limit exceeded',
                    'status' => 'RESOURCE_EXHAUSTED'
                ]
            ], 429)
        ]);

        $this->expectException(\Exception::class);

        $this->service->analyzeBrandStyle([
            Storage::disk('public')->path($imagePath)
        ]);
    }

    /** @test */
    public function it_handles_authentication_errors()
    {
        Storage::fake('public');
        $imagePath = 'test/image.jpg';
        Storage::disk('public')->put($imagePath, 'fake-image-content');

        $this->promptService->method('brandAnalysis')
            ->willReturn('Analyze this brand style');

        // Mock 401 authentication error
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 401,
                    'message' => 'Invalid API key',
                    'status' => 'UNAUTHENTICATED'
                ]
            ], 401)
        ]);

        $this->expectException(\Exception::class);

        $this->service->analyzeBrandStyle([
            Storage::disk('public')->path($imagePath)
        ]);
    }

    /** @test */
    public function it_generates_image_successfully()
    {
        $this->promptService->method('csvGeneration')
            ->willReturn('Generate an image with: {prompt}');

        // Mock successful image generation response
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => 'image/png',
                                        'data' => base64_encode('fake-image-data')
                                    ]
                                ]
                            ]
                        ],
                        'finishReason' => 'STOP'
                    ]
                ],
                'usageMetadata' => [
                    'totalTokenCount' => 200
                ]
            ], 200)
        ]);

        $result = $this->service->generateImage(
            prompt: 'A beautiful sunset',
            styleGuide: null,
            format: 'square'
        );

        $this->assertArrayHasKey('image_data', $result);
        $this->assertArrayHasKey('tokens_used', $result);
        $this->assertEquals(200, $result['tokens_used']);
    }

    /** @test */
    public function it_includes_brand_references_in_generation()
    {
        Storage::fake('public');

        // Create fake brand reference images
        $brandImages = [];
        for ($i = 1; $i <= 3; $i++) {
            $path = "brands/image{$i}.jpg";
            Storage::disk('public')->put($path, "fake-image-{$i}");
            $brandImages[] = Storage::disk('public')->path($path);
        }

        // Mock the correct prompt method
        $this->promptService->method('csvGeneration')
            ->willReturn('Generate with brand style: {prompt}');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => 'image/png',
                                        'data' => base64_encode('branded-image-data')
                                    ]
                                ]
                            ]
                        ],
                        'finishReason' => 'STOP'
                    ]
                ],
                'usageMetadata' => [
                    'totalTokenCount' => 300
                ]
            ], 200)
        ]);

        // Use generateWithReferences method instead
        $result = $this->service->generateWithReferences(
            prompt: 'Product showcase',
            referenceImagePaths: $brandImages,
            productImagePaths: [],
            format: 'landscape'
        );

        $this->assertArrayHasKey('image_data', $result);
    }

    /** @test */
    public function it_handles_empty_brand_reference_array()
    {
        $this->promptService->method('csvGeneration')
            ->willReturn('Generate: {prompt}');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => 'image/png',
                                        'data' => base64_encode('image-without-branding')
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $result = $this->service->generateImage(
            prompt: 'Simple image',
            styleGuide: null,
            format: 'square'
        );

        $this->assertArrayHasKey('image_data', $result);
    }

    /** @test */
    public function it_validates_format_parameter()
    {
        $this->promptService->method('csvGeneration')
            ->willReturn('Generate: {prompt}');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'inlineData' => [
                                'mimeType' => 'image/png',
                                'data' => base64_encode('test')
                            ]
                        ]]
                    ]
                ]]
            ], 200)
        ]);

        // Test each valid format
        $validFormats = ['square', 'landscape', 'portrait', 'story'];

        foreach ($validFormats as $format) {
            $result = $this->service->generateImage(
                prompt: 'Test',
                styleGuide: null,
                format: $format
            );

            $this->assertNotNull($result);
        }
    }

    /** @test */
    public function it_extracts_json_from_markdown_code_blocks()
    {
        Storage::fake('public');
        $imagePath = 'test/image.jpg';
        Storage::disk('public')->put($imagePath, 'fake-image-content');

        $this->promptService->method('brandAnalysis')
            ->willReturn('Analyze');

        // Mock response with JSON wrapped in markdown
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => "```json\n" . json_encode([
                                'colors' => ['#000000'],
                                'typography' => 'Bold',
                                'style' => 'Modern'
                            ]) . "\n```"
                        ]]
                    ]
                ]]
            ], 200)
        ]);

        $result = $this->service->analyzeBrandStyle([
            Storage::disk('public')->path($imagePath)
        ]);

        $this->assertArrayHasKey('colors', $result);
        $this->assertArrayHasKey('typography', $result);
    }

    /** @test */
    public function it_handles_server_errors()
    {
        Storage::fake('public');
        $imagePath = 'test/image.jpg';
        Storage::disk('public')->put($imagePath, 'fake-image-content');

        $this->promptService->method('brandAnalysis')
            ->willReturn('Analyze');

        // Mock 500 server error
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 500,
                    'message' => 'Internal server error'
                ]
            ], 500)
        ]);

        $this->expectException(\Exception::class);

        $this->service->analyzeBrandStyle([
            Storage::disk('public')->path($imagePath)
        ]);
    }

    /** @test */
    public function it_handles_malformed_json_response()
    {
        Storage::fake('public');
        $imagePath = 'test/image.jpg';
        Storage::disk('public')->put($imagePath, 'fake-image-content');

        $this->promptService->method('brandAnalysis')
            ->willReturn('Analyze');

        // Mock response with invalid JSON
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => 'This is not valid JSON at all'
                        ]]
                    ]
                ]]
            ], 200)
        ]);

        $this->expectException(\Exception::class);

        $this->service->analyzeBrandStyle([
            Storage::disk('public')->path($imagePath)
        ]);
    }
}
