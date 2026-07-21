<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class LemonSqueezyWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.lemonsqueezy.webhook_secret', $this->secret);
    }

    private function signedPost(array $payload)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, $this->secret);

        return $this->call(
            'POST',
            '/webhooks/lemon-squeezy',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Signature' => $signature,
            ],
            $body
        );
    }

    private function makePlan(string $variantId = '22222'): Plan
    {
        return Plan::create([
            'provider' => 'lemonsqueezy',
            'name' => 'Starter',
            'slug' => 'starter',
            'price' => 29,
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'provider_variant_monthly' => $variantId,
            'capabilities' => [
                'credits_per_month' => 100,
                'max_projects' => 10,
                'csv_max_rows' => 25,
            ],
            'is_active' => true,
        ]);
    }

    public function test_rejects_invalid_signature(): void
    {
        $payload = json_decode(
            file_get_contents(base_path('tests/webhooks/subscription_created.json')),
            true
        );

        $this->call(
            'POST',
            '/webhooks/lemon-squeezy',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Signature' => 'deadbeef',
            ],
            json_encode($payload)
        )->assertUnauthorized();
    }

    public function test_subscription_created_grants_credits_from_meta_custom_data(): void
    {
        $user = User::factory()->create();
        $this->makePlan('22222');

        $payload = json_decode(
            file_get_contents(base_path('tests/webhooks/subscription_created.json')),
            true
        );
        $payload['meta']['custom_data']['user_id'] = (string) $user->id;
        $payload['meta']['custom_data']['tier'] = 'starter';
        $payload['meta']['custom_data']['billing_period'] = 'monthly';

        $this->signedPost($payload)->assertOk();

        $sub = Subscription::where('user_id', $user->id)->where('lemonsqueezy_id', '12345')->first();
        $this->assertNotNull($sub);
        $this->assertSame('active', $sub->status);
        $this->assertSame(100, (int) data_get($sub->capabilities, 'credits_remaining'));
        $this->assertSame(100, (int) data_get($sub->capabilities, 'credits_limit'));
        $this->assertSame(10, (int) data_get($sub->capabilities, 'max_projects'));
    }

    public function test_subscription_created_is_idempotent(): void
    {
        $user = User::factory()->create();
        $this->makePlan('22222');

        $payload = json_decode(
            file_get_contents(base_path('tests/webhooks/subscription_created.json')),
            true
        );
        $payload['meta']['custom_data']['user_id'] = (string) $user->id;

        $this->signedPost($payload)->assertOk();
        $this->signedPost($payload)->assertOk();

        $this->assertSame(1, Subscription::where('lemonsqueezy_id', '12345')->count());
    }

    public function test_payment_success_renewal_resets_credits(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan('22222');

        $sub = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'provider' => 'lemonsqueezy',
            'lemonsqueezy_id' => '12345',
            'status' => 'active',
            'billing_period' => 'monthly',
            'starts_at' => now()->subMonth(),
            'price' => 29,
            'amount_paid' => 29,
            'currency' => 'USD',
            'capabilities' => [
                'credits_per_month' => 100,
                'credits_remaining' => 12,
                'credits_limit' => 100,
                'credits_used' => 88,
                'max_projects' => 10,
            ],
        ]);

        $payload = json_decode(
            file_get_contents(base_path('tests/webhooks/subscription_payment_success.json')),
            true
        );

        $this->signedPost($payload)->assertOk();

        $sub->refresh();
        $this->assertSame('active', $sub->status);
        $this->assertSame(100, (int) data_get($sub->capabilities, 'credits_remaining'));
        $this->assertSame(0, (int) data_get($sub->capabilities, 'credits_used'));
        $this->assertSame('55555', data_get($sub->metadata, 'last_payment_invoice_id'));

        $invoice = Invoice::where('lemonsqueezy_invoice_id', '55555')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($user->id, $invoice->user_id);
        $this->assertSame(89.0, (float) $invoice->total);
        $this->assertSame(
            'https://app.lemonsqueezy.com/my-orders/example/subscription-invoice/55555',
            data_get($invoice->meta, 'invoice_url')
        );
    }

    public function test_payment_success_is_idempotent_per_invoice(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan('22222');

        $sub = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'provider' => 'lemonsqueezy',
            'lemonsqueezy_id' => '12345',
            'status' => 'active',
            'billing_period' => 'monthly',
            'starts_at' => now()->subMonth(),
            'price' => 29,
            'amount_paid' => 29,
            'currency' => 'USD',
            'capabilities' => [
                'credits_per_month' => 100,
                'credits_remaining' => 12,
                'credits_limit' => 100,
                'credits_used' => 88,
            ],
        ]);

        $payload = json_decode(
            file_get_contents(base_path('tests/webhooks/subscription_payment_success.json')),
            true
        );

        $this->signedPost($payload)->assertOk();
        $sub->update([
            'capabilities' => array_merge($sub->fresh()->capabilities, [
                'credits_remaining' => 5,
                'credits_used' => 95,
            ]),
        ]);
        $this->signedPost($payload)->assertOk();

        $sub->refresh();
        // Second delivery must not reset again
        $this->assertSame(5, (int) data_get($sub->capabilities, 'credits_remaining'));
    }
}
