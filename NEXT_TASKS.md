# NEXT TASKS - Current Laravel Track

Current status: Phase 4 Slice 3 (Purchase Order Backend & UX with exact integer totals) is complete and verified locally on PostgreSQL. Phase 4 Slice 4 (Delivery Notes & Goods Receipts Operational Foundation) is prompt-ready in `PHASE_4_SLICE_4_GEMINI_PROMPT.md`.

Do not use the old Next.js tenant/company-scope checklist as implementation guidance. The ERP is single-installation context unless a later owner decision explicitly defines otherwise.

## Completed

- M2 Laravel/Inertia foundation.
- M3 foundation schema, global RBAC, and no-team Spatie Permission.
- M5 Laravel session auth.
- M6 migrated app shell/pages.
- M7 core kernel parity.
- Phase 2 accounting core ledger spine.
- M8 actions for migrated settings/users pages.
- M9 attachments and notifications services.
- M10 audit, scheduler, and jobs baseline.
- Phase 3 Slices 1-10:
  - master data
  - AR/AP subledgers
  - receipts/payments
  - allocation engine
  - cheques
  - bank reconciliation
  - Inertia pages/UX
  - operational reports
  - PostgreSQL stress/integrity hardening
  - close-out report
- Phase 4 Slice 1 Product/Service Catalog Foundation.
- Phase 4 Slice 2 Sales Order Backend & UX with exact integer totals.
- Phase 4 Slice 3 Purchase Order Backend & UX:
  - `purchase_order` and `purchase_order_line` tables and Eloquent models.
  - `PurchaseOrderService` lifecycle (`draft` -> `submitted` -> `confirmed` / `cancelled`).
  - Exact integer calculation helper using `intdiv` and `% 1000000`.
  - Overflow checks and fractional-minor rejection validation.
  - Number sequence allocation `PO-YYYY-XXXXX` with idempotent confirmation replay.
  - Spatie Activitylog audit via `AuditLogger`.
  - Attachment entity registry registration for `purchase_order`.
  - `PurchaseOrderController` endpoints under `/purchasing/orders/*`.
  - `PurchaseOrders.tsx` Inertia page.
  - `Phase4Slice3PurchaseOrderTest` 16/16 passing tests (74 assertions).

Latest reported baseline after Slice 3:

```text
php artisan test: 285 passing tests / 2311 assertions
Phase4Slice3PurchaseOrderTest: 16 passing tests / 74 assertions
php artisan test --testsuite=Concurrency: 7 passing tests / 16 assertions
vendor/bin/pint --test: passed
npm run typecheck: passed (0 TS errors)
npm run build: passed
Source scan check: 0 forbidden float/rounding patterns in authoritative Purchase Order backend code after local false-positive cleanup
```

## Immediate Priority

Execute:

- `PHASE_4_SLICE_4_GEMINI_PROMPT.md`

Scope:

- Delivery Notes from confirmed Sales Orders.
- Goods Receipts from confirmed Purchase Orders.
- `delivery_note`, `delivery_note_line`, `goods_receipt`, and `goods_receipt_line`.
- Operational fulfillment quantities only.
- Exact integer `quantity_e6` validation.
- Cumulative over-delivery and over-receipt prevention.
- `DN-YYYY-XXXXX` and `GRN-YYYY-XXXXX` global numbering.
- Spatie Activitylog via `AuditLogger`.
- Attachment registry entries.
- Inertia pages and actions.

Explicitly out of scope for Slice 4:

- customer invoices
- supplier bills
- AR/AP/GL posting
- PostingEngine integration
- inventory valuation
- stock balance ledger
- COGS
- VAT/tax
- discounts/price lists
- returns/credit notes/debit notes
- warehouse/location/branch semantics
- company/branch/tenant scope
- reports

## Upcoming Phase 4 Slices

- **Slice 5:** Customer Invoice posting to AR/GL through the existing PostingEngine.
- **Slice 6:** Supplier Bill posting to AP/GL through the existing PostingEngine.
- **Slice 7:** Inventory costing/subledger only after owner decision on costing method.
- **Slice 8:** Returns, Credit Notes, and Debit Notes only after owner decision.
- **Slice 9:** Phase 4 Inertia pages/actions polish for workflows that are already stable.
- **Slice 10:** Phase 4 reports, PostgreSQL stress/integrity hardening, documentation close-out, and final verification.

## Owner Decisions Still Needed

Do not implement these without explicit owner approval:

- VAT/tax workflow.
- inventory costing method: weighted average, FIFO, standard cost, or non-valued/manual tracking.
- COGS posting.
- warehouse/location semantics.
- warehouse-to-branch relationship.
- post-confirmation sales order cancellation behavior once delivery/invoice exists.
- post-confirmation purchase order cancellation behavior once goods receipt/bill exists.
- price lists, discounts, and contract pricing.
- separate quotation/requisition modules.
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
