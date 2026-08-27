<?php

return [
    'headers' => [
        'enabled' => env('SECURITY_HEADERS_ENABLED', true),
        'content_security_policy' => [
            'enabled' => env('SECURITY_CSP_ENABLED', false),
            'value' => env(
                'SECURITY_CSP_VALUE',
                "default-src 'self'; base-uri 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' data: blob:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; form-action 'self'"
            ),
        ],
    ],
];
