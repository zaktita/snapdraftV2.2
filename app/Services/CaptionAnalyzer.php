<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CaptionAnalyzer
{
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    protected ?string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.vision_model', 'gemini-3.1-pro-preview');
    }

    /**
     * Analyze caption to extract required elements and intent.
     * Results are cached for efficiency.
     */
    public function analyze(string $caption, ?string $title = null, ?string $description = null, ?string $format = null): array
    {
        $cacheKey = 'caption_analysis_' . md5($caption . $title . $description . $format);

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($caption, $title, $description, $format) {
            return $this->performAnalysis($caption, $title, $description, $format);
        });
    }

    /**
     * Perform AI-based caption analysis using Gemini.
     */
    private function performAnalysis(string $caption, ?string $title, ?string $description, ?string $format): array
    {
        $prompt = $this->buildAnalysisPrompt($caption, $title, $description, $format);

        try {
            $payload = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 2000,
                    'responseModalities' => ['TEXT'],
                ],
            ];

            $response = $this->http()->post(
                "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}",
                $payload
            );

            if (!$response->successful()) {
                throw new RuntimeException('Gemini API request failed: ' . $response->body());
            }

            $body = $response->json();
            $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$text) {
                throw new RuntimeException('Gemini returned no text');
            }

            $analysis = $this->parseJson($text);

            if (!$analysis) {
                Log::warning('Caption analysis returned invalid JSON', [
                    'response' => $text,
                ]);

                return $this->getFallbackAnalysis($caption, $title, $description);
            }

            return $analysis;
        } catch (\Exception $e) {
            Log::error('Caption analysis failed', [
                'error' => $e->getMessage(),
                'caption' => $caption,
            ]);

            return $this->getFallbackAnalysis($caption, $title, $description);
        }
    }

    /**
     * Build the analysis prompt for Gemini.
     */
    private function buildAnalysisPrompt(string $caption, ?string $title, ?string $description, ?string $format): string
    {
        $context = "Caption: $caption\n";
        if ($title) {
            $context .= "Title: $title\n";
        }
        if ($description) {
            $context .= "Description: $description\n";
        }
        if ($format) {
            $context .= "Format: $format\n";
        }

        return <<<PROMPT
You are an expert content analyzer. Analyze the following content and detect what visual elements are needed.

$context

Return ONLY valid JSON in this exact structure:

```json
{
    "required_elements": {
        "text_elements": {
            "headline": true,
            "subheadline": false,
            "body_text": false,
            "tagline": false,
            "quote": false,
            "testimonial": false,
            "caption": false,
            "fine_print": false
        },
        "temporal_elements": {
            "date": false,
            "time": false,
            "duration": false,
            "deadline": false,
            "schedule": false,
            "calendar": false
        },
        "location_elements": {
            "venue_name": false,
            "address": false,
            "city": false,
            "map": false,
            "directions": false
        },
        "people_elements": {
            "headshot": false,
            "group_photo": false,
            "speaker_name": false,
            "speaker_title": false,
            "speaker_company": false,
            "person_count": 0
        },
        "ecommerce_elements": {
            "product_image": false,
            "price": false,
            "sale_price": false,
            "discount_badge": false,
            "rating_stars": false,
            "review_count": false,
            "product_specs": false,
            "size_selector": false,
            "color_swatches": false,
            "inventory_status": false,
            "shipping_info": false
        },
        "marketing_elements": {
            "cta_button": false,
            "cta_text": "",
            "social_proof": false,
            "statistic": false,
            "percentage": false,
            "badge": false,
            "award": false,
            "certification": false,
            "trust_seal": false
        },
        "branding_elements": {
            "logo": false,
            "tagline": false,
            "brand_color_blocks": false,
            "watermark": false,
            "qr_code": false,
            "social_handles": false,
            "website_url": false
        },
        "creative_artistic_elements": {
            "illustration": false,
            "abstract_shapes": false,
            "pattern": false,
            "texture": false,
            "artistic_filter": false,
            "hand_drawn_elements": false,
            "brush_strokes": false,
            "collage": false
        },
        "data_visualization": {
            "chart": false,
            "graph": false,
            "infographic": false,
            "timeline": false,
            "progress_bar": false,
            "icon_grid": false
        },
        "interactive_ui": {
            "form_fields": false,
            "dropdown": false,
            "checkbox": false,
            "radio_button": false,
            "slider": false,
            "tabs": false,
            "accordion": false
        }
    },
    "layout_complexity": "simple | moderate | complex",
    "intent": "string describing the purpose (e.g., 'promote event', 'sell product', 'educate audience')",
    "tone": "string describing desired tone (e.g., 'professional', 'playful', 'urgent')",
    "style_preference": "string if explicit style mentioned (e.g., 'modern', 'vintage', 'minimalist')",
    "color_preference": ["#HEX if mentioned"],
    "priority_elements": ["list of 3-5 most critical elements in order of importance"]
}
```

Rules:
- Set element to true ONLY if explicitly mentioned or strongly implied
- layout_complexity:
  * simple = headline + 1-2 elements
  * moderate = headline + 3-5 elements
  * complex = headline + 6+ elements OR data visualization
- Be conservative with element detection (false negatives better than false positives)
- Output ONLY valid JSON (no markdown, no prose)
PROMPT;
    }

    /**
     * Rule-based fallback analysis if AI fails.
     */
    private function getFallbackAnalysis(string $caption, ?string $title, ?string $description): array
    {
        $text = strtolower($caption . ' ' . $title . ' ' . $description);
        $elementCount = 0;

        $requiredElements = [
            'text_elements' => [
                'headline' => !empty($title) || !empty($caption),
                'subheadline' => !empty($description),
                'body_text' => str_word_count($text) > 20,
                'tagline' => false,
                'quote' => str_contains($text, '"') || str_contains($text, 'said'),
                'testimonial' => str_contains($text, 'testimonial') || str_contains($text, 'review'),
                'caption' => false,
                'fine_print' => false,
            ],
            'temporal_elements' => [
                'date' => $this->detectPattern($text, ['date', 'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december', '2024', '2025', '2026']),
                'time' => $this->detectPattern($text, ['am', 'pm', 'time', 'clock', 'hour']),
                'duration' => $this->detectPattern($text, ['duration', 'hours', 'minutes', 'days']),
                'deadline' => $this->detectPattern($text, ['deadline', 'until', 'ends', 'expires']),
                'schedule' => $this->detectPattern($text, ['schedule', 'agenda', 'itinerary']),
                'calendar' => $this->detectPattern($text, ['calendar', 'event']),
            ],
            'location_elements' => [
                'venue_name' => $this->detectPattern($text, ['venue', 'location', 'hall', 'center', 'auditorium']),
                'address' => $this->detectPattern($text, ['street', 'avenue', 'address']),
                'city' => $this->detectPattern($text, ['city', 'town']),
                'map' => $this->detectPattern($text, ['map', 'directions']),
                'directions' => $this->detectPattern($text, ['directions', 'how to get']),
            ],
            'people_elements' => [
                'headshot' => $this->detectPattern($text, ['headshot', 'photo', 'portrait']),
                'group_photo' => $this->detectPattern($text, ['group', 'team', 'people']),
                'speaker_name' => $this->detectPattern($text, ['speaker', 'presenter', 'host']),
                'speaker_title' => $this->detectPattern($text, ['title', 'position']),
                'speaker_company' => $this->detectPattern($text, ['company', 'organization']),
                'person_count' => 0,
            ],
            'ecommerce_elements' => [
                'product_image' => $this->detectPattern($text, ['product', 'item', 'merchandise']),
                'price' => $this->detectPattern($text, ['price', '$', 'cost', 'usd', 'eur']),
                'sale_price' => $this->detectPattern($text, ['sale', 'discount', 'off']),
                'discount_badge' => $this->detectPattern($text, ['discount', 'sale', 'offer']),
                'rating_stars' => $this->detectPattern($text, ['rating', 'stars', 'review']),
                'review_count' => $this->detectPattern($text, ['reviews', 'ratings']),
                'product_specs' => $this->detectPattern($text, ['specs', 'specifications', 'features']),
                'size_selector' => $this->detectPattern($text, ['size', 'sizing']),
                'color_swatches' => $this->detectPattern($text, ['color', 'colors']),
                'inventory_status' => $this->detectPattern($text, ['stock', 'available', 'inventory']),
                'shipping_info' => $this->detectPattern($text, ['shipping', 'delivery']),
            ],
            'marketing_elements' => [
                'cta_button' => $this->detectPattern($text, ['register', 'sign up', 'buy', 'learn more', 'get started', 'join', 'subscribe']),
                'cta_text' => '',
                'social_proof' => $this->detectPattern($text, ['trusted by', 'used by', 'customers']),
                'statistic' => preg_match('/\d+%|\d+k|\d+ million/', $text),
                'percentage' => str_contains($text, '%'),
                'badge' => $this->detectPattern($text, ['badge', 'award', 'certified']),
                'award' => $this->detectPattern($text, ['award', 'winner']),
                'certification' => $this->detectPattern($text, ['certified', 'certification']),
                'trust_seal' => $this->detectPattern($text, ['secure', 'verified', 'trusted']),
            ],
            'branding_elements' => [
                'logo' => true, // Always include logo
                'tagline' => false,
                'brand_color_blocks' => false,
                'watermark' => false,
                'qr_code' => $this->detectPattern($text, ['qr', 'scan']),
                'social_handles' => $this->detectPattern($text, ['@', 'instagram', 'twitter', 'facebook']),
                'website_url' => $this->detectPattern($text, ['www', '.com', 'website', 'url']),
            ],
            'creative_artistic_elements' => [
                'illustration' => $this->detectPattern($text, ['illustration', 'illustrated', 'artwork']),
                'abstract_shapes' => $this->detectPattern($text, ['abstract', 'shapes']),
                'pattern' => $this->detectPattern($text, ['pattern']),
                'texture' => $this->detectPattern($text, ['texture']),
                'artistic_filter' => false,
                'hand_drawn_elements' => $this->detectPattern($text, ['hand-drawn', 'sketch']),
                'brush_strokes' => false,
                'collage' => $this->detectPattern($text, ['collage']),
            ],
            'data_visualization' => [
                'chart' => $this->detectPattern($text, ['chart', 'graph']),
                'graph' => $this->detectPattern($text, ['graph']),
                'infographic' => $this->detectPattern($text, ['infographic']),
                'timeline' => $this->detectPattern($text, ['timeline']),
                'progress_bar' => $this->detectPattern($text, ['progress']),
                'icon_grid' => $this->detectPattern($text, ['icons', 'icon grid']),
            ],
            'interactive_ui' => [
                'form_fields' => $this->detectPattern($text, ['form', 'input', 'field']),
                'dropdown' => false,
                'checkbox' => false,
                'radio_button' => false,
                'slider' => false,
                'tabs' => false,
                'accordion' => false,
            ],
        ];

        // Count total elements
        foreach ($requiredElements as $category => $elements) {
            foreach ($elements as $element => $present) {
                if ($present === true) {
                    $elementCount++;
                }
            }
        }

        // Determine complexity
        $complexity = $elementCount <= 3 ? 'simple' : ($elementCount <= 8 ? 'moderate' : 'complex');

        return [
            'required_elements' => $requiredElements,
            'layout_complexity' => $complexity,
            'intent' => $this->detectIntent($text),
            'tone' => $this->detectTone($text),
            'style_preference' => null,
            'color_preference' => [],
            'priority_elements' => ['headline', 'logo', 'cta_button'],
        ];
    }

    /**
     * Detect if any pattern exists in text.
     */
    private function detectPattern(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_contains($text, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect intent from text.
     */
    private function detectIntent(string $text): string
    {
        if ($this->detectPattern($text, ['event', 'conference', 'workshop', 'seminar'])) {
            return 'promote event';
        }
        if ($this->detectPattern($text, ['buy', 'shop', 'sale', 'product'])) {
            return 'sell product';
        }
        if ($this->detectPattern($text, ['learn', 'education', 'course', 'training'])) {
            return 'educate audience';
        }
        if ($this->detectPattern($text, ['brand', 'awareness', 'launch'])) {
            return 'build brand awareness';
        }

        return 'general communication';
    }

    /**
     * Detect tone from text.
     */
    private function detectTone(string $text): string
    {
        if ($this->detectPattern($text, ['urgent', 'now', 'limited', 'hurry'])) {
            return 'urgent';
        }
        if ($this->detectPattern($text, ['professional', 'business', 'corporate'])) {
            return 'professional';
        }
        if ($this->detectPattern($text, ['fun', 'exciting', 'amazing'])) {
            return 'playful';
        }
        if ($this->detectPattern($text, ['elegant', 'luxury', 'premium'])) {
            return 'elegant';
        }

        return 'neutral';
    }

    /**
     * Parse JSON from Gemini response (handles markdown fences).
     */
    private function parseJson(string $text): ?array
    {
        // Try direct decode first
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Strip markdown fences
        if (preg_match('/```json\s*(\{.*\})\s*```/s', $text, $m)) {
            $clean = $m[1];
        } else {
            // Extract first {...} block
            $start = strpos($text, '{');
            $end = strrpos($text, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $clean = substr($text, $start, $end - $start + 1);
            } else {
                return null;
            }
        }

        $decoded = json_decode($clean, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /**
     * HTTP client for Gemini API.
     */
    private function http()
    {
        $client = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(60)
            ->connectTimeout(15)
            ->retry(2, 1000);

        if (config('app.env') !== 'production') {
            $client = $client->withOptions(['verify' => false]);
        }

        return $client;
    }
}
