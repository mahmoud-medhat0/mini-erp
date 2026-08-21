# Mini ERP - Laravel Migration

Current target: Laravel + Inertia.js + React + TypeScript + Tailwind + PostgreSQL.

The repository still contains the older Next.js reference app under `app/`, but the active migration target is `laravel/`.

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
- Idempotency store, bounded `tokens:gc`, and PostgreSQL stress commands.

## Not Implemented Yet

- Sales and Purchasing workflows.
- Purchase Orders. The execution prompt is prepared in `PHASE_4_SLICE_3_GEMINI_PROMPT.md`.
- Customer Invoices and Supplier Bills.
- Inventory valuation, COGS, stock movement, and warehouse semantics.
- Payroll, Rentals, Fixed Assets, Projects, Budgeting, Recurring workflows.
- Full financial statements such as Balance Sheet, Income Statement, Cash Flow, and Equity Statement.
- Laravel browser E2E parity with the old Next.js Playwright suite.

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
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan accounting:phase3-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Latest verified result:

- 269 PHPUnit tests passing / 2221 assertions reported after Phase 4 Slice 2 correction.
- Phase 4 Slice 2 exact integer total correction is complete.
- Phase 3 Slice 9 stress/integrity suite: 6 tests / 262 assertions passed.
- Phase 3 Slice 8 report suite: 12 tests / 180 assertions passed.
- 7 Concurrency suite tests / 16 assertions passed.
- PostgreSQL concurrency, accounting, allocation, cheque, bank reconciliation, and phase3 stress commands passed.
- Phase 3 integrity check passed.
- TypeScript typecheck passed and Vite build passed.

## Documentation Entry Points

Use these first:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `PHASE_3_FINAL_VERIFICATION_REPORT.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `PHASE_4_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_2_CORRECTION_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_3_GEMINI_PROMPT.md`
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
