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
        'model' => env('GEMINI_MODEL', 'gemini-3.1-pro-preview'),
        // Vision/analysis model — used for brand reference image analysis
        'vision_model' => env('GEMINI_VISION_MODEL', 'gemini-3.1-pro-preview'),
        // Image generation model (default for most tasks)
        'image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-3.1-flash-image-preview'),
        // Text-to-image model (no references)
        // Text-to-image model (used when no reference images are supplied)
        // Align fallback name with service default; update .env if Google changes naming.
        'text_to_image_model' => env('GEMINI_TEXT_TO_IMAGE_MODEL', 'imagen-3.0-generate-001'),
        // Text-accurate model (4x credits). Prefer stable non-preview for reliability.
        'text_accurate_model' => env('GEMINI_TEXT_ACCURATE_MODEL', 'gemini-3.1-flash-image-preview'),
        // Step-2 prompt crafting model: cluster selection + image text per CSV row
        'prompt_model' => env('GEMINI_PROMPT_MODEL', 'gemini-3-flash-preview'),
        'rate_limit' => env('GEMINI_RATE_LIMIT', 30), // Requests per minute
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'model' => env('OPENROUTER_MODEL', 'bytedance-seed/seedream-4.5'),
        // Image model for canvas editor (inpainting, outpainting, prompt-based editing)
        'image_model' => 'bytedance-seed/seedream-4.5',
        // Comma-separated model list for the multi-model prompt-tester UI
        'prompt_models' => env('OPENROUTER_PROMPT_MODELS', 'google/gemini-3.1-pro-preview,openai/gpt-4o-mini'),
        'site_url' => env('OPENROUTER_SITE_URL', env('APP_URL')),
        'site_name' => env('OPENROUTER_APP_NAME', env('APP_NAME', 'SnapDraft')),
    ],

    'ai' => [
        // Primary provider for generation/editing; fallback provider is automatic per call.
        'preferred' => env('AI_PROVIDER', 'gemini'),
        'enable_fallback' => env('AI_ENABLE_FALLBACK', true),
        'timeout_seconds' => env('AI_TIMEOUT_SECONDS', 180),

        // CSV pipeline tuning.
        'csv_batch_concurrency' => env('AI_CSV_BATCH_CONCURRENCY', 1),
        'csv_batch_delay_seconds' => env('AI_CSV_BATCH_DELAY_SECONDS', 2),
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
        'verify_ssl' => env('LEMON_SQUEEZY_VERIFY_SSL', true), // Set to false in .env for local XAMPP dev only
        'variants' => [
            'starter_monthly'  => env('LEMON_SQUEEZY_STARTER_MONTHLY_VARIANT_ID'),
            'starter_yearly'   => env('LEMON_SQUEEZY_STARTER_YEARLY_VARIANT_ID'),
            'pro_monthly'      => env('LEMON_SQUEEZY_PRO_MONTHLY_VARIANT_ID'),
            'pro_yearly'       => env('LEMON_SQUEEZY_PRO_YEARLY_VARIANT_ID'),
            'business_monthly' => env('LEMON_SQUEEZY_BUSINESS_MONTHLY_VARIANT_ID'),
            'business_yearly'  => env('LEMON_SQUEEZY_BUSINESS_YEARLY_VARIANT_ID'),
            // Invite-only grandfather (optional LS product)
            'beta_monthly'     => env('LEMON_SQUEEZY_BETA_MONTHLY_VARIANT_ID'),
            'beta_yearly'      => env('LEMON_SQUEEZY_BETA_YEARLY_VARIANT_ID'),
        ],
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'fal' => [
        'api_key' => env('FAL_API_KEY'),
    ],

];
