# NEXT TASKS - Current Laravel Track

Current status: Phase 4 Slice 1 (Product/Service Catalog Foundation) is complete and reported verified locally on PostgreSQL. Phase 4 Slice 2 is prompt-ready in `PHASE_4_SLICE_2_GEMINI_PROMPT.md`.

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
- Phase 4 Slice 1 Product/Service Catalog Foundation:
  - `unit_of_measure`, `product_category`, and `product` tables.
  - `UnitOfMeasure`, `ProductCategory`, and `Product` models.
  - catalog services/controllers/pages.
  - Spatie Translatable EN/AR fields.
  - optimistic locking with `lock_version`.
  - product attachment registry entry.
  - `products.*` and `uom.*` RBAC.
  - catalog seeders registered in `DatabaseSeeder.php`.
  - `Phase4Slice1CatalogTest` reported 12/12 passing with 66 assertions.

Latest reported after Phase 4 Slice 1:

```text
php artisan test: 254 passing tests / 2145 assertions
Phase4Slice1CatalogTest: 12 passing tests / 66 assertions
vendor/bin/pint --test: passed
npm run typecheck: passed with 0 errors
npm run build: passed
Anti-tenancy rules: no company_id, branch_id, or tenant_id introduced by Slice 1
```

## Immediate Priority

Execute:

- `PHASE_4_SLICE_2_GEMINI_PROMPT.md`

Scope:

- Sales Order backend and Inertia UX.
- `sales_order` and `sales_order_line`.
- Customer relation.
- Product relation using Phase 4 Slice 1 catalog.
- Currency relation.
- draft -> submitted -> confirmed / cancelled lifecycle.
- `SO-YYYY-XXXXX` global numbering with key `sales.order`.
- server-computed integer totals.
- Spatie Activitylog via `AuditLogger`.
- `sales_order` attachment entity registration.

Explicitly out of scope for Slice 2:

- separate sales quotation module
- purchase orders
- delivery notes
- customer invoices
- supplier bills
- AR posting
- GL posting
- PostingEngine integration
- inventory stock movement
- inventory valuation
- COGS
- VAT/tax
- discounts/price lists
- company/branch/tenant scope

## Upcoming Phase 4 Slices

- **Slice 3:** Purchase Order Backend.
- **Slice 4:** Delivery Note and Goods Receipt operational foundation.
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
