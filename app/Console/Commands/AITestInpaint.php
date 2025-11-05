<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AITestInpaint extends Command
{
    protected $signature = 'ai:test-inpaint {--prompt="inpaint the masked area with \"maintenant\" make sure it\'s spelled correctly"}';
    protected $description = 'Test Gemini image inpainting using storage/app/public/tests/image.png and mask.png';

    public function handle()
    {
        $this->info('Starting Gemini inpaint test...');

        $imagePath = storage_path('app/public/tests/image.png');
        $maskPath = storage_path('app/public/tests/mask.png');

        if (!file_exists($imagePath) || !file_exists($maskPath)) {
            $this->error('Test files not found. Expected: storage/app/public/tests/image.png and mask.png');
            return 1;
        }

        $prompt = $this->option('prompt');

        $imageData = file_get_contents($imagePath);
        $maskData = file_get_contents($maskPath);

        $originalBase64 = base64_encode($imageData);
        $maskBase64 = base64_encode($maskData);

        $apiKey = config('services.gemini.api_key');
        $imageModel = config('services.gemini.image_model', 'gemini-2.5-flash-image');

        if (!$apiKey) {
            $this->error('GEMINI_API_KEY not configured.');
            return 1;
        }

        $fullPrompt = "You are an expert image editor. Only modify the white areas in the provided mask. " . $prompt;

        $this->line('Model: ' . $imageModel);
        $this->line('Prompt: ' . $fullPrompt);

        try {
            $response = Http::withoutVerifying()
                ->timeout(180)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$imageModel}:generateContent?key={$apiKey}", [
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

            if (!$response->successful()) {
                $this->error('Gemini API error: ' . $response->status() . ' - ' . $response->body());
                return 1;
            }

            $result = $response->json();
            $generatedBase64 = null;
            if (isset($result['candidates'][0]['content']['parts'])) {
                foreach ($result['candidates'][0]['content']['parts'] as $part) {
                    if (isset($part['inlineData']['data'])) {
                        $generatedBase64 = $part['inlineData']['data'];
                        break;
                    }
                }
            }

            if (!$generatedBase64) {
                $this->error('No image found in Gemini response.');
                $this->line(json_encode($result, JSON_PRETTY_PRINT));
                return 1;
            }

            $filename = 'generated_' . date('Ymd_His') . '.png';
            $savePath = 'tests/' . $filename;
            Storage::disk('public')->put($savePath, base64_decode($generatedBase64));

            $url = Storage::disk('public')->url($savePath);
            $this->info('Saved: ' . $savePath);
            $this->line('URL: ' . $url);

            return 0;
        } catch (\Throwable $e) {
            $this->error('Exception: ' . $e->getMessage());
            return 1;
        }
    }
}
