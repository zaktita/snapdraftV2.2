<?php

namespace App\Services\Brand;

use App\Services\AI\GeminiClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Lab-style caption → cluster matching on raw ClusteringService cluster arrays.
 * Skips "unfit", keyword scores first, Gemini fallback when ambiguous.
 */
class ClusterCaptionMatcher
{
    public function __construct(
        protected GeminiClient $gemini,
    ) {}

    /**
     * @param  list<array{name?: string, tags?: list<string>, reason?: string, imageIndices?: list<int>}>  $clusters
     * @return array{
     *     scores: array<int, float>,
     *     selected_index: int,
     *     selected_image_indices: list<int>,
     *     used_model_fallback: bool,
     *     top_score: float,
     *     second_score: float
     * }
     */
    public function match(array $clusters, string $caption): array
    {
        if ($clusters === []) {
            throw new RuntimeException('No clusters to match against.');
        }

        $text = Str::lower(trim($caption));
        $scores = [];
        $eligibleIndexes = [];

        foreach ($clusters as $i => $cluster) {
            $isUnfit = $this->isUnfitClusterName((string) ($cluster['name'] ?? ''));
            $scores[$i] = $isUnfit ? 0.0 : $this->keywordScore($text, $cluster);
            if (! $isUnfit) {
                $eligibleIndexes[] = $i;
            }
        }

        if ($eligibleIndexes === []) {
            $eligibleIndexes = array_keys($clusters);
        }

        // Prefer clusters that can supply 3 refs for master-prompt generation.
        $withEnoughImages = array_values(array_filter(
            $eligibleIndexes,
            fn (int $i) => count($clusters[$i]['imageIndices'] ?? []) >= 3,
        ));
        if ($withEnoughImages !== []) {
            $eligibleIndexes = $withEnoughImages;
        }

        $eligibleScores = [];
        foreach ($eligibleIndexes as $i) {
            $eligibleScores[$i] = $scores[$i];
        }
        arsort($eligibleScores);
        $ordered = array_keys($eligibleScores);
        $topIndex = (int) ($ordered[0] ?? 0);
        $secondIndex = isset($ordered[1]) ? (int) $ordered[1] : null;
        $topScore = $eligibleScores[$topIndex] ?? 0.0;
        $secondScore = $secondIndex !== null ? ($eligibleScores[$secondIndex] ?? 0.0) : 0.0;

        $threshold = (float) config('ai.cluster_selection.keyword_score_threshold', 0.15);
        $ambiguousGap = (float) config('ai.cluster_selection.ambiguous_gap', 0.05);

        $needsModelPick = $topScore < $threshold
            || ($secondIndex !== null && ($topScore - $secondScore) < $ambiguousGap);

        $selectedIndex = $topIndex;
        $usedModelFallback = false;

        $eligibleClusters = [];
        foreach ($eligibleIndexes as $i) {
            $eligibleClusters[] = $clusters[$i];
        }

        if ($needsModelPick && count($eligibleClusters) > 1) {
            $picked = $this->modelPickClusterIndex($eligibleClusters, $caption);
            if ($picked !== null) {
                $selectedIndex = $eligibleIndexes[$picked] ?? $topIndex;
                $usedModelFallback = true;
            }
        }

        $selected = $clusters[$selectedIndex] ?? $clusters[0];
        $imageIndices = array_values(array_map('intval', $selected['imageIndices'] ?? []));
        $limit = (int) config('ai.cluster_selection.max_images_per_cluster', 3);
        $selectedImageIndices = array_slice($imageIndices, 0, max(1, $limit));

        ksort($scores);

        return [
            'scores' => $scores,
            'selected_index' => $selectedIndex,
            'selected_image_indices' => $selectedImageIndices,
            'used_model_fallback' => $usedModelFallback,
            'top_score' => (float) $topScore,
            'second_score' => (float) $secondScore,
        ];
    }

    public function isUnfitClusterName(string $name): bool
    {
        $normalized = strtolower(trim($name));

        return $normalized === 'unfit' || str_starts_with($normalized, 'unfit');
    }

    /**
     * @param  array{name?: string, tags?: list<string>, reason?: string}  $cluster
     */
    protected function keywordScore(string $text, array $cluster): float
    {
        $terms = array_merge(
            [(string) ($cluster['name'] ?? '')],
            [(string) ($cluster['reason'] ?? '')],
            array_map('strval', $cluster['tags'] ?? []),
        );

        $tokens = $this->tokenize($text);
        if ($tokens === []) {
            return 0.0;
        }

        $hits = 0;
        foreach ($terms as $term) {
            foreach ($this->tokenize(Str::lower($term)) as $needle) {
                if (strlen($needle) < 3) {
                    continue;
                }
                foreach ($tokens as $token) {
                    if (str_contains($token, $needle) || str_contains($needle, $token)) {
                        $hits++;
                        break;
                    }
                }
            }
        }

        return $hits / max(count($tokens), 1);
    }

    /**
     * @return list<string>
     */
    protected function tokenize(string $text): array
    {
        $normalized = preg_replace('/[^a-z0-9àâäéèêëïîôùûüç\s-]/iu', ' ', $text) ?? $text;
        $parts = preg_split('/\s+/', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?? [];

        return array_values(array_filter($parts, fn ($p) => strlen($p) >= 3));
    }

    /**
     * @param  list<array>  $clusters
     */
    protected function modelPickClusterIndex(array $clusters, string $caption): ?int
    {
        $options = [];
        foreach ($clusters as $i => $cluster) {
            $options[] = [
                'index' => $i,
                'name' => $cluster['name'] ?? "Cluster {$i}",
                'tags' => $cluster['tags'] ?? [],
                'reason' => $cluster['reason'] ?? '',
                'composition' => $cluster['compositionType'] ?? '',
                'typography' => $cluster['typographyStyle'] ?? '',
            ];
        }

        $schema = [
            'type' => 'object',
            'properties' => [
                'clusterIndex' => ['type' => 'integer'],
                'explanation' => ['type' => 'string'],
            ],
            'required' => ['clusterIndex'],
        ];

        $prompt = "Pick the single best cluster index for this social post caption.\n"
            ."Caption:\n{$caption}\n\n"
            .'Clusters: '.json_encode($options, JSON_UNESCAPED_UNICODE)."\n\n"
            .'Reply with clusterIndex only (0-based). Do not pick outlier/unfit clusters.';

        $model = config('services.gemini.prompt_model', config('services.gemini.model'));

        try {
            $result = $this->gemini->generateContentWithSchema(
                $model,
                [['parts' => [['text' => $prompt]]]],
                $schema,
            );
            $index = (int) ($result['clusterIndex'] ?? -1);
            if ($index >= 0 && $index < count($clusters)) {
                return $index;
            }
        } catch (Throwable $e) {
            Log::warning('ClusterCaptionMatcher: model cluster pick failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
