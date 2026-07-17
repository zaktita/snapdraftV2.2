<wizard-report>
# PostHog post-wizard report

The wizard has completed a deep integration of PostHog analytics into Snapdraft. Both server-side (PHP SDK) and client-side (posthog-js) tracking are in place, with user identity correlation between the two layers.

**What was done:**

- Installed `posthog/posthog-php` (v4) via Composer and `posthog-js` via npm
- Created `config/posthog.php` for environment-based configuration
- Created `app/Services/PostHogService.php` - a singleton wrapper around the PHP SDK with `capture`, `identify`, and `shutdown` methods
- Registered `PostHogService` as a singleton in `app/Providers/AppServiceProvider.php`
- Updated `app/Http/Middleware/HandleInertiaRequests.php` to share the PostHog token and host with the React frontend via Inertia shared props
- Updated `resources/js/app.tsx` to initialize `posthog-js` on page load and identify the currently authenticated user, correlating client-side and server-side events by user ID
- Added `PostHog::identify()` on registration (email and Google OAuth) so user profiles are created immediately on signup
- Added `PostHog::capture()` calls in 8 controller/action files covering 11 distinct business events

| Event | Description | File |
|---|---|---|
| `user_signed_up` | New account via email registration | `app/Actions/Fortify/CreateNewUser.php` |
| `user_signed_up` (Google) | New account via Google OAuth | `app/Http/Controllers/Auth/SocialAuthController.php` |
| `subscription_created` | Paid subscription confirmed via webhook | `app/Http/Controllers/Webhooks/LemonSqueezyController.php` |
| `subscription_cancelled` | Subscription cancellation via webhook | `app/Http/Controllers/Webhooks/LemonSqueezyController.php` |
| `subscription_payment_failed` | Payment failure via webhook | `app/Http/Controllers/Webhooks/LemonSqueezyController.php` |
| `upgrade_checkout_started` | User initiated upgrade checkout | `app/Http/Controllers/SubscriptionController.php` |
| `project_created` | User created a new project | `app/Http/Controllers/ProjectController.php` |
| `project_deleted` | User deleted a project | `app/Http/Controllers/ProjectController.php` |
| `csv_generation_started` | CSV batch image generation dispatched | `app/Http/Controllers/Wizards/CSVWizardController.php` |
| `image_regenerated` | Single image regeneration triggered | `app/Http/Controllers/ImageController.php` |
| `beta_invite_redeemed` | Beta invite code successfully redeemed | `app/Http/Controllers/BetaInviteController.php` |

## Next steps

We've built some insights and a dashboard for you to keep an eye on user behavior, based on the events we just instrumented:

- **Dashboard - Analytics basics**: https://us.posthog.com/project/378849/dashboard/1457900
- **New Signups (Daily)**: https://us.posthog.com/project/378849/insights/AARAvCQY
- **Subscription Health (Weekly)**: https://us.posthog.com/project/378849/insights/37mz2W6O
- **Core Generation Activity (Daily)**: https://us.posthog.com/project/378849/insights/N9BVRunT
- **Revenue Conversion Funnel**: https://us.posthog.com/project/378849/insights/yoYrYBYN
- **Beta & Upgrade Pipeline (Weekly)**: https://us.posthog.com/project/378849/insights/7b2DfsbI

### Agent skill

We've left an agent skill folder in your project. You can use this context for further agent development when using Claude Code. This will help ensure the model provides the most up-to-date approaches for integrating PostHog.

</wizard-report>
