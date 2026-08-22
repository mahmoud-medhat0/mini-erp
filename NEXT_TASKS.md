# NEXT TASKS - Current Laravel Track

Current status: Phase 4 Slice 9 (Read-only Operational Reports & Returns Decision Pack) is complete and locally verified.

Do not use the old Next.js tenant/company-scope checklist as implementation guidance. The ERP is single-installation context unless a later owner decision explicitly defines otherwise.

## Completed

- M2 Laravel/Inertia foundation.
- M3 foundation schema, global RBAC, and no-team Spatie Permission.
- M5 Laravel session auth.
- M6 migrated app shell/pages.
- M7 Laravel core kernel parity.
- Phase 2 accounting core ledger spine.
- M8 page actions for migrated settings/users pages.
- M9 attachments and notifications services.
- M10 Spatie Activitylog audit backend, scheduler, and jobs baseline.
- Phase 3 Slices 1-10 Foundation: Master Data, AR/AP Subledgers, Receipts/Payments, Allocation Engine, Cheques, Bank Reconciliation, Inertia Pages/UX, Operational Reports, Concurrency Stress & Integrity, Close-Out Report.
- Phase 4 Slice 1 Product/Service Catalog Foundation.
- Phase 4 Slice 2 Sales Order Backend & UX.
- Phase 4 Slice 3 Purchase Order Backend & UX.
- Phase 4 Slice 4 Delivery Notes & Goods Receipts.
- Phase 4 Slice 5 Customer Invoice Posting.
- Phase 4 Slice 6 Supplier Bill Posting.
- Phase 4 Slice 7 Inventory Costing Decision Pack (Owner selected Option 1: Moving Weighted Average Costing).
- Phase 4 Slice 8 Moving Weighted Average Inventory Costing & Posting.
- Phase 4 Slice 9 Operational Reports & Returns Decision Pack:
  - 7 Read-only Query Services: `SalesOrderReportService`, `PurchaseOrderReportService`, `DeliveryNoteReportService`, `GoodsReceiptReportService`, `CustomerInvoiceReportService`, `SupplierBillReportService`, `StockMovementReportService`.
  - 7 HTTP Controllers under `App\Http\Controllers\Reports`.
  - 7 Inertia UI Pages (`SalesOrdersReport.tsx`, `PurchaseOrdersReport.tsx`, `DeliveryNotesReport.tsx`, `GoodsReceiptsReport.tsx`, `CustomerInvoicesReport.tsx`, `SupplierBillsReport.tsx`, `StockMovementsReport.tsx`).
  - Reports Hub (`Reports/Index.tsx`) links for all 7 new operational reports.
  - Owner decision pack `PHASE_4_RETURNS_CREDIT_DEBIT_DECISION.md`.
  - Feature test suite `Phase4Slice9OperationalReportsTest.php` (7/7 passing tests, 85 assertions after local schema-alignment correction).

Latest verified baseline:

```text
php artisan migrate --force: Nothing to migrate
php artisan migrate:status: all migrations Ran through 2026_08_22_090000_harden_phase4_slice8_inventory_integrity
php artisan test --filter=Phase4Slice9OperationalReportsTest: 7 tests / 85 assertions
php artisan test: 363 tests, 360 passed, 3 skipped / 2870 assertions
php artisan test --testsuite=Concurrency: 7 tests / 16 assertions
php artisan accounting:inventory-concurrency-stress --workers=50: PASSED CLEANLY
php artisan concurrency:stress --workers=10: PASSED CLEANLY
php artisan concurrency:stress --workers=100: BLOCKED LOCALLY by Windows paging-file VirtualAlloc exhaustion, not an application assertion failure
php artisan accounting:concurrency-stress --workers=50: PASSED CLEANLY
php artisan tokens:gc --batch=100: PASSED CLEANLY
vendor/bin/pint --test: passed
npm run typecheck: passed (0 TS errors)
npm run build: passed
Inventory backend forbidden float/rounding source scan: no results
```

## Next Execution

Proceed to Phase 4 Slice 10 only after the owner answers the open decisions in `PHASE_4_RETURNS_CREDIT_DEBIT_DECISION.md`.

Slice 10 expected scope after owner approval:

- implement the approved returns/credit/debit note model;
- preserve immutable posted ledgers and stock movement ledger behavior;
- do not invent VAT/tax, warehouse/location, or company/branch scope.

## Owner Decisions Still Needed

Do not implement these without explicit owner approval:

- VAT/tax workflow.
- FIFO, Standard Costing, or Non-Valued alternate inventory costing branches.
- Warehouse/location semantics.
- Warehouse-to-branch relationship.
- Landed cost and freight allocation.
- Post-confirmation sales order cancellation behavior once delivery/invoice exists.
- Post-confirmation purchase order cancellation behavior once goods receipt/bill exists.
- Price lists, discounts, and contract pricing.
- Separate quotation/requisition modules.
- Approval workflow engine beyond bounded status transitions.
- Credit limit blocking.
- Returns/credit notes/debit notes exact rules.

## Verification Gate

Run from `laravel/` for every Phase 4 slice:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan accounting:phase3-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Add slice-specific stress tests when a slice introduces concurrency-sensitive transitions or posting.
