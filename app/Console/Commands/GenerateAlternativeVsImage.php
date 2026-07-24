<?php

namespace App\Console\Commands;

use App\Services\AI\GeminiClient;
use App\Services\Marketing\AlternativeRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateAlternativeVsImage extends Command
{
    protected $signature = 'marketing:generate-alternative-vs
                            {slug=canva : Alternative slug (e.g. canva)}
                            {--force : Regenerate even if the output already exists}';

    protected $description = 'Generate a Canva-vs-SnapDraft style comparison image with Nano Banana using real logos as references';

    public function handle(GeminiClient $client, AlternativeRepository $alternatives): int
    {
        $slug = (string) $this->argument('slug');
        $alt = $alternatives->find($slug);

        if ($alt === null) {
            $this->error("Unknown alternative slug: {$slug}");

            return self::FAILURE;
        }

        $name = $alt['name'];
        $outDir = public_path('images/marketing/alternatives');
        File::ensureDirectoryExists($outDir);
        $outPath = $outDir.DIRECTORY_SEPARATOR."{$slug}-vs-snapdraft.png";

        if (! $this->option('force') && File::exists($outPath)) {
            $this->line("Skipping ({$slug}-vs-snapdraft.png already exists). Use --force to regenerate.");

            return self::SUCCESS;
        }

        $snapRef = 'marketing-refs/snapdraft-logo.png';
        $altRef = "marketing-refs/{$slug}-logo.png";

        foreach ([$snapRef, $altRef] as $ref) {
            if (! File::exists(storage_path('app/public/'.$ref))) {
                $this->error("Missing logo reference: storage/app/public/{$ref}");

                return self::FAILURE;
            }
        }

        $model = (string) config('services.gemini.image_model', 'gemini-3.1-flash-image-preview');
        $this->info("Generating {$name} vs SnapDraft with {$model}...");

        $prompt = implode(' ', [
            "Create a clean horizontal marketing comparison banner for a SaaS alternatives page.",
            "LEFT half: the {$name} logo exactly as shown in the first reference image, centered on a soft white card.",
            'RIGHT half: the SnapDraft logo exactly as shown in the second reference image, centered on a soft cream card.',
            'Between them, a small elegant "vs" badge in warm burnt-orange (#ff5806).',
            'Background: warm cream (#faf7f2), subtle soft shadow under both cards, generous padding, premium editorial feel.',
            'Preserve both logos accurately: colors, proportions, wordmarks. Do not invent, distort, or redraw the logos.',
            'No extra text besides the vs badge. No watermarks. No people. No UI screenshots. 16:9 composition.',
        ]);

        try {
            $parts = [
                ['text' => "Reference 1 is the official {$name} logo. Use it on the LEFT."],
                $client->imageToInlinePart($altRef),
                ['text' => 'Reference 2 is the official SnapDraft logo. Use it on the RIGHT.'],
                $client->imageToInlinePart($snapRef),
                ['text' => $prompt],
            ];

            $base64 = $client->generateImage($model, [['parts' => $parts]], '16:9');
            $binary = base64_decode($base64, true);

            if ($binary === false) {
                throw new \RuntimeException('Invalid base64 image data returned.');
            }

            File::put($outPath, $binary);
            $this->info('Saved public/images/marketing/alternatives/'.$slug.'-vs-snapdraft.png ('.number_format(strlen($binary) / 1024).' KB)');
        } catch (\Throwable $e) {
            $this->error('Failed: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
