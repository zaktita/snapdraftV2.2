<?php

namespace Tests\Feature\Wizards;

use App\Http\Middleware\EnsureUserHasCredits;
use App\Models\CsvWizardSession;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClusterCsvWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->withoutMiddleware(EnsureUserHasCredits::class);
        Bus::fake();
    }

    public function test_authenticated_user_can_access_csv_cluster_wizard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('projects.wizards.csv-cluster'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('projects/wizards/csv-cluster'));
    }

    public function test_guest_cannot_access_csv_cluster_wizard(): void
    {
        $response = $this->get(route('projects.wizards.csv-cluster'));

        $response->assertRedirect(route('login'));
    }

    public function test_store_requires_at_least_two_reference_images(): void
    {
        $user = User::factory()->create();

        $csvContent = "title,caption,format\nProduct Launch,Amazing product,square\n";
        $csvFile = UploadedFile::fake()->createWithContent('data.csv', $csvContent);

        $response = $this->actingAs($user)->post(route('projects.wizards.csv-cluster.store'), [
            'project_name' => 'Test Project',
            'csv_file' => $csvFile,
            'column_mappings' => json_encode(['title' => 'Product Title', 'caption' => 'Image Prompt']),
            'reference_images' => [
                UploadedFile::fake()->image('ref1.jpg'),
            ],
        ]);

        $response->assertSessionHasErrors('reference_images');
    }

    public function test_store_creates_csv_cluster_project_and_dispatches_chain(): void
    {
        $user = $this->userWithCredits(10);

        $csvContent = "title,caption,format\n";
        $csvContent .= "Product Launch,Amazing new product,square\n";
        $csvContent .= "Summer Sale,Hot deals,landscape\n";
        $csvFile = UploadedFile::fake()->createWithContent('data.csv', $csvContent);

        $response = $this->actingAs($user)->post(route('projects.wizards.csv-cluster.store'), [
            'project_name' => 'Cluster CSV Test',
            'csv_file' => $csvFile,
            'column_mappings' => json_encode([
                'title' => 'Product Title',
                'caption' => 'Image Prompt',
                'format' => 'Format',
            ]),
            'reference_images' => [
                UploadedFile::fake()->image('ref1.jpg'),
                UploadedFile::fake()->image('ref2.jpg'),
            ],
            'resolution_multiplier' => 1,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'name' => 'Cluster CSV Test',
            'user_id' => $user->id,
        ]);

        $project = Project::query()->where('name', 'Cluster CSV Test')->first();
        $this->assertNotNull($project);
        $this->assertSame('csv_cluster', $project->settings['wizard_type']);
        $this->assertCount(2, $project->settings['csv_data']);

        $this->assertDatabaseHas('csv_wizard_sessions', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'total_jobs' => 2,
        ]);

        Bus::assertChained([
            \App\Jobs\AnalyzeBrandJob::class,
            \App\Jobs\MatchCaptionsToClustersJob::class,
            \App\Jobs\GeneratePostPromptsJob::class,
            \App\Jobs\DispatchGenerationBatchJob::class,
        ]);
    }

    public function test_status_endpoint_returns_pipeline_payload(): void
    {
        $user = User::factory()->create();

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'settings' => [
                'wizard_type' => 'csv_cluster',
                'csv_data' => [
                    ['title' => 'A', 'caption' => 'A', 'format' => 'square'],
                ],
                'history_ids' => [],
                'cluster_csv_pipeline' => [
                    'phase' => 'matching',
                    'row_matches' => [],
                ],
            ],
        ]);

        $session = CsvWizardSession::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'status' => 'analyzing',
            'total_jobs' => 1,
        ]);

        $response = $this->actingAs($user)->getJson(route('projects.wizards.csv-cluster.status', $session));

        $response->assertOk();
        $response->assertJsonPath('pipeline.phase', 'matching');
        $response->assertJsonPath('session.id', $session->id);
    }

    public function test_row_debug_endpoint_returns_prompt_and_cluster_payload(): void
    {
        $user = User::factory()->create();

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'settings' => [
                'wizard_type' => 'csv_cluster',
                'csv_data' => [
                    ['title' => 'Launch', 'caption' => 'Big launch', 'format' => 'square'],
                ],
                'history_ids' => [],
                'cluster_csv_pipeline' => [
                    'row_matches' => [
                        0 => [
                            'cluster_key' => 'product',
                            'cluster_label' => 'Product',
                            'scores' => ['product' => 5],
                        ],
                    ],
                ],
            ],
        ]);

        $history = \App\Models\GenerationHistory::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'ai_model' => 'test-model',
            'prompt' => 'Launch',
            'status' => 'completed',
            'cluster_key' => 'product',
            'prompt_json' => ['post' => ['concept' => 'Launch concept']],
            'compiled_prompt' => 'Compiled text',
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

        \App\Models\ProjectCluster::create([
            'project_id' => $project->id,
            'key' => 'product',
            'label' => 'Product',
            'position' => 0,
        ]);

        $session = CsvWizardSession::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'status' => 'completed',
            'total_jobs' => 1,
        ]);

        $response = $this->actingAs($user)->getJson(
            route('projects.wizards.csv-cluster.row-debug', ['session' => $session->id, 'rowIndex' => 0])
        );

        $response->assertOk();
        $response->assertJsonPath('available', true);
        $response->assertJsonPath('cluster.key', 'product');
        $response->assertJsonPath('prompt_json.post.concept', 'Launch concept');
        $response->assertJsonPath('compiled_prompt', 'Compiled text');
        $response->assertJsonPath('image_request_prompt', 'Generate launch image');
    }

    private function userWithCredits(int $credits): User
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
                'credits_remaining' => $credits,
                'credits_used' => 0,
                'credits_limit' => $credits,
            ],
        ]);

        return $user;
    }
}
