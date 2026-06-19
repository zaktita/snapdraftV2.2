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
use Illuminate\Support\Facades\Log;
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
        $systemPrompt = $this->promptBuilder->systemPrompt('generate_post');
        $instruction = $this->buildInstruction($project, $cluster, $caption, $aspectRatio, $images);

        $content = [
            ['type' => 'text', 'text' => $instruction],
        ];

        if ($this->shouldAttachClusterImages()) {
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
        }

        $config = $model->config_json ?? [];

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $content],
        ];

        $payload = [
            'model' => $model->openrouter_model_id,
            'messages' => $messages,
            'max_tokens' => $config['max_tokens'] ?? 8192,
            'temperature' => $config['temperature'] ?? 0.3,
        ];

        $response = $this->client->chat($payload);
        $rawText = (string) data_get($response, 'choices.0.message.content', '');
        $parsed = $this->responseParser->parse($rawText, 'generate_post');

        $tokensIn = (int) data_get($response, 'usage.prompt_tokens', 0);
        $tokensOut = (int) data_get($response, 'usage.completion_tokens', 0);

        if (! $parsed['json_valid'] || $parsed['prompt_json'] === null) {
            $repair = $this->attemptJsonRepair(
                $model->openrouter_model_id,
                $systemPrompt,
                $messages,
                $rawText,
                $parsed['json_validation_errors'],
                (array) ($model->config_json ?? []),
            );

            if ($repair !== null) {
                $rawText = $repair['raw_text'];
                $parsed = $repair['parsed'];
                $tokensIn += $repair['tokens_in'];
                $tokensOut += $repair['tokens_out'];
            }
        }

        $latencyMs = (int) round((microtime(true) - $started) * 1000);
        $usage = $response['usage'] ?? [];

        return new PostGenerationResult(
            rawText: $rawText,
            analysisProse: $parsed['analysis_prose'],
            promptJson: $parsed['prompt_json'],
            tweaks: $parsed['tweaks'],
            tokensIn: $tokensIn,
            tokensOut: $tokensOut,
            estimatedCostUsd: isset($usage['cost']) ? (float) $usage['cost'] : null,
            latencyMs: $latencyMs,
            jsonValid: $parsed['json_valid'],
            jsonValidationErrors: $parsed['json_validation_errors'],
            clusterKey: $cluster->key,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<string>  $validationErrors
     * @return array{raw_text: string, parsed: array<string, mixed>, tokens_in: int, tokens_out: int}|null
     */
    protected function attemptJsonRepair(
        string $modelId,
        string $systemPrompt,
        array $messages,
        string $assistantRawText,
        array $validationErrors,
        array $config = [],
    ): ?array {
        if ($validationErrors === []) {
            $validationErrors = ['JSON block missing or invalid'];
        }

        $repairMessages = array_merge($messages, [
            ['role' => 'assistant', 'content' => $assistantRawText],
            ['role' => 'user', 'content' => 'Your JSON Prompt failed validation: '
                .implode('; ', $validationErrors)
                .'. Return a corrected response with On-brand check, JSON Prompt, and Tweaks. Fix all validation errors. Keep on_image_text in the same language as the input caption.'],
        ]);

        try {
            $response = $this->client->chat([
                'model' => $modelId,
                'messages' => $repairMessages,
                'max_tokens' => $config['max_tokens'] ?? 8192,
                'temperature' => 0.2,
            ]);
        } catch (\Throwable $e) {
            Log::warning('OpenRouterCsvPostGenerator: JSON repair request failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $repairRaw = (string) data_get($response, 'choices.0.message.content', '');
        $parsed = $this->responseParser->parse($repairRaw, 'generate_post');

        if (! $parsed['json_valid'] || $parsed['prompt_json'] === null) {
            return null;
        }

        return [
            'raw_text' => $repairRaw,
            'parsed' => $parsed,
            'tokens_in' => (int) data_get($response, 'usage.prompt_tokens', 0),
            'tokens_out' => (int) data_get($response, 'usage.completion_tokens', 0),
        ];
    }

    protected function shouldAttachClusterImages(): bool
    {
        return (bool) config('ai.post_generation.attach_cluster_images', false);
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
        ];

        if ($this->shouldAttachClusterImages()) {
            $parts[] = 'Attached reference images: '.$imageCount.' (complete cluster set — use ALL attached images together)';
        } else {
            $parts[] = 'Reference images: not attached — use the cluster metadata below for layout, palette, typography, and text zones.';
        }

        $parts = array_merge($parts, [
            'Raw caption/topic (rewrite and shorten — keep all relevant facts):',
            $caption,
            'Caption task: Tighten the raw caption. Remove hashtags, URLs, filler, and repetition. Keep offer, dates, times, location, audience, names, numbers, and CTA. Do not drop important details.',
            'Language (mandatory): Write post.caption, every on_image_text zone, and any rendered text in post.concept in the SAME language as the raw caption above. Never translate — e.g. French caption → French copy only, even if references or DNA notes are in another language.',
            'On-image text: Match the reference layout zones. Shorten each zone vs the full caption but keep key facts (e.g. date, event name, offer). Do not paste the full caption on the visual.',
            'Aspect ratio: '.$aspectRatio,
        ]);

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
