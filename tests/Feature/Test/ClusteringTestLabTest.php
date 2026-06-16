<?php

namespace Tests\Feature\Test;

use App\Http\Middleware\EnsureUserHasCredits;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClusteringTestLabTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->withoutMiddleware(EnsureUserHasCredits::class);
    }

    public function test_admin_can_access_clustering_test_index(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('test.clustering'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('test/clustering'));
    }

    public function test_non_admin_cannot_access_clustering_test_index(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get(route('test.clustering'));

        $response->assertForbidden();
    }

    public function test_analyze_requires_at_least_two_images(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('test.clustering.analyze'), [
            'brand_name' => 'Test Brand',
            'reference_images' => [
                UploadedFile::fake()->image('ref1.jpg'),
            ],
        ]);

        $response->assertSessionHasErrors('reference_images');
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_admin_can_view_clustering_results_for_own_project(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $project = Project::factory()->create([
            'user_id' => $admin->id,
            'settings' => ['type' => 'clustering_test_lab'],
        ]);

        $response = $this->actingAs($admin)->get(route('test.clustering', ['project' => $project->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('test/clustering')
            ->where('project.id', $project->id));
    }

    public function test_match_caption_requires_valid_caption(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $project = Project::factory()->create([
            'user_id' => $admin->id,
            'settings' => ['type' => 'clustering_test_lab'],
        ]);

        $response = $this->actingAs($admin)->post(route('test.clustering.match', $project), [
            'caption' => 'ab',
        ]);

        $response->assertSessionHasErrors('caption');
    }

    public function test_match_caption_fails_without_clusters(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $project = Project::factory()->create([
            'user_id' => $admin->id,
            'settings' => ['type' => 'clustering_test_lab'],
        ]);

        $response = $this->actingAs($admin)->post(route('test.clustering.match', $project), [
            'caption' => 'Summer workshop registration is open',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
    }

    public function test_generate_prompt_requires_dna(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $project = Project::factory()->create([
            'user_id' => $admin->id,
            'settings' => ['type' => 'clustering_test_lab'],
            'dna_json' => null,
        ]);

        $response = $this->actingAs($admin)->post(route('test.clustering.generate-prompt', $project), [
            'caption' => 'Summer workshop registration is open',
            'format' => '1:1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
    }

    public function test_generate_image_requires_prompt_generation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $project = Project::factory()->create([
            'user_id' => $admin->id,
            'settings' => [
                'type' => 'clustering_test_lab',
                'clustering_test_lab' => [],
            ],
            'dna_json' => ['brand' => ['name' => 'Test']],
        ]);

        $response = $this->actingAs($admin)->post(route('test.clustering.generate-image', $project), [
            'resolution_multiplier' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
    }
}
