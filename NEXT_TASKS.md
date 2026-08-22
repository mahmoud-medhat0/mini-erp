# NEXT TASKS - Current Laravel Track

Current status: Phase 4 Slice 7 (Inventory Costing Decision Pack) is complete, and the owner selected **Option 1: Moving Weighted Average Costing**. Phase 4 Slice 8 execution prompt is ready in `PHASE_4_SLICE_8_GEMINI_PROMPT.md`.

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
- Phase 4 Slice 5 Customer Invoice Posting:
  - `customer_invoice` and `customer_invoice_line` models/migrations.
  - `CustomerInvoiceService` lifecycle (`draft` -> `submitted` -> `approved` -> `posted` / `cancelled`).
  - Manual service/non-stock invoice lines.
  - Confirmed Sales Order and confirmed Delivery Note source lines.
  - Strict source mode validation: no source line without matching source header, no mixed Sales Order/Delivery Note sources, and product/UOM/unit price must match the source.
  - Integer quantity and minor-unit money math with overflow/fractional-minor rejection.
  - Cumulative over-invoicing prevention with deterministic source-line locks.
  - `INV-YYYY-XXXXX` global invoice numbering using `customer.invoice`.
  - `sales_revenue` accounting mapping.
  - PostingEngine integration: Dr AR Control / Cr Sales Revenue.
  - AR subledger `receivable_entry` debit creation.
  - Idempotent post replay.
  - Spatie Activitylog audit via `AuditLogger`.
  - Attachment entity registry registration for `customer_invoice`.
  - `CustomerInvoiceController` endpoints.
  - `CustomerInvoices.tsx` Inertia page.
  - `Phase4Slice5CustomerInvoiceTest` 19/19 passing tests (86 assertions) after local hardening.
- Phase 4 Slice 6 Supplier Bill Posting:
  - `supplier_bill` and `supplier_bill_line` models/migrations.
  - `SupplierBillService` lifecycle (`draft` -> `submitted` -> `approved` -> `posted` / `cancelled`).
  - Manual service/non-stock bill lines.
  - Confirmed Purchase Order and confirmed Goods Receipt source lines.
  - Strict source mode validation: no source line without matching source header, no mixed Purchase Order/Goods Receipt sources, and product/UOM/unit cost must match the source.
  - Integer quantity and minor-unit money math with overflow/fractional-minor rejection.
  - Cumulative over-billing prevention with deterministic source-line locks, including duplicate source-line protection inside one bill.
  - `BILL-YYYY-XXXXX` global bill numbering using `supplier.bill`.
  - `purchase_expense` accounting mapping and idempotent default seeding to account `5100`.
  - PostingEngine integration: Dr Purchase Expense / Cr AP Control.
  - AP subledger `payable_entry` credit creation.
  - Idempotent post replay.
  - Spatie Activitylog audit via `AuditLogger`.
  - Attachment entity registry registration for `supplier_bill`.
  - `SupplierBillController` endpoints.
  - `SupplierBills.tsx` Inertia page.
  - `Phase4Slice6SupplierBillTest` 16/16 passing tests (97 assertions).
- Phase 4 Slice 7 Inventory Costing Decision Pack:
  - Created `PHASE_4_INVENTORY_COSTING_DECISION.md`.
  - Owner selected Option 1: Moving Weighted Average Costing.
- Phase 4 Slice 8 Moving Weighted Average Inventory Costing & Posting:
  - Created `stock_balance` and `stock_movement_ledger` migrations and Eloquent models.
  - Extended `AccountingAccountMappingService` with `inventory_asset`, `grni_clearing`, and `cogs`.
  - Implemented `MovingWeightedAverageInventoryService` with exact integer valuation math, residual clearance, pessimistic balance locks (`lockForUpdate`), GL journal posting, and audit logging.
  - Integrated Goods Receipt confirmation to post Dr Inventory Asset / Cr GRNI Clearing.
  - Integrated Delivery Note confirmation to post Dr COGS / Cr Inventory Asset and prevent negative stock.
  - Integrated Supplier Bill posting to clear GRNI Clearing for stock lines sourced from Goods Receipts.
  - Integrated Customer Invoice posting for stock lines sourced from Delivery Notes.
  - Created read-only Inertia page `resources/js/Pages/Inventory/StockBalances.tsx`.
  - Implemented `Phase4Slice8InventoryCostingTest` (13/13 passing tests, 89 assertions).
  - Implemented `accounting:inventory-concurrency-stress --workers=50` command passing 100% cleanly.

Latest verified baseline:

```text
php artisan migrate --force: Nothing to migrate
php artisan migrate:status: all migrations Ran through 2026_08_22_080000_create_phase4_slice8_inventory_costing_tables
php artisan test --filter=Phase4Slice8InventoryCostingTest: 13 passing tests / 89 assertions
php artisan test: 355 tests, 353 passed, 2 skipped / 2761 assertions
php artisan accounting:inventory-concurrency-stress --workers=50: PASSED CLEANLY
vendor/bin/pint --test: passed
npm run typecheck: passed (0 TS errors)
npm run build: passed
Inventory backend forbidden float/rounding source scan: no results
```

## Next Execution

Proceed to Phase 4 Slice 9 (Sales & Purchasing Operational Reports & Returns/Credit/Debit Notes Decision Pack).

## Owner Decisions Still Needed

Do not implement these without explicit owner approval:

- VAT/tax workflow.
- FIFO, Standard Costing, or Non-Valued alternate inventory costing branches.
- Warehouse/location semantics.
- Warehouse-to-branch relationship.
- Stock-product invoicing/billing behavior.
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
