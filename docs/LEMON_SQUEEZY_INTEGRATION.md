# Lemon Squeezy Integration Guide for SnapDraft

## Overview
This guide walks you through integrating Lemon Squeezy payments and webhooks into SnapDraft.

## Prerequisites
- Lemon Squeezy account with store setup
- Products/variants created in Lemon Squeezy dashboard
- API key and webhook secret from Lemon Squeezy

---

## Step 1: Environment Configuration

Add these variables to your `.env` file:

```env
# Lemon Squeezy Configuration
LEMON_SQUEEZY_API_KEY=your_api_key_here
LEMON_SQUEEZY_STORE_ID=your_store_id_here
LEMON_SQUEEZY_WEBHOOK_SECRET=your_webhook_secret_here

# Product Variant IDs (from Lemon Squeezy dashboard)
LEMON_SQUEEZY_LAUNCH_MONTHLY_VARIANT_ID=
LEMON_SQUEEZY_LAUNCH_YEARLY_VARIANT_ID=
LEMON_SQUEEZY_GROWTH_MONTHLY_VARIANT_ID=
LEMON_SQUEEZY_GROWTH_YEARLY_VARIANT_ID=
LEMON_SQUEEZY_SCALE_MONTHLY_VARIANT_ID=
LEMON_SQUEEZY_SCALE_YEARLY_VARIANT_ID=
```

Add corresponding entries in `config/services.php`:

```php
'lemonsqueezy' => [
    'api_key' => env('LEMON_SQUEEZY_API_KEY'),
    'store_id' => env('LEMON_SQUEEZY_STORE_ID'),
    'webhook_secret' => env('LEMON_SQUEEZY_WEBHOOK_SECRET'),
    'variants' => [
        'launch_monthly' => env('LEMON_SQUEEZY_LAUNCH_MONTHLY_VARIANT_ID'),
        'launch_yearly' => env('LEMON_SQUEEZY_LAUNCH_YEARLY_VARIANT_ID'),
        'growth_monthly' => env('LEMON_SQUEEZY_GROWTH_MONTHLY_VARIANT_ID'),
        'growth_yearly' => env('LEMON_SQUEEZY_GROWTH_YEARLY_VARIANT_ID'),
        'scale_monthly' => env('LEMON_SQUEEZY_SCALE_MONTHLY_VARIANT_ID'),
        'scale_yearly' => env('LEMON_SQUEEZY_SCALE_YEARLY_VARIANT_ID'),
    ],
],
```

---

## Step 2: Database Migration

Update the users table to support Lemon Squeezy instead of Stripe:

**Migration file:** `database/migrations/2026_02_16_000001_update_users_for_lemonsqueezy.php`

---

## Step 3: Lemon Squeezy Service

Location: `app/Services/LemonSqueezyService.php`

This service handles:
- Creating checkout sessions
- Managing subscriptions
- Retrieving customer data
- Processing refunds

---

## Step 4: Webhook Handler

Location: `app/Http/Controllers/WebhookController.php`

Handles these webhook events:
- `order_created` - New purchase/subscription
- `subscription_created` - New subscription created
- `subscription_updated` - Subscription plan changed
- `subscription_cancelled` - Subscription cancelled
- `subscription_resumed` - Subscription reactivated
- `subscription_expired` - Subscription ended
- `subscription_paused` - Subscription paused
- `subscription_unpaused` - Subscription unpaused
- `subscription_payment_success` - Successful payment
- `subscription_payment_failed` - Failed payment
- `subscription_payment_recovered` - Recovered after failed payment

---

## Step 5: Routes Configuration

Update `routes/web.php`:

```php
// Replace the Stripe webhook route with Lemon Squeezy
Route::post('/webhook/lemonsqueezy', [WebhookController::class, 'handle'])
    ->name('webhook.lemonsqueezy')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
```

---

## Step 6: Update Subscription Controller

Update `app/Http/Controllers/SubscriptionController.php` to use Lemon Squeezy for checkout.

---

## Step 7: Configure Webhooks in Lemon Squeezy Dashboard

1. Go to Lemon Squeezy Dashboard → Settings → Webhooks
2. Click "+" to add new webhook endpoint
3. Set URL to: `https://yourdomain.com/webhook/lemonsqueezy`
4. Select events to listen to:
   - ✅ order_created
   - ✅ subscription_created
   - ✅ subscription_updated
   - ✅ subscription_cancelled
   - ✅ subscription_resumed
   - ✅ subscription_expired
   - ✅ subscription_paused
   - ✅ subscription_unpaused
   - ✅ subscription_payment_success
   - ✅ subscription_payment_failed
   - ✅ subscription_payment_recovered
5. Copy the webhook signing secret and add to `.env`

---

## Step 8: Testing Webhooks Locally

### Using ngrok or expose:

```bash
# Install ngrok or Laravel Expose
composer require --dev beyondcode/expose

# Start your local server
composer dev

# In another terminal, expose it
php artisan expose share

# Or with ngrok
ngrok http 8000
```

Use the public URL in Lemon Squeezy webhook configuration.

### Manual Testing:

Send test webhooks from Lemon Squeezy dashboard or use this curl command:

```bash
curl -X POST http://localhost:8000/webhook/lemonsqueezy \
  -H "Content-Type: application/json" \
  -H "X-Signature: test_signature" \
  -d @webhook-test-payload.json
```

---

## Step 9: Implement Frontend Checkout

Update your React components to initiate Lemon Squeezy checkout:

```tsx
import { router } from '@inertiajs/react';

const handleUpgrade = (tier: string, billingPeriod: 'monthly' | 'yearly') => {
    router.post('/subscription/checkout', {
        tier,
        billing_period: billingPeriod,
    }, {
        onSuccess: (response) => {
            // Redirect to Lemon Squeezy checkout
            window.location.href = response.props.checkout_url;
        },
    });
};
```

---

## Step 10: Security Considerations

1. **Webhook Signature Verification**: Always verify webhook signatures
2. **HTTPS Only**: Use HTTPS in production
3. **Rate Limiting**: Add rate limiting to webhook endpoint if needed
4. **Idempotency**: Handle duplicate webhooks gracefully
5. **Logging**: Log all webhook events for debugging

---

## API Reference

### Lemon Squeezy API Endpoints

**Base URL:** `https://api.lemonsqueezy.com/v1`

**Authentication:** Bearer token (API key)

```bash
# Example: Create checkout
curl https://api.lemonsqueezy.com/v1/checkouts \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "data": {
      "type": "checkouts",
      "attributes": {
        "checkout_data": {
          "email": "customer@example.com",
          "custom": {
            "user_id": "123"
          }
        }
      },
      "relationships": {
        "store": {
          "data": {
            "type": "stores",
            "id": "YOUR_STORE_ID"
          }
        },
        "variant": {
          "data": {
            "type": "variants",
            "id": "YOUR_VARIANT_ID"
          }
        }
      }
    }
  }'
```

---

## Troubleshooting

### Webhooks not receiving:
- Check firewall/server settings
- Verify webhook URL is accessible publicly
- Check Lemon Squeezy webhook logs in dashboard
- Ensure CSRF middleware is disabled for webhook route

### Signature verification failing:
- Ensure webhook secret matches
- Check request body is not modified before verification
- Verify you're reading raw request body

### Subscription not updating:
- Check webhook handler logic
- Review Laravel logs: `storage/logs/laravel.log`
- Check database transaction is committing

---

## Next Steps

1. Test each subscription tier upgrade/downgrade
2. Test subscription cancellation flow
3. Implement customer portal for managing subscriptions
4. Set up automated tests for webhook handlers
5. Monitor webhook delivery in production
6. Implement grace period handling for failed payments

---

## Resources

- [Lemon Squeezy API Documentation](https://docs.lemonsqueezy.com/api)
- [Lemon Squeezy Webhooks Guide](https://docs.lemonsqueezy.com/guides/developer-guide/webhooks)
- [Lemon Squeezy Laravel Integration Examples](https://docs.lemonsqueezy.com/help/getting-started/laravel)
