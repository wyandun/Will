<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Set CORS_ALLOWED_ORIGINS in your environment as a comma-separated list
    | of exact origins that are permitted to make cross-origin requests.
    |
    | Example:
    |   CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com
    |
    | Never use a wildcard (*) when supports_credentials is true — browsers
    | will reject the response and the request will fail.
    |
    */

    'allowed_origins' => array_filter(
        explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000'))
    ),

    /*
    |--------------------------------------------------------------------------
    | Allowed Origin Patterns
    |--------------------------------------------------------------------------
    |
    | In addition to exact origins above, you may specify regex patterns to
    | match dynamic origins such as preview or staging deployments.
    |
    | Set CORS_ALLOWED_PATTERNS as a comma-separated list of regex patterns.
    |
    | Example (matches any Railway preview URL for a given project):
    |   CORS_ALLOWED_PATTERNS=https://front-.*\.up\.railway\.app
    |
    | Leave empty in production unless you specifically need pattern matching.
    |
    */

    'allowed_origins_patterns' => array_filter(
        explode(',', env('CORS_ALLOWED_PATTERNS', ''))
    ),

    /*
    |--------------------------------------------------------------------------
    | Allowed HTTP Methods
    |--------------------------------------------------------------------------
    |
    | Specifies the HTTP methods that are allowed for cross-origin requests.
    |
    */

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed HTTP Headers
    |--------------------------------------------------------------------------
    |
    | Sets the HTTP headers that are allowed in cross-origin requests.
    |
    */

    'allowed_headers' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Exposed Headers
    |--------------------------------------------------------------------------
    |
    | Headers listed here will be made accessible to the browser's JavaScript
    | after a cross-origin response is received.
    |
    */

    'exposed_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Max Age
    |--------------------------------------------------------------------------
    |
    | The number of seconds the browser may cache a preflight (OPTIONS)
    | response. Setting this to 3600 (1 hour) reduces preflight overhead
    | significantly compared to the default of 0.
    |
    */

    'max_age' => env('CORS_MAX_AGE', 3600),

    /*
    |--------------------------------------------------------------------------
    | Supports Credentials
    |--------------------------------------------------------------------------
    |
    | Indicates whether the request can include user credentials (cookies,
    | HTTP authentication, client-side certificates). When true, you MUST
    | specify exact origins above — a wildcard (*) will not work and will
    | cause browsers to reject the response.
    |
    */

    'supports_credentials' => true,

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | The URI patterns that CORS headers will be applied to. The api/* pattern
    | covers all versioned API routes (e.g. api/v1/...).
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

];
