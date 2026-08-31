# Mini ERP - Phase 16 Slice 4: Project and Cost Center Actual Reports Completion Report

## 1. Executive Summary

Phase 16 Slice 4 delivers read-only **Project Profitability** and **Cost Center Actuals** reports built strictly on top of posted General Ledger (`ledger_entry`) data.

All financial calculations use integer minor units and respect account natures (`debit` vs `credit`) and financial statement line classifications (`revenue`, `contra_revenue`, `cogs`, `operating_expenses`, `other_income`, `other_expenses`). Unassigned ledger rows (`project_id IS NULL` or `cost_center_id IS NULL`) are isolated with distinct review badges to maintain complete financial auditability.

---

## 2. Routes and Controllers Implemented

| Route | Name | Controller Action | Permissions Required |
|---|---|---|---|
| `GET /reports/project-profitability` | `reports.project-profitability` | `ProjectProfitabilityReportController@index` | `reports.view` + `view_financials` |
| `GET /reports/project-profitability/export` | `reports.project-profitability.export` | `ProjectProfitabilityReportController@exportCsv` | `reports.view` + `reports.export` + `view_financials` |
| `GET /reports/cost-center-actuals` | `reports.cost-center-actuals` | `CostCenterActualsReportController@index` | `reports.view` + `view_financials` |
| `GET /reports/cost-center-actuals/export` | `reports.cost-center-actuals.export` | `CostCenterActualsReportController@exportCsv` | `reports.view` + `reports.export` + `view_financials` |

---

## 3. Backend Architecture and Services

### 3.1 `ProjectProfitabilityReportService` & `ProjectProfitabilityCsvExporter`
- **Data Source:** Only posted `ledger_entry` records (joined to `journal_entry` where `status = 'posted'`).
- **Scope:** P&L statement lines (`revenue`, `contra_revenue`, `cogs`, `operating_expenses`, `other_income`, `other_expenses`).
- **Grouping:** Grouped by `(project_id, currency)`.
- **Unassigned Rows:** Aggregated with `is_unassigned = true` and `project_code = 'UNASSIGNED'`, exposing `readiness.has_unassigned_pnl` and `readiness.unassigned_pnl_row_count`.
- **Calculations:**
  - `net_revenue_minor = revenue_minor - contra_revenue_minor`
  - `gross_profit_minor = net_revenue_minor - cogs_minor`
  - `operating_income_minor = gross_profit_minor - operating_expense_minor`
  - `net_income_minor = operating_income_minor + other_income_minor - other_expense_minor`
  - `profit_margin_bps = net_revenue_minor !== 0 ? intdiv(net_income_minor * 10000, abs(net_revenue_minor)) : null`
- **Filter Overrides:** When `period_id` is supplied, `FinancialPeriod` start and end dates override `date_from` and `date_to`.
- **CSV Exporter:** Streams CSV with report filter metadata, column headers, data rows, and currency summary block.

### 3.2 `CostCenterActualsReportService` & `CostCenterActualsCsvExporter`
- **Data Source:** Only posted `ledger_entry` records.
- **Grouping:** Grouped by `(cost_center_id, currency)`.
- **Breakdown:** Full account-level breakdown nested per cost center group:
  - Account nature `debit`: `net_minor = debit_minor - credit_minor`
  - Account nature `credit`: `net_minor = credit_minor - debit_minor`
- **Unassigned Rows:** Aggregated with `is_unassigned = true` and `cost_center_code = 'UNASSIGNED'`, exposing `readiness.has_unassigned` and `readiness.unassigned_row_count`.
- **CSV Exporter:** Streams CSV including per-cost-center account breakdowns and currency summary tables.

### 3.3 `ReportPageOptions`
- Added methods `projects()`, `costCenters()`, and `accounts()`.
- Explicitly queries without active status filters so that historical/inactive projects and cost centers remain selectable and auditable.

---

## 4. Frontend Implementation

### 4.1 Pages
- `laravel/resources/js/Pages/Reports/ProjectProfitability.tsx`:
  - Uses `AppLayout`, `PageHeader`, `Card`, `MetricCard`, `EmptyState`, `SearchableSelect`, `DatePicker`, `Button`, `StatusBadge`, `tableClasses`, `formatMoney`, `getLocalizedName`.
  - Single-currency metric cards only when a single currency is in scope.
  - Multi-currency summary table when several currencies are in scope, avoiding any misleading combined money total.
  - Multi-currency warning banner when `has_mixed_currencies` is true.
  - Interactive filters with period override and Inertia client navigation (`preserveState: true, replace: true`).
  - Ledger traceability link per row leading to `/accounting/ledger?project_id=...`.
- `laravel/resources/js/Pages/Reports/CostCenterActuals.tsx`:
  - Uses standard primitives and design system.
  - Expandable/collapsible account-level breakdown per cost center row.
  - Summary by currency tracking debits, credits, and net movements.
  - Ledger traceability link per row leading to `/accounting/ledger?cost_center_id=...`.

### 4.2 Reports Hub (`resources/js/Pages/Reports/Index.tsx`)
- Added Project Profitability and Cost Center Actuals report cards under the financial reporting section guarded by `canViewFinancials`.

### 4.3 Internationalization (`en.json` & `ar.json`)
- Comprehensive English and Arabic dictionary trees under `app.pages.projectProfitabilityReport` and `app.pages.costCenterActualsReport`.
- 100% dictionary-backed labels, zero hardcoded visible strings in TSX components.
- Local review correction translated account type, account nature, and month labels in report selectors and breakdown rows.
- Local review correction tightened currency filter validation to a three-character registered currency code.

---

## 5. Architectural Invariants and Policy Compliance

1. **No Multi-Tenant Compliance:**
   - Zero references to `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, or Spatie Teams.
   - Proved by static source scan in automated test suite.
2. **No Schema / Migration Additions:**
   - Bounded read-only reporting slice; no migrations created or needed.
3. **Multi-Currency Segregation:**
   - No cross-currency addition into single money fields. Every calculation is separated per currency and isolated in `summary_by_currency`.
4. **Design System & Primitives:**
   - Zero native HTML `<select>` elements; uses `SearchableSelect`.
   - Zero native `<input type="date">` elements; uses `DatePicker`.
   - Zero `window.location.href`; uses Inertia router and standard anchor tags for CSV streams.

---

## 6. Verification Results

| Command | Status | Result |
|---|---|---|
| `php artisan migrate --force` | PASSED | Nothing to migrate (clean) |
| `php artisan test --filter=Phase16Slice4ProjectCostCenterReportsTest --compact` | PASSED | 14 passed (204 assertions) |
| `php artisan test --filter=Phase16Slice3ExpenseDimensionTest --compact` | PASSED | 11 passed (119 assertions) |
| `php artisan test --testsuite=Concurrency --compact` | PASSED | 7 passed (16 assertions) |
| `vendor/bin/pint --test` | PASSED | Code style clean |
| `npm.cmd run typecheck` | PASSED | 0 TypeScript errors |
| `npm.cmd run build` | PASSED | Vite build succeeded (0 errors) |
| `php artisan concurrency:stress --workers=100` | PASSED | 100 workers unique & contiguous |
| `php artisan accounting:concurrency-stress --workers=50` | PASSED | 50 sequential JV sequence unique & durable |
| `php artisan tokens:gc --batch=100` | PASSED | Token GC executed successfully |

Additional local review scans passed:

- `git diff --check`: clean except expected LF/CRLF warnings on Windows-managed markdown files.
- Phase 16 markdown control-character scan: clean.
- New Slice 4 TSX native-control scan: no native select, native date input, unsafe redirect, or loose pagination link type.
- New Slice 4 implementation scope scan: no company/tenant/current-context tokens.

---

## 7. Next Steps in Phase 16

- **Slice 5:** Budgeting Domain, Tables, and Management UI (Budget headers, lines, approval workflows).
- **Slice 6:** Budget vs Actual Reporting (Comparing budgeted amounts with GL actuals by Project and Cost Center).
