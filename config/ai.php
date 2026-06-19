<?php

return [

    'default_analyzer_slug' => env('AI_DEFAULT_ANALYZER', 'gpt-4o'),

    'default_generator_slug' => env('AI_DEFAULT_GENERATOR', 'nano-banana-2'),

    'default_post_model_slug' => env('AI_DEFAULT_POST_MODEL', 'gpt-4o'),

    'prompts' => [
        'extract' => [
            'system' => resource_path('prompts/brand-extract-system.md'),
            'schema' => resource_path('prompts/brand-dna-schema.json'),
            'json_heading' => 'DNA JSON',
            'prose_heading' => 'Analysis',
            'summary_heading' => 'Summary',
        ],
        'generate_post' => [
            'system' => resource_path('prompts/post-generate-system.md'),
            'schema' => resource_path('prompts/post-prompt-schema.json'),
            'json_heading' => 'JSON Prompt',
            'prose_heading' => 'On-brand check',
            'summary_heading' => 'Tweaks',
        ],
    ],

    'post_generation' => [
        // When false, Step 2 text model uses cluster metadata text only (no base64 reference uploads per row).
        'attach_cluster_images' => (bool) env('AI_POST_ATTACH_CLUSTER_IMAGES', false),
    ],

    'cluster_selection' => [
        'min_images_per_cluster' => (int) env('AI_CLUSTER_MIN_IMAGES', 3),
        'max_images_per_cluster' => (int) env('AI_CLUSTER_MAX_IMAGES', 3),
        'keyword_score_threshold' => (float) env('AI_CLUSTER_KEYWORD_THRESHOLD', 0.15),
        'ambiguous_gap' => (float) env('AI_CLUSTER_AMBIGUOUS_GAP', 0.05),
    ],

  /*
  |--------------------------------------------------------------------------
  | CSV / PromptForge image generation driver
  |--------------------------------------------------------------------------
  |
  | auto       – try Gemini, fall back to OpenRouter on auth failures
  | gemini     – direct Gemini API only
  | openrouter – OpenRouter image model only
  |
  */
    'image_driver' => env('AI_IMAGE_DRIVER', 'auto'),

  /*
  |--------------------------------------------------------------------------
  | Brand clustering / DNA extraction driver (Phase 1)
  |--------------------------------------------------------------------------
  |
  | auto       – try Gemini ClusteringService, fall back to OpenRouter DNA extract
  | gemini     – Gemini clustering only
  | openrouter – OpenRouter BrandDnaExtractor only
  |
  */
    'cluster_driver' => env('AI_CLUSTER_DRIVER', 'auto'),

];
