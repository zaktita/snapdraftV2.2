<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Plan;
use Illuminate\Console\Command;

class TestLemonSqueezyWebhook extends Command
{
    protected $signature = 'test:webhook {event=subscription_created}';
    protected $description = 'Test Lemon Squeezy webhook locally';

    public function handle()
    {
        $event = $this->argument('event');
        $user = User::first();
        $plan = Plan::where('slug', 'growth')->first();

        if (!$user) {
            $this->error('No users found. Create a user first.');
            return 1;
        }

        $this->info("Testing webhook event: {$event}");
        $this->info("User: {$user->email}");

        // Sample webhook payload based on Lemon Squeezy format
        $payload = [
            'meta' => [
                'event_name' => $event,
                'test_mode' => true,
            ],
            'data' => [
                'type' => 'subscriptions',
                'id' => '123456',
                'attributes' => [
                    'store_id' => config('services.lemonsqueezy.store_id'),
                    'customer_id' => '789012',
                    'order_id' => '345678',
                    'product_id' => $plan?->provider_product_id ?? '833540',
                    'variant_id' => $plan?->provider_variant_monthly ?? '833540',
                    'product_name' => 'Growth Plan',
                    'variant_name' => 'Monthly',
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
                        'subscription_id' => '123456',
                        'price_id' => '1',
                        'quantity' => 1,
                        'is_usage_based' => false,
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                        'price' => 8900,
                        'subscription_custom_data' => [
                            'user_id' => (string) $user->id,
                            'tier' => 'growth',
                            'billing_period' => 'monthly',
                        ],
                        'currency' => 'EUR',
                    ],
                    'urls' => [
                        'update_payment_method' => 'https://example.lemonsqueezy.com/subscription/123456/payment-details',
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

        // Modify payload based on event type
        if ($event === 'subscription_cancelled') {
            $payload['data']['attributes']['status'] = 'cancelled';
            $payload['data']['attributes']['cancelled'] = true;
            $payload['data']['attributes']['ends_at'] = now()->addMonth()->toIso8601String();
        } elseif ($event === 'subscription_expired') {
            $payload['data']['attributes']['status'] = 'expired';
            $payload['data']['attributes']['ends_at'] = now()->toIso8601String();
        } elseif ($event === 'subscription_payment_failed') {
            $payload['data']['attributes']['status'] = 'past_due';
        }

        try {
            // Create a mock request
            $request = \Illuminate\Http\Request::create(
                '/webhooks/lemon-squeezy',
                'POST',
                $payload,
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_X_SIGNATURE' => hash_hmac('sha256', json_encode($payload), config('services.lemonsqueezy.webhook_secret')),
                ],
                json_encode($payload)
            );

            // Call the controller directly
            $controller = new \App\Http\Controllers\Webhooks\LemonSqueezyController();
            $response = $controller->handle($request);

            if ($response->getStatusCode() === 200) {
                $this->info('✅ Webhook processed successfully!');
                $this->newLine();
                $this->info('Check your database:');
                $this->line('  php artisan tinker');
                $this->line("  User::find({$user->id})->subscriptions");
                $this->newLine();
                $this->info('Check logs:');
                $this->line('  Get-Content storage/logs/laravel.log -Tail 30');
            } else {
                $this->error('❌ Webhook failed: ' . $response->getStatusCode());
                $this->line($response->getContent());
            }
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
        }

        return 0;
    }
}
