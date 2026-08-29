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

    'password_policy' => [
        'min_length' => (int) env('ERP_PASSWORD_MIN_LENGTH', 12),
        'max_length' => (int) env('ERP_PASSWORD_MAX_LENGTH', 128),
        'mixed_case' => filter_var(env('ERP_PASSWORD_REQUIRE_MIXED_CASE', true), FILTER_VALIDATE_BOOLEAN),
        'letters' => filter_var(env('ERP_PASSWORD_REQUIRE_LETTERS', true), FILTER_VALIDATE_BOOLEAN),
        'numbers' => filter_var(env('ERP_PASSWORD_REQUIRE_NUMBERS', true), FILTER_VALIDATE_BOOLEAN),
        'symbols' => filter_var(env('ERP_PASSWORD_REQUIRE_SYMBOLS', true), FILTER_VALIDATE_BOOLEAN),
    ],
];
