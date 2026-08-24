# MINI ERP - PHASE 5 SLICE 2 BALANCE SHEET & INCOME STATEMENT

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


You are continuing the active Laravel + Inertia Mini ERP migration.

Execute only Phase 5 Slice 2.

Do not start Cash Flow, Period Close hardening, Year-End Close, tax filing, payroll, fixed assets, E2E hardening, or deployment work in this pass.

## Read First

Read and follow:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_5_FINANCIAL_STATEMENTS_PERIOD_CLOSE.md`
- `PHASE_5_SLICE_1_GEMINI_PROMPT.md`

Inspect the Slice 1 implementation before coding.

## Objective

Build read-only Balance Sheet and Income Statement reports from immutable posted `ledger_entry` rows and the financial statement mapping foundation.

## Non-Negotiable Rules

Do not introduce:

- tenant/company/branch scope
- Spatie Teams
- new posting logic
- year-end close entries
- cached balance mutation
- floats or binary floating-point arithmetic
- hardcoded account codes as report logic
- hardcoded UI text in TSX pages

Financial reports must read posted ledger data only.

## Report Semantics

Balance Sheet:

- as-of report through a selected `as_of_date` or selected FinancialPeriod end date
- include balance sheet accounts only
- debit-normal accounts display `debit - credit`
- credit-normal accounts display `credit - debit`
- contra accounts follow their configured normal balance/sign
- total assets must be compared to liabilities + equity
- show an explicit imbalance warning if the statement is not balanced
- include unmapped balance-sheet accounts in a translated "Unmapped" section, not hidden

Income Statement:

- date range or selected FinancialPeriod/FiscalYear range
- include income statement accounts only
- revenue/credit-normal accounts display `credit - debit`
- expense/debit-normal accounts display `debit - credit`
- contra revenue uses its configured normal balance/sign
- calculate gross profit if revenue and COGS lines exist
- calculate net income/loss
- include unmapped income-statement accounts in a translated "Unmapped" section, not hidden

Do not implement retained earnings transfer in this slice.

## Required Backend

Create query services using current naming patterns, for example:

- `BalanceSheetReportService`
- `IncomeStatementReportService`

Create controllers under the existing Reports namespace/pattern.

Add routes under `/reports` with `reports.view` middleware and exact server-side checks for `view_financials`.

Add export routes only if bounded and consistent with existing CSV exports:

- export requires `reports.export` and `view_financials`

Do not use `settings.configure` as a financial report bypass.

## UI Scope

Add Inertia pages:

- `resources/js/Pages/Reports/BalanceSheet.tsx`
- `resources/js/Pages/Reports/IncomeStatement.tsx`

Update Reports Hub and AppLayout navigation only where permission-aware.

Frontend requirements:

- use `useCan`/`useCanAny`
- hide report links unless user has `reports.view` and `view_financials`
- no hardcoded user-facing text in TSX
- all headings/buttons/labels/table headers/statuses/empty states in `en.json` and `ar.json`
- use backend-provided statement line names for business-configured labels
- support RTL
- show empty states
- show unmapped-account warning when applicable
- do not make a landing page

## Tests

Add feature/unit tests for:

- Balance Sheet totals from posted ledger entries
- Income Statement net income from posted ledger entries
- contra revenue and contra asset display signs
- unmapped accounts are visible
- report access denied without `view_financials`
- report access denied without `reports.view`
- export access denied without `reports.export`
- routes render Inertia pages with expected props
- no company/branch/tenant columns or filters
- no mutation of ledger/journal tables

## Required Verification

Run:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --testsuite=Concurrency
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Report statement formulas, permissions used, routes added, tests added, and any remaining unmapped accounts.
