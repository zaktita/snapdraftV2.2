# SnapDraft Pre-Launch Quality Test Suite

## 📋 Overview

Comprehensive automated testing suite for SnapDraft covering 60+ quality assurance tests before launch. This suite automates approximately **70% of pre-launch tests**, with the remaining 30% requiring manual verification.

**Test Coverage**: 60+ automated tests  
**Execution Time**: ~10-30 seconds (excluding slow tests)  
**Success Criteria**: All automated tests must pass before production deployment

---

## 🚀 Quick Start

### Run All Tests
```powershell
.\run-quality-tests.ps1 -Suite all
```

### Run High Priority Tests Only
```powershell
.\run-quality-tests.ps1 -Priority high
```

### Run Specific Test Suite
```powershell
.\run-quality-tests.ps1 -Suite core
```

### Run with Code Coverage
```powershell
.\run-quality-tests.ps1 -Suite all -Coverage
```

---

## 📂 Test Structure

```
tests/
├── TestHelpers/
│   └── AITestHelper.php          # Common test utilities and mocking
└── Feature/
    └── Quality/
        ├── README.md              # Detailed documentation
        ├── CoreGenerationTest.php      # GEN-001 to GEN-005
        ├── BatchProcessingTest.php     # BAT-001 to BAT-004
        ├── PromptOnlyTest.php          # PRO-001 to PRO-003
        ├── UserScenarioTest.php        # SOC, SMB, AGY, ECO
        ├── EdgeCasesTest.php           # EDG, SAF tests
        └── PlatformPerformanceTest.php # FIL, PER, EXP tests
```

---

## ✅ Automated Test Coverage

### Core Generation Tests (GEN)
- [x] **GEN-001** - Cohesive Brand Style Transfer (High)
- [x] **GEN-002** - Diverse References Handling (High)
- [x] **GEN-003** - Low-Quality References (Medium)
- [x] **GEN-004** - Text-Heavy References (Medium)
- [x] **GEN-005** - Aspect Ratio Changes (Medium)

### Batch Processing Tests (BAT)
- [x] **BAT-001** - Small Batch (10 rows) (High)
- [x] **BAT-002** - Mixed Prompt Complexity (Medium)
- [x] **BAT-003** - CSV Error Handling (Medium)
- [x] **BAT-004** - Large Batch Performance (Low)

### Prompt-Only Tests (PRO)
- [x] **PRO-001** - Detailed Brand Description (High)
- [x] **PRO-002** - Vague Prompt (Medium)
- [x] **PRO-003** - Platform-Specific Format (Medium)

### User Scenario Tests (SOC, SMB, AGY, ECO)
- [x] **SOC-001** - Weekly Content Creation (High)
- [x] **SOC-002** - Carousel Content (Medium)
- [x] **SMB-001** - Product to Ad Conversion (High)
- [x] **SMB-002** - Amateur to Pro (Medium)
- [x] **AGY-001** - Client Pitch Concepts (High)
- [x] **ECO-001** - Lifestyle from Product Shots (High)
- [x] **ECO-002** - Product Badges (Medium)

### Edge Cases & Safety Tests (EDG, SAF)
- [x] **EDG-001** - Single Reference Image (Medium)
- [x] **EDG-002** - Too Many References (Low)
- [x] **EDG-003** - Extremely Long Prompt (Low)
- [x] **SAF-001** - Child-Friendly Brand (High)
- [x] **SAF-002** - Trademark Respect (Medium)

### Platform, Performance & Export Tests (FIL, PER, EXP)
- [x] **FIL-001** - Format Support (High)
- [x] **FIL-002** - Large Files (Medium)
- [x] **PER-001** - Generation Speed (High)
- [x] **PER-002** - Batch Progress (Medium)
- [x] **EXP-001** - Download Options (High)
- [x] **EXP-002** - Batch Download (Medium)

---

## ⚠️ Manual Testing Required

### Canvas Editing (EDT-001 to EDT-007)
These require browser automation or manual interaction:

- [ ] **EDT-001** - Change Text in Graphic
- [ ] **EDT-002** - Add Text to Image
- [ ] **EDT-003** - Remove Background Object
- [ ] **EDT-004** - Fix AI Artifacts
- [ ] **EDT-005** - Change Background via Prompt
- [ ] **EDT-006** - Modify Attributes
- [ ] **EDT-007** - Composite Elements

**Recommendation**: Implement Laravel Dusk tests or manual QA checklist

### Error Handling (ERR)
- [x] **ERR-001** - API Failure (Automated via mocks)
- [ ] **ERR-002** - Network Issues (Requires integration tests)
- [x] **ERR-003** - Credit Limit (Automated)

### UX & Onboarding (UX)
- [ ] **UX-001** - First-Time User Flow
- [ ] **UX-002** - Example Galleries

### Mobile Testing (PER)
- [ ] **PER-003** - Mobile Responsive (Requires real device testing)

### Business Logic (BUS)
- [ ] **BUS-001** - Free Tier Limits
- [ ] **BUS-002** - Paid Features

### Quality Assessment (OUT)
- [ ] **OUT-001** - Post-Readiness (Subjective quality check)
- [ ] **OUT-002** - Consistency (Visual comparison)
- [ ] **OUT-003** - Human Comparison (Designer benchmark)

### Brand Consistency (CON)
- [x] **CON-001** - Conflicting References (Covered in GEN-002)
- [ ] **CON-002** - Logo Maintenance (Requires visual verification)

---

## 🛠️ Test Helper Utilities

### AITestHelper Trait

Located in `tests/TestHelpers/AITestHelper.php`

#### Mocking Methods
```php
$this->mockGeminiSuccess();           // Mock successful generation
$this->mockGeminiBrandAnalysis();     // Mock brand analysis
$this->mockGeminiRateLimit();         // Mock rate limit error
$this->mockGeminiAuthError();         // Mock auth error
```

#### Test Data Generation
```php
$images = $this->createFakeReferenceImages(5, 'cohesive');
$csv = $this->createTestCSV($rows, $malformed = false);
$products = $this->createProductImages(3);
```

#### Setup Utilities
```php
$user = $this->createUserWithCredits(100);
$this->fakeStorage();
```

---

## 📊 Running Tests

### PowerShell Test Runner

```powershell
# Run all tests
.\run-quality-tests.ps1 -Suite all

# Run specific suite
.\run-quality-tests.ps1 -Suite core
.\run-quality-tests.ps1 -Suite batch
.\run-quality-tests.ps1 -Suite scenario

# Filter by priority
.\run-quality-tests.ps1 -Priority high
.\run-quality-tests.ps1 -Priority medium

# Include slow tests
.\run-quality-tests.ps1 -Suite all -Slow

# Generate coverage
.\run-quality-tests.ps1 -Suite all -Coverage
```

### Artisan Commands

```bash
# Run all quality tests
php artisan test tests/Feature/Quality

# Run specific test file
php artisan test tests/Feature/Quality/CoreGenerationTest.php

# Run specific test method
php artisan test --filter=test_cohesive_brand_style_transfer

# Exclude slow tests
php artisan test tests/Feature/Quality --exclude-group=slow

# Run with coverage
php artisan test tests/Feature/Quality --coverage
```

---

## 🎯 Pre-Launch Checklist

### Automated Tests
- [ ] Run full test suite: `.\run-quality-tests.ps1 -Suite all -Slow`
- [ ] All tests passing (green)
- [ ] No deprecation warnings
- [ ] Code coverage > 70%

### Manual Testing
- [ ] Complete Canvas Editor testing (EDT tests)
- [ ] Test on mobile devices (iOS, Android)
- [ ] Walk through first-time user experience
- [ ] Verify subscription tiers and billing
- [ ] Review sample outputs for quality (OUT-001)
- [ ] Test error scenarios (network failures, etc.)

### Performance Verification
- [ ] Single generation < 30 seconds
- [ ] Batch of 50 completes successfully
- [ ] No memory leaks during large batches
- [ ] Rate limiting works correctly

### Safety & Compliance
- [ ] Child-friendly content verified
- [ ] No trademark violations
- [ ] Content moderation active
- [ ] Terms of service reviewed

---

## 🐛 Troubleshooting

### Test Failures

**Storage errors**:
```bash
php artisan storage:link
php artisan cache:clear
```

**Database issues**:
```bash
php artisan migrate:fresh --seed
php artisan db:wipe
php artisan migrate
```

**HTTP mocking not working**:
- Ensure `Http::fake()` is called before making requests
- Check that `Illuminate\Support\Facades\Http` is imported

**Credits not deducting**:
- Verify `HasCreditsMiddleware` is registered
- Check user has sufficient credits in test setup

### Common Issues

**Tests skipped**: Check for `@group` annotations and `--exclude-group` flags

**Slow execution**: Run without slow tests: `--exclude-group=slow`

**Coverage missing**: Install Xdebug or PCOV for coverage reports

---

## 📈 CI/CD Integration

### GitHub Actions Example

```yaml
name: Quality Tests

on: [push, pull_request]

jobs:
  quality-tests:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, pdo, pdo_mysql
          
      - name: Install Composer Dependencies
        run: composer install --prefer-dist --no-interaction
        
      - name: Prepare Laravel Application
        run: |
          cp .env.example .env
          php artisan key:generate
          
      - name: Run Quality Tests
        run: php artisan test tests/Feature/Quality --exclude-group=slow
        
      - name: Upload Coverage
        if: success()
        uses: codecov/codecov-action@v3
```

---

## 📝 Test Maintenance

### Adding New Tests

1. Create test method in appropriate test class
2. Use `AITestHelper` for mocking and data generation
3. Follow naming: `test_{test_id}_{description}`
4. Add priority comment block
5. Update README documentation

### Best Practices

- ✅ Always use `RefreshDatabase` trait
- ✅ Always call `$this->fakeStorage()` in setUp
- ✅ Mock AI responses to avoid API calls
- ✅ Use descriptive assertions
- ✅ Tag slow tests with `@group slow`
- ✅ Test both success and error paths

---

## 📞 Support

For issues with the test suite:
- Check `tests/Feature/Quality/README.md` for detailed documentation
- Review test helper methods in `tests/TestHelpers/AITestHelper.php`
- Contact development team for assistance

---

## 📄 License

This test suite is part of the SnapDraft project and follows the same license terms.

---

**Last Updated**: December 29, 2025  
**Version**: 1.0.0  
**Coverage**: 60+ automated tests (~70% of pre-launch checklist)
