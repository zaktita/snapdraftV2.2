# AI Functions in Canvas Editor - Complete Documentation

> **Last Updated:** November 13, 2025  
> **File:** `resources/js/pages/canvas-editor.tsx`  
> **Backend:** `app/Http/Controllers/ImageEditController.php`

---

## 📋 Overview

The canvas editor includes **6 image enhancement functions**, of which **4 use AI** (Google Gemini) and **2 use traditional algorithms**.

---

## 🤖 AI-Powered Functions (Using Gemini)

### **1. Erase/Inpaint (`handleErase`)**

**Purpose:** Remove unwanted objects or areas from images

**Endpoint:** `POST /api/generate-with-mask`

**Workflow:**
1. User selects an image
2. User paints over areas to erase using retouch tool (brush)
3. System creates white mask where brush strokes were drawn
4. Sends original image + mask + prompt to Gemini
5. Gemini regenerates the masked area naturally
6. New image appears beside the original

**Client-Side Implementation:**
```javascript
// Location: handleErase() function
{
    originalImage: base64DataUrl,
    mask: maskDataUrl,  // White where user painted
    prompt: 'erase',
    brushStrokes: relevant,
    imageSize: { width, height }
}
```

**Backend AI Prompt:**
```php
// Receives 'erase' directly from client
// Sends to Gemini with:
// - Original image (base64 PNG)
// - Mask image (base64 PNG, white = areas to regenerate)
// - Prompt: 'erase'
```

**AI Model Configuration:**
- Model: `gemini-2.5-flash-image-preview`
- Temperature: 1.0
- TopK: 40
- TopP: 0.95
- Max Output Tokens: 8192
- Timeout: 120 seconds

**Output Behavior:**
- ✅ Creates new image beside original
- ✅ Clears brush strokes after generation
- ✅ Auto-selects new image
- ✅ Shows skeleton loader during generation

---

### **2. Replace Text (`handleReplaceText`)**

**Purpose:** Replace existing text in images with new text

**Endpoint:** `POST /api/generate-with-mask`

**Workflow:**
1. User selects an image
2. User paints over existing text using retouch tool
3. System prompts user to enter replacement text
4. Creates mask over painted areas
5. Sends to Gemini with text replacement instruction
6. New image with replaced text appears beside original

**Client-Side Implementation:**
```javascript
// Location: handleReplaceText() function
const textToReplace = await showPrompt(
    'Text Replacement',
    'Enter the text you want to replace the selected area with:',
    'Your text here...',
    'Your text here'
);

{
    originalImage: base64DataUrl,
    mask: maskDataUrl,
    prompt: `inpaint the masked area with "${textToReplace}" make sure it's spelled correctly`,
    brushStrokes: relevant,
    imageSize: { width, height }
}
```

**Backend Handling:**
```php
// Receives prompt directly from client
// Passes it to Gemini exactly as received:
// "inpaint the masked area with \"[USER TEXT]\" make sure it's spelled correctly"
```

**AI Model Configuration:**
- Model: `gemini-2.5-flash-image-preview`
- Temperature: 1.0
- TopK: 40
- TopP: 0.95
- Max Output Tokens: 8192
- Timeout: 120 seconds

**Output Behavior:**
- ✅ Creates new image beside original
- ✅ Clears brush strokes for source object
- ✅ Auto-selects new image
- ✅ Shows skeleton loader during generation

**Example Use Cases:**
- Change store name on a sign
- Replace watermarks with clean text
- Fix typos in images
- Translate text while maintaining style

---

### **3. AI Generate (`handleGenerate`)**

**Purpose:** Generate new creative content in masked areas based on text description

**Endpoint:** `POST /api/generate-with-mask`

**Workflow:**
1. User selects an image
2. User paints mask over areas to regenerate
3. System prompts for description of what to generate
4. Sends to Gemini with custom prompt
5. AI generates content matching the description
6. **Replaces** the selected object's image (in-place edit)

**Client-Side Implementation:**
```javascript
// Location: handleGenerate() function
const prompt = await showPrompt(
    'AI Generation',
    'Describe what you want to generate in the masked area:',
    'e.g., "a red sports car", "mountains in the background"...',
    ''
);

{
    originalImage: base64DataUrl,
    mask: maskDataUrl,
    prompt: prompt,  // User's custom description
    brushStrokes: relevant,
    imageSize: { width, height }
}
```

**Backend AI Prompt (Intended but Currently Not Used):**
```php
// Note: This wrapper exists in code but is commented out
$fullPrompt = "You are an expert image editor. The user has provided an image and a mask (white areas show where to edit). " . 
              $validated['prompt'] . ". Only modify the white areas in the mask. Keep the rest of the image unchanged.";

// Currently sends client prompt directly without wrapper
```

**AI Model Configuration:**
- Model: `gemini-2.5-flash-image-preview`
- Temperature: 1.0
- TopK: 40
- TopP: 0.95
- Max Output Tokens: 8192
- Timeout: 120 seconds

**Output Behavior:**
- ⚠️ **Different:** Replaces selected object in-place (doesn't create new object)
- ✅ Clears brush strokes after generation
- ✅ Updates selected object reference
- ✅ Shows skeleton loader during generation

**Example Use Cases:**
- "a red sports car" - replace a vehicle
- "mountains in the background" - change scenery
- "a modern building" - replace architecture
- "flowers in a vase" - add decorative elements

---

### **4. Expand Image / Outpaint (`handleExpandImage`)**

**Purpose:** Extend image boundaries by generating seamless content around edges

**Endpoint:** `POST /api/expand-image`

**Workflow:**
1. User selects an image
2. System prompts for expansion direction (all, top, bottom, left, right)
3. Backend creates expanded canvas (150% by default)
4. Places original image in appropriate position
5. Creates mask (white = areas to fill, black = original)
6. Gemini outpaints to fill empty areas naturally
7. New expanded image appears beside original

**Client-Side Implementation:**
```javascript
// Location: handleExpandImage() function
const direction = await showPrompt(
    'Expand Image',
    'Choose expansion direction (all, top, bottom, left, right):',
    'Direction',
    'all'
);

{
    image: imageDataUrl,
    direction: 'all' | 'top' | 'bottom' | 'left' | 'right',
    expansionRatio: 1.5  // 150% of original size
}
```

**Backend Processing:**
```php
// Calculate new dimensions based on direction
switch ($direction) {
    case 'all':
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);
        $offsetX = (int)(($newWidth - $width) / 2);
        $offsetY = (int)(($newHeight - $height) / 2);
        break;
    case 'top':
        $newHeight = (int)($height * $ratio);
        $offsetY = $newHeight - $height;
        break;
    // ... other directions
}

// Create expanded canvas with white background
// Place original image at calculated offset
// Create mask (white where to generate, black for original)
```

**Backend AI Prompt:**
```php
$prompt = "You are an expert image editor. Outpaint this image to fill the white masked areas. " .
          "Seamlessly extend the existing content in a natural and coherent way. " .
          "Match the style, colors, lighting, and context of the original image. " .
          "The black area shows the original image that must remain unchanged.";
```

**AI Model Configuration:**
- Model: `gemini-2.5-flash-image-preview`
- Temperature: **0.9** (slightly lower for more consistent outpainting)
- TopK: 40
- TopP: 0.95
- Max Output Tokens: 8192
- Timeout: 120 seconds

**Output Behavior:**
- ✅ Creates new expanded image beside original
- ✅ Auto-selects new image
- ✅ Shows "Expanding..." skeleton loader with status text
- ✅ Shows success alert with info
- ✅ Label: "Expanded Image"

**Example Use Cases:**
- Expand all directions for social media cropping (square → landscape)
- Extend top for more sky in landscape photos
- Extend bottom for more foreground/ground
- Extend left/right for wider panoramas

**Expansion Ratios:**
- Default: 1.5 (150% size)
- Configurable range: 1.2 to 2.0
- Example: 1000×800 → 1500×1200 (all directions)

---

## 🔧 Non-AI Functions (Traditional Algorithms)

### **5. Upscale Image (`handleUpscaleImage`)**

**Purpose:** Increase image resolution using traditional interpolation

**Endpoint:** `POST /api/upscale-image`

**Method:** ❌ **No AI** - Uses PHP GD Library with bicubic interpolation

**Workflow:**
1. User selects an image
2. System prompts for scale factor (1.5x to 4.0x)
3. Backend uses `imagecopyresampled()` with bicubic interpolation
4. Returns higher resolution image
5. New upscaled image appears beside original

**Client-Side Implementation:**
```javascript
// Location: handleUpscaleImage() function
const scaleInput = await showPrompt(
    'Upscale Image',
    'Enter scale factor (1.5 to 4.0, e.g., 2 for 2x):',
    'Scale',
    '2'
);

const scaleFactor = parseFloat(scaleInput);
// Validates: 1.5 ≤ scaleFactor ≤ 4.0

{
    image: imageDataUrl,
    scale: scaleFactor
}
```

**Backend Processing:**
```php
// Create image from base64
$img = imagecreatefromstring($imageData);
$width = imagesx($img);
$height = imagesy($img);
$scale = $validated['scale'] ?? 2.0;

$newWidth = (int)($width * $scale);
$newHeight = (int)($height * $scale);

// Create upscaled image
$upscaledImg = imagecreatetruecolor($newWidth, $newHeight);

// Preserve transparency for PNG
imagealphablending($upscaledImg, false);
imagesavealpha($upscaledImg, true);

// Use bicubic interpolation
imagecopyresampled(
    $upscaledImg, $img,
    0, 0, 0, 0,
    $newWidth, $newHeight,
    $width, $height
);
```

**Algorithm Details:**
- **Interpolation Method:** Bicubic (via GD's `imagecopyresampled`)
- **Transparency:** Preserved for PNG images
- **Compression:** Maximum PNG compression (level 9)
- **Quality:** Good for moderate upscaling, not AI-enhanced

**Output Behavior:**
- ✅ Creates new upscaled image beside original
- ✅ Shows detailed success message with dimensions
- ✅ Auto-selects new image
- ✅ Shows "Upscaling..." skeleton loader
- ✅ Label: "Upscaled Image"

**Example:**
- Input: 1000×800 @ 2.0x scale
- Output: 2000×1600
- Message: "Image upscaled from 1000x800 to 2000x1600!"

**Future Enhancement Opportunity:**
> 💡 Could be upgraded to use AI-powered upscaling models like:
> - Real-ESRGAN
> - GFPGAN (for faces)
> - BSRGAN
> - SwinIR

---

### **6. Remove Background (`handleRemoveBackground`)**

**Purpose:** Remove background and create transparent PNG

**Endpoint:** `POST /api/remove-background`

**Method:** ❌ **No AI** - Uses advanced edge detection algorithm

**Workflow:**
1. User selects an image
2. System immediately processes (no prompts needed)
3. Backend applies edge detection and color analysis
4. Creates PNG with transparent background
5. New transparent image appears beside original

**Client-Side Implementation:**
```javascript
// Location: handleRemoveBackground() function
// No user input required - just click the button

{
    image: imageDataUrl
}
```

**Backend Algorithm (Improved Method):**
```php
// 1. Edge Detection using Sobel Filters
$edgeMap = [];
for ($y = 1; $y < $height - 1; $y++) {
    for ($x = 1; $x < $width - 1; $x++) {
        // Calculate gradients using Sobel operator
        $gx = ... // Horizontal gradient
        $gy = ... // Vertical gradient
        $edgeStrength = sqrt($gx * $gx + $gy * $gy);
        $edgeMap[$y][$x] = ($edgeStrength > $edgeThreshold);
    }
}

// 2. Sample corner colors to identify background
$corners = [top-left, top-right, bottom-left, bottom-right];
$dominantBgColor = most_common($corners);

// 3. Apply Gaussian blur to corners for better sampling
imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR);

// 4. Process each pixel
for ($x = 0; $x < $width; $x++) {
    for ($y = 0; $y < $height; $y++) {
        // Calculate Euclidean distance from background color
        $distance = sqrt(
            pow($r - $bgR, 2) +
            pow($g - $bgG, 2) +
            pow($b - $bgB, 2)
        );
        
        $isEdge = isset($edgeMap[$y][$x]) && $edgeMap[$y][$x];
        
        if ($distance < $tolerance) {
            // Similar to background - make transparent with gradient
            $alpha = (int)(127 * ($distance / $tolerance));
            if ($isEdge) {
                // Keep edges more opaque to prevent fringing
                $alpha = max(0, $alpha - 40);
            }
        } else {
            // Different from background - keep opaque
            $alpha = 0;
        }
        
        // Set pixel with calculated alpha
        imagesetpixel($transparent, $x, $y, $color_with_alpha);
    }
}

// 5. Apply smooth filter to edges
imagefilter($transparent, IMG_FILTER_SMOOTH, 1);
```

**Algorithm Features:**
1. **Edge Detection:**
   - Sobel operator for gradient calculation
   - Threshold: 30 (configurable)
   - Identifies subject boundaries

2. **Background Sampling:**
   - Samples 4 corners (10% of dimensions)
   - Applies Gaussian blur for noise reduction
   - Selects most common corner color

3. **Transparency Calculation:**
   - Euclidean color distance from background
   - Tolerance: 50 (configurable)
   - Gradient transparency for smooth transitions

4. **Edge Preservation:**
   - Detected edges stay more opaque (-40 alpha)
   - Prevents white fringing around subjects
   - Maintains fine details

5. **Post-Processing:**
   - Smooth filter (level 1) for natural edges
   - Maximum PNG compression
   - Preserves full alpha channel

**Fallback Method (Simple):**
If improved algorithm fails:
```php
// 1. Sample corners for background color
// 2. Calculate simple color difference (Manhattan distance)
// 3. Make similar colors transparent (tolerance: 30)
// 4. No edge detection or gradient
```

**Output Behavior:**
- ✅ Creates PNG with transparency beside original
- ✅ Auto-selects new image
- ✅ Shows "Removing background..." skeleton loader
- ✅ Shows method used in success message
- ✅ Label: "No Background"

**Success Messages:**
- **Improved algorithm:** "Background removed (WIDTHxHEIGHT, PNG with transparency)"
- **Fallback method:** "Background removed using simple algorithm"

**Best Results With:**
- ✅ Solid or simple backgrounds
- ✅ Clear subject-background contrast
- ✅ Subjects away from edges
- ✅ Consistent lighting

**Limitations:**
- ⚠️ Complex backgrounds (patterns, gradients) may not remove cleanly
- ⚠️ Low contrast subjects may lose details
- ⚠️ Subjects touching edges may be partially removed

**Future Enhancement Opportunity:**
> 💡 Could be upgraded to use AI models like:
> - Rembg (U²-Net based)
> - SAM (Segment Anything Model)
> - Remove.bg API
> - BackgroundRemoval.ai

---

## 🔄 Common Patterns

### **All Functions Follow This Workflow:**

```
1. Validation
   ↓
2. Set Loading State (setIsGenerating(true))
   ↓
3. Show Skeleton Loader
   ↓
4. Convert Image to Base64
   ↓
5. Send to Backend API
   ↓
6. Backend Processing (AI or Algorithm)
   ↓
7. Receive Result
   ↓
8. Load Generated Image
   ↓
9. Add to Canvas (beside original or replace)
   ↓
10. Clear Loading State
    ↓
11. Show Success/Error Alert
```

### **Image Placement Strategy:**

| Function | Placement | Spacing |
|----------|-----------|---------|
| Erase | Beside original | +20px (`ERASE_GAP`) |
| Replace Text | Beside original | +20px (`ERASE_GAP`) |
| **AI Generate** | **Replace in-place** | **N/A** |
| Expand Image | Beside original | +50px (`OBJECT_SPACING`) |
| Upscale | Beside original | +50px (`OBJECT_SPACING`) |
| Remove BG | Beside original | +50px (`OBJECT_SPACING`) |

### **Error Handling:**

All functions include:
- ✅ Try-catch blocks
- ✅ CORS error detection
- ✅ Network error handling
- ✅ API error response parsing
- ✅ User-friendly error messages
- ✅ Loading state cleanup in finally block
- ✅ Console error logging for debugging

---

## 🎨 Skeleton Loaders

### **Enhancement Operations** (beside original):
```javascript
// Shows beside original image at exact position + offset
{
    position: 'absolute',
    left: panX + selectedObject.x * scale + selectedObject.image.width * scale + 30,
    top: panY + selectedObject.y * scale,
    width: selectedObject.image.width * scale,
    height: selectedObject.image.height * scale
}
```

**Status Messages:**
- `generatingType === 'expand'` → "Expanding..."
- `generatingType === 'upscale'` → "Upscaling..."
- `generatingType === 'remove-bg'` → "Removing background..."

### **Full Overlay** (for other operations):
```javascript
// Centers in viewport with backdrop
{
    position: 'absolute',
    top: 0, left: 0,
    width: '100%', height: '100%',
    background: 'color-mix(in oklab, var(--color-background) 70%, transparent)'
}
```

Shows generic skeleton animation without specific status.

---

## 🔧 Technical Configuration

### **Gemini API Settings:**

```javascript
// Model
const imageModel = 'gemini-2.5-flash-image-preview';

// Generation Config
{
    temperature: 1.0,      // 0.9 for expand
    topK: 40,
    topP: 0.95,
    maxOutputTokens: 8192
}

// Request Settings
{
    timeout: 120,          // seconds
    verifySSL: false,      // withoutVerifying()
    method: 'POST'
}
```

### **Image Format:**

**Input:**
- Format: Base64 data URL
- MIME type: `image/png` or `image/jpeg`
- Encoding: `data:image/png;base64,[BASE64_DATA]`

**Output:**
- Format: Base64 data URL
- MIME type: `image/png`
- Transparency: Preserved
- Compression: Maximum (level 9 for PHP GD)

### **Canvas Constants:**

```javascript
const CANVAS_DEFAULTS = {
    ERASE_GAP: 20,           // Spacing for erase/text results
    OBJECT_SPACING: 50,      // Spacing for other results
    UNDO_LIMIT: 50,          // Max undo stack size
    TOAST_DISMISS_MS: 3000   // Alert auto-dismiss time
};
```

---

## 📊 Performance Notes

### **Processing Times (Approximate):**

| Function | Average Time | Depends On |
|----------|-------------|------------|
| Erase | 5-15s | Mask complexity, image size |
| Replace Text | 5-15s | Text length, image size |
| AI Generate | 5-20s | Prompt complexity, image size |
| Expand Image | 10-25s | Expansion ratio, image size |
| Upscale | 1-3s | Scale factor, image size |
| Remove BG | 2-5s | Image size, complexity |

### **Gemini API Rate Limits:**
- Check your Google Cloud Console for specific limits
- Default: ~60 requests per minute
- Implement user-side rate limiting if needed

### **Image Size Recommendations:**
- **Optimal:** 512×512 to 2048×2048
- **Maximum:** 4096×4096 (depends on API limits)
- **Large images:** May timeout or fail, consider downscaling

---

## 🐛 Known Issues & Limitations

### **1. Erase Function:**
- ❌ May not perfectly remove objects with complex backgrounds
- ❌ CORS errors with external images
- ⚠️ Quality depends on mask accuracy

### **2. Replace Text:**
- ⚠️ Text spelling/style may vary
- ⚠️ Works best with clear, simple fonts
- ❌ May not perfectly match original text style

### **3. AI Generate:**
- ⚠️ Results quality varies with prompt clarity
- ⚠️ May not follow prompt exactly
- ❌ Replaces image in-place (can't compare side-by-side)

### **4. Expand Image:**
- ⚠️ May have visible seams with complex images
- ⚠️ Slower processing for large expansions
- ⚠️ Quality depends on content coherency

### **5. Upscale:**
- ❌ **Not AI-powered** - Limited quality improvement
- ⚠️ May introduce blur or artifacts at high scales
- ⚠️ Best for moderate upscaling (2x or less)

### **6. Remove Background:**
- ❌ **Not AI-powered** - Works best with simple backgrounds
- ⚠️ May leave artifacts with complex backgrounds
- ⚠️ Edge fringing on low-contrast subjects
- ⚠️ Requires subject to be clearly separated from background

---

## 🚀 Future Improvements

### **High Priority:**

1. **Improve Upscale with AI:**
   - Integrate Real-ESRGAN for true AI upscaling
   - Better quality, less artifacts
   - Especially good for faces and details

2. **Improve Remove Background with AI:**
   - Integrate Rembg or SAM (Segment Anything)
   - Much better accuracy
   - Handle complex backgrounds

3. **Add Prompt Templates:**
   - Predefined prompts for common tasks
   - "Make it look professional"
   - "Add dramatic lighting"
   - "Change to nighttime"

### **Medium Priority:**

4. **Batch Processing:**
   - Apply same operation to multiple images
   - Useful for consistent edits

5. **History/Undo for AI Operations:**
   - Currently only for brush strokes
   - Need full state history

6. **Prompt Suggestions:**
   - Show example prompts
   - Auto-complete based on image content

### **Low Priority:**

7. **Advanced Masking:**
   - Magic wand selection
   - Rectangle/lasso tools
   - More precise masks

8. **Custom AI Parameters:**
   - Let users adjust temperature
   - Control creativity vs accuracy

9. **Result Comparison:**
   - Side-by-side comparison slider
   - A/B testing of different prompts

---

## 📝 Code Locations

### **Frontend (React/TypeScript):**
```
resources/js/pages/canvas-editor.tsx

Line ~1258:  handleErase()
Line ~1397:  handleReplaceText()
Line ~1577:  handleGenerate()
Line ~1749:  handleExpandImage()
Line ~1834:  handleUpscaleImage()
Line ~1918:  handleRemoveBackground()
Line ~1749:  callApi() utility
```

### **Backend (PHP/Laravel):**
```
app/Http/Controllers/ImageEditController.php

Line ~14:   generateWithMask()    // Erase, Replace Text, AI Generate
Line ~228:  expandImage()         // Expand/Outpaint
Line ~400:  upscaleImage()        // Upscale
Line ~484:  removeBackground()    // Remove BG
```

### **Routes:**
```
routes/web.php

Line ~108:  POST /api/generate-with-mask
Line ~110:  POST /api/expand-image
Line ~111:  POST /api/upscale-image
Line ~112:  POST /api/remove-background
```

---

## 🔐 Security Notes

1. **CSRF Protection:**
   - All requests include CSRF token
   - Token retrieved from meta tag: `<meta name="csrf-token">`

2. **Image Validation:**
   - Base64 format validation
   - Data URL parsing
   - MIME type checking

3. **CORS Handling:**
   - Catches CORS errors gracefully
   - Suggests uploading local images
   - Sets `crossOrigin = 'anonymous'` on images

4. **API Key Security:**
   - Gemini API key stored server-side only
   - Never exposed to client
   - Configured in `.env`: `GEMINI_API_KEY`

---

## 📚 Related Documentation

- [Gemini API Documentation](https://ai.google.dev/docs)
- [Canvas API Reference](https://developer.mozilla.org/en-US/docs/Web/API/Canvas_API)
- [PHP GD Library](https://www.php.net/manual/en/book.image.php)
- [Laravel HTTP Client](https://laravel.com/docs/http-client)

---

**End of Documentation**
