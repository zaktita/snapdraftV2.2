# Quick Start: Test UI

## In 3 Steps

### 1️⃣ Start the server
```bash
composer dev
```

### 2️⃣ Open test UI
```
http://localhost/test/brand-analysis
```

### 3️⃣ Upload images and analyze
- Drag 5-10 images into the upload box
- Click "Analyze Images"
- Wait 30-60 seconds
- See results!

---

## What You'll See

✅ **Clusters tab** - Images grouped by style  
✅ **Elements** - All 80+ detected elements per image  
✅ **Metadata** - Quality, complexity, coherence scores  
✅ **Colors** - Dominant color swatches per cluster  
✅ **JSON export** - Raw data for debugging

---

## Expected Results

For a typical brand with 10 images:
- **Clusters**: 1-3 (depending on style variation)
- **Coherence**: 85-95% (not all same, not all different)
- **Elements per image**: 5-15 detected
- **Processing**: ~30-60 seconds

---

## Verify It Works

✓ Similar-style images in same cluster?  
✓ Elements match visual content?  
✓ Cluster names describe style?  
✓ Color swatches look right?

If **yes** → Everything works! 🎉  
If **no** → See `BRAND_ANALYSIS_TEST_UI.md` for troubleshooting

---

## Files
- **Controller**: `app/Http/Controllers/BrandAnalysisTestController.php`
- **Component**: `resources/js/pages/test/brand-analysis.tsx`
- **Routes**: `routes/web.php` (added 2 routes)

---

That's it! Go test it now 🚀
