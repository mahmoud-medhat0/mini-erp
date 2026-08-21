# NEXT TASKS - Current Laravel Track

Current status: Phase 4 Slice 2 (Sales Order Backend & UX with exact integer totals) is complete and verified locally on PostgreSQL. Phase 4 Slice 3 (Purchase Order Backend & UX) is prompt-ready in `PHASE_4_SLICE_3_GEMINI_PROMPT.md`. See `PHASE_4_SALES_PURCHASING_OPERATIONS.md`.

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
- M10 audit, scheduler, and jobs baseline.
- Phase 3 Slices 1-10 Foundation (Master Data, AR/AP Subledgers, Receipts/Payments, Allocation Engine, Cheques, Bank Reconciliation, Inertia Pages/UX, Operational Reports, Concurrency Stress & Integrity, Close-Out Report).
- Phase 4 Slice 1 Product/Service Catalog Foundation.
- Phase 4 Slice 2 Sales Order Backend & UX:
  - `sales_order` and `sales_order_line` tables and Eloquent models.
  - `SalesOrderService` lifecycle (`draft` -> `submitted` -> `confirmed` / `cancelled`).
  - Exact integer math calculation helper (`calculateLineTotalMinor` using `intdiv` & `% 1000000`), 0 float/rounding usage.
  - Overflow checks and fractional-minor rejection validation.
  - Number sequence allocation `SO-YYYY-XXXXX` with idempotent confirmation replay.
  - Spatie Activitylog audit via `AuditLogger`.
  - Attachment entity registry registration for `sales_order`.
  - `SalesOrderController` endpoints under `/sales/orders/*`.
  - `SalesOrders.tsx` Inertia page with customer selector, product/UOM selector, dynamic line items, real-time line total preview, status badges, and action buttons.
  - `Phase4Slice2SalesOrderTest` 15/15 passing tests; local recheck after source-scan cleanup: 72 assertions.

Latest verified test suite baseline:

```text
php artisan test: 269 passing tests / 2221 assertions
Phase4Slice2SalesOrderTest: 15 passing tests / 72 assertions locally after source-scan cleanup
vendor/bin/pint --test: passed
npm run typecheck: passed (0 TS errors)
npm run build: passed
Source scan check: 0 forbidden float/rounding patterns in authoritative Sales Order backend code
```

## Immediate Priority - Phase 4 Slice 3 (Purchase Order Backend & Operations)

1. Execute `PHASE_4_SLICE_3_GEMINI_PROMPT.md`:
   - Create `purchase_order` and `purchase_order_line` tables.
   - Implement `PurchaseOrderService` (create, update, submit, confirm, cancel).
   - Exact integer math for line totals and header totals.
   - Document sequence allocation `PO-YYYY-XXXXX`.
   - RBAC permissions (`purchasing.*`).
   - Attachment registry entry for `purchase_order`.
   - Inertia controllers and React pages for Purchase Orders.
   - Feature test suite for Purchase Orders.

## Next Steps - Phase 4 Slices 4-10

- **Slice 4**: Delivery Notes & Goods Receipts (`delivery_note`, `goods_receipt`).
- **Slice 5**: Customer Invoice Posting (linking Sales Orders & Delivery Notes to AR/GL via PostingEngine).
- **Slice 6**: Supplier Bill Posting (linking Purchase Orders & Goods Receipts to AP/GL via PostingEngine).
- **Slice 7**: Inventory Costing Decision Slice (FIFO / Weighted Average after owner decision).
- **Slice 8**: Returns, Credit Notes, Debit Notes.
- **Slice 9**: Phase 4 Inertia UX Refinements.
- **Slice 10**: Reports, Concurrency Stress, Final Verification Gate for Phase 4.

## Owner Decisions Still Needed

Do not implement these without explicit owner approval:

- VAT/tax workflow.
- inventory costing method: weighted average, FIFO, standard cost, or non-valued/manual tracking.
- COGS posting.
- warehouse/location semantics.
- warehouse-to-branch relationship.
- post-confirmation sales order cancellation behavior once delivery/invoice exists.
- price lists, discounts, and contract pricing.
- separate quotation module.
- approval workflow engine beyond bounded status transitions.
- credit limit blocking.
- returns/credit notes/debit notes exact rules.

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
