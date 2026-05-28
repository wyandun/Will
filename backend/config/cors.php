<?php

$rawOrigins = env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost');

// Filter out empty strings that arise from trailing commas or whitespace in the env value.
$allowedOrigins = array_values(array_filter(
    array_map('trim', explode(',', $rawOrigins))
));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
