# SnapDraft MVP - Pre-Launch TODO

> **Goal**: Fix all blockers and ship to 20 beta users
> **Assessed**: April 5, 2026
> **Overall readiness**: 6.5/10 - Core is solid, needs production hardening

---

## 🔴 Phase 1 - Critical Blockers (Day 1)

These 3 issues make the app **unsafe to launch**. Fix them first.

### 1.1 Credits are never deducted
- [x] Add `$project->user->useCredit()` in `GenerateSingleImageJob::handle()` BEFORE the AI call
- [x] Add credit refund in the `catch` block on generation failure
- [x] Add `catch()` callback to `Bus::batch()` in `DispatchGenerationBatchJob` for batch-level failure accounting
- [x] Add credit deduction to `ImageEditController` methods (canvas operations: erase, AI edit, text replace)
- [x] Verify `User::useCredit()` and `Subscription::useCredits()` methods work correctly
- **Files**: `app/Jobs/GenerateSingleImageJob.php`, `app/Jobs/DispatchGenerationBatchJob.php`, `app/Http/Controllers/ImageEditController.php`

### 1.2 Image edit API routes missing authorization + credit checks
- [x] Add `has.credits` middleware to all `/api/*` canvas routes in `routes/web.php`
- [x] Add `$this->authorize('update', $image)` checks in `ImageEditController` methods
- [x] Add `throttle.user` rate limiting to these routes
- **Files**: `routes/web.php` (lines ~150-165), `app/Http/Controllers/ImageEditController.php`

### 1.3 Test routes exposed to all users
- [x] Move `/test/cluster-generation/*` routes behind `admin` middleware
- **Files**: `routes/web.php` (lines ~66-71)

---

## 🟠 Phase 2 - High Priority (Day 2-3)

### 2.1 Security hardening
- [x] Remove `testInpaint()` method from `ImageEditController.php`
- [x] Add max file size validation (10MB) to all canvas/image edit endpoints
- [x] Add ZIP download total size cap in `ImageController::bulkDownload()`
- [x] Fix SSL verification pattern in `SubscriptionController.php` - replace `verify: false` with proper cert config
- [x] Add admin audit logging for: suspend, reactivate, impersonate, credit adjustment, user delete
- **Files**: `app/Http/Controllers/ImageEditController.php`, `app/Http/Controllers/CanvasController.php`, `app/Http/Controllers/ImageController.php`, `app/Http/Controllers/SubscriptionController.php`, `app/Http/Controllers/AdminDashboardController.php`

### 2.2 Config & DevOps
- [x] Create `.env.example` with all required env vars:
  - `GEMINI_API_KEY`, `OPENROUTER_API_KEY`
  - `LEMON_SQUEEZY_API_KEY`, `LEMON_SQUEEZY_STORE_ID`, `LEMON_SQUEEZY_WEBHOOK_SECRET`
  - `LEMON_SQUEEZY_BETA_MONTHLY_VARIANT_ID`, `LEMON_SQUEEZY_BETA_YEARLY_VARIANT_ID`
  - `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
  - DB, mail, queue, cache, app URL
- [ ] Set authorized redirect URI in Google Cloud Console: `{APP_URL}/auth/google/callback`
- [ ] Create product + 2 variants in LemonSqueezy dashboard → paste variant IDs into `.env`
- [ ] Run `php artisan db:seed --class=PlanSeeder`

### 2.3 Debug code cleanup
- [x] Wrap 200+ `debug.log` calls in `canvas-editor.tsx` with dev-only check (use same pattern as CSV wizard: `if (!import.meta.env.DEV) return;`)
- [x] Remove/wrap ~20 `console.error` statements in: `canvas-editor.tsx`, `quick-generate/`, `projects/show.tsx`, `projects/index.tsx`, `search.tsx`
- [x] Reduce verbose logging in `QuickGenerateController.php` (emoji-laden Log::info calls)
- [x] Redact PII from webhook payload logging in `LemonSqueezyController.php`
- **Files**: `resources/js/pages/canvas-editor.tsx`, `resources/js/pages/quick-generate/`, `resources/js/pages/projects/show.tsx`, `app/Http/Controllers/QuickGenerateController.php`, `app/Http/Controllers/Webhooks/LemonSqueezyController.php`

### 2.4 Error handling
- [x] Wire up `JobFailedNotification` - dispatch email to user when `GenerateSingleImageJob` fails
- [x] Add global React error boundary in `resources/js/app.tsx`
- [x] Fix empty catch blocks in `resources/js/pages/projects/show.tsx`

---

## 🟡 Phase 3 - Polish (Day 4-5)

### 3.1 Admin panel improvements
- [x] Add Plans, Subscriptions, Projects to admin sidebar nav in `resources/js/layouts/admin-layout.tsx`
- [x] Add impersonation exit banner to `app-sidebar-layout.tsx` (when session has `impersonating_user_id`)

### 3.2 Stub implementations
- [x] Implement `CleanOrphanedFilesJob` - delete storage files not referenced by any DB record
- [x] Update `SubscriptionController::purchaseCredits()` - either implement credit packs or show clear "resets monthly" messaging

### 3.3 UX polish
- [ ] Verify empty states on all key pages (no projects, no images, no subscriptions)
- [ ] Verify "Upgrade" CTA appears when credits exhausted (not dead-end)
- [ ] Test CSV wizard with 5, 10, 25 row CSVs

---

## 🔵 Phase 4 - Production Deploy (Day 5-7)

### 4.1 Infrastructure
- [ ] Set up production server (Forge, Vapor, or VPS)
- [ ] Configure queue worker (Redis preferred) with process supervisor
- [ ] Configure file storage (S3 or DigitalOcean Spaces - NOT local disk)
- [ ] Configure email (Postmark or Resend)
- [ ] SSL certificate + custom domain
- [ ] Set up `.env.production` with all API keys

### 4.2 Monitoring
- [ ] Install Sentry: `composer require sentry/sentry-laravel`
- [ ] Configure Sentry DSN in `.env`
- [ ] Set up uptime monitoring (UptimeRobot free tier)

### 4.3 Final checks
- [ ] Run `php artisan migrate` on production
- [ ] Run plan seeder on production
- [ ] Run `npm run build` (or `npm run build:ssr`)
- [ ] Test full flow: register → create project → CSV wizard → results → download
- [ ] Test LemonSqueezy webhook on production
- [ ] Test Google OAuth on production
- [ ] Set up daily database backups
- [ ] Run `composer test` - fix any failures

---

## 🟣 Phase 5 - Beta Launch (Day 7-8)

- [ ] Create 20 invite codes or open registration
- [ ] Prepare onboarding email (welcome + quick start guide)
- [ ] Reach out to 20 target users personally
- [ ] Set up feedback channel (email alias, Typeform, or in-app widget)
- [ ] Monitor first 48 hours: logs, Sentry, queue, Gemini API costs, signups

---

## ✅ Already Done (No changes needed)

- [x] AI service architecture (primary/fallback, retry, exponential backoff)
- [x] 3-phase CSV pipeline (AnalyzeBrand → MatchCaptions → DispatchGeneration)
- [x] Models/ORM (relationships, accessors, soft deletes, lifecycle hooks)
- [x] Database migrations (clean, chronological, no duplicates)
- [x] Authentication (Fortify + 2FA + Google OAuth)
- [x] Authorization policies (Project, Image ownership checks)
- [x] CSRF exemption for webhooks
- [x] Sidebar navigation (cut features hidden)
- [x] Dashboard (stats, credits, empty state)
- [x] CSV wizard console.logs (dev-gated)
- [x] Marketing homepage with pricing
- [x] Plan seeder (single beta plan)
- [x] Webhook signature verification (HMAC)
- [x] Admin panel (7 pages - users, credits, subscriptions, plans, projects, analytics, dashboard)

---

## Post-Launch Backlog

- [ ] Accessibility audit (WCAG 2.1 AA - canvas editor gaps)
- [ ] Performance audit (Lighthouse scores)
- [ ] Rate limiting enforcement in AI services
- [ ] Role-based admin permissions
- [ ] Custom date range for admin analytics
- [ ] CSV export from admin analytics
- [ ] Quick Generate feature (deferred from beta)
- [ ] Additional pricing tiers
- [ ] Canvas: full editing suite (outpaint, upscale, remove BG)
- [ ] Team/multi-seat support