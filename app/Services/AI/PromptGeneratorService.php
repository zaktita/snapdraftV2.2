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
        'google/gemini-3-flash-preview',
        'openai/gpt-5.2',
        'anthropic/claude-opus-4.5',
        'nvidia/nemotron-3-nano-30b-a3b:free',
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
            'max_tokens' => 1500,
            'top_p' => 0.95,
        ];
    }

    /**
     * Build the system prompt for models.
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
        $lines[] = 'You are a creative visual designer analyzing a caption to select the best brand references and generate a precise image prompt.';
        $lines[] = '';
        $lines[] = 'TASK:';
        $lines[] = '1) Analyze the caption requirements (mood, subject, colors, complexity)';
        $lines[] = '2) Choose the BEST style cluster that matches the caption';
        $lines[] = '3) Select 1-3 specific reference images (by index) that best match the caption from ANY cluster';
        $lines[] = '4) Write a concise generation prompt (2–4 sentences) incorporating the selected references';
        $lines[] = '';
        $lines[] = 'CAPTION (analyze and extract only essential copy): ' . $caption;
        $lines[] = 'TEXT DENSITY MODE: ' . strtoupper($textDensity) . ' — ' . $densityRule;

        if ($title) {
            $lines[] = 'TITLE: ' . $title;
        }
        if ($description) {
            $lines[] = 'DESCRIPTION: ' . $description;
        }

        $lines[] = '';
        $lines[] = 'STYLE CLUSTERS FROM BRAND REFERENCES:';
        $lines[] = $clusterDescription;
        $lines[] = '';
        $lines[] = 'AVAILABLE REFERENCE IMAGES:';
        foreach ($images as $image) {
            $index = $image['index'] ?? 'N/A';
            $cluster = $image['cluster_id'] ?? 'N/A';
            $quality = $image['quality'] ?? 'N/A';
            $complexity = $image['layout_complexity'] ?? 'N/A';
            $lines[] = "- Image {$index}: Cluster {$cluster}, Quality: {$quality}, Complexity: {$complexity}";
        }
        $lines[] = '';
        $lines[] = 'IMAGE SELECTION CRITERIA:';
        $lines[] = '- Choose images whose mood/subject/colors match the caption (not just the cluster)';
        $lines[] = '- First image = primary style anchor (highest caption match)';
        $lines[] = '- Additional images = supporting references (similar style but diverse composition)';
        $lines[] = '- Prefer high quality images over poor quality';
        $lines[] = '';
        $lines[] = 'PROMPT REQUIREMENTS:';
        $lines[] = '- Start with: "Match the exact visual style and branding of the provided reference images."';
        $lines[] = '- Distill the caption into key elements only (headline words, dates, CTA); omit filler.';
        $lines[] = '- Obey TEXT DENSITY MODE when deciding how much copy to keep.';
        $lines[] = '- Keep the prompt concise (2–4 sentences).';
        $lines[] = '- Reference the selected cluster\'s colors (use hex codes from cluster)';
        $lines[] = '- Match the cluster\'s typography and layout pattern';
        $lines[] = '- Keep same text density as references; do NOT add extra copy';
        $lines[] = '- End with: "Maintain strict brand consistency with the reference images."';
        $lines[] = '';
        $lines[] = 'RESPOND ONLY WITH VALID JSON (no markdown, no code blocks):';
        $lines[] = '{';
        $lines[] = '  "cluster_id": <number>,';
        $lines[] = '  "cluster_name": "<string>",';
        $lines[] = '  "selected_images": [<array of 1-3 image indices, e.g. [0, 2, 5]>],';
        $lines[] = '  "reasoning": "<why these images and cluster match the caption>",';
        $lines[] = '  "prompt": "<2–4 sentences following the requirements above>"';
        $lines[] = '}';

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
     * Estimate cost for a model call.
     * Rough estimates based on token usage and model pricing.
     */
    private function estimateCost(string $model): float
    {
        // Average input tokens: ~300, output tokens: ~800
        $costs = [
            'google/gemini-3-flash-preview' => 0.075,
            'openai/gpt-5.2' => 0.08,
            'anthropic/claude-opus-4.5' => 0.15,
            'nvidia/nemotron-3-nano-30b-a3b:free' => 0.0,
        ];

        return $costs[$model] ?? 0.10;
    }
}
