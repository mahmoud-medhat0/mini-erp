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
        'purchase_order' => [
            'table' => 'purchase_order',
            'key' => 'id',
            'permissions' => [
                'view' => ['purchasing.view'],
                'attach' => ['purchasing.edit', 'purchasing.create'],
                'delete' => ['purchasing.delete', 'purchasing.edit'],
            ],
        ],
        'delivery_note' => [
            'table' => 'delivery_note',
            'key' => 'id',
            'permissions' => [
                'view' => ['sales.view'],
                'attach' => ['sales.edit', 'sales.create'],
                'delete' => ['sales.delete', 'sales.edit'],
            ],
        ],
        'goods_receipt' => [
            'table' => 'goods_receipt',
            'key' => 'id',
            'permissions' => [
                'view' => ['purchasing.view'],
                'attach' => ['purchasing.edit', 'purchasing.create'],
                'delete' => ['purchasing.delete', 'purchasing.edit'],
            ],
        ],
        'customer_invoice' => [
            'table' => 'customer_invoice',
            'key' => 'id',
            'permissions' => [
                'view' => ['sales.view'],
                'attach' => ['sales.edit', 'sales.create'],
                'delete' => ['sales.delete', 'sales.edit'],
            ],
        ],
        'supplier_bill' => [
            'table' => 'supplier_bill',
            'key' => 'id',
            'permissions' => [
                'view' => ['purchasing.view'],
                'attach' => ['purchasing.edit', 'purchasing.create'],
                'delete' => ['purchasing.delete', 'purchasing.edit'],
            ],
        ],
        'sales_return' => [
            'table' => 'sales_return',
            'key' => 'id',
            'permissions' => [
                'view' => ['sales.view'],
                'attach' => ['sales.returns'],
                'delete' => ['sales.returns'],
            ],
        ],
        'customer_credit_note' => [
            'table' => 'customer_credit_note',
            'key' => 'id',
            'permissions' => [
                'view' => ['sales.view'],
                'attach' => ['sales.credit_notes'],
                'delete' => ['sales.credit_notes'],
            ],
        ],
        'customer_invoice_revision' => [
            'table' => 'customer_invoice_revision',
            'key' => 'id',
            'permissions' => [
                'view' => ['sales.view'],
                'attach' => ['sales.invoice_revisions'],
                'delete' => ['sales.invoice_revisions'],
            ],
        ],
        'purchase_return' => [
            'table' => 'purchase_return',
            'key' => 'id',
            'permissions' => [
                'view' => ['purchasing.view'],
                'attach' => ['purchasing.returns'],
                'delete' => ['purchasing.returns'],
            ],
        ],
        'supplier_adjustment_note' => [
            'table' => 'supplier_adjustment_note',
            'key' => 'id',
            'permissions' => [
                'view' => ['purchasing.view'],
                'attach' => ['purchasing.adjustment_notes'],
                'delete' => ['purchasing.adjustment_notes'],
            ],
        ],
        'fixed_asset' => [
            'table' => 'fixed_asset',
            'key' => 'id',
            'permissions' => [
                'view' => ['fixedAssets.view'],
                'attach' => ['fixedAssets.edit', 'fixedAssets.create'],
                'delete' => ['fixedAssets.delete', 'fixedAssets.edit'],
            ],
        ],
    ],
];
