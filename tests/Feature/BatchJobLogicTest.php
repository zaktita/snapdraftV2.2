<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchJobLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_job_logic_skips_empty_rows()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'settings' => [
                'csv_data' => [
                    ['title' => 'Valid Row', 'description' => 'Has content'],
                    ['title' => '', 'description' => ''], // Should be skipped
                    ['title' => '  ', 'description' => ''], // Should be skipped (trim)
                    ['title' => 'Another Valid', 'description' => 'Row'],
                ]
            ]
        ]);

        $csvData = $project->settings['csv_data'];
        $validJobs = [];

        foreach ($csvData as $row) {
            $title = trim($row['title'] ?? '');
            $description = trim($row['description'] ?? '');
            
            if (!empty($title) || !empty($description)) {
                $validJobs[] = $row;
            }
        }

        $this->assertCount(2, $validJobs);
        $this->assertEquals('Valid Row', $validJobs[0]['title']);
        $this->assertEquals('Another Valid', $validJobs[1]['title']);
    }
}
