<?php

namespace App\Services\Marketing;

/**
 * Public workflow template gallery powered by real product UI captures
 * (Tier 1-style evidence: product-generated screenshots, not scraped lists).
 */
class TemplateRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return array_values($this->items());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        return $this->items()[$slug] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function items(): array
    {
        return [
            'brand-dna-setup' => [
                'slug' => 'brand-dna-setup',
                'title' => 'Brand DNA setup',
                'excerpt' => 'Lock palette, composition, and typography from brand references before you generate.',
                'description' => 'A product workflow for teaching SnapDraft a client or brand look once, so every CSV row inherits the same visual rules.',
                'image' => '/images/marketing/feature-brand-dna.png',
                'image_alt' => 'SnapDraft Brand DNA analysis UI with brand reference extraction',
                'tags' => ['Brand DNA', 'Setup', 'Agencies'],
                'formats' => ['Any'],
                'steps' => [
                    'Collect 5-10 approved brand or client references',
                    'Create a project and run Brand DNA analysis',
                    'Review extracted palette and composition cues',
                    'Proceed to CSV batch generation with the profile locked',
                ],
                'related_use_cases' => [
                    ['href' => '/use-cases/agencies', 'label' => 'Agencies'],
                    ['href' => '/glossary/brand-dna', 'label' => 'Brand DNA glossary'],
                ],
            ],
            'csv-weekly-calendar' => [
                'slug' => 'csv-weekly-calendar',
                'title' => 'CSV weekly calendar batch',
                'excerpt' => 'Turn a week of spreadsheet rows into sized, on-brand visuals in one upload.',
                'description' => 'The core SnapDraft production pattern: title, description, and format columns become a finished batch ready for review.',
                'image' => '/images/marketing/feature-batch.png',
                'image_alt' => 'SnapDraft CSV batch generation progress for a weekly content calendar',
                'tags' => ['CSV', 'Batch', 'Social managers'],
                'formats' => ['Square', 'Portrait', 'Landscape'],
                'steps' => [
                    'Export the week’s calendar with title, description, format',
                    'Upload the CSV to a project with Brand DNA set',
                    'Watch per-row generation progress',
                    'Regenerate any weak rows, then move to Canvas or export',
                ],
                'related_use_cases' => [
                    ['href' => '/use-cases/social-media-managers', 'label' => 'Social media managers'],
                    ['href' => '/glossary/csv-batch-generation', 'label' => 'CSV batch glossary'],
                ],
            ],
            'canvas-last-mile' => [
                'slug' => 'canvas-last-mile',
                'title' => 'Canvas last-mile polish',
                'excerpt' => 'Close client feedback without reopening the design queue.',
                'description' => 'Use Canvas after generation to swap objects, fix headlines, erase, expand, and upscale before delivery or scheduling.',
                'image' => '/images/marketing/feature-canvas.png',
                'image_alt' => 'SnapDraft Canvas Editor applying last-mile edits to a generated visual',
                'tags' => ['Canvas', 'Revisions', 'Freelancers'],
                'formats' => ['Square', 'Portrait', 'Landscape'],
                'steps' => [
                    'Open a generated row in Canvas',
                    'Apply text, object, erase, or expand edits',
                    'Upscale or remove background if needed',
                    'Export the polished asset with the rest of the batch',
                ],
                'related_use_cases' => [
                    ['href' => '/use-cases/freelancers', 'label' => 'Freelancers'],
                    ['href' => '/glossary/canvas-editor', 'label' => 'Canvas glossary'],
                ],
            ],
            'multi-format-export' => [
                'slug' => 'multi-format-export',
                'title' => 'Multi-format export pack',
                'excerpt' => 'Generate feed, story, and banner sizes in one calendar sheet.',
                'description' => 'Set aspect ratio per row so the download pack is schedule-ready. No awkward crops after the fact.',
                'image' => '/images/marketing/feature-export.png',
                'image_alt' => 'SnapDraft export pack with social formats ready to download',
                'tags' => ['Export', 'Formats', 'Delivery'],
                'formats' => ['Square', 'Portrait', 'Landscape'],
                'steps' => [
                    'Add a format column to the content sheet',
                    'Generate the batch with Brand DNA applied',
                    'Spot-check framing per format',
                    'Batch download for scheduling or client handoff',
                ],
                'related_use_cases' => [
                    ['href' => '/features', 'label' => 'Features'],
                    ['href' => '/glossary/content-calendar-to-visuals', 'label' => 'Calendar → visuals'],
                ],
            ],
            'client-delivery-batch' => [
                'slug' => 'client-delivery-batch',
                'title' => 'Client delivery batch',
                'excerpt' => 'Freelance and agency pattern: generate, polish, deliver the same day.',
                'description' => 'A full loop from Brand DNA through CSV and Canvas into a client-ready download set.',
                'image' => '/images/marketing/img-publish.png',
                'image_alt' => 'SnapDraft workflow culminating in a publish-ready client batch',
                'tags' => ['Delivery', 'Agencies', 'Freelancers'],
                'formats' => ['Square', 'Portrait'],
                'steps' => [
                    'Set Brand DNA from the client’s approved references',
                    'Import their calendar CSV',
                    'Review the batch and polish flagged rows in Canvas',
                    'Deliver the export pack with consistent naming/sizes',
                ],
                'related_use_cases' => [
                    ['href' => '/use-cases/freelancers', 'label' => 'Freelancers'],
                    ['href' => '/use-cases/agencies', 'label' => 'Agencies'],
                ],
            ],
        ];
    }
}
