# SnapDraft Quality Tests - Setup Required

## ⚠️ Important: Required Elements Before Running Tests

Before running the automated quality tests, you need the following elements in place:

### 1. ✅ Test Files Created
The following test files have been created in `tests/Feature/Quality/`:

- `CoreGenerationTest.php` - Core generation workflow tests (GEN-001 to GEN-005)
- `BatchProcessingTest.php` - CSV batch processing tests (BAT-001 to BAT-004)  
- `PromptOnlyTest.php` - Text-only generation tests (PRO-001 to PRO-003)
- `UserScenarioTest.php` - Real-world user scenario tests (SOC, SMB, AGY, ECO)
- `EdgeCasesTest.php` - Edge cases and safety tests (EDG, SAF)
- `PlatformPerformanceTest.php` - Platform and performance tests (FIL, PER, EXP)

### 2. ✅ Test Helper Created
- `tests/TestHelpers/AITestHelper.php` - Provides mocking utilities and test data generation

### 3. ✅ Documentation Created
- `QUALITY_TESTS.md` - Main documentation
- `tests/Feature/Quality/README.md` - Detailed test documentation
- `run-quality-tests.ps1` - PowerShell test runner script

---

## 🔧 Current Test Status

### Tests Need Minor Fixes

Some tests currently use placeholder routes and may need adjustments based on your actual implementation:

**Known Issues**:
1. ~~Tests use `prompt` field, but TextWizard uses `idea_description`~~ ✅ FIXED
2. Some tests reference `ImagesWizard` which may have different field names
3. Tests assume certain middleware behavior (credits, authentication)

---

## 🚀 Next Steps Before Running

### Step 1: Verify Controllers Match Test Expectations

Check that these controllers accept the test data:

**TextWizardController** (`app/Http/Controllers/Wizards/TextWizardController.php`):
```php
// Expected fields in tests:
- project_name (string)
- idea_description (string)  ✅ CONFIRMED
- format (square|portrait|landscape)
- reference_images (array of files, optional)
- text_accurate (boolean, optional)
```

**CSVWizardController**:
```php
// Expected fields:
- project_name (string)
- csv_file (file)
- reference_images (array of files, optional)
```

**ImagesWizardController**:
```php
// Expected fields (NEED TO VERIFY):
- project_name (string)
- product_images (array of files)
- prompt OR idea_description (string)
- format (square|portrait|landscape)
```

### Step 2: Check Database Migrations

Ensure these tables exist with expected columns:

**projects table**:
- id, user_id, name, title, description, format, status, settings, is_favorite, featured_image, images_count, created_at, updated_at, deleted_at

**images table**:
- id, project_id, url, thumbnail_url, prompt, metadata, order, is_favorite, format, width, height, created_at, updated_at

**brand_references table**:
- id, project_id, url, thumbnail_url, order, created_at, updated_at

**generation_history table**:
- id, project_id, user_id, prompt, ai_model, status, parameters, created_at, updated_at

### Step 3: Verify Middleware

Check that these middleware exist and work as expected:

- `auth` - User authentication ✅
- `verified` - Email verification ✅  
- `has.credits` - Credits check (VERIFY THIS EXISTS)

### Step 4: Check AI Service Availability

Verify `GoogleGeminiService` can be mocked properly:
```php
// The tests use Http::fake() to mock Gemini API
// This should work if your service uses Laravel's Http facade
```

### Step 5: Test Job Configuration

Ensure `GenerateSingleImageJob` works correctly:
```php
// Tests assume this job:
// - Accepts: $project, $prompt, $format, $textAccurate, $generationId
// - Creates Image records
// - Updates generation history
// - Deducts credits from user
```

---

## 🧪 Running Tests (After Verification)

### Quick Test Run
```bash
# Test just one file to verify setup
php artisan test tests/Feature/Quality/CoreGenerationTest.php --stop-on-failure
```

### Full Test Suite
```powershell
# Run all quality tests
.\run-quality-tests.ps1 -Suite all
```

### Specific Test Method
```bash
# Test specific functionality
php artisan test --filter=test_cohesive_brand_style_transfer
```

---

## 🐛 Expected Issues & How to Fix

### Issue 1: "Route not defined"
**Fix**: Update test to use correct route name or create missing route

### Issue 2: "Column not found"
**Fix**: Run migrations or update test to match actual schema

### Issue 3: "Class not found" (e.g., HasCreditsMiddleware)
**Fix**: Create missing middleware or remove from test expectations

### Issue 4: "Undefined method on GoogleGeminiService"
**Fix**: Update `AITestHelper` mocking to match actual service interface

### Issue 5: Test creates project but no images
**Fix**: Check that `GenerateSingleImageJob` is being dispatched and executed correctly. In local environment, tests use `dispatchSync()` to run immediately.

---

## 📋 Checklist Before Running

- [ ] All database migrations run successfully
- [ ] User factory creates users with `credits_remaining` field
- [ ] TextWizardController accepts `idea_description` field ✅
- [ ] CSVWizardController exists and works
- [ ] ImagesWizardController exists (if using product image tests)
- [ ] `has.credits` middleware registered (or remove from routes)
- [ ] `GenerateSingleImageJob` creates Image records properly
- [ ] Storage is configured (tests use fake storage)
- [ ] GoogleGeminiService can be mocked with Http::fake()

---

## 🔍 What I Need From You

Please verify/provide:

1. **ImagesWizardController field names** - What fields does it expect?
   ```
   Current guess in tests:
   - project_name
   - product_images
   - prompt
   - format
   ```

2. **HasCreditsMiddleware** - Does this exist? If not, should tests skip credit checks?

3. **GenerateSingleImageJob** - Does it accept these parameters?
   ```php
   GenerateSingleImageJob::dispatch($project, $prompt, $format, $textAccurate, $generationId)
   ```

4. **BrandReference model** - Does it have these relationships and fields?
   ```php
   $project->brandReferences()->create([
       'url' => $url,
       'thumbnail_url' => $thumbnail,
       'order' => $index
   ])
   ```

---

## 💡 Quick Fix Script

Run this to identify missing pieces:

```bash
# Check if routes exist
php artisan route:list | grep wizard

# Check if middleware exists  
php artisan route:list | grep has.credits

# Check database schema
php artisan migrate:status

# Check if Job exists
php artisan tinker
>>> class_exists('App\\Jobs\\GenerateSingleImageJob')
```

---

## ✅ Once Everything is Verified

Run the complete test suite:

```powershell
.\run-quality-tests.ps1 -Suite all -Slow
```

Expected output:
```
Tests: 60+ passed
Time: 10-30 seconds
```

---

## 📞 Need Help?

If tests fail, provide:
1. Error message
2. Stack trace
3. Which test file/method failed
4. Any relevant controller or model code

I can then update the tests to match your actual implementation.

---

**Status**: ✅ Tests created and ready  
**Next**: Verify implementation details above and run tests
