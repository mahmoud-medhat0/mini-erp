<?php

return [
    'disk' => 'local',
    'max_size_kb' => 10240, // 10 MB
    'allowed_extensions' => ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'txt', 'csv', 'xlsx', 'docx'],
    'allowed_mimes' => [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/webp',
        'text/plain',
        'text/csv',
        'application/csv',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],

    'entities' => [
        'company' => [
            'table' => 'company',
            'key' => 'id',
            'permissions' => [
                'view' => ['settings.company', 'settings.configure'],
                'attach' => ['settings.company', 'settings.configure'],
                'delete' => ['settings.company', 'settings.configure'],
            ],
        ],
        'branch' => [
            'table' => 'branch',
            'key' => 'id',
            'permissions' => [
                'view' => ['settings.branches', 'settings.configure'],
                'attach' => ['settings.branches', 'settings.configure'],
                'delete' => ['settings.branches', 'settings.configure'],
            ],
        ],
        'journal_entry' => [
            'table' => 'journal_entry',
            'key' => 'id',
            'permissions' => [
                'view' => ['accounting.view'],
                'attach' => ['accounting.create'],
                'delete' => ['accounting.create'],
            ],
        ],
        'opening_balance' => [
            'table' => 'opening_balance',
            'key' => 'id',
            'permissions' => [
                'view' => ['accounting.opening_balances'],
                'attach' => ['accounting.opening_balances'],
                'delete' => ['accounting.opening_balances'],
            ],
        ],
        'user' => [
            'table' => 'users',
            'key' => 'id',
            'permissions' => [
                'view' => ['users.configure'],
                'attach' => ['users.configure'],
                'delete' => ['users.configure'],
            ],
        ],
    ],
];
