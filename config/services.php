<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Services Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AI image generation services used by SnapDraft.
    | Google Gemini is the primary service, OpenRouter is the fallback.
    |
    */

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        // Text/analysis model
        'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
        // Image generation model (default for most tasks)
        'image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image'),
        // Text-to-image model (no references)
        // Text-to-image model (used when no reference images are supplied)
        // Align fallback name with service default; update .env if Google changes naming.
        'text_to_image_model' => env('GEMINI_TEXT_TO_IMAGE_MODEL', 'imagen-3.0-generate-001'),
        // Text-accurate model (4x credits). Prefer stable non-preview for reliability.
        'text_accurate_model' => env('GEMINI_TEXT_ACCURATE_MODEL', 'gemini-2.0-flash-exp'),
        'rate_limit' => env('GEMINI_RATE_LIMIT', 30), // Requests per minute
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL', 'bytedance-seed/seedream-4.5'),
        // Image model for canvas editor (inpainting, outpainting, prompt-based editing)
        'image_model' => 'bytedance-seed/seedream-4.5',
        'site_url' => env('APP_URL'),
        'site_name' => env('APP_NAME', 'SnapDraft'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Lemon Squeezy Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Lemon Squeezy payment processing and subscriptions.
    | Get your API key and webhook secret from the Lemon Squeezy dashboard.
    |
    */

    'lemonsqueezy' => [
        'api_key' => env('LEMON_SQUEEZY_API_KEY'),
        'store_id' => env('LEMON_SQUEEZY_STORE_ID'),
        'webhook_secret' => env('LEMON_SQUEEZY_WEBHOOK_SECRET'),
        'signing_secret' => env('LEMON_SQUEEZY_WEBHOOK_SECRET'), // Alias for consistency
        'test_mode' => env('LEMON_SQUEEZY_TEST_MODE', false),
        'variants' => [
            'launch_monthly' => env('LEMON_SQUEEZY_LAUNCH_MONTHLY_VARIANT_ID'),
            'launch_yearly' => env('LEMON_SQUEEZY_LAUNCH_YEARLY_VARIANT_ID'),
            'growth_monthly' => env('LEMON_SQUEEZY_GROWTH_MONTHLY_VARIANT_ID'),
            'growth_yearly' => env('LEMON_SQUEEZY_GROWTH_YEARLY_VARIANT_ID'),
            'scale_monthly' => env('LEMON_SQUEEZY_SCALE_MONTHLY_VARIANT_ID'),
            'scale_yearly' => env('LEMON_SQUEEZY_SCALE_YEARLY_VARIANT_ID'),
        ],
    ],

];
