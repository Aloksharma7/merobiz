<?php

$frontendOrigins = array_values(array_filter(array_map(
    // A trailing slash here would make Access-Control-Allow-Origin fail to
    // byte-match the browser's Origin header (which never has one), silently
    // breaking every request — strip it regardless of how FRONTEND_URL is set.
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('FRONTEND_URL', 'http://localhost:3000'))
)));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $frontendOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['Content-Disposition'],
    'max_age' => 0,
    'supports_credentials' => true,
];
