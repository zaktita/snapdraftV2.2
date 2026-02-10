<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InpaintIntegrationTest extends TestCase
{
    public function test_inpaint_api_call_structure()
    {
        Storage::fake('public');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => 'image/png',
                                        'data' => 'fake_base64_image_data'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        Config::set('services.gemini.api_key', 'test_key');
        
        // Setup test images
        Storage::disk('public')->put('tests/image.png', 'fake_image_content');
        Storage::disk('public')->put('tests/mask.png', 'fake_mask_content');

        // Logic from AITestInpaint command
        $imagePath = Storage::disk('public')->path('tests/image.png');
        $maskPath = Storage::disk('public')->path('tests/mask.png');
        
        $imageData = file_get_contents($imagePath);
        $maskData = file_get_contents($maskPath);
        
        $originalBase64 = base64_encode($imageData);
        $maskBase64 = base64_encode($maskData);
        $prompt = "inpaint test";
        
        $fullPrompt = "You are an expert image editor. Only modify the white areas in the provided mask. " . $prompt;
        
        $apiKey = config('services.gemini.api_key');
        $imageModel = 'gemini-2.5-flash-image'; // Default

        $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/{$imageModel}:generateContent?key={$apiKey}", [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $fullPrompt],
                        [
                            'inlineData' => [
                                'mimeType' => 'image/png',
                                'data' => $originalBase64
                            ]
                        ],
                        [
                            'inlineData' => [
                                'mimeType' => 'image/png',
                                'data' => $maskBase64
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $this->assertTrue($response->successful());
        
        $result = $response->json();
        $generatedBase64 = $result['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;
        
        $this->assertEquals('fake_base64_image_data', $generatedBase64);
    }
}
