#!/usr/bin/env pwsh
<#
.SYNOPSIS
    SnapDraft Quality Test Runner

.DESCRIPTION
    Automated test runner for SnapDraft quality assurance tests.
    Runs all pre-launch tests and generates a report.

.PARAMETER Suite
    Specific test suite to run (core, batch, prompt, scenario, edge, platform, all)

.PARAMETER Priority
    Run only tests with specific priority (high, medium, low, all)

.PARAMETER Slow
    Include slow tests (default: exclude)

.PARAMETER Coverage
    Generate code coverage report

.EXAMPLE
    .\run-quality-tests.ps1 -Suite all
    
.EXAMPLE
    .\run-quality-tests.ps1 -Suite core -Priority high
    
.EXAMPLE
    .\run-quality-tests.ps1 -Suite all -Coverage
#>

param(
    [Parameter()]
    [ValidateSet('all', 'core', 'batch', 'prompt', 'scenario', 'edge', 'platform')]
    [string]$Suite = 'all',
    
    [Parameter()]
    [ValidateSet('all', 'high', 'medium', 'low')]
    [string]$Priority = 'all',
    
    [Parameter()]
    [switch]$Slow,
    
    [Parameter()]
    [switch]$Coverage
)

# Color output functions
function Write-Success { param($Message) Write-Host $Message -ForegroundColor Green }
function Write-Error { param($Message) Write-Host $Message -ForegroundColor Red }
function Write-Info { param($Message) Write-Host $Message -ForegroundColor Cyan }
function Write-Warning { param($Message) Write-Host $Message -ForegroundColor Yellow }

# Banner
Write-Host ""
Write-Host "╔═══════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║                                                       ║" -ForegroundColor Cyan
Write-Host "║        SnapDraft Quality Test Suite Runner           ║" -ForegroundColor Cyan
Write-Host "║                                                       ║" -ForegroundColor Cyan
Write-Host "╚═══════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

# Test suite mapping
$testSuites = @{
    'core' = 'CoreGenerationTest'
    'batch' = 'BatchProcessingTest'
    'prompt' = 'PromptOnlyTest'
    'scenario' = 'UserScenarioTest'
    'edge' = 'EdgeCasesTest'
    'platform' = 'PlatformPerformanceTest'
}

# Priority filter mapping (test method patterns)
$priorityFilters = @{
    'high' = 'test_cohesive_brand_style_transfer|test_diverse_references_handling|test_small_batch_10_rows|test_detailed_brand_description|test_weekly_content_creation|test_product_to_ad_conversion|test_client_pitch_concepts|test_lifestyle_from_product_shots|test_child_friendly_brand|test_multiple_image_format_support|test_generation_speed|test_download_options'
    'medium' = 'test_low_quality_references|test_text_heavy_references|test_aspect_ratio_changes|test_mixed_prompt_complexity|test_csv_error_handling|test_vague_prompt|test_platform_specific_format|test_carousel_content_generation|test_amateur_to_professional|test_product_badges_generation|test_single_reference_image|test_trademark_respect|test_large_file_upload|test_batch_progress_tracking|test_batch_download'
    'low' = 'test_too_many_references|test_extremely_long_prompt|test_large_batch_performance'
}

Write-Info "Configuration:"
Write-Host "  Suite: $Suite"
Write-Host "  Priority: $Priority"
Write-Host "  Include Slow Tests: $Slow"
Write-Host "  Code Coverage: $Coverage"
Write-Host ""

# Build test command
$testCommand = "php artisan test"

if ($Suite -eq 'all') {
    $testCommand += " tests/Feature/Quality"
} else {
    $testFilter = $testSuites[$Suite]
    $testCommand += " --filter=$testFilter"
}

# Add priority filter
if ($Priority -ne 'all' -and $priorityFilters.ContainsKey($Priority)) {
    $priorityPattern = $priorityFilters[$Priority]
    if ($Suite -ne 'all') {
        $testCommand += "|$priorityPattern"
    } else {
        $testCommand = "php artisan test --filter=`"$priorityPattern`""
    }
}

# Exclude slow tests unless explicitly included
if (-not $Slow) {
    $testCommand += " --exclude-group=slow"
}

# Add coverage if requested
if ($Coverage) {
    $testCommand += " --coverage"
}

Write-Info "Running tests..."
Write-Host "Command: $testCommand" -ForegroundColor DarkGray
Write-Host ""
Write-Host "════════════════════════════════════════════════════════" -ForegroundColor DarkGray
Write-Host ""

# Run the tests
$startTime = Get-Date

try {
    Invoke-Expression $testCommand
    $exitCode = $LASTEXITCODE
} catch {
    Write-Error "Failed to run tests: $_"
    exit 1
}

$endTime = Get-Date
$duration = $endTime - $startTime

Write-Host ""
Write-Host "════════════════════════════════════════════════════════" -ForegroundColor DarkGray
Write-Host ""

# Results summary
if ($exitCode -eq 0) {
    Write-Success "✓ All tests passed!"
} else {
    Write-Error "✗ Some tests failed"
}

Write-Info "Execution time: $($duration.TotalSeconds.ToString('0.00')) seconds"
Write-Host ""

# Additional information
if (-not $Slow) {
    Write-Warning "Note: Slow tests were excluded. Run with -Slow to include them."
}

if ($Suite -eq 'all') {
    Write-Info "Test Categories Covered:"
    Write-Host "  ✓ Core Generation (GEN-001 to GEN-005)" -ForegroundColor Green
    Write-Host "  ✓ Batch Processing (BAT-001 to BAT-004)" -ForegroundColor Green
    Write-Host "  ✓ Prompt-Only (PRO-001 to PRO-003)" -ForegroundColor Green
    Write-Host "  ✓ User Scenarios (SOC, SMB, AGY, ECO)" -ForegroundColor Green
    Write-Host "  ✓ Edge Cases & Safety (EDG, SAF)" -ForegroundColor Green
    Write-Host "  ✓ Platform & Performance (FIL, PER, EXP)" -ForegroundColor Green
    Write-Host ""
    Write-Warning "Manual Testing Still Required:"
    Write-Host "  - Canvas Editing (EDT-001 to EDT-007)" -ForegroundColor Yellow
    Write-Host "  - Mobile Responsive (PER-003)" -ForegroundColor Yellow
    Write-Host "  - UX Flow (UX-001, UX-002)" -ForegroundColor Yellow
    Write-Host "  - Output Quality Assessment (OUT-001 to OUT-003)" -ForegroundColor Yellow
    Write-Host "  - Business Features (BUS-001, BUS-002)" -ForegroundColor Yellow
}

Write-Host ""

# Report generation suggestion
if ($Coverage) {
    Write-Info "Coverage report generated. Check the output above for details."
}

# Exit with test result code
exit $exitCode
