<?php

namespace Tests\Feature;

use App\Models\AdminAction;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_non_admin_cannot_access_admin_pages(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_analytics_returns_ui_contract_props(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.analytics', ['period' => '30days']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/analytics')
                ->has('signup_trend')
                ->has('generation_trend')
                ->has('revenue_trend')
                ->has('top_users')
                ->has('summary.total_revenue')
                ->has('summary.period_generations')
                ->where('period', '30days')
            );
    }

    public function test_projects_page_includes_images_count(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        Project::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Brand Pack',
            'images_count' => 7,
            'format' => 'instagram',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.projects'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/projects')
                ->has('projects.data', 1)
                ->where('projects.data.0.images_count', 7)
                ->where('projects.data.0.name', 'Brand Pack')
            );
    }

    public function test_admin_cannot_suspend_self(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.users.suspend', $admin), ['reason' => 'test'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertFalse($admin->fresh()->is_suspended);
    }

    public function test_admin_cannot_change_own_admin_status(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'is_admin' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_cannot_demote_last_remaining_admin_via_peer(): void
    {
        $admin = $this->admin();
        $peer = User::factory()->admin()->create();

        // Demote peer first — allowed while two admins exist.
        $this->actingAs($admin)
            ->put(route('admin.users.update', $peer), [
                'name' => $peer->name,
                'email' => $peer->email,
                'is_admin' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($peer->fresh()->is_admin);

        // Peer (now non-admin) cannot access admin; admin cannot demote self (last admin).
        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'is_admin' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_suspend_writes_audit_row(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.users.suspend', $target), ['reason' => 'Abuse'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($target->fresh()->is_suspended);
        $this->assertDatabaseHas('admin_actions', [
            'admin_id' => $admin->id,
            'action' => 'user.suspend',
            'subject_id' => $target->id,
        ]);
        $this->assertSame(1, AdminAction::count());
    }

    public function test_failed_jobs_page_loads(): void
    {
        $admin = $this->admin();

        DB::table('failed_jobs')->insert([
            'uuid' => (string) str()->uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\GenerateSingleImageJob']),
            'exception' => "RuntimeException: boom\nstack",
            'failed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.failed-jobs'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/failed-jobs')
                ->where('stats.failed', 1)
                ->has('jobs.data', 1)
                ->where('jobs.data.0.display_name', 'GenerateSingleImageJob')
            );
    }

    public function test_audit_log_page_loads(): void
    {
        $admin = $this->admin();

        AdminAction::create([
            'admin_id' => $admin->id,
            'action' => 'credits.adjust',
            'metadata' => ['amount' => 5],
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.audit-log'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/audit-log')
                ->has('actions.data', 1)
                ->where('actions.data.0.action', 'credits.adjust')
            );
    }

    public function test_privilege_flags_are_not_mass_assignable(): void
    {
        $user = User::factory()->create();

        $user->update([
            'is_admin' => true,
            'is_suspended' => true,
            'name' => 'Safe Name',
        ]);

        $user->refresh();

        $this->assertFalse($user->is_admin);
        $this->assertFalse((bool) $user->is_suspended);
        $this->assertSame('Safe Name', $user->name);
    }
}
