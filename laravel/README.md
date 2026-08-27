# Mini ERP Laravel App

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


This is the active Laravel + Inertia + React migration app for the Mini ERP.

## Stack

- Laravel 13.x
- PHP 8.3+
- PostgreSQL
- Inertia.js + React + TypeScript
- Tailwind 4
- Spatie Permission with teams disabled
- Spatie Translatable
- Spatie Activitylog

## Local Development

```powershell
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev
composer run serve:no-xdebug
```

Then open:

```text
http://127.0.0.1:8000
```

On the current Windows/WAMP PHP setup, Xdebug can make the PHP development server exit after the first request. Use:

```powershell
composer run serve:no-xdebug
```

Do not run `php artisan serve` directly on this machine unless Xdebug is disabled first.

## Development Login

```text
Email: admin@mini-erp.local
Password: Password123!
Role: SUPER_ADMIN
```

The bootstrap user is a local migration/development entrypoint. It is not tied to a company, branch, tenant, or current-company context.

Useful env controls:

- `ERP_SEED_BOOTSTRAP_USER`
- `ERP_BOOTSTRAP_USER_EMAIL`
- `ERP_BOOTSTRAP_USER_PASSWORD`
- `ERP_BOOTSTRAP_USER_ASSIGN_ROLE`
- `ERP_BOOTSTRAP_USER_ROLE`

## Implemented Scope

- Session auth with throttling and active-user checks.
- Global RBAC through Spatie Permission.
- Settings pages and actions for company profile, standalone branches, numbering, users, roles.
- Phase 2 Accounting Core: account categories/types, chart of accounts, FX rates, periods, journals, posting, ledger, reversal, opening balances, General Journal, General Ledger, Trial Balance.
- Attachments service and UI panel with entity authorization.
- Notifications service and notification center.
- Spatie Activitylog-backed audit service and `/audit-log` viewer.
- Scheduler entry for `tokens:gc --batch=100`.
- Queue/jobs baseline tables and tests.
- Phase 3 Slices 1-6: Customer/Supplier, CashAccount/BankAccount, AR/AP subledgers and opening balances, receipt/payment posting, allocation settlement, cheque lifecycle, and bank reconciliation foundation.
- Phase 10 branch/warehouse operations: warehouses, stock transfers, stock counts, stock adjustments, warehouse-aware documents, branch cash/bank transfers, fixed asset branch/location movement, branch operational/profitability reports, branch-specific GL mapping overrides, and optional branch-aware approval rules without tenant scope.
- Phase 11 expense management: expense categories, supplier payable/cash/bank settlement, required attachments, tax-period blocking, and PostingEngine-backed posting.
- Phase 12 prepaid and accrued expenses: prepaid schedules, accrual schedules, exact monthly allocation, PostingEngine recognition/accrual entries, and period close blockers.
- Phase 13 payroll foundation: employees, payroll components, recurring component assignments, payroll periods/runs, PostingEngine payroll posting, period close blockers, payroll attachments, sensitive `view_payroll` authorization, and payroll pages.
- Phase 14 rentals foundation: rentable item register, optional product/fixed-asset source links, operational branch/warehouse placement, status history, attachment registry support, `/rentals/items`, rental contract lifecycle, customer linkage, item reservation/allocation/rented transitions, `/rentals/contracts`, rental handovers, returns, inspections, `/rentals/handovers`, `/rentals/returns`, rental invoices, deposits, damage/late/other charges, VAT, AR, PostingEngine GL integration under `/rentals/invoices`, and rental operations reporting under `/reports/rentals`.
- Latest Phase 15 update: Slices 1-133 are complete; Slice 133 replaced native rental filter selects with shared `SearchableSelect` controls in Handovers, Returns, and Invoices.
- Phase 15 product hardening Slices 1-133: `/reports` now requires `reports.view` + `view_financials`, selected report controllers enforce financial visibility, critical accounting/operational pages use dictionary-backed visible text or explicit currency behavior, backend validation/guard errors are progressively localized through Laravel translations, including catalog/master-data, sales/purchasing order, fulfillment, invoice, bill, return, credit-note, purchase-return, supplier-adjustment, accounting mapping, bank reconciliation, cash/bank book, cheque, landed cost allocation, AR/AP allocation, AR/AP settlement, AR/AP receipt/payment/opening-balance, invoice revision, shared currency-input services, and report export errors, statement/AR/AP/cheque/financial-statement/branch CSV composition is delegated to focused exporters, all report exporter stream lifecycle handling is centralized in `CsvReportResponse`, Income Statement/Cash Flow/Trial Balance period-selector data is centralized, report selector lists are centralized in `ReportPageOptions`, remaining settings/audit query and persistence work is delegated to services, selected operational reports use a shared filter panel with visible currency filters and reset actions, rental filters use shared searchable controls, expense/payroll and remaining operational filter reset flows use named guarded reset actions, Bank Reconciliation Detail/Dashboard/AppLayout missing or switcher labels are dictionary-backed, bank reconciliation finalization confirmation copy is explicit and localized, Arabic catalog product/category/UOM labels and placeholders are cleanly localized, dashboard/customer/supplier/cash/bank/AR-AP opening-balance/AR-AP receipt-payment/cheque/allocation/entry-settlement, Sales/Purchase Order, Delivery/Goods Receipt, Invoice Revision, Accounting Account Mapping, Accounting Overview, Account Category/Type, Journal/Opening Balance, remaining Accounting master-data, Catalog, Expense/Prepaid/Accrual, Fixed Assets, Payroll, Rentals, Inventory/Warehouse, Landed Cost, Treasury Transfer, Tax index/detail page data, report selector options, and all-controller direct query cleanup are delegated to application services, and all controllers are currently under 150 lines.

## Verification

```powershell
composer install
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan qa:verify-local --timeout=300
php artisan qa:verify-local --only-feature-files --filter=Phase10 --stop-on-failure --timeout=300
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan accounting:stock-transfer-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Latest verified result:

- Phase 14 migrations are Ran: `2026_08_25_100000_create_phase14_rental_item_tables`, `2026_08_25_101000_create_phase14_rental_contract_tables`, `2026_08_25_102000_create_phase14_rental_handover_return_tables`, and `2026_08_25_103000_create_phase14_rental_invoice_tables`.
- Phase 13 migrations are Ran: `2026_08_25_090000_create_phase13_payroll_tables` and `2026_08_25_091000_extend_accounting_mapping_keys_for_phase13_payroll`.
- Phase 14 rentals feature suite passed: 27 tests, 256 assertions.
- Phase 15 product hardening test passed after Slice 133: 138 tests, 20734 assertions.
- Bank/cheque localization guard passed after Slice 84: 1 test, 358 assertions.
- Phase 3 cheque and bank reconciliation feature suites passed after Slice 84: 19 tests, 97 assertions.
- Concurrency suite and PostgreSQL cheque/bank-reconciliation stress commands passed after Slice 84.
- Financial service, AR/AP cash-bank, and treasury-transfer validation localization guard passed: 1 test, 433 assertions.
- Branch approval rule localization guard passed: 1 test, 22 assertions. Tax service localization guard passed: 1 test, 132 assertions. Expense service localization guard passed: 1 test, 301 assertions. Payroll service localization guard passed: 1 test, 253 assertions. Inventory workflow localization guard passed: 1 test, 297 assertions.
- State-changing route security guard passed: 1 test, 550 assertions. AR/AP posting confirmation guard passed: 1 test, 80 assertions. Invoice source-document typing guard passed: 1 test, 15 assertions.
- Phase 14 rental reports close-out tests passed: 3 tests, 41 assertions.
- Phase 14 rentals billing feature tests passed: 8 tests, 56 assertions.
- Phase 14 rentals foundation feature tests passed: 16 tests, 159 assertions.
- Phase 13 payroll feature tests passed: 6 tests, 90 assertions.
- Last full Laravel suite pass remains Slice 57: 720 tests, 717 passed, 3 skipped, 17,923 assertions.
- Slice 94 targeted stream-boundary guard passed: 1 test, 148 assertions. VAT report suite passed: 9 tests, 44 assertions. Rental report suite passed: 3 tests, 41 assertions.
- Slice 95 targeted period-options guard passed: 1 test, 16 assertions. Phase5 financial statement and cash-flow suites passed.
- Slice 96 targeted bank reconciliation missing-reference guard passed: 1 test, 20 assertions. Phase3 reports suite passed.
- Slice 97 targeted dashboard missing-user fallback guard passed: 1 test, 16 assertions. MigratedPagesTest passed.
- Slice 98 targeted AppLayout dictionary guard passed: 1 test, 99 assertions.
- Slice 99 targeted bank reconciliation finalization dictionary guard passed: 1 test, 4 assertions.
- Slice 100 targeted catalog product Arabic select-label guard passed: 1 test, 15 assertions.
- Slice 101 targeted catalog category/UOM form dictionary guard passed: 1 test, 15 assertions.
- Slice 102 targeted dashboard controller-boundary guard passed: 1 test, 11 assertions.
- Slice 103 targeted customer/supplier controller-boundary guard passed: 1 test, 14 assertions.
- Slice 113 targeted invoice revision controller-boundary guard passed: 1 test, 9 assertions.
- Slice 58 targeted regression, Pint, TypeScript typecheck, and Vite production build passed; after Slice 94, Pint, TypeScript typecheck, and Vite production build passed again. The broader full-suite command exceeded the local timeout budget after Slice 58, while `Phase4Slice10ReturnsCreditNotesTest.php` passed standalone with a larger timeout.
- Security hardening tests passed: 6 tests, 413 assertions.
- Phase 3 integrity tests passed: 6 tests, 607 assertions.
- Concurrency suite passed: 7 tests, 16 assertions.
- PostgreSQL concurrency, accounting, allocation, cheque, bank reconciliation, stock-transfer, inventory, and integrity stress commands passed.
- Pint, TypeScript typecheck, and Vite build passed.

## Audit Backend

Spatie Activitylog is the active audit backend.

- New audit writes go to `activity_log`.
- Legacy `audit_log` is retained as a read-only archive.
- Both tables are protected by append-only DB triggers.
- The app-level `AuditLogger::record(...)` API is preserved.

## Health

```text
http://127.0.0.1:8000/health
```


