# SnapDraft PHP Code Audit Report

**Date:** 2025-01-24  
**Scope:** Controllers, Models, Services, Middleware, Routes, Config, Jobs, Policies  
**Methodology:** Static analysis of all PHP source files

---

## Summary

| Severity | Count |
|----------|-------|
| CRITICAL | 8     |
| WARNING  | 10    |
| INFO     | 8     |

---

## CRITICAL Issues

### C1. `generateWithReferences()` called with wrong argument count (ArgumentCountError)

**Files:**
- `app/Http/Controllers/Wizards/BrandAnalysisWizardController.php` — lines 151, 159, 250
- `app/Services/AI/GoogleGeminiService.php` — line 479

**Description:**  
`GoogleGeminiService::generateWithReferences()` accepts **4 parameters** (`$prompt`, `$referencePaths`, `$productImagePaths`, `$format`), but it is called with **5 arguments** in multiple places — passing an extra `false` as a 5th argument. PHP 8+ throws `ArgumentCountError` for surplus positional arguments.

Affected call sites:
```php
// BrandAnalysisWizardController (3 call sites)
$this->generator->generateWithReferences($prompt, $paths, [], $format, false);
//                                                                      ^^^^^ extra argument

// GoogleGeminiService::generateImage() line 479
return $this->generateWithReferences($prompt, [], [], $format, false);
//                                                             ^^^^^ extra argument
```

**Impact:** Fatal error at runtime when these code paths execute. Brand Analysis Wizard and `generateImage()` are completely broken.

**Fix:** Remove the 5th `false` argument from all 4 call sites, or add an optional 5th parameter to the method signature.

---

### C2. Removed User model properties still referenced (`subscription_tier`, `credits_remaining`, `credits_total`)

**Files:**
- `app/Http/Controllers/DashboardController.php` — lines 76–81
- `app/Http/Middleware/CheckCsvRowLimit.php` — line 39
- `app/Http/Middleware/CheckProjectLimit.php` — line 36

**Description:**  
Migration `database/migrations/2026_02_17_123415_remove_old_subscription_fields_from_users_table.php` drops `subscription_tier`, `credits_remaining`, `credits_total` from the `users` table. However, these properties are still accessed directly:

```php
// DashboardController.php lines 76-81
$creditsRemaining = $user->credits_remaining ?? 0;    // always null
$creditsTotal = $user->credits_total ?? 10;            // always null → defaults to 10
$subscriptionTier = $user->subscription_tier ?? 'free'; // always null → defaults to 'free'

// CheckCsvRowLimit.php line 39
$limits = SubscriptionService::getTierLimits($user->subscription_tier);
// CheckProjectLimit.php line 36
$limits = SubscriptionService::getTierLimits($user->subscription_tier);
```

**Impact:**  
- Dashboard always shows "free" tier and incorrect credits, regardless of user subscription.
- Middleware passes `null` to `getTierLimits()`, which will look up `Plan::where('slug', null)` and return the zero-everything fallback.

**Fix:**  
- `DashboardController`: Use `$user->subscription()?->creditsRemaining()` and `$user->currentTier()`.
- Middleware: Use `$user->currentTier() ?? 'free'` instead of `$user->subscription_tier`.

---

### C3. `now()->parse()` — invalid Carbon method call

**File:** `app/Http/Controllers/Webhooks/LemonSqueezyController.php` — lines 168–171, 197–198, and multiple other handler methods

**Description:**  
`now()` returns a `Carbon\Carbon` **instance**. `parse()` is a **static** method on Carbon. Calling `now()->parse(...)` throws `BadMethodCallException: Method Carbon\Carbon::parse does not exist on instance`.

```php
// Line 168-171
'starts_at' => $attributes['created_at'] ? now()->parse($attributes['created_at']) : now(),
'trial_ends_at' => $attributes['trial_ends_at'] ? now()->parse($attributes['trial_ends_at']) : null,
'renews_at' => $attributes['renews_at'] ? now()->parse($attributes['renews_at']) : null,
'ends_at' => $attributes['ends_at'] ? now()->parse($attributes['ends_at']) : null,
```

This pattern appears in:
- `handleSubscriptionCreated()` — 4 occurrences
- `handleSubscriptionUpdated()` — 2 occurrences (lines 197-198)
- Likely other handler methods that follow the same pattern.

**Impact:** All Lemon Squeezy webhook handlers crash. Subscriptions cannot be created or updated via webhooks.

**Fix:** Replace `now()->parse(...)` with `\Carbon\Carbon::parse(...)` or `Carbon::parse(...)`.

---

### C4. Invoice model column name mismatches in `BillingController`

**File:** `app/Http/Controllers/BillingController.php` — lines 34, 82

**Description:**  
The Invoice model defines columns `tax_amount` and `billing_zip`, but the controller accesses `$invoice->tax` and `$invoice->billing_postal_code`:

```php
// Line 34 — uses 'tax', model has 'tax_amount'
'tax' => $invoice->tax,

// Line 82 — uses 'billing_postal_code', model has 'billing_zip'
'billing_postal_code' => $invoice->billing_postal_code,
```

Both `$invoice->tax` and `$invoice->billing_postal_code` resolve to `null` via Eloquent's dynamic property behavior.

**Impact:** Invoice display and PDF generation show null for tax amount and postal code.

**Fix:** Use `$invoice->tax_amount` and `$invoice->billing_zip`.

---

### C5. `AdminDashboardController` does not extend `Controller`

**File:** `app/Http/Controllers/Admin/AdminDashboardController.php` — line 17

**Description:**
```php
class AdminDashboardController    // ← missing: extends Controller
```

All other controllers extend `App\Http\Controllers\Controller` which provides `$this->authorize()`, `$this->validate()`, and other helper methods.

**Impact:** If any method in this controller ever calls `$this->authorize()`, `$this->middleware()`, or other inherited methods, it will throw a fatal error. Currently the controller functions but cannot use authorization helpers, violating the pattern used across all other controllers.

**Fix:** Add `extends Controller` and import `use App\Http\Controllers\Controller;`.

---

### C6. `AdminDashboardController::plans()` references non-existent Plan model properties

**File:** `app/Http/Controllers/Admin/AdminDashboardController.php` — lines 205–214

**Description:**  
The `plans()` method accesses properties that don't exist on the `Plan` model:

| Accessed Property | Actual Column/Key |
|---|---|
| `$plan->price_monthly` | `$plan->price` (single column) |
| `$plan->price_yearly` | Does not exist (no separate yearly price column) |
| `$plan->features` | `$plan->capabilities` (JSON column) |
| `$plan->credits_per_month` | `$plan->capabilities['credits_per_month']` |
| `$plan->max_projects` | `$plan->capabilities['max_projects']` |
| `$plan->lemonsqueezy_variant_id` | `$plan->provider_variant_monthly` / `$plan->provider_variant_yearly` |

**Impact:** Admin plans page renders all plan details as null/0/empty.

**Fix:** Read `price` column directly; extract other values from the `capabilities` JSON cast; use `provider_variant_monthly` for variant ID.

---

### C7. Missing `use` import for `AIServiceManager` in `ImageController`

**File:** `app/Http/Controllers/ImageController.php` — line 191

**Description:**
```php
$aiModel = app(AIServiceManager::class)->getActiveModelName();
```

`AIServiceManager` is referenced without importing `App\Services\AI\AIServiceManager`. PHP will look for `App\Http\Controllers\AIServiceManager` (current namespace) which does not exist.

**Impact:** The `regenerate()` method throws `Class "App\Http\Controllers\AIServiceManager" not found` at runtime.

**Fix:** Add `use App\Services\AI\AIServiceManager;` to the imports.

---

### C8. `SubscriptionService::canUploadCsvRows()` uses wrong capabilities key

**File:** `app/Services/SubscriptionService.php` — line 92

**Description:**
```php
$csvLimit = (int) ($subscription->capabilities['csv_rows_limit'] ?? 0);
```

The Plan seeder and `SyncLemonSqueezyPlans` command store this as `csv_max_rows`. The migration that copies capabilities from Plan to Subscription (`2026_02_17_123415_migrate_user_subscription_data_to_subscriptions_table.php` line 54) translates it:
```php
'csv_rows_limit' => $limits['csv_max_rows'],
```

So the **Subscription** model's capabilities JSON uses `csv_rows_limit`, while the **Plan** model uses `csv_max_rows`. The key `csv_rows_limit` in `SubscriptionService` only works if the migration has run AND the subscription's capabilities were populated from the migration. Any subscription created via webhooks (which copies Plan capabilities directly) will have `csv_max_rows` instead.

**Impact:** CSV row limit check returns 0 for subscriptions created via webhooks, blocking users from uploading any CSV rows.

**Fix:** Normalize to one key name across the entire codebase, or check both keys:
```php
$csvLimit = (int) ($subscription->capabilities['csv_rows_limit'] ?? $subscription->capabilities['csv_max_rows'] ?? 0);
```

---

## WARNING Issues

### W1. `ImageController::bulkDownload()` uses wrong storage path

**File:** `app/Http/Controllers/ImageController.php` — line 111

**Description:**
```php
$filePath = storage_path('app/' . $image->url);
```

Images are stored on the `public` disk, so URLs like `projects/123/images/file.png` map to `storage/app/public/projects/...`, not `storage/app/projects/...`.

**Impact:** ZIP downloads contain no files (all `file_exists()` checks fail silently).

**Fix:** Use `Storage::disk('public')->path($image->url)` or `storage_path('app/public/' . $image->url)`.

---

### W2. `SearchController` — `$query` variable shadowing

**File:** `app/Http/Controllers/SearchController.php` — lines 26, 40–41

**Description:**  
`$query` is assigned the search string at line 28. Then at line 40, a closure parameter also named `$query` shadows the outer variable:

```php
$query = ...['q'];              // line 28: search string
->with(['images' => function ($query) {  // line 40: Eloquent builder shadows search string
    $query->orderBy('created_at', 'desc')->limit(1);
}])
```

In the current code this is not actually a bug since the outer `$query` is passed via `use ($query)` to the `where` closure separately. But it's confusing and error-prone.

**Impact:** Low — code works but is fragile and confusing to maintain.

**Fix:** Rename the closure parameter to `$q` for clarity.

---

### W3. `QuickGenerateController::store()` — contradictory validation

**File:** `app/Http/Controllers/QuickGenerateController.php` — validation rules

**Description:**
```php
'reference_images' => 'required|array|min:0|max:10',
```

`required` means the field must be present and non-empty. `min:0` means zero elements are acceptable. These contradict — `required|array` already means "at least 1 element"; `min:0` weakens that to "0 elements okay," which combined creates undefined behavior.

**Impact:** Potentially allows empty reference image arrays to pass validation.

**Fix:** Use `min:1` (or `min:3` to match CSV wizard) instead of `min:0`.

---

### W4. `User::subscription()` is not an Eloquent relationship — N+1 query risk

**File:** `app/Models/User.php` — line 203

**Description:**
```php
public function subscription()
{
    return $this->subscriptions()->where('status', 'active')->latest()->first();
}
```

This is a regular method that executes a query and returns a `Subscription|null`, NOT a relationship. Every call to `$user->subscription()` fires a new SQL query. It's called repeatedly in:
- `HandleInertiaRequests::share()` (called on every request)
- `SubscriptionService::canCreateProject()`
- `SubscriptionService::canUploadCsvRows()`
- `SubscriptionService::hasFeature()`
- `SubscriptionService::getRemainingProjectSlots()`

**Impact:** Multiple duplicate queries per request. Performance degradation.

**Fix:** Cache the result on the model instance or convert to a proper relationship with a scope.

---

### W5. `ProjectController::index()` — duplicate `withCount('images')`

**File:** `app/Http/Controllers/ProjectController.php`

**Description:**  
When sorting by `images-desc`, the query calls `withCount('images')` twice — once at the base query and again inside the sort condition.

**Impact:** Minor — duplicate eager loading, no functional issue.

---

### W6. `ProjectController::show()` — `images_count` without `withCount`

**File:** `app/Http/Controllers/ProjectController.php`

**Description:**  
The `show()` method accesses `$project->images_count` but `findOrFail()` does not call `withCount('images')`. The attribute will be `null`.

**Impact:** Frontend receives null for image count in project detail view.

**Fix:** Use `Project::withCount('images')->findOrFail($id)` or calculate from the loaded images relation.

---

### W7. `ImagesWizardController::store()` — missing `name` field on project creation

**File:** `app/Http/Controllers/Wizards/ImagesWizardController.php`

**Description:**  
When creating a project, only `title` is set, but the database migration has both `name` and `title` required columns. Compare with `CSVWizardController::store()` which sets both:
```php
// CSVWizardController (correct)
'name' => $validated['project_name'],
'title' => $validated['project_name'],

// ImagesWizardController (missing 'name')
'title' => $validated['project_name'],
```

**Impact:** Project `name` column is null unless it has a default value.

---

### W8. `GenerateBatchImagesJob` — potential Mail sending issues

**File:** `app/Jobs/GenerateBatchImagesJob.php` — `failed()` method

**Description:**  
The `failed()` method sends `JobFailedNotification` mail. While the class and Blade template exist, the `failed()` method references `$this->projectName` which is set from `$this->project->title`. If the project has been deleted before the job fails (e.g., user deleted project while job was queued), `$this->project` will be null (soft-deleted model serialization), causing a null property access.

**Impact:** Error notification cannot be sent if project was deleted. Job failure goes unnoticed.

---

### W9. `OpenRouterService::parseResponse()` uses `Str::limit()` without import

**File:** `app/Services/AI/OpenRouterService.php` — lines ~261, ~262

**Description:**
```php
Log::error('OpenRouter: No image found in response content', ['content' => Str::limit($content, 200)]);
throw new RuntimeException("No image found in OpenRouter response. Content: " . Str::limit($content, 100));
```

`Str` is used but there is no `use Illuminate\Support\Str;` import at the top of the file.

**Impact:** If the error path is triggered, a `Class 'App\Services\AI\Str' not found` error is thrown, masking the actual error.

**Fix:** Add `use Illuminate\Support\Str;` to imports.

---

### W10. `LemonSqueezyController` webhook handlers don't validate required attributes

**File:** `app/Http/Controllers/Webhooks/LemonSqueezyController.php`

**Description:**  
Handler methods like `handleSubscriptionCreated()` directly access `$data['attributes']` array keys (`customer_id`, `order_id`, `product_id`, `variant_id`, `status`, `created_at`, etc.) without checking if they exist. If Lemon Squeezy changes their webhook payload or sends an incomplete event, these will throw `Undefined array key` notices or `ErrorException`.

**Impact:** Webhook processing fragile; any payload change crashes the handler.

**Fix:** Add validation or use null-coalescing operators for all accessed attributes.

---

## INFO Issues

### I1. Empty controller directory

**Path:** `app/Http/Controllers/Payment/`

An empty directory exists with no controllers. Should be removed if unused.

---

### I2. `AdminDashboardController` — duplicate admin middleware groups in routes

**File:** `routes/admin.php`

**Description:** Two separate `Route::middleware(['auth', 'admin'])` groups exist for the admin prefix. These can be consolidated into a single group.

---

### I3. `FileUploadService` uses `\Log::` facade alias

**File:** `app/Services/FileUploadService.php`

**Description:** Uses `\Log::warning()` instead of importing `use Illuminate\Support\Facades\Log;`. While technically functional, it's inconsistent with the rest of the codebase.

---

### I4. `config/services.php` — duplicate comment

**File:** `config/services.php`

Two identical comments exist for `text_to_image_model` configuration keys.

---

### I5. `GoogleGeminiService` — constructor default vs config model inconsistency

**File:** `app/Services/AI/GoogleGeminiService.php`

The class property defaults to `'gemini-2.0-flash-exp-image-generation'` but the constructor overrides it with `config('services.gemini.text_accurate_model')`. The default value is never used.

---

### I6. `routes/website.php` is empty

Contains only a comment, no routes. Should be removed or documented as a placeholder.

---

### I7. `Plan` model has no `price_monthly`/`price_yearly` accessors

The `Plan` model stores pricing in a single `price` column with a `billing_cycle` column for period. There are no computed accessors for `price_monthly` or `price_yearly`. This forces all consumers to handle the logic themselves inconsistently.

---

### I8. `SubscriptionService` — inconsistent static vs instance methods

**File:** `app/Services/SubscriptionService.php`

All methods are `static`, which makes them impossible to mock in tests and couples all consumers to the concrete implementation. Some methods like `canCreateProject()` and `canUploadCsvRows()` internally call `$user->subscription()` (an instance method), creating a mixed paradigm.

---

## Dependency/Architecture Notes

1. **No `AIServiceInterface` enforcement:** `GoogleGeminiService` and `OpenRouterService` both have `generateWithReferences()` with the same signature, but there's no shared interface or abstract class enforcing this contract. The `AIServiceManager` checks `method_exists()` at runtime instead.

2. **Subscription model coupling:** The `User::subscription()` method being a query (not a relationship) means `$user->load('subscription')` and `$user->subscription` (magic property access) don't work as expected. This is a frequent source of confusion.

3. **Mixed key naming in capabilities JSON:** `csv_max_rows` (Plan seeder) vs `csv_rows_limit` (Subscription migration) creates ongoing confusion. Recommend standardizing to one key name.

---

*End of audit report.*
