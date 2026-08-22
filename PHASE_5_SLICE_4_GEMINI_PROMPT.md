# MINI ERP - PHASE 5 SLICE 4 PERIOD CLOSE CONTROLS & POSTING GUARDS

You are continuing the active Laravel + Inertia Mini ERP migration.

Execute only Phase 5 Slice 4.

Do not start Year-End Close, retained earnings postings, tax filing, payroll, fixed assets, deployment, or unrelated modules in this pass.

## Read First

Read and follow:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_5_FINANCIAL_STATEMENTS_PERIOD_CLOSE.md`
- `PHASE_5_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_5_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_5_SLICE_3_GEMINI_PROMPT.md`

Inspect:

- `laravel/app/Application/Accounting/PeriodService.php`
- `laravel/app/Application/Accounting/PostingEngine.php`
- all services that create/post/reverse documents into `financial_period_id`
- current `/accounting/periods` page/actions
- current RBAC config
- actual migrations/models for every document table touched by close blockers; do not reference guessed number/status/date columns

## Objective

Harden FinancialPeriod close/reopen behavior so closed periods cannot receive new postings, reversals, allocations with period impact, inventory postings, or document postings.

This slice is about period controls and guards. It must not create year-end closing entries.

## Non-Negotiable Rules

Do not introduce:

- tenant/company/branch scope
- Spatie Teams
- fiscal year company ownership
- year-end retained earnings postings
- mutation of posted ledger entries
- hardcoded UI text in TSX
- broad permission shortcuts for close/reopen
- posting guards based on `created_at` / `updated_at`

Preserve single-ERP FinancialPeriod/FiscalYear context.
Posting and close logic must use accounting/document dates and `financial_period_id`, not row timestamps.

## Required Scope

Period status:

- preserve allowed statuses: `open`, `closed`, `reopened`
- if adding metadata, use fields such as `closed_by`, `closed_at`, `reopened_by`, `reopened_at`, and `close_note`
- do not add company/branch fields
- closed means no new accounting/stock/subledger financial impact may be posted into that period
- reopened must behave as postable/open for guards, but must keep audit metadata showing that the period was reopened

Guard design:

- Prefer a central application service such as `FinancialPeriodGuard` / `PeriodGuard` reused by posting services. If an existing service is extended instead, document the exact method and every caller.
- Guard method must lock/read the target FinancialPeriod by id where appropriate and reject statuses other than `open` or `reopened`.
- Guard method must validate that the posting/accounting date falls inside the selected period start/end bounds.
- Guard failures must throw a typed/domain exception or validation exception already used by the app; do not silently redirect or rely only on controller dropdown filtering.
- PostingEngine must be protected as a final safety net. Service-level guards must also exist so domain services fail before partial side effects.

Close readiness checks:

- block close if the period has unposted postable documents that would affect GL/AR/AP/stock
- report blockers with entity type, number/reference, status, and route if available
- include at minimum journal entries, opening balances, customer/supplier opening balances, receipts/payments, cheques with pending posting impact, bank reconciliations, sales/purchase documents that post, returns, credit notes, supplier adjustment notes, and inventory-impacting documents
- do not delete, auto-cancel, auto-post, or mutate blockers
- use actual table columns and states after inspection; if a module has no postable pending state for the selected period, explicitly report "covered by design" with reason
- close blockers must be calculated from explicit `financial_period_id` and/or accounting/document date fields, never `created_at`

Posting guards:

- centralize or consistently enforce checks so every posting service refuses closed periods
- apply to manual journals, reversals, opening balances, AR/AP documents, receipts/payments, cheque lifecycle postings, bank reconciliation finalization if it posts, invoices, bills, returns, credit notes, supplier adjustments, goods receipts/delivery notes inventory postings, and inventory costing movements
- controller period dropdown filtering is not enough
- service-level/server-side checks are mandatory
- every service inspected must be listed in the final report as `guard added`, `already guarded`, or `not period-impacting`, with file/method reference

Concurrency:

- close must use a transaction and deterministic locking on the selected financial period
- concurrent close and post attempts must not both succeed when they conflict
- reopen must be explicit and audited
- if PostgreSQL advisory locks or row locks are used, explain the lock key/order and prove no deadlock-prone mixed ordering is introduced
- close/readiness must re-check blockers inside the same transaction after acquiring the period lock

## RBAC

Use exact permissions:

- close: `close_period`
- reopen: `reopen_period`
- view close readiness: `accounting.periods` or `accounting.view` plus `view_financials` if sensitive totals are shown

Do not use `settings.configure` as a close/reopen bypass.

## UI Scope

Update the existing Accounting Periods page if needed.

Frontend requirements:

- permission-aware close/reopen buttons via `useCan`
- no hardcoded user-facing text in TSX
- all labels/actions/statuses/empty states/errors in `en.json` and `ar.json`
- show close blockers clearly
- do not add a landing page
- no currentCompany/currentBranch props
- close/reopen modals/forms must not contain hardcoded visible strings; use dictionaries for titles, button text, blockers, statuses, empty states, and validation messages

## Audit

Use `AuditLogger` for:

- close readiness failure/success if current audit style supports action events
- period close
- period reopen

Do not write to legacy `audit_log`.

## Tests

Add tests for:

- cannot post manual journal into closed period
- cannot reverse into closed period
- cannot post customer invoice/supplier bill into closed period
- cannot post receipt/payment into closed period
- cannot perform stock/inventory posting into closed period
- close blocked by unposted postable documents
- close succeeds when no blockers exist
- reopen requires `reopen_period`
- close requires `close_period`
- concurrent close/post race safety
- no company/branch/tenant fields
- date-in-period validation rejects mismatched `financial_period_id` and posting date
- PostingEngine final safety net rejects closed period even if called directly
- period close does not mutate or auto-post blockers
- TSX pages do not introduce new hardcoded visible English labels for close/reopen UI

Add a stress command or concurrency test if the current suite lacks close/post race coverage.

## Mandatory Source Scans Before Completion

Run and report the results:

```powershell
rg -n "created_at|updated_at" laravel/app/Application laravel/app/Http/Controllers laravel/tests/Feature
rg -n "settings\\.configure|Gate::authorize\\('settings\\.configure'|can\\('settings\\.configure'" laravel/app laravel/resources/js laravel/tests
rg -n "Close Period|Reopen Period|Closed|Reopened|Blockers|Close readiness|Posting period" laravel/resources/js/Pages laravel/resources/js/Components
rg -n "company_id|branch_id|tenant_id|currentCompany|currentBranch|Spatie Teams" laravel/database/migrations laravel/app laravel/resources/js laravel/tests
```

Investigate every match. Timestamp matches are acceptable only for audit metadata such as `closed_at`/`reopened_at`, never for accounting period filtering. `settings.configure` must not bypass close/reopen permissions.

## Required Verification

Run:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=10
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:settlement-concurrency-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Report all posting services inspected, guards added, close blockers covered, and race test results.
