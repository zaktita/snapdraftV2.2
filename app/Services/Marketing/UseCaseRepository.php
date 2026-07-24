<?php

namespace App\Services\Marketing;

class UseCaseRepository
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
            'social-media-managers' => [
                'slug' => 'social-media-managers',
                'title' => 'SnapDraft for social media managers',
                'headline' => 'Ship the week’s posts without waiting on design',
                'description' => 'For in-house social media managers who plan in spreadsheets and need on-brand visuals on calendar speed, not ticket speed.',
                'audience' => 'In-house social media managers',
                'pain' => 'Your content calendar is ready. Design is not. Every delayed visual pushes publish dates, and every revision reopens the queue.',
                'outcome' => 'Upload brand references once, drop in the week’s spreadsheet, review a finished batch, and tweak outliers in Canvas before you schedule.',
                'image' => '/images/marketing/feature-batch.png',
                'image_alt' => 'SnapDraft batch generation from a content spreadsheet for social calendars',
                'workflow' => [
                    'Lock Brand DNA from past posts and campaign assets',
                    'Export your calendar rows (title, description, format)',
                    'Generate the batch and spot-check for on-brand fit',
                    'Fix last-mile feedback in Canvas, then download to schedule',
                ],
                'evidence' => [
                    'One Brand DNA profile drives every row. No re-briefing per post.',
                    'CSV rows map 1:1 to feed, story, or banner formats',
                    'Regenerate a single row without redoing the whole week',
                ],
                'faqs' => [
                    [
                        'q' => 'Can SnapDraft replace our designer entirely?',
                        'a' => 'It replaces the wait for routine calendar production. Designers still matter for big campaigns and net-new concepts. SnapDraft covers the weekly volume that usually clogs the queue.',
                    ],
                    [
                        'q' => 'What if a post needs a client-style tweak?',
                        'a' => 'Open it in Canvas. Swap copy, erase distractions, expand framing, or upscale. Then export without opening another ticket.',
                    ],
                    [
                        'q' => 'Does this work with how we already plan?',
                        'a' => 'Yes. If you already plan titles and captions in a spreadsheet, that sheet becomes the generation brief.',
                    ],
                ],
            ],
            'freelancers' => [
                'slug' => 'freelancers',
                'title' => 'SnapDraft for freelancers',
                'headline' => 'Deliver client batches the same day. Stop being the bottleneck.',
                'description' => 'For freelance content designers and creators who juggle multiple brands and cannot afford a week-long design loop per client.',
                'audience' => 'Freelance content designers and creators',
                'pain' => 'You are the production line. Every client revision eats the afternoon you needed for the next account.',
                'outcome' => 'Teach each client’s Brand DNA once, generate calendar batches from their sheets, and close feedback yourself in Canvas before delivery.',
                'image' => '/images/marketing/feature-canvas.png',
                'image_alt' => 'SnapDraft Canvas Editor for last-mile freelance client revisions',
                'workflow' => [
                    'Create a project per client with their reference set',
                    'Import their content calendar as CSV',
                    'Generate and review the batch against Brand DNA',
                    'Apply client notes in Canvas and deliver the export pack',
                ],
                'evidence' => [
                    'Separate Brand DNA per client keeps looks distinct',
                    'Canvas handles swap/erase/expand without another round-trip',
                    'Batch download keeps delivery organized for handoff',
                ],
                'faqs' => [
                    [
                        'q' => 'How do I keep Client A from looking like Client B?',
                        'a' => 'Each project has its own Brand DNA from that client’s references. Batches inherit that profile only.',
                    ],
                    [
                        'q' => 'What about last-minute caption changes?',
                        'a' => 'Update the row and regenerate, or edit text/objects directly in Canvas for one-off fixes.',
                    ],
                    [
                        'q' => 'Is this only for Instagram?',
                        'a' => 'No. Set square, portrait, or landscape per row so feed, stories, and banners are sized correctly on generation.',
                    ],
                ],
            ],
            'agencies' => [
                'slug' => 'agencies',
                'title' => 'SnapDraft for agencies',
                'headline' => 'Keep multi-brand calendars full without adding design headcount',
                'description' => 'For agencies and account teams producing weekly calendars across brands who need consistency and throughput without scaling the design bench linearly.',
                'audience' => 'Agency account leads and production teams',
                'pain' => 'Volume across brands outpaces design capacity. Consistency slips when every post is a fresh brief.',
                'outcome' => 'Standardize Brand DNA per account, batch from shared calendar sheets, and let account managers close light revisions in Canvas.',
                'image' => '/images/marketing/feature-brand-dna.png',
                'image_alt' => 'SnapDraft Brand DNA keeping agency client brands visually consistent',
                'workflow' => [
                    'Set Brand DNA per client account from approved references',
                    'Align content ops on a shared CSV schema',
                    'Generate weekly batches in parallel across brands',
                    'Route light revisions to Canvas; escalate only true creative work',
                ],
                'evidence' => [
                    'Brand DNA reduces off-brand drift across long calendars',
                    'Parallel row generation fits agency weekly cadences',
                    'Clear handoff: production in SnapDraft, craft elsewhere when needed',
                ],
                'faqs' => [
                    [
                        'q' => 'Will clients notice AI output?',
                        'a' => 'They notice consistency and speed. Brand DNA anchors palette and composition to their references; Canvas lets you polish before they see it.',
                    ],
                    [
                        'q' => 'How do we fit this into existing process?',
                        'a' => 'Keep strategy and copy in your sheet. SnapDraft owns visual production and last-mile edits. Not brand strategy.',
                    ],
                    [
                        'q' => 'Can multiple people work from the same brand profile?',
                        'a' => 'Yes. Once Brand DNA is set on a project, the team generates and edits against the same visual rules.',
                    ],
                ],
            ],
        ];
    }
}
