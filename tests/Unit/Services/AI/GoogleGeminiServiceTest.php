<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\GoogleGeminiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class GoogleGeminiServiceTest extends TestCase
{
    public function test_analyze_brand_style_returns_decoded_json(): void
    {
        config()->set('services.gemini.api_key', 'test-key');
        config()->set('services.gemini.vision_model', 'gemini-vision');

        Storage::fake('public');
        Storage::disk('public')->put('refs/one.png', 'fake-image-bytes');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => '{"style_clusters":[{"cluster_id":1,"name":"A","image_indices":[0,1]}],"global_rules":["Rule"]}',
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $service = new GoogleGeminiService();
        $result = $service->analyzeBrandStyle([Storage::disk('public')->path('refs/one.png')]);

        $this->assertArrayHasKey('style_clusters', $result);
        $this->assertSame('A', $result['style_clusters'][0]['name']);
    }

    public function test_generate_with_references_returns_image_data_keys(): void
    {
        config()->set('services.gemini.api_key', 'test-key');
        config()->set('services.gemini.image_model', 'gemini-image');

        Storage::fake('public');
        Storage::disk('public')->put('refs/two.png', 'fake-image-bytes');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'inlineData' => [
                                'mimeType' => 'image/png',
                                'data' => base64_encode('img-bytes'),
                            ],
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $service = new GoogleGeminiService();
        $result = $service->generateWithReferences('Test prompt', [Storage::disk('public')->path('refs/two.png')], [], 'square');

        $this->assertArrayHasKey('image_base64', $result);
        $this->assertArrayHasKey('image_data', $result);
    }

    public function test_it_throws_for_missing_api_key(): void
    {
        config()->set('services.gemini.api_key', null);

        $service = new GoogleGeminiService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GEMINI_API_KEY is not configured');

        $service->generateWithReferences('Prompt', [], [], 'square');
    }
}
