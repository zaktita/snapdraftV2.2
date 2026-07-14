<?php

namespace App\Services\Brand;

use App\Models\Project;
use App\Models\ProjectCluster;
use App\Models\ProjectClusterImage;
use Illuminate\Support\Collection;
use RuntimeException;

class ProjectClusterSelector
{
    public function __construct(
        protected ClusterCaptionMatcher $captionMatcher,
    ) {}

    /**
     * @return array{
     *     scores: array<string, float>,
     *     selected_key: ?string,
     *     used_model_fallback: bool,
     *     used_vision_fallback: bool,
     *     top_score: float,
     *     second_score: float
     * }
     */
    public function scoreClusters(Project $project, string $caption): array
    {
        $project->loadMissing(['clusters.images']);

        if ($project->clusters->isEmpty()) {
            return [
                'scores' => [],
                'selected_key' => null,
                'used_model_fallback' => false,
                'used_vision_fallback' => false,
                'top_score' => 0,
                'second_score' => 0,
            ];
        }

        $clusters = $project->clusters->values();
        $match = $this->captionMatcher->match(
            $this->clustersToMatcherPayload($clusters),
            $caption,
        );

        $selected = $clusters[$match['selected_index']] ?? $clusters->first();

        $scoresByKey = [];
        foreach ($clusters as $i => $cluster) {
            $scoresByKey[$cluster->key] = (float) ($match['scores'][$i] ?? 0);
        }

        return [
            'scores' => $scoresByKey,
            'selected_key' => $selected?->key,
            'used_model_fallback' => (bool) $match['used_model_fallback'],
            'used_vision_fallback' => false,
            'top_score' => (float) $match['top_score'],
            'second_score' => (float) $match['second_score'],
        ];
    }

    /**
     * @return array{cluster: ProjectCluster, images: Collection<int, ProjectClusterImage>}
     */
    public function select(Project $project, string $caption): array
    {
        $project->loadMissing(['clusters.images.brandReference']);

        if ($project->clusters->isEmpty()) {
            throw new RuntimeException('Project has no clusters.');
        }

        $scoring = $this->scoreClusters($project, $caption);
        $cluster = $project->clusters->firstWhere('key', $scoring['selected_key'])
            ?? $project->clusters->first();

        if (! $cluster) {
            throw new RuntimeException('Project has no clusters.');
        }

        return [
            'cluster' => $cluster,
            'images' => $this->pickImages($cluster),
        ];
    }

    /**
     * @param  list<int>|null  $preferredIds  Cluster image IDs chosen during post generation
     * @return Collection<int, ProjectClusterImage>
     */
    public function imagesForCluster(ProjectCluster $cluster, ?array $preferredIds = null): Collection
    {
        $cluster->loadMissing('images.brandReference');
        $limit = $this->imagesPerClusterLimit();
        $ordered = $this->orderedClusterImages($cluster);

        if ($preferredIds !== null && $preferredIds !== []) {
            $byId = $ordered->keyBy('id');
            $picked = collect();

            foreach ($preferredIds as $id) {
                $image = $byId->get((int) $id);
                if ($image) {
                    $picked->push($image);
                }
            }

            foreach ($ordered as $image) {
                if ($picked->count() >= $limit) {
                    break;
                }

                if (! $picked->contains('id', $image->id)) {
                    $picked->push($image);
                }
            }

            if ($picked->isNotEmpty()) {
                return $picked->take($limit)->values();
            }
        }

        return $ordered->take($limit)->values();
    }

    /**
     * @return Collection<int, ProjectClusterImage>
     */
    protected function pickImages(ProjectCluster $cluster): Collection
    {
        return $this->imagesForCluster($cluster);
    }

    /**
     * @return Collection<int, ProjectClusterImage>
     */
    protected function orderedClusterImages(ProjectCluster $cluster): Collection
    {
        return $cluster->images
            ->sortBy([
                ['is_anchor', 'desc'],
                ['position', 'asc'],
            ])
            ->values();
    }

    protected function imagesPerClusterLimit(): int
    {
        return (int) config('ai.cluster_selection.max_images_per_cluster', 3);
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
            'rendering_style' => $entry['renderingStyle'] ?? null,
            'photo_treatment' => $entry['photoTreatment'] ?? null,
            'graphic_devices' => $entry['graphicDevices'] ?? [],
            'typography_details' => $entry['typographyDetails'] ?? null,
            'layout_skeleton' => $entry['layoutSkeleton'] ?? null,
            'logo_treatment' => $entry['logoTreatment'] ?? null,
            'text_density' => $entry['textDensity'] ?? null,
            'global_rules' => $clusterResult['globalRules'] ?? [],
            'global_analysis' => $clusterResult['globalAnalysis'] ?? null,
        ];
    }

    /**
     * @param  Collection<int, ProjectCluster>  $clusters
     * @return list<array{name: string, tags: list<string>, reason: string, imageIndices: list<int>}>
     */
    protected function clustersToMatcherPayload(Collection $clusters): array
    {
        $payload = [];

        foreach ($clusters as $cluster) {
            $imageCount = $cluster->images->count();
            $payload[] = [
                'name' => (string) $cluster->label,
                'tags' => array_values(array_map('strval', $cluster->keywords_json ?? [])),
                'reason' => (string) ($cluster->summary ?? ''),
                'imageIndices' => $imageCount > 0 ? range(0, $imageCount - 1) : [],
            ];
        }

        return $payload;
    }
}
