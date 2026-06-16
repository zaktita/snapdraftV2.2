<?php

namespace Tests\Feature\Test;

use App\Http\Middleware\EnsureUserHasCredits;
use App\Models\CsvWizardSession;
use App\Models\GenerationHistory;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_admin_can_access_prompt_forge_index(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('test.prompt-forge'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('test/prompt-forge'));
    }

    public function test_non_admin_cannot_access_prompt_forge_index(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get(route('test.prompt-forge'));

        $response->assertForbidden();
    }

    public function test_admin_can_create_prompt_forge_session_with_csv_shape(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('test.prompt-forge.store'), [
            'brand_name' => 'Test Brand',
            'caption' => 'Summer workshop announcement',
            'format' => '1:1',
            'reference_images' => [
                UploadedFile::fake()->image('ref1.jpg'),
                UploadedFile::fake()->image('ref2.jpg'),
            ],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'name' => 'Test Brand',
            'user_id' => $admin->id,
        ]);

        $project = Project::query()->where('name', 'Test Brand')->first();
        $this->assertNotNull($project);
        $this->assertIsArray($project->settings['csv_data'] ?? null);
        $this->assertCount(1, $project->settings['csv_data']);

        $this->assertDatabaseHas('csv_wizard_sessions', [
            'project_id' => $project->id,
            'user_id' => $admin->id,
            'total_jobs' => 1,
        ]);

        $this->assertDatabaseHas('generation_history', [
            'project_id' => $project->id,
            'user_id' => $admin->id,
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_reload_existing_csv_wizard_session(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = Project::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Reload Brand',
            'settings' => [
                'prompt_forge_lab' => [
                    'pipeline_step' => 'setup_complete',
                    'caption' => 'Test caption',
                    'format' => '1:1',
                ],
            ],
        ]);

        $session = CsvWizardSession::query()->create([
            'user_id' => $admin->id,
            'project_id' => $project->id,
            'status' => 'pending',
            'total_jobs' => 1,
        ]);

        GenerationHistory::query()->create([
            'user_id' => $admin->id,
            'project_id' => $project->id,
            'ai_model' => 'gemini-3.1-flash-image-preview',
            'prompt' => 'Test caption',
            'status' => 'pending',
            'parameters' => ['caption' => 'Test caption'],
        ]);

        $response = $this->actingAs($admin)->get(route('test.prompt-forge', ['session' => $session->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('test/prompt-forge')
            ->where('session.id', $session->id)
            ->where('project.name', 'Reload Brand'));
    }
}
