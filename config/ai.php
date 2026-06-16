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

    'cluster_selection' => [
        'max_images_per_cluster' => (int) env('AI_CLUSTER_MAX_IMAGES', 3),
        'keyword_score_threshold' => (float) env('AI_CLUSTER_KEYWORD_THRESHOLD', 0.15),
        'ambiguous_gap' => (float) env('AI_CLUSTER_AMBIGUOUS_GAP', 0.05),
    ],

];
