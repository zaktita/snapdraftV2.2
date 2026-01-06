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
        'deepseek/deepseek-v3.2',
        'bytedance-seed/seed-1.6-flash',
        'openai/gpt-5.2',
        'openai/gpt-5.1',
        'openai/gpt-oss-120b',
        'anthropic/claude-opus-4.5',
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
        ?array $selectedReferenceIndices = null
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
                'request' => $this->buildRequest($model, $caption, $title, $description, $clusterDescription, $clusters, $selectedReferenceIndices),
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
                                'selected_indices' => $selectedReferenceIndices ?? [],
                    'raw_response' => $content,
                ];

                Log::info('PromptGeneratorService: Successful generation', [
                    'model' => $model,
                    'duration_ms' => round($duration * 1000),
                ]);
            } catch (\Exception $e) {
                $failed[] = [
                    'model' => $model,
                    'status' => 'failed',
                    'duration_ms' => round($duration * 1000),
                    'error' => $e->getMessage(),
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
        ?array $selectedReferenceIndices = null
    ): array {
        $prompt = $this->buildSystemPrompt($caption, $title, $description, $clusterDescription, $clusters, $selectedReferenceIndices);

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
        ?array $selectedReferenceIndices = null
    ): string {
        $lines = [];
        $lines[] = 'You are a creative visual designer generating one short, high-precision prompt for an image model.';
        $lines[] = '';
        $lines[] = 'TASK:';
        $lines[] = '1) Choose the single most relevant style cluster from the brand reference images';
        $lines[] = '2) Write a concise prompt (2–4 sentences) that directly tells the image model what to do';
        $lines[] = '';
        $lines[] = 'CAPTION (include verbatim inside the prompt): ' . $caption;

        if ($title) {
            $lines[] = 'TITLE: ' . $title;
        }
        if ($description) {
            $lines[] = 'DESCRIPTION: ' . $description;
        }

        $lines[] = '';
        $lines[] = 'AVAILABLE STYLE CLUSTERS (summarized from brand references):';
        $lines[] = $clusterDescription;
        $lines[] = '';
        $lines[] = 'IMPORTANT: The image generator WILL receive the reference images alongside your prompt. Your prompt must explicitly tell it to mirror their style.';
        if (!empty($selectedReferenceIndices)) {
            $main = $selectedReferenceIndices[0] ?? null;
            $supports = array_values(array_slice($selectedReferenceIndices, 1));
            $lines[] = 'USE THESE SELECTED REFERENCES:';
            if ($main !== null) {
                $lines[] = "- Main style anchor image: index {$main}";
            }
            if (!empty($supports)) {
                $lines[] = '- Supporting reference indices: ' . implode(', ', $supports);
            }
        }
        $lines[] = '';
        $lines[] = 'WRITE THE PROMPT WITH THESE RULES:';
        $lines[] = '- Start with: "Match the exact visual style and branding of the provided reference images."';
        $lines[] = '- Include the full caption verbatim (in quotes)';
        $lines[] = '- Keep the same amount of visible text as the references; do NOT add extra copy beyond the caption';
        $lines[] = '- Use 1–2 hex colors from the chosen cluster (keep palette fidelity)';
        $lines[] = '- Match the cluster\'s typography feel and layout pattern';
        $lines[] = '- End with: "Maintain strict brand consistency with the reference images."';
        $lines[] = '';
        $lines[] = 'RESPOND ONLY WITH VALID JSON (no markdown, no code blocks, no explanation):';
        $lines[] = '{';
        $lines[] = '  "cluster_id": <number>,';
        $lines[] = '  "cluster_name": "<string>",';
        $lines[] = '  "reasoning": "<why this cluster matches the caption>",';
        $lines[] = '  "prompt": "<2–4 short sentences following the rules above>"';
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
            'deepseek/deepseek-v3.2' => 0.14,
            'bytedance-seed/seed-1.6-flash' => 0.03,
            'openai/gpt-5.2' => 0.08,
            'openai/gpt-5.1' => 0.06,
            'openai/gpt-oss-120b' => 0.80,
            'anthropic/claude-opus-4.5' => 0.15,
        ];

        return $costs[$model] ?? 0.10;
    }
}
