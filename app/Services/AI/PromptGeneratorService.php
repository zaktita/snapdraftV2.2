<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PromptGeneratorService
{
    protected string $baseUrl = 'https://openrouter.ai/api/v1/chat/completions';
    protected ?string $apiKey;
    protected string $siteName;
    protected string $siteUrl;

    /**
     * Models to test for prompt generation.
     */
    protected array $models = [
        'google/gemini-3.1-pro-preview',
        'openai/gpt-5.2',

    ];

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.api_key');
        $this->siteUrl = config('services.openrouter.site_url');
        $this->siteName = config('services.openrouter.site_name');
    }

    /**
     * Generate prompts using multiple AI models in parallel.
     * Returns array of results with timing and cost information.
     *
     * @param array $analysis Brand analysis with style_clusters and image_analysis
     * @param string $caption User-provided caption
     * @param string|null $title Optional title
     * @param string|null $description Optional description
     * @return array ['successful' => [...], 'failed' => [...]]
     */
    public function generatePromptsMultiModel(
        array $analysis,
        string $caption,
        ?string $title = null,
        ?string $description = null,
        string $textDensity = 'standard'
    ): array {
        if (!$this->apiKey) {
            throw new RuntimeException('OpenRouter API key not configured');
        }

        $clusters = $analysis['style_clusters'] ?? [];
        $images = $analysis['image_analysis'] ?? [];

        if (empty($clusters)) {
            throw new RuntimeException('No style clusters available for prompt generation');
        }

        // Build a description of clusters for the models
        $clusterDescription = $this->buildClusterDescription($clusters, $images);

        // Build a list of requests with their model names and start times
        $requestList = [];
        $startTime = microtime(true);
        foreach ($this->models as $model) {
            $requestList[] = [
                'model' => $model,
                'start_time' => $startTime,
                'request' => $this->buildRequest($model, $caption, $title, $description, $clusterDescription, $clusters, $images, $textDensity),
            ];
        }
        // Execute all requests in parallel
        $poolResponses = Http::pool(function ($pool) use ($requestList) {
            foreach ($requestList as $item) {
                $model = $item['model'];
                $request = $item['request'];
                $pool->as($model)
                    ->withHeaders([
                        'Authorization' => "Bearer {$this->apiKey}",
                        'Content-Type' => 'application/json',
                        'HTTP-Referer' => $this->siteUrl,
                        'X-Title' => $this->siteName,
                    ])
                    ->withOptions(['verify' => false]) // Disable SSL verification for Laragon
                    ->timeout(120)
                    ->post($this->baseUrl, $request);
            }
        });

        // Map responses back with timing info
        $responses = [];
        foreach ($requestList as $item) {
            $model = $item['model'];
            $responses[$model] = [
                'start_time' => $item['start_time'],
                'response' => $poolResponses[$model],
            ];
        }

        // Process results
        $successful = [];
        $failed = [];

        foreach ($responses as $model => $data) {
            $response = $data['response'];
            $startTime = $data['start_time'];
            $duration = microtime(true) - $startTime;

            try {
                // Check if response is an exception (connection error, timeout, etc.)
                if ($response instanceof \Exception) {
                    throw $response;
                }

                if (!$response->successful()) {
                    throw new RuntimeException('API returned status ' . $response->status());
                }

                $data = $response->json();
                $content = $data['candidates'][0]['content']['parts'][0]['text']
                    ?? $data['choices'][0]['message']['content']
                    ?? null;

                if (!$content) {
                    throw new RuntimeException('No content in response');
                }

                // Parse JSON from response
                $parsed = $this->parsePromptJson($content);

                $successful[] = [
                    'model' => $model,
                    'status' => 'success',
                    'duration_ms' => round($duration * 1000),
                    'cost' => $this->estimateCost($model),
                    'cluster_id' => $parsed['cluster_id'] ?? null,
                    'cluster_name' => $parsed['cluster_name'] ?? 'Unknown',
                    'prompt' => $parsed['prompt'] ?? $content,
                    'selected_indices' => $parsed['selected_images'] ?? [],
                    'reasoning' => $parsed['reasoning'] ?? null,
                ];
            } catch (\Throwable $e) {
                $failed[] = [
                    'model' => $model,
                    'status' => 'failed',
                    'duration_ms' => round($duration * 1000),
                    'cost' => $this->estimateCost($model),
                    'error' => $e->getMessage(),
                    'status_code' => method_exists($response, 'status') ? $response->status() : null,
                ];

                Log::warning('PromptGeneratorService: Generation failed', [
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'successful' => $successful,
            'failed' => $failed,
            'total_cost' => array_sum(array_column($successful, 'cost')),
        ];
    }

    /**
     * Build cluster description for context.
     */
    private function buildClusterDescription(array $clusters, array $images): string
    {
        $lines = [];
        foreach ($clusters as $cluster) {
            $clusterInfo = [];
            $clusterInfo[] = "Cluster {$cluster['cluster_id']}: {$cluster['name']}";
            $clusterInfo[] = "  - Images: " . implode(', ', $cluster['image_indices'] ?? []);
            $clusterInfo[] = "  - Coherence: " . ($cluster['coherence_score'] ?? 'N/A') . "%";
            $clusterInfo[] = "  - Mood: " . ($cluster['mood'] ?? 'neutral');
            $clusterInfo[] = "  - Typography: " . ($cluster['typography_style'] ?? 'standard');
            $clusterInfo[] = "  - Layout: " . ($cluster['layout_pattern'] ?? 'standard');
            $clusterInfo[] = "  - Colors: " . implode(', ', $cluster['dominant_colors'] ?? ['N/A']);
            
            $lines[] = implode("\n", $clusterInfo);
        }

        return implode("\n\n", $lines);
    }

    /**
     * Build OpenRouter API request for a single model.
     */
    private function buildRequest(
        string $model,
        string $caption,
        ?string $title,
        ?string $description,
        string $clusterDescription,
        array $clusters,
        array $images,
        string $textDensity
    ): array {
        $prompt = $this->buildSystemPrompt($caption, $title, $description, $clusterDescription, $clusters, $images, $textDensity);

        return [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.7,
            'max_tokens' => 2500,
            'top_p' => 0.95,
        ];
    }

    /**
     * Build the system prompt for models using the Master Prompt Template.
     */
    private function buildSystemPrompt(
        string $caption,
        ?string $title,
        ?string $description,
        string $clusterDescription,
        array $clusters,
        array $images,
        string $textDensity
    ): string {
        $densityGuide = [
            'light' => 'Light: only the most important words (core event/product + CTA if present). Keep copy ultra-short.',
            'standard' => 'Standard: concise headline-style copy; include key nouns, dates, CTA. Avoid filler.',
            'heavy' => 'Heavy: keep most meaningful details but still concise; no filler or repetition.',
        ];

        $densityRule = $densityGuide[$textDensity] ?? $densityGuide['standard'];

        $lines = [];
        $lines[] = 'You are an expert AI prompt generator for poster creation. Your task is to produce a tailored, ready-to-use poster prompt for an image generation AI, adapting instructions based on the poster\'s complexity, type, and niche.';
        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $lines[] = '1. INPUTS';
        $lines[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $lines[] = '';
        $lines[] = 'BRAND DNA (from reference analysis):';
        $lines[] = $clusterDescription;
        $lines[] = '';
        $lines[] = 'POSTER CONTENT:';
        $lines[] = '- Caption: ' . $caption;
        if ($title) {
            $lines[] = '- Title: ' . $title;
        }
        if ($description) {
            $lines[] = '- Description: ' . $description;
        }
        $lines[] = '';
        $lines[] = 'TEXT DENSITY MODE: ' . strtoupper($textDensity) . ' — ' . $densityRule;
        $lines[] = '';
        $lines[] = 'AVAILABLE REFERENCE IMAGES:';
        foreach ($images as $image) {
            $index = $image['index'] ?? 'N/A';
            $cluster = $image['cluster_id'] ?? 'N/A';
            $quality = $image['quality'] ?? 'N/A';
            $complexity = $image['layout_complexity'] ?? 'N/A';
            $lines[] = "  - Image {$index}: Cluster {$cluster}, Quality: {$quality}, Layout: {$complexity}";
        }
        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $lines[] = '2. YOUR TASK';
        $lines[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $lines[] = '';
        $lines[] = 'Step 1: COMPLEXITY ANALYSIS';
        $lines[] = '  - Analyze the caption and determine layout complexity:';
        $lines[] = '    * SIMPLE: 1-2 text elements, single image, no overlays, grid-based layout';
        $lines[] = '    * COMPLEX: 3+ text elements, multiple images, shapes/masks, overlays, asymmetric layout';
        $lines[] = '';
        $lines[] = 'Step 2: SELECT BEST CLUSTER & IMAGES';
        $lines[] = '  - Choose the style cluster that best matches the caption mood/subject';
        $lines[] = '  - Select 1-3 reference images from ANY cluster that align with caption';
        $lines[] = '  - Assign each selected image a role:';
        $lines[] = '    * "style_anchor" = primary style reference (color, mood, overall vibe)';
        $lines[] = '    * "typography_reference" = text style, hierarchy, font treatment';
        $lines[] = '    * "composition_guide" = layout pattern, spacing, element arrangement';
        $lines[] = '';
        $lines[] = 'Step 3: DETECT POSTER NICHE';
        $lines[] = '  - Auto-detect from brand DNA and caption (tech, fashion, nonprofit, luxury, corporate, etc.)';
        $lines[] = '';
        $lines[] = 'Step 4: IDENTIFY LAYOUT TYPE';
        $lines[] = '  - Analyze the SELECTED images (1-3) to extract common layout patterns:';
        $lines[] = '    * hero (single dominant image with text overlay)';
        $lines[] = '    * split (image + text side-by-side or diagonal split)';
        $lines[] = '    * grid (structured blocks/sections)';
        $lines[] = '    * text-only (no images, pure typography with shapes/colors)';
        $lines[] = '    * custom (unique asymmetric or layered composition)';
        $lines[] = '';
        $lines[] = 'Step 5: GENERATE FINAL PROMPT';
        $lines[] = '  - Merge Brand DNA, Poster Content, and selected References';
        $lines[] = '  - If complexity = COMPLEX, include detailed LAYOUT CONSTRUCTION section';
        $lines[] = '  - If complexity = SIMPLE, keep instructions minimal and direct';
        $lines[] = '  - Final prompt must be ready-to-use for image generation AI';
        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $lines[] = '3. PROMPT STRUCTURE GUIDELINES';
        $lines[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $lines[] = '';
        $lines[] = 'For SIMPLE layouts:';
        $lines[] = '  - Start: "Match the exact visual style and branding of the provided reference images."';
        $lines[] = '  - Include: Poster content (headline/subtext/CTA), brand colors (hex codes), typography style';
        $lines[] = '  - Include: Text placement (centered, aligned), basic spacing';
        $lines[] = '  - Reference usage: List which reference provides style/typography/composition';
        $lines[] = '  - End: "Maintain strict brand consistency. High-resolution, flat poster output."';
        $lines[] = '';
        $lines[] = 'For COMPLEX layouts, ADD this section:';
        $lines[] = '  LAYOUT CONSTRUCTION:';
        $lines[] = '    - Layer order: [e.g., "background gradient → geometric shapes → masked product image → headline text → CTA button → accent elements"]';
        $lines[] = '    - Image masking/clipping: [e.g., "Product photo clipped inside circular shape, no overflow"]';
        $lines[] = '    - Text placement: [e.g., "Headline top-left aligned, subtext bottom-right, CTA centered"]';
        $lines[] = '    - Spacing & margins: [e.g., "40px margins all sides, 20px gap between elements"]';
        $lines[] = '    - Overlays/depth: [e.g., "Semi-transparent overlay on image, text shadow for depth"]';
        $lines[] = '';
        $lines[] = 'DO NOT instructions (always include):';
        $lines[] = '  - No off-brand colors or fonts';
        $lines[] = '  - No random overlapping or free-floating images';
        $lines[] = '  - No 3D effects, mockups, or shadows unless specified in brand DNA';
        $lines[] = '  - No extra text beyond caption content';
        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $lines[] = '4. OUTPUT FORMAT';
        $lines[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $lines[] = '';
        $lines[] = 'RESPOND ONLY WITH VALID JSON (no markdown, no code blocks):';
        $lines[] = '{';
        $lines[] = '  "cluster_id": <number>,';
        $lines[] = '  "cluster_name": "<string>",';
        $lines[] = '  "selected_images": [<array of 1-3 image indices>],';
        $lines[] = '  "reference_roles": {';
        $lines[] = '    "<image_index>": "style_anchor" | "typography_reference" | "composition_guide"';
        $lines[] = '  },';
        $lines[] = '  "complexity": "simple" | "complex",';
        $lines[] = '  "layout_type": "hero" | "split" | "grid" | "text-only" | "custom",';
        $lines[] = '  "layout_construction": {  // ONLY include if complexity = "complex"';
        $lines[] = '    "layer_order": "<describe layer stack>",';
        $lines[] = '    "masking_rules": "<image clipping/containment>",';
        $lines[] = '    "text_placement": "<alignment and hierarchy>",';
        $lines[] = '    "spacing": "<margins and gaps>",';
        $lines[] = '    "overlays": "<transparency/depth effects>"';
        $lines[] = '  },';
        $lines[] = '  "niche": "<auto-detected niche>",';
        $lines[] = '  "reasoning": "<why this cluster, images, layout, and complexity>",';
        $lines[] = '  "prompt": "<final ready-to-use prompt for image generation AI>"';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'CRITICAL: Obey TEXT DENSITY MODE (' . strtoupper($textDensity) . ') when extracting caption copy for the final prompt.';
        $lines[] = 'CRITICAL: Only include layout_construction object if complexity = "complex".';
        $lines[] = 'CRITICAL: Ensure reference_roles maps ALL selected_images to their roles.';

        return implode("\n", $lines);
    }

    /**
     * Parse JSON from model response.
     */
    private function parsePromptJson(string $response): array
    {
        // Try to extract JSON from response (models might add extra text)
        $jsonMatch = null;
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $jsonMatch = $matches[0];
        }

        if (!$jsonMatch) {
            return ['prompt' => $response];
        }

        $parsed = json_decode($jsonMatch, true);
        if (!$parsed) {
            return ['prompt' => $response];
        }

        return $parsed;
    }

    /**
     * Generate simple prompt with title/description extraction for Quick Generate.
     * Uses first successful model, extracts title/description, selects cluster, generates simple prompt.
     *
     * @param array $analysis Brand analysis with style_clusters and image_analysis
     * @param string $caption User-provided caption
     * @return array ['title' => string, 'description' => string, 'cluster_id' => int, 'selected_images' => array, 'prompt' => string]
     */
    public function generateSimplePrompt(array $analysis, string $caption): array
    {
        if (!$this->apiKey) {
            throw new RuntimeException('OpenRouter API key not configured');
        }

        $clusters = $analysis['style_clusters'] ?? [];
        $images = $analysis['image_analysis'] ?? [];

        if (empty($clusters)) {
            throw new RuntimeException('No style clusters available for prompt generation');
        }

        $clusterDescription = $this->buildClusterDescription($clusters, $images);
        
        // Build simplified prompt for title/description extraction + cluster matching
        $prompt = $this->buildSimpleSystemPrompt($caption, $clusterDescription, $images);

        // Try models in sequence until one succeeds
        foreach ($this->models as $model) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => $this->siteUrl,
                    'X-Title' => $this->siteName,
                ])
                ->withOptions(['verify' => false])
                ->timeout(120)
                ->post($this->baseUrl, [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 1000,
                    'top_p' => 0.95,
                ]);

                if (!$response->successful()) {
                    continue;
                }

                $data = $response->json();
                $content = $data['candidates'][0]['content']['parts'][0]['text']
                    ?? $data['choices'][0]['message']['content']
                    ?? null;

                if (!$content) {
                    continue;
                }

                $parsed = $this->parsePromptJson($content);
                
                // Validate required fields
                if (!isset($parsed['title']) || !isset($parsed['description']) || !isset($parsed['cluster_id'])) {
                    continue;
                }

                Log::info('PromptGeneratorService: Simple prompt generated', [
                    'model' => $model,
                    'title' => $parsed['title'],
                    'cluster_id' => $parsed['cluster_id'],
                ]);

                return [
                    'title' => $parsed['title'],
                    'description' => $parsed['description'],
                    'cluster_id' => $parsed['cluster_id'],
                    'selected_images' => $parsed['selected_images'] ?? [],
                    'simple_prompt' => $this->buildFinalSimplePrompt(
                        $caption,
                        $parsed['title'],
                        $parsed['description']
                    ),
                    'model_used' => $model,
                ];

            } catch (\Throwable $e) {
                Log::warning('PromptGeneratorService: Model failed', [
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        throw new RuntimeException('All models failed to generate prompt');
    }

    /**
     * Build simplified system prompt for Quick Generate.
     */
    private function buildSimpleSystemPrompt(string $caption, string $clusterDescription, array $images): string
    {
        $lines = [];
        $lines[] = 'You are an AI assistant for visual content generation. Analyze the caption and brand DNA to extract key information.';
        $lines[] = '';
        $lines[] = 'BRAND DNA:';
        $lines[] = $clusterDescription;
        $lines[] = '';
        $lines[] = 'AVAILABLE REFERENCE IMAGES:';
        foreach ($images as $image) {
            $index = $image['index'] ?? 'N/A';
            $cluster = $image['cluster_id'] ?? 'N/A';
            $lines[] = "  - Image {$index}: Cluster {$cluster}";
        }
        $lines[] = '';
        $lines[] = 'CAPTION:';
        $lines[] = $caption;
        $lines[] = '';
        $lines[] = 'YOUR TASK:';
        $lines[] = '1. Extract a concise title (main headline, 3-8 words)';
        $lines[] = '2. Extract supporting description/details (1 sentence)';
        $lines[] = '3. Select the best matching cluster ID based on caption mood/subject';
        $lines[] = '4. Select 1-3 reference image indices that best match the caption';
        $lines[] = '';
        $lines[] = 'RESPOND WITH VALID JSON ONLY:';
        $lines[] = '{';
        $lines[] = '  "title": "<extracted title>",';
        $lines[] = '  "description": "<extracted description>",';
        $lines[] = '  "cluster_id": <number>,';
        $lines[] = '  "selected_images": [<array of 1-3 image indices>]';
        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * Build final simple prompt for image generation.
     */
    private function buildFinalSimplePrompt(string $caption, string $title, string $description): string
    {
        return "Generate a visual for this caption: {$caption}, with this title: {$title} and this description: {$description}. Match the style and branding of the reference images.";
    }

    /**
     * Estimate cost for a model call.
     * Rough estimates based on token usage and model pricing.
     */
    private function estimateCost(string $model): float
    {
        // Average input tokens: ~300, output tokens: ~800
        $costs = [
            'google/gemini-3.1-pro-preview' => 0.075,
            'openai/gpt-5.2' => 0.08,
            'anthropic/claude-opus-4.5' => 0.15,
            'nvidia/nemotron-3-nano-30b-a3b:free' => 0.0,
        ];

        return $costs[$model] ?? 0.10;
    }
}
