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
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash-exp'),
        // Image generation model (used by Style Mirror)
        'image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image'),
        'rate_limit' => env('GEMINI_RATE_LIMIT', 30), // Requests per minute
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL', 'openrouter/auto'),
        // Image model for canvas editor (inpainting, outpainting, prompt-based editing)
        'image_model' => 'openai/gpt-5-image',
        'site_url' => env('APP_URL'),
        'site_name' => env('APP_NAME', 'SnapDraft'),
    ],

];
