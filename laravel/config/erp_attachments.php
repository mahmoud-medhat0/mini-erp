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
        'customer' => [
            'table' => 'customer',
            'key' => 'id',
            'permissions' => [
                'view' => ['customers.view'],
                'attach' => ['customers.edit', 'customers.create'],
                'delete' => ['customers.edit', 'customers.delete'],
            ],
        ],
        'supplier' => [
            'table' => 'supplier',
            'key' => 'id',
            'permissions' => [
                'view' => ['suppliers.view'],
                'attach' => ['suppliers.edit', 'suppliers.create'],
                'delete' => ['suppliers.edit', 'suppliers.delete'],
            ],
        ],
        'cash_account' => [
            'table' => 'cash_account',
            'key' => 'id',
            'permissions' => [
                'view' => ['cash.view'],
                'attach' => ['cash.edit', 'cash.create'],
                'delete' => ['cash.edit'],
            ],
        ],
        'bank_account' => [
            'table' => 'bank_account',
            'key' => 'id',
            'permissions' => [
                'view' => ['banks.view'],
                'attach' => ['banks.edit', 'banks.create'],
                'delete' => ['banks.edit'],
            ],
        ],
        'customer_opening_balance' => [
            'table' => 'customer_opening_balance',
            'key' => 'id',
            'permissions' => [
                'view' => ['customers.opening_balances'],
                'attach' => ['customers.opening_balances'],
                'delete' => ['customers.opening_balances'],
            ],
        ],
        'supplier_opening_balance' => [
            'table' => 'supplier_opening_balance',
            'key' => 'id',
            'permissions' => [
                'view' => ['suppliers.opening_balances'],
                'attach' => ['suppliers.opening_balances'],
                'delete' => ['suppliers.opening_balances'],
            ],
        ],
        'customer_receipt' => [
            'table' => 'customer_receipt',
            'key' => 'id',
            'permissions' => [
                'view' => ['customers.receipts'],
                'attach' => ['customers.receipts'],
                'delete' => ['customers.receipts'],
            ],
        ],
        'supplier_payment' => [
            'table' => 'supplier_payment',
            'key' => 'id',
            'permissions' => [
                'view' => ['suppliers.payments'],
                'attach' => ['suppliers.payments'],
                'delete' => ['suppliers.payments'],
            ],
        ],
        'incoming_cheque' => [
            'table' => 'incoming_cheque',
            'key' => 'id',
            'permissions' => [
                'view' => ['cheques.view'],
                'attach' => ['cheques.edit', 'cheques.create'],
                'delete' => ['cheques.delete', 'cheques.edit'],
            ],
        ],
        'outgoing_cheque' => [
            'table' => 'outgoing_cheque',
            'key' => 'id',
            'permissions' => [
                'view' => ['cheques.view'],
                'attach' => ['cheques.edit', 'cheques.create'],
                'delete' => ['cheques.delete', 'cheques.edit'],
            ],
        ],
        'bank_reconciliation' => [
            'table' => 'bank_reconciliation',
            'key' => 'id',
            'permissions' => [
                'view' => ['banks.view'],
                'attach' => ['banks.reconcile'],
                'delete' => ['banks.reconcile'],
            ],
        ],
        'product' => [
            'table' => 'product',
            'key' => 'id',
            'permissions' => [
                'view' => ['products.view'],
                'attach' => ['products.edit', 'products.create'],
                'delete' => ['products.delete', 'products.edit'],
            ],
        ],
        'sales_order' => [
            'table' => 'sales_order',
            'key' => 'id',
            'permissions' => [
                'view' => ['sales.view'],
                'attach' => ['sales.edit', 'sales.create'],
                'delete' => ['sales.delete', 'sales.edit'],
            ],
        ],
    ],
];
