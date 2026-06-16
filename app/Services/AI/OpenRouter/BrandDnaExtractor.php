<?php

namespace App\Services\AI\OpenRouter;

use App\Models\Project;
use App\Services\AI\DTO\AnalysisResult;
use App\Services\AI\ModelRegistry;
use App\Services\Prompt\AnalysisResponseParser;
use App\Services\Prompt\DnaJsonNormalizer;
use App\Services\Prompt\JsonSchemaValidator;
use App\Services\Prompt\SkillPromptBuilder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BrandDnaExtractor
{
    public function __construct(
        protected OpenRouterClient $client,
        protected SkillPromptBuilder $promptBuilder,
        protected AnalysisResponseParser $responseParser,
        protected DnaJsonNormalizer $normalizer,
        protected JsonSchemaValidator $validator,
        protected ModelRegistry $modelRegistry,
    ) {}

    /**
     * @param  list<string>  $storagePaths  Paths relative to the public disk
     */
    public function extract(Project $project, array $storagePaths): AnalysisResult
    {
        if ($storagePaths === []) {
            throw new RuntimeException('No reference images provided for DNA extraction.');
        }

        $model = $this->modelRegistry->findBySlug(
            config('ai.default_analyzer_slug', 'gpt-4o'),
        );

        $brandName = $project->name ?? $project->title ?? 'the brand';
        $imageCount = count($storagePaths);
        $started = microtime(true);

        $instruction = "Extract Brand DNA from these {$imageCount} reference post(s) for \"{$brandName}\". "
            .'Group images into clusters, output DNA JSON with clusters, and a Summary. '
            .'Respond with: Analysis, DNA JSON, Summary.';

        $content = [
            ['type' => 'text', 'text' => $instruction],
        ];

        foreach ($storagePaths as $path) {
            if (! Storage::disk('public')->exists($path)) {
                throw new RuntimeException("Reference image not found: {$path}");
            }

            $mime = Storage::disk('public')->mimeType($path) ?? 'image/jpeg';
            $base64 = base64_encode(Storage::disk('public')->get($path));

            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => 'data:'.$mime.';base64,'.$base64],
            ];
        }

        $config = $model->config_json ?? [];

        $payload = [
            'model' => $model->openrouter_model_id,
            'messages' => [
                ['role' => 'system', 'content' => $this->promptBuilder->systemPrompt('extract')],
                ['role' => 'user', 'content' => $content],
            ],
            'max_tokens' => $config['max_tokens'] ?? 8192,
            'temperature' => $config['temperature'] ?? 0.2,
        ];

        $response = $this->client->chat($payload);
        $latencyMs = (int) round((microtime(true) - $started) * 1000);

        $rawText = (string) data_get($response, 'choices.0.message.content', '');
        $parsed = $this->responseParser->parse($rawText, 'extract');

        $promptJson = $parsed['prompt_json'];
        $jsonValid = $parsed['json_valid'];
        $jsonErrors = $parsed['json_validation_errors'];

        if (is_array($promptJson)) {
            $promptJson = $this->normalizer->normalize($promptJson, $brandName, $imageCount);
            $validation = $this->validator->validate($promptJson, 'extract');
            $jsonValid = $validation['valid'];
            $jsonErrors = $validation['errors'];
        }

        $usage = $response['usage'] ?? [];

        return new AnalysisResult(
            rawText: $rawText,
            analysisProse: $parsed['analysis_prose'],
            promptJson: $promptJson,
            tweaks: $parsed['tweaks'],
            tokensIn: (int) ($usage['prompt_tokens'] ?? 0),
            tokensOut: (int) ($usage['completion_tokens'] ?? 0),
            estimatedCostUsd: isset($response['usage']['cost']) ? (float) $response['usage']['cost'] : null,
            latencyMs: $latencyMs,
            jsonValid: $jsonValid,
            jsonValidationErrors: $jsonErrors,
            summary: $parsed['summary'],
        );
    }
}
