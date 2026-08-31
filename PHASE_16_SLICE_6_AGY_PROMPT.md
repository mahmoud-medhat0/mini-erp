# MINI ERP - PHASE 16 SLICE 6 - BUDGET VS ACTUAL REPORTS AND CLOSE-OUT

You are continuing the active Laravel 13 + Inertia + React Mini ERP repository.

This is the final bounded implementation slice for Phase 16.

Implement only budget-vs-actual reporting and Phase 16 close-out documentation.

## Objective

Add a read-only Budget vs Actual report under:

- `/budgeting/variance`

The report compares approved or active budget lines against posted ledger actuals by:

- financial period or date range
- account
- project
- cost center
- currency

This slice must not mutate accounting, budgets, fiscal periods, source documents, or ledger rows.

## Must Read First

- `PHASE_16_PROJECTS_COST_CENTERS_BUDGETING.md`
- `PHASE_16_SLICE_1_REPORT.md`
- `PHASE_16_SLICE_2_REPORT.md`
- `PHASE_16_SLICE_3_REPORT.md`
- `PHASE_16_SLICE_4_REPORT.md`
- `PHASE_16_SLICE_5_REPORT.md`
- `NO_MULTI_TENANT_POLICY.md`
- `spec/MASTER_ERP_SPEC.md` section C20
- `spec/PERMISSION_MATRIX.md`
- `laravel/config/erp_rbac.php`
- `laravel/app/Models/Budget.php`
- `laravel/app/Models/BudgetLine.php`
- `laravel/app/Application/Budgeting/BudgetService.php`
- `laravel/app/Application/Reports/ProjectProfitabilityReportService.php`
- `laravel/app/Application/Reports/CostCenterActualsReportService.php`
- `laravel/app/Application/Reports/ReportPageOptions.php`
- `laravel/resources/js/Pages/Budgeting/Budgets.tsx`
- `laravel/resources/js/Pages/Reports/ProjectProfitability.tsx`
- `laravel/routes/web.php`

## Non-Negotiable Scope

Do not create migrations in this slice.

Do not create or modify:

- `journal_entry`
- `journal_line`
- `ledger_entry`
- `budget`
- `budget_line`
- `financial_period`
- `fiscal_year`
- PostingEngine behavior
- ReversalService behavior
- Period close behavior
- Sales, purchasing, inventory, fixed assets, payroll, rentals, tax, treasury, or expenses workflow logic
- Balance Sheet, Income Statement, or Cash Flow report services unless a tiny navigation-only link is required

Do not build:

- forecast tables
- recurring budget templates
- department model
- branch budgets
- branch-scoped budgets
- company budgets
- budget posting
- budget journals
- budget revisions beyond the existing `budget` version model

Do not introduce:

- `company_id`
- `tenant_id`
- `currentCompany`
- `currentTenant`
- Spatie Teams
- company-owned budgets
- branch-owned budgets
- user-owned budgets

Branch is an existing operational dimension in bounded earlier workflows, but it is not part of the Phase 16 budget schema and must not be added to Budget vs Actual in this slice.

Project and Cost Center are standalone reporting dimensions.

## Required Backend Files

Create:

- `laravel/app/Application/Budgeting/BudgetVarianceReportService.php`
- `laravel/app/Application/Budgeting/BudgetVarianceCsvExporter.php`
- `laravel/app/Application/Budgeting/BudgetVariancePageData.php`
- `laravel/app/Http/Controllers/Budgeting/BudgetVarianceController.php`
- `laravel/tests/Feature/Phase16Slice6BudgetVarianceCloseOutTest.php`
- `PHASE_16_FINAL_VERIFICATION_REPORT.md`

Update only as needed:

- `laravel/routes/web.php`
- `laravel/resources/js/Components/AppLayout.tsx`
- `laravel/resources/js/Pages/Reports/Index.tsx` only if adding a compact link/card follows existing report hub patterns
- `laravel/resources/js/Types/index.ts`
- `laravel/resources/js/locales/en.json`
- `laravel/resources/js/locales/ar.json`
- `laravel/lang/ar.json` only if new backend validation messages need Arabic
- `PHASE_16_PROJECTS_COST_CENTERS_BUDGETING.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

## Report Service Contract

`BudgetVarianceReportService` must be read-only.

Inputs:

- `budget_id` optional
- `fiscal_year_id` optional
- `period_id` optional
- `from_date` optional
- `to_date` optional
- `account_id` optional
- `project_id` optional
- `cost_center_id` optional
- `currency` optional

Budget selection rules:

1. If `budget_id` is supplied, use that budget.
2. The selected budget must have status `active` or `approved`.
3. If `budget_id` is absent and `fiscal_year_id` is supplied, use the active budget for that fiscal year.
4. If no active budget exists for the selected fiscal year, return an empty report with a machine-readable warning code.
5. If both `budget_id` and `fiscal_year_id` are absent, use the first active budget ordered by most recent `activated_at`, then newest `created_at`.
6. Never select draft, submitted, archived, or cancelled budgets for comparison.

Date and period rules:

1. If `period_id` is supplied, it overrides `from_date` and `to_date`.
2. `period_id` must belong to the selected budget's fiscal year.
3. Budget lines are included by `budget_line.financial_period_id`.
4. Actuals are included by `ledger_entry.entry_date`.
5. If `from_date` and `to_date` are supplied, include budget lines whose financial period date range overlaps the selected date range.
6. If no date/period filter is supplied, compare the full selected budget fiscal year.

Actuals source rules:

1. Actuals must come from posted ledger entries only.
2. Use `ledger_entry` as the source of movement values.
3. Join `journal_entry` only to verify posted status and retrieve journal metadata if needed.
4. Do not read draft/submitted/approved-but-unposted journal lines as actuals.
5. Do not mutate or delete any ledger/journal rows.

Comparison grain:

Compare by the exact tuple:

- `financial_period_id`
- `account_id`
- `project_id` nullable
- `cost_center_id` nullable
- `currency`

The report must include:

- rows that have budget and actual
- rows that have budget but no actual
- rows that have actual but no budget

This is required so accountants can see both unused budget and unbudgeted actual spending or revenue.

Amount math:

1. Use integer minor units only.
2. Do not use floats, `(float)`, `round()`, JS `Number.toFixed()` for source calculations, or binary floating point arithmetic in PHP services.
3. Budget amount is `budget_line.amount_minor`.
4. Actual amount uses account normal balance:
   - debit-nature account: `debit_minor - credit_minor`
   - credit-nature account: `credit_minor - debit_minor`
5. `variance_minor = actual_minor - budget_minor`.
6. `variance_abs_minor = abs(variance_minor)`.
7. `variance_percent_bps` is nullable:
   - null when budget is zero
   - otherwise integer basis points = half-up rounded `abs(variance_minor) * 10000 / budget_minor`
8. Do not combine currencies. Return `summary_by_currency`.

Return shape:

- `selected_budget`
- `filters`
- `periods`
- `rows`
- `summary_by_currency`
- `warning_codes`
- `has_warnings`

Each row should include:

- period metadata
- account metadata
- project metadata or null
- cost center metadata or null
- currency
- budget_minor
- actual_minor
- variance_minor
- variance_abs_minor
- variance_percent_bps
- row_type: `matched`, `budget_only`, or `actual_only`

Machine-readable warning codes:

- `no_active_budget`
- `budget_not_comparable`
- `mixed_currencies`
- `unbudgeted_actuals_present`
- `budget_lines_without_actuals_present`

UI should localize these warning codes through dictionaries.

## Controller And Routes

Add routes inside the authenticated group:

- GET `/budgeting/variance`
- GET `/budgeting/variance/export`

Permissions:

- View route requires all of:
  - `budgeting.view`
  - `reports.view`
  - `view_financials`
- Export route requires all of:
  - `budgeting.export`
  - `reports.export`
  - `view_financials`

Keep controller thin:

- authorize
- validate query input
- call page-data/service/exporter
- return Inertia or CSV

Validation:

- UUID filters must be valid and exist in their tables when provided.
- `currency` must be size 3 and exist in `currency.code`.
- Dates must be valid dates.
- `to_date` must be greater than or equal to `from_date` when both supplied.

## Frontend

Create:

- `laravel/resources/js/Pages/Budgeting/Variance.tsx`

Update navigation:

- add `budgeting.variance` nav key if needed
- show it only when the user has `budgeting.view`, `reports.view`, and `view_financials`

UX requirements:

- accountant-friendly filters:
  - budget
  - fiscal year
  - financial period
  - date range
  - account
  - project
  - cost center
  - currency
- Use `SearchableSelect` for option sets.
- Use shared `DatePicker` for dates.
- Use existing report/table primitives and dense operational layout.
- Show summary cards by currency.
- Show warnings as localized operational messages.
- Show row type badges: matched, budget only, actual only.
- Show budget, actual, variance, and variance percentage.
- Highlight unfavorable variance with a warning/danger tone, but do not invent accounting policy labels such as favorable/unfavorable unless all affected account categories are explicitly handled.
- Add CSV export action only when permission allows.
- Add print action when `reports.print` and `view_financials` are present.

UI prohibitions:

- no native `<select>`
- no native `<option>`
- no native `type="date"`
- no `window.location.href`
- no `dangerouslySetInnerHTML`
- no hardcoded visible English or Arabic text
- no loose `any[]`, `unknown[]`, or untyped pagination link arrays
- do not place UI cards inside other cards
- no huge landing-page style hero

## CSV Export

CSV export must include:

- budget code
- budget version
- fiscal year
- period label
- account code
- account name
- project code/name or blank
- cost center code/name or blank
- currency
- budget_minor
- actual_minor
- variance_minor
- variance_percent_bps
- row_type

CSV must not use localized money formatting as the source value. Export raw integer minor units and basis points.

## Tests Required

Create `Phase16Slice6BudgetVarianceCloseOutTest.php`.

Minimum tests:

1. View route requires `budgeting.view`, `reports.view`, and `view_financials`.
2. Export route requires `budgeting.export`, `reports.export`, and `view_financials`.
3. Service selects active budget by fiscal year when no budget_id is supplied.
4. Draft/submitted/archived/cancelled budgets are not selected for comparison.
5. Budget lines compare to posted ledger actuals by period, account, project, cost center, and currency.
6. Actuals use posted ledger entries only and ignore unposted journal rows.
7. Debit-nature and credit-nature actual signs are calculated correctly.
8. Budget-only rows appear.
9. Actual-only rows appear and set `unbudgeted_actuals_present`.
10. Period filter overrides date filters and validates period belongs to selected budget fiscal year.
11. Date range includes budget lines whose financial period overlaps the date range and actuals by ledger entry date.
12. Account filter restricts both budget and actual sides.
13. Project filter restricts both budget and actual sides.
14. Cost-center filter restricts both budget and actual sides.
15. Currency filter restricts both budget and actual sides.
16. Mixed currencies are summarized separately and set `mixed_currencies`.
17. Variance percent basis points uses integer half-up math and returns null when budget is zero.
18. No budget action creates or mutates `journal_entry` or `ledger_entry` rows.
19. Inertia page receives selected budget, rows, summaries, filters, options, warning codes, and permissions.
20. CSV export returns raw minor-unit and basis-point values.
21. UI source scan proves no native select, native date input, unsafe redirect, `dangerouslySetInnerHTML`, hardcoded visible text placeholders, or loose pagination types in the new page.
22. Source scan proves no tenant, company/current context, branch-budget scope, or Spatie Teams terms were introduced in Slice 6 files.
23. `PHASE_16_FINAL_VERIFICATION_REPORT.md` exists and records Phase 16 close-out evidence.

Keep tests deterministic.

## Verification Commands

Run and report exact results:

- `php artisan migrate --force`
- `php artisan migrate:status`
- `php artisan test --filter=Phase16Slice6BudgetVarianceCloseOutTest --compact`
- `php artisan test --filter=Phase16Slice5BudgetFoundationTest --compact`
- `php artisan test --filter=Phase16Slice4ProjectCostCenterReportsTest --compact`
- `php artisan test --filter=Phase16Slice3ExpenseDimensionTest --compact`
- `php artisan test --filter=Phase16Slice2GlDimensionTest --compact`
- `php artisan test --filter=Phase16Slice1ProjectCostCenterTest --compact`
- `php artisan test --filter=Phase15ProductHardeningTest --compact`
- `php artisan test --testsuite=Concurrency --compact`
- `vendor/bin/pint --test`
- `npm.cmd run typecheck`
- `npm.cmd run build`
- `php artisan concurrency:stress --workers=100`
- `php artisan accounting:concurrency-stress --workers=50`
- `php artisan tokens:gc --batch=100`

Do not claim the full test suite passed unless you actually run the full suite.

If the local Windows machine hits paging-file/resource errors, retry the failed command once with:

- `php -d memory_limit=512M`
- or `php -d xdebug.mode=off`

If it still fails due local machine resources, report the exact local resource error.

## Documentation Updates

Update:

- `PHASE_16_PROJECTS_COST_CENTERS_BUDGETING.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

Create:

- `PHASE_16_FINAL_VERIFICATION_REPORT.md`

The final report must include:

- files changed
- migrations added, which must be zero
- service/controller/page summary
- budget-vs-actual math explanation
- permission summary
- no GL mutation confirmation
- no multi-tenant confirmation
- UI source scan confirmation
- exact verification command results
- remaining risks
- explicit next phase recommendation

