# CONTINUE HERE - Mini ERP Laravel handoff

Current date/context: 2026-08-21. This is the current handoff for the Laravel + Inertia + React migration track.

The old Next.js app under `app/` remains historical reference only. Do not restore old tenant/company-scope behavior from it.

## Source Of Truth

Use the current Laravel code and these documents first:

- `README.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `DOMAIN_MODEL_REVIEW.md`
- `DOMAIN_RELATIONSHIP_AUDIT.md`
- `SCHEMA_ASSUMPTION_AUDIT.md`
- `PROJECT_LOGIC_AUDIT.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `PHASE_4_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_2_CORRECTION_GEMINI_PROMPT.md`
- `docs/CONCURRENCY_AUDIT.md`

Historical specs can still be useful for ERP scope, but owner corrections override old generated architecture.

## Current Stack

- Laravel 13.x + PHP 8.3+
- PostgreSQL
- Inertia.js + React + TypeScript + Tailwind
- Laravel session auth and CSRF
- Spatie Permission with teams disabled
- Spatie Translatable for multilingual master data
- Spatie Activitylog as the active audit backend
- Laravel scheduler/queues baseline

## Non-Negotiable Corrections

This ERP is not currently a multi-tenant SaaS.

Do not introduce:

- tenant context or tenant middleware
- `company_user`
- `users.company_id`
- `branch.company_id`
- `fiscal_year.company_id`
- `currentCompany` or `currentBranch`
- company-owned roles/permissions
- Spatie teams
- company/branch dimensions in document numbering
- branch/company security scopes unless explicitly defined later

If a relationship is not explicitly supported by owner requirements or a later owner decision, classify it as:

`UNDEFINED - DO NOT ASSUME`

Confirmed later owner decision:

- FiscalYear is `SINGLE-ERP CONTEXT`.
- Fiscal years are global to this installation/business profile.
- `fiscal_year.year` is globally unique.
- FinancialPeriod belongs to FiscalYear.

## Current Verified Status

The Laravel migration through M10, Phase 3 Slices 1-10, and Phase 4 Slice 1 (Catalog Foundation) is complete. Phase 4 Slice 2 (Sales Order Backend & UX) is implemented and reported verified, but local review found authoritative `round(... / 1000000)` Sales Order line-total math that must be corrected before Slice 3.

Implemented:

- M2 Inertia foundation.
- M3 schema foundation and global RBAC.
- M5 Laravel session authentication.
- M6 migrated Inertia shell/pages.
- M7 Laravel core kernel parity.
- Phase 2 accounting core ledger spine.
- M8 page actions.
- M9 attachments and notifications services.
- M10 Spatie Activitylog migration, audit viewer, scheduler, and jobs baseline.
- Phase 3 Slices 1-10 Foundation (Master Data, AR/AP Subledgers, Receipts/Payments, Allocation Engine, Cheques, Bank Reconciliation, Inertia Pages/UX, Operational Reports, Concurrency Stress & Integrity, Close-Out Report).
- Phase 4 Slice 1 Product & Service Catalog Foundation.
- Phase 4 Slice 2 Sales Order Backend & UX:
  - `sales_order` and `sales_order_line` models and migrations.
  - `SalesOrderService` lifecycle (`draft` -> `submitted` -> `confirmed` / `cancelled`).
  - Integer minor currency math & exact quantity scaling (`quantity_e6`).
  - Number sequence allocation `SO-YYYY-XXXXX` with idempotent confirmation replay.
  - Spatie Activitylog audit via `AuditLogger`.
  - Attachment entity registry registration for `sales_order`.
  - `SalesOrderController` endpoints under `/sales/orders/*`.
  - `SalesOrders.tsx` Inertia page with customer selector, product/UOM selector, dynamic line items, real-time line total preview, status badges, and action buttons.
  - `Phase4Slice2SalesOrderTest` 12/12 passing tests (52 assertions) reported by Gemini.
  - Needs correction before acceptance: replace authoritative `round(... / 1000000)` line-total calculation with exact integer arithmetic.
  - currencies and FX rates
  - fiscal years and periods
  - account categories and account types
  - chart of accounts
  - manual journals
  - posting engine
  - immutable ledger entries
  - reversal workflow
  - opening balances
  - General Journal, General Ledger, Trial Balance
  - demo accounting data seeder and empty states
- M8 page actions:
  - company create/update
  - branch create/update
  - numbering create/update
  - role assign/revoke
- M9 attachments and notifications services.
- M10 Spatie Activitylog migration, audit viewer, scheduler, and jobs baseline.
- Phase 3 Slice 1 master data:
  - Customer and Supplier models/services.
  - CashAccount and BankAccount models/services.
  - GL account and currency relationships.
  - optimistic locking, RBAC permissions, Spatie Activitylog audit, and attachment registry entries.
- Phase 3 Slice 2 AR/AP subledger and opening balances:
  - Customer/Supplier opening balances.
  - `receivable_entry` and `payable_entry` subledgers.
  - global accounting mappings for AR control, AP control, and opening-balance offset accounts.
  - PostingEngine integration, idempotent posting, subledger-to-GL reconciliation, and DB integrity hardening.
- Phase 3 Slice 3 receipt/payment posting:
  - Customer Receipt and Supplier Payment draft/post services.
  - global `REC-YYYY-XXXXX` and `PAY-YYYY-XXXXX` numbering.
  - PostingEngine GL effects for Cash/Bank GL vs AR/AP control.
  - AR/AP subledger effects, unapplied balance tracking, idempotent posting, linked GL currency validation, and DB integrity hardening.
- Phase 3 Slice 4 allocation engine:
  - ReceivableAllocation and PayableAllocation models/services.
  - CustomerReceipt-to-ReceivableEntry and SupplierPayment-to-PayableEntry settlement.
  - allocation reversal without mutating journals/ledgers.
  - deterministic locks, active allocation row locking, idempotency, and true concurrent allocation stress coverage.
- Phase 3 Slice 5 cheque lifecycle:
  - IncomingCheque and OutgoingCheque models/services.
  - incoming receive/deposit/clear/bounce/return and outgoing issue/clear/return/cancel pre-clear workflows.
  - Cheques Under Collection and Cheques Payable mappings.
  - PostingEngine GL effects, AR/AP subledger effects, idempotency, Spatie Activitylog audit, attachment registry entries, and PostgreSQL cheque transition stress coverage.
- Phase 3 Slice 6 bank reconciliation:
  - BankReconciliation and BankReconciliationLine models/services.
  - manual ledger-backed statement matching only; no bank statement import.
  - CashBook and BankBook query services derived from immutable posted ledger entries.
  - draft -> in_progress -> reconciled lifecycle, zero-difference finalization, DB-enforced immutable finalized records, and PostgreSQL reconciliation stress coverage.
- Phase 3 Slice 7 Inertia pages and UX actions:
  - 13 Controllers, 14 Inertia pages, DatePicker with RTL support, sidebar navigation, EN/AR locale support, and 13/13 UI feature tests.
- Phase 3 Slice 8 operational and subledger reports:
  - `reports.view` permission, Reports Hub, customer/supplier statements, AR/AP aging, Cash Book, Bank Book, Cheque Register, bank reconciliation status/detail, AR/AP to GL reconciliation, CSV exports, and read-only report services under `App\Application\Reports`.
- Phase 3 Slice 9 PostgreSQL stress and integrity hardening:
  - `accounting:phase3-integrity-check` audit command.
  - `accounting:phase3-stress` orchestrator command.
  - PostgreSQL concurrency stress coverage across all Phase 3 workflows.
  - `Phase3Slice9StressIntegrityTest` 6/6 tests, 262 assertions.
  - read-only report integrity verified.
- Phase 3 Slice 10 Close-Out & Final Verification Gate:
  - Repository documentation audit, status synchronization, final verification gate execution, and `PHASE_3_FINAL_VERIFICATION_REPORT.md`.

Latest verified commands:

```powershell
cd laravel
composer install
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --filter=Phase4Slice1CatalogTest
php artisan test --filter=Phase4Slice2SalesOrderTest
php artisan test --filter=Phase3Slice9StressIntegrityTest
php artisan test --filter=Phase3Slice8ReportsTest
php artisan test --filter=Phase3Slice6BankReconciliationTest
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

Latest results:

- `php artisan migrate:status`: not included in the attached Slice 2 summary; must be re-run in the correction pass.
- `php artisan test`: 266 passing tests / 2207 assertions reported after Phase 4 Slice 2.
- `php artisan test --filter=Phase4Slice2SalesOrderTest`: 12 tests / 52 assertions passed.
- `php artisan test --filter=Phase3Slice9StressIntegrityTest`: 6 tests / 262 assertions passed.
- `php artisan test --filter=Phase3Slice8ReportsTest`: 12 tests / 180 assertions passed.
- `php artisan test --testsuite=Concurrency`: 7 tests / 16 assertions passed.
- `php artisan concurrency:stress --workers=100`: passed.
- `php artisan accounting:concurrency-stress --workers=50`: passed.
- `php artisan accounting:allocation-concurrency-stress --workers=50`: passed.
- `php artisan accounting:cheque-concurrency-stress --workers=50`: passed.
- `php artisan accounting:bank-reconciliation-concurrency-stress --workers=50`: passed.
- `php artisan accounting:phase3-integrity-check`: passed.
- `php artisan accounting:phase3-stress --workers=50`: passed.
- `php artisan tokens:gc --batch=100`: passed.
- `vendor/bin/pint --test`: passed after Phase 4 Slice 2 report.
- `npm run typecheck`: passed.
- `npm run build`: passed.

## Audit Status

Spatie Activitylog is now the active audit backend.

- New writes go to `activity_log`.
- Legacy `audit_log` is retained as a read-only archive.
- `AuditLogger::record(...)` keeps the old application API but writes through Spatie Activitylog.
- `AuditLogQueryService` maps Spatie rows back to the old UI aliases:
  - `actor_id`
  - `actor_name`
  - `actor_email`
  - `action`
  - `entity_type`
  - `entity_id`
  - `before_json`
  - `after_json`
  - `reason`
  - `request_id`
  - `ip`
  - `device`
  - `at`
- `activity_log` and legacy `audit_log` are protected by append-only DB triggers.

Spatie Activitylog installed version:

```text
spatie/laravel-activitylog 4.12.3
```

## Local Login

Default development bootstrap user:

```text
Email: admin@mini-erp.local
Password: Password123!
Role: SUPER_ADMIN
```

The bootstrap user is not tied to a company, branch, tenant, or current-company context.

## Run Locally

```powershell
cd laravel
composer install
npm install
php artisan migrate --seed
npm run dev
composer run serve:no-xdebug
```

Open:

```text
http://127.0.0.1:8000
```

On this Windows/WAMP setup, direct `php artisan serve` may exit when Xdebug is enabled. Prefer `composer run serve:no-xdebug`.

## Current DB Counts From Last Verification

```text
audit_log: 17
activity_log: 397
users: 7
jobs: 0
failed_jobs: 0
bank_reconciliation: 10
bank_reconciliation_line: 12
journal_entry: 81
ledger_entry: 156
```

`activity_log` can vary because stress commands create real audit records outside PHPUnit transactions.

## Phase 3 Completion & Phase 4 Start

**Phase 3 Slices 1–10 are 100% complete.** Phase 3 AR/AP + Cash/Bank/Cheques track is fully closed out for the agreed scope. See `PHASE_3_FINAL_VERIFICATION_REPORT.md`.

All 10 bounded prompt files (`PHASE_3_SLICE_1_GEMINI_PROMPT.md` through `PHASE_3_SLICE_10_GEMINI_PROMPT.md`) have been executed and remain as historical traceability reference.

Phase 4 planning is prepared:

- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `PHASE_4_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_2_CORRECTION_GEMINI_PROMPT.md`

Next prepared execution step:

1. **Phase 4 Slice 2 Correction: Sales Order Integer Totals**
   - Remove authoritative `round(... / 1000000)` and floating division from Sales Order total calculation.
   - Use exact integer arithmetic with overflow checks, modulo, and `intdiv`.
   - No Purchase Orders, Delivery Notes, Goods Receipts, Customer Invoices, Supplier Bills, AR/GL posting, Inventory Valuation, COGS, VAT, Returns, Reports, E2E hardening, or deployment work.

Other possible owner choices:

- **Optional: E2E Browser Testing** (Playwright / Dusk smoke testing).
- **Optional: Production Deployment Readiness** (Nginx, Supervisor, Redis, Backup strategies).

Do not start Sales, Purchasing, Inventory, Payroll, Rentals, Fixed Assets, or full financial statements unless explicitly requested through a bounded prompt.

Going forward, keep these invariants:

- no tenant/company/branch scope
- no float money math
- posted journal and ledger data immutable
- corrections by reversal
- numbering atomic
- posting idempotent
- Phase 3 audit uses the owner-approved Spatie Activitylog decision through the existing `AuditLogger` API
- attachment authorization through entity registry
- notifications targeted to users
