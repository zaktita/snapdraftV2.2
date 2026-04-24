<?php

namespace Tests\Feature;

use App\Enums\BetaApplicationStatus;
use App\Mail\BetaInviteCodeMail;
use App\Models\BetaApplication;
use App\Models\BetaInvite;
use App\Models\User;
use App\Notifications\NewBetaApplicationNotification;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BetaApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_guest_can_view_beta_apply_page(): void
    {
        $this->get('/beta/apply')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('website/beta-apply'));
    }

    public function test_authenticated_user_is_redirected_from_beta_apply_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/beta/apply')
            ->assertRedirect(route('dashboard'));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'applicant@example.com',
            'role' => 'founder',
            'monthly_post_volume' => '6-20',
            'visual_workflow' => 'I use Canva for everything.',
        ], $overrides);
    }

    public function test_beta_apply_creates_pending_application(): void
    {
        config(['snapdraft.beta_application_notify_email' => 'ops@example.com']);
        Notification::fake();

        $response = $this->postJson('/beta/apply', $this->validPayload());

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('beta_applications', [
            'email' => 'applicant@example.com',
            'role' => 'founder',
            'monthly_post_volume' => '6-20',
            'status' => 'pending',
        ]);

        Notification::assertSentOnDemand(
            NewBetaApplicationNotification::class,
        );
    }

    public function test_beta_apply_skips_notification_when_notify_email_empty(): void
    {
        config(['snapdraft.beta_application_notify_email' => '']);
        Notification::fake();

        $this->postJson('/beta/apply', $this->validPayload())->assertOk();

        Notification::assertNothingSent();
    }

    public function test_beta_apply_rejects_duplicate_pending_email(): void
    {
        BetaApplication::factory()->create([
            'email' => 'dup@example.com',
            'status' => BetaApplicationStatus::Pending,
        ]);

        $response = $this->postJson('/beta/apply', $this->validPayload([
            'email' => 'dup@example.com',
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertEquals(1, BetaApplication::where('email', 'dup@example.com')->count());
    }

    public function test_admin_can_approve_and_creates_invite_and_mail(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $app = BetaApplication::factory()->create([
            'email' => 'winner@example.com',
            'status' => BetaApplicationStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->post("/admin/beta-applications/{$app->id}/approve")
            ->assertRedirect();

        $app->refresh();
        $this->assertSame(BetaApplicationStatus::Approved, $app->status);
        $this->assertNotNull($app->invite_code);
        $this->assertNotNull($app->beta_invite_id);

        $this->assertDatabaseHas('beta_invites', [
            'code' => $app->invite_code,
        ]);

        Mail::assertSent(BetaInviteCodeMail::class, function (BetaInviteCodeMail $mail) use ($app) {
            return $mail->hasTo($app->email) && strlen($mail->code) === 8;
        });
    }

    public function test_admin_can_reject_pending_application(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $app = BetaApplication::factory()->create([
            'status' => BetaApplicationStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->post("/admin/beta-applications/{$app->id}/reject")
            ->assertRedirect();

        $app->refresh();
        $this->assertSame(BetaApplicationStatus::Rejected, $app->status);
    }

    public function test_admin_cannot_approve_non_pending(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $app = BetaApplication::factory()->create([
            'status' => BetaApplicationStatus::Approved,
        ]);

        $before = BetaInvite::count();

        $this->actingAs($admin)
            ->post("/admin/beta-applications/{$app->id}/approve")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertEquals($before, BetaInvite::count());
    }
}
