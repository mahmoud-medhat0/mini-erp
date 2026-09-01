# Phase 21 - Report Server-Side Pagination (Yajra DataTables)

> Performance and scalability hardening for accountant-facing report surfaces. Deployment execution remains parked until the owner explicitly resumes cutover work.

## Status

COMPLETE - 2026-09-01. Slices 1-2 are 100% complete and verified.

## Purpose

Phase 21 finishes the Yajra DataTables server-side pagination rollout across report pages
that still load an unbounded row set into the Inertia payload.

The DataTables infrastructure already exists and is proven: `ServerDataTable.tsx`, seven
report DataTable services, their request classes, controllers, and routes. This phase does
not introduce a new pattern — it closes the remaining gaps in an established one.

This phase must not add a new ERP business module, and must not change any reported number.
Every slice is a pure delivery-mechanism change behind identical figures.

## Non-Negotiable Rules

- No multi-tenant architecture.
- No Company-as-tenant semantics.
- No `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, or Spatie Teams.
- Branch remains an operational/reporting filter only. Never a security boundary or scope.
- No float money math. Integer minor units end to end.
- No deployment/server cutover work in Phase 21.
- No hardcoded visible text in React pages. Use `resources/js/locales/en.json` and `ar.json`.
- Controllers stay thin. Query and composition work belongs in services and request classes.
- DataTable endpoints authorize server-side with `reports.view` + `view_financials`, matching
  the page route they serve. Never widen access.
- CSV exports must keep exporting every row and must never be limited to one DataTable page.
- Reported figures must not change. Parity against the existing service is the acceptance
  criterion for any conversion slice.

## Slice Plan

| Slice | File | Status | Goal |
|---|---|---|---|
| 1 | `PHASE_21_SLICE_1_REPORT.md` | COMPLETE | VAT Register server-side pagination; wired the orphaned `VatRegisterDataTableService`, added cross-cutting DataTable endpoint guards. |
| 2 | `PHASE_21_SLICE_2_AGY_PROMPT.md` | COMPLETE | Rental Operations Report server-side pagination; the last unbounded report row set (`PHASE_21_SLICE_2_REPORT.md`). |

## Deliberately Out Of Scope

These report pages render row sets bounded by design (`groupBy` over branches, cost centers,
or projects). Their row counts do not grow with transaction volume, so pagination would add
machinery without solving a problem:

- `BranchOperations`
- `BranchProfitability`
- `CostCenterActuals`
- `ProjectProfitability`

Also out of scope: converting ordinary Inertia-paginated operational list pages (sales
orders, invoices, and similar). Those already paginate server-side through Laravel and are
not part of this phase.

## Required Close-Out Evidence

Each implemented slice must provide:

- A `PHASE_21_SLICE_N_REPORT.md` describing what changed and why.
- Feature test coverage for the new endpoint: authorization, input validation rejection,
  filtering, search, ordering, and server-side pagination.
- A parity assertion proving reported figures are unchanged.
- A CSV completeness assertion proving export is not limited to one page.
- Real verification output. A command counts as passed only after it exits successfully.

## Shared Regression Guard

`tests/Feature/ReportInputHardeningTest.php` holds two cross-cutting guards driven by a
single `dataTableEndpoints()` list:

- `test_datatable_endpoints_require_financial_report_permissions`
- `test_datatable_endpoints_reject_unsupported_page_lengths`

**Every new DataTable endpoint must be added to that list.**
