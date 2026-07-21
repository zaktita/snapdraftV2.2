<?php

namespace App\Console\Commands;

use App\Http\Controllers\Webhooks\LemonSqueezyController;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class TestLemonSqueezyWebhook extends Command
{
    protected $signature = 'test:webhook
                            {event=subscription_created : Webhook event name}
                            {--user= : User id (defaults to first user)}
                            {--tier=pro : Plan slug}
                            {--billing=monthly : monthly|yearly}
                            {--subscription-id=2351333 : Lemon Squeezy subscription id}';

    protected $description = 'Simulate a Lemon Squeezy webhook locally (no tunnel needed)';

    public function handle(): int
    {
        $event = $this->argument('event');
        $userId = $this->option('user');
        $tier = $this->option('tier');
        $billing = $this->option('billing');
        $lemonSubscriptionId = (string) $this->option('subscription-id');

        $user = $userId
            ? User::find($userId)
            : User::query()->orderBy('id')->first();

        if (! $user) {
            $this->error('No users found. Create a user first, or pass --user=');

            return 1;
        }

        $plan = Plan::where('slug', $tier)->first();
        if (! $plan) {
            $this->error("Plan slug '{$tier}' not found. Seed plans or pass --tier=starter|pro|business");

            return 1;
        }

        $secret = config('services.lemonsqueezy.webhook_secret');
        if (empty($secret)) {
            $this->error('LEMON_SQUEEZY_WEBHOOK_SECRET is empty in .env');

            return 1;
        }

        $this->info("Event: {$event}");
        $this->info("User: {$user->email} (id {$user->id})");
        $this->info("Plan: {$plan->slug}");
        $this->info("Lemon subscription id: {$lemonSubscriptionId}");

        $variantId = $billing === 'yearly'
            ? ($plan->provider_variant_yearly ?: '22222')
            : ($plan->provider_variant_monthly ?: '22222');

        $payload = [
            'meta' => [
                'event_name' => $event,
                'test_mode' => true,
                'custom_data' => [
                    'user_id' => (string) $user->id,
                    'tier' => $tier,
                    'billing_period' => $billing,
                ],
            ],
            'data' => [
                'type' => 'subscriptions',
                'id' => $lemonSubscriptionId,
                'attributes' => [
                    'store_id' => config('services.lemonsqueezy.store_id'),
                    'customer_id' => '9333725',
                    'order_id' => '345678',
                    'product_id' => $plan->provider_product_id ?? '833540',
                    'variant_id' => $variantId,
                    'product_name' => $plan->name,
                    'variant_name' => ucfirst($billing),
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'status' => 'active',
                    'status_formatted' => 'Active',
                    'card_brand' => 'visa',
                    'card_last_four' => '4242',
                    'pause' => null,
                    'cancelled' => false,
                    'trial_ends_at' => null,
                    'billing_anchor' => now()->day,
                    'first_subscription_item' => [
                        'id' => '1',
                        'subscription_id' => $lemonSubscriptionId,
                        'price_id' => '1',
                        'quantity' => 1,
                        'is_usage_based' => false,
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                        'price' => (int) round(((float) $plan->price) * 100),
                        'currency' => strtoupper($plan->currency ?? 'USD'),
                    ],
                    'urls' => [
                        'update_payment_method' => 'https://example.lemonsqueezy.com/subscription/payment-details',
                        'customer_portal' => 'https://example.lemonsqueezy.com/billing',
                    ],
                    'renews_at' => now()->addMonth()->toIso8601String(),
                    'ends_at' => null,
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                    'test_mode' => true,
                ],
            ],
        ];

        if ($event === 'subscription_cancelled') {
            $payload['data']['attributes']['status'] = 'cancelled';
            $payload['data']['attributes']['cancelled'] = true;
            $payload['data']['attributes']['ends_at'] = now()->addMonth()->toIso8601String();
        } elseif ($event === 'subscription_expired') {
            $payload['data']['attributes']['status'] = 'expired';
            $payload['data']['attributes']['ends_at'] = now()->toIso8601String();
        } elseif ($event === 'subscription_payment_failed') {
            $payload['data']['attributes']['status'] = 'past_due';
        } elseif ($event === 'subscription_payment_success') {
            $payload['data'] = [
                'type' => 'subscription-invoices',
                'id' => '7930582',
                'attributes' => [
                    'subscription_id' => (int) $lemonSubscriptionId,
                    'customer_id' => 9333725,
                    'billing_reason' => 'initial',
                    'status' => 'paid',
                    'currency' => 'USD',
                    'total' => 8900,
                    'test_mode' => true,
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ];
        }

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $request = Request::create(
            '/webhooks/lemon-squeezy',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => hash_hmac('sha256', $body, $secret),
            ],
            $body
        );

        $response = app(LemonSqueezyController::class)->handle($request);

        if ($response->getStatusCode() === 200) {
            $this->info('Webhook processed successfully.');
            $this->line("Check: User::find({$user->id})->activeSubscription");

            return 0;
        }

        $this->error('Webhook failed: '.$response->getStatusCode());
        $this->line($response->getContent());

        return 1;
    }
}
