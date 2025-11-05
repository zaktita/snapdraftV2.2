<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestBatchJobLogic extends Command
{
    protected $signature = 'test:batch-job-logic {project}';
    protected $description = 'Test batch job logic to verify empty row filtering';

    public function handle()
    {
        $projectId = $this->argument('project');
        $project = Project::find($projectId);

        if (!$project) {
            $this->error("Project not found: {$projectId}");
            return 1;
        }

        $csvData = $project->settings['csv_data'] ?? [];
        
        $this->info("CSV data from project settings: " . count($csvData) . " rows");

        // Simulate the job logic
        $jobs = [];
        $jobIndex = 0;
        foreach ($csvData as $row) {
            // Skip rows with empty title AND description
            $title = trim($row['title'] ?? '');
            $description = trim($row['description'] ?? '');
            
            if (empty($title) && empty($description)) {
                $this->warn("Skipping empty row");
                continue;
            }
            
            $this->info("Would create job #{$jobIndex} for: {$title}");
            $jobIndex++;
        }

        $this->info("\nTotal jobs that would be created: {$jobIndex}");

        return 0;
    }
}
