# MINI ERP - PHASE 16 SLICE 4 - PROJECT AND COST CENTER ACTUAL REPORTS

You are continuing the active Laravel 13 + Inertia + React Mini ERP repository.

This is a bounded reporting slice. Keep it small, strict, and reviewable.

## Objective

Build read-only Project and Cost Center actual reports from already posted GL ledger data.

Create:

- `/reports/project-profitability`
- `/reports/project-profitability/export`
- `/reports/cost-center-actuals`
- `/reports/cost-center-actuals/export`

## Must Read First

- `PHASE_16_PROJECTS_COST_CENTERS_BUDGETING.md`
- `PHASE_16_SLICE_1_REPORT.md`
- `PHASE_16_SLICE_2_REPORT.md`
- `PHASE_16_SLICE_3_REPORT.md`
- `NO_MULTI_TENANT_POLICY.md`
- `laravel/app/Application/Reports/BranchProfitabilityReportService.php`
- `laravel/app/Application/Reports/BranchProfitabilityCsvExporter.php`
- `laravel/app/Http/Controllers/Reports/BranchProfitabilityReportController.php`
- `laravel/resources/js/Pages/Reports/BranchProfitability.tsx`
- `laravel/app/Application/Reports/IncomeStatementReportService.php`
- `laravel/app/Application/Reports/ReportPageOptions.php`
- `laravel/routes/web.php`

## Non-Negotiable Scope

Do not create migrations in this slice.

Do not add columns to operational source documents.

Do not touch Sales, Purchasing, Inventory, Fixed Assets, Payroll, Rentals, Treasury, Tax, Prepaids, Accruals, or Period Close behavior.

Do not add write actions. These reports are read-only.

Do not introduce:

- `company_id`
- `tenant_id`
- `currentCompany`
- `currentTenant`
- Spatie Teams
- user/project ownership
- user/cost-center ownership
- branch as tenant or security scope

Branch may remain wherever it already exists, but do not use it as a new filter for these reports unless current code already provides it naturally through existing ledger filters. Project and Cost Center are the focus.

## Data Source Rules

Use `ledger_entry` as the only financial movement source.

Join `journal_entry` only to confirm the source journal is posted if the current schema exposes that status.

Use `ledger_entry.entry_date` for date filtering.

Never read unposted journal drafts.

Never read subledger/source document totals to compute actuals.

Never double count by joining one ledger row to multiple rows.

Use exact integer money values only. No floats.

## Currency Rules

Do not combine different currencies into one money total.

Return:

- `currency_codes`
- `summary_by_currency`
- row totals carrying their own `currency`
- a warning flag when more than one currency exists in the filtered result

If the user selects a currency filter, restrict all calculations to that currency.

If no currency filter is selected and several currencies exist, show per-currency summaries. Do not display a single grand money total across currencies.

## Filters

Support these optional filters:

- `period_id`
- `date_from`
- `date_to`
- `project_id`
- `cost_center_id`
- `account_id`
- `currency`

Rules:

- If `period_id` is present, it overrides date_from and date_to using the selected FinancialPeriod start and end dates.
- Validate `period_id` exists in `financial_period`.
- Validate `project_id` exists in `project`.
- Validate `cost_center_id` exists in `cost_center`.
- Validate `account_id` exists in `account`.
- Validate `currency` exists in `currency.code`.
- Validate `date_to` is after or equal to `date_from`.
- Reporting filters may include inactive Projects and Cost Centers because historical posted ledger entries must remain reportable.

## Project Profitability Report

Create:

- `App\Application\Reports\ProjectProfitabilityReportService`
- `App\Application\Reports\ProjectProfitabilityCsvExporter`
- `App\Http\Controllers\Reports\ProjectProfitabilityReportController`
- `resources/js/Pages/Reports/ProjectProfitability.tsx`

Report rows must be grouped by:

- project
- currency

Also include an unassigned project row for ledger rows where `ledger_entry.project_id` is null.

Filter by optional cost center and account.

Include profit-and-loss ledger movements only:

- revenue
- contra revenue
- cost of goods sold
- operating expenses
- other income
- other expenses

Prefer `financial_statement_line.section_code` when available. Fall back to `account.type` and `account.nature` only when mapping is missing.

Required row fields:

- project_id
- project_code
- project_name
- project_status
- is_unassigned
- currency
- ledger_row_count
- debit_minor
- credit_minor
- revenue_minor
- contra_revenue_minor
- net_revenue_minor
- cogs_minor
- gross_profit_minor
- operating_expense_minor
- operating_income_minor
- other_income_minor
- other_expense_minor
- net_income_minor
- profit_margin_bps nullable integer

## Cost Center Actuals Report

Create:

- `App\Application\Reports\CostCenterActualsReportService`
- `App\Application\Reports\CostCenterActualsCsvExporter`
- `App\Http\Controllers\Reports\CostCenterActualsReportController`
- `resources/js/Pages/Reports/CostCenterActuals.tsx`

Report rows must be grouped by:

- cost center
- currency

Also include an unassigned cost center row for ledger rows where `ledger_entry.cost_center_id` is null.

Filter by optional project and account.

Include account-level breakdown so accountants can inspect what created the actuals.

Required row fields:

- cost_center_id
- cost_center_code
- cost_center_name
- cost_center_status
- is_unassigned
- currency
- ledger_row_count
- debit_minor
- credit_minor
- net_minor
- accounts

Each account breakdown row must include:

- account_id
- account_code
- account_name
- account_type
- account_nature
- debit_minor
- credit_minor
- net_minor
- ledger_row_count

For `net_minor`, use account nature:

- debit nature: debit minus credit
- credit nature: credit minus debit

## Page Options

Extend `ReportPageOptions` with reusable methods if needed:

- projects for reporting
- cost centers for reporting
- accounts for reporting
- currencies
- financial periods if there is already a period options helper, use it instead of duplicating logic

Do not filter out inactive projects or cost centers from reporting selectors. Historical dimensions must remain selectable.

## Routes And Permissions

Register routes inside the existing reports group.

Both report pages require:

- `reports.view`
- `view_financials`

Both export routes require:

- `reports.view`
- `reports.export`
- `view_financials`

Controllers must remain thin:

- validate filters
- call service
- render Inertia page or stream CSV

No query-heavy report logic in controllers.

## Frontend UX Rules

Use existing UI patterns.

Use:

- `AppLayout`
- `PageHeader`
- `Card`
- `MetricCard`
- `EmptyState`
- `SearchableSelect`
- `DatePicker`
- `Button`
- `StatusBadge`
- `tableClasses`
- `formatMoney`
- `getLocalizedName`

Do not use native HTML selects.

Do not use native date inputs.

Do not use `window.location.href`.

Use Inertia router for filtering.

CSV export may be a normal anchor href like existing reports.

All visible page text must be dictionary-backed through:

- `laravel/resources/js/locales/en.json`
- `laravel/resources/js/locales/ar.json`

Add Arabic and English translations.

No hardcoded visible TSX labels.

Keep pages usable for accountants:

- filters at the top
- compact summary cards
- clear warning when multiple currencies are present
- separate rows by currency
- obvious unassigned rows requiring review
- account breakdown for cost-center actuals
- print button if existing permission pattern supports `reports.print`
- export button guarded by permissions

## Reports Hub

Add cards to `resources/js/Pages/Reports/Index.tsx` under the financial or management reporting group.

Only show these cards when the user can view financial reports.

Use dictionary text only.

## Tests Required

Create `laravel/tests/Feature/Phase16Slice4ProjectCostCenterReportsTest.php`.

Minimum tests:

1. Routes require both `reports.view` and `view_financials`.
2. CSV export additionally requires `reports.export`.
3. Project profitability reads only posted ledger rows and ignores draft journals.
4. Project profitability calculates revenue, contra revenue, COGS, expenses, other income, other expenses, net income, and margin correctly from integer ledger movements.
5. Project profitability includes unassigned project rows.
6. Project profitability does not combine different currencies into a single grand money total.
7. Cost center actuals groups by cost center and currency.
8. Cost center actuals includes account-level breakdown with net by account nature.
9. Cost center actuals includes unassigned cost center rows.
10. `period_id` overrides date range using FinancialPeriod start and end dates.
11. Filters validate missing project, cost center, account, currency, and invalid dates.
12. Inactive projects and cost centers with historical ledger rows remain reportable.
13. Reports hub exposes both pages through dictionary-backed cards.
14. Source scan proves no tenant, company, current context, native select, native date input, or hardcoded visible labels were introduced in the new TSX pages.

Keep tests deterministic and use existing factories/seed helpers where possible.

## Verification Commands

Run and report exact results:

- `php artisan migrate --force`
- `php artisan test --filter=Phase16Slice4ProjectCostCenterReportsTest --compact`
- `php artisan test --filter=Phase16Slice3ExpenseDimensionTest --compact`
- `php artisan test --testsuite=Concurrency --compact`
- `vendor/bin/pint --test`
- `npm.cmd run typecheck`
- `npm.cmd run build`
- `php artisan concurrency:stress --workers=100`
- `php artisan accounting:concurrency-stress --workers=50`
- `php artisan tokens:gc --batch=100`

Do not claim the full test suite passed unless you actually run the full suite.

## Required Report File

Create `PHASE_16_SLICE_4_REPORT.md` with:

- files changed
- migrations added, expected zero
- routes added
- services/controllers/pages added
- exact reporting formulas
- currency handling statement
- permissions statement
- no multi-tenant scope confirmation
- UI hardcoded text scan confirmation
- test results and command outputs summary
- remaining risks, if any

