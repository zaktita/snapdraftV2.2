<?php

namespace Tests\Feature;

use App\Models\GenerationHistory;
use App\Models\Image;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectCluster;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectImageGenerationDebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_cluster_project_image_debug_endpoint(): void
    {
        $user = User::factory()->create();

        $plan = Plan::create([
            'name' => 'Test Plan',
            'slug' => 'test-'.uniqid(),
            'price' => 0,
            'billing_cycle' => 'monthly',
            'is_active' => true,
        ]);

        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'billing_period' => 'monthly',
            'amount_paid' => 0,
            'capabilities' => [
                'credits_remaining' => 10,
                'credits_used' => 0,
                'credits_limit' => 10,
            ],
        ]);

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'settings' => [
                'wizard_type' => 'csv_cluster',
                'history_ids' => [],
            ],
        ]);

        $image = Image::create([
            'project_id' => $project->id,
            'url' => 'projects/1/images/test.png',
            'thumbnail_url' => 'projects/1/images/test_thumb.png',
            'prompt' => 'test',
            'metadata' => ['csv_row_index' => 0],
        ]);

        $history = GenerationHistory::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'image_id' => $image->id,
            'ai_model' => 'test-model',
            'prompt' => 'Launch',
            'status' => 'completed',
            'cluster_key' => 'product',
            'prompt_json' => ['post' => ['concept' => 'Launch concept']],
            'compiled_prompt' => 'Compiled text',
            'parameters' => ['csv_row_index' => 0],
            'request_data' => [
                'image_generation' => [
                    'cluster_key' => 'product',
                    'image_request_prompt' => 'Generate launch image',
                ],
            ],
        ]);

        $project->update([
            'settings' => array_merge($project->settings, [
                'history_ids' => [0 => $history->id],
            ]),
        ]);

        ProjectCluster::create([
            'project_id' => $project->id,
            'key' => 'product',
            'label' => 'Product',
            'position' => 0,
        ]);

        $response = $this->actingAs($user)->getJson(
            route('projects.images.generation-debug', ['id' => $project->id, 'imageId' => $image->id])
        );

        $response->assertOk();
        $response->assertJsonPath('available', true);
        $response->assertJsonPath('cluster.key', 'product');
        $response->assertJsonPath('prompt_json.post.concept', 'Launch concept');
    }
}
