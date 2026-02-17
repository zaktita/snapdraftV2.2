<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Migrate existing user subscription data to subscriptions table.
     */
    public function up(): void
    {
        // Get all users with subscriptions (not free tier)
        $users = User::whereNotNull('subscription_tier')
            ->where('subscription_tier', '!=', 'free')
            ->get();

        foreach ($users as $user) {
            // Skip if user already has a subscription record
            if ($user->subscriptions()->exists()) {
                continue;
            }

            // Find the plan based on tier (will be seeded before this migration runs)
            $plan = Plan::where('slug', $user->subscription_tier)->first();
            
            if (!$plan) {
                // If plan not found, skip this user
                \Log::warning("Plan not found for user {$user->id} with tier {$user->subscription_tier}");
                continue;
            }

            // Get tier limits for capabilities
            $limits = SubscriptionService::getTierLimits($user->subscription_tier);
            
            // Calculate capabilities used
            $creditsUsed = ($user->credits_total ?? 0) - ($user->credits_remaining ?? 0);
            $projectsCount = $user->projects()->count();
            
            // Create capabilities JSON
            $capabilities = [
                'credits_used' => $creditsUsed,
                'credits_limit' => $limits['credits_per_month'],
                'credits_remaining' => $user->credits_remaining ?? 0,
                'projects_used' => $projectsCount,
                'projects_limit' => $limits['max_projects'],
                'csv_rows_limit' => $limits['csv_max_rows'],
                'team_seats_limit' => $limits['max_team_seats'],
                'features' => $limits['features'],
            ];

            // Determine status
            $status = 'active';
            if ($user->subscription_ends_at && $user->subscription_ends_at->isPast()) {
                $status = 'expired';
            }

            // Create subscription record
            Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'lemonsqueezy_id' => null, // No Lemon Squeezy data in old schema
                'lemonsqueezy_customer_id' => null,
                'lemonsqueezy_order_id' => null,
                'lemonsqueezy_variant_id' => null,
                'name' => $user->subscription_tier,
                'status' => $status,
                'starts_at' => $user->subscription_started_at ?? now(),
                'ends_at' => $user->subscription_ends_at,
                'billing_period' => 'monthly', // Default to monthly
                'amount_paid' => 0, // Historical data not available
                'price' => $plan->price,
                'currency' => $plan->currency,
                'provider' => 'lemonsqueezy',
                'provider_subscription_id' => null, // No provider data in old schema
                'provider_customer_id' => null,
                'cancelled_at' => null, // No cancellation data in old schema
                'auto_renew' => true, // Default to auto-renew
                'next_billing_at' => null,
                'renews_at' => null,
                'capabilities' => $capabilities,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove all subscriptions created by this migration
        // Use delete instead of truncate to avoid foreign key constraint issues
        DB::table('subscriptions')->delete();
    }
};
