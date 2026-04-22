<?php

return [
    'api_key' => env('POSTHOG_PROJECT_TOKEN', ''),
    'host' => env('POSTHOG_HOST', ''),
    'disabled' => env('POSTHOG_DISABLED', false),

    // Client-only: avoids loading recorder / dead-clicks subresources from us-assets.i.posthog.com
    // when a strict CSP, corporate filter, or blocker would reject them.
    'disable_session_recording' => (bool) env('POSTHOG_DISABLE_SESSION_RECORDING', false),
    'capture_dead_clicks' => filter_var(env('POSTHOG_CAPTURE_DEAD_CLICKS', true), FILTER_VALIDATE_BOOLEAN),
];
