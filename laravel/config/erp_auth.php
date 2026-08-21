<?php

return [
    'bootstrap_user' => [
        'enabled' => filter_var(env('ERP_SEED_BOOTSTRAP_USER', env('APP_ENV') !== 'production'), FILTER_VALIDATE_BOOLEAN),
        'name' => env('ERP_BOOTSTRAP_USER_NAME', 'Mini ERP Admin'),
        'email' => env('ERP_BOOTSTRAP_USER_EMAIL', 'admin@mini-erp.local'),
        'password' => env('ERP_BOOTSTRAP_USER_PASSWORD', 'Password123!'),
        'assign_role' => filter_var(env('ERP_BOOTSTRAP_USER_ASSIGN_ROLE', true), FILTER_VALIDATE_BOOLEAN),
        'role' => env('ERP_BOOTSTRAP_USER_ROLE', 'ERP_ADMIN'),
    ],
];
