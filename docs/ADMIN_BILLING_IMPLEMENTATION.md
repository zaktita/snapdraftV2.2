# Admin & Billing System Implementation

## Overview
Complete admin panel and subscription/billing system with three tiers (Free, Pro, Enterprise), credits-based usage limits, and user management capabilities.

## Database Changes

### Migration: `add_admin_and_subscription_fields_to_users_table`
Added 12 new columns to `users` table:
- `is_admin` (boolean, default false)
- `subscription_tier` (string, default 'free')
- `credits_remaining` (integer, default 10)
- `credits_total` (integer, default 10)
- `subscription_started_at` (timestamp, nullable)
- `subscription_ends_at` (timestamp, nullable)
- `stripe_customer_id` (string, nullable)
- `stripe_subscription_id` (string, nullable)
- `total_generations` (integer, default 0)
- `last_generation_at` (timestamp, nullable)
- `is_suspended` (boolean, default false)
- `suspension_reason` (text, nullable)

## Subscription Tiers

| Tier       | Credits/Month | Price  | Features                                      |
|------------|---------------|--------|-----------------------------------------------|
| Free       | 10            | $0     | Basic features, limited generations           |
| Pro        | 100           | $29/mo | Priority support, batch processing, templates |
| Enterprise | Unlimited     | $99/mo | Dedicated support, API access, custom models  |

## Backend Components

### Models
- **User Model** (`app/Models/User.php`)
  - Added methods: `isAdmin()`, `hasCredits()`, `useCredit()`, `resetMonthlyCredits()`
  - Fillable fields include subscription data
  - Hidden fields for Stripe IDs

### Controllers

#### AdminDashboardController (`app/Http/Controllers/Admin/AdminDashboardController.php`)
- **index()** - Dashboard with platform statistics
  - Total users, active users, projects
  - Generation stats (success/fail rates)
  - Cost tracking
  - Subscription tier breakdown
  
- **users()** - User management interface
  - Search by name/email
  - Filter by tier and status
  - Pagination (50 per page)
  
- **suspendUser()** - Suspend user accounts
  - Cannot suspend admins
  - Requires suspension reason
  
- **reactivateUser()** - Remove suspension
  
- **updateUserTier()** - Change subscription level
  - Automatically resets credits based on tier
  
- **deleteUser()** - Remove user accounts
  - Cannot delete admins
  
- **usage()** - Usage analytics
  - Date range filters (24h, 7d, 30d, 90d)
  - Top users by generation count
  - Cost tracking per user

#### SubscriptionController (`app/Http/Controllers/SubscriptionController.php`)
- **index()** - Display subscription plans
  
- **portal()** - Billing portal
  - Current subscription details
  - Credit usage
  - Invoice history
  
- **upgrade()** - Change to higher tier
  - Stripe integration (TODO)
  - Immediate credit reset
  
- **downgrade()** - Cancel to free tier
  - Takes effect at period end
  
- **purchaseCredits()** - Buy additional credits
  - Stripe integration (TODO)
  
- **webhook()** - Handle Stripe webhooks
  - subscription.created/updated/deleted
  - invoice.paid/payment_failed
  - (TODO: Full implementation)

### Middleware

#### EnsureUserIsAdmin (`app/Http/Middleware/EnsureUserIsAdmin.php`)
- Checks `user->isAdmin()`
- Returns 403 if not admin
- Applied to all `/admin/*` routes

#### EnsureUserHasCredits (`app/Http/Middleware/EnsureUserHasCredits.php`)
- Checks `user->hasCredits()`
- Blocks generation requests if no credits
- Returns friendly error message
- Applied to generation endpoints

#### ThrottlePerUser (from previous implementation)
- Rate limiting: 10 requests per minute per user
- Applied to AI generation endpoints

### Routes

#### Admin Routes (`routes/admin.php`)
```
GET    /admin                          → AdminDashboardController@index
GET    /admin/users                    → AdminDashboardController@users
POST   /admin/users/{id}/suspend       → AdminDashboardController@suspendUser
POST   /admin/users/{id}/reactivate    → AdminDashboardController@reactivateUser
PUT    /admin/users/{id}/tier          → AdminDashboardController@updateUserTier
DELETE /admin/users/{id}               → AdminDashboardController@deleteUser
GET    /admin/usage                    → AdminDashboardController@usage
```

#### Subscription Routes (`routes/web.php`)
```
GET    /subscription/plans             → SubscriptionController@index
GET    /subscription/portal            → SubscriptionController@portal
POST   /subscription/upgrade           → SubscriptionController@upgrade
POST   /subscription/downgrade         → SubscriptionController@downgrade
POST   /subscription/purchase-credits  → SubscriptionController@purchaseCredits
POST   /webhook/stripe                 → SubscriptionController@webhook (outside auth)
```

### Jobs Integration

#### GenerateSingleImageJob (Updated)
- Added credit check at start of `handle()`
- Calls `user->useCredit()` before generation
- Creates failed generation history if no credits
- Prevents generation without credits

## Frontend Components

### Admin Pages

#### Admin Dashboard (`resources/js/pages/admin/dashboard.tsx`)
- Platform overview with key metrics
- Quick stats cards:
  - Total Users (with active count)
  - Total Projects
  - Generations (with success rate)
  - Total Cost
- Subscription tier breakdown (Free/Pro/Enterprise)
- Generation stats (Successful/Failed/Success Rate)
- Quick action buttons (Manage Users, View Usage)
- Suspended users warning alert

#### User Management (`resources/js/pages/admin/users.tsx`)
- Search by name or email
- Filter by tier (all/free/pro/enterprise)
- Filter by status (all/active/suspended)
- User table with:
  - Name, email, admin badge
  - Subscription tier badge
  - Credits (remaining/total)
  - Total generations
  - Status (Active/Suspended)
  - Join date
  - Actions menu
- Actions:
  - Suspend (with reason input)
  - Reactivate
  - Change Tier
  - Delete (confirmation dialog)
- Pagination (50 per page)

### Subscription Pages

#### Plans Page (`resources/js/pages/subscription/plans.tsx`)
- Three-column pricing table
- Current plan badge
- Credits remaining display
- Plan features list with checkmarks
- CTA buttons (Upgrade/Downgrade/Current)
- FAQ section (4 common questions)
- Contact Sales CTA

#### Billing Portal (`resources/js/pages/subscription/portal.tsx`)
- Current plan overview
  - Tier badge (color-coded)
  - Monthly credits
  - Start/renewal dates
- Credit usage section
  - Progress bar
  - Low credits warning (< 20%)
  - Purchase credits button
- Invoice history table
  - Date, description, amount, status
- Payment method card (placeholder)
- Quick stats sidebar
  - Total spent
  - Member since
- Help section

### Navigation Updates

#### User Menu (`resources/js/components/user-menu-content.tsx`)
- Added "Billing & Credits" link (all users)
- Added "Upgrade Plan" link (free tier only, with Crown icon)
- Added "Admin Dashboard" section (admins only)
  - Yellow background highlight
  - Shield icon
  - Separated by divider

#### Credits Card (`resources/js/components/credits-card.tsx`)
- Dynamic tier display (Free/Pro/Enterprise)
- Color-coded gradient (gray/blue/purple)
- Tier-specific icons (Coins/Zap/Crown)
- Credits remaining with total
- Unlimited indicator (∞) for Enterprise
- Low credits warning (< 20%)
- Smart CTA button:
  - Free: "Upgrade Plan" → `/subscription/plans`
  - Pro/Enterprise: "Manage Billing" → `/subscription/portal`

## Default Values

### New User Registration (`app/Actions/Fortify/CreateNewUser.php`)
- `is_admin`: false
- `subscription_tier`: 'free'
- `credits_remaining`: 10
- `credits_total`: 10

### User Factory (`database/factories/UserFactory.php`)
Same defaults as registration for testing

### Admin User Seeder (`database/seeders/AdminUserSeeder.php`)
- Email: admin@snapdraft.com
- Password: password
- Admin: true
- Tier: enterprise
- Credits: 999999 (unlimited)

## Credits System

### Credit Allocation
- **Free**: 10 credits/month
- **Pro**: 100 credits/month
- **Enterprise**: 999999 (unlimited)

### Credit Usage Flow
1. User initiates generation
2. `EnsureUserHasCredits` middleware checks availability
3. If no credits → redirect with error
4. `GenerateSingleImageJob` runs
5. Job checks credits again (safety)
6. Calls `user->useCredit()` before generation
7. Credit decremented, `total_generations` incremented
8. `last_generation_at` timestamp updated

### Credit Reset
- Monthly reset via `resetMonthlyCredits()` method
- Sets `credits_remaining` to `credits_total` based on tier
- Can be triggered:
  - Manually by admin
  - Via scheduled task (TODO)
  - On subscription renewal

## Protection & Security

### Admin Routes
- Middleware: `auth`, `verified`, `admin`
- Cannot suspend/delete other admins
- All actions logged (TODO: implement logging)

### Generation Routes
- Middleware: `auth`, `verified`, `throttle.user:10,1`, `has.credits`
- Rate limited: 10 requests/minute per user
- Credit check at middleware AND job level

### Suspended Users
- Cannot log in
- Cannot generate content
- Suspension reason stored
- Admin-only reactivation

## Stripe Integration (TODO)

### Required Setup
1. Install Laravel Cashier: `composer require laravel/cashier`
2. Add Stripe keys to `.env`:
   ```
   STRIPE_KEY=pk_test_...
   STRIPE_SECRET=sk_test_...
   STRIPE_WEBHOOK_SECRET=whsec_...
   ```
3. Implement webhook handlers in `SubscriptionController@webhook()`
4. Create Stripe products and prices
5. Update upgrade/downgrade methods with Stripe API calls

### Webhook Events to Handle
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `invoice.paid`
- `invoice.payment_failed`
- `customer.updated`

## Next Steps

### High Priority
1. ✅ Run migration
2. ✅ Create admin seeder
3. ✅ Test admin login and dashboard
4. ⏳ Integrate Stripe for real payments
5. ⏳ Add scheduled task for monthly credit resets

### Medium Priority
- Email notifications (low credits, payment failed, subscription renewal)
- Admin activity logs
- Bulk user actions
- Export user data
- Invoice PDF generation

### Low Priority
- Feature flags system
- Usage analytics charts (Chart.js/Recharts)
- Auto-recharge options
- Gift credits feature
- Referral system

## Testing

### Manual Testing Steps
1. Log in as admin (admin@snapdraft.com / password)
2. Navigate to Admin Dashboard via user menu
3. Check statistics are displaying correctly
4. Go to User Management
5. Test search and filters
6. Try suspending a user (not admin)
7. Try changing a user's tier
8. Log in as regular user
9. Check credits card in sidebar
10. Visit Billing Portal
11. View subscription plans
12. Try generating content with/without credits

### Test Accounts
- Admin: admin@snapdraft.com / password (Enterprise, unlimited)
- Regular users: Create via registration (Free, 10 credits)

## Database Queries for Testing

```sql
-- Check all admin users
SELECT id, name, email, is_admin, subscription_tier FROM users WHERE is_admin = 1;

-- Check user credits
SELECT name, subscription_tier, credits_remaining, credits_total FROM users;

-- Check suspended users
SELECT name, email, suspension_reason FROM users WHERE is_suspended = 1;

-- Reset a user's credits
UPDATE users SET credits_remaining = credits_total WHERE id = 1;

-- Make a user admin
UPDATE users SET is_admin = 1 WHERE email = 'user@example.com';
```

## Files Changed/Created

### Created
- `database/migrations/2025_11_03_154618_add_admin_and_subscription_fields_to_users_table.php`
- `app/Http/Middleware/EnsureUserIsAdmin.php`
- `app/Http/Middleware/EnsureUserHasCredits.php`
- `app/Http/Controllers/Admin/AdminDashboardController.php`
- `app/Http/Controllers/SubscriptionController.php`
- `routes/admin.php`
- `database/seeders/AdminUserSeeder.php`
- `resources/js/pages/admin/dashboard.tsx`
- `resources/js/pages/admin/users.tsx`
- `resources/js/pages/subscription/plans.tsx`
- `resources/js/pages/subscription/portal.tsx`

### Modified
- `app/Models/User.php` - Added subscription fields and methods
- `bootstrap/app.php` - Registered middleware aliases
- `routes/web.php` - Added subscription routes and required admin routes
- `app/Jobs/GenerateSingleImageJob.php` - Added credit check and usage
- `database/factories/UserFactory.php` - Added default subscription values
- `app/Actions/Fortify/CreateNewUser.php` - Set defaults for new users
- `resources/js/components/user-menu-content.tsx` - Added billing and admin links
- `resources/js/components/credits-card.tsx` - Dynamic credit display

## Notes

- All Stripe integration is currently stubbed with TODO comments
- Payment processing requires Stripe account and products setup
- Webhook endpoint is ready but needs full implementation
- Consider implementing audit logging for admin actions
- Consider adding email notifications for billing events
- Monthly credit reset needs scheduled task implementation
