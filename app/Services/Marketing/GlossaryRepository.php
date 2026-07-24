<?php

namespace App\Services\Marketing;

class GlossaryRepository
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
            'brand-dna' => [
                'slug' => 'brand-dna',
                'term' => 'Brand DNA',
                'title' => 'What is Brand DNA?',
                'description' => 'Brand DNA is SnapDraft\'s locked visual profile extracted from your references. It covers palette, composition, and typographic mood that drive every batch.',
                'definition' => 'Brand DNA is a persistent visual profile built from 5-10 brand or client references. SnapDraft extracts palette, composition, lighting, and typographic cues so every generated visual stays consistent without re-briefing the model on each post.',
                'how_snapdraft' => 'Upload references on a project. SnapDraft locks the profile and uses it for every CSV row and regeneration. Update references when the brand evolves.',
                'related' => [
                    ['href' => '/features', 'label' => 'Features'],
                    ['href' => '/use-cases/agencies', 'label' => 'For agencies'],
                    ['href' => '/blog/why-brand-consistency-beats-volume', 'label' => 'Consistency vs volume'],
                ],
            ],
            'csv-batch-generation' => [
                'slug' => 'csv-batch-generation',
                'term' => 'CSV batch generation',
                'title' => 'What is CSV batch generation?',
                'description' => 'CSV batch generation turns each spreadsheet row (title, description, format) into a finished on-brand visual in parallel.',
                'definition' => 'CSV batch generation is a production workflow where a content spreadsheet is the brief. Each row becomes one visual, generated in parallel, so a week of posts can be produced from a single upload.',
                'how_snapdraft' => 'Export your calendar with title, description, and format columns. SnapDraft generates each row against Brand DNA, shows progress per row, and lets you regenerate outliers without redoing the batch.',
                'related' => [
                    ['href' => '/features', 'label' => 'Features'],
                    ['href' => '/use-cases/social-media-managers', 'label' => 'For social managers'],
                    ['href' => '/blog/from-spreadsheet-to-campaign', 'label' => 'Spreadsheet to campaign'],
                ],
            ],
            'canvas-editor' => [
                'slug' => 'canvas-editor',
                'term' => 'Canvas Editor',
                'title' => 'What is the Canvas Editor?',
                'description' => 'SnapDraft’s Canvas Editor is the last-mile tool for swapping objects, fixing headlines, erasing, expanding, and upscaling before you publish.',
                'definition' => 'The Canvas Editor is an in-browser workspace for finishing generated visuals. It covers the edits that usually reopen a design ticket: text and object changes, erase, expand, background removal, and upscale.',
                'how_snapdraft' => 'After a batch, open any row in Canvas. Apply AI-assisted edits, keep version history on your changes, and export when it matches the brief.',
                'related' => [
                    ['href' => '/features', 'label' => 'Features'],
                    ['href' => '/use-cases/freelancers', 'label' => 'For freelancers'],
                    ['href' => '/faq', 'label' => 'FAQ'],
                ],
            ],
            'on-brand-consistency' => [
                'slug' => 'on-brand-consistency',
                'term' => 'On-brand consistency',
                'title' => 'What is on-brand consistency?',
                'description' => 'On-brand consistency means every post in a calendar looks like it came from the same studio. Same palette, composition habits, and typographic mood.',
                'definition' => 'On-brand consistency is visual coherence across a set of assets: colors, framing, lighting, and type feel aligned with the brand’s existing work. It is what makes a grid look intentional instead of randomly generated.',
                'how_snapdraft' => 'Consistency comes from Brand DNA, not from rewriting prompts. One profile drives the batch; Canvas fixes outliers without breaking the set.',
                'related' => [
                    ['href' => '/glossary/brand-dna', 'label' => 'Brand DNA'],
                    ['href' => '/blog/why-brand-consistency-beats-volume', 'label' => 'Why consistency beats volume'],
                    ['href' => '/use-cases/agencies', 'label' => 'Agency use case'],
                ],
            ],
            'content-calendar-to-visuals' => [
                'slug' => 'content-calendar-to-visuals',
                'term' => 'Content calendar to visuals',
                'title' => 'What does content calendar to visuals mean?',
                'description' => 'Content calendar to visuals is the workflow of turning planned spreadsheet rows into finished, sized social assets ready to schedule.',
                'definition' => 'Content calendar to visuals describes moving from a planned week (topics, captions, formats, dates) to finished creative without a separate design queue for every row.',
                'how_snapdraft' => 'Your sheet stays the source of truth. SnapDraft maps rows to visuals via Brand DNA and CSV batch generation, then Canvas handles last-mile feedback before export.',
                'related' => [
                    ['href' => '/glossary/csv-batch-generation', 'label' => 'CSV batch generation'],
                    ['href' => '/templates', 'label' => 'Workflow templates'],
                    ['href' => '/blog/from-spreadsheet-to-campaign', 'label' => 'From spreadsheet to campaign'],
                ],
            ],
        ];
    }
}
