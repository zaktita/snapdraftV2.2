<?php

namespace Tests\Unit\Services\Brand;

use App\Models\GenerationHistory;
use App\Models\Project;
use App\Models\ProjectCluster;
use App\Models\User;
use App\Services\AI\DTO\PostGenerationResult;
use App\Services\AI\OpenRouter\OpenRouterCsvPostGenerator;
use App\Services\Brand\CsvPostPromptBuilder;
use App\Services\Brand\ProjectClusterSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CsvPostPromptBuilderBuildBatchFromMatchesTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_batch_from_matches_uses_pre_matched_cluster(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'dna_json' => ['brand' => ['name' => 'Test']],
            'settings' => [
                'csv_data' => [
                    ['title' => 'Launch', 'caption' => 'Big launch', 'format' => 'square'],
                ],
                'history_ids' => [],
                'resolution_multiplier' => 1,
                'cluster_csv_pipeline' => [
                    'row_matches' => [
                        0 => [
                            'cluster_key' => 'product',
                            'cluster_label' => 'Product',
                        ],
                    ],
                ],
            ],
        ]);

        $cluster = ProjectCluster::create([
            'project_id' => $project->id,
            'key' => 'product',
            'label' => 'Product',
            'position' => 0,
        ]);

        $history = GenerationHistory::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'ai_model' => 'test-model',
            'prompt' => 'Launch',
            'status' => 'pending',
        ]);

        $project->update([
            'settings' => array_merge($project->settings, [
                'history_ids' => [0 => $history->id],
            ]),
        ]);

        $postResult = new PostGenerationResult(
            rawText: '{}',
            analysisProse: null,
            promptJson: ['post' => ['concept' => 'Launch concept']],
            tweaks: [],
            tokensIn: 10,
            tokensOut: 20,
            estimatedCostUsd: 0.01,
            latencyMs: 100,
            jsonValid: true,
            jsonValidationErrors: [],
            clusterKey: 'product',
        );

        $postGenerator = Mockery::mock(OpenRouterCsvPostGenerator::class);
        $postGenerator->shouldReceive('generateForRowWithCluster')
            ->once()
            ->with(
                Mockery::on(fn ($p) => $p->id === $project->id),
                'Big launch',
                '1:1',
                Mockery::on(fn ($c) => $c->id === $cluster->id),
            )
            ->andReturn($postResult);

        $postGenerator->shouldNotReceive('generateForRow');

        $clusterSelector = Mockery::mock(ProjectClusterSelector::class);
        $clusterSelector->shouldReceive('imagesForCluster')
            ->once()
            ->andReturn(collect());
        $clusterSelector->shouldReceive('clusterMetadata')
            ->andReturn(['key' => 'product']);

        $builder = new CsvPostPromptBuilder($postGenerator, $clusterSelector);

        $batch = $builder->buildBatchFromMatches($project->fresh());

        $this->assertCount(1, $batch);
        $this->assertSame('product', $batch[0]['clusterKey']);
        $this->assertSame($history->id, $batch[0]['historyId']);

        $history->refresh();
        $this->assertSame('product', $history->cluster_key);
        $this->assertTrue($history->json_valid);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
