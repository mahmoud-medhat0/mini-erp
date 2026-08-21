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

The Laravel migration through M10 and Phase 3 Slices 1-6 is complete and locally verified on PostgreSQL.

Implemented:

- M2 Inertia foundation.
- M3 schema foundation and global RBAC.
- M5 Laravel session authentication.
- M6 migrated Inertia shell/pages.
- M7 Laravel core kernel parity.
- Phase 2 accounting core ledger spine:
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

Latest verified commands:

```powershell
cd laravel
composer install
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --filter=Phase3Slice6BankReconciliationTest
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Latest results:

- `php artisan migrate:status`: 33 migrations Ran.
- `php artisan test`: 213 total / 211 passed / 2 PostgreSQL-specific skipped, 1510 assertions.
- `php artisan test --testsuite=Concurrency`: 7 tests / 16 assertions passed.
- `php artisan concurrency:stress --workers=100`: passed.
- `php artisan accounting:concurrency-stress --workers=50`: passed.
- `php artisan accounting:allocation-concurrency-stress --workers=50`: passed.
- `php artisan accounting:cheque-concurrency-stress --workers=50`: passed.
- `php artisan accounting:bank-reconciliation-concurrency-stress --workers=50`: passed.
- `php artisan tokens:gc --batch=100`: passed.
- `npm run typecheck`: passed.
- `npm run build`: passed with optional `laravel:fonts`/`fontaine` warning only.

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

## Next Work

Recommended next product slice: Phase 3 Slice 7 - Inertia Pages for Phase 3 workflows.

Use `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md` as the corrected Phase 3 contract.

`PHASE_3_SLICE_1_GEMINI_PROMPT.md` has already been used for the first bounded Phase 3 slice and is now historical reference for what Slice 1 delivered.

`PHASE_3_SLICE_2_GEMINI_PROMPT.md` has already been used for the second bounded Phase 3 slice and is now historical reference for what Slice 2 delivered.

`PHASE_3_SLICE_3_GEMINI_PROMPT.md` has already been used for the third bounded Phase 3 slice and is now historical reference for what Slice 3 delivered.

`PHASE_3_SLICE_4_GEMINI_PROMPT.md` has already been used for the fourth bounded Phase 3 slice and is now historical reference for what Slice 4 delivered.

`PHASE_3_SLICE_5_GEMINI_PROMPT.md` has already been used for the fifth bounded Phase 3 slice and is now historical reference for what Slice 5 delivered.

`PHASE_3_SLICE_6_GEMINI_PROMPT.md` has already been used for the sixth bounded Phase 3 slice and is now historical reference for what Slice 6 delivered.

Prepare a new bounded Slice 7 prompt before asking Gemini to implement Phase 3 UI pages/actions.

Do not start Sales, Purchasing, Inventory, Payroll, Rentals, Fixed Assets, or full financial statements unless explicitly requested.

Before Phase 3, keep these invariants:

- no tenant/company/branch scope
- no float money math
- posted journal and ledger data immutable
- corrections by reversal
- numbering atomic
- posting idempotent
- Phase 3 audit uses the owner-approved Spatie Activitylog decision through the existing `AuditLogger` API
- attachment authorization through entity registry
- notifications targeted to users
