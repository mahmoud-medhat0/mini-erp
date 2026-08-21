# NEXT TASKS - Current Laravel Track

Current status: Phase 4 Slice 2 (Sales Order Backend & UX) is implemented, but local review found authoritative Sales Order line-total calculation using `round(... / 1000000)`. Execute `PHASE_4_SLICE_2_CORRECTION_GEMINI_PROMPT.md` before starting Phase 4 Slice 3. See `PHASE_4_SALES_PURCHASING_OPERATIONS.md`.

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
- Phase 3 Slices 1-10 Foundation (Master Data, AR/AP Subledgers, Receipts/Payments, Allocation Engine, Cheques, Bank Reconciliation, Inertia Pages/UX, Operational Reports, Concurrency Stress & Integrity, Close-Out Report).
- Phase 4 Slice 1 Product/Service Catalog Foundation.
- Phase 4 Slice 2 Sales Order Backend & UX:
  - `sales_order` and `sales_order_line` tables and Eloquent models.
  - `SalesOrderService` lifecycle (`draft` -> `submitted` -> `confirmed` / `cancelled`).
  - Integer minor currency math & exact quantity scaling (`quantity_e6`).
  - Number sequence allocation `SO-YYYY-XXXXX` with idempotent confirmation replay.
  - Spatie Activitylog audit via `AuditLogger`.
  - Attachment entity registry registration for `sales_order`.
  - `SalesOrderController` endpoints under `/sales/orders/*`.
  - `SalesOrders.tsx` Inertia page with customer selector, product/UOM selector, dynamic line items, real-time line total preview, status badges, and action buttons.
  - `Phase4Slice2SalesOrderTest` 12/12 passing tests (52 assertions) reported by Gemini.
  - Needs correction: authoritative server-side line-total calculation must remove `round()` and floating division.

Latest verified test suite baseline:

```text
php artisan test: 266 passing tests / 2207 assertions
Phase4Slice2SalesOrderTest: 12 passing tests / 52 assertions
vendor/bin/pint --test: passed
npm run typecheck: passed (0 TS errors)
npm run build: passed
```

## Immediate Priority - Phase 4 Slice 2 Correction

Execute:

- `PHASE_4_SLICE_2_CORRECTION_GEMINI_PROMPT.md`

Required correction:

- Remove authoritative `round(($quantityE6 * $unitPriceMinor) / 1000000)` style calculation from `SalesOrderService`.
- Use exact integer arithmetic with overflow checks, modulo, and `intdiv`.
- Reject fractional-minor results until an owner-approved rounding policy exists.
- Verify no Sales Order service/model/test authoritative path contains `round(`, `(float)`, `float`, `double`, `/ 1000000`, or `/1000000`.

After this correction passes, the next implementation slice is:

- `PHASE_4_SLICE_3_GEMINI_PROMPT.md` to be created for Purchase Order Backend & UX.

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
