# SnapDraft Beta — Master TODO

> **Goal**: Ship to 20 beta users in 1-2 weeks  
> **Rule**: Don't delete code files. Hide features from UI. Delete only junk/orphan files.  
> **Reference**: `docs/LEAN_MVP_BETA.md` for full strategy

---

## Phase 1 — Delete Junk (Day 1, ~2 hours)

### Root directory cleanup
- [x] Delete `page.php` (WordPress template — nothing to do with this project)
- [x] Delete `read_log.php` (security risk — exposes Laravel logs without auth)
- [x] Delete `brankit.md` (scratch notes)
- [x] Delete `DYNAMIC_IMPORT_FIX.md` (bug fix diary)
- [x] Delete `QUICK_START.txt` (Zubaz integration notes)
- [x] Delete `run-quality-tests.ps1` (test script, not needed)
- [x] Delete `test-webhook.ps1` (debug script)
- [x] Delete `ZUBAZ_ADAPTATION_COMPLETE.md`
- [x] Delete `ZUBAZ_COMPONENTS_READY.md`
- [x] Delete `ZUBAZ_FINAL_REPORT.md`
- [x] Delete `ZUBAZ_FIX_SUMMARY.md`
- [x] Delete `ZUBAZ_INTEGRATION_STATUS.md`

### Docs folder cleanup
- [x] Keep: `LEAN_MVP_BETA.md`, `AI_INTEGRATION.md`, `TECHNICAL_ARCHITECTURE.md`
- [x] Delete everything else in `docs/` (46 AI-generated implementation diaries)
- [x] Delete `docs/prototypes/` folder entirely

### Zubaz website template cleanup
- [x] Delete `resources/js/pages/website/assets/` (entire folder — jQuery/Bootstrap/fonts dead weight)
- [x] Delete `resources/js/pages/website/components/` (purchased template components, unused)
- [x] Delete `resources/js/pages/website/design-test.tsx` (test page)
- [x] Delete `resources/js/pages/website/index-03.html` (raw HTML prototype)
- [x] Delete `resources/js/pages/website/page.js` (dead JS file)
- [x] Keep: `resources/js/pages/website/page.tsx` (homepage), `startup.tsx` (can hide from nav)

### Stray env files
- [x] Delete `.env.lemonsqueezy` if it exists (stray env file)

---

## Phase 2 — Fix Broken Things (Day 1-2, ~4 hours)

### Critical fixes
- [x] Fix `BrandAnalysisWizardController.php` — references `$this->gpt52Analyzer` which is never injected (will crash). Either inject it properly or disable the controller entirely (it's being cut from beta anyway)
- [x] Remove hardcoded `http://127.0.0.1:8000/storage/` URLs in `BrandAnalysisWizardController.php` (2 places) — replace with `Storage::url()` or `asset()`
- [x] Fix duplicate migration: `2025_11_09_140122_add_missing_performance_indexes.php` and `2025_11_09_140159_add_missing_performance_indexes.php` — one of these needs to be deleted or made idempotent

### Dead code removal
- [x] Delete `app/Http/Controllers/LemonSqueezyWebhookController.php` (~497 lines, dead — not used by any route)
- [x] Delete `app/Http/Controllers/Payment/LemonSqueezyController.php` (~482 lines, dead — not used by any route)
- [x] Keep only `app/Http/Controllers/Webhooks/LemonSqueezyController.php` (this is the one `routes/webhooks.php` actually uses)

### Dead dependencies
- [x] Remove `paddlehq/paddle-php-sdk` from `composer.json` (you use LemonSqueezy, not Paddle)
- [x] Remove `php-http/guzzle7-adapter` from `composer.json` (Paddle dependency)
- [x] Run `composer update` after removing

### Console.log cleanup (47 instances across 7 files)
- [x] Clean `resources/js/pages/subscription/plans.tsx` (15 console.logs)
- [x] Clean `resources/js/pages/canvas-editor.tsx` (16 console.logs)
- [x] Clean `resources/js/pages/quick-generate/index.tsx` (12 console.logs)
- [x] Clean remaining 4 files with scattered console.logs
- [x] Quick way: search project for `console.log` and remove all
  - Note: 2 remain in csv.tsx and csv-processing.tsx but both are DEV-only guarded (intentional)

### Test route removal
- [x] Remove `Route::get('/test/gemini-inpaint', ...)` from `routes/web.php`

---

## Phase 3 — Hide Cut Features from UI (Day 2-3, ~6 hours)

### Sidebar navigation
- [x] Open sidebar component (likely in `resources/js/layouts/` or `resources/js/components/`)
- [x] Remove/comment nav links for: Images Wizard, Text Wizard, Brand Kit, Brand Analysis, Simple Wizard, Quick Generate
- [x] Remove/comment nav links for: Search, Updates
- [x] Keep nav links for: Dashboard, Projects, Settings, Admin (if admin user)

### Project Create page
- [x] Edit `resources/js/pages/projects/create.tsx`
- [x] Show ONLY the CSV Wizard card — hide/remove cards for: Images, Text, Brand Kit, Brand Analysis
- [x] Or: redirect `/projects/create` straight to `/projects/create/csv` ← implemented

### Canvas Editor — hide extra operations
- [x] Investigated `canvas-editor.tsx` — Expand/Upscale/Remove BG are keyboard-shortcut-only (keys X, S, B), NOT toolbar buttons. AI Tools sidebar already shows only: Erase, AI Edit, Replace Text. No changes needed.

### Settings page
- [x] Remove Appearance tab from settings nav
- [x] Keep: Profile, Password, Two-Factor

### Admin panel
- [x] Hide nav items for: Analytics, Plans, Subscriptions, Projects
- [x] Keep: Dashboard (overview), Users, Credits, Usage

### Routes (optional — code stays, just remove from nav)
- [x] Left routes active but hidden from nav (less disruptive, lower blast radius)

---

## Phase 4 — Simplify Billing (Day 3-4, ~4 hours)

### Single plan setup
- [x] Create a database seeder for the single "SnapDraft Beta" plan ($29/mo, 100 credits, 10 projects, 25 CSV rows, 7-day trial)
- [x] Simplify `resources/js/pages/subscription/plans.tsx` — single-plan card instead of 3-tier grid
- [x] Updated `SubscriptionController` subtitle/bestFor/webhook fallback for 'beta' slug
- [x] Updated `config/services.php` variants — 2 keys: `beta_monthly`, `beta_yearly`
- [x] Added `LEMON_SQUEEZY_BETA_MONTHLY_VARIANT_ID` and `LEMON_SQUEEZY_BETA_YEARLY_VARIANT_ID` to `.env` (fill in from LemonSqueezy dashboard)
- [ ] **ACTION REQUIRED**: Create product + 2 variants in [LemonSqueezy dashboard](https://app.lemonsqueezy.com) and paste variant IDs into `.env`
- [ ] Run `php artisan db:seed --class=PlanSeeder` once MySQL/Laragon is running
- [ ] Test checkout flow: click "Start Free Trial" → LemonSqueezy checkout page
- [ ] Verify webhook: `subscription_created` → subscription row created in DB
- [ ] Test credit deduction: generate image → credits decrease

---

## Phase 5 — Add Google OAuth (Day 4-5, ~4 hours)

### Install & configure
- [x] `composer require laravel/socialite` — installed v5.24.3
- [x] Add to `config/services.php`: google client_id, client_secret, redirect
- [x] Add to `.env`: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` (placeholders added — fill from Google Cloud Console)

### Backend
- [x] Created `app/Http/Controllers/Auth/SocialAuthController.php` with `redirect()` and `callback()` methods
- [x] Added routes: `GET /auth/google` → redirect, `GET /auth/google/callback` → handle (guest middleware)
- [x] Handles: new user creation, existing email account linking, avatar sync
- [x] Added `google_id` + `avatar` columns migration (`2026_03_06_133910_add_google_id_to_users_table.php`), password made nullable

### Frontend
- [x] "Continue with Google" button added to `login.tsx` (with Google SVG logo + divider)
- [x] "Continue with Google" button added to `register.tsx` (with Google SVG logo + divider)

### Manual actions required before testing
- [x] **ACTION REQUIRED**: Go to [Google Cloud Console](https://console.cloud.google.com/) → Create OAuth 2.0 credentials → paste `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` into `.env`
- [ ] Set Authorized redirect URI in Google Cloud Console to: `{APP_URL}/auth/google/callback`
- [x] Run migration once MySQL/Laragon is running: `php artisan migrate`

---

## Phase 6 — Polish & Test (Day 5-7, ~6 hours)

### Dashboard polish
- [x] Empty state for new user already implemented (CTA to create first project)
- [x] Fixed `credits_percentage` — was previously showing remaining% with label "Credits Used" (bug). Now correctly shows used%
- [x] Fixed `credits_used` — guarded against negative values in controller
- [x] Fixed `is_low_credits` — now triggers at < 20% remaining (not < 20% used)
- [x] Added `'beta'` tier badge to `getTierBadge()` in dashboard

### Error handling
- [x] Credit exhaustion (`EnsureUserHasCredits` middleware) now redirects to `/subscription/plans` instead of dead-end `back()`
- [x] CSV wizard error bar now shows **"Upgrade →"** button when error mentions credits/upgrade/subscribe
- [x] Added `'beta'` to `SubscriptionService::getProcessingPriority()`

### Create sample content
- [x] Sample CSV template already available via "Download CSV Template" button in the wizard (step 2)

### Manual testing checklist (requires running app)
- [ ] Test with 5-row CSV → all images generated successfully
- [ ] Test with 25-row CSV (max for beta plan)
- [ ] Test with malformed CSV → clear error message shown
- [ ] Test with missing required columns → clear validation error
- [ ] Test with JPG, PNG, WebP reference images
- [ ] Test progress polling → real-time updates shown
- [ ] Test result page → images display, single + bulk download works
- [ ] Test text replace in Canvas Editor → works end-to-end
- [ ] Test AI edit in Canvas Editor → works end-to-end
- [ ] Test erase in Canvas Editor → works end-to-end
- [ ] Test brand analysis failure → graceful fallback to generation without brand DNA
- [ ] Verify no NaN or 0/0 errors on fresh user dashboard

---

## Phase 7 — Marketing Homepage (Day 6-7, ~4 hours)

- [x] Created `resources/js/pages/website/home.tsx` — clean Tailwind marketing homepage (rendered at `/`)
- [x] Hero section: "Brand-consistent visuals from your CSV, in minutes" with mock UI card and background gradients
- [x] 3-step process visual: Upload brand refs → Upload CSV → Get visuals
- [x] Pricing section: single plan ($29/mo), trial CTA with full feature list
- [x] CTA buttons: "Start Free Trial" + "See how it works" → register page
- [x] Social proof placeholder (stat bar with 10x, 25+, 100%, 7-day metrics)
- [x] Footer: Features, How it works, Pricing, FAQ, Sign in, Create account links
- [x] Fixed `page.tsx` — removed all broken Zubaz component imports
- [x] Fixed `startup.tsx` — replaced `<style jsx>` with `<style dangerouslySetInnerHTML>` to resolve TS error

---

## Phase 8 — Production Deployment (Day 7-8)

### Infrastructure
- [ ] Set up production server (Forge, Vapor, or VPS)
- [ ] Configure queue worker (Redis preferred — ensure jobs survive restarts)
- [ ] Configure file storage (S3 or DigitalOcean Spaces — NOT local disk)
- [ ] Configure email (Postmark or Resend — for verification, welcome, invoices)
- [ ] SSL certificate + custom domain
- [ ] Set up `.env.production` with all API keys:
  - `GEMINI_API_KEY`
  - `LEMON_SQUEEZY_API_KEY`, `LEMON_SQUEEZY_STORE_ID`, `LEMON_SQUEEZY_WEBHOOK_SECRET`
  - `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`
  - Mail credentials
  - S3 credentials

### Error tracking & monitoring
- [ ] Install Sentry (`composer require sentry/sentry-laravel`)
- [ ] Configure Sentry DSN in `.env`
- [ ] Set up uptime monitoring (UptimeRobot — free tier)

### Final checks
- [ ] Run `php artisan migrate` on production
- [ ] Run plan seeder on production
- [ ] Run `npm run build` (or `npm run build:ssr`)
- [ ] Test full flow on production: register → create project → CSV wizard → results → download
- [ ] Test LemonSqueezy webhook on production
- [ ] Test Google OAuth on production
- [ ] Set up daily database backups

---

## Phase 9 — Beta Launch (Day 8-10)

- [ ] Create 20 invite codes OR open registration with waitlist
- [ ] Prepare onboarding email (welcome + quick start guide)
- [ ] Reach out to 20 target users personally
- [ ] Set up feedback channel (email alias, Typeform, or in-app widget)
- [ ] Monitor first 48 hours closely:
  - [ ] Check Laravel logs for errors
  - [ ] Check Sentry for unhandled exceptions
  - [ ] Check queue for stuck/failed jobs
  - [ ] Check Gemini API usage/costs
  - [ ] Check user signups and activations

---

## Quick Reference — What NOT to Touch

These features are **cut from beta but code stays**. Don't delete, just hide from navigation:

| Feature | Files (leave in place) |
|---|---|
| Images Wizard | `ImagesWizardController.php`, `resources/js/pages/projects/wizards/images.tsx` |
| Text Wizard | `TextWizardController.php`, `resources/js/pages/projects/wizards/text.tsx` |
| Brand Kit Wizard | `BrandKitWizardController.php`, `resources/js/pages/projects/wizards/brand-kit.tsx` |
| Brand Analysis | `BrandAnalysisWizardController.php`, `resources/js/pages/projects/wizards/brand-analysis.tsx` |
| Simple Text Wizard | `SimpleTextWizardController.php`, `resources/js/pages/simple-wizard/index.tsx` |
| Quick Generate | `QuickGenerateController.php`, `resources/js/pages/quick-generate/*` |
| Search | `SearchController.php`, `resources/js/pages/search.tsx` |
| Canvas extras | Outpaint/Upscale/RemoveBG/Resize/GenFromPrompt code in `canvas-editor.tsx` |
