<?php

namespace App\Services\Brand;

use App\Models\Project;
use App\Models\ProjectCluster;
use App\Models\ProjectClusterImage;
use App\Services\AI\ModelRegistry;
use App\Services\AI\OpenRouter\OpenRouterClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProjectClusterSelector
{
    public function __construct(
        protected OpenRouterClient $client,
        protected ModelRegistry $modelRegistry,
    ) {}

    /**
     * @return array{
     *     scores: array<string, float>,
     *     selected_key: ?string,
     *     used_model_fallback: bool,
     *     top_score: float,
     *     second_score: float
     * }
     */
    public function scoreClusters(Project $project, string $caption): array
    {
        $project->loadMissing(['clusters']);

        if ($project->clusters->isEmpty()) {
            return [
                'scores' => [],
                'selected_key' => null,
                'used_model_fallback' => false,
                'top_score' => 0,
                'second_score' => 0,
            ];
        }

        $text = Str::lower(trim($caption));
        $scoresById = [];

        foreach ($project->clusters as $cluster) {
            $scoresById[$cluster->id] = $this->keywordScore($text, $cluster);
        }

        arsort($scoresById);
        $ids = array_keys($scoresById);
        $topId = $ids[0] ?? null;
        $secondId = $ids[1] ?? null;
        $topScore = $topId ? $scoresById[$topId] : 0;
        $secondScore = $secondId ? $scoresById[$secondId] : 0;

        $threshold = (float) config('ai.cluster_selection.keyword_score_threshold', 0.15);
        $ambiguousGap = (float) config('ai.cluster_selection.ambiguous_gap', 0.05);

        $needsModelPick = $topScore < $threshold
            || ($secondId !== null && ($topScore - $secondScore) < $ambiguousGap);

        $selectedCluster = $topId
            ? $project->clusters->firstWhere('id', $topId)
            : $project->clusters->first();

        $usedModelFallback = false;

        if ($needsModelPick && $project->clusters->count() > 1) {
            $picked = $this->modelPickCluster($project, $caption);
            if ($picked !== null) {
                $selectedCluster = $picked;
                $usedModelFallback = true;
            }
        }

        $scoresByKey = [];
        foreach ($project->clusters as $cluster) {
            $scoresByKey[$cluster->key] = $scoresById[$cluster->id] ?? 0;
        }

        return [
            'scores' => $scoresByKey,
            'selected_key' => $selectedCluster?->key,
            'used_model_fallback' => $usedModelFallback,
            'top_score' => $topScore,
            'second_score' => $secondScore,
        ];
    }

    /**
     * @return array{cluster: ProjectCluster, images: Collection<int, ProjectClusterImage>}
     */
    public function select(Project $project, string $caption): array
    {
        $project->loadMissing(['clusters.images.brandReference']);

        if ($project->clusters->isEmpty()) {
            throw new \RuntimeException('Project has no clusters.');
        }

        $text = Str::lower(trim($caption));
        $scores = [];

        foreach ($project->clusters as $cluster) {
            $scores[$cluster->id] = $this->keywordScore($text, $cluster);
        }

        arsort($scores);
        $ids = array_keys($scores);
        $topId = $ids[0] ?? null;
        $secondId = $ids[1] ?? null;
        $topScore = $topId ? $scores[$topId] : 0;
        $secondScore = $secondId ? $scores[$secondId] : 0;

        $threshold = (float) config('ai.cluster_selection.keyword_score_threshold', 0.15);
        $ambiguousGap = (float) config('ai.cluster_selection.ambiguous_gap', 0.05);

        $needsModelPick = $topScore < $threshold
            || ($secondId !== null && ($topScore - $secondScore) < $ambiguousGap);

        $cluster = $topId
            ? $project->clusters->firstWhere('id', $topId)
            : $project->clusters->first();

        if ($needsModelPick && $project->clusters->count() > 1) {
            $picked = $this->modelPickCluster($project, $caption);
            if ($picked !== null) {
                $cluster = $picked;
            }
        }

        if (! $cluster) {
            throw new \RuntimeException('Project has no clusters.');
        }

        return [
            'cluster' => $cluster,
            'images' => $this->pickImages($cluster),
        ];
    }

    protected function keywordScore(string $text, ProjectCluster $cluster): float
    {
        $terms = array_merge(
            [$cluster->label, $cluster->summary ?? ''],
            $cluster->keywords_json ?? [],
        );

        $tokens = $this->tokenize($text);
        if ($tokens === []) {
            return 0;
        }

        $hits = 0;
        foreach ($terms as $term) {
            foreach ($this->tokenize(Str::lower((string) $term)) as $needle) {
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

    protected function modelPickCluster(Project $project, string $caption): ?ProjectCluster
    {
        $model = $this->modelRegistry->resolveSlug(
            config('ai.default_post_model_slug', 'gpt-4o'),
        );

        if (! $model) {
            return null;
        }

        $options = $project->clusters->map(fn ($c) => [
            'key' => $c->key,
            'label' => $c->label,
            'keywords' => $c->keywords_json,
            'summary' => $c->summary,
        ])->values()->all();

        $payload = [
            'model' => $model->openrouter_model_id,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Pick the single best cluster key for a new social post. Reply with ONLY the cluster key string, nothing else.',
                ],
                [
                    'role' => 'user',
                    'content' => "Caption: {$caption}\nClusters: ".json_encode($options),
                ],
            ],
            'max_tokens' => 64,
            'temperature' => 0,
        ];

        try {
            $response = $this->client->chat($payload);
            $key = trim((string) data_get($response, 'choices.0.message.content', ''));

            return $project->clusters->firstWhere('key', $key);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<int>|null  $preferredIds  Cluster image IDs chosen during post generation
     * @return Collection<int, ProjectClusterImage>
     */
    public function imagesForCluster(ProjectCluster $cluster, ?array $preferredIds = null): Collection
    {
        $cluster->loadMissing('images.brandReference');
        $max = (int) config('ai.cluster_selection.max_images_per_cluster', 3);

        if ($preferredIds !== null && $preferredIds !== []) {
            $byId = $cluster->images->keyBy('id');
            $picked = collect();

            foreach ($preferredIds as $id) {
                $image = $byId->get((int) $id);
                if ($image) {
                    $picked->push($image);
                }
            }

            if ($picked->isNotEmpty()) {
                return $picked->take($max)->values();
            }
        }

        return $this->pickImages($cluster);
    }

    /**
     * @return Collection<int, ProjectClusterImage>
     */
    protected function pickImages(ProjectCluster $cluster): Collection
    {
        $max = (int) config('ai.cluster_selection.max_images_per_cluster', 3);

        return $cluster->images()
            ->with('brandReference')
            ->orderBy('position')
            ->take($max)
            ->get()
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function clusterMetadata(Project $project, ProjectCluster $cluster): ?array
    {
        $clusterResult = $project->settings['cluster_result'] ?? null;
        if (! is_array($clusterResult)) {
            return null;
        }

        $clusters = $clusterResult['clusters'] ?? [];
        $entry = $clusters[$cluster->position] ?? null;

        if (! is_array($entry)) {
            return null;
        }

        return [
            'name' => $entry['name'] ?? $cluster->label,
            'tags' => $entry['tags'] ?? $cluster->keywords_json ?? [],
            'reason' => $entry['reason'] ?? $cluster->summary,
            'image_indices' => $entry['imageIndices'] ?? [],
            'dominant_color' => $entry['dominantColor'] ?? null,
            'palette' => $entry['palette'] ?? [],
            'typography_style' => $entry['typographyStyle'] ?? null,
            'composition_type' => $entry['compositionType'] ?? null,
            'background_treatment' => $entry['backgroundTreatment'] ?? null,
            'text_placement' => $entry['textPlacement'] ?? null,
            'global_rules' => $clusterResult['globalRules'] ?? [],
            'global_analysis' => $clusterResult['globalAnalysis'] ?? null,
        ];
    }
}
