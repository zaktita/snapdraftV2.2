<?php

namespace App\Services\Wizards;

use App\Models\Project;

class ClusterCsvPipeline
{
    public static function isClusterCsvWizard(Project $project): bool
    {
        return in_array(
            $project->settings['wizard_type'] ?? null,
            ['csv', 'csv_cluster', 'prompt_forge_lab'],
            true,
        );
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    public static function merge(Project $project, array $patch): void
    {
        if (! self::isClusterCsvWizard($project)) {
            return;
        }

        $pipeline = $project->settings['cluster_csv_pipeline'] ?? [];
        $project->update([
            'settings' => array_merge($project->settings ?? [], [
                'cluster_csv_pipeline' => array_merge($pipeline, $patch),
            ]),
        ]);
    }

    public static function setPhase(Project $project, string $phase): void
    {
        self::merge($project, ['phase' => $phase]);
    }

    public static function markFailed(Project $project, string $error): void
    {
        self::merge($project, [
            'phase' => 'failed',
            'error' => $error,
        ]);
    }
}
