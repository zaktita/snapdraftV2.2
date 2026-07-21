<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesActiveSubscription;
use Tests\TestCase;

class SubscriptionAccessTest extends TestCase
{
    use CreatesActiveSubscription;
    use RefreshDatabase;

    public function test_cancelled_subscription_within_period_still_entitled(): void
    {
        $user = $this->createUserWithSubscription();
        $sub = $user->subscription();
        $sub->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'ends_at' => now()->addDays(20),
            'provider' => 'lemonsqueezy',
            'lemonsqueezy_id' => 'ls-sub-1',
        ]);

        $user = $user->fresh();

        $this->assertTrue($user->hasActiveSubscription());
        $this->assertTrue($user->subscription()->isOnGracePeriod());
        $this->assertFalse($user->isSubscriptionReadOnly());
    }

    public function test_expired_user_can_view_projects_but_not_create(): void
    {
        $user = $this->createUserWithSubscription();
        Project::factory()->create(['user_id' => $user->id]);

        $user->subscription()->update([
            'status' => 'expired',
            'ends_at' => now()->subDay(),
            'cancelled_at' => now()->subDays(30),
        ]);

        $user = $user->fresh();
        $this->assertFalse($user->hasActiveSubscription());
        $this->assertTrue($user->isSubscriptionReadOnly());

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('projects.create'))
            ->assertRedirect(route('subscription.plans'));
    }

    public function test_upgrade_with_existing_ls_subscription_redirects_to_portal(): void
    {
        $user = $this->createUserWithSubscription();
        $user->subscription()->update([
            'provider' => 'lemonsqueezy',
            'lemonsqueezy_id' => 'ls-sub-99',
            'customer_portal_url' => 'https://example.lemonsqueezy.com/billing',
            'metadata' => [
                'customer_portal_update_subscription' => 'https://example.lemonsqueezy.com/billing/update',
            ],
        ]);

        $this->actingAs($user)
            ->post(route('subscription.upgrade'), [
                'tier' => 'pro',
                'billing_period' => 'monthly',
            ])
            ->assertRedirect('https://example.lemonsqueezy.com/billing/update');
    }
}
