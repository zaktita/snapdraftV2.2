<?php

return [
    'api_key' => env('POSTHOG_PROJECT_TOKEN', ''),
    // US cloud default matches resources/js/app.tsx; use https://eu.i.posthog.com for EU projects.
    'host' => env('POSTHOG_HOST') ?: 'https://us.i.posthog.com',
    'disabled' => filter_var(env('POSTHOG_DISABLED', false), FILTER_VALIDATE_BOOLEAN),

    // Client-only: avoids loading recorder / dead-clicks subresources from us-assets.i.posthog.com
    // when a strict CSP, corporate filter, or blocker would reject them.
    'disable_session_recording' => filter_var(env('POSTHOG_DISABLE_SESSION_RECORDING', false), FILTER_VALIDATE_BOOLEAN),
    'capture_dead_clicks' => filter_var(env('POSTHOG_CAPTURE_DEAD_CLICKS', true), FILTER_VALIDATE_BOOLEAN),
];
