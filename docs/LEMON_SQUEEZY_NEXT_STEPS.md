# Lemon Squeezy Integration - Next Steps

## 📋 Analysis Summary

You've added two controllers from another project (WordPress hosting SaaS). Here's what they do and what we need to adapt for SnapDraft:

### Controllers Analyzed:

**1. BillingController.php** (Different Project)
- Manages invoices, checkout pages, PDF generation
- Uses Blade views (`client-area.*` routes)
- Creates physical products (WordPress sites)
- Uses Invoice, Transaction, Product models (not in SnapDraft)

**2. PayementController.php (LemonSqueezyController)** (Different Project)
- Handles one-time orders (not subscriptions)
- Creates checkout with custom pricing
- Webhook handles: `order_created`, `order_refunded`, `subscription_payment_failed`
- Creates Transaction + Invoice + Product records

### Key Differences vs SnapDraft:

| Feature | Other Project | SnapDraft |
|---------|--------------|-----------|
| Business Model | Sell products (WP sites) | Subscription SaaS |
| Checkout Type | One-time orders | Recurring subscriptions |
| Database | Invoice, Transaction, Product | User subscriptions in users table |
| Views | Blade templates | Inertia.js + React |
| Webhook Events | order_created | subscription_created, subscription_updated |

---

## ✅ What's Already Done

- [x] LemonSqueezyService.php created (subscription-focused)
- [x] WebhookController.php created (handles subscription events)
- [x] SubscriptionController.php updated for Lemon Squeezy
- [x] Database migration for Lemon Squeezy fields
- [x] Config updated with test_mode support
- [x] Better error logging added
- [x] Webhook CSRF exemption
- [x] Routes configured

---

## 🎯 Next Steps (Prioritized)

### CRITICAL - Do These First

#### 1. Update User Model Fillable Fields
**File:** `app/Models/User.php`

Add these to `$fillable` array:
```php
'billing_period',
'subscription_status',
'lemonsqueezy_customer_id',
'lemonsqueezy_subscription_id',
'lemonsqueezy_order_id',
'lemonsqueezy_variant_id',
'next_billing_date',
'subscription_cancelled_at',
```

Also add to `$hidden` array (if not already):
```php
'lemonsqueezy_customer_id',
'lemonsqueezy_subscription_id',
```

And update `casts()` method:
```php
'next_billing_date' => 'datetime',
'subscription_cancelled_at' => 'datetime',
```

#### 2. Run Database Migration
```bash
php artisan migrate
```

This applies the migration from `2026_02_16_000001_update_users_for_lemonsqueezy.php`

#### 3. Configure Environment Variables

Add to your `.env` file (API key and Store ID already in `.env.lemonsqueezy`):

```env
LEMON_SQUEEZY_API_KEY=eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
LEMON_SQUEEZY_STORE_ID=294203
LEMON_SQUEEZY_WEBHOOK_SECRET=your_webhook_secret_here
LEMON_SQUEEZY_TEST_MODE=true

# Get these variant IDs from Lemon Squeezy dashboard
LEMON_SQUEEZY_LAUNCH_MONTHLY_VARIANT_ID=
LEMON_SQUEEZY_LAUNCH_YEARLY_VARIANT_ID=
LEMON_SQUEEZY_GROWTH_MONTHLY_VARIANT_ID=
LEMON_SQUEEZY_GROWTH_YEARLY_VARIANT_ID=
LEMON_SQUEEZY_SCALE_MONTHLY_VARIANT_ID=
LEMON_SQUEEZY_SCALE_YEARLY_VARIANT_ID=
```

#### 4. Create Products in Lemon Squeezy Dashboard

Go to https://app.lemonsqueezy.com/products and create:

**Launch Plan Product:**
- Name: "SnapDraft Launch Plan"
- Description: "100 credits/month, 1 brand project"
- Create 2 variants:
  - Monthly: €39/month
  - Yearly: €372/year (€31/month)
- Copy variant IDs to `.env`

**Growth Plan Product:**
- Name: "SnapDraft Growth Plan"
- Description: "350 credits/month, 3 brand projects"
- Create 2 variants:
  - Monthly: €89/month
  - Yearly: €852/year (€71/month)
- Copy variant IDs to `.env`

**Scale Plan Product:**
- Name: "SnapDraft Scale Plan"
- Description: "900 credits/month, 10 brand projects"
- Create 2 variants:
  - Monthly: €199/month
  - Yearly: €1908/year (€159/month)
- Copy variant IDs to `.env`

#### 5. Configure Webhook in Lemon Squeezy

**For Local Testing:**
```bash
# Install Laravel Expose or use ngrok
composer require --dev beyondcode/expose
php artisan expose share
```

**In Lemon Squeezy Dashboard:**
1. Go to Settings → Webhooks
2. Click "Add endpoint"
3. URL: `https://your-domain.com/webhook/lemonsqueezy` (or expose URL)
4. Select events:
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
5. Copy signing secret → `LEMON_SQUEEZY_WEBHOOK_SECRET` in `.env`
6. Restart app: `composer dev`

---

### OPTIONAL - Consider These Later

#### 6. Add Invoice/Transaction Tracking (Optional)

If you want detailed billing history like the other project, create these models:

**Models to Create:**
- `Invoice` - Store invoice records
- `Transaction` - Track payment transactions

**Benefits:**
- Detailed payment history
- PDF invoice generation
- Refund tracking
- Better accounting

**Implementation:**
1. Create migrations for `invoices` and `transactions` tables
2. Create Invoice and Transaction models
3. Update WebhookController to create these records
4. Add invoice display in subscription portal

#### 7. Create Subscription Management Pages

**Pages to build (Inertia + React):**
- `resources/js/pages/subscription/plans.tsx` - Already exists ✓
- `resources/js/pages/subscription/portal.tsx` - Already exists ✓
- `resources/js/pages/subscription/invoices.tsx` - NEW (if adding invoice tracking)
- `resources/js/pages/subscription/invoice-detail.tsx` - NEW (if adding invoice tracking)

#### 8. Add Email Notifications

Create mail classes for:
- Subscription activated
- Payment successful
- Payment failed
- Subscription cancelled
- Subscription expired

Example:
```bash
php artisan make:mail SubscriptionActivated
php artisan make:mail PaymentFailed
```

Update WebhookController to send emails on events.

#### 9. Add Customer Portal Link

Lemon Squeezy provides a customer portal URL. Add this to your subscription portal page:

```php
// In SubscriptionController::portal()
$portalUrl = null;
if ($user->lemonsqueezy_subscription_id) {
    $subscription = $this->lemonSqueezy->getSubscription($user->lemonsqueezy_subscription_id);
    $portalUrl = $subscription['data']['attributes']['urls']['customer_portal'] ?? null;
}

return Inertia::render('subscription/portal', [
    'subscription' => $subscription,
    'portal_url' => $portalUrl,
]);
```

#### 10. Add Proration Logic

When users upgrade/downgrade, calculate prorated amounts:

```php
// In SubscriptionService
public static function calculateProration(User $user, string $newTier): array
{
    $currentTier = $user->subscription_tier;
    $daysRemaining = $user->subscription_ends_at?->diffInDays(now()) ?? 0;
    
    // Calculate credit/charge based on plan difference
    // Return prorated amount
}
```

---

## 🧪 Testing Checklist

### Local Testing

- [ ] Run migration successfully
- [ ] API key and store ID work (test API call)
- [ ] Webhook endpoint accessible via expose/ngrok
- [ ] Webhook signature verification works
- [ ] Test webhook with provided test scripts:
  ```powershell
  .\test-webhook.ps1 -Event subscription_created
  ```

### Integration Testing

- [ ] Visit `/subscription/plans`
- [ ] Click upgrade on a plan
- [ ] Verify redirect to Lemon Squeezy
- [ ] Complete test checkout (use test mode)
- [ ] Verify webhook received and processed
- [ ] Check user subscription updated in database
- [ ] Verify credits assigned correctly

### Production Checklist

- [ ] Update webhook URL to production domain
- [ ] Set `LEMON_SQUEEZY_TEST_MODE=false`
- [ ] Test with real card (small amount)
- [ ] Monitor logs for errors
- [ ] Set up error alerting (Sentry, etc.)

---

## 🐛 Common Issues & Solutions

### Issue: Webhook signature fails
**Solution:** Ensure webhook secret in `.env` matches Lemon Squeezy dashboard exactly (no extra spaces)

### Issue: Variant ID not found (404 error)
**Solution:** Double-check variant IDs in `.env` match your Lemon Squeezy products

### Issue: User not updating after payment
**Solution:** 
1. Check webhook logs: `tail -f storage/logs/laravel.log | grep "Lemon Squeezy"`
2. Verify `user_id` is in custom_data
3. Check fillable fields in User model

### Issue: Checkout redirect fails
**Solution:** 
1. Check API key and store ID are correct
2. Verify variant exists in Lemon Squeezy
3. Check error logs for API response

---

## 📊 Monitoring & Maintenance

### Log Checks

```bash
# Watch webhook activity
tail -f storage/logs/laravel.log | grep "Lemon Squeezy"

# Check for errors
grep -i "error" storage/logs/laravel.log | grep -i "lemon"
```

### Database Checks

```php
// Check recent subscriptions
User::whereNotNull('lemonsqueezy_subscription_id')
    ->latest()
    ->take(10)
    ->get(['id', 'name', 'subscription_tier', 'subscription_status', 'credits_remaining']);

// Check failed webhooks (if logging to database)
// This would require a webhook_logs table
```

### Health Checks

Create a health check endpoint that verifies:
- Lemon Squeezy API is reachable
- Webhook secret is configured
- All variant IDs are set

---

## 🔐 Security Reminders

- ✅ Webhook signature verification is implemented
- ✅ CSRF exemption for webhook route
- ⚠️ Never log full API keys
- ⚠️ Use HTTPS in production
- ⚠️ Rate limit webhook endpoint if needed
- ⚠️ Validate all webhook data before processing

---

## 📚 Resources

- [SnapDraft Integration Guide](./LEMON_SQUEEZY_INTEGRATION.md)
- [Setup Checklist](./LEMON_SQUEEZY_SETUP_CHECKLIST.md)
- [Quick Reference](./LEMON_SQUEEZY_QUICK_REFERENCE.md)
- [Lemon Squeezy API Docs](https://docs.lemonsqueezy.com/api)
- [Lemon Squeezy Webhooks](https://docs.lemonsqueezy.com/guides/developer-guide/webhooks)

---

## ✨ Summary

**Immediate Actions (Today):**
1. Update User model fillable fields
2. Run migration
3. Create products in Lemon Squeezy
4. Add variant IDs to `.env`
5. Configure webhook endpoint
6. Test checkout flow

**This Week:**
- Test webhook events
- Deploy to staging
- Monitor for issues

**Later:**
- Add invoice tracking (optional)
- Email notifications
- Customer portal enhancements
- Proration logic

You're **80% done** with the core integration! The remaining 20% is configuration and testing.
