# Quick Implementation Guide: Quality Improvements (Phase 1-2)

## Timeline: 3-5 Days

This guide walks you through implementing the quality improvements to close the gap between reference designs and generated visuals.

---

## Day 1-2: Enhanced Brand Analysis

### Step 1: Update BrandReferenceAnalyzer to Use Enhanced Prompt

**File:** `app/Services/AI/BrandReferenceAnalyzer.php`

**What to Change:**
- In the `analyze()` method, replace the call to `$this->buildInstruction()` with the new enhanced prompt
- OR create a new method `analyzeWithDetail()` that uses the enhanced prompt
- Keep the old method for backward compatibility

**Code Change:**
```php
// In BrandReferenceAnalyzer.php, update buildInstruction() method:
protected function buildInstruction(): string
{
    // OLD: return $this->simpleInstruction();
    
    // NEW: return $this->enhancedInstruction();
    return file_get_contents(app_path('Prompts/brand-analysis-enhanced.txt'));
}
```

**Test:** 
- Run brand analysis on 1-2 test brands
- Verify JSON output includes color percentages, typography ratios, spacing grids
- Check that `coverage_percentage`, `usage`, `psychology` fields are populated

### Step 2: Create PromptService Method for Dynamic Brand Prompts

**File:** `app/Services/PromptService.php`

**Add New Method:**
```php
/**
 * Build CSV generation prompt with brand DNA
 */
public function csvGenerationWithBrandDNA(
    string $caption, 
    array $brandDNA,
    array $productNames = []
): string {
    // Extract key data from brand DNA
    $colorSystem = $brandDNA['brand_dna']['visual_identity']['color_system'] ?? [];
    $primary = $colorSystem['primary_palette'][0] ?? [];
    
    $variables = [
        'caption' => $caption,
        'color_system_detailed' => $this->formatColorSystem($colorSystem),
        'primary_color_percentage' => $primary['coverage_percentage'] ?? 40,
        'secondary_accent_percentage' => $colorSystem['secondary_accents'][0]['coverage_percentage'] ?? 15,
        'headline_font' => $this->extractTypographyValue($brandDNA, 'headline_font'),
        'headline_weight' => $this->extractTypographyValue($brandDNA, 'headline_weight'),
        'headline_size_range' => $this->extractTypographyValue($brandDNA, 'headline_size_range'),
        // ... more variables
    ];
    
    $template = $this->getRaw('csv-generation-with-brand-dna');
    return $this->renderWithVariables($template, $variables);
}

protected function formatColorSystem(array $colorSystem): string
{
    $lines = [];
    foreach ($colorSystem['primary_palette'] ?? [] as $color) {
        $lines[] = "- {$color['color']} ({$color['name']}): {$color['coverage_percentage']}% - {$color['usage']}";
    }
    return implode("\n", $lines);
}

protected function extractTypographyValue(array $brandDNA, string $key): string
{
    $typography = $brandDNA['brand_dna']['visual_identity']['typography'] ?? [];
    // Navigate the structure and extract value
    return 'value'; // Implement based on JSON structure
}

protected function renderWithVariables(string $template, array $variables): string
{
    foreach ($variables as $key => $value) {
        $template = str_replace('{' . $key . '}', (string) $value, $template);
    }
    return $template;
}
```

**Test:**
- Create a mock brand DNA object
- Call `csvGenerationWithBrandDNA('Test caption', $brandDNA)`
- Verify prompt includes specific color percentages, fonts, spacing rules

---

## Day 2-3: Image Quality Validator

### Step 1: Create ImageQualityValidator Service

**File:** `app/Services/ImageQualityValidator.php`

✓ Already created above. Review the implementation.

**Key Methods:**
- `validateAgainstBrandDNA()` - Main entry point
- `checkColorCompliance()` - Verify colors
- `checkWhitespaceUsage()` - Verify spacing
- `checkHierarchyPresence()` - Verify visual structure
- `checkTextReadability()` - Verify text is readable
- `checkCompositionStructure()` - Verify layout

**Test:**
```php
// In a test file or artisan command:
$validator = new ImageQualityValidator();
$result = $validator->validateAgainstBrandDNA(
    '/path/to/generated/image.png',
    $brandDNA,
    ['format' => 'square']
);

echo "Overall Score: " . $result['overall_score'];
echo "Passes Quality Gate: " . ($result['passes_quality_gate'] ? 'Yes' : 'No');
print_r($result['recommendations']);
```

### Step 2: Create Artisan Command to Test Validator

**File:** `app/Console/Commands/TestImageQualityCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Services\ImageQualityValidator;
use Illuminate\Console\Command;

class TestImageQualityCommand extends Command
{
    protected $signature = 'test:image-quality {imagePath} {brandDnaPath}';
    protected $description = 'Test image quality validation';

    public function handle()
    {
        $imagePath = $this->argument('imagePath');
        $brandDnaPath = $this->argument('brandDnaPath');

        if (!file_exists($imagePath)) {
            $this->error("Image not found: $imagePath");
            return 1;
        }

        if (!file_exists($brandDnaPath)) {
            $this->error("Brand DNA file not found: $brandDnaPath");
            return 1;
        }

        $brandDNA = json_decode(file_get_contents($brandDnaPath), true);
        $validator = new ImageQualityValidator();
        $result = $validator->validateAgainstBrandDNA($imagePath, $brandDNA);

        $this->info("Image Quality Report");
        $this->info("====================");
        $this->line("Overall Score: " . $result['overall_score']);
        $this->line("Passes Quality Gate: " . ($result['passes_quality_gate'] ? 'Yes' : 'No'));
        
        $this->newLine();
        $this->info("Detailed Checks:");
        foreach ($result['checks'] as $checkName => $checkResult) {
            $status = $checkResult['status'] ?? 'unknown';
            $score = $checkResult['score'] ?? 0;
            $this->line("  $checkName: $score ($status)");
        }

        if (!empty($result['recommendations'])) {
            $this->newLine();
            $this->info("Recommendations:");
            foreach ($result['recommendations'] as $rec) {
                $this->line("  [{$rec['priority']}] {$rec['area']}: {$rec['recommendation']}");
            }
        }

        return 0;
    }
}
```

**Test Command:**
```bash
php artisan test:image-quality /path/to/image.png /path/to/brand_dna.json
```

---

## Day 3-4: Integrate Dynamic Prompts into Generation Job

### Step 1: Update GenerateSingleImageJob

**File:** `app/Jobs/GenerateSingleImageJob.php`

**What to Change:**
The job currently builds prompts generically. Enhance it to use brand DNA:

```php
// In GenerateSingleImageJob.php, modify the executeGeneration method:

protected function executeGeneration(AIServiceManager $aiService, ...): void
{
    // ... existing code ...
    
    // NEW: Retrieve project's brand DNA
    $brandDNA = $this->project->settings['brand_dna'] ?? null;
    
    // Build enhanced prompt if brand DNA exists
    if ($brandDNA) {
        $enhancedPrompt = app(PromptService::class)->csvGenerationWithBrandDNA(
            $this->prompt,
            $brandDNA,
            $productNames
        );
        $promptToUse = $enhancedPrompt;
    } else {
        // Fallback to generic prompt
        $promptToUse = $this->prompt;
    }
    
    // Get reference paths
    $referenceDir = 'projects/' . $this->project->id . '/references';
    $referencePaths = Storage::disk('public')->files($referenceDir);
    
    // Generate with references
    $result = $aiService->generateWithReferences(
        $promptToUse,
        $referencePaths,
        [],
        $this->format,
        $this->textAccurate
    );
    
    // NEW: Validate against brand DNA
    if ($brandDNA) {
        $validator = new ImageQualityValidator();
        $validation = $validator->validateAgainstBrandDNA(
            $tempImagePath,
            $brandDNA,
            ['format' => $this->format]
        );
        
        // Log validation results
        Log::info('Image quality validation', [
            'score' => $validation['overall_score'],
            'passes' => $validation['passes_quality_gate'],
        ]);
        
        $generation->update([
            'quality_score' => $validation['overall_score'],
            'brand_compliance_score' => $validation['checks']['color_compliance']['score'] ?? null,
        ]);
    }
    
    // ... rest of existing code ...
}
```

### Step 2: Add Quality Metrics to GenerationHistory Model

**File:** `app/Models/GenerationHistory.php`

**Add Columns (via Migration):**
```php
php artisan make:migration add_quality_metrics_to_generation_history

// In the migration:
Schema::table('generation_history', function (Blueprint $table) {
    $table->unsignedSmallInteger('quality_score')->nullable()->default(null);
    $table->unsignedSmallInteger('brand_compliance_score')->nullable();
    $table->boolean('refinement_applied')->default(false);
    $table->unsignedTinyInteger('refinement_count')->default(0);
    $table->json('quality_report')->nullable();
});

php artisan migrate
```

**Update Model:**
```php
// In GenerationHistory.php
protected $casts = [
    'quality_report' => 'array',
];
```

---

## Day 4-5: Test & Validate

### Test 1: Manual Quality Test

1. Upload brand reference images (5-10 images of LCI Career Expo design)
2. Create a CSV with 3-5 rows
3. Run batch generation
4. Check logs for quality scores
5. Compare generated images with originals visually

### Test 2: Quality Baseline

Generate 10 images and record:
- Average quality score before refinement
- % of images passing quality gate (>75)
- Most common failure reasons

### Test 3: Integration Test

```php
// In test file
public function test_quality_validation_workflow()
{
    $brandDNA = $this->createMockBrandDNA();
    $imagePath = storage_path('test-image.png');
    
    $validator = new ImageQualityValidator();
    $result = $validator->validateAgainstBrandDNA($imagePath, $brandDNA);
    
    $this->assertIsArray($result);
    $this->assertArrayHasKey('overall_score', $result);
    $this->assertArrayHasKey('passes_quality_gate', $result);
    $this->assertIsArray($result['recommendations']);
}
```

---

## Rollout Checklist

- [ ] Enhanced brand analysis prompt deployed
- [ ] PromptService methods added and tested
- [ ] ImageQualityValidator service created and tested
- [ ] GenerateSingleImageJob updated with quality validation
- [ ] GenerationHistory model updated with quality columns
- [ ] Quality metrics populated in logs
- [ ] Test command works: `php artisan test:image-quality`
- [ ] 10-image manual test completed
- [ ] Quality scores logged and reviewed
- [ ] No regressions in existing generation pipeline
- [ ] Performance acceptable (validation adds <2s per image)

---

## Performance Considerations

**Image Quality Validator:**
- Runs synchronously after generation
- Should complete in <2 seconds per image (using sampling)
- Optional: Move to background job for batch processing

**Prompt Generation:**
- Brand DNA parsing is fast (<100ms)
- Dynamic prompt building: <50ms
- No additional API calls

**Overall Impact:**
- Generation time: +2-3 seconds for validation
- Credits per image: No change yet (refinement comes in Phase 4)

---

## Debugging Tips

### Brand DNA looks wrong
- Check that enhanced analysis prompt is being used
- Verify JSON is valid: `php artisan tinker` → `json_decode($json)`
- Look for missing color percentages in analysis

### Quality scores are all 50
- Validator helper methods need implementation (they're stubs)
- Color detection needs proper Intervention Image usage
- Check Intervention Image installation: `composer require intervention/image`

### Dynamic prompts not generating
- Verify PromptService methods exist
- Check that brand DNA structure matches extraction
- Look for unreplaced {variables} in final prompt

---

## Next Phase (Bonus: Day 5+)

### Phase 4: Multi-Pass Refinement

When quality score < 75:
```php
// In GenerateSingleImageJob
if ($validation['overall_score'] < 75) {
    Log::info('Triggering refinement due to low quality score');
    
    $refinementPrompt = app(PromptService::class)->render('refinement-focused', [
        'failed_checks_summary' => json_encode($validation['recommendations']),
        'refinement_focus_detailed' => $this->buildRefinementFocus($validation),
        'brand_dna_summary' => $this->summarizeBrandDNA($brandDNA),
    ]);
    
    // Regenerate with refinement prompt + text-accurate model
    $refinedResult = $aiService->generateWithReferences(
        $refinementPrompt,
        $referencePaths,
        textAccurate: true
    );
    
    $generation->update([
        'refinement_applied' => true,
        'refinement_count' => 1,
        'original_quality_score' => $validation['overall_score'],
    ]);
}
```

---

## Questions?

- **Brand DNA structure unclear?** → Review extracted JSON from your test brand
- **Validator scores too low?** → Implement proper color/whitespace detection
- **Prompts too generic?** → Add more specific brand DNA fields
- **Performance issues?** → Sample fewer pixels, cache validator results

