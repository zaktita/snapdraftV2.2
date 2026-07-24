<?php

namespace App\Services\Marketing;

class AlternativeRepository
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
     * Feature matrix keys used across comparison pages.
     *
     * @return list<string>
     */
    public function matrixCriteria(): array
    {
        return [
            'Brand DNA / locked visual profile',
            'Spreadsheet → batch generation',
            'Row-level regenerate',
            'In-browser Canvas last-mile edits',
            'Native feed / story / banner formats',
            'Built for weekly publishing ops',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function items(): array
    {
        $criteria = $this->matrixCriteria();

        return [
            'canva' => [
                'slug' => 'canva',
                'name' => 'Canva',
                'image' => '/images/marketing/alternatives/canva.svg',
                'image_alt' => 'Canva comparison mark',
                'compare_image' => '/images/marketing/alternatives/canva-vs-snapdraft.png',
                'compare_image_alt' => 'Canva vs SnapDraft logos comparison',
                'color' => '#00C4CC',
                'title' => 'Canva alternatives for batch on-brand social visuals',
                'headline' => 'Need Canva-speed templates with calendar-scale batching?',
                'description' => 'Compare Canva and SnapDraft if you plan in spreadsheets and need on-brand social batches. Not one-off template edits.',
                'summary' => 'Canva excels at manual template design and team brand kits. SnapDraft is built for spreadsheet-driven batches anchored to Brand DNA, with Canvas for last-mile tweaks.',
                'best_for_competitor' => 'Designers and marketers building one-off creatives from templates and brand kits.',
                'best_for_snapdraft' => 'Social managers, freelancers, and agencies turning content calendars into on-brand batches.',
                'matrix' => [
                    $criteria[0] => ['competitor' => 'Brand kits / styles', 'snapdraft' => 'Brand DNA from references'],
                    $criteria[1] => ['competitor' => 'Bulk create (limited)', 'snapdraft' => 'CSV row → visual'],
                    $criteria[2] => ['competitor' => 'Per design', 'snapdraft' => 'Per spreadsheet row'],
                    $criteria[3] => ['competitor' => 'Full editor', 'snapdraft' => 'AI Canvas (swap, erase, expand, upscale)'],
                    $criteria[4] => ['competitor' => 'Yes', 'snapdraft' => 'Yes per generation'],
                    $criteria[5] => ['competitor' => 'General design', 'snapdraft' => 'Weekly calendar ops'],
                ],
            ],
            'adobe-express' => [
                'slug' => 'adobe-express',
                'name' => 'Adobe Express',
                'image' => '/images/marketing/alternatives/adobe-express.svg',
                'image_alt' => 'Adobe Express comparison mark',
                'color' => '#EB1000',
                'title' => 'Adobe Express alternatives for content calendar batches',
                'headline' => 'Like Express polish, but driven by your spreadsheet?',
                'description' => 'See how Adobe Express and SnapDraft differ when the job is weekly social production from a content calendar.',
                'summary' => 'Adobe Express is strong for quick branded graphics in the Adobe ecosystem. SnapDraft focuses on Brand DNA + CSV batch pipelines for people who already plan in sheets.',
                'best_for_competitor' => 'Teams already in Adobe who need fast single-asset creation.',
                'best_for_snapdraft' => 'Ops-minded publishers who want one upload → many on-brand posts.',
                'matrix' => [
                    $criteria[0] => ['competitor' => 'Brand kits', 'snapdraft' => 'Brand DNA from references'],
                    $criteria[1] => ['competitor' => 'Limited bulk', 'snapdraft' => 'CSV batch core workflow'],
                    $criteria[2] => ['competitor' => 'Per asset', 'snapdraft' => 'Per row'],
                    $criteria[3] => ['competitor' => 'Express editor', 'snapdraft' => 'AI Canvas last-mile'],
                    $criteria[4] => ['competitor' => 'Yes', 'snapdraft' => 'Yes per row'],
                    $criteria[5] => ['competitor' => 'General creative', 'snapdraft' => 'Calendar production'],
                ],
            ],
            'midjourney' => [
                'slug' => 'midjourney',
                'name' => 'Midjourney',
                'image' => '/images/marketing/alternatives/midjourney.svg',
                'image_alt' => 'Midjourney comparison mark',
                'color' => '#111111',
                'title' => 'Midjourney alternatives for on-brand social batches',
                'headline' => 'Great for exploration. Less ideal for locked brand calendars',
                'description' => 'Compare Midjourney and SnapDraft when you need consistent brand visuals from a content spreadsheet. Not prompt-by-prompt exploration.',
                'summary' => 'Midjourney is excellent for creative exploration. SnapDraft is for production: lock Brand DNA, generate from CSV rows, and polish in Canvas.',
                'best_for_competitor' => 'Art direction, moodboards, and exploratory concepts.',
                'best_for_snapdraft' => 'Repeatable weekly posts that must match a brand.',
                'matrix' => [
                    $criteria[0] => ['competitor' => 'Prompt / style refs', 'snapdraft' => 'Persistent Brand DNA'],
                    $criteria[1] => ['competitor' => 'No CSV pipeline', 'snapdraft' => 'Native CSV batches'],
                    $criteria[2] => ['competitor' => 'Re-prompt', 'snapdraft' => 'Row regenerate'],
                    $criteria[3] => ['competitor' => 'External editors', 'snapdraft' => 'Built-in Canvas'],
                    $criteria[4] => ['competitor' => 'Manual framing', 'snapdraft' => 'Format per row'],
                    $criteria[5] => ['competitor' => 'Exploration', 'snapdraft' => 'Publishing ops'],
                ],
            ],
            'chatgpt' => [
                'slug' => 'chatgpt',
                'name' => 'ChatGPT image tools',
                'image' => '/images/marketing/alternatives/chatgpt.svg',
                'image_alt' => 'ChatGPT comparison mark',
                'color' => '#10A37F',
                'title' => 'ChatGPT image alternatives for brand-consistent batches',
                'headline' => 'Chat is flexible. Calendars need a production pipeline.',
                'description' => 'Compare ChatGPT image workflows and SnapDraft for teams that need spreadsheet-scale, on-brand social visuals.',
                'summary' => 'ChatGPT is versatile for ad-hoc images and copy. SnapDraft is a dedicated Brand DNA → CSV → Canvas pipeline for weekly publishing.',
                'best_for_competitor' => 'Ad-hoc ideas, copy, and one-off image asks.',
                'best_for_snapdraft' => 'Structured calendars with consistent brand output.',
                'matrix' => [
                    $criteria[0] => ['competitor' => 'Prompt memory / custom GPT', 'snapdraft' => 'Brand DNA profile'],
                    $criteria[1] => ['competitor' => 'Manual prompting', 'snapdraft' => 'CSV upload'],
                    $criteria[2] => ['competitor' => 'Re-ask', 'snapdraft' => 'Per-row regenerate'],
                    $criteria[3] => ['competitor' => 'External', 'snapdraft' => 'Canvas in product'],
                    $criteria[4] => ['competitor' => 'Prompted', 'snapdraft' => 'Column-driven'],
                    $criteria[5] => ['competitor' => 'General AI', 'snapdraft' => 'Social production'],
                ],
            ],
            'predis' => [
                'slug' => 'predis',
                'name' => 'Predis.ai',
                'image' => '/images/marketing/alternatives/predis.svg',
                'image_alt' => 'Predis.ai comparison mark',
                'color' => '#6C5CE7',
                'title' => 'Predis.ai alternatives for Brand DNA batch workflows',
                'headline' => 'Social AI suites vs a spreadsheet-native batch pipeline',
                'description' => 'Compare Predis.ai and SnapDraft if your priority is Brand DNA consistency and CSV-driven visual batches.',
                'summary' => 'Predis focuses on social content suites (ideas, captions, creatives). SnapDraft specializes in brand-locked visual batches from your existing calendar sheet.',
                'best_for_competitor' => 'Teams wanting ideation + posting assistance in one social suite.',
                'best_for_snapdraft' => 'Teams that already plan copy and need reliable visual production.',
                'matrix' => [
                    $criteria[0] => ['competitor' => 'Brand kit features', 'snapdraft' => 'Reference-based Brand DNA'],
                    $criteria[1] => ['competitor' => 'Campaign generators', 'snapdraft' => 'CSV as source of truth'],
                    $criteria[2] => ['competitor' => 'Per creative', 'snapdraft' => 'Per row'],
                    $criteria[3] => ['competitor' => 'Varies', 'snapdraft' => 'AI Canvas edits'],
                    $criteria[4] => ['competitor' => 'Social formats', 'snapdraft' => 'Format column'],
                    $criteria[5] => ['competitor' => 'Social suite', 'snapdraft' => 'Visual production pipeline'],
                ],
            ],
            'napkin' => [
                'slug' => 'napkin',
                'name' => 'Napkin AI',
                'image' => '/images/marketing/alternatives/napkin.svg',
                'image_alt' => 'Napkin AI comparison mark',
                'color' => '#1F2937',
                'title' => 'Napkin AI alternatives for social visual batches',
                'headline' => 'Diagrams and explainers vs on-brand social posts',
                'description' => 'Napkin AI and SnapDraft solve different jobs. Use this page if you need social calendar visuals, not diagram generation.',
                'summary' => 'Napkin shines at turning text into diagrams. SnapDraft turns brand references + spreadsheet rows into on-brand social visuals.',
                'best_for_competitor' => 'Explainers, decks, and visual diagrams from text.',
                'best_for_snapdraft' => 'Feed/story/banner batches for brands and clients.',
                'matrix' => [
                    $criteria[0] => ['competitor' => 'N/A (diagram style)', 'snapdraft' => 'Brand DNA'],
                    $criteria[1] => ['competitor' => 'Text → diagram', 'snapdraft' => 'CSV → social visual'],
                    $criteria[2] => ['competitor' => 'Re-generate diagram', 'snapdraft' => 'Row regenerate'],
                    $criteria[3] => ['competitor' => 'Diagram editor', 'snapdraft' => 'Social Canvas'],
                    $criteria[4] => ['competitor' => 'Diagram layouts', 'snapdraft' => 'Social aspect ratios'],
                    $criteria[5] => ['competitor' => 'Knowledge visuals', 'snapdraft' => 'Publishing calendars'],
                ],
            ],
            'figma' => [
                'slug' => 'figma',
                'name' => 'Figma',
                'image' => '/images/marketing/alternatives/figma.svg',
                'image_alt' => 'Figma comparison mark',
                'color' => '#F24E1E',
                'title' => 'Figma alternatives for weekly social production',
                'headline' => 'Design systems vs calendar batch production',
                'description' => 'Compare Figma and SnapDraft when the bottleneck is weekly social volume, not component design systems.',
                'summary' => 'Figma is the standard for collaborative design systems. SnapDraft is for turning approved brand look + CSV calendars into finished post batches.',
                'best_for_competitor' => 'Product design, design systems, and high-craft campaigns.',
                'best_for_snapdraft' => 'Operational social output after the system is defined.',
                'matrix' => [
                    $criteria[0] => ['competitor' => 'Libraries / styles', 'snapdraft' => 'Brand DNA from refs'],
                    $criteria[1] => ['competitor' => 'Manual / plugins', 'snapdraft' => 'Native CSV batches'],
                    $criteria[2] => ['competitor' => 'Per frame', 'snapdraft' => 'Per row'],
                    $criteria[3] => ['competitor' => 'Full design tool', 'snapdraft' => 'AI last-mile Canvas'],
                    $criteria[4] => ['competitor' => 'Any', 'snapdraft' => 'Social formats first'],
                    $criteria[5] => ['competitor' => 'Design craft', 'snapdraft' => 'Publish cadence'],
                ],
            ],
            'later' => [
                'slug' => 'later',
                'name' => 'Later',
                'image' => '/images/marketing/alternatives/later.svg',
                'image_alt' => 'Later comparison mark',
                'color' => '#7C3AED',
                'title' => 'Later alternatives for creating the visuals you schedule',
                'headline' => 'Scheduling is solved. Visual production still is not.',
                'description' => 'Later helps you schedule. SnapDraft helps you produce the on-brand visuals that fill the calendar.',
                'summary' => 'Later is a scheduling and social management platform. SnapDraft sits upstream: Brand DNA + CSV batches + Canvas, then you export into your scheduler.',
                'best_for_competitor' => 'Planning, scheduling, and publishing to social networks.',
                'best_for_snapdraft' => 'Creating the asset batch before it hits the scheduler.',
                'matrix' => [
                    $criteria[0] => ['competitor' => 'Limited', 'snapdraft' => 'Core Brand DNA'],
                    $criteria[1] => ['competitor' => 'Not a generator', 'snapdraft' => 'CSV generation'],
                    $criteria[2] => ['competitor' => 'N/A', 'snapdraft' => 'Row regenerate'],
                    $criteria[3] => ['competitor' => 'N/A', 'snapdraft' => 'Canvas edits'],
                    $criteria[4] => ['competitor' => 'Scheduling sizes', 'snapdraft' => 'Generation sizes'],
                    $criteria[5] => ['competitor' => 'Publish & schedule', 'snapdraft' => 'Produce visuals'],
                ],
            ],
        ];
    }
}
