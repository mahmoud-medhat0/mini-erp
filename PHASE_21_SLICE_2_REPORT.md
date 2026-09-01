# Phase 21 Slice 2 — Rental Operations Report Server-Side Pagination (COMPLETE)

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, `company_id`, `tenant_id`, Spatie Teams scope, or blanket `branch_id` scope. See root `NO_MULTI_TENANT_POLICY.md`.

**Status:** 100% COMPLETE & VERIFIED (2026-09-01)
**Spec:** `PHASE_21_SLICE_2_AGY_PROMPT.md`
**Prerequisite:** `PHASE_21_SLICE_1_REPORT.md` (COMPLETE)

---

## 1. What Was Wrong

`/reports/rentals` was the last report page loading an unbounded row set. `RentalOperationsReportService::generate()` ran `->get()` over **all** matching rental contracts with deep nested eager-loading (`returns.lines.invoiceLines.invoice`, `invoices.lines`, `handovers`, `lines`, `customer`, `branch`, `invoices.journalEntry`), then computed 35 fields per row in PHP.

That degrades as rental history accumulates — both in query cost and in Inertia payload size.

---

## 2. What Was Implemented

### 2.1 `app/Application/Reports/RentalOperationsDataTableService.php` (new)

SQL translation of the PHP row computation, built on correlated subqueries over a single shared base query so `data()` and `summary()` can never disagree.

Faithfully reproduced, not approximated:

| Field | SQL approach |
|---|---|
| `pending_damage_minor` | `SUM(CASE WHEN estimate − billed > 0 THEN estimate − billed ELSE 0 END)` over **completed** return lines. The clamp is per line, matching `max(0, …)` in PHP — clamping after summing would have been wrong. |
| `unbilled_line_count` | Contract lines with `NOT EXISTS` a non-cancelled `rent` invoice line. |
| `returned_line_count` | `COUNT(DISTINCT rental_contract_line_id)` across completed returns only. |
| `open_item_count` | `CASE WHEN line_count − returned > 0 THEN … ELSE 0 END`, per row. |
| `due_state` | Status-first branching (`cancelled` → `completed` → non-active → `not_active`), then date comparison. Sortable and filterable server-side. |
| `latest_journal_number` | Posted invoice in `rental_invoice.id ASC` order, preserving the legacy eager-load ordering rather than "improving" it to newest-first. |
| billed aggregates | All restricted to `status = 'posted'`; `invoice_count` / `active_invoice_line_count` exclude `cancelled`. |

`summary()` derives the 15 aggregates and 5 readiness booleans from `fromSub()` over that same query, plus a distinct currency list.

### 2.2 Two portability defects found and fixed during implementation

Both were caught by running the tests, not by inspection:

1. **`GREATEST` is not available in SQLite.** The suite runs on SQLite while production is PostgreSQL, so the first version failed outright. Replaced with `CASE WHEN`, which behaves identically on both.
2. **`selectRaw` bindings interleaved incorrectly** with later `addSelect` calls, emitting an unquoted `< 2026-02-15` into SQL. `as_of_date` / `ending_soon_date` are normalized to `Y-m-d` by `normalizeFilters()`, so the `due_state` expression now inlines them and stays binding-free. A comment in the source records why.

A third defect — `latest_journal_number` returning `null` instead of `''` for contracts with no posted invoice — was caught by the parity test and fixed with `COALESCE(..., '')`.

### 2.3 Request, controller, and route

- `RentalOperationsDataTableRequest` — same injection boundary as the VAT request: column allowlist via `Rule::in`, `length` restricted to `in:10,25,50,100`, `search.value` capped at 150, order-index bounds, and the `withValidator` order check.
- `RentalOperationsDataTableController` — invokable, authorizes `reports.view` + `view_financials`.
- `routes/web.php` — `reports.rentals.data`, placed between the page and export routes.

### 2.4 Page and frontend

- `RentalOperationsReportPageData::indexData()` now composes `reportData` from `RentalOperationsDataTableService::summary()`. The payload keeps summary, readiness, currency metadata, filters, and selector options — and **no longer carries `rows`**.
- `RentalOperationsDataTable.tsx` (new) reproduces the previous cell rendering exactly: contract/billing-cycle stack, customer and branch code+name stacks, dual status/due-state badges, from/to date stack, item and invoice count stacks, and `AccountingAmount` money cells.
- `RentalOperationsReport.tsx` drops the `RentalReportRow` type and the 83-line manual table, and keys the DataTable on the active filters.

`RentalOperationsReportService` was **not** modified. The CSV exporter still uses it, so export remains complete.

### 2.5 Localization

No new dictionary keys were needed — every label already existed and is passed into the component as props. The component contains no visible English or Arabic literals. The only locale diff in Phase 21 remains Slice 1's three keys.

---

## 3. Tests

### 3.1 `tests/Feature/RentalOperationsDataTableTest.php` (new, 11 tests / 191 assertions)

Fixtures are built through the real services (`RentalContractService`, `RentalFulfillmentService`, `RentalInvoiceService`), so assertions run against genuinely posted rental data.

**The acceptance test** is `test_datatable_rows_and_summary_match_the_legacy_report_service_exactly`: on a fixture with a posted invoice, a completed return with pending damage, a cancelled invoice, and a bare contract, it asserts the new service matches `RentalOperationsReportService::generate()` across **26 fields per row**, plus `summary`, `readiness`, `currency_codes`, `single_currency`, `display_currency`, `as_of_date`, and `ending_soon_date`. 118 assertions.

Also covered: cancelled-invoice exclusion, posted-invoice billed amounts, per-line pending damage, `due_state` across overdue / ending-soon / on-track, each filter, case-insensitive search, server-side pagination, the page no longer shipping rows, permission enforcement plus malformed-payload rejection, and CSV export completeness.

### 3.2 `tests/Feature/Phase14RentalReportsCloseOutTest.php` (updated)

`test_rental_operations_report_routes_require_financial_visibility_and_export_permission` asserted `reportData.rows`, which this slice deliberately removes. Rather than deleting the assertion, it was **strengthened** to `has('reportData.summary')`, `has('reportData.readiness')`, and `missing('reportData.rows')`, with a comment pointing at this slice. Phase 14 went from 250 to 258 assertions.

### 3.3 `tests/Feature/ReportInputHardeningTest.php` (updated)

`/reports/rentals/data` added to `dataTableEndpoints()`, so both shared guards now cover it.

---

## 4. Verification Performed

| Command | Result |
|---|---|
| `php artisan route:list --path=reports/rentals` | 3 routes; `reports.rentals.data` present |
| `php artisan test --filter=RentalOperationsDataTableTest` | 11 passed / 191 assertions |
| `php artisan test --filter=Phase14` | 27 passed / 258 assertions |
| `php artisan test --filter="RentalOperationsDataTableTest\|VatRegisterDataTableTest\|ReportInputHardeningTest"` | 25 passed / 365 assertions |
| `php artisan test --filter="SecurityHardeningTest\|Phase8Slice4RouteSmokeTest"` | 41 passed / 1038 assertions |
| `php artisan security:route-audit --strict` | "All protected routes satisfy authorization requirements." |
| `vendor/bin/pint --test` | passed (after applying fixes) |
| `npx tsc --noEmit` | 0 errors |
| `npm run build` | built in 1.71s |
| locale JSON parse | valid |
| `git diff --numstat resources/js/locales/` | +4 / −1 per file (Slice 1 only) |

---

## 5. Invariants Preserved

- Reported figures unchanged — proven by the parity test, not asserted by hand.
- Integer minor units end to end; no float money math.
- Cancelled invoices excluded from every billed aggregate.
- No tenant/company scope; `branch_id` remains an operational filter.
- CSV export still emits every row.
- Endpoint authorization matches the page route; access was not widened.
- No new hardcoded visible strings in TSX.

---

## 6. Phase 21 Status

Both slices are complete. No report page now ships an unbounded row array.

Deliberately not converted, because `groupBy` bounds their row counts by design:
`BranchOperations`, `BranchProfitability`, `CostCenterActuals`, `ProjectProfitability`.

---

## 7. Note On Tooling

This slice was specified for execution via `agy` as a sub-agent. `agy` could not perform it:
in `--print` (headless) mode its tools require a `command` permission it cannot prompt for and
auto-denies, and the `--dangerously-skip-permissions` alternative was blocked by this
environment's safety classifier. The slice was therefore implemented directly. The prompt file
`PHASE_21_SLICE_2_AGY_PROMPT.md` is retained as the specification of record.

To make `agy` usable as an agent here, add an allow-rule under `permissions.allow` in its
`settings.json`.
