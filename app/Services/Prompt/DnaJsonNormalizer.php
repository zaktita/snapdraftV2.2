<?php

namespace App\Services\Prompt;

use Illuminate\Support\Str;

/**
 * Normalizes Brand DNA JSON from vision models that drift from the strict schema.
 */
class DnaJsonNormalizer
{
    /**
     * @param  array<string, mixed>  $dna
     * @return array<string, mixed>
     */
    public function normalize(array $dna, string $brandName, int $imageCount): array
    {
        $dna['brand'] = is_array($dna['brand'] ?? null) ? $dna['brand'] : [];
        $dna['brand']['name'] = trim((string) ($dna['brand']['name'] ?? $brandName));
        if ($dna['brand']['name'] === '') {
            $dna['brand']['name'] = $brandName;
        }

        if (empty($dna['brand']['slug'])) {
            $dna['brand']['slug'] = Str::slug($dna['brand']['name']);
        }

        $dna['rules'] = $this->normalizeRules($dna);
        $dna['clusters'] = $this->normalizeClusters($dna['clusters'] ?? null, $imageCount);

        return $dna;
    }

    /**
     * @param  array<string, mixed>  $dna
     * @return array{locked: list<string>, flex: list<string>}
     */
    protected function normalizeRules(array $dna): array
    {
        $rulesBlock = is_array($dna['rules'] ?? null) ? $dna['rules'] : [];

        $locked = $rulesBlock['locked']
            ?? $rulesBlock['locked_rules']
            ?? $dna['locked_rules']
            ?? $dna['lockedRules']
            ?? $dna['globalRules']
            ?? $dna['global_rules']
            ?? null;

        if (is_string($locked)) {
            $locked = [$locked];
        }

        if (! is_array($locked) || $locked === []) {
            $locked = $this->deriveLockedRules($dna);
        }

        $locked = array_values(array_filter(array_map(
            fn ($rule) => is_string($rule) ? trim($rule) : null,
            $locked
        )));

        $flex = $rulesBlock['flex']
            ?? $rulesBlock['flex_rules']
            ?? $dna['flex_rules']
            ?? $dna['flexRules']
            ?? [];

        if (! is_array($flex)) {
            $flex = [];
        }

        return [
            'locked' => $locked,
            'flex' => array_values(array_filter(array_map(
                fn ($rule) => is_string($rule) ? trim($rule) : null,
                $flex
            ))),
        ];
    }

    /**
     * @param  array<string, mixed>  $dna
     * @return list<string>
     */
    protected function deriveLockedRules(array $dna): array
    {
        $derived = [];

        $visual = is_array($dna['visual_identity'] ?? null) ? $dna['visual_identity'] : [];

        if (! empty($visual['composition']['layout_system'])) {
            $derived[] = (string) $visual['composition']['layout_system'];
        }

        if (! empty($visual['typography']['headline'])) {
            $derived[] = 'Typography: '.$visual['typography']['headline'];
        }

        if (! empty($visual['recurring_motifs']) && is_array($visual['recurring_motifs'])) {
            foreach ($visual['recurring_motifs'] as $motif) {
                if (is_string($motif) && $motif !== '') {
                    $derived[] = $motif;
                }
            }
        }

        $voice = is_array($dna['voice_and_copy'] ?? null) ? $dna['voice_and_copy'] : [];
        foreach ($voice['do'] ?? [] as $item) {
            if (is_string($item) && $item !== '') {
                $derived[] = $item;
            }
        }

        if ($derived === []) {
            $derived[] = 'Maintain strict visual consistency with the reference posts.';
        }

        return $derived;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function normalizeClusters(mixed $clusters, int $imageCount): array
    {
        if (! is_array($clusters) || $clusters === []) {
            return [[
                'key' => 'default',
                'label' => 'Default template family',
                'summary' => 'All reference posts',
                'keywords' => [],
                'images' => $this->allImagePositions($imageCount),
            ]];
        }

        $normalized = [];

        foreach ($clusters as $index => $cluster) {
            if (! is_array($cluster)) {
                continue;
            }

            $key = (string) ($cluster['key'] ?? 'cluster-'.$index);
            $label = (string) ($cluster['label'] ?? 'Cluster '.($index + 1));
            $keywords = $cluster['keywords'] ?? $cluster['tags'] ?? [];

            if (! is_array($keywords)) {
                $keywords = is_string($keywords) ? [$keywords] : [];
            }

            $images = $cluster['images'] ?? $cluster['image_indices'] ?? $cluster['imageIndices'] ?? [];
            if (! is_array($images) || $images === []) {
                $images = [['position' => 0, 'label' => $label, 'is_anchor' => true]];
            }

            $imageRows = [];
            foreach ($images as $imgIndex => $img) {
                if (is_int($img)) {
                    $imageRows[] = [
                        'position' => $img,
                        'label' => 'Reference '.$img,
                        'is_anchor' => $imgIndex === 0,
                    ];
                    continue;
                }

                if (! is_array($img)) {
                    continue;
                }

                $position = isset($img['position']) ? (int) $img['position'] : (int) $imgIndex;
                $imageRows[] = [
                    'position' => $position,
                    'label' => (string) ($img['label'] ?? 'Reference '.$position),
                    'is_anchor' => (bool) ($img['is_anchor'] ?? $imgIndex === 0),
                ];
            }

            if ($imageRows === []) {
                $imageRows = [['position' => 0, 'label' => $label, 'is_anchor' => true]];
            }

            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'summary' => $cluster['summary'] ?? null,
                'keywords' => array_values(array_filter(array_map('strval', $keywords))),
                'images' => $imageRows,
            ];
        }

        return $normalized !== [] ? $normalized : [[
            'key' => 'default',
            'label' => 'Default template family',
            'keywords' => [],
            'images' => $this->allImagePositions($imageCount),
        ]];
    }

    /**
     * @return list<array{position: int, label: string, is_anchor: bool}>
     */
    protected function allImagePositions(int $imageCount): array
    {
        $count = max($imageCount, 1);
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'position' => $i,
                'label' => 'Reference '.$i,
                'is_anchor' => $i === 0,
            ];
        }

        return $rows;
    }
}
