# Phase 21 Slice 1 — VAT Register Server-Side Pagination (COMPLETE)

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, `company_id`, `tenant_id`, Spatie Teams scope, or blanket `branch_id` scope. See root `NO_MULTI_TENANT_POLICY.md`.

**Status:** 100% COMPLETE & VERIFIED (2026-09-01)
**Track:** Yajra DataTables server-side pagination rollout for heavy report surfaces.

---

## 1. Why This Slice Existed

`VatRegisterDataTableService` and `VatRegisterDataTableRequest` already existed in the
repository, fully written and matching the six sibling report DataTable services. They were
unreachable: no controller, no route, no frontend consumer.

Meanwhile `/reports/vat-register` still loaded **every** matching VAT line into the Inertia
payload through `VatRegisterReportService::generate()`. Over a wide date range this grows
without bound — a genuine performance and memory problem on the exact page an accountant
opens at every filing cycle.

This slice closed the wiring gap only. No VAT math, sign rules, or accounting behavior changed.

---

## 2. What Was Implemented

### 2.1 Backend

**Created `app/Http/Controllers/Reports/VatRegisterDataTableController.php`**

Single-action invokable controller matching `ChequeRegisterDataTableController` exactly:

```php
public function __invoke(VatRegisterDataTableRequest $request): JsonResponse
{
    Gate::authorize('reports.view');
    Gate::authorize('view_financials');

    return $this->service->data($request->reportFilters());
}
```

**Registered route in `routes/web.php`**

```php
Route::get('/vat-register/data', VatRegisterDataTableController::class)
    ->name('reports.vat-register.data');
```

Placed between `reports.vat-register` and `reports.vat-register.export`, matching the
`<report>` / `<report>/data` / `<report>/export` ordering used by every other report.
The `use` statement was inserted alphabetically before `VatReportController`.

**Changed `app/Application/Reports/VatReportPageData.php`**

`register()` now composes the page from `VatRegisterDataTableService::summary()` instead of
`VatRegisterReportService::generate()`. The Inertia payload carries filters, tax codes,
currency, and SQL-computed summary totals — **but no row array**. This mirrors how
`ChequeRegisterReportController::index()` was already built.

`VatRegisterReportService` is intentionally left untouched and still used by
`VatCsvReportExporter::register()`, so **CSV export remains complete and is never limited
to one DataTable page**.

### 2.2 Frontend

**Created `resources/js/Components/VatRegisterDataTable.tsx`**

Wraps the shared `ServerDataTable`, following `ChequeRegisterDataTable.tsx`. Preserves the
previous visual language exactly: monospace document dates and numbers, coloured
output/input category pills, red negative tax amounts, and the `code (rate%)` tax display.
All nine columns are server-ordered; monetary columns are `searchable: false`.

**Updated `resources/js/Pages/Reports/VatRegister.tsx`**

- Removed the `VatRegisterRow` type and the `rows` prop.
- Replaced the 51-line hand-rolled `<table>` with `<VatRegisterDataTable />`.
- Removed the now-unused `EmptyState`, `tableClasses`, `thRightClass`, `tdRightClass`.
- Summary cards, filters, permissions, print, and export are unchanged.
- A `key` built from the active filters forces a clean table reload when filters change.

### 2.3 Localization

Visible strings stay dictionary-backed. Existing `app.accounting.entity_*` labels are reused
for document type names rather than duplicating them under the tax namespace.

Added to **both** `en.json` and `ar.json`:

| Key | EN | AR |
|---|---|---|
| `app.accounting.entity_rental_invoice` | Rental Invoice | فاتورة تأجير |
| `app.taxes.vatRegister.outputCategory` | OUTPUT | مخرجات |
| `app.taxes.vatRegister.inputCategory` | INPUT | مدخلات |

`entity_rental_invoice` was a real gap: the VAT register unions `rental_invoice` rows, which
would otherwise have rendered a raw snake_case string.

Locale diff was verified minimal — 4 added lines per file, no reordering or reformatting.

---

## 3. Tests Added

### 3.1 `tests/Feature/VatRegisterDataTableTest.php` (new, 8 tests / 51 assertions)

Built on the `ChequeRegisterDataTableTest` pattern and using **real posting services**
(`CustomerInvoiceService`, `CustomerCreditNoteService`, `SupplierBillService`) so the
assertions exercise genuine posted VAT data, not hand-inserted rows.

| Test | Guards |
|---|---|
| `test_endpoint_unions_output_and_input_documents_with_signed_amounts` | Output and input rows union correctly; 14% VAT arithmetic is exact |
| `test_credit_note_rows_are_negative_and_summary_matches_datatable_rows` | Credit notes are negative; SQL summary equals row-level truth (840 / 2800 / −1960) |
| `test_type_filter_restricts_rows_to_the_requested_tax_category` | `type=output` / `type=input` isolation |
| `test_date_range_filter_excludes_documents_outside_the_period` | Period boundaries |
| `test_search_is_case_insensitive_across_document_and_entity_columns` | `recordsFiltered` vs `recordsTotal` semantics |
| `test_server_side_pagination_limits_the_returned_rows` | 6 records, offset 4 → 2 rows returned |
| `test_endpoint_enforces_permissions_and_rejects_malformed_datatables_payload` | 403 for outsiders; rejects bad type, non-UUID tax code, reversed dates, oversized length, 151-char search, SQL-injection column name, out-of-range order index |
| `test_csv_export_remains_complete_and_is_not_limited_to_a_datatable_page` | 12 invoices → all 12 in CSV |

### 3.2 `tests/Feature/ReportInputHardeningTest.php` (extended)

Two cross-cutting guards so **no** DataTable endpoint can regress:

- `test_datatable_endpoints_require_financial_report_permissions` — every endpoint returns 403 without `reports.view` + `view_financials`.
- `test_datatable_endpoints_reject_unsupported_page_lengths` — every endpoint rejects `length=999`.

Both iterate a shared `dataTableEndpoints()` list (AR aging, AP aging, cheque register, VAT
register). **Add new DataTable endpoints to that list.**

---

## 4. Verification Performed

| Command | Result |
|---|---|
| `php artisan route:list --path=reports/vat` | 7 routes; `reports.vat-register.data` present |
| `php artisan test --filter=VatRegisterDataTableTest` | 8 passed / 51 assertions |
| `php artisan test --filter="VatRegisterDataTableTest\|Phase7Slice5VatReportsTest"` | 17 passed / 95 assertions |
| `php artisan test --filter=ReportInputHardeningTest` | 6 passed / 119 assertions |
| `php artisan test --filter="ReportInputHardeningTest\|SecurityHardeningTest\|Phase8Slice4RouteSmokeTest"` | 45 passed / 1140 assertions |
| `npx tsc --noEmit` | 0 errors |
| `node -e` locale JSON parse | valid |
| `git diff --numstat resources/js/locales/` | +4 / −1 per file |

`Phase7Slice5VatReportsTest` passing is the important one: it still exercises
`VatRegisterReportService` directly, proving the untouched service and the CSV path that
depends on it both still behave.

---

## 5. Invariants Preserved

- No tenant/company scope, no branch security scope.
- No float money math — all amounts stay integer minor units end to end.
- VAT sign rules unchanged (credit notes, sales returns, purchase returns stay negative).
- Posted-only filtering unchanged.
- CSV export still emits every row.
- Endpoint authorization is `reports.view` + `view_financials`, matching sibling reports.
- Visible UI text is dictionary-backed in EN/AR.

---

## 6. Follow-On Work

`RentalOperationsReport` is the one remaining report page that loads an unbounded row set —
see `PHASE_21_SLICE_2_AGY_PROMPT.md`.

Deliberately **not** converted, because their row counts are bounded by design (`groupBy`
over branches / cost centers / projects):

- `BranchOperations`
- `BranchProfitability`
- `CostCenterActuals`
- `ProjectProfitability`

Converting these would add machinery without solving a real problem.
