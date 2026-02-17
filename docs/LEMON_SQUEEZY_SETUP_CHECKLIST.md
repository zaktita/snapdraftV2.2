# Lemon Squeezy Setup Checklist

Follow this checklist to complete your Lemon Squeezy integration.

## ✅ Step-by-Step Setup

### 1. Create Lemon Squeezy Account
- [ ] Sign up at https://lemonsqueezy.com
- [ ] Complete your store setup
- [ ] Add business information

### 2. Create Products in Lemon Squeezy Dashboard

Create 3 products (one for each tier):

#### Launch Plan Product
- [ ] Create product "SnapDraft Launch Plan"
- [ ] Create monthly variant (€39/month)
- [ ] Create yearly variant (€31/month, billed €372/year)
- [ ] Copy monthly variant ID → `LEMON_SQUEEZY_LAUNCH_MONTHLY_VARIANT_ID`
- [ ] Copy yearly variant ID → `LEMON_SQUEEZY_LAUNCH_YEARLY_VARIANT_ID`

#### Growth Plan Product
- [ ] Create product "SnapDraft Growth Plan"
- [ ] Create monthly variant (€89/month)
- [ ] Create yearly variant (€71/month, billed €852/year)
- [ ] Copy monthly variant ID → `LEMON_SQUEEZY_GROWTH_MONTHLY_VARIANT_ID`
- [ ] Copy yearly variant ID → `LEMON_SQUEEZY_GROWTH_YEARLY_VARIANT_ID`

#### Scale Plan Product
- [ ] Create product "SnapDraft Scale Plan"
- [ ] Create monthly variant (€199/month)
- [ ] Create yearly variant (€159/month, billed €1908/year)
- [ ] Copy monthly variant ID → `LEMON_SQUEEZY_SCALE_MONTHLY_VARIANT_ID`
- [ ] Copy yearly variant ID → `LEMON_SQUEEZY_SCALE_YEARLY_VARIANT_ID`

### 3. Get API Credentials

- [ ] Go to Settings → API in Lemon Squeezy dashboard
- [ ] Create new API key
- [ ] Copy API key → `LEMON_SQUEEZY_API_KEY`
- [ ] Copy Store ID → `LEMON_SQUEEZY_STORE_ID`

### 4. Configure Environment Variables

Add to your `.env` file:

```env
LEMON_SQUEEZY_API_KEY=your_api_key
LEMON_SQUEEZY_STORE_ID=12345
LEMON_SQUEEZY_WEBHOOK_SECRET=your_webhook_secret

LEMON_SQUEEZY_LAUNCH_MONTHLY_VARIANT_ID=
LEMON_SQUEEZY_LAUNCH_YEARLY_VARIANT_ID=
LEMON_SQUEEZY_GROWTH_MONTHLY_VARIANT_ID=
LEMON_SQUEEZY_GROWTH_YEARLY_VARIANT_ID=
LEMON_SQUEEZY_SCALE_MONTHLY_VARIANT_ID=
LEMON_SQUEEZY_SCALE_YEARLY_VARIANT_ID=
```

- [ ] All environment variables configured

### 5. Run Database Migration

```bash
php artisan migrate
```

- [ ] Migration completed successfully

### 6. Configure Webhooks in Lemon Squeezy

**For Local Development:**

Option A: Use Laravel Expose
```bash
composer require --dev beyondcode/expose
php artisan expose share
# Use the provided URL
```

Option B: Use ngrok
```bash
ngrok http 8000
# Use the provided URL
```

**Configure webhook in Lemon Squeezy:**

- [ ] Go to Settings → Webhooks in Lemon Squeezy dashboard
- [ ] Click "+" to add webhook
- [ ] Enter URL: `https://your-domain.com/webhook/lemonsqueezy`
- [ ] Select events:
  - [ ] order_created
  - [ ] subscription_created
  - [ ] subscription_updated
  - [ ] subscription_cancelled
  - [ ] subscription_resumed
  - [ ] subscription_expired
  - [ ] subscription_paused
  - [ ] subscription_unpaused
  - [ ] subscription_payment_success
  - [ ] subscription_payment_failed
  - [ ] subscription_payment_recovered
- [ ] Save webhook
- [ ] Copy webhook signing secret → `LEMON_SQUEEZY_WEBHOOK_SECRET` in `.env`
- [ ] Update `.env` with webhook secret
- [ ] Restart application

### 7. Test Webhook Integration

#### Test subscription creation:
```bash
curl -X POST http://localhost:8000/webhook/lemonsqueezy \
  -H "Content-Type: application/json" \
  -H "X-Signature: test_signature_for_local_testing" \
  -d @tests/webhooks/subscription_created.json
```

- [ ] Subscription created webhook works
- [ ] User subscription tier updated
- [ ] Credits assigned correctly

#### Test subscription cancellation:
```bash
curl -X POST http://localhost:8000/webhook/lemonsqueezy \
  -H "Content-Type: application/json" \
  -H "X-Signature: test_signature_for_local_testing" \
  -d @tests/webhooks/subscription_cancelled.json
```

- [ ] Subscription cancelled webhook works
- [ ] User status updated to cancelled

#### Test from Lemon Squeezy dashboard:
- [ ] Send test webhook from Lemon Squeezy dashboard
- [ ] Check application logs: `tail -f storage/logs/laravel.log`
- [ ] Verify webhook signature validation works

### 8. Test Checkout Flow

- [ ] Visit `/subscription/plans` in your application
- [ ] Click "Upgrade" on a plan
- [ ] Verify redirect to Lemon Squeezy checkout
- [ ] Complete test purchase
- [ ] Verify webhook received
- [ ] Verify user subscription updated
- [ ] Verify credits assigned

### 9. Test Subscription Management

- [ ] View subscription portal at `/subscription/portal`
- [ ] Test subscription cancellation
- [ ] Verify cancellation webhook received
- [ ] Test subscription resumption (if applicable)

### 10. Production Deployment

- [ ] Update webhook URL to production domain
- [ ] Disable test mode in Lemon Squeezy
- [ ] Test with real card (you'll be refunded)
- [ ] Monitor logs for any errors
- [ ] Set up error alerting

### 11. Optional Enhancements

- [ ] Add email notifications for subscription events
- [ ] Implement grace period for failed payments
- [ ] Add subscription upgrade/downgrade flow
- [ ] Create customer portal using Lemon Squeezy customer portal URL
- [ ] Add invoice history retrieval
- [ ] Implement refund handling
- [ ] Add analytics tracking for subscription events

## 🔍 Verification Commands

Check user subscription status:
```bash
php artisan tinker
>>> $user = User::find(1);
>>> $user->subscription_tier
>>> $user->lemonsqueezy_subscription_id
>>> $user->subscription_status
>>> $user->credits_remaining
```

Check webhook logs:
```bash
tail -f storage/logs/laravel.log | grep "Lemon Squeezy"
```

## 📚 Additional Resources

- [Lemon Squeezy API Documentation](https://docs.lemonsqueezy.com/api)
- [Lemon Squeezy Webhooks](https://docs.lemonsqueezy.com/guides/developer-guide/webhooks)
- [Project Documentation](./LEMON_SQUEEZY_INTEGRATION.md)

## 🆘 Troubleshooting

**Webhooks not working:**
1. Check firewall settings
2. Verify webhook URL is publicly accessible
3. Check Lemon Squeezy webhook logs in dashboard
4. Verify X-Signature header is present
5. Check application logs for errors

**Signature verification failing:**
1. Ensure webhook secret matches exactly
2. Don't modify request body before verification
3. Check you're using raw request body

**User not updating:**
1. Verify user_id is in custom_data
2. Check webhook handler isn't throwing exceptions
3. Review database transaction logs
4. Check user model has fillable fields

## ✨ Success Criteria

Integration is complete when:
- [x] Database migration successful
- [x] Lemon Squeezy service created
- [x] Webhook handler implemented
- [x] Routes configured
- [ ] All environment variables set
- [ ] Webhooks configured in Lemon Squeezy
- [ ] Test webhook successful
- [ ] Test purchase successful
- [ ] User subscription updates correctly
- [ ] Credits assigned correctly
