# NEXT TASKS - Current Laravel Track

Current status: Phase 4 Slice 4 (Delivery Notes & Goods Receipts Operational Foundation) is complete and verified locally on PostgreSQL. Phase 4 Slice 5 (Customer Invoice Posting to AR/GL) is planned. See `PHASE_4_SALES_PURCHASING_OPERATIONS.md`.

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
- Phase 4 Slice 2 Sales Order Backend & UX.
- Phase 4 Slice 3 Purchase Order Backend & UX.
- Phase 4 Slice 4 Delivery Notes & Goods Receipts:
  - `delivery_note`, `delivery_note_line`, `goods_receipt`, `goods_receipt_line` models/migrations.
  - `DeliveryNoteService` & `GoodsReceiptService` lifecycle (`draft` -> `confirmed` / `cancelled`).
  - Integer quantity validation (`quantity_e6 = 1000000 = 1.000000`).
  - Cumulative over-delivery and over-receipt prevention with deterministic row locks.
  - Number sequence allocation `DN-YYYY-XXXXX` and `GRN-YYYY-XXXXX` with idempotent confirmation replay.
  - Spatie Activitylog audit via `AuditLogger`.
  - Attachment entity registry registration for `delivery_note` and `goods_receipt`.
  - `DeliveryNoteController` and `GoodsReceiptController` endpoints.
  - `DeliveryNotes.tsx` and `GoodsReceipts.tsx` Inertia pages.
  - `Phase4Slice4FulfillmentTest` 17/17 passing tests (138 assertions).

Latest verified test suite baseline:

```text
php artisan test: 302 passing tests / 2469 assertions
Phase4Slice4FulfillmentTest: 17 passing tests / 138 assertions
vendor/bin/pint --test: passed
npm run typecheck: passed (0 TS errors)
npm run build: passed
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
