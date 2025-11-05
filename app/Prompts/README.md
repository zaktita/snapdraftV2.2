# SnapDraft AI Prompts

This directory contains all AI generation prompt templates used throughout SnapDraft. Each prompt supports variable substitution using `{variable_name}` syntax.

## Available Prompts

### CSV Wizard
- **`csv-generation.txt`** - Basic CSV image generation without products
  - Variables: `{caption}`
  
- **`csv-generation-with-products.txt`** - CSV generation with product images
  - Variables: `{caption}`, `{product_names}`, `{product_count_suffix}`

### Brand Analysis
- **`brand-analysis.txt`** - Extract brand style from reference images
  - Variables: None

### Image Editing
- **`image-edit.txt`** - Basic image editing
  - Variables: `{edit_instructions}`
  
- **`image-edit-with-references.txt`** - Image editing with brand references
  - Variables: `{edit_instructions}`

### Canvas Editor
- **`generative-fill.txt`** - Basic generative fill for rectangular areas
  - Variables: `{task}`, `{selection_x}`, `{selection_y}`, `{selection_width}`, `{selection_height}`
  
- **`generative-fill-with-references.txt`** - Generative fill with brand consistency
  - Variables: `{task}`, `{selection_x}`, `{selection_y}`, `{selection_width}`, `{selection_height}`
  
- **`mask-generation.txt`** - Mask-based inpainting
  - Variables: `{task}`, `{brush_strokes}`, `{image_width}`, `{image_height}`
  
- **`mask-generation-with-references.txt`** - Mask-based inpainting with brand consistency
  - Variables: `{task}`, `{brush_strokes}`, `{image_width}`, `{image_height}`

## Usage

### In Code

```php
use App\Services\PromptService;

$promptService = app(PromptService::class);

// Basic rendering with variables
$prompt = $promptService->render('csv-generation', [
    'caption' => 'Summer sale announcement'
]);

// Using helper methods
$prompt = $promptService->csvGeneration('Summer sale', ['product-shoe', 'product-hat']);
$prompt = $promptService->brandAnalysis();
$prompt = $promptService->imageEdit('Make the sky more blue', hasReferences: true);
```

### Direct File Editing

You can directly edit any `.txt` file in this directory. Changes take effect immediately without restarting the server.

**Best practices:**
1. Keep prompts clear and concise
2. Use descriptive variable names in `{curly_braces}`
3. Add comments at the top of complex prompts (not sent to AI)
4. Test changes with `php artisan ai:test-generate "your prompt"`

## Variable Naming Conventions

- Use `snake_case` for variable names
- Be descriptive: `{product_names}` not `{prod}`
- Group related variables: `{selection_x}`, `{selection_y}`, `{selection_width}`, `{selection_height}`
- Use suffixes for pluralization: `{product_count_suffix}` becomes `s` or empty string

## Adding New Prompts

1. Create a new `.txt` file in this directory
2. Use `{variable_name}` syntax for dynamic content
3. Add a helper method to `app/Services/PromptService.php` (optional but recommended)
4. Update this README with the new prompt

Example:

```txt
# new-feature.txt
Generate a {style} image featuring {subject}.
The image should be {mood} and include {elements}.
```

```php
// In PromptService.php
public function newFeature(string $subject, string $style, string $mood, array $elements): string
{
    return $this->render('new-feature', [
        'subject' => $subject,
        'style' => $style,
        'mood' => $mood,
        'elements' => implode(', ', $elements),
    ]);
}
```

## Debugging

### Check which prompts are available:
```php
$prompts = $promptService->listPrompts();
dd($prompts);
```

### View raw template:
```php
$raw = $promptService->getRaw('csv-generation');
echo $raw;
```

### Unreplaced variables:
The system logs warnings when variables in templates aren't replaced. Check `storage/logs/laravel.log` for:
```
Unreplaced variables in prompt 'csv-generation': caption, product_names
```

## Version Control

All prompts are tracked in Git. When tweaking prompts:

1. Test thoroughly before committing
2. Document major changes in commit messages
3. Keep a backup of working prompts if experimenting
4. Consider A/B testing different prompt versions

## Performance Notes

- Prompts are loaded from disk on each use (no caching)
- File reads are fast (~1ms) so caching isn't necessary
- For high-traffic scenarios, consider adding cache layer in PromptService

## Related Files

- **Service**: `app/Services/PromptService.php`
- **AI Integration**: `app/Services/AI/GoogleGeminiService.php`
- **Test Command**: `routes/console.php` (see `ai:test-generate`)
