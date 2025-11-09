<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_view_projects_index()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('projects.index'));

        $response->assertOk();
        $response->assertInertia(fn($page) => $page->component('projects/index'));
    }

    /** @test */
    public function guest_cannot_access_projects()
    {
        $response = $this->get(route('projects.index'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function user_can_create_project()
    {
        $user = User::factory()->create();

        $projectData = [
            'name' => 'Test Project',
            'description' => 'Test project description',
            'format' => 'square',
        ];

        $response = $this->actingAs($user)
            ->post(route('projects.store'), $projectData);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'name' => 'Test Project',
            'user_id' => $user->id,
            'format' => 'square',
        ]);
    }

    /** @test */
    public function project_name_is_required()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('projects.store'), [
                'description' => 'Test description',
                'format' => 'square',
            ]);

        $response->assertSessionHasErrors(['name']);
    }

    /** @test */
    public function user_can_update_own_project()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $updateData = [
            'name' => 'Updated Project Name',
            'description' => 'Updated description',
        ];

        $response = $this->actingAs($user)
            ->put(route('projects.update', $project), $updateData);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Project Name',
            'description' => 'Updated description',
        ]);
    }

    /** @test */
    public function user_cannot_update_others_project()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)
            ->put(route('projects.update', $project), [
                'name' => 'Hacked Project Name',
            ]);

        $response->assertForbidden();
    }

    /** @test */
    public function user_can_view_own_project()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get(route('projects.show', $project));

        $response->assertOk();
        $response->assertInertia(
            fn($page) =>
            $page->component('projects/show')
                ->has('project')
                ->where('project.id', $project->id)
        );
    }

    /** @test */
    public function user_cannot_view_others_project()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)
            ->get(route('projects.show', $project));

        $response->assertForbidden();
    }

    /** @test */
    public function user_can_delete_own_project()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->delete(route('projects.destroy', $project));

        $response->assertRedirect();
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    /** @test */
    public function user_cannot_delete_others_project()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)
            ->delete(route('projects.destroy', $project));

        $response->assertForbidden();
    }

    /** @test */
    public function user_can_toggle_favorite_on_own_project()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'is_favorite' => false,
        ]);

        $response = $this->actingAs($user)
            ->post(route('projects.toggle-favorite', $project));

        $response->assertOk();
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'is_favorite' => true,
        ]);
    }

    /** @test */
    public function projects_are_ordered_by_favorites_then_recent()
    {
        $user = User::factory()->create();

        $normalProject = Project::factory()->create([
            'user_id' => $user->id,
            'is_favorite' => false,
            'created_at' => now()->subDays(2),
        ]);

        $favoriteProject = Project::factory()->create([
            'user_id' => $user->id,
            'is_favorite' => true,
            'created_at' => now()->subDays(3),
        ]);

        $response = $this->actingAs($user)->get(route('projects.index'));

        $response->assertOk();
        $projects = $response->viewData('page')['props']['projects']['data'];

        $this->assertEquals($favoriteProject->id, $projects[0]['id']);
    }

    /** @test */
    public function user_can_search_projects()
    {
        $user = User::factory()->create();

        Project::factory()->create([
            'user_id' => $user->id,
            'name' => 'Summer Campaign',
        ]);

        Project::factory()->create([
            'user_id' => $user->id,
            'name' => 'Winter Campaign',
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.index', ['search' => 'Summer']));

        $response->assertOk();
        $projects = $response->viewData('page')['props']['projects']['data'];

        $this->assertCount(1, $projects);
        $this->assertEquals('Summer Campaign', $projects[0]['name']);
    }

    /** @test */
    public function user_can_filter_projects_by_format()
    {
        $user = User::factory()->create();

        Project::factory()->create([
            'user_id' => $user->id,
            'format' => 'square',
        ]);

        Project::factory()->create([
            'user_id' => $user->id,
            'format' => 'landscape',
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.index', ['format' => 'square']));

        $response->assertOk();
        $projects = $response->viewData('page')['props']['projects']['data'];

        $this->assertCount(1, $projects);
        $this->assertEquals('square', $projects[0]['format']);
    }

    /** @test */
    public function project_includes_images_count()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        // Create some images for the project (assuming Image model exists)
        // $project->images()->createMany([...]);

        $response = $this->actingAs($user)->get(route('projects.show', $project));

        $response->assertOk();
        $response->assertInertia(
            fn($page) =>
            $page->component('projects/show')
                ->has('project.images_count')
        );
    }
}
