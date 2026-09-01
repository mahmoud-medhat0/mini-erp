# Phase 21 Slice 2 — Rental Operations Report Server-Side Pagination

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, `company_id`, `tenant_id`, Spatie Teams scope, or blanket `branch_id` scope. See root `NO_MULTI_TENANT_POLICY.md`.

**Status:** PREPARED — NOT IMPLEMENTED
**Prerequisite:** `PHASE_21_SLICE_1_REPORT.md` (COMPLETE)

---

## 1. Goal

`/reports/rentals` is the last report page that loads an unbounded row set into the Inertia
payload. Give it Yajra server-side pagination, search, and ordering — matching the seven
report DataTables already in the codebase — **without changing a single reported number.**

---

## 2. Read This Before Writing Any Code

This slice is materially harder than Slice 1. In Slice 1 the DataTable service already
existed and only needed wiring. Here **nothing exists**, and the current implementation is
not a straightforward query.

`app/Application/Reports/RentalOperationsReportService::generate()` currently:

1. Loads **all** matching `RentalContract` rows via `->get()` — no limit.
2. Eager-loads deep nested relations, including `returns.lines.invoiceLines.invoice`,
   `invoices.lines`, `handovers`, `lines`, `customer`, `branch`, `invoices.journalEntry`.
3. Maps each contract through `contractRow()`, which computes **35 fields in PHP**, several
   requiring traversal of those nested collections.
4. Derives `summary()` (15 aggregates) and `readiness()` (5 booleans) **from the mapped rows**.

The unbounded `->get()` plus deep eager-loading is the actual performance defect. It degrades
as rental history accumulates.

### 2.1 The Fields That Make This Hard

Most fields map cleanly to SQL. These do not — study them before designing:

| Field | Why it resists a naive translation |
|---|---|
| `pending_damage_minor` | Per return line: `max(0, estimated_damage_charge_minor − billed)`, where `billed` sums only `damage_charge` invoice lines whose parent invoice is **not** cancelled. The `max(0, …)` is per line and must **not** be applied after summing. |
| `unbilled_line_count` | Depends on `unbilledContractLineCount()` — read the current body and reproduce its predicate exactly. |
| `returned_line_count` | `DISTINCT rental_contract_line_id` across **completed** returns only. |
| `open_item_count` | `max(0, line_count − returned_line_count)` — again per row, not post-aggregate. |
| `due_state` | Status-first branching (`cancelled` → `completed` → non-active → `not_active`), then date comparison against `asOfDate` / `endingSoonDate`. Must be sortable and filterable server-side. |
| `latest_journal_number` | `$postedInvoices->first()?->journalEntry?->number` — reproduce the **existing** ordering; do not silently "improve" it to newest-first. |
| `customer_name` / `branch_name` | Spatie translatable JSON. Sibling services pass these through and let the frontend localize (see `translatableName()` in `VatRegisterDataTableService`). |

Cancelled invoices are excluded from `activeInvoices` throughout. Every aggregate must honour
that. Getting this wrong silently corrupts financial figures on an accountant-facing page.

---

## 3. Required Changes

### 3.1 `app/Application/Reports/RentalOperationsDataTableService.php` (new)

Model it on `VatRegisterDataTableService`:

- `data(array $filters): JsonResponse` — the Yajra endpoint.
- `summary(array $filters): array` — SQL aggregates for cards + `readiness`, computed via
  `DB::query()->fromSub(...)` over the same base query so **cards and rows can never disagree**.

Both must share one private query builder. Use `DB::table('rental_contract as ...')` with
explicit joins / correlated subqueries, integer minor units only, and no float math.

Honour every existing filter: `branch_id`, `customer_id`, `status`, `currency`, `date_from`
(`expected_end_date >=`), `date_to` (`start_date <=`), and `search` across contract number,
reference, and customer code/name (EN + AR JSON paths). Preserve the existing default
ordering: `expected_end_date`, then `number`.

### 3.2 `app/Http/Requests/Reports/RentalOperationsDataTableRequest.php` (new)

Copy the shape of `VatRegisterDataTableRequest` exactly — including `allowedColumns()`,
the `Rule::in` column allowlist, `length` restricted to `in:10,25,50,100`, `search.value`
capped at 150 chars, `order.*.column` bounds, and the `withValidator` order-index check.
This request class is the injection boundary; do not weaken it.

Add `reportFilters()` returning the normalized filter array.

### 3.3 `app/Http/Controllers/Reports/RentalOperationsDataTableController.php` (new)

Invokable, ~20 lines, identical in shape to `VatRegisterDataTableController`:

```php
Gate::authorize('reports.view');
Gate::authorize('view_financials');

return $this->service->data($request->reportFilters());
```

Check the existing `/reports/rentals` route for an additional rentals-specific permission
and mirror it if present — do not silently widen access.

### 3.4 `routes/web.php`

```php
Route::get('/rentals/data', RentalOperationsDataTableController::class)
    ->name('reports.rentals.data');
```

Place it directly after `reports.rentals` and before the export route. Insert the `use`
statement alphabetically.

### 3.5 `app/Http/Controllers/Reports/RentalOperationsReportController.php`

Switch `index()` to `RentalOperationsDataTableService::summary()`. The Inertia payload must
keep `summary`, `readiness`, `currency_codes`, `single_currency`, `display_currency`,
`base_currency`, `as_of_date`, `ending_soon_date`, filters, and selector options — and must
**drop `rows`**.

Leave `RentalOperationsReportService` in place: the CSV exporter uses it, and CSV must keep
exporting every row. Confirm this by reading the exporter before you touch anything.

### 3.6 `resources/js/Components/RentalOperationsDataTable.tsx` (new)

Wrap `ServerDataTable`, following `VatRegisterDataTable.tsx`. Reproduce the existing column
set, badges, and money formatting so the page looks unchanged. Take labels through props;
put no visible English or Arabic literals in the component.

### 3.7 `resources/js/Pages/Reports/RentalOperationsReport.tsx`

Remove the row type and the `rows` prop, replace the manual `<table>` with the new component,
delete any now-unused imports and helpers, and key the table on the active filters. Keep the
summary cards and readiness banners exactly as they are.

### 3.8 Localization

Reuse existing dictionary keys wherever they exist. Add EN **and** AR entries for anything
genuinely new. Verify both files parse and that `git diff --numstat resources/js/locales/`
shows only your additions — no reordering, no reindentation.

---

## 4. Tests

### 4.1 `tests/Feature/RentalOperationsDataTableTest.php` (new)

Follow `VatRegisterDataTableTest`. Build fixtures through the **real** services
(`RentalContractService`, `RentalFulfillmentService`, `RentalInvoiceService`) so the
assertions run against genuinely posted data.

Required coverage:

1. Rows carry correct contract/customer/branch identity and status.
2. **Parity test — the important one.** For a fixture with several contracts, invoices,
   completed returns, pending damage, and at least one cancelled invoice, assert the new
   service's row values and summary equal `RentalOperationsReportService::generate()`
   field-for-field. This is what proves no number changed.
3. Each filter (`branch_id`, `customer_id`, `status`, `currency`, `date_from`, `date_to`)
   narrows correctly.
4. Search is case-insensitive across contract number, reference, and customer code/name.
5. `due_state` is correct for overdue, ending-soon, active, completed, and cancelled.
6. Server-side pagination returns only the requested page while `recordsTotal` stays full.
7. Cancelled invoices are excluded from every billed aggregate.
8. `pending_damage_minor` never goes negative when billed damage exceeds the estimate.
9. Permissions: 403 without `reports.view` + `view_financials`.
10. Malformed payload rejected: bad UUIDs, reversed dates, `length=999`, 151-char search,
    injection-style column name, out-of-range order index.
11. CSV export still contains every row, not just one page.

### 4.2 `tests/Feature/ReportInputHardeningTest.php`

Add `/reports/rentals/data` to `dataTableEndpoints()`. The two shared guards then cover it
automatically.

---

## 5. Verification Gate

Run from `laravel/` and report only what actually exited successfully:

```powershell
php artisan route:list --path=reports/rentals
php artisan test --filter=RentalOperationsDataTableTest --stop-on-failure
php artisan test --filter=Phase14 --stop-on-failure
php artisan test --filter=ReportInputHardeningTest --stop-on-failure
php artisan test --filter=SecurityHardeningTest --stop-on-failure
php artisan test --filter=Phase8Slice4RouteSmokeTest --stop-on-failure
vendor/bin/pint --test
npx tsc --noEmit
npm run build
node -e "JSON.parse(require('fs').readFileSync('resources/js/locales/en.json','utf8'));JSON.parse(require('fs').readFileSync('resources/js/locales/ar.json','utf8'))"
git diff --numstat resources/js/locales/
```

`Phase14RentalReportsCloseOutTest` must still pass. If it asserts on `rows` in the Inertia
payload, that assertion needs updating to match the DataTable contract — update it
deliberately and say so; do not delete coverage to make a suite go green.

---

## 6. Review Gate

- A scan is clean only when it prints zero matches.
- A command counts as passed only after it exits successfully. No "will pass" claims.
- No float money math; integer minor units end to end.
- No tenant/company scope; `branch_id` stays an operational filter only.
- No new hardcoded visible strings in TSX — EN/AR dictionaries only.
- Endpoint authorization must match the existing page route, not be widened.
- CSV export must stay complete.
- If the parity test cannot be made to pass, **stop and report the discrepancy** rather than
  adjusting the expected values to match the new code.

---

## 7. Out Of Scope

Do not convert these — their row counts are bounded by `groupBy` over branches, cost centers,
or projects, so pagination adds machinery without solving a problem:

- `BranchOperations`
- `BranchProfitability`
- `CostCenterActuals`
- `ProjectProfitability`

Also out of scope: rental business-logic changes, new rental features, deployment/cutover
work, and touching `RentalOperationsReportService`'s calculations.
