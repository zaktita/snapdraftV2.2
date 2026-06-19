<?php

namespace Tests\Feature\Test;

use App\Http\Middleware\EnsureUserHasCredits;
use App\Jobs\AnalyzeBrandJob;
use App\Jobs\DispatchGenerationBatchJob;
use App\Jobs\GeneratePostPromptsJob;
use App\Jobs\MatchCaptionsToClustersJob;
use App\Models\CsvWizardSession;
use App\Models\GenerationHistory;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PromptForgeTestLabTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->withoutMiddleware(EnsureUserHasCredits::class);
    }

    public function test_admin_can_access_prompt_forge_csv_wizard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('test.prompt-forge'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('projects/wizards/csv')
            ->where('wizardMode', 'prompt_forge_lab'));
    }

    public function test_non_admin_cannot_access_prompt_forge_index(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get(route('test.prompt-forge'));

        $response->assertForbidden();
    }

    public function test_admin_can_start_prompt_forge_csv_pipeline(): void
    {
        Bus::fake();

        $admin = User::factory()->create(['is_admin' => true]);

        $csvContent = "title,caption,format\nSpring Sale,Up to 50% off,instagram_square\n";

        $response = $this->actingAs($admin)->post(route('test.prompt-forge.store'), [
            'project_name' => 'Test Brand',
            'csv_file' => UploadedFile::fake()->createWithContent('data.csv', $csvContent),
            'column_mappings' => json_encode([
                'title' => 'Product Title',
                'caption' => 'Image Prompt',
                'format' => 'Format',
            ]),
            'reference_images' => [
                UploadedFile::fake()->image('ref1.jpg'),
                UploadedFile::fake()->image('ref2.jpg'),
                UploadedFile::fake()->image('ref3.jpg'),
            ],
            'resolution_multiplier' => 1,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'name' => 'Test Brand',
            'user_id' => $admin->id,
        ]);

        $project = Project::query()->where('name', 'Test Brand')->first();
        $this->assertNotNull($project);
        $this->assertSame('prompt_forge_lab', $project->settings['wizard_type'] ?? null);
        $this->assertCount(1, $project->settings['csv_data'] ?? []);

        $session = CsvWizardSession::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($session);

        $response->assertRedirect(route('test.prompt-forge.session', $session->id));

        Bus::assertChained([
            AnalyzeBrandJob::class,
            MatchCaptionsToClustersJob::class,
            GeneratePostPromptsJob::class,
            DispatchGenerationBatchJob::class,
        ]);
    }

    public function test_admin_can_view_prompt_forge_processing_session(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = Project::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Reload Brand',
            'settings' => [
                'wizard_type' => 'prompt_forge_lab',
                'csv_data' => [
                    ['title' => 'Row 1', 'caption' => 'Caption 1', 'format' => 'square'],
                ],
                'cluster_csv_pipeline' => ['phase' => 'matching'],
            ],
        ]);

        $session = CsvWizardSession::query()->create([
            'user_id' => $admin->id,
            'project_id' => $project->id,
            'status' => 'generating',
            'total_jobs' => 1,
        ]);

        GenerationHistory::query()->create([
            'user_id' => $admin->id,
            'project_id' => $project->id,
            'ai_model' => 'gemini-3.1-flash-image-preview',
            'prompt' => 'Caption 1',
            'status' => 'pending',
            'parameters' => ['caption' => 'Caption 1', 'csv_row_index' => 0],
        ]);

        $response = $this->actingAs($admin)->get(route('test.prompt-forge.session', $session->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('projects/wizards/csv-cluster-processing')
            ->where('session.id', $session->id)
            ->has('lab'));
    }

    public function test_prompt_forge_result_redirects_to_project_show(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = Project::factory()->create([
            'user_id' => $admin->id,
            'status' => 'completed',
            'settings' => [
                'wizard_type' => 'prompt_forge_lab',
                'cluster_csv_pipeline' => ['phase' => 'complete'],
            ],
        ]);

        $session = CsvWizardSession::query()->create([
            'user_id' => $admin->id,
            'project_id' => $project->id,
            'status' => 'completed',
            'total_jobs' => 3,
        ]);

        $response = $this->actingAs($admin)->get(route('test.prompt-forge.result', $session->id));

        $response->assertRedirect(route('projects.show', $project->id).'?justCreated=1&expectedImages=3');
    }
}
