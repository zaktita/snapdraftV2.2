<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesActiveSubscription;
use Tests\TestCase;

class PlanLimitMiddlewareTest extends TestCase
{
    use CreatesActiveSubscription;
    use RefreshDatabase;

    public function test_user_at_project_limit_cannot_create_via_csv_wizard(): void
    {
        $user = $this->createUserWithSubscription();
        $sub = $user->subscription();
        $caps = $sub->capabilities;
        $caps['max_projects'] = 1;
        $sub->update(['capabilities' => $caps]);

        Project::factory()->create(['user_id' => $user->id]);

        Storage::fake('local');
        $csv = UploadedFile::fake()->createWithContent('posts.csv', "title,description,format\nHello,World,square\n");

        $response = $this->actingAs($user)->post(route('projects.wizards.csv.store'), [
            'project_name' => 'Over limit',
            'csv_file' => $csv,
            'column_mappings' => json_encode([
                'title' => 'title',
                'description' => 'description',
                'format' => 'format',
            ]),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('maximum of 1 project', session('error'));
        $this->assertSame(1, $user->projects()->count());
    }

    public function test_csv_over_row_limit_is_rejected(): void
    {
        $user = $this->createUserWithSubscription();
        $sub = $user->subscription();
        $caps = $sub->capabilities;
        $caps['csv_max_rows'] = 2;
        $caps['max_projects'] = 10;
        $sub->update(['capabilities' => $caps]);

        $lines = ["title,description,format"];
        for ($i = 1; $i <= 5; $i++) {
            $lines[] = "Title {$i},Desc {$i},square";
        }
        $csv = UploadedFile::fake()->createWithContent('posts.csv', implode("\n", $lines)."\n");

        $response = $this->actingAs($user)->post(route('projects.wizards.csv.store'), [
            'project_name' => 'Too many rows',
            'csv_file' => $csv,
            'column_mappings' => json_encode([
                'title' => 'title',
                'description' => 'description',
                'format' => 'format',
            ]),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('5 rows', session('error'));
        $this->assertStringContainsString('up to 2 rows', session('error'));
        $this->assertSame(0, $user->projects()->count());
    }

    public function test_user_within_limits_can_submit_csv_wizard(): void
    {
        $user = $this->createUserWithSubscription();

        $csv = UploadedFile::fake()->createWithContent('posts.csv', "title,description,format\nHello,World,square\n");

        $response = $this->actingAs($user)->post(route('projects.wizards.csv.store'), [
            'project_name' => 'Allowed',
            'csv_file' => $csv,
            'column_mappings' => json_encode([
                'title' => 'title',
                'description' => 'description',
                'format' => 'format',
            ]),
        ]);

        // Should pass middleware; controller may redirect to session or project
        $response->assertSessionMissing('error');
        $this->assertFalse(
            str_contains((string) session('error'), 'maximum of')
            || str_contains((string) session('error'), 'allows up to')
        );
    }

    public function test_unverified_user_can_access_dashboard_while_verification_disabled(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }
}
