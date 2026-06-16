<?php

namespace App\Services\AI\OpenRouter;

use App\Models\ProjectClusterImage;
use App\Services\AI\ModelRegistry;
use App\Services\Prompt\JsonPromptCompiler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OpenRouterCsvImageGenerator
{
    public function __construct(
        protected OpenRouterClient $client,
        protected ModelRegistry $modelRegistry,
        protected JsonPromptCompiler $promptCompiler,
    ) {}

    /**
     * @param  array<string, mixed>  $promptJson
     * @param  Collection<int, ProjectClusterImage>  $clusterImages
     */
    public function generateFromPromptJson(
        array $promptJson,
        Collection $clusterImages,
        string $aspectRatio,
        int $resolutionMultiplier = 1,
        ?string $caption = null,
    ): string {
        $caption = trim($caption ?? (string) data_get($promptJson, 'post.caption', ''));
        $promptText = $this->promptCompiler->buildImageRequestPrompt(
            $promptJson,
            $clusterImages->count(),
            $caption !== '' ? $caption : null,
        );

        return $this->generate($promptText, $clusterImages, $aspectRatio, $resolutionMultiplier);
    }

    /**
     * @param  Collection<int, ProjectClusterImage>  $clusterImages
     */
    public function generate(
        string $promptText,
        Collection $clusterImages,
        string $aspectRatio,
        int $resolutionMultiplier = 1,
    ): string {
        $model = $this->modelRegistry->findBySlug(
            config('ai.default_generator_slug', 'nano-banana-2'),
        );

        $config = $model->config_json ?? [];
        $imageSize = $this->resolveImageSize($resolutionMultiplier, $config['image_size'] ?? '2K');
        if ($resolutionMultiplier > 1) {
            $promptText .= "\n\nGenerate this image at {$resolutionMultiplier}x native resolution quality.";
        }

        $content = [
            ['type' => 'text', 'text' => $promptText],
        ];

        foreach ($clusterImages as $image) {
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

        $payload = [
            'model' => $model->openrouter_model_id,
            'messages' => [
                ['role' => 'user', 'content' => $content],
            ],
            'modalities' => ['image', 'text'],
            'image_config' => array_filter([
                'aspect_ratio' => $aspectRatio,
                'image_size' => $imageSize,
            ]),
        ];

        $response = $this->client->chat($payload);
        $binary = $this->extractImageData($response);

        if ($binary === null) {
            throw new RuntimeException('OpenRouter returned no image in the response.');
        }

        return $binary;
    }

    protected function resolveImageSize(int $resolutionMultiplier, string $default): string
    {
        return match ($resolutionMultiplier) {
            4 => '4K',
            2 => '2K',
            default => $default,
        };
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function extractImageData(array $response): ?string
    {
        $images = data_get($response, 'choices.0.message.images', []);

        if (is_array($images) && $images !== []) {
            $url = data_get($images, '0.image_url.url')
                ?? data_get($images, '0.url');

            if (is_string($url) && $url !== '') {
                return $this->decodeDataUrl($url);
            }
        }

        $content = data_get($response, 'choices.0.message.content');

        if (is_array($content)) {
            foreach ($content as $part) {
                if (($part['type'] ?? '') === 'image_url') {
                    $url = $part['image_url']['url'] ?? null;
                    if (is_string($url)) {
                        return $this->decodeDataUrl($url);
                    }
                }
            }
        }

        if (is_string($content) && str_starts_with($content, 'data:image')) {
            return $this->decodeDataUrl($content);
        }

        return null;
    }

    protected function decodeDataUrl(string $dataUrl): ?string
    {
        if (preg_match('/^data:image\/[\w+]+;base64,(.+)$/s', $dataUrl, $matches)) {
            $decoded = base64_decode($matches[1], true);

            return $decoded !== false ? $decoded : null;
        }

        return null;
    }
}
