# Paddle Payment Integration Guide

## Overview
This guide covers integrating Paddle payment gateway into SnapDraft's subscription and billing system. All the infrastructure is already in place - we just need to connect Paddle's SDK and webhooks.

## Prerequisites
- Paddle account (https://paddle.com)
- Paddle vendor ID
- Paddle API key
- Products created in Paddle dashboard for each tier

## Step 1: Install Paddle SDK

```bash
composer require paddle/paddle-php-sdk
```

## Step 2: Configure Paddle

Add to `.env`:
```env
PADDLE_VENDOR_ID=your_vendor_id
PADDLE_API_KEY=your_api_key
PADDLE_PUBLIC_KEY=your_public_key
PADDLE_ENVIRONMENT=sandbox # or 'production'
PADDLE_WEBHOOK_SECRET=your_webhook_secret

# Product IDs from Paddle Dashboard
PADDLE_PRODUCT_PRO=pro_xxxxx
PADDLE_PRODUCT_ENTERPRISE=ent_xxxxx
```

Add to `config/services.php`:
```php
'paddle' => [
    'vendor_id' => env('PADDLE_VENDOR_ID'),
    'api_key' => env('PADDLE_API_KEY'),
    'public_key' => env('PADDLE_PUBLIC_KEY'),
    'environment' => env('PADDLE_ENVIRONMENT', 'sandbox'),
    'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),
    'products' => [
        'pro' => env('PADDLE_PRODUCT_PRO'),
        'enterprise' => env('PADDLE_PRODUCT_ENTERPRISE'),
    ],
],
```

## Step 3: Create Paddle Products

In Paddle Dashboard, create 2 products:
1. **SnapDraft Pro**
   - Price: $29/month
   - Recurring: Yes
   - Product ID: Save to `PADDLE_PRODUCT_PRO`

2. **SnapDraft Enterprise**
   - Price: $99/month
   - Recurring: Yes
   - Product ID: Save to `PADDLE_PRODUCT_ENTERPRISE`

## Step 4: Update SubscriptionController

Replace the TODO sections in `app/Http/Controllers/SubscriptionController.php`:

### upgrade() method

```php
public function upgrade(Request $request)
{
    $request->validate([
        'tier' => 'required|in:pro,enterprise',
    ]);

    $user = auth()->user();
    $tier = $request->tier;

    // Get Paddle product ID
    $productId = config('services.paddle.products.' . $tier);

    // Initialize Paddle
    $paddle = new \Paddle\API(
        config('services.paddle.vendor_id'),
        config('services.paddle.api_key'),
        config('services.paddle.environment')
    );

    try {
        // Create or update subscription in Paddle
        if ($user->stripe_subscription_id) {
            // Update existing subscription
            $subscription = $paddle->updateSubscription($user->stripe_subscription_id, [
                'product_id' => $productId,
            ]);
        } else {
            // Create new subscription
            $subscription = $paddle->createSubscription([
                'product_id' => $productId,
                'customer_email' => $user->email,
                'customer_name' => $user->name,
                'passthrough' => json_encode(['user_id' => $user->id]),
                'return_url' => route('subscription.portal'),
            ]);

            // Store Paddle IDs
            $user->update([
                'stripe_customer_id' => $subscription->customer_id,
                'stripe_subscription_id' => $subscription->subscription_id,
            ]);
        }

        // Return checkout URL for Paddle.js
        return response()->json([
            'checkout_url' => $subscription->checkout_url,
        ]);

    } catch (\Exception $e) {
        Log::error('Paddle upgrade error: ' . $e->getMessage());
        return back()->with('error', 'Failed to process upgrade. Please try again.');
    }
}
```

### purchaseCredits() method

```php
public function purchaseCredits(Request $request)
{
    $request->validate([
        'amount' => 'required|integer|min:10|max:1000',
    ]);

    $user = auth()->user();
    $amount = $request->amount;
    $price = $amount * 0.50; // $0.50 per credit

    $paddle = new \Paddle\API(
        config('services.paddle.vendor_id'),
        config('services.paddle.api_key'),
        config('services.paddle.environment')
    );

    try {
        // Create one-time payment
        $payment = $paddle->generatePayLink([
            'title' => "SnapDraft - {$amount} Credits",
            'webhook_url' => route('webhook.paddle'),
            'prices' => ["USD:{$price}"],
            'customer_email' => $user->email,
            'passthrough' => json_encode([
                'user_id' => $user->id,
                'type' => 'credits_purchase',
                'amount' => $amount,
            ]),
            'return_url' => route('subscription.portal'),
        ]);

        return response()->json([
            'checkout_url' => $payment->url,
        ]);

    } catch (\Exception $e) {
        Log::error('Paddle credits purchase error: ' . $e->getMessage());
        return back()->with('error', 'Failed to process purchase. Please try again.');
    }
}
```

### webhook() method

```php
public function webhook(Request $request)
{
    // Verify Paddle signature
    $signature = $request->header('Paddle-Signature');
    $webhookSecret = config('services.paddle.webhook_secret');

    if (!$this->verifyPaddleSignature($request->getContent(), $signature, $webhookSecret)) {
        Log::warning('Invalid Paddle webhook signature');
        return response()->json(['error' => 'Invalid signature'], 403);
    }

    $payload = $request->all();
    $eventType = $payload['alert_name'] ?? null;

    Log::info('Paddle webhook received', ['event' => $eventType, 'payload' => $payload]);

    try {
        switch ($eventType) {
            case 'subscription_created':
                $this->handleSubscriptionCreated($payload);
                break;

            case 'subscription_updated':
                $this->handleSubscriptionUpdated($payload);
                break;

            case 'subscription_cancelled':
                $this->handleSubscriptionCancelled($payload);
                break;

            case 'subscription_payment_succeeded':
                $this->handlePaymentSucceeded($payload);
                break;

            case 'subscription_payment_failed':
                $this->handlePaymentFailed($payload);
                break;

            case 'payment_succeeded':
                // One-time payment (credits purchase)
                $this->handleOneTimePayment($payload);
                break;

            default:
                Log::info('Unhandled Paddle webhook event', ['event' => $eventType]);
        }

        return response()->json(['success' => true]);

    } catch (\Exception $e) {
        Log::error('Paddle webhook error: ' . $e->getMessage(), [
            'event' => $eventType,
            'payload' => $payload,
        ]);
        return response()->json(['error' => 'Webhook processing failed'], 500);
    }
}

private function verifyPaddleSignature($payload, $signature, $secret)
{
    ksort($payload);
    $serialized = serialize($payload);
    $hash = base64_encode(hash_hmac('sha256', $serialized, $secret, true));
    return hash_equals($hash, $signature);
}

private function handleSubscriptionCreated($payload)
{
    $userId = json_decode($payload['passthrough'], true)['user_id'] ?? null;
    if (!$userId) return;

    $user = User::find($userId);
    if (!$user) return;

    // Determine tier from product ID
    $tier = $this->getTierFromProductId($payload['subscription_plan_id']);

    $user->update([
        'stripe_customer_id' => $payload['user_id'],
        'stripe_subscription_id' => $payload['subscription_id'],
        'subscription_tier' => $tier,
        'subscription_started_at' => now(),
        'subscription_ends_at' => $payload['next_bill_date'] ?? null,
    ]);

    $user->resetMonthlyCredits();

    Log::info('Subscription created for user', ['user_id' => $userId, 'tier' => $tier]);
}

private function handleSubscriptionUpdated($payload)
{
    $subscriptionId = $payload['subscription_id'];
    $user = User::where('stripe_subscription_id', $subscriptionId)->first();

    if ($user) {
        $tier = $this->getTierFromProductId($payload['subscription_plan_id']);
        $user->update([
            'subscription_tier' => $tier,
            'subscription_ends_at' => $payload['next_bill_date'] ?? null,
        ]);
        $user->resetMonthlyCredits();

        Log::info('Subscription updated for user', ['user_id' => $user->id, 'tier' => $tier]);
    }
}

private function handleSubscriptionCancelled($payload)
{
    $subscriptionId = $payload['subscription_id'];
    $user = User::where('stripe_subscription_id', $subscriptionId)->first();

    if ($user) {
        $user->update([
            'subscription_tier' => 'free',
            'subscription_ends_at' => $payload['cancellation_effective_date'] ?? now(),
        ]);
        $user->resetMonthlyCredits();

        Log::info('Subscription cancelled for user', ['user_id' => $user->id]);

        // TODO: Send email notification
    }
}

private function handlePaymentSucceeded($payload)
{
    $subscriptionId = $payload['subscription_id'];
    $user = User::where('stripe_subscription_id', $subscriptionId)->first();

    if ($user) {
        // Reset monthly credits on successful payment
        $user->resetMonthlyCredits();

        // TODO: Create invoice record
        Log::info('Payment succeeded for user', ['user_id' => $user->id]);
    }
}

private function handlePaymentFailed($payload)
{
    $subscriptionId = $payload['subscription_id'];
    $user = User::where('stripe_subscription_id', $subscriptionId)->first();

    if ($user) {
        Log::warning('Payment failed for user', ['user_id' => $user->id]);
        // TODO: Send email notification
        // TODO: Suspend account after X failed attempts
    }
}

private function handleOneTimePayment($payload)
{
    $passthrough = json_decode($payload['passthrough'], true);
    
    if ($passthrough['type'] === 'credits_purchase') {
        $userId = $passthrough['user_id'];
        $amount = $passthrough['amount'];

        $user = User::find($userId);
        if ($user) {
            $user->increment('credits_remaining', $amount);
            $user->increment('credits_total', $amount);

            Log::info('Credits purchased', ['user_id' => $userId, 'amount' => $amount]);
            // TODO: Send email receipt
        }
    }
}

private function getTierFromProductId($productId)
{
    $products = config('services.paddle.products');
    
    if ($productId === $products['pro']) {
        return 'pro';
    } elseif ($productId === $products['enterprise']) {
        return 'enterprise';
    }
    
    return 'free';
}
```

## Step 5: Update Frontend - Plans Page

Update `resources/js/pages/subscription/plans.tsx`:

```tsx
const handleUpgrade = async (tier: string) => {
    if (tier === 'free') {
        // Downgrade
        router.post('/subscription/downgrade');
        return;
    }

    try {
        // Get Paddle checkout URL
        const response = await fetch('/subscription/upgrade', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ tier }),
        });

        const data = await response.json();

        if (data.checkout_url) {
            // Open Paddle checkout
            window.location.href = data.checkout_url;
        }
    } catch (error) {
        console.error('Upgrade error:', error);
        alert('Failed to initiate upgrade. Please try again.');
    }
};
```

## Step 6: Update Frontend - Billing Portal

Update `resources/js/pages/subscription/portal.tsx`:

```tsx
const handlePurchaseCredits = async () => {
    try {
        const response = await fetch('/subscription/purchase-credits', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ amount: 50 }),
        });

        const data = await response.json();

        if (data.checkout_url) {
            window.location.href = data.checkout_url;
        }
    } catch (error) {
        console.error('Purchase error:', error);
        alert('Failed to initiate purchase. Please try again.');
    }
};
```

## Step 7: Configure Webhook URL in Paddle

In Paddle Dashboard:
1. Go to Developer Tools → Webhooks
2. Add webhook URL: `https://yourdomain.com/webhook/paddle`
3. Copy webhook secret to `PADDLE_WEBHOOK_SECRET` in .env
4. Enable these events:
   - subscription_created
   - subscription_updated
   - subscription_cancelled
   - subscription_payment_succeeded
   - subscription_payment_failed
   - payment_succeeded

## Step 8: Test in Sandbox

1. Set `PADDLE_ENVIRONMENT=sandbox` in .env
2. Create test subscription via frontend
3. Use Paddle test card: `4242 4242 4242 4242`
4. Verify webhook hits your endpoint
5. Check user subscription updated in database

## Step 9: Handle Invoices

Create migration for invoices table:

```bash
php artisan make:migration create_invoices_table
```

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('paddle_invoice_id')->unique();
    $table->decimal('amount', 10, 2);
    $table->string('currency', 3)->default('USD');
    $table->string('status'); // paid, pending, failed
    $table->text('description');
    $table->string('invoice_url')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'created_at']);
});
```

Create Invoice model and store invoices in webhook handler.

## Step 10: Scheduled Credit Reset

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Reset monthly credits on 1st of each month
    $schedule->call(function () {
        User::where('subscription_tier', '!=', 'free')
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    $user->resetMonthlyCredits();
                }
            });
        
        Log::info('Monthly credits reset completed');
    })->monthlyOn(1, '00:00');
}
```

## Testing Checklist

- [ ] Sandbox mode configured
- [ ] Pro subscription purchase works
- [ ] Enterprise subscription purchase works
- [ ] Credits allocated correctly after purchase
- [ ] Webhooks received and processed
- [ ] User tier updated in database
- [ ] Subscription shown in billing portal
- [ ] Downgrade to free works
- [ ] Credits purchase works
- [ ] Failed payment handled gracefully
- [ ] Invoice history displayed
- [ ] Monthly credit reset tested

## Production Checklist

- [ ] Change `PADDLE_ENVIRONMENT=production`
- [ ] Update product IDs to production IDs
- [ ] Configure production webhook URL
- [ ] Test with real payment
- [ ] Set up monitoring for failed webhooks
- [ ] Configure email notifications
- [ ] Enable Paddle fraud protection
- [ ] Set up tax handling (Paddle handles this)

## Notes

- Paddle handles VAT/tax automatically
- Paddle provides hosted checkout (no PCI compliance needed)
- Webhook retries automatic by Paddle
- Consider adding Paddle.js for inline checkout (optional)
- Monitor webhook failures in Paddle dashboard

## Support

- Paddle Docs: https://developer.paddle.com/
- Paddle PHP SDK: https://github.com/PaddleHQ/paddle-php-sdk
- Contact: support@paddle.com
