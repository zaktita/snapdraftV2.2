<?php

namespace App\Services\Brand;

use App\Models\Project;
use App\Services\AI\ClusteringService;
use RuntimeException;

class ClusteringBrandAnalyzer
{
    public function __construct(
        protected ClusteringService $clusteringService,
        protected ClusteringDnaMapper $mapper,
        protected ProjectBrandPersister $persister,
    ) {}

    /**
     * Run original Gemini clustering and persist clusters + DNA-compatible profile.
     *
     * @param  list<string>  $refPaths
     */
    public function analyze(Project $project, array $refPaths): void
    {
        if ($refPaths === []) {
            throw new RuntimeException('No reference image paths found.');
        }

        if (count($refPaths) < 2) {
            throw new RuntimeException('At least 2 reference images are required for clustering.');
        }

        $clusterResult = $this->clusteringService->cluster($refPaths);

        $mapped = $this->mapper->toDnaJson(
            $clusterResult,
            $project->name ?? $project->title ?? 'Brand',
        );

        $project->update([
            'settings' => array_merge($project->settings ?? [], [
                'cluster_result' => $clusterResult,
                'clustering_engine' => 'gemini_clustering_service',
            ]),
        ]);

        $this->persister->persist($project, $mapped['dna'], $mapped['summary']);
    }
}
