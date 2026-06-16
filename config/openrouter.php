<?php

return [

    'api_key' => env('OPENROUTER_API_KEY'),

    'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),

    'app_name' => env('OPENROUTER_APP_NAME', env('APP_NAME', 'SnapDraft')),

    'site_url' => env('OPENROUTER_SITE_URL', env('APP_URL', 'http://localhost')),

    'timeout' => (int) env('AI_ANALYZE_TIMEOUT', env('OPENROUTER_TIMEOUT', 180)),

    'connect_timeout' => (int) env('OPENROUTER_CONNECT_TIMEOUT', 15),

];
