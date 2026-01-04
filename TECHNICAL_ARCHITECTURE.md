# Technical Architecture: Quality Improvement Pipeline

## System Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        SnapDraft Quality Pipeline                        │
└─────────────────────────────────────────────────────────────────────────┘

                           STEP 1: ANALYSIS
                              │
                              ↓
                    Brand Reference Images
                              │
                              ↓
              ┌──────────────────────────────┐
              │  BrandReferenceAnalyzer      │
              │  (with enhanced prompt)      │
              │                              │
              │  Extracts:                   │
              │  • Color percentages         │
              │  • Typography hierarchy      │
              │  • Spacing rules             │
              │  • Signature elements        │
              │  • Composition patterns      │
              │  • Design psychology         │
              └──────────────────────────────┘
                              │
                              ↓
                        Brand DNA JSON
                    (detailed visual rules)
                              │
                    ┌─────────┴─────────┐
                    │                   │
                    ↓                   ↓
         Stored in Project         Used for Prompts
         settings['brand_dna']


                        STEP 2: GENERATION
                              │
                              ↓
                    CSV Row + Caption
                              │
                              ↓
              ┌──────────────────────────────┐
              │  PromptService               │
              │  csvGenerationWithBrandDNA() │
              │                              │
              │  Retrieves Brand DNA         │
              │  Builds dynamic prompt with: │
              │  • Exact color hex codes     │
              │  • Font specifications       │
              │  • Size/weight ratios        │
              │  • Spacing requirements      │
              │  • Mandatory signatures      │
              └──────────────────────────────┘
                              │
                              ↓
                    Dynamic Branded Prompt
                              │
                              ↓
              ┌──────────────────────────────┐
              │  GoogleGeminiService         │
              │  generateWithReferences()    │
              │                              │
              │  Sends to API:               │
              │  • Branded prompt (explicit) │
              │  • Reference images (style)  │
              │  • Generation config         │
              └──────────────────────────────┘
                              │
                              ↓
                      Generated Image
                              │
                    ┌─────────┴─────────┐
                    │                   │
                    ↓                   ↓
           Save to Storage        STEP 3: VALIDATION


                      STEP 3: VALIDATION
                              │
                              ↓
              ┌──────────────────────────────┐
              │  ImageQualityValidator       │
              │  validateAgainstBrandDNA()   │
              │                              │
              │  Checks:                     │
              │  1. Color Compliance         │
              │  2. Whitespace Usage         │
              │  3. Hierarchy Presence       │
              │  4. Text Readability         │
              │  5. Composition Structure    │
              │                              │
              │  Returns:                    │
              │  • Overall Score (0-100)     │
              │  • Individual check scores   │
              │  • Detailed recommendations  │
              │  • Compliance report         │
              └──────────────────────────────┘
                              │
                              ↓
                    Quality Validation Report
                              │
                    ┌─────────┴─────────┐
                    │                   │
           Score >= 75?         Score < 75?
              ✓ PASS             FAIL/REFINE
                 │                   │
                 ↓                   ↓
        STEP 4A: COMPLETION    STEP 4B: REFINEMENT


              STEP 4A: COMPLETION (Score >= 75)
                              │
                              ↓
              ┌──────────────────────────────┐
              │  GenerationHistory           │
              │  record.update([              │
              │    'status' => 'completed',   │
              │    'quality_score' => 87,     │
              │    'refinement_applied' => 0  │
              │  ])                           │
              └──────────────────────────────┘
                              │
                              ↓
                        Ready for User ✓


              STEP 4B: REFINEMENT (Score < 75)
                              │
                              ↓
              ┌──────────────────────────────┐
              │  PromptService               │
              │  buildRefinementPrompt()     │
              │                              │
              │  Creates focused prompt:     │
              │  • Specific failures listed  │
              │  • Solutions for each issue  │
              │  • Stricter constraints      │
              │  • Verification checklist    │
              └──────────────────────────────┘
                              │
                              ↓
          Refinement Prompt + References
                              │
                              ↓
              ┌──────────────────────────────┐
              │  GoogleGeminiService         │
              │  generateWithReferences()    │
              │                              │
              │  Uses:                       │
              │  • Text-Accurate Model (4x)  │
              │  • Refined prompt            │
              │  • Same references           │
              └──────────────────────────────┘
                              │
                              ↓
                      Refined Image
                              │
                              ↓
              ┌──────────────────────────────┐
              │  ImageQualityValidator       │
              │  validateAgainstBrandDNA()   │
              │  (Re-validate)               │
              └──────────────────────────────┘
                              │
                    ┌─────────┴─────────┐
                    │                   │
           Now >= 75?          Still < 75?
              ✓ PASS             ACCEPT
                 │                 (flag for
                 ↓              manual review)
        Update Record
        refinement_applied = 1
        refinement_count = 1
        Final Score = 89
                 │
                 ↓
          Ready for User ✓
```

---

## Data Structures

### Brand DNA JSON (Extracted)

```json
{
  "brand_dna": {
    "brand_positioning": {
      "core_promise": ["string"],
      "attributes": ["string"],
      "one_line_summary": "string"
    },
    "visual_identity": {
      "color_system": {
        "primary_palette": [
          {
            "color": "#HEX",
            "name": "color name",
            "coverage_percentage": 45,
            "usage": "where/how used",
            "psychology": "why this works"
          }
        ],
        "secondary_accents": [...],
        "rules": ["rule1", "rule2"]
      },
      "typography": {
        "headline": {
          "font_families": ["font1", "font2"],
          "weight": "700",
          "size_range": "48px-64px",
          "line_height": "1.2",
          "behavior": "description"
        },
        "body": {...},
        "size_ratios": {
          "headline_to_body": "3:1"
        }
      },
      "spacing_and_layout": {
        "grid_system": "8px",
        "whitespace_target_percentage": 38,
        "padding_convention": {...},
        "layout_structure": ["pattern1", "pattern2"],
        "balance": "symmetrical | asymmetrical"
      }
    },
    "imagery": {
      "human_style": ["description"],
      "composition_rules": ["rule1", "rule2"],
      "emotion": ["feeling1", "feeling2"],
      "depth_strategy": "flat | shadows | 3D"
    },
    "graphic_language": {
      "signature_elements": ["badge", "underline", "rounded corners"],
      "icon_style": "minimal | bold | gradient",
      "button_treatment": "solid | outline"
    },
    "layout_system": {
      "structure": ["layout pattern"],
      "negative_space": "percentage",
      "balance_logic": "description",
      "reading_flow": "Z-pattern | F-pattern"
    },
    "replication_checklist": [
      "✓ checklist item 1",
      "✓ checklist item 2"
    ]
  },
  "coherence_score": 87,
  "confidence": {
    "color_palette": 95,
    "typography": 85,
    "composition": 80
  }
}
```

### Quality Validation Report

```json
{
  "overall_score": 87,
  "passes_quality_gate": true,
  "needs_refinement": false,
  "image_dimensions": {
    "width": 1024,
    "height": 1024
  },
  "checks": {
    "color_compliance": {
      "score": 93,
      "status": "pass",
      "primary_color_coverage": {
        "found": 43,
        "target": 45,
        "tolerance": 5,
        "match": true
      },
      "palette_compliance": 95
    },
    "whitespace_usage": {
      "score": 95,
      "status": "pass",
      "whitespace_percentage": {
        "found": 39,
        "target": 38,
        "tolerance": 5
      }
    },
    "hierarchy_presence": {
      "score": 88,
      "status": "pass",
      "focal_points_detected": 3
    },
    "text_readability": {
      "score": 85,
      "status": "pass",
      "average_contrast": 65
    },
    "composition_structure": {
      "score": 90,
      "status": "pass",
      "balanced": true
    }
  },
  "recommendations": [
    {
      "priority": "info",
      "area": "Overall",
      "issue": "No issues detected",
      "recommendation": "Image passes all quality standards"
    }
  ],
  "validated_at": "2026-01-03T12:34:56Z"
}
```

### Generation History (Enhanced)

```
generation_history table:
├── id
├── user_id
├── project_id
├── prompt (string)
├── ai_model (gemini-2.5-flash-image, gemini-3-pro-image-preview)
├── status (pending, processing, completed, failed)
├── image_path
├── error_message
├── quality_score (0-100) [NEW]
├── brand_compliance_score (0-100) [NEW]
├── refinement_applied (boolean) [NEW]
├── refinement_count (int) [NEW]
├── original_quality_score (for before refinement) [NEW]
├── quality_report (json) [NEW]
├── parameters (json)
│   ├── format
│   ├── text_accurate
│   ├── wizard_type
│   └── csv_row_index
├── created_at
├── updated_at
```

---

## File Organization

```
app/
├── Services/
│   ├── AI/
│   │   ├── BrandReferenceAnalyzer.php (ENHANCED)
│   │   ├── GoogleGeminiService.php (MODIFIED for multi-pass)
│   │   └── AIServiceInterface.php
│   │
│   ├── PromptService.php (ENHANCED with dynamic methods)
│   │
│   ├── ImageQualityValidator.php [NEW]
│   │   ├── validateAgainstBrandDNA()
│   │   ├── checkColorCompliance()
│   │   ├── checkWhitespaceUsage()
│   │   ├── checkHierarchyPresence()
│   │   ├── checkTextReadability()
│   │   └── checkCompositionStructure()
│   │
│   └── BrandDNAPromptBuilder.php [NEW]
│       ├── buildFromBrandDNA()
│       ├── buildRefinementPrompt()
│       └── validateStructure()
│
├── Jobs/
│   ├── GenerateSingleImageJob.php (MODIFIED)
│   │   ├── handle() - now calls validator
│   │   ├── executeGeneration() - enhanced with quality checks
│   │   └── triggerRefinement() [NEW]
│   │
│   └── GenerateBatchImagesJob.php
│
├── Models/
│   ├── GenerationHistory.php (ENHANCED)
│   │   └── Added quality_score, brand_compliance_score, refinement_applied
│   │
│   └── Project.php
│       └── $settings['brand_dna'] (now stores detailed DNA)
│
├── Console/
│   └── Commands/
│       └── TestImageQualityCommand.php [NEW]
│           ├── test:image-quality {imagePath} {brandDnaPath}
│           └── Outputs quality report
│
└── Prompts/
    ├── brand-analysis.txt (ORIGINAL - kept for compatibility)
    ├── brand-analysis-enhanced.txt [NEW]
    │   └── Detailed extraction with percentages, psychology, rules
    │
    ├── csv-generation.txt (ORIGINAL - kept for compatibility)
    ├── csv-generation-with-brand-dna.txt [NEW]
    │   └── Dynamic, brand-specific generation prompt
    │
    └── refinement-focused.txt [NEW]
        └── Targets specific quality failures
```

---

## Integration Points

### 1. Brand Analysis Entry Point

**File:** `ProjectController.php` or `BrandWizardController.php`

```php
// When user uploads reference images
$analyzer = app(BrandReferenceAnalyzer::class);
$brandDNA = $analyzer->analyze($referencePaths);

// Store in project settings
$project->update([
    'settings' => array_merge($project->settings ?? [], [
        'brand_dna' => $brandDNA,
        'brand_analyzed_at' => now(),
    ])
]);
```

### 2. Generation Entry Point

**File:** `GenerateSingleImageJob.php` handle() method

```php
// Retrieve brand DNA
$brandDNA = $this->project->settings['brand_dna'] ?? null;

// Build dynamic prompt if brand DNA exists
$prompt = $brandDNA 
    ? app(PromptService::class)->csvGenerationWithBrandDNA($caption, $brandDNA)
    : $caption;

// Generate image
$result = $aiService->generateWithReferences($prompt, $references, ...);

// Validate quality
$validator = new ImageQualityValidator();
$validation = $validator->validateAgainstBrandDNA($tempPath, $brandDNA);

// Log and potentially refine
$generation->update(['quality_score' => $validation['overall_score']]);
```

### 3. Refinement Entry Point

**In GenerateSingleImageJob.php executeGeneration() method**

```php
if ($validation['overall_score'] < 75 && $refinementCount < 2) {
    $refinementPrompt = app(PromptService::class)->buildRefinementPrompt(
        $prompt,
        $validation,
        $brandDNA
    );
    
    // Regenerate with stronger model
    $refinedResult = $aiService->generateWithReferences(
        $refinementPrompt,
        $references,
        textAccurate: true
    );
    
    // Update generation record
    $generation->update([
        'refinement_applied' => true,
        'refinement_count' => $refinementCount + 1,
        'original_quality_score' => $validation['overall_score'],
    ]);
}
```

---

## Performance Characteristics

### Memory Usage
- **Brand DNA JSON:** ~10-20KB per project (one-time, stored)
- **Validation per image:** ~5MB (image loaded in memory briefly)
- **Total per generation:** ~100MB (generation + validation)

### Processing Time
- **Brand Analysis:** 15-30 seconds (API call to Gemini)
- **Dynamic Prompt Building:** 50-100ms
- **Image Generation:** 20-40 seconds (API call)
- **Quality Validation:** 1-2 seconds (sampling-based)
- **Refinement (if needed):** 40-60 seconds additional
- **Total per image:** 22-60 seconds (vs 20-40 before)

### API Costs
- **Brand Analysis:** 1 call per project setup (1-time)
- **Generation:** 1 call per image (standard)
- **Refinement:** 1 additional call (4x cost) for ~20-30% of images
- **Net impact:** ~10-15% cost increase offset by fewer manual refinements

---

## Testing Strategy

### Unit Tests
- `BrandReferenceAnalyzer::analyze()` returns proper JSON structure
- `PromptService::csvGenerationWithBrandDNA()` fills variables correctly
- `ImageQualityValidator::validateAgainstBrandDNA()` scores images

### Integration Tests
- Full pipeline: reference → brand DNA → prompt → generation → validation
- Refinement trigger on low scores
- Database records updated correctly

### Quality Tests
- Compare 10 images before/after implementation
- Manual visual inspection by design team
- Measure improvement metrics (color accuracy, hierarchy, etc.)
- User feedback on brand consistency

---

## Monitoring & Observability

### Log Points
```php
Log::info('Brand analysis complete', [
    'project_id' => $project->id,
    'quality_score' => $brandDNA['coherence_score'],
    'confidence' => $brandDNA['confidence'],
]);

Log::info('Dynamic prompt generated', [
    'project_id' => $project->id,
    'prompt_length' => strlen($prompt),
    'brand_dna_used' => !empty($brandDNA),
]);

Log::info('Quality validation', [
    'generation_id' => $generation->id,
    'score' => $validation['overall_score'],
    'passes' => $validation['passes_quality_gate'],
]);

Log::info('Refinement triggered', [
    'generation_id' => $generation->id,
    'original_score' => $validation['overall_score'],
    'reason' => implode(', ', array_column($validation['recommendations'], 'issue')),
]);
```

### Metrics Dashboard
Track per-brand:
- Average quality score
- % passing on first attempt
- % requiring refinement
- Common failure types
- User satisfaction

---

## Rollback Plan

If issues arise:

1. **Disable brand DNA prompts (5 min):**
   - Set `BRAND_DNA_ENABLED=false` in .env
   - GenerateSingleImageJob falls back to generic prompts

2. **Disable quality validation (5 min):**
   - Comment out validator call in GenerateSingleImageJob
   - Images still generate, just without quality scoring

3. **Revert to previous version (15 min):**
   - `git revert` for any breaking changes
   - Restore from backup if data corruption

---

## Next Steps

1. Implement Phase 1-2 (brand analysis + dynamic prompts)
2. Deploy to staging
3. Test with 3-5 diverse brands
4. Measure baseline quality scores
5. Implement Phase 3-4 (validation + refinement)
6. Deploy to production
7. Monitor metrics and iterate

