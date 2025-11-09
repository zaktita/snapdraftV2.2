<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditsMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_credits_can_access_generation_endpoint(): void
    {
        $user = User::factory()->create([
            'credits_remaining' => 10,
        ]);

        $project = Project::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->post("/projects/{$project->id}/generate", [
            'count' => 1,
        ]);

        // The endpoint might fail for other reasons (validation, no images, etc.)
        // but it should not fail with the "no credits" error message
        if ($response->getSession()->has('error')) {
            $errorMessage = $response->getSession()->get('error');
            $this->assertStringNotContainsString('no credits remaining', $errorMessage);
        }
    }

    public function test_user_without_credits_cannot_access_generation_endpoint(): void
    {
        $user = User::factory()->create([
            'credits_remaining' => 0,
        ]);

        $project = Project::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->post("/projects/{$project->id}/generate", [
            'count' => 1,
        ]);

        // Should redirect back with error
        $response->assertRedirect();
        $response->assertSessionHas('error', 'You have no credits remaining. Please upgrade your plan or purchase additional credits.');
    }

    public function test_guest_cannot_access_generation_endpoint(): void
    {
        $project = Project::factory()->create();

        $response = $this->post("/projects/{$project->id}/generate", [
            'count' => 1,
        ]);

        // Should redirect to login
        $response->assertRedirect('/login');
    }
}
