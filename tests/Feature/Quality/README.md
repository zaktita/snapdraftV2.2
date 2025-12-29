# SnapDraft Automated Quality Test Suite

## Overview

This automated test suite covers all quality tests from the pre-launch checklist. Tests are organized by category and can be run individually or as a complete suite.

## Test Categories

### 1. Core Generation Tests (`CoreGenerationTest.php`)
**Coverage**: GEN-001 to GEN-005  
**Focus**: 5+1 workflow and brand style transfer

- ✅ GEN-001: Cohesive Brand Style Transfer (High Priority)
- ✅ GEN-002: Diverse References Handling (High Priority)
- ✅ GEN-003: Low-Quality References (Medium Priority)
- ✅ GEN-004: Text-Heavy References (Medium Priority)
- ✅ GEN-005: Aspect Ratio Changes (Medium Priority)

**Run Command**:
```bash
php artisan test --filter=CoreGenerationTest
```

### 2. Batch Processing Tests (`BatchProcessingTest.php`)
**Coverage**: BAT-001 to BAT-004  
**Focus**: CSV upload and batch generation

- ✅ BAT-001: Small Batch (10 rows) (High Priority)
- ✅ BAT-002: Mixed Prompt Complexity (Medium Priority)
- ✅ BAT-003: CSV Error Handling (Medium Priority)
- ✅ BAT-004: Large Batch Performance (Low Priority) - Tagged as @group slow

**Run Command**:
```bash
php artisan test --filter=BatchProcessingTest
```

**Run excluding slow tests**:
```bash
php artisan test --filter=BatchProcessingTest --exclude-group=slow
```

### 3. Prompt-Only Tests (`PromptOnlyTest.php`)
**Coverage**: PRO-001 to PRO-003  
**Focus**: Text-based generation without reference images

- ✅ PRO-001: Detailed Brand Description (High Priority)
- ✅ PRO-002: Vague Prompt (Medium Priority)
- ✅ PRO-003: Platform-Specific Format (Medium Priority)

**Run Command**:
```bash
php artisan test --filter=PromptOnlyTest
```

### 4. User Scenario Tests (`UserScenarioTest.php`)
**Coverage**: SOC, SMB, AGY, ECO scenarios  
**Focus**: Real-world usage patterns

- ✅ SOC-001: Weekly Content Creation (High Priority)
- ✅ SOC-002: Carousel Content (Medium Priority)
- ✅ SMB-001: Product to Ad Conversion (High Priority)
- ✅ SMB-002: Amateur to Pro (Medium Priority)
- ✅ AGY-001: Client Pitch Concepts (High Priority)
- ✅ ECO-001: Lifestyle from Product Shots (High Priority)
- ✅ ECO-002: Product Badges (Medium Priority)

**Run Command**:
```bash
php artisan test --filter=UserScenarioTest
```

### 5. Edge Cases and Safety Tests (`EdgeCasesTest.php`)
**Coverage**: EDG-001 to EDG-003, SAF-001 to SAF-002  
**Focus**: Boundary conditions and content safety

- ✅ EDG-001: Single Reference (Medium Priority)
- ✅ EDG-002: Too Many References (Low Priority)
- ✅ EDG-003: Extremely Long Prompt (Low Priority)
- ✅ SAF-001: Child-Friendly Brand (High Priority)
- ✅ SAF-002: Trademark Respect (Medium Priority)

**Run Command**:
```bash
php artisan test --filter=EdgeCasesTest
```

### 6. Platform & Performance Tests (`PlatformPerformanceTest.php`)
**Coverage**: FIL, PER, EXP tests  
**Focus**: File handling, performance, and exports

- ✅ FIL-001: Format Support (High Priority)
- ✅ FIL-002: Large Files (Medium Priority)
- ✅ PER-001: Generation Speed (High Priority)
- ✅ PER-002: Batch Progress (Medium Priority)
- ✅ EXP-001: Download Options (High Priority)
- ✅ EXP-002: Batch Download (Medium Priority)

**Run Command**:
```bash
php artisan test --filter=PlatformPerformanceTest
```

## Test Coverage Not Yet Automated

### Canvas Editing Tests (EDT-001 to EDT-007)
These tests require browser automation (Dusk) or manual testing:

- EDT-001: Change Text in Graphic
- EDT-002: Add Text to Image
- EDT-003: Remove Background Object
- EDT-004: Fix AI Artifacts
- EDT-005: Change Background via Prompt
- EDT-006: Modify Attributes
- EDT-007: Composite Elements

**Recommendation**: Implement with Laravel Dusk for frontend interaction testing.

### Error Handling Tests (ERR-001 to ERR-003)
Partially covered in existing tests:

- ERR-001: API Failure - ✅ Covered in `AITestHelper::mockGeminiRateLimit()`
- ERR-002: Network Issues - Requires integration testing
- ERR-003: Credit Limit - ✅ Covered in `CoreGenerationTest::test_generation_without_credits_fails()`

### Business & Output Quality Tests
These require manual assessment:

- BUS-001: Free Tier Limits
- BUS-002: Paid Features
- OUT-001: Post-Readiness (subjective)
- OUT-002: Consistency
- OUT-003: Human Comparison

### UX Tests (UX-001, UX-002)
Require browser automation:

- UX-001: First-Time User Flow
- UX-002: Example Galleries

### Performance Test (PER-003)
Requires actual mobile device testing:

- PER-003: Mobile Responsive

## Running Tests

### Run All Quality Tests
```bash
php artisan test tests/Feature/Quality
```

### Run Only High Priority Tests
```bash
php artisan test tests/Feature/Quality --filter="test_cohesive_brand_style_transfer|test_small_batch_10_rows|test_detailed_brand_description|test_weekly_content_creation|test_product_to_ad_conversion|test_client_pitch_concepts|test_lifestyle_from_product_shots|test_child_friendly_brand|test_multiple_image_format_support|test_generation_speed|test_download_options"
```

### Run Excluding Slow Tests
```bash
php artisan test tests/Feature/Quality --exclude-group=slow
```

### Run with Coverage Report
```bash
php artisan test tests/Feature/Quality --coverage
```

### Run Specific Test Method
```bash
php artisan test --filter=test_cohesive_brand_style_transfer
```

## Test Helpers

### AITestHelper Trait
Located in `tests/TestHelpers/AITestHelper.php`

**Available Methods**:

- `mockGeminiSuccess()` - Mock successful AI generation
- `mockGeminiBrandAnalysis()` - Mock brand style analysis
- `mockGeminiRateLimit()` - Mock API rate limit error
- `mockGeminiAuthError()` - Mock authentication error
- `createFakeReferenceImages($count, $style)` - Generate test images
- `createTestCSV($rows, $malformed)` - Generate test CSV files
- `createProductImages($count)` - Generate product images
- `createUserWithCredits($credits)` - Create test user
- `fakeStorage()` - Set up fake storage

**Usage Example**:
```php
use Tests\TestHelpers\AITestHelper;

class MyTest extends TestCase
{
    use AITestHelper;
    
    public function test_something(): void
    {
        $this->mockGeminiSuccess();
        $this->fakeStorage();
        $user = $this->createUserWithCredits(100);
        
        // Your test code...
    }
}
```

## CI/CD Integration

### GitHub Actions Example
```yaml
name: Quality Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          
      - name: Install Dependencies
        run: composer install
        
      - name: Run Quality Tests
        run: php artisan test tests/Feature/Quality --exclude-group=slow
```

## Test Maintenance

### Adding New Tests

1. Create test file in `tests/Feature/Quality/`
2. Use `AITestHelper` trait for common operations
3. Follow naming convention: `test_{test_id}_{description}`
4. Add appropriate priority and category comments
5. Update this README with new test coverage

### Best Practices

- ✅ Always use `RefreshDatabase` trait
- ✅ Always call `$this->fakeStorage()` in setUp
- ✅ Mock AI responses to avoid API calls
- ✅ Use descriptive test names matching CSV test IDs
- ✅ Add `@group slow` annotation for tests >5 seconds
- ✅ Test both success and failure paths
- ✅ Assert specific, meaningful conditions

## Expected Test Results

When all automated tests pass:

```
Tests: 60+ passed
Time: ~10-30 seconds (excluding slow tests)
Coverage: ~70% of CSV test cases
```

## Manual Testing Required

After automated tests pass, perform these manual checks:

1. **Canvas Editor** - All EDT tests require visual verification
2. **Mobile Testing** - Test on actual devices (PER-003)
3. **Quality Assessment** - Review generated images (OUT-001)
4. **UX Flow** - Walk through first-time user experience (UX-001)
5. **Business Logic** - Verify subscription tiers (BUS-001, BUS-002)

## Troubleshooting

### Tests Failing with Storage Errors
```bash
php artisan storage:link
php artisan cache:clear
```

### Database Issues
```bash
php artisan migrate:fresh --seed
```

### HTTP Mock Not Working
Ensure `Illuminate\Support\Facades\Http` is imported and `Http::fake()` is called before requests.

### Credits Not Deducting
Check that `HasCreditsMiddleware` is properly registered and user has sufficient credits.

## Contact

For issues with the test suite, contact the development team or create an issue in the repository.
