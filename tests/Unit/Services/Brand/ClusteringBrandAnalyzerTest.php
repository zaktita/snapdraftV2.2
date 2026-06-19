<?php

namespace Tests\Unit\Services\Brand;

use App\Models\Project;
use App\Services\AI\ClusteringService;
use App\Services\AI\DTO\AnalysisResult;
use App\Services\AI\OpenRouter\BrandDnaExtractor;
use App\Services\Brand\ClusteringBrandAnalyzer;
use App\Services\Brand\ClusteringDnaMapper;
use App\Services\Brand\ProjectBrandPersister;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use RuntimeException;
use Tests\TestCase;

class ClusteringBrandAnalyzerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_falls_back_to_openrouter_when_gemini_key_is_revoked(): void
    {
        config()->set('ai.cluster_driver', 'auto');
        config()->set('services.ai.enable_fallback', true);
        config()->set('openrouter.api_key', 'sk-test');

        $project = Mockery::mock(Project::class)->makePartial();
        $project->id = 1;
        $project->name = 'Test Brand';
        $project->title = null;
        $project->settings = [];
        $project->shouldReceive('update')->once()->andReturnTrue();
        $refPaths = ['refs/a.jpg', 'refs/b.jpg', 'refs/c.jpg'];

        $clustering = Mockery::mock(ClusteringService::class);
        $clustering->shouldReceive('cluster')
            ->once()
            ->with($refPaths)
            ->andThrow(new RuntimeException('Gemini API error (HTTP 403): leaked'));

        $mapper = Mockery::mock(ClusteringDnaMapper::class);
        $mapper->shouldNotReceive('toDnaJson');

        $persister = Mockery::mock(ProjectBrandPersister::class);
        $persister->shouldReceive('persist')
            ->once()
            ->with($project, ['brand' => ['name' => 'Test Brand']], 'summary text');

        $extractor = Mockery::mock(BrandDnaExtractor::class);
        $extractor->shouldReceive('extract')
            ->once()
            ->with($project, $refPaths)
            ->andReturn(new AnalysisResult(
                rawText: 'raw',
                analysisProse: 'analysis',
                promptJson: ['brand' => ['name' => 'Test Brand']],
                tweaks: [],
                tokensIn: 0,
                tokensOut: 0,
                estimatedCostUsd: null,
                latencyMs: 1,
                jsonValid: true,
                jsonValidationErrors: [],
                summary: 'summary text',
            ));

        $analyzer = new ClusteringBrandAnalyzer($clustering, $mapper, $persister, $extractor);
        $analyzer->analyze($project, $refPaths);
    }
}
