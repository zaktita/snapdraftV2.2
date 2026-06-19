<?php

namespace App\Services\Brand;

use App\Models\Project;
use App\Services\AI\ClusteringService;
use App\Services\AI\GeminiOpenRouterFallback;
use App\Services\AI\OpenRouter\BrandDnaExtractor;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ClusteringBrandAnalyzer
{
    public function __construct(
        protected ClusteringService $clusteringService,
        protected ClusteringDnaMapper $mapper,
        protected ProjectBrandPersister $persister,
        protected BrandDnaExtractor $dnaExtractor,
    ) {}

    /**
     * Run brand clustering / DNA extraction and persist clusters + DNA profile.
     *
     * @param  list<string>  $refPaths
     */
    public function analyze(Project $project, array $refPaths): void
    {
        if ($refPaths === []) {
            throw new RuntimeException('No reference image paths found.');
        }

        if (count($refPaths) < 3) {
            throw new RuntimeException('At least 3 reference images are required for clustering.');
        }

        $driver = config('ai.cluster_driver', 'auto');
        $brandName = $project->name ?? $project->title ?? 'Brand';

        if ($driver === 'openrouter') {
            $this->analyzeViaOpenRouter($project, $refPaths);

            return;
        }

        if ($driver === 'gemini') {
            $this->analyzeViaGeminiClustering($project, $refPaths, $brandName);

            return;
        }

        try {
            $this->analyzeViaGeminiClustering($project, $refPaths, $brandName);
        } catch (Throwable $primaryError) {
            if (! GeminiOpenRouterFallback::shouldFallback($primaryError)) {
                throw $primaryError;
            }

            Log::warning('ClusteringBrandAnalyzer: Gemini failed, falling back to OpenRouter', [
                'project_id' => $project->id,
                'error' => $primaryError->getMessage(),
            ]);

            $this->analyzeViaOpenRouter($project, $refPaths);
        }
    }

    /**
     * @param  list<string>  $refPaths
     */
    private function analyzeViaGeminiClustering(Project $project, array $refPaths, string $brandName): void
    {
        $clusterResult = $this->clusteringService->cluster($refPaths);

        $mapped = $this->mapper->toDnaJson($clusterResult, $brandName);

        $project->update([
            'settings' => array_merge($project->settings ?? [], [
                'cluster_result' => $clusterResult,
                'clustering_engine' => 'gemini_clustering_service',
            ]),
        ]);

        $this->persister->persist($project, $mapped['dna'], $mapped['summary']);
    }

    /**
     * @param  list<string>  $refPaths
     */
    private function analyzeViaOpenRouter(Project $project, array $refPaths): void
    {
        $result = $this->dnaExtractor->extract($project, $refPaths);

        if (! is_array($result->promptJson) || ! $result->jsonValid) {
            $errors = implode('; ', $result->jsonValidationErrors ?? []);
            throw new RuntimeException(
                'OpenRouter brand DNA extraction failed validation'
                .($errors !== '' ? ": {$errors}" : '.')
            );
        }

        $project->update([
            'settings' => array_merge($project->settings ?? [], [
                'clustering_engine' => 'openrouter_brand_dna_extractor',
            ]),
        ]);

        $this->persister->persist($project, $result->promptJson, $result->summary);
    }
}
