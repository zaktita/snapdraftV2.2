<?php

namespace App\Services\Wizards;

use App\Models\GenerationHistory;
use App\Models\Project;
use App\Services\Brand\ProjectClusterSelector;
use Illuminate\Support\Facades\Storage;

class ImageGenerationDebugPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function forHistory(Project $project, GenerationHistory $history, ?int $rowIndex = null): array
    {
        $project->loadMissing(['generationHistory', 'clusters.images.brandReference']);

        $rowIndex ??= (int) data_get($history->parameters, 'csv_row_index', -1);
        if ($rowIndex < 0) {
            $rowIndex = null;
        }

        $rowMatches = $project->settings['cluster_csv_pipeline']['row_matches'] ?? [];
        $match = $rowIndex !== null ? ($rowMatches[$rowIndex] ?? null) : null;

        $clusterKey = $history->cluster_key
            ?? (is_array($match) ? ($match['cluster_key'] ?? null) : null)
            ?? data_get($history->parameters, 'cluster_key');

        $cluster = is_string($clusterKey) && $clusterKey !== ''
            ? $project->clusters->firstWhere('key', $clusterKey)
            : null;

        $clusterSelector = app(ProjectClusterSelector::class);

        $imageGen = data_get($history->request_data, 'image_generation', []);
        $preferredIds = $imageGen['cluster_image_ids']
            ?? data_get($history->request_data, 'cluster_image_ids')
            ?? (is_array($match) ? ($match['cluster_image_ids'] ?? null) : null);

        $clusterImages = $cluster
            ? $clusterSelector->imagesForCluster(
                $cluster,
                is_array($preferredIds) && $preferredIds !== [] ? $preferredIds : null,
            )
            : collect();

        $clusterMetadata = null;
        if ($cluster) {
            $clusterMetadata = is_array($history->prompt_json)
                ? ($history->prompt_json['cluster_context'] ?? null)
                : null;
            $clusterMetadata ??= $clusterSelector->clusterMetadata($project, $cluster);
        }

        return [
            'row_index' => $rowIndex,
            'available' => true,
            'cluster' => $cluster ? [
                'key' => $cluster->key,
                'label' => $cluster->label,
                'summary' => $cluster->summary,
                'keywords' => $cluster->keywords_json ?? [],
                'metadata' => $clusterMetadata,
                'images_sent_to_model' => $clusterImages
                    ->map(function ($img) {
                        $ref = $img->brandReference;

                        return [
                            'cluster_image_id' => $img->id,
                            'brand_reference_id' => $ref?->id,
                            'order' => $ref?->order,
                            'url' => $ref ? self::storageUrl($ref->url) : null,
                            'thumbnail_url' => $ref?->thumbnail_url
                                ? self::storageUrl($ref->thumbnail_url)
                                : null,
                            'is_anchor' => (bool) $img->is_anchor,
                        ];
                    })
                    ->values()
                    ->all(),
            ] : null,
            'prompt_json' => $history->prompt_json,
            'compiled_prompt' => $history->compiled_prompt,
            'image_request_prompt' => $imageGen['image_request_prompt'] ?? null,
            'image_generation' => $imageGen !== [] ? $imageGen : null,
            'match' => $match,
            'json_valid' => $history->json_valid,
            'history_status' => $history->status,
        ];
    }

    protected static function storageUrl(string $path): string
    {
        if (str_starts_with($path, 'http') || str_starts_with($path, '/storage/')) {
            return $path;
        }

        return Storage::url($path);
    }
}
