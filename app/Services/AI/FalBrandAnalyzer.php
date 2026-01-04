<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FalBrandAnalyzer
{
    protected ?string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.api_key');
        $this->model = 'openai/gpt-5.2';
    }

    /**
     * Analyze a set of reference images using OpenRouter GPT-5.2.
     * Returns per-image categories plus aggregated brand DNA.
     *
     * @param array $referencePaths Absolute or storage-relative paths to images
     * @return array
     */
    public function analyze(array $referencePaths): array
    {
        if (!$this->apiKey) {
            throw new RuntimeException('OpenRouter API key not configured');
        }

        if (empty($referencePaths)) {
            return [
                'brand_dna' => null,
                'images' => [],
                'coherence_score' => null,
                'notes' => 'No reference images supplied',
                'model' => $this->model,
            ];
        }

        $imageContent = $this->prepareImageContent($referencePaths);
        if (empty($imageContent)) {
            throw new RuntimeException('No readable images found for analysis');
        }

        $start = microtime(true);

        // OpenRouter API call with GPT-5.2
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'HTTP-Referer' => config('services.openrouter.site_url'),
            'X-Title' => config('services.openrouter.site_name'),
        ])
        ->timeout(120)
        ->post('https://openrouter.ai/api/v1/chat/completions', [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => array_merge(
                        [['type' => 'text', 'text' => $this->buildInstruction()]],
                        $imageContent
                    ),
                ],
            ],
            'max_tokens' => 4000,
            'temperature' => 0.3,
        ]);

        $durationMs = round((microtime(true) - $start) * 1000);

        if (!$response->successful()) {
            Log::error('OpenRouter GPT-5.2: API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('OpenRouter API request failed: ' . $response->body());
        }

        $parsed = $this->parseResponse($response->json());

        Log::info('OpenRouter GPT-5.2: analysis complete', [
            'model' => $this->model,
            'references_sent' => count($imageContent),
            'duration_ms' => $durationMs,
        ]);

        return $parsed;
    }

    /**
     * Build generation prompt from brand DNA (same as Gemini analyzer).
     */
    public function buildGenerationPrompt(array $analysis, string $caption): string
    {
        $dna = $analysis['brand_dna'] ?? [];

        $lines = [];
        $lines[] = 'Create a brand-true visual based on this caption: ' . trim($caption);

        $colors = $this->listToLine($dna['visual_identity']['color_system']['primary_palette'] ?? []);
        $accents = $this->listToLine($dna['visual_identity']['color_system']['secondary_accents'] ?? []);
        if ($colors || $accents) {
            $lines[] = 'Color system: primary ' . ($colors ?: 'brand palette') . '; accents ' . ($accents ?: 'minimal');
        }

        $typography = $this->listToLine($dna['visual_identity']['typography']['headline'] ?? []);
        if ($typography) {
            $lines[] = 'Typography: ' . $typography;
        }

        $imagery = $this->listToLine($dna['imagery']['human_style'] ?? []);
        if ($imagery) {
            $lines[] = 'Imagery: ' . $imagery;
        }

        $signature = $this->listToLine($dna['signature_elements'] ?? []);
        if ($signature) {
            $lines[] = 'Signature elements: ' . $signature;
        }

        $lines[] = 'Output: one polished, photorealistic image, on-brand, clean edges.';

        return implode("\n", array_filter($lines));
    }

    /**
     * Convert file paths to base64-encoded images for OpenRouter vision API.
     * Using base64 because localhost URLs aren't accessible to external APIs.
     */
    protected function prepareImageContent(array $paths): array
    {
        $content = [];
        
        foreach (array_slice($paths, 0, 10) as $path) {
            $imageData = null;
            $mimeType = null;

            // Read file content
            if (Storage::disk('public')->exists($path)) {
                $imageData = Storage::disk('public')->get($path);
                $mimeType = Storage::disk('public')->mimeType($path);
            } elseif (file_exists($path)) {
                $imageData = file_get_contents($path);
                $mimeType = mime_content_type($path);
            }

            if ($imageData && $mimeType) {
                $base64 = base64_encode($imageData);
                $content[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => "data:{$mimeType};base64,{$base64}"
                    ],
                ];
            }
        }

        return $content;
    }

    /**
     * Instruction prompt for OpenRouter GPT-5.2.
     */
    protected function buildInstruction(): string
    {
        return <<<'PROMPT'
You are a brand visual analyst. Analyze ALL provided images together. Return STRICT JSON ONLY in this exact shape:
{
    "brand_dna": {
        "brand_positioning": {
            "core_promise": ["string"],
            "attributes": ["string"],
            "one_line_summary": "string"
        },
        "visual_identity": {
            "color_system": {
                "primary_palette": ["color descriptions"],
                "secondary_accents": ["color descriptions"],
                "rules": ["usage rules"]
            },
            "typography": {
                "headline": ["font characteristics"],
                "body": ["font characteristics"],
                "behavior": ["alignment, spacing rules"]
            }
        },
        "imagery": {
            "human_style": ["subject descriptions"],
            "wardrobe": ["clothing style"],
            "emotion": ["mood keywords"],
            "composition_rules": ["framing rules"],
            "depth_strategy": ["depth techniques"]
        },
        "graphic_language": {
            "ui_cards": ["card style"],
            "icons_symbols": ["icon descriptions"],
            "icon_style": ["style characteristics"]
        },
        "layout_system": {
            "structure": ["grid type"],
            "negative_space": ["whitespace rules"],
            "balance_logic": ["symmetry approach"],
            "reading_flow": ["eye path"]
        },
        "copywriting": {
            "tone": ["tone keywords"],
            "language_patterns": ["sentence structures"],
            "formula": ["headline formulas"]
        },
        "signature_elements": ["unique repeating elements"],
        "replication_checklist": ["must-have elements"]
    },
    "images": [
        {
            "index": 0,
            "quality": "good|acceptable|poor",
            "role": "style_ref|context|avoid",
            "notes": "specific observations"
        }
    ],
    "coherence_score": 0.0-1.0,
    "notes": "overall brand consistency observations"
}

NO markdown, NO code fences, ONLY valid JSON. Be comprehensive but concise.
PROMPT;
    }

    /**
     * Parse OpenRouter response and extract JSON from assistant message.
     */
    protected function parseResponse(array $response): array
    {
        $message = $response['choices'][0]['message'] ?? null;
        $text = $message['content'] ?? null;
        
        if (!$text) {
            Log::error('OpenRouter GPT-5.2: no content in response', ['response' => $response]);
            throw new RuntimeException('No content in OpenRouter response');
        }

        // Remove markdown code fences if present
        $text = preg_replace('/^```json\s*/', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        $text = trim($text);

        $decoded = json_decode($text, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('OpenRouter GPT-5.2: JSON parse failed', [
                'error' => json_last_error_msg(),
                'text' => substr($text, 0, 500),
            ]);
            throw new RuntimeException('Failed to parse OpenRouter response as JSON: ' . json_last_error_msg());
        }

        // Add model info
        $decoded['model'] = $this->model;
        $decoded['provider'] = 'openrouter-gpt-5.2';

        return $decoded;
    }

    /**
     * Helper to join array to comma-separated line.
     */
    protected function listToLine(array $items): string
    {
        return implode(', ', array_filter($items));
    }
}
