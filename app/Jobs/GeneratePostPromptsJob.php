<?php

namespace App\Jobs;

use App\Models\CsvWizardSession;
use App\Models\Project;
use App\Services\Brand\CsvMasterPromptBuilder;
use App\Services\Brand\CsvPostPromptBuilder;
use App\Services\Wizards\ClusterCsvPipeline;
use App\Services\Wizards\CreativityLevel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeneratePostPromptsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 2;

    public function __construct(
        public readonly int $projectId,
        public readonly int $sessionId,
    ) {}

    public function handle(
        CsvPostPromptBuilder $builder,
        CsvMasterPromptBuilder $masterPromptBuilder,
    ): void {
        $project = Project::findOrFail($this->projectId);
        $session = CsvWizardSession::findOrFail($this->sessionId);

        try {
            if ($project->dna_json === null) {
                throw new \RuntimeException('dna_json missing — AnalyzeBrandJob may not have completed.');
            }

            ClusterCsvPipeline::setPhase($project, 'prompts');
            $project->refresh();

            $csvData = $project->settings['csv_data'] ?? [];
            if ($csvData === []) {
                throw new \RuntimeException('csv_data missing from project settings.');
            }

            $useMasterPrompt = CreativityLevel::isPromptForgeLab($project);

            Log::info('GeneratePostPromptsJob: building prompts', [
                'project_id' => $this->projectId,
                'row_count' => count($csvData),
                'pipeline' => $useMasterPrompt ? 'master_prompt_lab' : 'post_json',
            ]);

            $promptBatch = $useMasterPrompt
                ? $masterPromptBuilder->buildBatchFromMatches($project)
                : $builder->buildBatchFromMatches($project);

            $project->update([
                'settings' => array_merge($project->settings ?? [], [
                    'prompt_batch' => $promptBatch,
                    'cluster_csv_pipeline' => array_merge(
                        $project->settings['cluster_csv_pipeline'] ?? [],
                        [
                            'phase' => 'prompts_done',
                            'prompt_batch' => $promptBatch,
                            'prompt_pipeline' => $useMasterPrompt ? 'master_prompt_lab' : 'post_json',
                        ],
                    ),
                ]),
            ]);

            Log::info('GeneratePostPromptsJob: completed', [
                'project_id' => $this->projectId,
                'batch_size' => count($promptBatch),
                'pipeline' => $useMasterPrompt ? 'master_prompt_lab' : 'post_json',
            ]);
        } catch (\Throwable $e) {
            Log::error('GeneratePostPromptsJob: failed', [
                'project_id' => $this->projectId,
                'error' => $e->getMessage(),
            ]);
            ClusterCsvPipeline::markFailed($project, $e->getMessage());
            $session->markAsFailed('Post prompt generation failed: '.$e->getMessage());
            $project->generationHistory()
                ->whereIn('status', ['pending', 'processing'])
                ->update([
                    'status' => 'failed',
                    'error_message' => 'Pipeline aborted: post prompt generation failed.',
                ]);
            throw $e;
        }
    }
}
