# Lemon Squeezy Integration - Quick Reference

## 🚀 Quick Start

### 1. Install & Configure (5 minutes)
```bash
# Already done - files created!
# Add your credentials to .env (see .env.lemonsqueezy for template)
php artisan migrate
```

### 2. Get Lemon Squeezy Credentials
- API Key: https://app.lemonsqueezy.com/settings/api
- Store ID: Found in dashboard URL
- Webhook Secret: https://app.lemonsqueezy.com/settings/webhooks

### 3. Create Products
Create 3 products with monthly/yearly variants:
- **Launch Plan**: €39/mo or €31/mo (yearly)
- **Growth Plan**: €89/mo or €71/mo (yearly)  
- **Scale Plan**: €199/mo or €159/mo (yearly)

### 4. Configure Webhook
URL: `https://yourdomain.com/webhook/lemonsqueezy`

Events to enable:
- ✅ All subscription events
- ✅ All payment events

---

## 📁 Files Created

| File | Purpose |
|------|---------|
| `app/Services/LemonSqueezyService.php` | API integration service |
| `app/Http/Controllers/WebhookController.php` | Webhook event handler |
| `app/Http/Controllers/SubscriptionController.php` | ✏️ Updated for Lemon Squeezy |
| `database/migrations/2026_02_16_000001_update_users_for_lemonsqueezy.php` | Database schema |
| `config/services.php` | ✏️ Added Lemon Squeezy config |
| `routes/web.php` | ✏️ Added webhook route |
| `bootstrap/app.php` | ✏️ Excluded webhooks from CSRF |

---

## 🔧 Key Components

### LemonSqueezyService Methods

```php
// Create checkout
$checkout = $lemonSqueezy->createCheckout($variantId, $email, $customData);

// Get subscription
$subscription = $lemonSqueezy->getSubscription($subscriptionId);

// Cancel subscription
$lemonSqueezy->cancelSubscription($subscriptionId);

// Resume subscription
$lemonSqueezy->resumeSubscription($subscriptionId);

// Update subscription
$lemonSqueezy->updateSubscription($subscriptionId, $newVariantId);

// Verify webhook signature
$isValid = $lemonSqueezy->verifyWebhookSignature($payload, $signature);
```

### Webhook Events Handled

| Event | Action |
|-------|--------|
| `order_created` | Store customer & order info |
| `subscription_created` | Activate subscription, grant credits |
| `subscription_updated` | Update plan, adjust credits |
| `subscription_cancelled` | Mark as cancelled, set end date |
| `subscription_expired` | Downgrade to free tier |
| `subscription_payment_success` | Reset monthly credits |
| `subscription_payment_failed` | Mark as past_due |

---

## 🔐 Environment Variables

```env
# Required
LEMON_SQUEEZY_API_KEY=
LEMON_SQUEEZY_STORE_ID=
LEMON_SQUEEZY_WEBHOOK_SECRET=

# Product Variants (6 total - 3 plans × 2 billing periods)
LEMON_SQUEEZY_LAUNCH_MONTHLY_VARIANT_ID=
LEMON_SQUEEZY_LAUNCH_YEARLY_VARIANT_ID=
LEMON_SQUEEZY_GROWTH_MONTHLY_VARIANT_ID=
LEMON_SQUEEZY_GROWTH_YEARLY_VARIANT_ID=
LEMON_SQUEEZY_SCALE_MONTHLY_VARIANT_ID=
LEMON_SQUEEZY_SCALE_YEARLY_VARIANT_ID=
```

---

## 🧪 Testing

### Test Webhooks Locally

```powershell
# Using provided test script
.\test-webhook.ps1 -Event subscription_created

# Available test events:
# - subscription_created
# - subscription_cancelled
# - subscription_payment_success
```

### Manual cURL Test

```bash
curl -X POST http://localhost:8000/webhook/lemonsqueezy \
  -H "Content-Type: application/json" \
  -H "X-Signature: test_sig" \
  -d @tests/webhooks/subscription_created.json
```

### Check Logs

```bash
tail -f storage/logs/laravel.log | grep "Lemon Squeezy"
```

---

## 📊 Database Schema Changes

New columns added to `users` table:

| Column | Type | Purpose |
|--------|------|---------|
| `lemonsqueezy_customer_id` | string | Renamed from stripe_customer_id |
| `lemonsqueezy_subscription_id` | string | Renamed from stripe_subscription_id |
| `lemonsqueezy_order_id` | string | Order reference |
| `lemonsqueezy_variant_id` | string | Current plan variant |
| `billing_period` | string | monthly or yearly |
| `subscription_status` | string | active, cancelled, expired, paused, past_due |
| `next_billing_date` | timestamp | Next renewal date |
| `subscription_cancelled_at` | timestamp | When cancelled |

---

## 🎯 User Journey

### Subscription Flow

1. **User clicks "Upgrade"** → `POST /subscription/upgrade`
2. **Create checkout session** → Redirect to Lemon Squeezy
3. **User completes payment** → Lemon Squeezy processes
4. **Webhook received** → `subscription_created` event
5. **System updates user** → Grant credits, set tier
6. **User redirected back** → Can use features

### Cancellation Flow

1. **User clicks "Cancel"** → `POST /subscription/downgrade`
2. **Cancel via API** → Lemon Squeezy cancels subscription
3. **Webhook received** → `subscription_cancelled` event
4. **System updates** → Mark cancelled, keep access until end date
5. **Subscription expires** → `subscription_expired` event
6. **Downgrade to free** → Credits reset to free tier

---

## 🐛 Troubleshooting

### Webhook signature failing?
```php
// Check in WebhookController
Log::info('Webhook signature check', [
    'provided' => $signature,
    'expected' => hash_hmac('sha256', $payload, $secret),
]);
```

### User not updating?
```php
// Check custom_data in webhook payload
'meta' => [
    'custom_data' => [
        'user_id' => '123',  // Must be present!
    ]
]
```

### Checkout not redirecting?
```php
// Verify variant ID exists
dd($this->lemonSqueezy->getVariantId('growth', 'monthly'));
```

---

## 📚 Resources

- **Full Guide**: `docs/LEMON_SQUEEZY_INTEGRATION.md`
- **Setup Checklist**: `docs/LEMON_SQUEEZY_SETUP_CHECKLIST.md`
- **Lemon Squeezy API**: https://docs.lemonsqueezy.com/api
- **Webhooks Guide**: https://docs.lemonsqueezy.com/guides/developer-guide/webhooks

---

## ✅ Next Steps

1. [ ] Add Lemon Squeezy credentials to `.env`
2. [ ] Run migration: `php artisan migrate`
3. [ ] Create products in Lemon Squeezy dashboard
4. [ ] Add variant IDs to `.env`
5. [ ] Configure webhook in Lemon Squeezy
6. [ ] Test with provided scripts
7. [ ] Deploy to production

---

**Need help?** Check the full integration guide or setup checklist for detailed instructions.
