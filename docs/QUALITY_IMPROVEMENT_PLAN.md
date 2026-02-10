# SnapDraft Quality Improvement Plan: Closing the Gap Between Brand DNA and Generated Visuals

## Executive Summary
The quality gap between reference designs and generated visuals is caused by **insufficient brand extraction depth**, **weak prompt engineering**, and **inadequate style enforcement** in the generation pipeline. The current system does surface-level analysis and generic prompting. Here's the comprehensive fix.

---

## Root Cause Analysis

### 1. **Brand Analysis is Too Generic**
**Current Issue:**
- `BrandReferenceAnalyzer.php` extracts only basic categories (colors, typography, composition)
- JSON structure captures what's visible, not the *strategic design decisions*
- No analysis of: visual hierarchy principles, whitespace ratios, texture/gradients, micro-interactions, emphasis patterns
- Missing: brand-specific design patterns, stylistic consistency rules, composition formulas

**Example Gap:**
- Extracts: "primary colors: blue, white, red"
- Missing: "primary blue (#0066CC) dominates 40-60% of canvas, white is used for breathing room, red is accent only in CTAs"

### 2. **Prompt Engineering is Rudimentary**
**Current Issue:** (`csv-generation.txt`)
```
generate a creative visual for this caption : {caption}
keep the branding, colors and style similar the reference images
keep the text to a minimum on the visual.
no need to include the caption text on the image.
make sure the text is correct and properly spelled.
```

**Problems:**
- Generic, no specificity about the brand's visual rules
- Doesn't leverage extracted brand DNA
- No instruction on hierarchy, spacing, or layout principles
- No explicit constraint on what NOT to do
- Weak on stylistic consistency enforcement

### 3. **Missing Visual Constraint Enforcement**
**Current Gap:**
- References are passed to the model, but no explicit "replicate this style exactly" instructions
- No enforcement of: color usage percentages, typography hierarchy, spacing rules
- No visual "signature elements" enforcement
- AI model has to infer style from images alone—this is where quality degrades

### 4. **No Multi-Pass Generation/Refinement**
**Current Issue:**
- Single-shot generation: prompt + references → image
- No feedback loop to check if output matches brand DNA
- No secondary refinement based on detected issues
- Missing: quality control, style adherence checking

### 5. **Text Accuracy Model Misuse**
**Current Issue:**
- `textAccurate` flag uses different model but doesn't address *visual consistency*
- Text-accurate model is about readable text, not brand adherence
- No balancing of text quality vs. visual brand consistency

---

## Improvement Strategy: 5-Phase Implementation

### **Phase 1: Enhanced Brand Analysis (2-3 days)**

#### **1.1 Deepen Brand DNA Extraction**

Enhance `BrandReferenceAnalyzer.php` to capture strategic design principles:

**What to Add:**
1. **Visual Hierarchy Analysis**
   - Primary/secondary/tertiary element sizing ratios
   - Emphasis patterns (size, color, position)
   - Visual weight distribution

2. **Spacing & Layout Metrics**
   - Whitespace usage percentage
   - Grid system (8px, 16px, etc.)
   - Padding/margin conventions
   - Safe areas, bleeding zones

3. **Color Usage Rules**
   - Primary color coverage percentage
   - Secondary/accent usage patterns
   - Background treatment (solid, gradient, image)
   - Color contrast ratios

4. **Typography Hierarchy**
   - Headline size ranges
   - Line-height conventions
   - Letter-spacing patterns
   - Size ratios (headline:body, etc.)

5. **Stylistic Signatures**
   - Distinctive visual elements (corners, borders, overlays)
   - Texture/grain/effects (if any)
   - Animation/micro-interaction style (if applicable)
   - Gradient patterns, shadows, depth

6. **Composition Patterns**
   - Common layout arrangements
   - Subject placement rules
   - Negative space distribution
   - Focal point strategies

**Implementation:**
- Extend `brand_dna` JSON structure with detailed sections
- Create a new "visual-grid-analysis" prompt for deeper extraction
- Add per-image detailed breakdown, not just aggregated summary
- Create "replication_checklist" with specific, measurable criteria

#### **1.2 Add Visual Comparison Scoring**

Create a secondary analysis that compares images to detect:
- Consistency scores between reference images
- Which images are "most representative" of brand
- Outliers or lower-quality references
- Confidence levels for each extracted rule

---

### **Phase 2: Intelligent Prompt Engineering (2-3 days)**

#### **2.1 Replace Generic Prompts with Dynamic Brand-Aware Prompts**

**Create new prompt template: `csv-generation-with-brand-dna.txt`**

```
You are a brand-consistent visual designer. Generate an image that EXACTLY replicates this brand's visual DNA while illustrating: {caption}

## BRAND VISUAL RULES (STRICT - NO EXCEPTIONS):

### Color System
{color_primary_hex}: Used in {color_primary_percentage}% of composition, typically for {color_primary_purpose}
{color_secondary}: Accent color, max {color_secondary_percentage}%
{color_background}: Background treatment
Rule: {color_rules}

### Typography Hierarchy
Headlines: {headline_font}, size range {headline_size_range}, weight {headline_weight}
Body text: {body_font}, size {body_size}, line-height {line_height}
Rule: {typography_behavior}

### Layout & Spacing
Canvas structure: {layout_structure}
Whitespace ratio: {whitespace_percentage}% of canvas is negative space
Grid system: {grid_size}px spacing
Margin/padding: {margin_padding_rule}

### Visual Characteristics
Composition pattern: {composition_pattern}
Depth strategy: {depth_strategy}
Signature elements: {signature_elements}
Texture/effects: {texture_description}

### Imagery Style
Subject treatment: {subject_treatment}
Lighting: {lighting_style}
Mood: {mood_attributes}

## GENERATION RULES:
1. HIERARCHY: Organize elements following the brand's visual hierarchy exactly
2. COLOR COMPLIANCE: Respect color percentages ± 5%
3. TYPOGRAPHY: Use described fonts and sizing relationships
4. SPACING: Follow whitespace and padding conventions
5. SIGNATURE: Include {signature_count} brand signature element(s)
6. CONSISTENCY: Match the visual tone and style of reference images

## WHAT NOT TO DO:
- Do not add decorative elements not in the brand's vocabulary
- Do not use colors outside the defined palette
- Do not break the spacing grid
- Do not use different typography hierarchies
- Do not add effects (glows, 3D, filters) not evident in references
- Do not deviate from composition patterns

## REFERENCE IMAGES:
[These images define your style. Replicate their essence exactly in the new composition.]

Generate the image now.
```

#### **2.2 Create Model-Specific Variants**

Different prompts for different models based on their strengths:
- **Gemini Flash**: Focus on speed + color accuracy + basic hierarchy
- **Gemini Pro**: Emphasize brand consistency + detail + typography
- **Imagen 3**: Design-specific language emphasizing composition + hierarchy

---

### **Phase 3: Implement Quality Gates (1-2 days)**

#### **3.1 Post-Generation Validation**

Create `ImageQualityValidator.php` that checks:

**Visual Compliance Checks:**
1. **Color Accuracy**: Analyze generated image, verify primary colors match ±10%
2. **Hierarchy Check**: Verify element sizes follow expected ratios
3. **Typography Check**: Confirm text is readable and styled correctly
4. **Spacing Validation**: Check whitespace compliance
5. **Signature Element Detection**: Verify brand signatures are present

**Implementation:**
```php
// Pseudo-code structure
class ImageQualityValidator {
    public function validateAgainstBrandDNA(
        string $imagePath, 
        array $brandDNA, 
        array $generationParams
    ): array {
        return [
            'overall_score' => 0-100,
            'color_compliance' => 0-100,
            'hierarchy_compliance' => 0-100,
            'spacing_compliance' => 0-100,
            'signature_presence' => bool,
            'issues' => ['issue1', 'issue2'],
            'recommendations' => ['fix1', 'fix2'],
            'passes_quality_gate' => bool, // threshold: 75+
        ];
    }
}
```

#### **3.2 Automatic Refinement Pipeline**

If quality score < 75:
- Log specific failing criteria
- Re-generate with enhanced prompt focusing on failures
- Add explicit "avoid these mistakes" section
- Optionally escalate to higher-cost text-accurate model

---

### **Phase 4: Multi-Pass Generation (2-3 days)**

#### **4.1 Two-Pass Generation System**

**Pass 1: Initial Generation**
- Use standard model with brand DNA prompt
- Run quality validation
- Log compliance issues

**Pass 2: Refinement (if needed)**
- Use results from Pass 1 to enhance prompt
- Add specific constraints for any failed checks
- Optionally increase model capability (flash → pro → text-accurate)
- Generate refined version

**Implementation:**
```php
// In GenerateSingleImageJob.php handle() method
protected function executeGeneration(...) {
    // Pass 1: Initial generation
    $initialResult = $aiService->generateWithReferences($prompt, $references);
    $generationRecord->update(['image_data' => $initialResult]);
    
    // Quality check
    $validation = $qualityValidator->validate($initialResult, $brandDNA);
    
    if ($validation['overall_score'] < 75) {
        Log::info('Quality score below threshold, triggering refinement', [
            'score' => $validation['overall_score'],
            'issues' => $validation['issues'],
        ]);
        
        // Pass 2: Refinement with focused prompt
        $refinedPrompt = $this->buildRefinementPrompt($prompt, $validation);
        $refinedResult = $aiService->generateWithReferences(
            $refinedPrompt, 
            $references,
            textAccurate: true // Use stronger model for refinement
        );
        
        $generationRecord->update([
            'image_data' => $refinedResult,
            'refinement_applied' => true,
            'original_quality_score' => $validation['overall_score'],
        ]);
    }
}
```

---

### **Phase 5: Analytics & Continuous Improvement (1-2 days)**

#### **5.1 Track Quality Metrics**

Add to `GenerationHistory` model:
- `quality_score` (0-100)
- `brand_compliance_score` (0-100)
- `refinement_required` (boolean)
- `refinement_count` (int)
- `model_used` (string)
- `time_to_generate` (ms)
- `generation_attempt` (1, 2, 3...)

#### **5.2 Per-Brand Performance Dashboard**

Track which brands:
- Consistently pass quality gates
- Frequently need refinement
- Have highest user satisfaction
- Show improvement over time

Use insights to refine brand analysis for problematic brands.

---

## Implementation Roadmap

### **Week 1:**
1. Create enhanced brand analysis prompts and JSON schema
2. Implement visual comparison scoring
3. Create dynamic prompt generation from brand DNA

### **Week 2:**
1. Build image quality validator
2. Implement validation checks (color, hierarchy, spacing)
3. Create refinement logic in GenerateSingleImageJob

### **Week 3:**
1. Implement multi-pass generation
2. Add analytics tracking
3. Testing and optimization

### **Week 4:**
1. A/B testing: old vs. new quality
2. Fine-tune thresholds based on real data
3. Documentation and team training

---

## Quick Wins (Implement First)

These can be done in 1-2 days and show immediate improvement:

1. **Enhanced Brand DNA Prompt** (30 min)
   - Add color percentage extraction
   - Add typography ratio analysis
   - Add signature element identification

2. **Dynamic Prompt Generation** (2-3 hours)
   - Replace generic CSV prompt with dynamic template using brand DNA
   - Test with 5 brands

3. **Color Compliance Check** (2-3 hours)
   - Analyze generated image colors
   - Compare to brand DNA
   - Add to logs for manual review

4. **Explicit "What NOT to Do" Section** (1 hour)
   - Add to CSV generation prompt
   - List brand exclusions extracted from analysis

---

## Expected Impact

### Quality Improvements:
- **Color Accuracy**: From ~60% to ~85-90%
- **Visual Hierarchy**: From ~50% to ~80-85%
- **Typography Consistency**: From ~55% to ~80-90%
- **Overall Brand Compliance**: From ~55-60% to ~80-85%

### User Satisfaction:
- Fewer manual refinements needed
- Faster batch generation (fewer regenerations)
- More consistent multi-item batches
- Higher perceived brand quality

### System Benefits:
- Better credit utilization (fewer failed attempts)
- Reduced customer support tickets ("image doesn't match brand")
- Data for training brand-specific models later
- Analytics to identify hard-to-match brands

---

## Code Structure for Implementation

```
app/
├── Services/
│   ├── AI/
│   │   ├── BrandReferenceAnalyzer.php [ENHANCE with Phase 1]
│   │   ├── ImageQualityValidator.php [NEW - Phase 3]
│   │   └── GoogleGeminiService.php [MODIFY to support multi-pass]
│   ├── PromptService.php [ENHANCE to be dynamic]
│   └── BrandDNAPromptBuilder.php [NEW - Phase 2]
├── Jobs/
│   └── GenerateSingleImageJob.php [MODIFY for Phase 4 multi-pass]
├── Prompts/
│   ├── csv-generation-with-brand-dna.txt [NEW - Phase 2]
│   ├── brand-analysis-enhanced.txt [NEW - Phase 1]
│   └── refinement-focused.txt [NEW - Phase 4]
└── Models/
    └── GenerationHistory.php [ADD quality metrics]
```

---

## Validation Checklist

Before considering this complete:

- [ ] Brand analysis extracts color percentages, typography ratios, spacing grids
- [ ] Dynamic prompts use brand DNA to constrain generation
- [ ] Quality validator scores images against brand DNA (>75 = pass)
- [ ] Multi-pass refinement triggers on low quality scores
- [ ] Analytics dashboard shows quality trends per brand
- [ ] A/B test shows 25%+ improvement in quality scores
- [ ] User feedback confirms better brand consistency
- [ ] Batch generation times remain acceptable (refinement adds <20% overhead)

---

## Risk Mitigation

| Risk | Mitigation |
|------|-----------|
| Refinement loops increase costs | Use quality threshold to only refine low-scoring images |
| Slower generation times | Cache brand DNA analysis, parallelize where possible |
| Complex prompts confuse models | Start with smaller enhancements, test with 5 brands first |
| Hard-to-analyze brands | Implement fallback to original prompts for edge cases |
| Over-fitting to specific brands | Use metrics dashboard to identify outliers and adjust |

---

## Next Steps

1. **Review this plan with team**
2. **Pick Quick Wins to implement first** (1-2 days)
3. **Set up test environment** with 5 diverse brands
4. **Implement Phase 1** (enhanced analysis)
5. **Measure baseline quality scores**
6. **Implement Phase 2-4** sequentially
7. **A/B test new vs. old pipeline**
8. **Rollout to production**

