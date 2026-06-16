<?php

namespace App\Services\Brand;

use App\Models\BrandReference;
use App\Models\Project;
use App\Models\ProjectCluster;
use App\Models\ProjectClusterImage;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProjectBrandPersister
{
    public function persist(Project $project, array $dna, ?string $summary = null): Project
    {
        if ($dna === []) {
            throw new InvalidArgumentException('DNA JSON is empty.');
        }

        $project->loadMissing(['brandReferences']);

        $referencesByOrder = $project->brandReferences->keyBy('order');

        return DB::transaction(function () use ($project, $dna, $summary, $referencesByOrder) {
            $project->update([
                'dna_json' => $dna,
                'dna_summary' => $summary,
                'dna_extracted_at' => now(),
            ]);

            $project->clusters()->each(function (ProjectCluster $cluster) {
                $cluster->images()->delete();
            });
            $project->clusters()->delete();

            foreach ($dna['clusters'] ?? [] as $index => $clusterData) {
                $cluster = ProjectCluster::query()->create([
                    'project_id' => $project->id,
                    'key' => (string) ($clusterData['key'] ?? 'cluster-'.$index),
                    'label' => (string) ($clusterData['label'] ?? 'Cluster '.($index + 1)),
                    'summary' => $clusterData['summary'] ?? null,
                    'keywords_json' => $clusterData['keywords'] ?? [],
                    'position' => $index,
                ]);

                foreach ($clusterData['images'] ?? [] as $imgIndex => $imgData) {
                    $position = (int) ($imgData['position'] ?? $imgIndex);
                    $reference = $referencesByOrder->get($position);

                    if (! $reference instanceof BrandReference) {
                        continue;
                    }

                    ProjectClusterImage::query()->create([
                        'project_cluster_id' => $cluster->id,
                        'brand_reference_id' => $reference->id,
                        'is_anchor' => (bool) ($imgData['is_anchor'] ?? false),
                        'position' => $imgIndex,
                    ]);
                }

                if ($cluster->images()->where('is_anchor', true)->doesntExist()) {
                    $first = $cluster->images()->orderBy('position')->first();
                    if ($first) {
                        $first->update(['is_anchor' => true]);
                    }
                }
            }

            return $project->fresh(['clusters.images.brandReference']);
        });
    }
}
