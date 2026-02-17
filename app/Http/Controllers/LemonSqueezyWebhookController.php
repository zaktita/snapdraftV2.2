<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LemonSqueezyWebhookController extends Controller
{
    /**
     * Handle Lemon Squeezy webhook events.
     */
    public function handle(Request $request): JsonResponse
    {
        // Verify webhook signature
        $signingSecret = config('services.lemonsqueezy.webhook_secret') 
            ?? config('services.lemonsqueezy.signing_secret');
        
        if (empty($signingSecret)) {
            Log::error('Lemon Squeezy webhook: signing secret not configured');
            return response()->json(['status' => 'error', 'message' => 'Webhook not configured'], 500);
        }
        
        $signature = $request->header('X-Signature');
        $payload = $request->getContent();
        
        if (empty($signature) || empty($payload)) {
            Log::warning('Lemon Squeezy webhook: missing signature or body', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['status' => 'error', 'message' => 'Invalid request'], 401);
        }

        // Verify signature
        $computed = hash_hmac('sha256', $payload, $signingSecret);
        if (!hash_equals($computed, $signature)) {
            Log::warning('Lemon Squeezy webhook: signature verification failed', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        $data = $request->json()->all();
        $eventName = $data['meta']['event_name'] ?? null;

        Log::info('Lemon Squeezy webhook received', [
            'event' => $eventName,
            'webhook_id' => $data['meta']['webhook_id'] ?? null,
        ]);

        try {
            match ($eventName) {
                'subscription_created' => $this->handleSubscriptionCreated($data),
                'subscription_updated' => $this->handleSubscriptionUpdated($data),
                'subscription_cancelled' => $this->handleSubscriptionCancelled($data),
                'subscription_resumed' => $this->handleSubscriptionResumed($data),
                'subscription_expired' => $this->handleSubscriptionExpired($data),
                'subscription_paused' => $this->handleSubscriptionPaused($data),
                'subscription_unpaused' => $this->handleSubscriptionUnpaused($data),
                'subscription_payment_success' => $this->handleSubscriptionPaymentSuccess($data),
                'subscription_payment_failed' => $this->handleSubscriptionPaymentFailed($data),
                'subscription_payment_recovered' => $this->handleSubscriptionPaymentRecovered($data),
                default => $this->handleUnknownEvent($eventName, $data),
            };

            return response()->json(['status' => 'success'], 200);
        } catch (\Exception $e) {
            Log::error('Lemon Squeezy webhook processing error', [
                'event' => $eventName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle subscription created event.
     */
    protected function handleSubscriptionCreated(array $data): void
    {
        $attributes = $data['data']['attributes'];
        $customData = $data['meta']['custom_data'] ?? [];
        
        $userId = $customData['user_id'] ?? null;
        $subscriptionId = $attributes['identifier'] ?? null;
        
        if (!$userId || !$subscriptionId) {
            Log::warning('Subscription created: missing user_id or subscription_id', [
                'custom_data' => $customData,
                'identifier' => $subscriptionId,
            ]);
            return;
        }

        // Check if already processed
        if (Subscription::where('lemonsqueezy_id', $subscriptionId)->exists()) {
            Log::info('Subscription already exists', ['subscription_id' => $subscriptionId]);
            return;
        }

        $user = User::find($userId);
        if (!$user) {
            Log::error('User not found for subscription', ['user_id' => $userId]);
            return;
        }

        // Determine tier from custom data or variant ID
        $tier = $customData['tier'] ?? $this->getTierFromVariantId($attributes['variant_id']);
        $billingPeriod = $customData['billing_period'] ?? 'monthly';
        
        // Get plan from database
        $plan = \App\Models\Plan::where('slug', $tier)->first();
        if (!$plan) {
            Log::error('Plan not found for tier', ['tier' => $tier]);
            return;
        }
        
        // Get tier limits from plan capabilities
        $planCapabilities = $plan->capabilities ?? [];
        
        // Initialize capabilities with full limits and zero usage
        $capabilities = [
            'credits_used' => 0,
            'credits_limit' => $planCapabilities['credits_per_month'] ?? 0,
            'credits_remaining' => $planCapabilities['credits_per_month'] ?? 0,
            'projects_used' => 0,
            'projects_limit' => $planCapabilities['max_projects'] ?? 0,
            'csv_rows_limit' => $planCapabilities['csv_max_rows'] ?? 0,
            'team_seats_limit' => $planCapabilities['max_team_seats'] ?? 1,
            'features' => $planCapabilities['features'] ?? [],
        ];

        // Create subscription record
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'lemonsqueezy_id' => $subscriptionId,
            'lemonsqueezy_customer_id' => $attributes['customer_id'],
            'lemonsqueezy_order_id' => $attributes['order_id'] ?? null,
            'lemonsqueezy_variant_id' => $attributes['variant_id'],
            'lemonsqueezy_product_id' => $attributes['product_id'],
            'name' => $tier,
            'status' => $attributes['status'],
            'starts_at' => now(),
            'billing_period' => $billingPeriod,
            'price' => $plan->price,
            'currency' => $plan->currency,
            'provider' => 'lemonsqueezy',
            'provider_subscription_id' => $subscriptionId,
            'provider_customer_id' => $attributes['customer_id'],
            'trial_ends_at' => isset($attributes['trial_ends_at']) ? \Carbon\Carbon::parse($attributes['trial_ends_at']) : null,
            'renews_at' => isset($attributes['renews_at']) ? \Carbon\Carbon::parse($attributes['renews_at']) : null,
            'ends_at' => isset($attributes['ends_at']) ? \Carbon\Carbon::parse($attributes['ends_at']) : null,
            'card_brand' => $attributes['card_brand'] ?? null,
            'card_last_four' => $attributes['card_last_four'] ?? null,
            'update_payment_url' => $attributes['urls']['update_payment_method'] ?? null,
            'customer_portal_url' => $attributes['urls']['customer_portal'] ?? null,
            'capabilities' => $capabilities,
            'metadata' => $attributes,
        ]);

        Log::info('Subscription created successfully', [
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'tier' => $tier,
            'credits' => $capabilities['credits_limit'],
        ]);
    }

    /**
     * Handle subscription updated event.
     */
    protected function handleSubscriptionUpdated(array $data): void
    {
        $attributes = $data['data']['attributes'];
        $subscriptionId = $attributes['identifier'];

        $subscription = Subscription::where('lemonsqueezy_id', $subscriptionId)->first();
        if (!$subscription) {
            Log::warning('Subscription not found for update', ['subscription_id' => $subscriptionId]);
            return;
        }

        // Check if tier changed
        $newTier = $this->getTierFromVariantId($attributes['variant_id']);
        $tierChanged = $subscription->name !== $newTier;
        
        $updateData = [
            'lemonsqueezy_variant_id' => $attributes['variant_id'],
            'status' => $attributes['status'],
            'renews_at' => isset($attributes['renews_at']) ? \Carbon\Carbon::parse($attributes['renews_at']) : null,
            'ends_at' => isset($attributes['ends_at']) ? \Carbon\Carbon::parse($attributes['ends_at']) : null,
            'card_brand' => $attributes['card_brand'] ?? $subscription->card_brand,
            'card_last_four' => $attributes['card_last_four'] ?? $subscription->card_last_four,
            'update_payment_url' => $attributes['urls']['update_payment_method'] ?? $subscription->update_payment_url,
            'customer_portal_url' => $attributes['urls']['customer_portal'] ?? $subscription->customer_portal_url,
            'metadata' => $attributes,
        ];

        // If tier changed, update plan and reset capabilities
        if ($tierChanged) {
            $newPlan = \App\Models\Plan::where('slug', $newTier)->first();
            if ($newPlan) {
                $planCapabilities = $newPlan->capabilities ?? [];
                
                // Reset capabilities for new tier, preserving current usage
                $currentCapabilities = $subscription->capabilities ?? [];
                $creditsUsed = $currentCapabilities['credits_used'] ?? 0;
                
                $updateData['plan_id'] = $newPlan->id;
                $updateData['name'] = $newTier;
                $updateData['price'] = $newPlan->price;
                $updateData['capabilities'] = [
                    'credits_used' => $creditsUsed,
                    'credits_limit' => $planCapabilities['credits_per_month'] ?? 0,
                    'credits_remaining' => max(0, ($planCapabilities['credits_per_month'] ?? 0) - $creditsUsed),
                    'projects_used' => $subscription->user->projects()->count(),
                    'projects_limit' => $planCapabilities['max_projects'] ?? 0,
                    'csv_rows_limit' => $planCapabilities['csv_max_rows'] ?? 0,
                    'team_seats_limit' => $planCapabilities['max_team_seats'] ?? 1,
                    'features' => $planCapabilities['features'] ?? [],
                ];

                Log::info('Subscription tier changed', [
                    'subscription_id' => $subscription->id,
                    'old_tier' => $subscription->name,
                    'new_tier' => $newTier,
                ]);
            }
        }
        
        $subscription->update($updateData);
        Log::info('Subscription updated', ['subscription_id' => $subscription->id]);
    }

    /**
     * Handle subscription cancelled event.
     */
    protected function handleSubscriptionCancelled(array $data): void
    {
        $attributes = $data['data']['attributes'];
        $subscriptionId = $attributes['identifier'];

        $subscription = Subscription::where('lemonsqueezy_id', $subscriptionId)->first();
        if (!$subscription) {
            return;
        }

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'ends_at' => isset($attributes['ends_at']) ? \Carbon\Carbon::parse($attributes['ends_at']) : now(),
        ]);

        Log::info('Subscription cancelled', [
            'subscription_id' => $subscription->id,
            'ends_at' => $subscription->ends_at,
        ]);
    }

    /**
     * Handle subscription resumed event.
     */
    protected function handleSubscriptionResumed(array $data): void
    {
        $attributes = $data['data']['attributes'];
        $subscriptionId = $attributes['identifier'];

        $subscription = Subscription::where('lemonsqueezy_id', $subscriptionId)->first();
        if (!$subscription) {
            return;
        }

        $subscription->update([
            'status' => 'active',
            'cancelled_at' => null,
            'paused_at' => null,
            'renews_at' => isset($attributes['renews_at']) ? \Carbon\Carbon::parse($attributes['renews_at']) : null,
            'ends_at' => null,
        ]);

        $subscription->user->update([
            'subscription_ends_at' => $subscription->renews_at,
        ]);

        Log::info('Subscription resumed', ['subscription_id' => $subscription->id]);
    }

    /**
     * Handle subscription expired event.
     */
    protected function handleSubscriptionExpired(array $data): void
    {
        $attributes = $data['data']['attributes'];
        $subscriptionId = $attributes['identifier'];

        $subscription = Subscription::where('lemonsqueezy_id', $subscriptionId)->first();
        if (!$subscription) {
            return;
        }

        $subscription->update([
            'status' => 'expired',
            'ends_at' => now(),
        ]);

        // Downgrade user to free tier
        $freeLimits = SubscriptionService::getTierLimits('free');
        $subscription->user->update([
            'subscription_tier' => 'free',
            'credits_remaining' => $freeLimits['credits_per_month'],
            'credits_total' => $freeLimits['credits_per_month'],
            'subscription_ends_at' => now(),
        ]);

        Log::info('Subscription expired, user downgraded to free', [
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
        ]);
    }

    /**
     * Handle subscription paused event.
     */
    protected function handleSubscriptionPaused(array $data): void
    {
        $attributes = $data['data']['attributes'];
        $subscriptionId = $attributes['identifier'];

        $subscription = Subscription::where('lemonsqueezy_id', $subscriptionId)->first();
        if (!$subscription) {
            return;
        }

        $subscription->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);

        Log::info('Subscription paused', ['subscription_id' => $subscription->id]);
    }

    /**
     * Handle subscription unpaused event.
     */
    protected function handleSubscriptionUnpaused(array $data): void
    {
        $attributes = $data['data']['attributes'];
        $subscriptionId = $attributes['identifier'];

        $subscription = Subscription::where('lemonsqueezy_id', $subscriptionId)->first();
        if (!$subscription) {
            return;
        }

        $subscription->update([
            'status' => 'active',
            'paused_at' => null,
        ]);

        Log::info('Subscription unpaused', ['subscription_id' => $subscription->id]);
    }

    /**
     * Handle successful subscription payment (renewal).
     */
    protected function handleSubscriptionPaymentSuccess(array $data): void
    {
        $attributes = $data['data']['attributes'];
        $subscriptionId = $attributes['subscription_id'] ?? null;

        if (!$subscriptionId) {
            return;
        }

        $subscription = Subscription::where('lemonsqueezy_id', $subscriptionId)->first();
        if (!$subscription) {
            return;
        }

        // Reset credits for new billing period
        $subscription->resetMonthlyCredits();

        $subscription->update([
            'status' => 'active',
        ]);

        Log::info('Subscription payment successful, credits reset', [
            'subscription_id' => $subscription->id,
            'credits' => $subscription->creditsLimit(),
        ]);
    }

    /**
     * Handle failed subscription payment.
     */
    protected function handleSubscriptionPaymentFailed(array $data): void
    {
        $attributes = $data['data']['attributes'];
        $subscriptionId = $attributes['subscription_id'] ?? null;

        if (!$subscriptionId) {
            return;
        }

        $subscription = Subscription::where('lemonsqueezy_id', $subscriptionId)->first();
        if (!$subscription) {
            return;
        }

        $subscription->update([
            'status' => 'past_due',
        ]);

        Log::warning('Subscription payment failed', [
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
        ]);

        // TODO: Send email notification to user
    }

    /**
     * Handle recovered subscription payment.
     */
    protected function handleSubscriptionPaymentRecovered(array $data): void
    {
        $attributes = $data['data']['attributes'];
        $subscriptionId = $attributes['subscription_id'] ?? null;

        if (!$subscriptionId) {
            return;
        }

        $subscription = Subscription::where('lemonsqueezy_id', $subscriptionId)->first();
if (!$subscription) {
            return;
        }

        // Reset credits after payment recovered
        $subscription->resetMonthlyCredits();

        $subscription->update([
            'status' => 'active',
        ]);

        Log::info('Subscription payment recovered', ['subscription_id' => $subscription->id]);

        // TODO: Send email notification to user
    }

    /**
     * Handle unknown webhook events.
     */
    protected function handleUnknownEvent(?string $eventName, array $data): void
    {
        Log::info('Lemon Squeezy webhook: unhandled event', [
            'event' => $eventName,
            'data' => $data,
        ]);
    }

    /**
     * Get tier name from variant ID.
     * Checks both database plans and config for variant matching.
     */
    protected function getTierFromVariantId($variantId): string
    {
        // First, try to find from database plans
        $plan = \App\Models\Plan::where('provider_variant_monthly', $variantId)
            ->orWhere('provider_variant_yearly', $variantId)
            ->first();
        
        if ($plan) {
            return $plan->slug;
        }
        
        // Fallback to config-based lookup
        $variants = config('services.lemonsqueezy.variants', []);
        
        foreach ($variants as $key => $id) {
            if ((string) $id === (string) $variantId) {
                // Key format: "launch_monthly" -> return "launch"
                return explode('_', $key)[0];
            }
        }

        Log::warning('Unknown variant ID', ['variant_id' => $variantId]);
        return 'launch'; // Default fallback
    }
}
