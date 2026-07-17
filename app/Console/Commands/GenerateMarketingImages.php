<?php

namespace App\Console\Commands;

use App\Services\AI\GeminiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateMarketingImages extends Command
{
    protected $signature = 'marketing:generate-images
                            {--force : Regenerate images even if they already exist}
                            {--only= : Comma-separated list of asset keys to generate}';

    protected $description = 'Generate marketing site imagery with the Gemini image model';

    private const STYLE = 'Minimal premium editorial aesthetic. Warm cream background (#faf7f2), '
        .'burnt-orange accent (#ff5806), soft neutral shadows, generous negative space, '
        .'high-end design-studio feel. Absolutely no text, letters, words, logos, or watermarks. '
        .'Clean geometry, subtle grain, soft diffused studio lighting.';

    private const UI_CARD_STYLE = 'Flat modern SaaS marketing graphic in the exact visual language of '
        .'fintech dashboard floaters: thick white outer rounded frame, solid flat color fill behind a '
        .'centered deep-black (#0a0a0a) rounded UI card, soft minimal depth, clean white sans-serif UI text, '
        .'generous padding, high contrast, no photorealism, no people, no photography, no watermarks. '
        .'Readable short UI labels only. Square 1:1 composition that works small as a floating hero tile.';

    public function handle(GeminiClient $client): int
    {
        $model = (string) config('services.gemini.image_model', 'gemini-3.1-flash-image-preview');
        $dir = public_path('images/marketing');
        File::ensureDirectoryExists($dir);

        $assets = $this->assets();

        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        if ($only !== []) {
            $assets = array_intersect_key($assets, array_flip($only));
        }

        $failures = 0;

        foreach ($assets as $key => $asset) {
            [$filename, $aspectRatio, $prompt, $style] = array_pad($asset, 4, self::STYLE);
            $path = $dir.DIRECTORY_SEPARATOR.$filename;

            if (! $this->option('force') && File::exists($path)) {
                $this->line("Skipping {$key} ({$filename} already exists).");

                continue;
            }

            $this->info("Generating {$key} ({$aspectRatio}) with {$model}...");

            try {
                $base64 = $client->generateImage(
                    $model,
                    [['parts' => [['text' => $prompt.' '.$style]]]],
                    $aspectRatio,
                );

                $binary = base64_decode($base64, true);

                if ($binary === false) {
                    throw new \RuntimeException('Invalid base64 image data returned.');
                }

                File::put($path, $binary);
                $this->info('  Saved '.str_replace(public_path(), 'public', $path).' ('.number_format(strlen($binary) / 1024).' KB)');
            } catch (\Throwable $e) {
                $failures++;
                $this->error("  Failed {$key}: ".$e->getMessage());
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3?: string}>
     */
    private function assets(): array
    {
        return [
            'hero' => [
                'hero.png',
                '16:9',
                'Overhead hero image for an AI brand-visual generation tool: a pristine cream designer desk '
                .'seen from above with a neat grid of nine small printed brand visuals arranged like a moodboard, '
                .'all sharing one cohesive burnt-orange and cream identity. Beside the grid, a single elegant '
                .'swatch card and a thin pencil. The grid conveys "many on-brand images generated in one batch".',
            ],
            'feature-brand-dna' => [
                'feature-brand-dna.png',
                '4:3',
                'Abstract visual of brand DNA extraction: a single elegant photograph on cream paper dissolving '
                .'into an orderly row of color swatches, a composition wireframe, and abstract typography blocks, '
                .'connected by fine hairline threads. Feels like a designer dissecting a brand into its parts.',
            ],
            'feature-batch' => [
                'feature-batch.png',
                '4:3',
                'Abstract visual of spreadsheet-to-image batch generation: a minimal paper spreadsheet with soft '
                .'rows on the left transforming into a fanned-out stack of finished branded image cards on the '
                .'right, each card a cohesive orange-and-cream composition. Conveys one row becoming one visual.',
            ],
            'feature-canvas' => [
                'feature-canvas.png',
                '4:3',
                'Abstract visual of a refined canvas editing workspace: one large branded image card on a cream '
                .'surface with delicate floating editing handles, a soft circular brush cursor, and a small '
                .'color-dot palette nearby. Feels precise, calm, and professional.',
            ],
            'feature-export' => [
                'feature-export.png',
                '4:3',
                'Abstract visual of multi-format export: the same branded visual repeated at three crisp sizes '
                .'- square, portrait, and wide - laid out neatly on cream paper like print proofs, with subtle '
                .'crop marks. Conveys one design adapted to every channel.',
            ],
            'blog-brand-consistency' => [
                'blog-brand-consistency.png',
                '16:9',
                'Editorial blog cover about brand consistency: a long row of identical small cream cards each '
                .'stamped with the same burnt-orange geometric mark, receding into soft focus, one card slightly '
                .'lifted. Calm, rhythmic, premium.',
            ],
            'blog-batch-workflow' => [
                'blog-batch-workflow.png',
                '16:9',
                'Editorial blog cover about batch creative workflows: an assembly-line arrangement of paper cards '
                .'moving left to right across a cream surface, evolving from blank to fully designed orange '
                .'compositions. Suggests momentum and effortless scale.',
            ],
            'blog-ai-design' => [
                'blog-ai-design.png',
                '16:9',
                'Editorial blog cover about AI and design collaboration: a human hand holding a pencil and an '
                .'abstract geometric orange form meeting over a shared sheet of cream paper, their strokes '
                .'blending into one elegant composition.',
            ],
            'og' => [
                'og.png',
                '16:9',
                'Social share image for a premium AI brand-visual tool: a centered, balanced still-life of a '
                .'small grid of cohesive orange-and-cream brand visuals on a cream backdrop with one accent '
                .'orange sphere resting beside them. Simple, iconic, uncluttered composition that reads well small.',
            ],

            // Hero floaters — dark UI cards on solid color fields (reference style)
            'hero-float-wait' => [
                'hero-float-wait.png',
                '1:1',
                'UI marketing tile: solid soft periwinkle-blue background (#8ea0ff) inside a thick white rounded '
                .'outer frame. Centered deep-black rounded card titled "Designer queue". Semi-circular segmented '
                .'progress gauge at 20% filled white / rest dark grey. Large white "5 days" in the arc. Subtitle '
                .'"avg wait time". Bottom legend: white dot "Brief sent" and grey dot "Still waiting". Matches '
                .'fintech gauge-card reference style.',
                self::UI_CARD_STYLE,
            ],
            'hero-float-brand' => [
                'hero-float-brand.png',
                '1:1',
                'UI marketing tile: solid soft butter-yellow background (#f3ecc0) inside a thick white rounded '
                .'outer frame. Centered deep-black rounded card titled "Brand DNA". Two small nested dark-grey '
                .'stat cards on top: "Palette" with five colored dots (cream, terracotta, charcoal, blush, sage) '
                .'and "Locked" with a check. Below, two list rows of brand tokens like "Tone · warm" and '
                .'"Layout · editorial". Clean flat SaaS card UI.',
                self::UI_CARD_STYLE,
            ],
            'hero-float-batch' => [
                'hero-float-batch.png',
                '1:1',
                'UI marketing tile: solid vivid burnt-orange background (#ff5806) inside a thick white rounded '
                .'outer frame. Centered deep-black rounded card with headline "Calendar → batch", label '
                .'"This week", large white "12 posts", and a simple 5-bar chart (Sun–Thu) where most bars are '
                .'medium grey and one peak bar is bright white. Flat modern dashboard graphic style.',
                self::UI_CARD_STYLE,
            ],
            'hero-float-shipped' => [
                'hero-float-shipped.png',
                '1:1',
                'UI marketing tile: solid soft pink background (#ffb3c6) inside a thick white rounded outer '
                .'frame. Centered deep-black rounded card titled "Week shipped". A 3x3 mini grid of tiny abstract '
                .'social-post thumbnails in cream and terracotta. Below: white pill "Ready to schedule" and a '
                .'small checkmark row "Mon–Sun done". Flat high-contrast SaaS UI card.',
                self::UI_CARD_STYLE,
            ],
        ];
    }
}
