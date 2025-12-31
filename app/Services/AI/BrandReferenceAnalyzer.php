<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BrandReferenceAnalyzer
{
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    protected ?string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.vision_model', 'gemini-2.5-pro');
    }

    /**
     * Analyze a set of reference images in a single Gemini 2.5 Pro call.
     * Returns per-image categories plus aggregated brand DNA.
     *
     * @param array $referencePaths Absolute or storage-relative paths to images
     * @return array
     */
    public function analyze(array $referencePaths): array
    {
        $this->ensureConfigured();

        if (empty($referencePaths)) {
            return [
                'brand_dna' => null,
                'images' => [],
                'coherence_score' => null,
                'notes' => 'No reference images supplied',
            ];
        }

        $parts = $this->buildParts($referencePaths);
        if (count($parts) === 0) {
            throw new RuntimeException('No readable images found for analysis');
        }

        $systemInstruction = [
            'text' => $this->buildInstruction(),
        ];

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => array_merge([$systemInstruction], $parts),
                ],
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT'],
            ],
        ];

        $start = microtime(true);

        $response = $this->http()->post(
            "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}",
            $payload
        );

        $durationMs = round((microtime(true) - $start) * 1000);

        $parsed = $this->parseResponse($response);

        Log::info('BrandReferenceAnalyzer: analysis complete', [
            'model' => $this->model,
            'references_sent' => count($parts),
            'duration_ms' => $durationMs,
        ]);

        return $parsed;
    }

    /**
     * Build a generation-ready prompt from the brand DNA JSON and a user caption.
     */
    public function buildGenerationPrompt(array $analysis, string $caption): string
    {
        $dna = $analysis['brand_dna'] ?? [];

        $lines = [];
        $lines[] = 'Create a brand-true visual based on this caption: ' . trim($caption);

        $colors = $this->listToLine($dna['visual_identity']['color_system']['primary_palette'] ?? []);
        $accents = $this->listToLine($dna['visual_identity']['color_system']['secondary_accents'] ?? []);
        $colorRules = $this->listToLine($dna['visual_identity']['color_system']['rules'] ?? []);
        if ($colors || $accents || $colorRules) {
            $lines[] = 'Color system: primary ' . ($colors ?: 'brand palette') . '; accents ' . ($accents ?: 'minimal');
            if ($colorRules) {
                $lines[] = 'Color rules: ' . $colorRules;
            }
        }

        $typography = $this->listToLine($dna['visual_identity']['typography']['headline'] ?? []);
        $body = $this->listToLine($dna['visual_identity']['typography']['body'] ?? []);
        $behavior = $this->listToLine($dna['visual_identity']['typography']['behavior'] ?? []);
        if ($typography || $body || $behavior) {
            $lines[] = 'Typography: headlines ' . ($typography ?: 'brand headline style') . '; body ' . ($body ?: 'clean body style');
            if ($behavior) {
                $lines[] = 'Type behavior: ' . $behavior;
            }
        }

        $imagery = $this->listToLine($dna['imagery']['human_style'] ?? []);
        $wardrobe = $this->listToLine($dna['imagery']['wardrobe'] ?? []);
        $emotion = $this->listToLine($dna['imagery']['emotion'] ?? []);
        $composition = $this->listToLine($dna['imagery']['composition_rules'] ?? []);
        $depth = $this->listToLine($dna['imagery']['depth_strategy'] ?? []);
        if ($imagery || $wardrobe || $emotion || $composition || $depth) {
            $lines[] = 'Imagery: ' . ($imagery ?: 'brand subjects') . '; wardrobe ' . ($wardrobe ?: 'brand wardrobe');
            if ($emotion) {
                $lines[] = 'Mood: ' . $emotion;
            }
            if ($composition) {
                $lines[] = 'Composition rules: ' . $composition;
            }
            if ($depth) {
                $lines[] = 'Depth strategy: ' . $depth;
            }
        }

        $ui = $this->listToLine($dna['graphic_language']['ui_cards'] ?? []);
        $icons = $this->listToLine($dna['graphic_language']['icons_symbols'] ?? []);
        $iconStyle = $this->listToLine($dna['graphic_language']['icon_style'] ?? []);
        if ($ui || $icons || $iconStyle) {
            $lines[] = 'Graphic language: ' . implode(' | ', array_filter([$ui, $icons, $iconStyle]));
        }

        $layout = $this->listToLine($dna['layout_system']['structure'] ?? []);
        $negativeSpace = $this->listToLine($dna['layout_system']['negative_space'] ?? []);
        $balance = $this->listToLine($dna['layout_system']['balance_logic'] ?? []);
        $flow = $this->listToLine($dna['layout_system']['reading_flow'] ?? []);
        if ($layout || $negativeSpace || $balance || $flow) {
            $lines[] = 'Layout: ' . implode(' | ', array_filter([$layout, $negativeSpace, $balance, $flow]));
        }

        $tone = $this->listToLine($dna['copywriting']['tone'] ?? []);
        $languagePatterns = $this->listToLine($dna['copywriting']['language_patterns'] ?? []);
        $formula = $this->listToLine($dna['copywriting']['formula'] ?? []);
        if ($tone || $languagePatterns || $formula) {
            $lines[] = 'Copy tone: ' . implode(' | ', array_filter([$tone, $languagePatterns, $formula]));
        }

        $signature = $this->listToLine($dna['signature_elements'] ?? []);
        if ($signature) {
            $lines[] = 'Signature elements to keep: ' . $signature;
        }

        $checklist = $this->listToLine($dna['replication_checklist'] ?? []);
        if ($checklist) {
            $lines[] = 'Replication checklist: ' . $checklist;
        }

        $positioning = $dna['brand_positioning']['one_line_summary'] ?? null;
        if ($positioning) {
            $lines[] = 'Brand promise: ' . $positioning;
        }

        $lines[] = 'Output: one polished, photorealistic image, on-brand lighting, clean edges, no watermarks, no extra text unless requested.';

        return implode("\n", array_filter($lines));
    }

    /**
     * Build image parts for Gemini inlineData payload.
     */
    protected function buildParts(array $paths): array
    {
        $parts = [];
        foreach (array_slice($paths, 0, 10) as $path) {
            if ($part = $this->fileToPart($path)) {
                $parts[] = $part;
            }
        }

        return $parts;
    }

    /**
     * Instruction prompt to steer Gemini to output strict JSON.
     */
        protected function buildInstruction(): string
        {
                return <<<'PROMPT'
You are a brand visual analyst. Analyze ALL provided images together. Return STRICT JSON ONLY in this exact shape:
{
    "brand_dna": {
        "brand_positioning": {
            "core_promise": ["string", ...],
            "attributes": ["string", ...],
            "one_line_summary": "string"
        },
        "visual_identity": {
            "color_system": {
                "primary_palette": ["string", ...],
                "secondary_accents": ["string", ...],
                "rules": ["string", ...]
            },
            "typography": {
                "headline": ["string", ...],
                "body": ["string", ...],
                "behavior": ["string", ...]
            }
        },
        "imagery": {
            "human_style": ["string", ...],
            "wardrobe": ["string", ...],
            "emotion": ["string", ...],
            "composition_rules": ["string", ...],
            "depth_strategy": ["string", ...]
        },
        "graphic_language": {
            "ui_cards": ["string", ...],
            "icons_symbols": ["string", ...],
            "icon_style": ["string", ...]
        },
        "layout_system": {
            "structure": ["string", ...],
            "negative_space": ["string", ...],
            "balance_logic": ["string", ...],
            "reading_flow": ["string", ...]
        },
        "copywriting": {
            "tone": ["string", ...],
            "language_patterns": ["string", ...],
            "formula": ["string", ...]
        },
        "signature_elements": ["string", ...],
        "replication_checklist": ["string", ...]
    },
    "images": [
        {
            "index": <int>,
            "role": "style_ref" | "product" | "brand_identity" | "other",
            "labels": ["keyword", ...],
            "quality": "good" | "blurry" | "off_topic" | "low_light",
            "suggested_category": "string",
            "notes": "string"
        }
    ],
    "coherence_score": <0-100>,
    "detected_categories": ["string", ...]
}
Rules:
- Output ONLY JSON, no markdown, no prose.
- If uncertain, still fill fields with best-guess short phrases.
- Keep strings concise; prefer bullet-like phrases over sentences.
- Do not omit keys; use [] or "" if truly unknown.
PROMPT;
        }

    /**
     * Convert a file path to Gemini inlineData part.
     */
    protected function fileToPart(string $path): ?array
    {
        if (Storage::disk('public')->exists($path)) {
            $mime = Storage::disk('public')->mimeType($path);
            $content = Storage::disk('public')->get($path);
        } elseif (file_exists($path)) {
            $mime = mime_content_type($path) ?: 'application/octet-stream';
            $content = file_get_contents($path);
        } else {
            Log::warning('BrandReferenceAnalyzer: image not found', ['path' => $path]);
            return null;
        }

        if (empty($content)) {
            Log::warning('BrandReferenceAnalyzer: empty image content', ['path' => $path]);
            return null;
        }

        return [
            'inlineData' => [
                'mimeType' => $mime,
                'data' => base64_encode($content),
            ],
        ];
    }

    protected function parseResponse($response): array
    {
        if (!$response->successful()) {
            Log::error('BrandReferenceAnalyzer: Gemini response error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Gemini analysis request failed');
        }

        $body = $response->json();
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$text) {
            Log::error('BrandReferenceAnalyzer: unexpected response shape', ['body' => $body]);
            throw new RuntimeException('Gemini analysis returned no text');
        }

        // Attempt strict JSON decode first
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Fallback: strip markdown fences or grab first JSON object
        $clean = $text;

        // Strip ```json ... ``` blocks if present
        if (preg_match('/```json\s*(\{.*\})\s*```/s', $text, $m)) {
            $clean = $m[1];
        } else {
            // Extract first {...} block
            $start = strpos($text, '{');
            $end = strrpos($text, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $clean = substr($text, $start, $end - $start + 1);
            }
        }

        $decoded = json_decode($clean, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('BrandReferenceAnalyzer: JSON decode failed', [
                'error' => json_last_error_msg(),
                'raw_text' => $text,
                'clean_attempt' => $clean,
            ]);
            throw new RuntimeException('Unable to parse Gemini analysis JSON');
        }

        return $decoded;
    }

    protected function listToLine($value): string
    {
        $items = [];

        if (is_string($value)) {
            $value = [$value];
        }

        if (is_array($value)) {
            array_walk_recursive($value, function ($item) use (&$items) {
                if (is_scalar($item)) {
                    $trimmed = trim((string) $item);
                    if ($trimmed !== '') {
                        $items[] = $trimmed;
                    }
                }
            });
        }

        return implode(', ', $items);
    }

    protected function ensureConfigured(): void
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('Gemini API Key is missing');
        }
    }

    protected function http()
    {
        $client = Http::withHeaders(['Content-Type' => 'application/json'])
            // Increase timeout to handle large image uploads/processing
            ->timeout(90)
            ->connectTimeout(15)
            // Lightweight retry to survive transient network hiccups
            ->retry(2, 1000);

        if (config('app.env') !== 'production') {
            $client = $client->withOptions(['verify' => false]);
        }

        return $client;
    }
}
