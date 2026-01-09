# AI Model Tester UI - Visual Layout

## Page Structure

```
┌─────────────────────────────────────────────────────────────┐
│  Dashboard > Test AI Models                                  │
└─────────────────────────────────────────────────────────────┘

HEADER
├─ Title: "Test AI Models"
└─ Description: "Upload reference images and a prompt to compare..."

INPUT SECTION (Card)
├─ Reference Images Section
│  ├─ Drag & Drop Area
│  │  └─ "Drag and drop images here, or click to select"
│  └─ Image Previews Grid (2-4 columns)
│     └─ [Image] [Image] [Image] [Remove] [Remove] [Remove]
│
├─ Prompt Section
│  └─ Textarea: "Describe what image you want to generate..."
│
├─ Error Alert (if any)
│  └─ ⚠️ "Please upload at least one reference image"
│
└─ Generate Button (Full Width)
   └─ "Generate Images" (disabled until inputs are valid)

RESULTS SECTION (Grid - appears after generation)
├─ Grid: Responsive layout (1-4 columns based on screen size)
│
├─ Card (per model)
│  ├─ Image Container
│  │  ├─ [Generated Image] (if successful)
│  │  ├─ [Loading Spinner] (if still generating)
│  │  └─ [Error Message] (if failed)
│  │
│  └─ Info Section
│     ├─ Model Name: "flux.2-max"
│     └─ Duration: "3.2s"

```

## Responsive Breakpoints

| Screen Size | Columns |
|------------|---------|
| Mobile (< 768px) | 1 |
| Tablet (768px - 1024px) | 2 |
| Desktop (1024px - 1280px) | 3 |
| Large (> 1280px) | 4 |

## User Flow

```
1. User navigates to /test-ai-models
   ↓
2. User uploads 1-5 reference images (drag & drop or click)
   ↓
3. User enters a text prompt (e.g., "A futuristic car in motion")
   ↓
4. User clicks "Generate Images"
   ↓
5. System sends to all 7 models simultaneously
   ↓
6. Images appear in grid as each model completes
   ↓
7. User can see generation times and compare results
   ↓
8. If model fails, error message displays in that card
```

## Key Interactions

### Upload Images
- Click or drag-drop images
- Thumbnails appear in preview grid
- Click "Remove" on any thumbnail to delete it

### Generate
- Button disabled until images + prompt provided
- Shows "Generating..." with spinner while processing
- All models generate concurrently (fast completion)

### View Results
- Images load as models complete
- Generation time shown in seconds (e.g., "3.2s")
- Failed models show error message instead of image
- Fully side-by-side comparison view

## Models Being Compared

1. ByteDance SeedDream 4.5
2. Black Forest Labs FLUX.2 Max
3. Sourceful Riverflow v2 Max Preview
4. Black Forest Labs FLUX.2 Flex
5. Google Gemini 3 Pro Image Preview
6. OpenAI GPT-5 Image Mini
7. OpenAI GPT-5 Image

---

## Technical Flow

```
Frontend (React)
  ├─ Upload images → Convert to base64
  └─ POST /test-ai-models/generate
       {
         reference_images: [base64_1, base64_2, ...],
         prompt: "user text"
       }

Backend (Laravel)
  ├─ Validate inputs
  ├─ Parse base64 images
  └─ Call OpenRouterImageTester service
       ├─ Build content parts (images + prompt)
       ├─ Fire 7 concurrent Guzzle requests
       ├─ Each model: generate image
       └─ Collect results with metadata
            {
              "bytedance-seed/seedream-4.5": {
                image_url: "https://...",
                duration_ms: 3200,
                error: null
              },
              ...
            }

Frontend (React)
  ├─ Receive JSON results
  └─ Render grid with images + times
```
