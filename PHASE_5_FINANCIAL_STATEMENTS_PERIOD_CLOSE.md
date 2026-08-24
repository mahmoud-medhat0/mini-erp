# PHASE 5 - FINANCIAL STATEMENTS & PERIOD CLOSE

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


Status: PARTIAL

This document is the Phase 5 planning contract for the active Laravel + Inertia Mini ERP migration.

Phase 5 must be implemented in bounded slices, like Phase 3 and Phase 4. Do not implement all reporting, close, year-end, tax, payroll, fixed assets, or deployment work in one pass.

## Current Baseline

The Laravel target is complete and locally verified through:

- M10 Spatie Activitylog audit backend, scheduler, and jobs baseline.
- Phase 3 Slices 1-10 AR/AP + Cash/Bank/Cheques.
- Phase 4 Slices 1-10 Sales, Purchasing, Moving Weighted Average Inventory, Returns, Credit Notes, Supplier Adjustments, and Manual AR/AP Note Settlement.
- Phase 5 Slice 1 Financial Statement Mapping Foundation.
- Phase 5 Slice 2 Balance Sheet & Income Statement Core Generation, including the local correction pass that requires report filtering by `ledger_entry.entry_date` and not row `created_at`.
- Phase 5 Slice 3 Cash Flow Statement Foundation, including explicit cash-flow classification, cash-equivalent derivation from Cash/Bank account links, and no timestamp financial filtering.
- Phase 5 Slice 4 Period Close Controls & Hardening, including central PeriodGuard checks, PostingEngine final safety net, close-readiness blockers, and exact close/reopen permissions.

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

## Strict Review-Avoidance Contract

Every remaining Phase 5 slice must satisfy these rules before reporting completion:

- Inspect the actual migrations/models/services before coding and do not reference columns that do not exist.
- Do not use non-existent or non-fillable model fields in tests as if they prove schema behavior. If a test needs a field, verify it exists in the migration/model first.
- Report date filters must use accounting dates (`ledger_entry.entry_date`, `journal_entry.entry_date`, or explicit document/period dates as appropriate), never audit timestamps such as `created_at` unless the feature is explicitly about creation timestamps.
- Financial reporting must read immutable posted accounting records only. Draft/submitted documents may appear as close blockers, but not as statement balances.
- Money/report totals must use integer minor units end-to-end. Frontend formatting must avoid floating-point division; split minor units with integer math.
- Unmapped/unclassified warnings must include only records that materially affect the selected report period/range. Do not show noisy warnings for inactive records or zero-movement accounts.
- Server-side authorization must be tested for every route/action/export/print endpoint. UI `useCan` checks are not a substitute.
- New TSX pages/components must not contain hardcoded visible English/Arabic strings. Translation keys/import names are allowed; visible labels, titles, statuses, empty states, table headers, button text, and warnings must come from dictionaries or backend multilingual payloads.
- Backend messages that are displayed to users must be localization-ready. Prefer structured `code` + parameters in service payloads, then translate in TSX/dictionaries; do not return English prose from services and render it directly.
- If a slice adds a backend route/action/configuration control that a user must operate, add the matching Inertia UI control in the same slice. Backend-only actions are acceptable only when explicitly documented as internal and tested as such.
- If a slice adds a column with a bounded set of values, add database-level constraints where the current database supports them and add tests for invalid values.
- New report/export output must be reconciled against the service totals in tests; UI, CSV, and service calculations must not diverge.
- Add regression tests for the exact mistakes likely in this slice, especially date-field misuse, non-existent columns, permission bypass, float math, and hidden unclassified/unmapped records.
- Run targeted source scans before final report and classify every result as acceptable or fixed.
- A source scan is "clean" only when it prints no matches. If it prints matches, include the matches or a summarized classification table and fix anything that is not explicitly acceptable.
- Verification commands must complete synchronously before they are reported as passed. If a command times out, is cancelled, or is left running in the background, report it as incomplete and rerun with a larger timeout if needed.
- Documentation/status updates must use the actual local command results from this slice, not previous Gemini summaries or older baselines.

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
   - Must implement explicit cash-flow classification only; no guessed operating/investing/financing logic.

4. `PHASE_5_SLICE_4_GEMINI_PROMPT.md`
   - Period Close Controls and Posting Guards.
   - Must centrally or consistently enforce service-level closed-period posting guards.

5. `PHASE_5_SLICE_5_GEMINI_PROMPT.md`
   - Year-End Close and Retained Earnings Decision Pack.
   - Documentation/decision slice unless the owner has explicitly approved a retained earnings posting model.

6. `PHASE_5_SLICE_6_GEMINI_PROMPT.md`
   - Phase 5 UX, Export/Print, E2E Smoke, and Close-Out Verification.
   - Must verify no hardcoded visible Phase 5 UI text remains and produce the final Phase 5 verification report.

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
