<?php

namespace App\Services\AI\OpenRouter;

use App\Models\Project;
use App\Models\ProjectCluster;
use App\Models\ProjectClusterImage;
use App\Services\AI\DTO\PostGenerationResult;
use App\Services\AI\ModelRegistry;
use App\Services\Brand\ProjectClusterSelector;
use App\Services\Prompt\AnalysisResponseParser;
use App\Services\Prompt\SkillPromptBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class OpenRouterCsvPostGenerator
{
    public function __construct(
        protected OpenRouterClient $client,
        protected SkillPromptBuilder $promptBuilder,
        protected AnalysisResponseParser $responseParser,
        protected ProjectClusterSelector $clusterSelector,
        protected ModelRegistry $modelRegistry,
    ) {}

    public function generateForRow(
        Project $project,
        string $caption,
        string $aspectRatio,
    ): PostGenerationResult {
        $project->loadMissing(['clusters.images.brandReference']);

        if (! is_array($project->dna_json) || $project->dna_json === []) {
            throw new \RuntimeException('Project has no brand DNA. Run extraction first.');
        }

        $selection = $this->clusterSelector->select($project, $caption);
        $cluster = $selection['cluster'];
        /** @var Collection<int, ProjectClusterImage> $images */
        $images = $selection['images'];

        return $this->generateForCluster($project, $caption, $aspectRatio, $cluster, $images);
    }

    /**
     * @param  Collection<int, ProjectClusterImage>  $images
     */
    public function generateForRowWithCluster(
        Project $project,
        string $caption,
        string $aspectRatio,
        ProjectCluster $cluster,
    ): PostGenerationResult {
        $project->loadMissing(['clusters.images.brandReference']);

        if (! is_array($project->dna_json) || $project->dna_json === []) {
            throw new \RuntimeException('Project has no brand DNA. Run extraction first.');
        }

        $images = $this->clusterSelector->imagesForCluster($cluster);

        return $this->generateForCluster($project, $caption, $aspectRatio, $cluster, $images);
    }

    /**
     * @param  Collection<int, ProjectClusterImage>  $images
     */
    protected function generateForCluster(
        Project $project,
        string $caption,
        string $aspectRatio,
        ProjectCluster $cluster,
        Collection $images,
    ): PostGenerationResult {
        $model = $this->modelRegistry->findBySlug(
            config('ai.default_post_model_slug', 'gpt-4o'),
        );

        $started = microtime(true);
        $instruction = $this->buildInstruction($project, $cluster, $caption, $aspectRatio, $images);

        $content = [
            ['type' => 'text', 'text' => $instruction],
        ];

        foreach ($images as $image) {
            $reference = $image->brandReference;
            if (! $reference || ! Storage::disk('public')->exists($reference->url)) {
                continue;
            }

            $mime = Storage::disk('public')->mimeType($reference->url) ?? 'image/jpeg';
            $base64 = base64_encode(Storage::disk('public')->get($reference->url));

            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => 'data:'.$mime.';base64,'.$base64],
            ];
        }

        $config = $model->config_json ?? [];

        $payload = [
            'model' => $model->openrouter_model_id,
            'messages' => [
                ['role' => 'system', 'content' => $this->promptBuilder->systemPrompt('generate_post')],
                ['role' => 'user', 'content' => $content],
            ],
            'max_tokens' => $config['max_tokens'] ?? 8192,
            'temperature' => $config['temperature'] ?? 0.3,
        ];

        $response = $this->client->chat($payload);
        $latencyMs = (int) round((microtime(true) - $started) * 1000);

        $rawText = (string) data_get($response, 'choices.0.message.content', '');
        $parsed = $this->responseParser->parse($rawText, 'generate_post');

        $usage = $response['usage'] ?? [];

        return new PostGenerationResult(
            rawText: $rawText,
            analysisProse: $parsed['analysis_prose'],
            promptJson: $parsed['prompt_json'],
            tweaks: $parsed['tweaks'],
            tokensIn: (int) ($usage['prompt_tokens'] ?? 0),
            tokensOut: (int) ($usage['completion_tokens'] ?? 0),
            estimatedCostUsd: isset($response['usage']['cost']) ? (float) $response['usage']['cost'] : null,
            latencyMs: $latencyMs,
            jsonValid: $parsed['json_valid'],
            jsonValidationErrors: $parsed['json_validation_errors'],
            clusterKey: $cluster->key,
        );
    }

    protected function buildInstruction(
        Project $project,
        ProjectCluster $cluster,
        string $caption,
        string $aspectRatio,
        Collection $clusterImages,
    ): string {
        $imageCount = $clusterImages->count();
        $clusterMeta = $this->clusterSelector->clusterMetadata($project, $cluster);

        $parts = [
            'Generate one on-brand post JSON prompt.',
            'Target generator: nano-banana',
            'Chosen cluster: '.$cluster->label.' ('.$cluster->key.')',
            'Cluster keywords: '.json_encode($cluster->keywords_json ?? []),
            'Attached reference images: '.$imageCount.' (complete cluster set — use ALL attached images together)',
            'Raw caption/topic (rewrite and shorten — keep all relevant facts):',
            $caption,
            'Caption task: Tighten the raw caption. Remove hashtags, URLs, filler, and repetition. Keep offer, dates, times, location, audience, names, numbers, and CTA. Do not drop important details.',
            'On-image text: Match the reference layout zones. Shorten each zone vs the full caption but keep key facts (e.g. date, event name, offer). Do not paste the full caption on the visual.',
            'Aspect ratio: '.$aspectRatio,
        ];

        if ($clusterMeta !== null) {
            $parts[] = 'Full cluster metadata (style anchor for this template family):'
                ."\n".json_encode($clusterMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $parts[] = 'Brand DNA JSON:';
        $parts[] = json_encode($project->dna_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $parts[] = 'Respond with: On-brand check, JSON Prompt, Tweaks.';

        return implode("\n\n", $parts);
    }
}
