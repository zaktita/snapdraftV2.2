# AI Model Tester - Setup & Usage Guide

## Quick Start

### Prerequisites
✅ Your `.env` file already has `OPENROUTER_API_KEY` configured
✅ SnapDraft app is running with `composer dev`
✅ You're logged in as an authenticated user

### Access the Tool

1. Start your dev environment:
   ```bash
   composer dev
   ```

2. Navigate to the test page:
   ```
   http://localhost:8000/test-ai-models
   ```

3. You should see the UI with:
   - Drag-and-drop area for reference images
   - Text input for your prompt
   - "Generate Images" button

---

## How to Use

### Step 1: Upload Reference Images
- **Drag & Drop**: Drag image files into the upload area
- **Click to Select**: Click the upload area to open file picker
- **Supported formats**: JPG, PNG, WebP
- **Upload multiple**: Add 1-5 images for style reference

Visual feedback:
- Images appear as thumbnails below the upload area
- Hover over thumbnail and click "Remove" to delete

### Step 2: Enter Your Prompt
- Click in the textarea
- Describe what you want to generate (e.g., "A sleek laptop on a desk")
- The more detailed, the better the results

### Step 3: Generate
- Click "Generate Images" button
- Watch as images load in the results grid below
- Each model appears as a card with:
  - Generated image
  - Model name
  - Generation time (in seconds)

### Step 4: Compare Results
- All 7 models' outputs are displayed side-by-side
- Look for consistency with your reference images
- Generation times vary per model
- Note any failures (models that didn't generate an image)

---

## Understanding the Results

### Successful Generation
```
┌──────────────────┐
│  [Generated IMG] │
├──────────────────┤
│  flux.2-max      │
│  2.3s            │
└──────────────────┘
```
- Image displays correctly
- Model name and generation time shown

### Generation Error
```
┌──────────────────┐
│  ⚠️ Error        │
│  Rate limited... │
├──────────────────┤
│  gpt-5-image     │
│  —               │
└──────────────────┘
```
- Model name still shows
- Error message explains why generation failed
- Common reasons:
  - API rate limiting
  - Invalid image format
  - Model timeout
  - Insufficient credits on OpenRouter

---

## Tips for Best Results

### Reference Images
- Choose 2-5 high-quality images that represent your desired style
- Images should all follow a consistent visual theme
- Include various angles/compositions for better coverage

### Prompts
- Be specific: "A modern office desk with a laptop and coffee" beats "A desk"
- Include style hints: "photorealistic," "minimalist," "cinematic"
- Mention mood/tone: "professional," "cozy," "energetic"
- Reference the images: "matching the style of the reference images"

### Comparing Models
- FLUX.2 Max & Flex are generally fast and detailed
- ByteDance SeedDream excels at specific compositions
- Gemini models are good for text-heavy images
- OpenAI GPT-5 models offer unique artistic interpretations

---

## Troubleshooting

### Images Not Uploading
- Check file format (JPG, PNG, WebP only)
- Check file size (large images may take longer)
- Refresh and try again

### "Generate" Button Disabled
- Make sure at least 1 image is uploaded
- Make sure prompt is not empty (min 5 chars)

### Blank Results
- Models may still be generating - wait a moment
- Some models take longer than others
- Check browser console for errors (F12)

### All Models Failed
- Check if OpenRouter API key is valid
- Verify API key has sufficient credits
- Check network connection
- Try with different/simpler prompt

### One or Two Models Failed
- This is normal - some models occasionally timeout
- Re-run the generation to retry
- The other models' outputs are still useful for comparison

---

## API Integration Details

### Request Format
```json
{
  "reference_images": [
    "data:image/png;base64,iVBORw0KGgo...",
    "data:image/jpeg;base64,/9j/4AAQSkZJRg..."
  ],
  "prompt": "A modern workspace with natural lighting"
}
```

### Response Format
```json
{
  "success": true,
  "results": {
    "bytedance-seed/seedream-4.5": {
      "image_url": "https://openrouter.ai/images/...",
      "duration_ms": 3200,
      "error": null
    },
    "black-forest-labs/flux.2-max": {
      "image_url": "https://openrouter.ai/images/...",
      "duration_ms": 2800,
      "error": null
    },
    ...
  }
}
```

### Error Response
```json
{
  "success": false,
  "error": "No reference images provided"
}
```

---

## Performance Notes

### Generation Times
- Typical range: 2-10 seconds per model
- All models generate simultaneously (concurrent)
- Total wait time ≈ slowest model's time
- Times depend on:
  - Image complexity
  - Server load
  - Model size
  - OpenRouter queue

### API Costs
- Each generation request counts toward OpenRouter usage
- Costs vary by model
- Check OpenRouter dashboard for pricing details
- Reference images don't add extra cost per model

---

## Advanced: Batch Testing

Currently, the tool handles single generations. For batch testing:

1. **Manual batching**: Generate multiple times with different prompts
2. **Keep results**: Take screenshots or note generation times
3. **Analyze patterns**: See which models work best for your style

Future enhancement: Could add CSV batch mode (similar to CSV Wizard)

---

## Support

For issues:
1. Check browser console (F12) for JavaScript errors
2. Check Laravel logs: `storage/logs/laravel.log`
3. Verify OpenRouter API key in `.env`
4. Check OpenRouter dashboard for account status

---

## Feature Roadmap

Potential future enhancements:
- [ ] Save/compare multiple generations
- [ ] Download results as ZIP
- [ ] Batch CSV import for prompts
- [ ] Model filtering (test subset of 7)
- [ ] Prompt templates library
- [ ] Generation history
- [ ] Cost estimation before generating
