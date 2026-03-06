# SnapDraft Codebase Audit — March 6, 2026

Comprehensive analysis of errors, inconsistencies, and bugs across the entire codebase.

---

## Table of Contents

1. [TypeScript Errors](#1-typescript-errors)
2. [Critical PHP Bugs (Will Crash)](#2-critical-php-bugs)
3. [Frontend ↔ Backend Data Mismatches](#3-frontend--backend-data-mismatches)
4. [Warning-Level Issues](#4-warning-level-issues)
5. [Code Smells & Inconsistencies](#5-code-smells--inconsistencies)
6. [Dead Code & Orphaned Pages](#6-dead-code--orphaned-pages)
7. [Prioritized Fix Order](#7-prioritized-fix-order)

---

## 1. TypeScript Errors

Only **2 TS errors** in the entire codebase, both in `canvas-editor.tsx`:

| # | File | Line | Error | Impact |
|---|------|------|-------|--------|
| TS-1 | `resources/js/pages/canvas-editor.tsx` | 2699 | `setEraseSelection` is never declared — `TS2304: Cannot find name` | **Runtime crash** when user finishes an erase operation. The state setter doesn't exist. |
| TS-2 | `resources/js/pages/canvas-editor.tsx` | 3535 | `Type 'string' is not assignable to type 'number'` | Minor — TypeScript warning, works at runtime due to coercion. |

**Fix for TS-1**: Add a `useState` for erase selection or remove the `setEraseSelection(null)` call if it's dead code from a refactor.

---

## 2. Critical PHP Bugs

These will crash at runtime when the affected code path is triggered.

### C-1. Dashboard always shows 0 credits

**File**: `app/Http/Controllers/DashboardController.php` lines 75-76

```php
$creditsRemaining = $user->credits_remaining ?? 0;   // ← property doesn't exist
$creditsTotal = $user->credits_total ?? 10;           // ← property doesn't exist
```

**Problem**: `User` model has **methods** `creditsRemaining()` and `creditsTotal()`, not properties or accessors. Accessing `$user->credits_remaining` returns `null` (no DB column, no accessor), so `??` always falls through to `0` and `10`.

**Result**: Dashboard permanently shows "0 / 10 credits" regardless of actual subscription.

**Fix**: Change to `$user->creditsRemaining()` and `$user->creditsTotal()`.

---

### C-2. BrandAnalysisWizardController — 5 args, method accepts 4

**File**: `app/Http/Controllers/Wizards/BrandAnalysisWizardController.php` lines 151-157

```php
$geminiGeneration = $this->generator->generateWithReferences(
    $geminiPrompt,
    array_slice($geminiSelectedPaths, 0, 5),
    [],
    $format,
    false   // ← 5th argument — not accepted
);
```

`AIServiceManager::generateWithReferences()` only accepts 4 parameters. This throws `ArgumentCountError` when brand analysis tries to generate.

**Impact**: Brand analysis wizard is hidden from beta UI, so this is non-blocking for launch but will crash if anyone navigates to the URL directly.

---

### C-3. ImageController — missing `AIServiceManager` import

**File**: `app/Http/Controllers/ImageController.php` line 191

```php
$aiModel = app(AIServiceManager::class)->getActiveModelName();
```

No `use App\Services\AI\AIServiceManager;` import. PHP resolves `AIServiceManager::class` to `App\Http\Controllers\AIServiceManager` (current namespace), which doesn't exist → `Class not found` error.

**Impact**: Image regeneration crashes. This is the `regenerate()` method — triggered from project gallery.

---

### C-4. AdminDashboardController doesn't extend Controller

**File**: `app/Http/Controllers/Admin/AdminDashboardController.php` line 17

```php
class AdminDashboardController    // missing: extends Controller
```

Missing base class. If any authorization or base functionality is called, it will crash. Currently works for basic renders because Inertia doesn't need `authorize()`, but it's fragile.

---

### C-5. Admin `plans()` accesses non-existent Plan properties

**File**: `app/Http/Controllers/Admin/AdminDashboardController.php` lines 228-238

```php
'price_monthly' => $plan->price_monthly ?? 0,     // ← doesn't exist → null
'price_yearly'  => $plan->price_yearly ?? 0,       // ← doesn't exist → null
'features'      => $plan->features ?? [],          // ← doesn't exist → null
'credits_per_month' => $plan->credits_per_month,   // ← doesn't exist → null
'max_projects'  => $plan->max_projects ?? 0,       // ← doesn't exist → null
```

The `Plan` model has `price` (single field), and stores limits inside `capabilities` JSON. None of these directly-accessed properties exist. Admin plans page will display all `$0` and empty.

---

### C-6. BillingController — wrong column names

**File**: `app/Http/Controllers/BillingController.php`

| Access | Actual Column (Invoice model) | Result |
|--------|-------------------------------|--------|
| `$invoice->tax` | `tax_amount` | Always `null` |
| `$invoice->billing_postal_code` | `billing_zip` | Always `null` |

Tax shows as $0 and postal code is blank on all invoices.

---

## 3. Frontend ↔ Backend Data Mismatches

These pages render but display incorrect/empty data because controller output doesn't match what the React page expects.

### M-1. Admin Analytics — COMPLETE MISMATCH 🔴

**Controller**: `AdminDashboardController@analytics` (also `@usage`)
**Page**: `resources/js/pages/admin/analytics.tsx`

| Controller passes | Page expects |
|---|---|
| `signups` | `signup_trend` |
| `generations` | `generation_trend` |
| `revenue` | `revenue_trend` |
| `totals: { signups, generations, revenue }` | `summary: { total_revenue, revenue_period, total_generations, period_generations, new_users_period, active_subs }` |

**Result**: Every chart renders empty. All summary stats show `undefined`.

---

### M-2. Admin Subscriptions — FIELD NAME MISMATCH 🔴

**Controller**: `AdminDashboardController@subscriptions`
**Page**: `resources/js/pages/admin/subscriptions.tsx`

Frontend expects fields the controller doesn't pass:
- `name` — not passed
- `billing_period` — not passed  
- `price` — controller sends `amount` instead
- `renews_at` — controller sends `next_billing` instead
- `cancelled_at`, `cancellation_reason`, `lemonsqueezy_id` — not passed
- `credits_remaining`, `credits_used`, `credits_limit` — not passed

**Result**: Subscription table renders mostly empty cells.

---

### M-3. Admin Plans — STRUCTURAL MISMATCH 🔴

**Controller**: `AdminDashboardController@plans`
**Page**: `resources/js/pages/admin/plans.tsx`

Controller passes `price_monthly`/`price_yearly`/`features`/`credits_per_month` (none of which exist on the model anyway — see C-5).
Page expects `price`, `currency`, `billing_cycle`, `is_featured`, `has_trial`, `trial_days`, `capabilities`, `provider_variant_monthly`, etc.

**Result**: Plan cards show `$undefined`, missing trial badges, empty capabilities.

---

### M-4. Admin Projects — Partial Mismatch ⚠️

**Controller**: `AdminDashboardController@projects`
**Page**: `resources/js/pages/admin/projects.tsx`

| Issue | Detail |
|---|---|
| Controller passes `generations` | Page expects `images_count` |
| Controller passes `$p->generation_count` | Not a real column — always `null` |
| Controller doesn't pass `title` or `format` | Page expects both |

**Result**: Images column shows `undefined`. Title fallback to `name` works.

---

### M-5. Projects Index — Flash Message Never Shows ⚠️

**File**: `resources/js/pages/projects/index.tsx` line 82

```tsx
const { success } = pageProps;  // ← always undefined
```

Flash messages flow through `HandleInertiaRequests` as `flash.success`, not as top-level `success`. The success alert after deleting a project never displays.

---

### M-6. HandleInertiaRequests vs SharedData Type ⚠️

**File**: `resources/js/types/index.d.ts` — `SharedData` interface

Missing type definitions for:
- `flash: { success, error, warning }` — shared by middleware but not typed
- `error` (top-level) — shared by middleware but not typed  
- `impersonating` — shared by middleware but not typed

The `[key: string]: unknown` catch-all prevents TS errors but kills autocomplete.

---

## 4. Warning-Level Issues

### W-1. `now()->parse()` in LemonSqueezy webhook handler

**File**: `app/Http/Controllers/Webhooks/LemonSqueezyController.php` — 11 occurrences

```php
'starts_at' => $attributes['created_at'] ? now()->parse($attributes['created_at']) : now(),
```

`now()->parse()` technically works (Carbon instance method delegates to static), but it's misleading and wastes a `now()` allocation. Should be `Carbon::parse()`.

---

### W-2. `SubscriptionService::canUploadCsvRows()` key mismatch

**File**: `app/Services/SubscriptionService.php` line 92

```php
$csvLimit = (int) ($subscription->capabilities['csv_rows_limit'] ?? 0);
```

The plan capabilities store `csv_max_rows`. The check always evaluates to `0`, meaning CSV row limit validation never passes via this method.

---

### W-3. SearchController variable shadowing

**File**: `app/Http/Controllers/SearchController.php` lines 37-44

Outer `$query` (search term) is shadowed by inner closure parameter `$query` (Eloquent builder). Not a bug here, but confusing and fragile.

---

### W-4. `User::subscription()` is not a relationship

**File**: `app/Models/User.php` line 211

```php
public function subscription()
{
    return $this->subscriptions()->where('status', 'active')->latest()->first();
}
```

This runs a query every time it's called (no caching). Multiple calls in a request trigger N+1 queries. `HandleInertiaRequests` calls `creditsRemaining()` + `creditsTotal()` on every page load, each calling `subscription()` internally — that's 2 extra queries per page load minimum.

Should either cache the result or use the `activeSubscription` HasOne relationship.

---

### W-5. Missing `use Illuminate\Support\Str` in OpenRouterService

**File**: `app/Services/AI/OpenRouterService.php`

Uses `Str::startsWith()` without importing the facade. Will crash if the code path is hit.

---

### W-6. Image bulk download path issue

**File**: `app/Http/Controllers/ImageController.php` ~line 100

```php
$filePath = storage_path('app/' . $image->url);
```

Assumes `$image->url` is a relative path inside `storage/app/`. If the URL is actually a full URL (e.g., from S3 or `Storage::url()`), this path won't exist and images will silently not be included in the ZIP.

---

## 5. Code Smells & Inconsistencies

### S-1. Inconsistent `error` sharing

In `HandleInertiaRequests.php`, `error` is shared both as top-level `error` AND inside `flash.error`. But `success` is ONLY in `flash.success`. Pages that destructure `{ error }` as a direct prop work; pages that destructure `{ success }` as a direct prop don't.

### S-2. Canvas Editor `projectId` type mismatch

Route passes `request()->query('projectId')` (string|null), but the TSX types it as `number | undefined`. Numeric comparisons could fail silently.

### S-3. Contradictory validation

`CSVWizardController` has:
```php
'csv_file' => 'required|file|mimes:csv,txt|max:5120',
'reference_images.*' => 'required|image|max:10240|min:0',  // min:0 with required
```

`required|min:0` is contradictory — `min:0` allows zero-length files while `required` demands a file.

### S-4. Admin Dashboard passes unused data

`AdminDashboardController@index` passes `stats.admin_users` and `recent_users[].is_admin` — the frontend interface doesn't include them and they're never rendered.

### S-5. Portal subscription.id type

Controller passes integer `$activeSubscription->id` but frontend types `id` as `string` (UUID from `HasUuids` trait). Actually, since Subscription uses `HasUuids`, the ID IS a string — the controller behavior is correct, but the chain of assumptions is fragile.

### S-6. CSV result page data exposure

`CSVWizardController@result` passes the full Project Eloquent model (line ~267) which serializes `settings` containing the entire `csv_data` and `brand_analysis` JSON. This leaks potentially large data to the frontend unnecessarily.

---

## 6. Dead Code & Orphaned Pages

| File | Status | Notes |
|---|---|---|
| `resources/js/pages/subscription/index.tsx` | **Dead page** — No route renders `subscription/index` | Can be deleted |
| `resources/js/pages/website/page.tsx` | **Dead page** — No route renders `website/page` (homepage is now `website/home`) | Already gutted (returns null), safe to delete |
| `resources/js/pages/website/startup.tsx` | **Orphaned from nav** — Route exists (`/startup`) but hidden from all navigation | Keep for now per project rules |
| `settings/appearance` route | **Still registered** in `routes/web.php` | Phase 3 removed the tab from settings nav but the route is still active |
| `updates` route | **Still registered** — renders `updates` page | Hidden from nav but accessible via direct URL |

---

## 7. Prioritized Fix Order

### 🔴 Fix Immediately (Blocks Beta)

| # | Issue | Effort | Impact |
|---|-------|--------|--------|
| C-1 | Dashboard credits: `$user->credits_remaining` → `$user->creditsRemaining()` | 5 min | Dashboard shows wrong data for every user |
| C-3 | ImageController: add `use App\Services\AI\AIServiceManager;` | 1 min | Image regeneration crashes |
| TS-1 | canvas-editor: declare or remove `setEraseSelection` | 5 min | Erase tool crashes |
| W-2 | SubscriptionService: `csv_rows_limit` → `csv_max_rows` | 2 min | CSV row limit never validated |
| M-5 | Projects index: `success` prop → `usePage().props.flash?.success` | 5 min | Success messages invisible |

### 🟡 Fix Before Launch

| # | Issue | Effort | Impact |
|---|-------|--------|--------|
| C-6 | BillingController: `tax` → `tax_amount`, `postal_code` → `billing_zip` | 5 min | Invoice display wrong |
| W-1 | LemonSqueezy webhook: `now()->parse()` → `Carbon::parse()` | 15 min | Works but fragile |
| W-4 | `User::subscription()` N+1 queries — cache or use relationship | 30 min | Performance on every page |
| W-6 | Bulk download path — handle both local and S3 URLs | 20 min | ZIP downloads may be empty |
| S-1 | Standardize flash message access across all pages | 30 min | Inconsistent UX |

### 🟢 Fix When Convenient (Admin Pages)

These admin pages are only used by admins, not beta users:

| # | Issue | Effort | Impact |
|---|-------|--------|--------|
| M-1 | Admin analytics — rewrite controller output to match frontend | 1 hour | Admin charts empty |
| M-2 | Admin subscriptions — align field names | 30 min | Admin table empty |
| M-3 | Admin plans — rewrite to use actual model fields | 30 min | Admin plans display wrong |
| M-4 | Admin projects — align fields | 15 min | Admin projects partial |
| C-4 | AdminDashboardController extend Controller | 2 min | Prevents future issues |
| C-5 | Admin plans() — use `$plan->capabilities` properly | 15 min | Admin plans show $0 |

### ⚪ Low Priority

| # | Issue | Effort | Notes |
|---|-------|--------|-------|
| C-2 | BrandAnalysis 5-arg call | 5 min | Feature is hidden from beta |
| W-5 | OpenRouterService missing Str import | 1 min | Only if OpenRouter is used |
| S-2 | Canvas projectId type | 5 min | Works via JS coercion |
| S-3 | Contradictory validation | 2 min | Edge case |
| S-6 | CSV result data exposure | 15 min | Data hygiene |
| Dead pages cleanup | Delete `subscription/index.tsx`, `website/page.tsx` | 2 min | Tidiness |
