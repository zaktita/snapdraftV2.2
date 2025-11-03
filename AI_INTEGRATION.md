# AI Integration Guide

## Overview

SnapDraft uses AI services to analyze brand style from reference images and generate new images that match the brand's visual identity. The system is built with a primary/fallback architecture for reliability.

## Architecture

### Services

1. **GoogleGeminiService** (Primary)
   - Location: `app/Services/AI/GoogleGeminiService.php`
   - Uses Google's Gemini API for vision and image generation
   - Primary service for all AI operations

2. **OpenRouterService** (Fallback)
   - Location: `app/Services/AI/OpenRouterService.php`
   - Routes requests through OpenRouter to various AI models
   - Automatically used if Gemini fails

3. **AIServiceManager**
   - Location: `app/Services/AI/AIServiceManager.php`
   - Manages primary/fallback logic
   - Handles error recovery and logging

### Queue Jobs

1. **AnalyzeBrandStyleJob**
   - Analyzes 5-10 reference images to extract brand style
   - Stores analysis in `brand_references.analysis_data` and `projects.settings`
   - Location: `app/Jobs/AnalyzeBrandStyleJob.php`

2. **GenerateSingleImageJob**
   - Generates one image from a prompt + style guide
   - Creates `Image` record with metadata
   - Tracks generation in `generation_history` table
   - Location: `app/Jobs/GenerateSingleImageJob.php`

3. **GenerateBatchImagesJob**
   - Processes CSV data to generate multiple images
   - Dispatches `GenerateSingleImageJob` for each row with 2-second delays
   - Uses Laravel's job batching for progress tracking
   - Location: `app/Jobs/GenerateBatchImagesJob.php`

## Implementation Steps

### 1. Add API Keys

Update your `.env` file:

```env
# Google Gemini
GEMINI_API_KEY=your_gemini_api_key_here
GEMINI_MODEL=gemini-pro-vision
GEMINI_RATE_LIMIT=30

# OpenRouter (optional fallback)
OPENROUTER_API_KEY=your_openrouter_api_key_here
OPENROUTER_MODEL=openrouter/auto
```

### 2. Implement GoogleGeminiService

Edit `app/Services/AI/GoogleGeminiService.php`:

#### `analyzeBrandStyle()` Method

This method should:
1. Accept array of image file paths
2. Convert images to base64 or URLs
3. Send to Gemini Vision API with analysis prompt
4. Parse response for style attributes
5. Return structured array:

```php
return [
    'colors' => [
        'primary' => '#000000',
        'secondary' => '#FFFFFF',
        'accent' => '#FF0000',
        'palette' => ['#000000', '#FFFFFF', '#FF0000'],
    ],
    'typography' => [
        'style' => 'modern|classic|playful|professional',
        'font_family' => 'sans-serif|serif|monospace',
        'weight' => 'light|regular|bold',
    ],
    'composition' => [
        'layout' => 'centered|asymmetric|grid|minimal',
        'balance' => 'symmetrical|asymmetrical',
        'whitespace' => 'generous|compact',
    ],
    'mood' => 'professional|playful|elegant|bold|minimal',
    'analyzed_at' => now()->toIso8601String(),
];
```

**Sample Prompt:**
```
Analyze these brand reference images and extract the visual style.
Identify:
- Color palette (primary, secondary, accent colors)
- Typography style (modern, classic, etc.)
- Composition patterns (layout, balance)
- Overall mood and tone

Return as JSON with keys: colors, typography, composition, mood.
```

#### `generateImage()` Method

This method should:
1. Accept prompt, styleGuide (optional), and format
2. Build enhanced prompt incorporating style guide
3. Determine dimensions:
   - `square`: 1024x1024
   - `landscape`: 1792x1024
   - `portrait`: 1024x1792
4. Call Gemini Imagen API
5. Download generated image
6. Save to storage using `FileUploadService`
7. Return:

```php
return [
    'url' => 'path/to/generated/image.png',
    'thumbnail_url' => 'path/to/thumbnail.png',
    'prompt' => $prompt,
    'model' => 'gemini-pro-vision',
    'metadata' => [
        'format' => $format,
        'style_applied' => !is_null($styleGuide),
        'dimensions' => ['width' => 1024, 'height' => 1024],
        'tokens_used' => 1234, // if available
        'cost' => 0.05, // if tracked
        'generated_at' => now()->toIso8601String(),
    ],
];
```

**Sample Prompt Construction:**
```php
$stylePrompt = '';
if ($styleGuide) {
    $colors = implode(', ', $styleGuide['colors']['palette'] ?? []);
    $mood = $styleGuide['mood'] ?? 'professional';
    $stylePrompt = "Style: {$mood} mood, color palette: {$colors}. ";
}

$fullPrompt = $stylePrompt . $prompt;
```

### 3. Implement OpenRouterService (Optional)

Similar structure to Gemini, but routes through OpenRouter API.

Edit `app/Services/AI/OpenRouterService.php` with the same method signatures.

**OpenRouter Headers:**
```php
$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . $this->apiKey,
    'HTTP-Referer' => config('services.openrouter.site_url'),
    'X-Title' => config('services.openrouter.site_name'),
])->post($endpoint, $data);
```

### 4. Integrate with Wizards

The wizard controllers already have TODO comments ready for job dispatching:

**CSV Wizard** (`app/Http/Controllers/Wizards/CSVWizardController.php`):
```php
// After project creation, uncomment:
GenerateBatchImagesJob::dispatch($project);
```

**Images Wizard** (`app/Http/Controllers/Wizards/ImagesWizardController.php`):
```php
// After project creation:
AnalyzeBrandStyleJob::dispatch($project)
    ->chain([
        new GenerateSingleImageJob($project, $project->description, $format),
    ]);
```

**Text Wizard** (`app/Http/Controllers/Wizards/TextWizardController.php`):
```php
// After project creation:
if ($request->hasFile('reference_images')) {
    AnalyzeBrandStyleJob::dispatch($project)
        ->chain([
            new GenerateSingleImageJob($project, $project->description, $format),
        ]);
} else {
    GenerateSingleImageJob::dispatch($project, $project->description, $format);
}
```

### 5. Test the Integration

1. **Test Brand Analysis:**
   ```php
   php artisan tinker
   $project = Project::find(1);
   AnalyzeBrandStyleJob::dispatch($project);
   ```

2. **Test Single Generation:**
   ```php
   GenerateSingleImageJob::dispatch($project, 'A modern product photo', 'square');
   ```

3. **Test Batch Generation:**
   ```php
   GenerateBatchImagesJob::dispatch($project);
   ```

4. **Monitor Queue:**
   ```bash
   php artisan queue:work --tries=3
   ```

## Database Tracking

### generation_history Table

Every AI request is tracked:
- `user_id`: Who initiated the request
- `project_id`: Which project it belongs to
- `ai_model`: Service used (Gemini, OpenRouter)
- `tokens_used`: API token consumption
- `cost`: Calculated cost (if tracked)
- `status`: processing, completed, failed
- `error_message`: If failed, why

Access via:
```php
$user->generationHistory;
$project->generationHistory;
```

## Error Handling

The system includes comprehensive error handling:

1. **Service Level**: Try primary, fallback to secondary
2. **Job Level**: Jobs fail gracefully, update status
3. **Logging**: All operations logged to `storage/logs/laravel.log`
4. **User Feedback**: Errors stored in project settings

Check logs:
```bash
tail -f storage/logs/laravel.log
```

## Rate Limiting

- **Batch jobs**: 2-second delay between generations (configurable in `GenerateBatchImagesJob`)
- **API limits**: Tracked in `generation_history` table
- **Per-user limits**: TODO - implement in middleware

## File Storage

Generated images are stored at:
```
storage/app/public/projects/{project_id}/images/{filename}
```

Thumbnails at:
```
storage/app/public/projects/{project_id}/images/thumbnails/{filename}
```

Cleanup happens automatically via `CleanOrphanedFilesJob` (daily at 2 AM).

## Next Steps

1. ✅ Implement `GoogleGeminiService::analyzeBrandStyle()`
2. ✅ Implement `GoogleGeminiService::generateImage()`
3. ✅ Uncomment job dispatches in wizard controllers
4. ✅ Test with sample data
5. ✅ Monitor and optimize rate limits
6. ✅ Add cost tracking and user quotas
