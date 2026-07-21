<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PostHogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LemonSqueezyController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('Lemon Squeezy webhook received', [
            'event' => $request->input('meta.event_name'),
        ]);

        if (! $this->verifySignature($request)) {
            Log::warning('Invalid Lemon Squeezy webhook signature');

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $eventName = $request->input('meta.event_name');
        $data = $request->input('data');

        if (empty($eventName) || empty($data) || ! is_array($data)) {
            Log::warning('Malformed Lemon Squeezy webhook payload');

            return response()->json(['message' => 'Invalid payload'], 422);
        }

        try {
            switch ($eventName) {
                case 'order_created':
                    $this->handleOrderCreated($data);
                    break;
                case 'subscription_created':
                    $this->handleSubscriptionCreated($request, $data);
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
                    Log::info('Unhandled Lemon Squeezy event', ['event' => $eventName]);
            }

            return response()->json(['message' => 'Webhook processed successfully']);
        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'event' => $eventName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Webhook processing failed'], 500);
        }
    }

    protected function verifySignature(Request $request): bool
    {
        $secret = config('services.lemonsqueezy.webhook_secret');

        if (empty($secret)) {
            Log::error('Webhook secret not configured - rejecting webhook');

            return false;
        }

        $signature = $request->header('X-Signature');
        if (! $signature) {
            return false;
        }

        $payload = $request->getContent();
        $hash = hash_hmac('sha256', $payload, $secret);

        return hash_equals($hash, $signature);
    }

    /**
     * Lemon Squeezy puts checkout custom fields on meta.custom_data.
     * Some payloads also nest them under attributes / first_subscription_item.
     *
     * @return array<string, mixed>
     */
    protected function extractCustomData(Request $request, array $data): array
    {
        $candidates = [
            $data['attributes']['first_subscription_item']['subscription_custom_data'] ?? null,
            $data['attributes']['custom_data'] ?? null,
            $request->input('meta.custom_data'),
        ];

        $merged = [];
        foreach ($candidates as $chunk) {
            if (is_array($chunk)) {
                $merged = array_merge($merged, $chunk);
            }
        }

        return $merged;
    }

    /**
     * Resolve subscription for subscription events vs invoice events.
     */
    protected function findSubscription(array $data): ?Subscription
    {
        $type = $data['type'] ?? '';

        if ($type === 'subscription-invoices') {
            $subscriptionId = $data['attributes']['subscription_id'] ?? null;
            if ($subscriptionId !== null && $subscriptionId !== '') {
                return Subscription::where('lemonsqueezy_id', (string) $subscriptionId)->first();
            }
        }

        return Subscription::where('lemonsqueezy_id', (string) ($data['id'] ?? ''))->first();
    }

    /**
     * Build capabilities from plan (credits + limits) for a new paid subscription.
     *
     * @return array<string, mixed>
     */
    protected function capabilitiesFromPlan(Plan $plan): array
    {
        $planCapabilities = $plan->capabilities ?? [];
        $credits = (int) data_get(
            $planCapabilities,
            'credits_per_month',
            data_get($planCapabilities, 'credits_limit', 0)
        );

        return array_merge($planCapabilities, [
            'credits_remaining' => $credits,
            'credits_limit' => $credits,
            'credits_used' => 0,
            'credits_per_month' => $credits,
        ]);
    }

    protected function handleOrderCreated(array $data): void
    {
        Log::info('Processing order_created', [
            'order_id' => $data['id'] ?? null,
        ]);
        // One-time purchases / credit packs — not used yet.
    }

    protected function handleSubscriptionCreated(Request $request, array $data): void
    {
        $attributes = $data['attributes'] ?? [];
        $customData = $this->extractCustomData($request, $data);

        $userId = $customData['user_id'] ?? null;
        if (! $userId) {
            Log::error('No user_id in subscription custom data', [
                'lemon_subscription_id' => $data['id'] ?? null,
                'custom_keys' => array_keys($customData),
            ]);

            return;
        }

        $user = User::find($userId);
        if (! $user) {
            Log::error('User not found for subscription webhook', ['user_id' => $userId]);

            return;
        }

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
            Log::error('Plan not found for subscription webhook', [
                'variant_id' => $variantId,
                'tier' => $tier,
            ]);

            return;
        }

        $existing = Subscription::where('lemonsqueezy_id', $data['id'])->first();
        if ($existing) {
            Log::info('Subscription already exists for Lemon id - skipping create', [
                'lemonsqueezy_id' => $data['id'],
                'subscription_id' => $existing->id,
            ]);

            return;
        }

        // Cancel any leftover invite/comps so the paid sub is the active one
        $user->subscriptions()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('lemonsqueezy_id')
                    ->orWhere('provider', 'invite_code')
                    ->orWhere('provider', 'test');
            })
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'ends_at' => now(),
            ]);

        $billingPeriod = $customData['billing_period'] ?? 'monthly';
        $price = ($attributes['first_subscription_item']['price'] ?? 0) / 100;
        $currency = strtoupper($attributes['first_subscription_item']['currency'] ?? 'EUR');

        Subscription::create([
            'user_id' => $userId,
            'plan_id' => $plan->id,
            'provider' => 'lemonsqueezy',
            'lemonsqueezy_id' => $data['id'],
            'lemonsqueezy_customer_id' => $attributes['customer_id'] ?? null,
            'lemonsqueezy_order_id' => $attributes['order_id'] ?? null,
            'lemonsqueezy_product_id' => $attributes['product_id'] ?? null,
            'lemonsqueezy_variant_id' => $attributes['variant_id'] ?? null,
            'status' => $attributes['status'] ?? 'active',
            'billing_period' => $billingPeriod,
            'starts_at' => ! empty($attributes['created_at']) ? Carbon::parse($attributes['created_at']) : now(),
            'trial_ends_at' => ! empty($attributes['trial_ends_at']) ? Carbon::parse($attributes['trial_ends_at']) : null,
            'renews_at' => ! empty($attributes['renews_at']) ? Carbon::parse($attributes['renews_at']) : null,
            'ends_at' => ! empty($attributes['ends_at']) ? Carbon::parse($attributes['ends_at']) : null,
            'price' => $price,
            'amount_paid' => $price,
            'currency' => $currency,
            'customer_portal_url' => $attributes['urls']['customer_portal'] ?? null,
            'update_payment_url' => $attributes['urls']['update_payment_method'] ?? null,
            'metadata' => array_filter([
                'customer_portal_update_subscription' => $attributes['urls']['customer_portal_update_subscription'] ?? null,
            ]),
            'capabilities' => $this->capabilitiesFromPlan($plan),
        ]);

        app(PostHogService::class)->capture((string) $userId, 'subscription_created', [
            'plan' => $plan->slug,
            'billing_period' => $billingPeriod,
            'price' => $price,
            'currency' => $currency,
        ]);

        Log::info('Subscription created successfully', [
            'user_id' => $userId,
            'plan' => $plan->slug,
            'credits' => data_get($plan->capabilities, 'credits_per_month'),
        ]);
    }

    protected function handleSubscriptionUpdated(array $data): void
    {
        $subscription = $this->findSubscription($data);

        if (! $subscription) {
            Log::warning('Subscription not found for update', ['id' => $data['id'] ?? null]);

            return;
        }

        $attributes = $data['attributes'] ?? [];

        $subscription->update([
            'status' => $attributes['status'] ?? $subscription->status,
            'renews_at' => ! empty($attributes['renews_at']) ? Carbon::parse($attributes['renews_at']) : null,
            'ends_at' => array_key_exists('ends_at', $attributes)
                ? (! empty($attributes['ends_at']) ? Carbon::parse($attributes['ends_at']) : null)
                : $subscription->ends_at,
            'cancelled_at' => ! empty($attributes['cancelled'])
                ? ($subscription->cancelled_at ?? now())
                : null,
            'price' => isset($attributes['first_subscription_item']['price'])
                ? ($attributes['first_subscription_item']['price'] / 100)
                : $subscription->price,
            'customer_portal_url' => $attributes['urls']['customer_portal'] ?? $subscription->customer_portal_url,
            'update_payment_url' => $attributes['urls']['update_payment_method'] ?? $subscription->update_payment_url,
            'metadata' => array_merge($subscription->metadata ?? [], array_filter([
                'customer_portal_update_subscription' => $attributes['urls']['customer_portal_update_subscription'] ?? null,
            ])),
        ]);

        Log::info('Subscription updated', [
            'subscription_id' => $subscription->id,
            'status' => $attributes['status'] ?? null,
        ]);
    }

    protected function handleSubscriptionCancelled(array $data): void
    {
        $subscription = $this->findSubscription($data);

        if (! $subscription) {
            Log::warning('Subscription not found for cancellation', ['id' => $data['id'] ?? null]);

            return;
        }

        $attributes = $data['attributes'] ?? [];
        $endsAt = ! empty($attributes['ends_at'])
            ? Carbon::parse($attributes['ends_at'])
            : ($subscription->renews_at ?? now());

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'ends_at' => $endsAt,
            'customer_portal_url' => $attributes['urls']['customer_portal'] ?? $subscription->customer_portal_url,
            'update_payment_url' => $attributes['urls']['update_payment_method'] ?? $subscription->update_payment_url,
        ]);

        app(PostHogService::class)->capture((string) $subscription->user_id, 'subscription_cancelled', [
            'plan' => $subscription->plan?->slug,
            'billing_period' => $subscription->billing_period,
        ]);

        Log::info('Subscription cancelled', ['subscription_id' => $subscription->id]);
    }

    protected function handleSubscriptionResumed(array $data): void
    {
        $subscription = $this->findSubscription($data);

        if (! $subscription) {
            Log::warning('Subscription not found for resume', ['id' => $data['id'] ?? null]);

            return;
        }

        $attributes = $data['attributes'] ?? [];

        $subscription->update([
            'status' => 'active',
            'cancelled_at' => null,
            'ends_at' => null,
            'renews_at' => ! empty($attributes['renews_at']) ? Carbon::parse($attributes['renews_at']) : null,
            'customer_portal_url' => $attributes['urls']['customer_portal'] ?? $subscription->customer_portal_url,
            'update_payment_url' => $attributes['urls']['update_payment_method'] ?? $subscription->update_payment_url,
        ]);

        Log::info('Subscription resumed', ['subscription_id' => $subscription->id]);
    }

    protected function handleSubscriptionExpired(array $data): void
    {
        $subscription = $this->findSubscription($data);

        if (! $subscription) {
            Log::warning('Subscription not found for expiration', ['id' => $data['id'] ?? null]);

            return;
        }

        $subscription->update([
            'status' => 'expired',
            'ends_at' => ! empty($data['attributes']['ends_at'])
                ? Carbon::parse($data['attributes']['ends_at'])
                : now(),
        ]);

        Log::info('Subscription expired', ['subscription_id' => $subscription->id]);
    }

    protected function handleSubscriptionPaused(array $data): void
    {
        $subscription = $this->findSubscription($data);

        if (! $subscription) {
            Log::warning('Subscription not found for pause', ['id' => $data['id'] ?? null]);

            return;
        }

        $subscription->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);

        Log::info('Subscription paused', ['subscription_id' => $subscription->id]);
    }

    protected function handleSubscriptionUnpaused(array $data): void
    {
        $subscription = $this->findSubscription($data);

        if (! $subscription) {
            Log::warning('Subscription not found for unpause', ['id' => $data['id'] ?? null]);

            return;
        }

        $attributes = $data['attributes'] ?? [];

        $subscription->update([
            'status' => 'active',
            'paused_at' => null,
            'renews_at' => ! empty($attributes['renews_at']) ? Carbon::parse($attributes['renews_at']) : null,
        ]);

        Log::info('Subscription unpaused', ['subscription_id' => $subscription->id]);
    }

    protected function handleSubscriptionPaymentSuccess(array $data): void
    {
        $subscription = $this->findSubscription($data);

        if (! $subscription) {
            Log::warning('Subscription not found for payment success', [
                'invoice_id' => $data['id'] ?? null,
                'subscription_id' => $data['attributes']['subscription_id'] ?? null,
            ]);

            return;
        }

        $attributes = $data['attributes'] ?? [];
        $invoiceId = (string) ($data['id'] ?? '');
        $metadata = $subscription->metadata ?? [];

        // Idempotent: ignore duplicate delivery of the same invoice
        if ($invoiceId !== '' && ($metadata['last_payment_invoice_id'] ?? null) === $invoiceId) {
            Log::info('Duplicate payment webhook ignored', ['invoice_id' => $invoiceId]);

            return;
        }

        $subscription->update([
            'status' => 'active',
            'cancelled_at' => null,
            'ends_at' => null,
            'renews_at' => ! empty($attributes['renews_at'])
                ? Carbon::parse($attributes['renews_at'])
                : $subscription->renews_at,
            'metadata' => array_merge($metadata, [
                'last_payment_invoice_id' => $invoiceId,
                'last_payment_at' => now()->toIso8601String(),
                'last_billing_reason' => $attributes['billing_reason'] ?? null,
            ]),
        ]);

        $this->syncInvoiceFromSubscriptionPayment($subscription, $data);

        // Renewals refill monthly credits. Initial payment is handled on subscription_created.
        if (($attributes['billing_reason'] ?? null) === 'renewal') {
            $subscription->resetMonthlyCredits();
        }

        Log::info('Subscription payment successful', [
            'subscription_id' => $subscription->id,
            'billing_reason' => $attributes['billing_reason'] ?? null,
        ]);
    }

    /**
     * Persist Lemon Squeezy subscription invoice into local invoices table.
     */
    protected function syncInvoiceFromSubscriptionPayment(Subscription $subscription, array $data): void
    {
        $attributes = $data['attributes'] ?? [];
        $invoiceId = (string) ($data['id'] ?? '');

        if ($invoiceId === '') {
            return;
        }

        if (Invoice::where('lemonsqueezy_invoice_id', $invoiceId)->exists()) {
            return;
        }

        $user = $subscription->user;
        $subtotal = ((int) ($attributes['subtotal'] ?? 0)) / 100;
        $tax = ((int) ($attributes['tax'] ?? 0)) / 100;
        $total = ((int) ($attributes['total'] ?? $attributes['subtotal'] ?? 0)) / 100;
        $currency = strtoupper($attributes['currency'] ?? $subscription->currency ?? 'EUR');
        $issuedAt = ! empty($attributes['created_at'])
            ? Carbon::parse($attributes['created_at'])
            : now();
        $planName = $subscription->plan?->name ?? 'Subscription';
        $billingReason = $attributes['billing_reason'] ?? 'subscription';

        Invoice::create([
            'user_id' => $subscription->user_id,
            'invoice_number' => 'LS-'.$invoiceId,
            'lemonsqueezy_invoice_id' => $invoiceId,
            'billing_name' => $attributes['user_name'] ?? $user?->name ?? 'Customer',
            'billing_email' => $attributes['user_email'] ?? $user?->email ?? '',
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total' => $total,
            'currency' => $currency,
            'status' => ($attributes['status'] ?? 'paid') === 'paid' ? 'paid' : ($attributes['status'] ?? 'paid'),
            'items' => [[
                'description' => "{$planName} ({$billingReason})",
                'quantity' => 1,
                'price' => $subtotal,
                'total' => $subtotal,
            ]],
            'meta' => [
                'lemonsqueezy_invoice_id' => $invoiceId,
                'subscription_id' => $attributes['subscription_id'] ?? $subscription->lemonsqueezy_id,
                'billing_reason' => $billingReason,
                'invoice_url' => $attributes['urls']['invoice_url'] ?? null,
                'card_brand' => $attributes['card_brand'] ?? null,
                'card_last_four' => $attributes['card_last_four'] ?? null,
            ],
            'issued_at' => $issuedAt,
            'due_at' => $issuedAt,
            'paid_at' => ($attributes['status'] ?? 'paid') === 'paid' ? $issuedAt : null,
        ]);
    }

    protected function handleSubscriptionPaymentFailed(array $data): void
    {
        $subscription = $this->findSubscription($data);

        if (! $subscription) {
            Log::warning('Subscription not found for payment failure', ['id' => $data['id'] ?? null]);

            return;
        }

        $subscription->update([
            'status' => 'past_due',
        ]);

        app(PostHogService::class)->capture((string) $subscription->user_id, 'subscription_payment_failed', [
            'plan' => $subscription->plan?->slug,
            'billing_period' => $subscription->billing_period,
        ]);

        Log::warning('Subscription payment failed', ['subscription_id' => $subscription->id]);
    }

    protected function handleSubscriptionPaymentRecovered(array $data): void
    {
        $subscription = $this->findSubscription($data);

        if (! $subscription) {
            Log::warning('Subscription not found for payment recovery', ['id' => $data['id'] ?? null]);

            return;
        }

        $attributes = $data['attributes'] ?? [];

        $subscription->update([
            'status' => 'active',
            'renews_at' => ! empty($attributes['renews_at'])
                ? Carbon::parse($attributes['renews_at'])
                : $subscription->renews_at,
        ]);

        Log::info('Subscription payment recovered', ['subscription_id' => $subscription->id]);
    }
}
