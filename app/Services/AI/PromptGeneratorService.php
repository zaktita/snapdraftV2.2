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
     * Generate prompt for a single CSV row using the Gemini direct API.
     *
     * Pipeline step 2: given the brand analysis (style_clusters) and the row caption,
     * ask Gemini to (a) select the best-matching cluster + reference image indices and
     * (b) write the text content (headline / subtext / CTA) that should appear on the image.
     * Styling, colour, and layout are handled by the reference images in step 3.
     *
     * @param array  $analysis Brand analysis produced by BrandReferenceAnalyzer::analyze()
     * @param string $caption  Raw caption/description from the CSV row
     * @return array [
     *     'cluster_id'     => int|null,
     *     'selected_images' => int[],
     *     'simple_prompt'  => string,   // text content for image generation
     *     'model_used'     => string,
     * ]
     */
    public function generateSimplePrompt(array $analysis, string $caption): array
    {
        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
            throw new RuntimeException('Gemini API key not configured');
        }

        $model   = config('services.gemini.prompt_model', config('services.gemini.model', 'gemini-3.1-pro-preview'));
        $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

        $clusters = $analysis['style_clusters'] ?? [];
        $images   = $analysis['image_analysis'] ?? [];

        // If no clusters were produced by the brand analyzer, return the raw caption
        // so the job can still proceed using all reference images.
        if (empty($clusters)) {
            Log::warning('PromptGeneratorService: no style_clusters in brand analysis — using raw caption');
            return [
                'cluster_id'      => null,
                'selected_images' => [],
                'simple_prompt'   => $caption,
                'model_used'      => 'none',
            ];
        }

        $clusterDescription = $this->buildClusterDescription($clusters, $images);
        $prompt = $this->buildContentPrompt($caption, $clusterDescription, $clusters);

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->withOptions(['verify' => false])
            ->timeout(60)
            ->post("{$baseUrl}/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'responseModalities' => ['TEXT'],
                    'temperature'        => 0.7,
                    'maxOutputTokens'    => 500,
                ],
            ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                'Gemini prompt generation failed (HTTP ' . $response->status() . '): ' . $response->body()
            );
        }

        $body = $response->json();
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$text) {
            throw new RuntimeException('Gemini returned no content for prompt generation');
        }

        $parsed = $this->parsePromptJson($text);

        $clusterId      = isset($parsed['cluster_id']) ? (int) $parsed['cluster_id'] : null;
        $selectedImages = array_map('intval', $parsed['selected_images'] ?? []);
        $contentPrompt  = trim((string) ($parsed['prompt'] ?? $caption));

        Log::info('PromptGeneratorService: prompt generated via Gemini', [
            'model'           => $model,
            'cluster_id'      => $clusterId,
            'selected_images' => $selectedImages,
        ]);

        return [
            'cluster_id'      => $clusterId,
            'selected_images' => $selectedImages,
            'simple_prompt'   => $contentPrompt,
            'model_used'      => $model,
        ];
    }

    /**
     * Build the prompt sent to Gemini for cluster selection + image text extraction.
     * The model must return JSON with cluster_id, selected_images, and prompt.
     */
    private function buildContentPrompt(string $caption, string $clusterDescription, array $clusters): string
    {
        $clusterIds = implode(', ', array_column($clusters, 'cluster_id'));

        $lines   = [];
        $lines[] = 'You are a visual content assistant. Given the caption and the available brand style clusters, do two things:';
        $lines[] = '';
        $lines[] = '1. SELECT the style cluster and 1-3 reference image indices that best match the caption mood and subject.';
        $lines[] = '2. WRITE the text content that will appear on the image (headline, supporting text, CTA if applicable).';
        $lines[] = '';
        $lines[] = 'AVAILABLE STYLE CLUSTERS:';
        $lines[] = $clusterDescription;
        $lines[] = '';
        $lines[] = 'CAPTION: ' . $caption;
        $lines[] = '';
        $lines[] = 'RULES:';
        $lines[] = '- The "prompt" field must contain ONLY the text that will appear on the image.';
        $lines[] = '- Do NOT include styling, colours, layout, or brand DNA — those come from the reference images.';
        $lines[] = '- Keep the copy concise and match the tone from the cluster description.';
        $lines[] = '';
        $lines[] = 'RESPOND WITH VALID JSON ONLY (no markdown, no code blocks):';
        $lines[] = '{';
        $lines[] = '  "cluster_id": <number — must be one of: ' . $clusterIds . '>,';
        $lines[] = '  "selected_images": [<1-3 integer image indices from that cluster>],';
        $lines[] = '  "prompt": "<text content for the image — headline, subtext, CTA if any>"';
        $lines[] = '}';

        return implode("\n", $lines);
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
