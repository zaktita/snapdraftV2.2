<?php

namespace App\Services\Test;

use App\Services\AI\GeminiClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class MasterPromptLabService
{
    public const ASPECT_RATIOS = ['1:1', '4:5', '9:16', '16:9', '3:4', '2:3'];

    public function __construct(
        protected GeminiClient $client,
    ) {}

    /**
     * Step 1: Analyze 3 refs + caption → structured copy + master_prompt.
     *
     * @param  UploadedFile[]  $images
     * @return array{slots_detected: array, copy: array, visual_lock_summary: string, master_prompt: string, reference_paths: string[], reference_urls: string[], model_used: string}
     */
    public function buildMasterPrompt(array $images, string $caption, string $aspectRatio = '1:1'): array
    {
        if (count($images) !== 3) {
            throw new RuntimeException('Exactly 3 reference images are required.');
        }

        $stored = $this->storeReferences($images);
        $paths = array_column($stored, 'path');
        $built = $this->buildFromStoragePaths($paths, $caption, $aspectRatio);

        return [
            ...$built,
            'reference_paths' => $paths,
            'reference_urls' => array_column($stored, 'url'),
        ];
    }

    /**
     * Build master prompt from existing public-disk storage paths (e.g. cluster brand refs).
     *
     * @param  string[]  $storagePaths  Relative to storage/app/public
     * @return array{slots_detected: array, copy: array, visual_lock_summary: string, master_prompt: string, model_used: string}
     */
    public function buildFromStoragePaths(array $storagePaths, string $caption, string $aspectRatio = '1:1'): array
    {
        $paths = array_values(array_filter($storagePaths, fn ($p) => is_string($p) && $p !== ''));
        if (count($paths) < 3) {
            throw new RuntimeException('At least 3 reference image paths are required.');
        }
        $paths = array_slice($paths, 0, 3);

        $parts = array_map(
            fn (string $path) => $this->client->imageToInlinePart($path),
            $paths
        );

        $system = $this->loadSystemPrompt();
        $userText = $this->buildStep1UserMessage($caption, $aspectRatio);
        $contents = [[
            'parts' => [...$parts, ['text' => $system."\n\n".$userText]],
        ]];

        $schema = $this->loadSchema();
        [$result, $modelUsed] = $this->generateWithSchemaFallback($contents, $schema);

        return [
            'slots_detected' => $result['slots_detected'] ?? [],
            'copy' => $result['copy'] ?? [],
            'visual_lock_summary' => $result['visual_lock_summary'] ?? '',
            'master_prompt' => $result['master_prompt'] ?? '',
            'model_used' => $modelUsed,
        ];
    }

    /**
     * Step 2: Generate image from master_prompt + stored reference paths.
     *
     * @param  string[]  $referencePaths
     * @return array{url: string, path: string, model_used: string, generation_ms: int}
     */
    public function generateImage(array $referencePaths, string $masterPrompt, string $aspectRatio = '1:1'): array
    {
        $start = microtime(true);
        $binary = $this->generateBinary($referencePaths, $masterPrompt, $aspectRatio);
        $generationMs = (int) round((microtime(true) - $start) * 1000);
        $model = config('services.gemini.image_model', 'gemini-3.1-flash-image-preview');

        $outPath = 'master-prompt-lab/generated/'.uniqid('img_', true).'.png';
        Storage::disk('public')->put($outPath, $binary);

        return [
            'url' => Storage::url($outPath),
            'path' => $outPath,
            'model_used' => $model,
            'generation_ms' => $generationMs,
        ];
    }

    /**
     * Generate raw PNG bytes from master prompt + reference storage paths.
     *
     * @param  string[]  $referencePaths
     */
    public function generateBinary(
        array $referencePaths,
        string $masterPrompt,
        string $aspectRatio = '1:1',
        int $resolutionMultiplier = 1,
    ): string {
        $paths = array_values(array_filter($referencePaths, fn ($p) => is_string($p) && $p !== ''));
        if (count($paths) < 3) {
            throw new RuntimeException('At least 3 reference image paths are required.');
        }
        $paths = array_slice($paths, 0, 3);

        if (! preg_match('/^\d+:\d+$/', $aspectRatio)) {
            throw new RuntimeException('Invalid aspect ratio.');
        }

        $promptText = trim($masterPrompt);
        if ($promptText === '') {
            throw new RuntimeException('Master prompt is empty.');
        }

        if (! in_array($resolutionMultiplier, [1, 2, 4], true)) {
            $resolutionMultiplier = 1;
        }

        if ($resolutionMultiplier > 1) {
            $promptText .= "\n\nGenerate this image at {$resolutionMultiplier}x native resolution quality.";
        }

        $parts = [];
        foreach ($paths as $path) {
            $parts[] = $this->client->imageToInlinePart($path);
        }
        $parts[] = ['text' => $promptText];

        $model = config('services.gemini.image_model', 'gemini-3.1-flash-image-preview');
        $base64 = $this->client->generateImage($model, [['parts' => $parts]], $aspectRatio);
        $binary = base64_decode($base64, true);

        if ($binary === false) {
            throw new RuntimeException('Gemini returned invalid base64 image data.');
        }

        return $binary;
    }

    /**
     * @param  UploadedFile[]  $images
     * @return array<int, array{path: string, url: string}>
     */
    private function storeReferences(array $images): array
    {
        $stored = [];
        foreach ($images as $file) {
            $path = $file->store('master-prompt-lab/refs', 'public');
            $stored[] = [
                'path' => $path,
                'url' => Storage::url($path),
            ];
        }

        return $stored;
    }

    private function buildStep1UserMessage(string $caption, string $aspectRatio): string
    {
        return <<<TXT
## Inputs

**Aspect ratio:** {$aspectRatio}

**Caption (source of all on-image copy and subject):**
{$caption}

The three images attached above are the brand references (index 0, 1, 2).
Analyze them, fill the JSON schema, and assemble `master_prompt`.
TXT;
    }

    private function loadSystemPrompt(): string
    {
        $path = resource_path('prompts/master-prompt-lab-system.md');
        if (! is_readable($path)) {
            throw new RuntimeException('Master prompt lab system prompt file is missing.');
        }

        return file_get_contents($path) ?: '';
    }

    private function loadSchema(): array
    {
        $path = resource_path('prompts/master-prompt-lab-schema.json');
        if (! is_readable($path)) {
            throw new RuntimeException('Master prompt lab schema file is missing.');
        }

        $decoded = json_decode(file_get_contents($path) ?: '', true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Master prompt lab schema is invalid JSON.');
        }

        return $decoded;
    }

    /**
     * Prefer image model for Step 1; fall back to vision / prompt models if schema call fails.
     *
     * @return array{0: array, 1: string}
     */
    private function generateWithSchemaFallback(array $contents, array $schema): array
    {
        $candidates = array_values(array_unique(array_filter([
            config('services.gemini.image_model'),
            config('services.gemini.vision_model'),
            config('services.gemini.prompt_model'),
            config('services.gemini.model'),
        ])));

        $lastError = null;

        foreach ($candidates as $model) {
            try {
                $result = $this->client->generateContentWithSchema($model, $contents, $schema);

                if (empty($result['master_prompt'])) {
                    throw new RuntimeException("Model {$model} returned empty master_prompt.");
                }

                return [$result, $model];
            } catch (Throwable $e) {
                $lastError = $e;
                Log::warning('MasterPromptLabService: Step 1 model failed, trying next', [
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new RuntimeException(
            'Failed to build master prompt: '.($lastError?->getMessage() ?? 'unknown error')
        );
    }
}
