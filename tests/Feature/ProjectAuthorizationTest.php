<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesActiveSubscription;
use Tests\TestCase;

class ProjectAuthorizationTest extends TestCase
{
    use CreatesActiveSubscription;
    use RefreshDatabase;

    /** @test */
    public function user_can_only_see_own_projects()
    {
        $user = $this->createUserWithSubscription();
        $otherUser = $this->createUserWithSubscription();

        $userProject = Project::factory()->create(['user_id' => $user->id]);
        $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get(route('projects.index'));

        $response->assertOk();
        $projects = $response->viewData('page')['props']['projects']['data'];
        
        $projectIds = collect($projects)->pluck('id')->toArray();
        
        $this->assertContains($userProject->id, $projectIds);
        $this->assertNotContains($otherProject->id, $projectIds);
    }

    /** @test */
    public function user_cannot_view_others_project_details()
    {
        $owner = $this->createUserWithSubscription();
        $otherUser = $this->createUserWithSubscription();
        $project = Project::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)
            ->get(route('projects.show', $project));

        $response->assertForbidden();
    }

    /** @test */
    public function user_can_view_own_project_details()
    {
        $user = $this->createUserWithSubscription();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get(route('projects.show', $project));

        $response->assertOk();
    }

    /** @test */
    public function admin_can_view_all_projects()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = $this->createUserWithSubscription();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($admin)
            ->get(route('admin.projects'));

        $response->assertOk();
    }

    /** @test */
    public function regular_user_cannot_access_admin_projects()
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)
            ->get(route('admin.projects'));

        $response->assertForbidden();
    }
}
