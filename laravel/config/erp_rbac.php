<?php

return [
    'guard' => 'web',

    'modules' => [
        'dashboard' => ['view'],
        'accounting' => ['view', 'create', 'edit', 'submit', 'approve', 'post', 'reverse', 'export', 'print'],
        'sales' => ['view', 'create', 'edit', 'delete', 'submit', 'approve', 'post', 'cancel', 'reverse', 'export', 'print'],
        'purchasing' => ['view', 'create', 'edit', 'delete', 'submit', 'approve', 'post', 'cancel', 'reverse', 'export', 'print'],
        'inventory' => ['view', 'create', 'edit', 'delete', 'approve', 'post', 'export', 'print'],
        'equipment' => ['view', 'create', 'edit', 'delete', 'export'],
        'rentals' => ['view', 'create', 'edit', 'submit', 'approve', 'post', 'cancel', 'export', 'print'],
        'customers' => ['view', 'create', 'edit', 'delete', 'export'],
        'suppliers' => ['view', 'create', 'edit', 'delete', 'export'],
        'cash' => ['view', 'create', 'edit', 'post', 'reverse', 'export', 'print'],
        'banks' => ['view', 'create', 'edit', 'post', 'reverse', 'export', 'print'],
        'cheques' => ['view', 'create', 'edit', 'post', 'reverse', 'export'],
        'expenses' => ['view', 'create', 'edit', 'submit', 'approve', 'post', 'export', 'print'],
        'fixedAssets' => ['view', 'create', 'edit', 'post', 'reverse', 'export'],
        'payroll' => ['view', 'create', 'edit', 'submit', 'approve', 'post', 'export', 'print'],
        'taxes' => ['view', 'edit', 'export', 'print'],
        'partners' => ['view', 'create', 'edit', 'post', 'export'],
        'projects' => ['view', 'create', 'edit', 'export'],
        'costCenters' => ['view', 'create', 'edit', 'export'],
        'budgeting' => ['view', 'create', 'edit', 'approve', 'export'],
        'recurring' => ['view', 'create', 'edit', 'export'],
        'reports' => ['view', 'export', 'print'],
        'audit' => ['view', 'export'],
        'settings' => ['view', 'configure'],
        'users' => ['view', 'create', 'edit', 'delete', 'configure'],
    ],

    'sensitive_capabilities' => [
        'view_financials',
        'view_payroll',
        'override_credit_limit',
        'override_negative_stock',
        'close_period',
        'reopen_period',
    ],

    'role_templates' => [
        'SUPER_ADMIN' => [
            'all' => true,
        ],
        'COMPANY_ADMIN' => [
            'all' => true,
            'except' => ['reopen_period'],
        ],
        'ACCOUNTANT' => [
            'view_all' => true,
            'modules_all' => ['accounting', 'cash', 'banks', 'cheques', 'taxes', 'partners', 'fixedAssets'],
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
            'modules_all' => ['payroll'],
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
