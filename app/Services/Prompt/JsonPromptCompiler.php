<?php

namespace App\Services\Prompt;

class JsonPromptCompiler
{
    /**
     * Build the full text payload for image generation: style from references,
     * exact on-image copy from JSON/caption, then the post prompt JSON block.
     *
     * @param  array<string, mixed>  $promptJson
     */
    public function buildImageRequestPrompt(
        array $promptJson,
        int $referenceImageCount = 0,
        ?string $caption = null,
    ): string {
        $parts = [];

        if ($referenceImageCount > 0) {
            $label = $referenceImageCount === 1 ? 'image' : 'images';
            $parts[] = sprintf(
                'The %d attached reference %s define the visual template AND text zone placement. Replicate their layout, graphic zones, typography style, color-block structure, photo masks, pill/badge shapes, spacing, and bottom logo bar.',
                $referenceImageCount,
                $label,
            );
            $parts[] = 'Use references for WHERE each text type goes (headline area, subhead, pills, CTAs) and how long each zone typically is. Do NOT copy any words from the references. Match zone count; shorten copy vs the full caption but keep relevant facts in each zone. Do not invent a new grid, collage, speech bubble, or modular layout.';
            $parts[] = 'ON-IMAGE COPY: Render only the ON-IMAGE TEXT strings below — condensed per zone, with key facts preserved. Do not paste the full social caption, paragraphs, or bullet lists on the image.';
        }

        $post = $promptJson['post'] ?? [];
        $onImageTextBlock = $this->formatOnImageTextBlock(
            $post['on_image_text'] ?? null,
            $referenceImageCount > 0,
        );
        if ($onImageTextBlock !== '') {
            $parts[] = $onImageTextBlock;
        }

        $caption = trim($caption ?? (string) ($post['caption'] ?? ''));
        if ($caption !== '') {
            $parts[] = 'Rewritten caption (context for facts — do not render in full on the image; use the ON-IMAGE TEXT zones above, shortened but complete for each zone):'
                ."\n\"{$caption}\"";
        }

        $referenceUsage = $this->extractReferenceUsage($promptJson['reference_usage'] ?? null);
        if ($referenceUsage !== '') {
            $parts[] = $referenceUsage;
        }

        $clusterContext = $promptJson['cluster_context'] ?? null;
        if (is_array($clusterContext) && $clusterContext !== []) {
            $encoded = json_encode($clusterContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (is_string($encoded)) {
                $parts[] = "Cluster style anchor (match this template family using all {$referenceImageCount} attached references):\n{$encoded}";
            }
        }

        $constraintsJson = $this->buildImageConstraintsJson($promptJson);
        if ($constraintsJson !== []) {
            $json = json_encode($constraintsJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json !== false) {
                $parts[] = "Visual constraints (subject, palette, quality — on-image copy is above, not in this block):\n\n```json\n{$json}\n```";
            }
        }

        return trim(implode("\n\n", $parts));
    }

    /**
     * JSON for the image model excluding fields already expressed in natural-language blocks above.
     *
     * @param  array<string, mixed>  $promptJson
     * @return array<string, mixed>
     */
    protected function buildImageConstraintsJson(array $promptJson): array
    {
        $constraints = $promptJson;
        unset($constraints['cluster_context'], $constraints['reference_usage']);

        if (isset($constraints['post']) && is_array($constraints['post'])) {
            $post = $constraints['post'];
            unset($post['caption'], $post['on_image_text']);
            $constraints['post'] = $post;
        }

        return array_filter($constraints, fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $promptJson
     */
    public function compile(array $promptJson): string
    {
        $parts = [];

        $post = $promptJson['post'] ?? [];
        $locked = $promptJson['brand_locked'] ?? [];
        $meta = $promptJson['meta'] ?? [];
        $quality = $promptJson['quality'] ?? [];

        if (! empty($post['concept'])) {
            $parts[] = (string) $post['concept'];
        }

        foreach ([
            'visual_style' => 'Visual style',
            'layout_system' => 'Layout',
            'layout' => 'Layout',
            'typography' => 'Typography',
            'typography_hierarchy' => 'Typography',
            'lighting' => 'Lighting',
            'post_processing' => 'Post-processing',
        ] as $key => $label) {
            if (! empty($locked[$key]) && is_string($locked[$key])) {
                $parts[] = "{$label}: {$locked[$key]}";
            }
        }

        if (! empty($locked['palette']) && is_array($locked['palette'])) {
            $palette = collect($locked['palette'])
                ->map(fn ($c) => is_array($c)
                    ? (($c['hex'] ?? '').' ('.($c['role'] ?? 'color').')')
                    : (string) $c)
                ->filter()
                ->implode(', ');
            if ($palette !== '') {
                $parts[] = 'Color palette (use these exact hex codes): '.$palette;
            }
        }

        if (! empty($locked['motifs']) && is_array($locked['motifs'])) {
            $parts[] = 'Required motifs: '.implode('; ', $locked['motifs']);
        }

        if (! empty($post['composition'])) {
            $parts[] = 'Composition: '.$post['composition'];
        }

        if (! empty($post['dominant_colors']) && is_array($post['dominant_colors'])) {
            $parts[] = 'Dominant colors: '.implode(', ', $post['dominant_colors']);
        }

        if (! empty($post['subjects']) && is_array($post['subjects'])) {
            $parts[] = 'Photo subjects: '.implode('; ', $post['subjects']);
        }

        foreach ([
            'photo_treatment' => 'Photo treatment',
            'footer_structure' => 'Footer',
            'logo_placement' => 'Logo placement',
        ] as $key => $label) {
            if (! empty($locked[$key]) && is_string($locked[$key])) {
                $parts[] = "{$label}: {$locked[$key]}";
            }
        }

        $textLines = $this->extractOnImageTextEntries($post['on_image_text'] ?? null);
        if ($textLines !== []) {
            $parts[] = "On-image text (spell exactly, legible typography):\n- ".implode("\n- ", $textLines);
        }

        if (! empty($meta['style'])) {
            $parts[] = 'Style: '.$meta['style'];
        }

        if (! empty($meta['aspect_ratio'])) {
            $parts[] = 'Aspect ratio: '.$meta['aspect_ratio'];
        }

        $include = $quality['include'] ?? $quality['must_have'] ?? [];
        $avoid = $quality['avoid'] ?? [];

        if (is_array($include) && $include !== []) {
            $parts[] = 'Quality keywords: '.implode(', ', $include);
        }

        if (is_array($avoid) && $avoid !== []) {
            $parts[] = 'Avoid: '.implode(', ', $avoid);
        }

        $referenceUsage = $this->extractReferenceUsage($promptJson['reference_usage'] ?? null);
        if ($referenceUsage !== '') {
            $parts[] = $referenceUsage;
        }

        return trim(implode("\n\n", array_filter($parts)));
    }

    protected function formatOnImageTextBlock(mixed $onImageText, bool $useReferenceZones = false): string
    {
        $entries = $this->extractOnImageTextEntries($onImageText);
        if ($entries === []) {
            return '';
        }

        $numbered = [];
        foreach ($entries as $index => $entry) {
            $numbered[] = ($index + 1).'. '.$entry;
        }

        $zoneHint = $useReferenceZones
            ? 'Place each string in the matching text zone from the references (same position, size, and hierarchy). '
            : '';

        return 'ON-IMAGE TEXT — spell exactly, character for character. Shorten vs the full caption but keep relevant facts (dates, offers, names, CTA) in each zone. '
            .$zoneHint
            ."One string per layout zone — scannable headline-style, not a paragraph or the full social caption. Do not copy reference wording:\n"
            .implode("\n", $numbered);
    }

    protected function extractReferenceUsage(mixed $referenceUsage): string
    {
        if (is_string($referenceUsage) && trim($referenceUsage) !== '') {
            return 'Reference usage: '.trim($referenceUsage);
        }

        if (is_array($referenceUsage) && ! empty($referenceUsage['instruction'])) {
            return 'Reference usage: '.trim((string) $referenceUsage['instruction']);
        }

        return '';
    }

    /**
     * @return list<string>
     */
    protected function extractOnImageTextEntries(mixed $onImageText): array
    {
        if ($onImageText === null) {
            return [];
        }

        $lines = [];

        if (is_array($onImageText) && array_is_list($onImageText)) {
            foreach ($onImageText as $item) {
                $line = $this->formatOnImageTextEntry($item, null);
                if ($line !== null) {
                    $lines[] = $line;
                }
            }

            return $lines;
        }

        if (is_array($onImageText)) {
            foreach ($onImageText as $role => $item) {
                $line = $this->formatOnImageTextEntry($item, is_string($role) ? $role : null);
                if ($line !== null) {
                    $lines[] = $line;
                }
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>|mixed  $item
     */
    protected function formatOnImageTextEntry(mixed $item, ?string $role): ?string
    {
        if (is_string($item) && trim($item) !== '') {
            $line = is_string($role) && $role !== ''
                ? '['.strtoupper($role).' zone] "'.trim($item).'"'
                : '"'.trim($item).'"';

            return $line;
        }

        if (! is_array($item)) {
            return null;
        }

        $text = $item['text'] ?? null;
        if (! is_string($text) || trim($text) === '') {
            return null;
        }

        $zone = $item['position'] ?? $item['zone'] ?? null;
        $resolvedRole = $item['role'] ?? $role;

        $prefix = [];
        if (is_string($resolvedRole) && $resolvedRole !== '') {
            $prefix[] = strtoupper($resolvedRole).' zone';
        }
        if (is_string($zone) && $zone !== '') {
            $prefix[] = $zone;
        }

        $line = $prefix !== []
            ? '['.implode(' · ', $prefix).'] "'.$text.'"'
            : '"'.$text.'"';

        $details = array_filter([
            $item['font_style'] ?? $item['font'] ?? null,
            $item['weight'] ?? null,
            $item['color'] ?? null,
            $item['alignment'] ?? null,
        ]);

        if ($details !== []) {
            $line .= ' ('.implode(', ', $details).')';
        }

        return $line;
    }
}
