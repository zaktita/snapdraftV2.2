<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class PromptService
{
    /**
     * Base path for prompt templates.
     */
    protected string $promptsPath;

    public function __construct()
    {
        $this->promptsPath = app_path('Prompts');
    }

    /**
     * Load and render a prompt template with variables.
     *
     * @param string $promptName The name of the prompt file (without .txt extension)
     * @param array $variables Key-value pairs to replace in the template
     * @return string The rendered prompt
     * @throws \Exception If prompt file not found
     */
    public function render(string $promptName, array $variables = []): string
    {
        $filePath = $this->promptsPath . '/' . $promptName . '.txt';

        if (!File::exists($filePath)) {
            Log::error("Prompt template not found: {$promptName}", ['path' => $filePath]);
            throw new \Exception("Prompt template '{$promptName}' not found");
        }

        $template = File::get($filePath);

        // Replace variables in the format {variable_name}
        foreach ($variables as $key => $value) {
            // Convert value to string if it's not already
            $stringValue = is_array($value) ? json_encode($value) : (string) $value;
            $template = str_replace('{' . $key . '}', $stringValue, $template);
        }

        // Log if there are unreplaced variables (for debugging)
        if (preg_match_all('/\{([a-zA-Z_]+)\}/', $template, $matches)) {
            $unreplacedVars = array_unique($matches[1]);
            Log::warning("Unreplaced variables in prompt '{$promptName}': " . implode(', ', $unreplacedVars));
        }

        return trim($template);
    }

    /**
     * Get the raw template content without variable replacement.
     *
     * @param string $promptName The name of the prompt file (without .txt extension)
     * @return string The raw template
     * @throws \Exception If prompt file not found
     */
    public function getRaw(string $promptName): string
    {
        $filePath = $this->promptsPath . '/' . $promptName . '.txt';

        if (!File::exists($filePath)) {
            throw new \Exception("Prompt template '{$promptName}' not found");
        }

        return File::get($filePath);
    }

    /**
     * List all available prompt templates.
     *
     * @return array Array of prompt names (without .txt extension)
     */
    public function listPrompts(): array
    {
        $files = File::files($this->promptsPath);
        
        return array_map(function ($file) {
            return pathinfo($file, PATHINFO_FILENAME);
        }, $files);
    }

    /**
     * Save or update a prompt template.
     *
     * @param string $promptName The name of the prompt file (without .txt extension)
     * @param string $content The prompt content
     * @return bool Success status
     */
    public function save(string $promptName, string $content): bool
    {
        $filePath = $this->promptsPath . '/' . $promptName . '.txt';
        
        try {
            File::put($filePath, $content);
            Log::info("Prompt template saved: {$promptName}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to save prompt template: {$promptName}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Helper: Build CSV generation prompt with conditional product logic.
     *
     * @param string $caption The caption/description from CSV
     * @param array $productNames Array of product image names
     * @return string The rendered prompt
     */
    public function csvGeneration(string $caption, array $productNames = []): string
    {
        if (empty($productNames)) {
            return $this->render('csv-generation', [
                'caption' => $caption,
            ]);
        }

        // Format product names and determine singular/plural
        $count = count($productNames);
        $suffix = $count > 1 ? 's' : '';
        $names = $count > 1 ? implode(', ', $productNames) : $productNames[0];

        return $this->render('csv-generation-with-products', [
            'caption' => $caption,
            'product_names' => $names,
            'product_count_suffix' => $suffix,
        ]);
    }

    /**
     * Helper: Build brand analysis prompt.
     *
     * @return string The rendered prompt
     */
    public function brandAnalysis(): string
    {
        return $this->render('brand-analysis');
    }

    /**
     * Helper: Build image edit prompt.
     *
     * @param string $editInstructions User's edit instructions
     * @param bool $hasReferences Whether reference images are provided
     * @return string The rendered prompt
     */
    public function imageEdit(string $editInstructions, bool $hasReferences = false): string
    {
        $promptName = $hasReferences ? 'image-edit-with-references' : 'image-edit';
        
        return $this->render($promptName, [
            'edit_instructions' => $editInstructions,
        ]);
    }

    /**
     * Helper: Build generative fill prompt.
     *
     * @param string $task The fill task description
     * @param array $selectionArea ['x' => int, 'y' => int, 'width' => int, 'height' => int]
     * @param bool $hasReferences Whether reference images are provided
     * @return string The rendered prompt
     */
    public function generativeFill(string $task, array $selectionArea, bool $hasReferences = false): string
    {
        $promptName = $hasReferences ? 'generative-fill-with-references' : 'generative-fill';
        
        return $this->render($promptName, [
            'task' => $task,
            'selection_x' => round($selectionArea['x'] ?? 0),
            'selection_y' => round($selectionArea['y'] ?? 0),
            'selection_width' => round($selectionArea['width'] ?? 0),
            'selection_height' => round($selectionArea['height'] ?? 0),
        ]);
    }

    /**
     * Helper: Build mask-based generation prompt.
     *
     * @param string $task The generation task description
     * @param int $brushStrokes Number of brush strokes
     * @param array $imageSize ['width' => int, 'height' => int]
     * @param bool $hasReferences Whether reference images are provided
     * @return string The rendered prompt
     */
    public function maskGeneration(
        string $task,
        int $brushStrokes = 0,
        array $imageSize = [],
        bool $hasReferences = false
    ): string {
        $promptName = $hasReferences ? 'mask-generation-with-references' : 'mask-generation';
        
        return $this->render($promptName, [
            'task' => $task,
            'brush_strokes' => $brushStrokes,
            'image_width' => $imageSize['width'] ?? 'unknown',
            'image_height' => $imageSize['height'] ?? 'unknown',
        ]);
    }
}
