# PHASE 5 - FINANCIAL STATEMENTS & PERIOD CLOSE

Status: PLANNED

This document is the Phase 5 planning contract for the active Laravel + Inertia Mini ERP migration.

Phase 5 must be implemented in bounded slices, like Phase 3 and Phase 4. Do not implement all reporting, close, year-end, tax, payroll, fixed assets, or deployment work in one pass.

## Current Baseline

The Laravel target is complete and locally verified through:

- M10 Spatie Activitylog audit backend, scheduler, and jobs baseline.
- Phase 3 Slices 1-10 AR/AP + Cash/Bank/Cheques.
- Phase 4 Slices 1-10 Sales, Purchasing, Moving Weighted Average Inventory, Returns, Credit Notes, Supplier Adjustments, and Manual AR/AP Note Settlement.

Read first:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `PHASE_4_SLICE_10_FINAL_REPORT.md`
- `docs/CONCURRENCY_AUDIT.md`

Use the current Laravel code as the source of truth.

Do not treat old Next.js docs, generated specs, or historical prompts as proof of unsupported business relationships.

## Non-Negotiable Rules

Do not introduce:

- tenant context or tenant middleware
- `company_id`, `branch_id`, or `tenant_id`
- `company_user`
- `users.company_id`
- `branch.company_id`
- `fiscal_year.company_id`
- `currentCompany` or `currentBranch`
- Spatie Teams
- company/branch dimensions in number sequences
- company/branch reporting scope
- warehouse/location semantics
- full tax/VAT filing workflow
- payroll, rentals, fixed assets, projects, budgeting, or landed-cost modules

If a relationship is not explicitly supported by current owner requirements, classify it as:

`UNDEFINED - DO NOT ASSUME`

Preserve:

- single-installation ERP context
- Spatie Permission with teams disabled
- detailed permission checks already used by the Laravel app
- Spatie Activitylog through the existing `AuditLogger`
- append-only audit behavior
- existing attachment and notification services
- atomic global document numbering by key
- idempotent actions
- integer minor-unit money only
- no floats, no `(float)`, no `round()`, and no binary floating-point arithmetic in money/reporting/close calculations
- immutable posted accounting records
- immutable stock movement ledger
- corrections through reversal/credit/debit documents, not mutation of posted ledgers

## Permission & UI Contract

Phase 5 must continue the current detailed permission model.

Server-side authorization is mandatory. UI permission checks are only presentation convenience.

Minimum permission rules:

- financial statement viewing requires `reports.view` and `view_financials`
- financial statement export requires `reports.export` and `view_financials`
- financial statement print requires `reports.print` and `view_financials`
- financial statement mapping/configuration requires `accounting.mappings`
- period close requires `close_period`
- period reopen requires `reopen_period`
- report hub visibility remains permission-aware through existing `useCan`/`useCanAny` patterns

Do not replace detailed checks with broad page-only gates.

Do not rely on `settings.configure` as a shortcut for financial close/reporting actions unless the current code path already intentionally grants it and the implementation report calls that out. Prefer exact permissions.

Frontend pages must not contain hardcoded user-facing text or hardcoded business terms in TSX. Add EN/AR keys to the existing dictionaries and read labels, statuses, empty states, headings, table headers, action names, and validation messages through the existing translation pattern. If option labels are business-configured, provide them from the backend instead of hardcoding them in the page.

Do not add any hardcoded "team", tenant, company, branch, currentCompany, or currentBranch assumptions in pages, props, routes, services, or tests.

## Phase 5 Business Scope

Phase 5 adds financial statements and period close controls on top of the existing posted ledger.

Target capabilities across the phase:

- financial statement line/mapping foundation
- Balance Sheet report
- Income Statement report
- Cash Flow statement foundation with explicit cash-flow classifications
- period close/reopen hardening and posting guards
- close readiness checks
- optional year-end close decision pack before retained-earnings posting is implemented
- polished Inertia pages with permission-aware actions and no hardcoded text
- exports/print views where bounded
- tests and stress/integrity checks for close/posting race conditions

## Must Not Be Built Yet Without Owner Decision

Do not implement these until explicitly approved:

- automatic year-end closing journal entries
- retained earnings transfer posting
- dividend/distribution workflow
- full VAT/tax filing, tax returns, or jurisdiction-specific reports
- external audit packs
- consolidation/multi-company reports
- warehouse/location reporting dimensions
- budget versus actual
- approval workflow engine
- production deployment

## Confirmed Integration Points

Use existing systems:

- `ledger_entry`, `journal_entry`, and `journal_line` as financial statement source of truth
- Account, AccountGroup, AccountType, and AccountCategory metadata
- FinancialPeriod and FiscalYear in single-ERP context
- existing PostingEngine and posting services
- existing PeriodService unless a bounded replacement is justified
- existing Report controllers/pages structure under `/reports`
- existing `AccountingAccountMappingService`
- existing `AuditLogger`
- existing RBAC config/seeding pattern
- existing EN/AR translation dictionaries
- existing `useCan`/`useCanAny` permission helpers

## Phase 5 Slice Plan

1. `PHASE_5_SLICE_1_GEMINI_PROMPT.md`
   - Financial Statement Mapping Foundation.

2. `PHASE_5_SLICE_2_GEMINI_PROMPT.md`
   - Balance Sheet and Income Statement reports.

3. `PHASE_5_SLICE_3_GEMINI_PROMPT.md`
   - Cash Flow Statement Foundation.

4. `PHASE_5_SLICE_4_GEMINI_PROMPT.md`
   - Period Close Controls and Posting Guards.

5. `PHASE_5_SLICE_5_GEMINI_PROMPT.md`
   - Year-End Close and Retained Earnings Decision Pack.

6. `PHASE_5_SLICE_6_GEMINI_PROMPT.md`
   - Phase 5 UX, Export/Print, E2E Smoke, and Close-Out Verification.

## Verification Gate

Run from `laravel/` for every implementation slice:

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
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan accounting:phase3-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Use `concurrency:stress --workers=100` only when the local workstation can handle it without Windows paging-file exhaustion.

Add Phase 5 specific close/reporting tests and stress commands when the slice introduces concurrency-sensitive close/reopen behavior.
