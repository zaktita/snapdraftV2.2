<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LemonSqueezyController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('🍋 Lemon Squeezy webhook received', [
            'event' => $request->input('meta.event_name'),
            'payload' => $request->all(),
        ]);

        // Verify webhook signature
        if (!$this->verifySignature($request)) {
            Log::warning('❌ Invalid Lemon Squeezy webhook signature');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $eventName = $request->input('meta.event_name');
        $data = $request->input('data');

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
        
        // Skip signature verification if no secret is configured (development mode)
        if (empty($secret)) {
            Log::warning('⚠️ Webhook secret not configured - skipping signature verification');
            return true;
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
            'user_email' => $data['attributes']['user_email'] ?? null,
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

        // Find plan by tier slug or variant ID
        $tier = $customData['tier'] ?? 'beta';
        $plan = Plan::where('slug', $tier)->first();
        
        if (!$plan) {
            Log::error('❌ Plan not found', ['tier' => $tier]);
            return;
        }

        Log::info('✅ Creating subscription', [
            'user_id' => $userId,
            'subscription_id' => $data['id'],
            'plan_id' => $plan->id,
        ]);

        Subscription::create([
            'user_id' => $userId,
            'plan_id' => $plan->id,
            'lemonsqueezy_id' => $data['id'],
            'lemonsqueezy_customer_id' => $attributes['customer_id'],
            'lemonsqueezy_order_id' => $attributes['order_id'],
            'lemonsqueezy_product_id' => $attributes['product_id'],
            'lemonsqueezy_variant_id' => $attributes['variant_id'],
            'status' => $attributes['status'],
            'billing_period' => $customData['billing_period'] ?? 'monthly',
            'starts_at' => $attributes['created_at'] ? now()->parse($attributes['created_at']) : now(),
            'trial_ends_at' => $attributes['trial_ends_at'] ? now()->parse($attributes['trial_ends_at']) : null,
            'renews_at' => $attributes['renews_at'] ? now()->parse($attributes['renews_at']) : null,
            'ends_at' => $attributes['ends_at'] ? now()->parse($attributes['ends_at']) : null,
            'price' => ($attributes['first_subscription_item']['price'] ?? 0) / 100, // Convert cents to dollars
            'amount_paid' => ($attributes['first_subscription_item']['price'] ?? 0) / 100,
            'currency' => strtoupper($attributes['first_subscription_item']['currency'] ?? 'EUR'),
            'customer_portal_url' => $attributes['urls']['customer_portal'] ?? null,
            'update_payment_url' => $attributes['urls']['update_payment_method'] ?? null,
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
            'renews_at' => $attributes['renews_at'] ? now()->parse($attributes['renews_at']) : null,
            'ends_at' => $attributes['ends_at'] ? now()->parse($attributes['ends_at']) : null,
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
            'ends_at' => $attributes['ends_at'] ? now()->parse($attributes['ends_at']) : now(),
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
            'renews_at' => $attributes['renews_at'] ? now()->parse($attributes['renews_at']) : null,
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
            'renews_at' => $attributes['renews_at'] ? now()->parse($attributes['renews_at']) : null,
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
            'renews_at' => $attributes['renews_at'] ? now()->parse($attributes['renews_at']) : null,
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
            'renews_at' => $attributes['renews_at'] ? now()->parse($attributes['renews_at']) : null,
        ]);

        Log::info('✅ Subscription payment recovered', ['subscription_id' => $subscription->id]);
    }
}
