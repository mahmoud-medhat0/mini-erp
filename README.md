# Mini ERP - Laravel Migration

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.

> **Branch-Capable Product Direction:** Owner direction on 2026-08-24 requires future support for multiple operational branches, branch transfers, and branch-aware workflows. This is not multi-tenancy. See `PRODUCT_EXTENSIBILITY_ROADMAP.md`.


Current target: Laravel + Inertia.js + React + TypeScript + Tailwind + PostgreSQL.

The repository still contains the older Next.js reference app under `app/`, but the active migration target is `laravel/`.

Latest product-hardening update: Phase 15 Slices 1-133 are complete. Slice 133 replaced native rental filter selects with shared `SearchableSelect` controls in Handovers, Returns, and Invoices. `Phase15ProductHardeningTest` passed with 138 tests / 20,734 assertions.

Latest verified status: Phase 15 Product Hardening Slices 1-133 are complete as of 2026-08-26. Current verified scope covers report/security/permission hardening, controller/service cleanup, dictionary-backed accountant UI text, visible report currency filters, shared operational report filter UX, rental searchable filter controls, expense/payroll and remaining operational filter reset cleanup, explicit currency behavior, backend validation localization through master data, sales/purchasing orders, fulfillment, invoices, bills, returns/adjustments, catalog/master-data, accounting mappings, bank reconciliation, cash/bank books, cheques, landed cost allocation, AR/AP allocation, AR/AP settlement, AR/AP receipt/payment/opening-balance guards, invoice revision, accounting overview page-data cleanup, account category/type page-data cleanup, journal/opening-balance page-data cleanup, remaining accounting master-data page-data cleanup, catalog controller page-data cleanup, expense/prepaid/accrual controller page-data cleanup, fixed-asset location/disposal page-data cleanup, payroll controller page-data cleanup, rentals operational page-data cleanup, inventory/warehouse page-data cleanup, landed-cost/treasury-transfer page-data cleanup, tax controller page-data cleanup, shared report selector options cleanup, settings/audit controller query and persistence cleanup, shared currency input, report export errors, statement CSV exporter cleanup, AR/AP/cheque CSV exporter cleanup, financial-statement/branch CSV exporter cleanup, centralized report CSV stream lifecycle cleanup, shared financial-period report selector options including Trial Balance, localized bank reconciliation missing-reference labels, localized dashboard missing-user label, dictionary-backed app-shell language switcher, bank reconciliation finalization confirmation cleanup, Arabic catalog product select-label cleanup, catalog category/UOM placeholder cleanup, dashboard/customer/supplier/cash/bank/AR-AP opening-balance/AR-AP receipt-payment/cheque/allocation/entry-settlement, Sales/Purchase Order, Delivery/Goods Receipt, Invoice Revision, Accounting Account Mapping, Accounting Overview, Account Category/Type, Journal/Opening Balance, remaining Accounting master-data, Catalog, Expense/Prepaid/Accrual, Fixed Assets, Payroll, Rentals, Inventory/Warehouse, Landed Cost, Treasury Transfer, Tax controller page-data boundary cleanup, report controller selector boundary cleanup, operational report filter UX cleanup, and all-controller direct query cleanup, and preservation of PostingEngine, RBAC, audit, VAT, subledger, stock, and operational branch/warehouse invariants. See `PHASE_15_PRODUCT_HARDENING_REPORT.md`.

## Current Rule

The Mini ERP is not currently a multi-tenant SaaS.

Do not introduce or restore:

- tenant context or tenant middleware
- `company_user`
- `users.company_id`
- `branch.company_id`
- `fiscal_year.company_id`
- Company-owned users, branches, roles, or permissions
- Spatie Teams
- `currentCompany` or `currentBranch`
- company/branch dimensions in document numbering

Allowed future branch capability:

- explicit branch references on operational records only when a bounded future slice approves the schema and behavior
- branch/warehouse selectors and filters where useful for sales, purchasing, inventory, cash/bank, fixed assets, and reports
- stock/cash/asset transfers between operational branches without treating branches as tenants

If a relationship is not explicitly supported by owner requirements or a later owner decision, classify it as:

`UNDEFINED - DO NOT ASSUME`

## Implemented Laravel Scope

- Laravel session authentication with throttling, active-user checks, and bootstrap admin seeding.
- Spatie Permission RBAC with teams disabled.
- Global role templates and module/action permissions.
- Inertia React app shell and settings/dashboard/notification pages.
- Company profile configuration and standalone Branch reference records.
- Global FiscalYear with FinancialPeriod linked by `fiscal_year_id`.
- Atomic document number sequence allocation by global `key`.
- Money value object, currency registry, accounting invariant kernel, and number formatting/config primitives.
- Phase 2 Accounting Core:
  - account categories and account types
  - chart of accounts
  - FX rates
  - fiscal periods
  - manual journal workflow
  - posting engine
  - immutable ledger entries
  - reversal workflow
  - opening balances
  - General Journal, General Ledger, Trial Balance
- M8 settings/user actions for company, branch, numbering, and role assign/revoke.
- M9 attachment and notification services.
- M10 Spatie Activitylog active audit backend, read-only audit viewer, scheduler, and queue/jobs baseline.
- Phase 3 Slice 1 master data foundation:
  - Customer and Supplier models/services.
  - CashAccount and BankAccount models/services linked to GL accounts and currencies.
  - optimistic locking, RBAC permissions, Spatie Activitylog audit, and attachment registry entries.
- Phase 3 Slice 2 AR/AP subledger and opening balance foundation:
  - Customer/Supplier opening balances through the existing PostingEngine.
  - `receivable_entry` and `payable_entry` subledgers.
  - global accounting mappings for AR control, AP control, and opening-balance offset accounts.
  - subledger-to-GL reconciliation and DB integrity hardening.
- Phase 3 Slice 3 receipt/payment posting:
  - Customer Receipt and Supplier Payment draft/post services.
  - global receipt/payment numbering, PostingEngine GL effects, AR/AP subledger effects, and unapplied balances.
  - idempotent posting, linked GL currency validation, and DB integrity hardening.
- Phase 3 Slice 4 allocation engine:
  - Receivable and Payable allocation records.
  - CustomerReceipt-to-ReceivableEntry and SupplierPayment-to-PayableEntry settlement.
  - allocation reversal, unapplied balance updates, idempotency, deterministic locking, and over-allocation prevention.
- Phase 3 Slice 5 cheque lifecycle:
  - IncomingCheque and OutgoingCheque records/services.
  - pre-clear receive/deposit/clear/bounce/return and issue/clear/return/cancel workflows.
  - configurable Cheques Under Collection and Cheques Payable mappings.
  - PostingEngine GL effects, AR/AP subledger effects, idempotency, Spatie Activitylog audit, attachment registry entries, and cheque concurrency stress coverage.
- Phase 3 Slice 6 bank reconciliation:
  - BankReconciliation and BankReconciliationLine records/services.
  - manual ledger-backed statement matching, CashBook and BankBook query services, zero-difference finalization, immutable finalized records, and bank reconciliation stress coverage.
- Phase 3 Slice 7 Inertia pages and UX actions:
  - customer/supplier, cash/bank, opening balance, receipt/payment, allocation, cheque, and bank reconciliation pages/actions.
  - expandable navigation groups, full English/Arabic translations, RTL-aware DatePicker, validation feedback, permission-aware actions, and UI feature tests.
- Phase 3 Slice 8 operational/subledger reports:
  - Reports Hub, customer/supplier statements, AR/AP aging, Cash Book, Bank Book, Cheque Register, Bank Reconciliation status/detail, and AR/AP to GL reconciliation.
  - `reports.view` permission, CSV exports, read-only report services, and Inertia report pages.
- Phase 3 Slice 9 PostgreSQL stress and integrity hardening:
  - `accounting:phase3-integrity-check` non-mutating audit command.
  - `accounting:phase3-stress` orchestrator command.
  - Phase 3 stress/integrity feature coverage, period-close checks, report read-only checks, and subledger-to-GL consistency verification.
- Phase 3 Slice 10 documentation close-out and final verification gate:
  - `PHASE_3_FINAL_VERIFICATION_REPORT.md` final close-out report.
  - repository-wide documentation audit and status alignment.
  - 100% passing verification gate (242 tests, 0 TS errors, clean Pint, Vite build).
- Phase 4 Slice 1 Product/Service Catalog Foundation:
  - Unit of Measure, Product Category, and Product catalog.
  - catalog services/controllers/Inertia pages.
  - product attachment registry, RBAC, Spatie Activitylog audit, optimistic locking, and EN/AR translatable fields.
  - reported verification: 254 passing tests, 0 TS errors, clean Pint, and successful Vite build.
- Phase 4 Slice 2 Sales Order Backend & UX:
  - Sales Order header/lines, lifecycle, `SO-YYYY-XXXXX` numbering, attachment registry, audit, and Inertia UX.
  - exact integer line-total math with overflow checks and fractional-minor rejection.
- Phase 4 Slice 3 Purchase Order Backend & UX:
  - Purchase Order header/lines, lifecycle, `PO-YYYY-XXXXX` numbering, attachment registry, audit, and Inertia UX.
  - exact integer line-total math with overflow checks and fractional-minor rejection.
- Phase 4 Slice 4 Delivery Notes & Goods Receipts:
  - Delivery Note and Goods Receipt header/line tables, lifecycle, `DN-YYYY-XXXXX` and `GRN-YYYY-XXXXX` numbering, attachment registry, audit, and Inertia UX.
  - exact integer fulfillment quantities with cumulative over-delivery and over-receipt prevention.
- Phase 4 Slice 5 Customer Invoice Posting:
  - Customer Invoice header/lines, lifecycle, `INV-YYYY-XXXXX` numbering, `sales_revenue` mapping, attachment registry, audit, and Inertia UX.
  - strict Sales Order/Delivery Note source matching, exact integer invoice totals, PostingEngine integration, and AR `receivable_entry` debit.
- Phase 4 Slice 6 Supplier Bill Posting:
  - Supplier Bill header/lines, lifecycle, `BILL-YYYY-XXXXX` numbering, `purchase_expense` mapping, attachment registry, audit, and Inertia UX.
  - strict Purchase Order/Goods Receipt source matching, exact integer bill totals, PostingEngine integration, and AP `payable_entry` credit.
- Phase 10 operational branch/warehouse inventory:
  - warehouse master data, stock locations, warehouse-aware stock balances, immutable stock movements, and stock transfers.
  - stock count and stock adjustment workflows with approval/posting.
  - warehouse selectors on Delivery Notes, Goods Receipts, Sales Returns, and Purchase Returns.
  - warehouse filters on stock balances, stock movements, Delivery Note reports, and Goods Receipt reports.
- Security hardening:
  - baseline security headers, active-user protected-route recheck, explicit route authorization, sensitive `taxes.file` capability, private local storage direct-serve disabled by default, and regression tests.
- Phase 10 landed cost/freight allocation:
  - confirmed Goods Receipt landed cost allocations by receipt value, quantity, or manual split.
  - capitalization into remaining stock valuation, COGS expensing for already-issued stock, input VAT recognition, AP payable creation, PostingEngine journal posting, and immutable zero-quantity stock value movements.
- Phase 11 expense management:
  - expense categories, supplier payable/cash/bank expense documents, attachment requirements, tax-period blocking, and PostingEngine-backed expense posting.
- Phase 12 prepaid and accrued expenses:
  - prepaid schedules, accrual schedules, monthly recognition/accrual entries, exact allocation math, PostingEngine posting, and period close blockers.
- Phase 13 payroll foundation:
  - standalone payroll employees, payroll components, recurring component assignments, payroll periods, payroll runs, PostingEngine payroll posting, period close blockers, payroll attachments, sensitive `view_payroll` permission checks, and payroll Inertia pages.
- Phase 14 rentals foundation:
  - standalone rentable item register, optional product/fixed-asset source links, operational branch/warehouse placement, status history, attachment registry support, and `/rentals/items`.
  - rental contracts, customer linkage, optional operational branch reference, item reservation/allocation/rented transitions, attachment registry support, and `/rentals/contracts`.
  - rental handovers, returns, inspections, item outcome transitions, contract completion after all items close, attachment registry support, and `/rentals/handovers` plus `/rentals/returns`.
  - rental invoices, deposits, damage/late/other charges, output VAT, AR subledger entries, PostingEngine GL posting, attachment support, and `/rentals/invoices`.
  - rental operations report, readiness checks, CSV export, print UX, and `/reports/rentals`.
- Phase 15 product hardening Slices 1-133:
  - sensitive report access requires `reports.view` + `view_financials`, selected report controllers include explicit financial visibility checks, sales/purchasing/invoice/bill report pages use dictionary-backed visible text, simple row-based report CSV streaming is centralized in `CsvReportResponse`, and last-active-super-admin checks are centralized in `SuperAdminProtection`.
  - financial posting routes and matching visible post actions require `view_financials` alongside module posting permissions.
  - selected operational reports have a shared filter panel, visible currency filters, active-filter counts, and reset actions.
  - large controller page-data, CSV composition, General Ledger page-data, report selector options, catalog page-data, expense/prepaid/accrual page-data, fixed-asset location/disposal page-data, payroll page-data, rental operational page-data, settings/audit query and persistence work, and settings persistence/listing were extracted into focused application services.
  - General Ledger, VAT Register, VAT Summary, and AR/AP settlement pages have dictionary-backed operational text.
  - Sensitive payroll, expense, prepaid/accrual, and rental operational state-changing actions require confirmation.
  - Corrupted Arabic rental-contract dictionary text was repaired.
  - User/role permission administration labels and sensitive permission action labels are dictionary-backed.
  - Company, branch, and numbering settings placeholders, options, and confirmations are dictionary-backed.
  - Sales, purchasing, and inventory workflow pages no longer use silent React-side USD/EGP/PCS/MAIN fallbacks.
  - Payroll, expense, rental, and fixed-asset pages no longer use silent React-side `EGP` fallbacks.
  - AR/AP Aging and AR/AP GL Reconciliation pages no longer use hidden React-side `EGP` currency-selector fallbacks.
  - Operational report summaries show a localized mixed-currency label instead of labeling unfiltered totals as EGP.
  - VAT Register/Summary and Tax Period filing pages use typed canonical dictionary paths instead of loose `as any` access.
  - Tax Codes/Rates pages use typed tax dictionary paths, localized unavailable text, and explicit select union casts.
  - Tax Codes/Rates and Tax Period pages no longer use visible fallback labels.
  - Selected master-data delete confirmations name the exact entity being deleted.
  - Accounting account category/type forms and detail modals are dictionary-backed.
  - FX rates, currency master-data, Chart of Accounts, Trial Balance, and General Journal pages have no hidden/default currency or visible fallback assumptions in the cleaned paths.
  - Operational Laravel services now require explicit currency through `CurrencyInput`, FX-rate lookup uses configured base currency, and missing foreign exchange rates no longer fall back silently to `1.000000`.
  - Journal Detail workflow labels, reverse-entry copy, status labels, and audit/detail table headings are dictionary-backed.
  - Journal Form requires an explicit registry currency and uses dictionary-backed creation guidance, placeholders, warnings, and control-account badges.
  - Opening Balances page title, post action, fiscal-year selector, totals, status badge, empty state, table headers, and save action are dictionary-backed.
  - Fiscal Periods navigation is aligned with route permissions, and fiscal-year creation is shown only for users with `settings.configure`.
  - Accounting and Tax sidebar labels use canonical dictionary keys without hardcoded fallbacks.
  - Financial Statement Mapping delete confirmation includes the statement line code and localized name; Account Mapping branch override deletion includes mapping key, branch, and GL account; Accounting landing page labels/statuses and Reports Hub tax report labels are dictionary-backed; VAT-to-GL reconciliation has no visible fallback labels or hidden USD default; AR/AP Aging and AR/AP GL Reconciliation have no hidden EGP currency-selector defaults; operational summaries do not present mixed-currency totals as EGP.
  - Audit Log labels, request-id placeholder, actor fallback, unavailable markers, payload modal, and pagination now use canonical `app.audit` / `app.actions` dictionary keys without legacy fallback chains.
  - AppLayout navigation/header labels now use typed accounting/tax/nav/header dictionary paths without hardcoded visible fallback identity text.
  - Landed cost allocation service validation errors are backed by Laravel translations for lifecycle, posting, allocation, VAT, GL mapping, supplier, Goods Receipt, manual split, and exact-cost guards.
  - Receivable/payable allocation service validation errors are backed by Laravel translations while preserving allocation balance, idempotency, locking, audit, and no-GL-entry invariants.
  - Receivable/payable entry settlement service validation errors are backed by Laravel translations while preserving credit/debit settlement math, idempotency, locking, audit, and no-extra-GL behavior.
  - Customer receipt, supplier payment, customer opening balance, and supplier opening balance cancellation/period guards are translation-backed.
  - Customer invoice revision and shared currency-input validation errors are translation-backed.
  - Report export missing-ID aborts and CSV stream-opening errors are translation-backed.
  - Cash/Bank Book and Customer/Supplier Statement CSV composition is delegated to focused exporters.
  - AR/AP aging, AR/AP-to-GL reconciliation, and Cheque Register CSV composition is delegated to focused exporters.
  - Balance Sheet, Income Statement, Cash Flow, and Branch Profitability CSV composition is delegated to focused exporters.
  - Remaining VAT and rental report exporter stream lifecycle handling is centralized in `CsvReportResponse::stream()`.
  - Income Statement and Cash Flow period-selector data is centralized in `FinancialPeriodReportOptions`.
  - Bank Reconciliation Detail missing GL journal references use EN/AR dictionary-backed labels instead of raw fallback text.
  - Dashboard missing-user fallback uses the shared EN/AR `unknownUser` label.
  - AppLayout language switcher uses common EN/AR dictionary labels instead of raw literals.
  - all controllers under `app/Http/Controllers` are currently under 150 lines.
- Idempotency store, bounded `tokens:gc`, and PostgreSQL stress commands.

## Remaining Major Decisions / Future Scope

- Deployment process is parked until the owner/operator is ready to choose staging/production hosting, PostgreSQL hosting/backups, queue worker process manager, and scheduler trigger.
- Phase 10 warehouse/stock-transfer foundation, stock count, stock adjustment, warehouse document selectors, branch cash/bank transfer, fixed asset movement, branch reporting, branch profitability, optional branch-specific GL mapping overrides, branch-aware approval rules, and landed cost/freight allocation are implemented. Continue from `PRODUCT_EXTENSIBILITY_ROADMAP.md` for future product slices.
- Decide whether to add formal browser automation/CI later. No GitHub Actions pipeline is currently connected.
- Year-end physical retained-earnings close remains an owner decision. Current path keeps soft close/reporting behavior.
- Phase 14 Rentals foundation is implemented and verified. Future rental quotations, contract amendments/extensions, automated recurring rental invoice generation, deposit refunds, item profitability, and maintenance scheduling require bounded future slices.
- Phase 15 Product Hardening is active. Next hardening should continue accountant-focused UX consistency and broader dictionary-backed UI regression coverage.
- Projects, Budgeting, Recurring workflows, external filing/collection integrations, and e-invoicing APIs are not part of the implemented scope yet.
- Payroll future extensions such as payslips, salary payment execution, payroll reports, employee loans/advances, attendance inputs, and statutory payroll tax/social insurance rules require bounded future slices.
- Multi-company and tenant-like relationships remain `UNDEFINED - DO NOT ASSUME`.
- Branch operations are now approved as future product capability, but branch as tenant/security boundary/owned-by-company remains explicitly not approved.

## Setup

```powershell
cd laravel
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run dev
composer run serve:no-xdebug
```

Open:

```text
http://127.0.0.1:8000
```

Default development login:

```text
Email: admin@mini-erp.local
Password: Password123!
Role: SUPER_ADMIN
```

## Verification

Run from `laravel/`:

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
php artisan accounting:phase3-integrity-check
php artisan accounting:phase3-stress --workers=50
php artisan accounting:sales-tax-stress --workers=50
php artisan accounting:purchasing-tax-stress --workers=50
php artisan accounting:tax-filing-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Latest verified result:

- Last full PHPUnit suite pass remains Slice 57: 720 tests, 717 passed, 3 skipped, 17,923 assertions.
- Phase 15 product hardening test after Slice 84: 90 tests / 17,583 assertions passed.
- Bank/cheque localization guard after Slice 84: 1 test / 358 assertions passed.
- Phase 3 cheque and bank reconciliation feature suites after Slice 84: 19 tests / 97 assertions passed.
- Concurrency suite and PostgreSQL cheque/bank-reconciliation stress commands passed after Slice 84.
- Financial service, AR/AP cash-bank, and treasury-transfer validation localization guard: 1 test / 433 assertions passed.
- Branch approval rule localization guard: 1 test / 22 assertions passed. Tax service localization guard: 1 test / 132 assertions passed. Expense service localization guard: 1 test / 301 assertions passed. Payroll service localization guard: 1 test / 253 assertions passed. Inventory workflow localization guard: 1 test / 297 assertions passed.
- State-changing route security guard passed: 1 test / 550 assertions. AR/AP posting confirmation guard passed: 1 test / 80 assertions. Invoice source-document typing guard passed: 1 test / 15 assertions.
- Slice 58 inventory dense-table UX cleanup passed targeted regression, Pint, TypeScript typecheck, and Vite production build; the broader full-suite command exceeded the local timeout budget after Slice 58, while `Phase4Slice10ReturnsCreditNotesTest.php` passed standalone with a larger timeout.
- Phase 14 rentals full feature coverage: 27 tests / 256 assertions passed.
- Phase 14 rentals foundation feature tests: 16 tests / 159 assertions passed.
- Phase 13 payroll feature tests: 6 tests / 90 assertions passed.
- Security hardening tests: 6 tests / 413 assertions passed.
- Phase 3 integrity tests: 6 tests / 607 assertions passed.
- Concurrency suite: 7 tests / 16 assertions passed.
- PostgreSQL concurrency, accounting, allocation, settlement, cheque, bank reconciliation, stock-transfer, inventory, fixed-asset depreciation/disposal, and Phase 3 integrity stress commands passed after Slice 56.
- Pint, TypeScript typecheck, and Vite production build passed after Slice 84.

## Documentation Entry Points

Use these first:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `PHASE_14_RENTAL_FULFILLMENT_REPORT.md`
- `PHASE_14_RENTAL_CONTRACTS_REPORT.md`
- `PHASE_14_RENTALS_FOUNDATION_REPORT.md`
- `PHASE_14_RENTALS_POLICY_DECISION.md`
- `PHASE_13_PAYROLL_FOUNDATION_REPORT.md`
- `PHASE_9_STAGING_PRODUCTION_CUTOVER.md`
- `PHASE_9_FINAL_CUTOVER_REPORT.md`
- `PHASE_9_SLICE_1_GEMINI_PROMPT.md`
- `spec/DEPLOYMENT.md`
- `spec/DEPLOYMENT_RUNBOOK.md`
- `spec/ROLLBACK_RUNBOOK.md`
- `spec/ENVIRONMENT_CHECKLIST.md`
- `spec/BACKUP_RESTORE_DRILL.md`
- `spec/RUNTIME_PROCESSES.md`
- `spec/GO_LIVE_ACCEPTANCE.md`
- `PHASE_8_FINAL_OPERATIONAL_READINESS_REPORT.md`
- `PHASE_7_FINAL_VERIFICATION_REPORT.md`
- `PHASE_6_FINAL_VERIFICATION_REPORT.md`
- `PHASE_5_FINAL_VERIFICATION_REPORT.md`
- `PHASE_3_FINAL_VERIFICATION_REPORT.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `PHASE_4_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_2_CORRECTION_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_3_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_4_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_5_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_6_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_7_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_8_GEMINI_PROMPT.md`
- `PHASE_4_INVENTORY_COSTING_DECISION.md`
- `NEXT_TASKS.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`
- `PHASE_3_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_3_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_4_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_5_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_6_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_7_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_8_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_9_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_10_GEMINI_PROMPT.md`
- `DOMAIN_MODEL_REVIEW.md`
- `DOMAIN_RELATIONSHIP_AUDIT.md`
- `SCHEMA_ASSUMPTION_AUDIT.md`
- `PROJECT_LOGIC_AUDIT.md`
- `docs/CONCURRENCY_AUDIT.md`

Historical files may mention tenant/company scope. Treat those mentions as legacy unless a later owner decision explicitly confirms the relationship.


