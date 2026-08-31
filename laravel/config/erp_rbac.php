<?php

return [
    'guard' => 'web',

    'modules' => [
        'dashboard' => ['view'],
        'accounting' => ['view', 'create', 'edit', 'delete', 'submit', 'approve', 'post', 'reverse', 'override_control', 'export', 'print', 'currencies', 'fx_rates', 'periods', 'opening_balances', 'account_types', 'account_categories', 'mappings'],
        'sales' => ['view', 'create', 'edit', 'delete', 'submit', 'approve', 'post', 'cancel', 'reverse', 'export', 'print', 'returns', 'credit_notes', 'invoice_revisions'],
        'purchasing' => ['view', 'create', 'edit', 'delete', 'submit', 'approve', 'post', 'cancel', 'reverse', 'export', 'print', 'returns', 'adjustment_notes', 'landed_costs'],
        'inventory' => ['view', 'create', 'edit', 'delete', 'approve', 'post', 'transfer', 'receive', 'count', 'adjust', 'export', 'print'],
        'equipment' => ['view', 'create', 'edit', 'delete', 'export'],
        'rentals' => ['view', 'create', 'edit', 'delete', 'submit', 'approve', 'post', 'cancel', 'deliver', 'return', 'inspect', 'invoice', 'configure', 'export', 'print'],
        'customers' => ['view', 'create', 'edit', 'delete', 'export', 'opening_balances', 'receipts', 'allocations'],
        'suppliers' => ['view', 'create', 'edit', 'delete', 'export', 'opening_balances', 'payments', 'allocations'],
        'cash' => ['view', 'create', 'edit', 'delete', 'post', 'reverse', 'export', 'print'],
        'banks' => ['view', 'create', 'edit', 'delete', 'post', 'reverse', 'reconcile', 'export', 'print'],
        'cheques' => ['view', 'create', 'edit', 'delete', 'receive', 'issue', 'deposit', 'clear', 'bounce', 'return', 'cancel', 'post', 'reverse', 'export'],
        'expenses' => ['view', 'create', 'edit', 'delete', 'submit', 'approve', 'post', 'export', 'print'],
        'fixedAssets' => ['view', 'create', 'edit', 'delete', 'post', 'reverse', 'transfer', 'export'],
        'approvals' => ['view', 'configure', 'override'],
        'payroll' => ['view', 'create', 'edit', 'delete', 'submit', 'approve', 'post', 'export', 'print'],
        'taxes' => ['view', 'edit', 'export', 'print'],
        'partners' => ['view', 'create', 'edit', 'delete', 'post', 'export'],
        'projects' => ['view', 'create', 'edit', 'delete', 'export'],
        'costCenters' => ['view', 'create', 'edit', 'delete', 'export'],
        'budgeting' => ['view', 'create', 'edit', 'delete', 'approve', 'export'],
        'recurring' => ['view', 'create', 'edit', 'delete', 'export'],
        'reports' => ['view', 'export', 'print'],
        'audit' => ['view', 'export'],
        'products' => ['view', 'create', 'edit', 'delete', 'export'],
        'uom' => ['view', 'create', 'edit', 'delete'],
        'settings' => ['view', 'configure', 'company', 'branches', 'numbering'],
        'users' => ['view', 'create', 'edit', 'delete', 'configure'],
    ],

    'sensitive_capabilities' => [
        'view_financials',
        'view_payroll',
        'override_credit_limit',
        'override_negative_stock',
        'close_period',
        'reopen_period',
        'manage_currencies',
        'manage_fx_rates',
        'manage_account_types',
        'manage_account_categories',
        'taxes.file',
        'approvals.override',
    ],

    'role_templates' => [
        'SUPER_ADMIN' => [
            'all' => true,
        ],
        'ERP_ADMIN' => [
            'all' => true,
            'except' => ['reopen_period'],
        ],
        'ACCOUNTANT' => [
            'view_all' => true,
            'modules_except' => [
                'accounting' => ['delete'],
                'cash' => ['delete'],
                'banks' => ['delete'],
                'cheques' => ['delete'],
                'partners' => ['delete'],
                'fixedAssets' => ['delete'],
            ],
            'modules_all' => ['taxes'],
            'permissions' => ['expenses.post', 'sales.post', 'purchasing.post', 'reports.export', 'reports.print', 'view_financials', 'close_period'],
        ],
        'SALES' => [
            'permissions' => ['dashboard.view', 'customers.view', 'customers.create', 'customers.edit', 'reports.view', 'reports.export'],
            'modules_except' => [
                'sales' => ['post', 'reverse'],
            ],
        ],
        'PURCHASING' => [
            'permissions' => ['dashboard.view', 'suppliers.view', 'suppliers.create', 'suppliers.edit', 'inventory.view', 'reports.view', 'reports.export'],
            'modules_except' => [
                'purchasing' => ['post', 'reverse'],
            ],
        ],
        'INVENTORY' => [
            'permissions' => ['dashboard.view', 'reports.view', 'reports.export', 'override_negative_stock'],
            'modules_all' => ['inventory', 'equipment'],
        ],
        'HR' => [
            'permissions' => ['dashboard.view', 'reports.view', 'reports.export', 'view_payroll'],
            'modules_except' => [
                'payroll' => ['delete'],
            ],
        ],
        'AUDITOR' => [
            'view_all' => true,
            'permissions' => ['audit.view', 'audit.export', 'reports.view', 'reports.export', 'view_financials'],
        ],
        'VIEWER' => [
            'permissions' => ['dashboard.view', 'reports.view'],
        ],
    ],
];
