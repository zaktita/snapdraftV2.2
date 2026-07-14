<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PostHogService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LemonSqueezyController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('🍋 Lemon Squeezy webhook received', [
            'event' => $request->input('meta.event_name'),
        ]);

        // Verify webhook signature
        if (!$this->verifySignature($request)) {
            Log::warning('❌ Invalid Lemon Squeezy webhook signature');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $eventName = $request->input('meta.event_name');
        $data      = $request->input('data');

        if (empty($eventName) || empty($data) || !is_array($data)) {
            Log::warning('❌ Malformed Lemon Squeezy webhook payload');
            return response()->json(['message' => 'Invalid payload'], 422);
        }

        try {
            switch ($eventName) {
                case 'order_created':
                    $this->handleOrderCreated($data);
                    break;
                case 'subscription_created':
                    $this->handleSubscriptionCreated($data);
                    break;
                case 'subscription_updated':
                    $this->handleSubscriptionUpdated($data);
                    break;
                case 'subscription_cancelled':
                    $this->handleSubscriptionCancelled($data);
                    break;
                case 'subscription_resumed':
                    $this->handleSubscriptionResumed($data);
                    break;
                case 'subscription_expired':
                    $this->handleSubscriptionExpired($data);
                    break;
                case 'subscription_paused':
                    $this->handleSubscriptionPaused($data);
                    break;
                case 'subscription_unpaused':
                    $this->handleSubscriptionUnpaused($data);
                    break;
                case 'subscription_payment_success':
                    $this->handleSubscriptionPaymentSuccess($data);
                    break;
                case 'subscription_payment_failed':
                    $this->handleSubscriptionPaymentFailed($data);
                    break;
                case 'subscription_payment_recovered':
                    $this->handleSubscriptionPaymentRecovered($data);
                    break;
                default:
                    Log::info('ℹ️ Unhandled Lemon Squeezy event', ['event' => $eventName]);
            }

            return response()->json(['message' => 'Webhook processed successfully']);
        } catch (\Exception $e) {
            Log::error('❌ Webhook processing error', [
                'event' => $eventName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Verify webhook signature using HMAC.
     */
    protected function verifySignature(Request $request): bool
    {
        $secret = config('services.lemonsqueezy.webhook_secret');
        
        // Reject webhooks if no secret is configured — never skip verification
        if (empty($secret)) {
            Log::error('❌ Webhook secret not configured - rejecting webhook for security');
            return false;
        }

        $signature = $request->header('X-Signature');
        if (!$signature) {
            return false;
        }

        $payload = $request->getContent();
        $hash = hash_hmac('sha256', $payload, $secret);

        return hash_equals($hash, $signature);
    }

    /**
     * Handle order created event.
     */
    protected function handleOrderCreated(array $data): void
    {
        Log::info('📦 Processing order_created', [
            'order_id' => $data['id'],
        ]);

        // Orders are typically one-time purchases
        // For subscriptions, we'll handle subscription_created instead
    }

    /**
     * Handle subscription created event.
     */
    protected function handleSubscriptionCreated(array $data): void
    {
        $attributes = $data['attributes'];
        $customData = $attributes['first_subscription_item']['subscription_custom_data'] ?? [];
        
        $userId = $customData['user_id'] ?? null;
        if (!$userId) {
            Log::error('❌ No user_id in subscription custom data');
            return;
        }

        $user = User::find($userId);
        if (!$user) {
            Log::error('❌ User not found', ['user_id' => $userId]);
            return;
        }

        // Resolve plan by Lemon variant ID first (source of truth), then custom tier slug
        $variantId = (string) ($attributes['variant_id'] ?? '');
        $tier = $customData['tier'] ?? null;

        $plan = null;
        if ($variantId !== '') {
            $plan = Plan::query()
                ->where(function ($query) use ($variantId) {
                    $query->where('provider_variant_monthly', $variantId)
                        ->orWhere('provider_variant_yearly', $variantId);
                })
                ->first();
        }
        if (! $plan && $tier) {
            $plan = Plan::where('slug', $tier)->first();
        }

        if (! $plan) {
            Log::error('❌ Plan not found for subscription webhook', [
                'variant_id' => $variantId,
                'tier' => $tier,
            ]);
            return;
        }

        // Idempotent: ignore duplicate delivery of the same Lemon subscription id
        $existing = Subscription::where('lemonsqueezy_id', $data['id'])->first();
        if ($existing) {
            Log::info('Subscription already exists for Lemon id — skipping create', [
                'lemonsqueezy_id' => $data['id'],
                'subscription_id' => $existing->id,
            ]);
            return;
        }

        Log::info('✅ Creating subscription', [
            'user_id' => $userId,
            'subscription_id' => $data['id'],
            'plan_id' => $plan->id,
            'plan_slug' => $plan->slug,
        ]);

        $billingPeriod = $customData['billing_period'] ?? 'monthly';
        $price         = ($attributes['first_subscription_item']['price'] ?? 0) / 100;
        $currency      = strtoupper($attributes['first_subscription_item']['currency'] ?? 'EUR');

        Subscription::create([
            'user_id' => $userId,
            'plan_id' => $plan->id,
            'lemonsqueezy_id' => $data['id'],
            'lemonsqueezy_customer_id' => $attributes['customer_id'],
            'lemonsqueezy_order_id' => $attributes['order_id'],
            'lemonsqueezy_product_id' => $attributes['product_id'],
            'lemonsqueezy_variant_id' => $attributes['variant_id'],
            'status' => $attributes['status'],
            'billing_period' => $billingPeriod,
            'starts_at' => $attributes['created_at'] ? Carbon::parse($attributes['created_at']) : now(),
            'trial_ends_at' => $attributes['trial_ends_at'] ? Carbon::parse($attributes['trial_ends_at']) : null,
            'renews_at' => $attributes['renews_at'] ? Carbon::parse($attributes['renews_at']) : null,
            'ends_at' => $attributes['ends_at'] ? Carbon::parse($attributes['ends_at']) : null,
            'price' => $price,
            'amount_paid' => $price,
            'currency' => $currency,
            'customer_portal_url' => $attributes['urls']['customer_portal'] ?? null,
            'update_payment_url' => $attributes['urls']['update_payment_method'] ?? null,
        ]);

        app(PostHogService::class)->capture((string) $userId, 'subscription_created', [
            'plan'           => $plan->slug,
            'billing_period' => $billingPeriod,
            'price'          => $price,
            'currency'       => $currency,
        ]);

        Log::info('🎉 Subscription created successfully', ['user_id' => $userId]);
    }

    /**
     * Handle subscription updated event.
     */
    protected function handleSubscriptionUpdated(array $data): void
    {
        $subscription = Subscription::where('lemonsqueezy_id', $data['id'])->first();
        
        if (!$subscription) {
            Log::warning('⚠️ Subscription not found for update', ['subscription_id' => $data['id']]);
            return;
        }

        $attributes = $data['attributes'];
        
        $subscription->update([
            'status' => $attributes['status'],
            'renews_at' => $attributes['renews_at'] ? Carbon::parse($attributes['renews_at']) : null,
            'ends_at' => $attributes['ends_at'] ? Carbon::parse($attributes['ends_at']) : null,
            'price' => $attributes['first_subscription_item']['price'] ?? $subscription->price,
        ]);

        Log::info('🔄 Subscription updated', [
            'subscription_id' => $subscription->id,
            'status' => $attributes['status'],
        ]);
    }

    /**
     * Handle subscription cancelled event.
     */
    protected function handleSubscriptionCancelled(array $data): void
    {
        $subscription = Subscription::where('lemonsqueezy_id', $data['id'])->first();
        
        if (!$subscription) {
            Log::warning('⚠️ Subscription not found for cancellation', ['subscription_id' => $data['id']]);
            return;
        }

        $attributes = $data['attributes'];
        
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'ends_at' => $attributes['ends_at'] ? Carbon::parse($attributes['ends_at']) : now(),
        ]);

        app(PostHogService::class)->capture((string) $subscription->user_id, 'subscription_cancelled', [
            'plan'            => $subscription->plan?->slug,
            'billing_period'  => $subscription->billing_period,
        ]);

        Log::info('❌ Subscription cancelled', ['subscription_id' => $subscription->id]);
    }

    /**
     * Handle subscription resumed event.
     */
    protected function handleSubscriptionResumed(array $data): void
    {
        $subscription = Subscription::where('lemonsqueezy_id', $data['id'])->first();
        
        if (!$subscription) {
            Log::warning('⚠️ Subscription not found for resume', ['subscription_id' => $data['id']]);
            return;
        }

        $attributes = $data['attributes'];
        
        $subscription->update([
            'status' => 'active',
            'cancelled_at' => null,
            'renews_at' => $attributes['renews_at'] ? Carbon::parse($attributes['renews_at']) : null,
        ]);

        Log::info('▶️ Subscription resumed', ['subscription_id' => $subscription->id]);
    }

    /**
     * Handle subscription expired event.
     */
    protected function handleSubscriptionExpired(array $data): void
    {
        $subscription = Subscription::where('lemonsqueezy_id', $data['id'])->first();
        
        if (!$subscription) {
            Log::warning('⚠️ Subscription not found for expiration', ['subscription_id' => $data['id']]);
            return;
        }

        $subscription->update([
            'status' => 'expired',
            'ends_at' => now(),
        ]);

        Log::info('⏱️ Subscription expired', ['subscription_id' => $subscription->id]);
    }

    /**
     * Handle subscription paused event.
     */
    protected function handleSubscriptionPaused(array $data): void
    {
        $subscription = Subscription::where('lemonsqueezy_id', $data['id'])->first();
        
        if (!$subscription) {
            Log::warning('⚠️ Subscription not found for pause', ['subscription_id' => $data['id']]);
            return;
        }

        $subscription->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);

        Log::info('⏸️ Subscription paused', ['subscription_id' => $subscription->id]);
    }

    /**
     * Handle subscription unpaused event.
     */
    protected function handleSubscriptionUnpaused(array $data): void
    {
        $subscription = Subscription::where('lemonsqueezy_id', $data['id'])->first();
        
        if (!$subscription) {
            Log::warning('⚠️ Subscription not found for unpause', ['subscription_id' => $data['id']]);
            return;
        }

        $attributes = $data['attributes'];
        
        $subscription->update([
            'status' => 'active',
            'paused_at' => null,
            'renews_at' => $attributes['renews_at'] ? Carbon::parse($attributes['renews_at']) : null,
        ]);

        Log::info('▶️ Subscription unpaused', ['subscription_id' => $subscription->id]);
    }

    /**
     * Handle subscription payment success event.
     */
    protected function handleSubscriptionPaymentSuccess(array $data): void
    {
        $subscription = Subscription::where('lemonsqueezy_id', $data['id'])->first();
        
        if (!$subscription) {
            Log::warning('⚠️ Subscription not found for payment success', ['subscription_id' => $data['id']]);
            return;
        }

        $attributes = $data['attributes'];
        
        $subscription->update([
            'status' => 'active',
            'renews_at' => $attributes['renews_at'] ? Carbon::parse($attributes['renews_at']) : null,
        ]);

        Log::info('💳 Subscription payment successful', ['subscription_id' => $subscription->id]);
    }

    /**
     * Handle subscription payment failed event.
     */
    protected function handleSubscriptionPaymentFailed(array $data): void
    {
        $subscription = Subscription::where('lemonsqueezy_id', $data['id'])->first();
        
        if (!$subscription) {
            Log::warning('⚠️ Subscription not found for payment failure', ['subscription_id' => $data['id']]);
            return;
        }

        $subscription->update([
            'status' => 'past_due',
        ]);

        app(PostHogService::class)->capture((string) $subscription->user_id, 'subscription_payment_failed', [
            'plan'           => $subscription->plan?->slug,
            'billing_period' => $subscription->billing_period,
        ]);

        Log::warning('⚠️ Subscription payment failed', ['subscription_id' => $subscription->id]);
    }

    /**
     * Handle subscription payment recovered event.
     */
    protected function handleSubscriptionPaymentRecovered(array $data): void
    {
        $subscription = Subscription::where('lemonsqueezy_id', $data['id'])->first();
        
        if (!$subscription) {
            Log::warning('⚠️ Subscription not found for payment recovery', ['subscription_id' => $data['id']]);
            return;
        }

        $attributes = $data['attributes'];
        
        $subscription->update([
            'status' => 'active',
            'renews_at' => $attributes['renews_at'] ? Carbon::parse($attributes['renews_at']) : null,
        ]);

        Log::info('✅ Subscription payment recovered', ['subscription_id' => $subscription->id]);
    }
}
