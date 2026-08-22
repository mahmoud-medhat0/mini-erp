# IMPLEMENTATION STATUS

- **Current phase:** Phase 4 Slice 8 complete (Moving Weighted Average Inventory Costing & Posting).
- **Latest verified:** 2026-08-22, local Laravel + PostgreSQL after Phase 4 Slice 8 implementation.
- **Tests passing:** Laravel PHPUnit 355 tests, 353 passed, 2 skipped / 2761 assertions; Phase 4 Slice 8 suite 13/13 (89 assertions).
- **Stress passing:** `concurrency:stress --workers=100`, `accounting:concurrency-stress --workers=50`, `accounting:allocation-concurrency-stress --workers=50`, `accounting:cheque-concurrency-stress --workers=50`, `accounting:bank-reconciliation-concurrency-stress --workers=50`, `accounting:inventory-concurrency-stress --workers=50`, `accounting:phase3-integrity-check`, and `accounting:phase3-stress --workers=50`.
- **Frontend verification:** `npm run typecheck` passed (0 TS errors), `npm run build` passed.
- **Remote/CI:** No GitHub Actions pipeline is connected for the Laravel migration track.
- **Latest verified code commit:** pending for Phase 4 Slice 8 implementation.
- **Handoff:** start with `CONTINUE_HERE.md`, then `NEXT_TASKS.md`.

## Legend

`COMPLETE` fully implemented + tested · `PARTIAL` partially implemented · `PLANNED` prompt/contract prepared but not implemented · `SCAFFOLD ONLY` structure without full business logic · `LEGACY_REFERENCE` old Next.js reference material only.

## Laravel Migration Track

| Item | Status | Notes |
|---|---|---|
| M2 Inertia foundation | COMPLETE | Laravel app boots with Inertia/Vite and health/foundation routes. |
| Domain model correction | COMPLETE | Company/Branch/User ownership is not assumed; undefined relationships remain `UNDEFINED - DO NOT ASSUME`. |
| M3 database foundation | COMPLETE | Native `users`, company profile, standalone branch references, global fiscal years/periods, currency, number sequence, attachments, notifications, legacy audit archive, and non-team Spatie RBAC. |
| M5 session auth backend | COMPLETE | Login/logout, Argon2id, throttling, active users, session regeneration/invalidation, protected routes, bootstrap admin seeding. |
| M6 migrated Inertia pages | COMPLETE | Dashboard, settings hub, company, branches, numbering, users/roles, notifications, app shell, and notification read action backed by real Laravel data. |
| M7 Laravel core kernel parity | COMPLETE | Money, currency registry, accounting invariant, domain errors, number formatter/config, and Laravel invariant tests. |
| Phase 2 accounting core | COMPLETE | Account categories/types, chart of accounts, FX rates, fiscal periods, manual journals, posting engine, immutable ledger, reversal, opening balances, General Journal, General Ledger, Trial Balance, demo seeder, and accounting stress command. |
| M8 page actions | COMPLETE | Company/branch/numbering actions and role assign/revoke use explicit IDs, validation, permissions, optimistic locks where applicable, and no tenant/current-company session. |
| M9 attachments + notifications | COMPLETE | Attachment upload/download/list/delete service/routes, explicit allowlisted entity authorization, MIME/extension/size checks, storage cleanup compensation, UI panels, and user-targeted notification service with dedupe/list/mark-read behavior. |
| M10 audit + jobs/scheduler | COMPLETE | Spatie Activitylog is the active audit backend, legacy `audit_log` is retained as archive, activity/audit tables are append-only, `/audit-log` viewer exists, `tokens:gc --batch=100` is scheduled hourly, and jobs/failed_jobs baseline is verified. |
| Phase 3 Slices 1-10 Foundation | COMPLETE | Master Data, AR/AP Subledgers, Receipts/Payments, Allocation Engine, Cheques, Bank Reconciliation, Inertia Pages/UX, Operational Reports, Concurrency Stress/Integrity, Close-Out Report. |
| Phase 4 Slice 1 Catalog Foundation | COMPLETE | UnitOfMeasure, ProductCategory, Product models/migrations/services/controllers, Spatie Activitylog audit, attachment entity registry for product, Inertia catalog management pages, 12/12 passing feature tests. |
| Phase 4 Slice 2 Sales Order Backend | COMPLETE | `sales_order` and `sales_order_line`, `SalesOrderService` lifecycle, exact integer total calculation with overflow/fractional-minor rejection, `SO-YYYY-XXXXX` idempotent confirmation, Spatie Activitylog audit, `sales_order` attachment registry, Inertia page, and 15/15 passing feature tests. |
| Phase 4 Slice 3 Purchase Order Backend | COMPLETE | `purchase_order` and `purchase_order_line` models/migrations, `PurchaseOrderService` lifecycle (`draft` -> `submitted` -> `confirmed` / `cancelled`), exact integer total calculation (`intdiv` & `% 1000000`), number sequence allocation `PO-YYYY-XXXXX`, idempotent confirmation, Spatie Activitylog audit, attachment entity registry for `purchase_order`, Inertia purchase order management pages (`PurchaseOrders.tsx`), 16/16 passing feature tests. |
| Phase 4 Slice 4 Delivery Notes & Goods Receipts | COMPLETE | `delivery_note`, `delivery_note_line`, `goods_receipt`, `goods_receipt_line` models/migrations, `DeliveryNoteService` & `GoodsReceiptService` lifecycle, integer quantity validation (`quantity_e6`), cumulative over-fulfillment prevention with deterministic transaction locks, `DN-YYYY-XXXXX` and `GRN-YYYY-XXXXX` number allocation, Spatie Activitylog audit, attachment entity registry for `delivery_note` & `goods_receipt`, Inertia management pages (`DeliveryNotes.tsx`, `GoodsReceipts.tsx`), 17/17 passing feature tests. |
| Phase 4 Slice 5 Customer Invoice Posting | COMPLETE | `customer_invoice` and `customer_invoice_line`, lifecycle, strict Sales Order/Delivery Note source matching, exact integer totals, `INV-YYYY-XXXXX`, `sales_revenue` mapping, PostingEngine posting Dr AR / Cr Sales Revenue, `receivable_entry` debit, attachment registry, Inertia page, and 19/19 passing feature tests after local hardening. |
| Phase 4 Slice 6 Supplier Bill Posting | COMPLETE | `supplier_bill` and `supplier_bill_line`, lifecycle, strict Purchase Order/Goods Receipt source matching, exact integer totals, `BILL-YYYY-XXXXX`, `purchase_expense` mapping, PostingEngine posting Dr Purchase Expense / Cr AP Control, `payable_entry` credit, attachment registry, Inertia page, and 16/16 passing feature tests. |
| Phase 4 Slice 7 Inventory Costing Decision | COMPLETE | Created `PHASE_4_INVENTORY_COSTING_DECISION.md` comparing Weighted Average, FIFO, Standard Costing, and Non-Valued Stock Tracking. Owner selected Option 1: Moving Weighted Average Costing. |
| Phase 4 Slice 8 Moving Weighted Average Inventory Costing & Posting | COMPLETE | `stock_balance` and `stock_movement_ledger` tables/models, `MovingWeightedAverageInventoryService`, GL mappings (`inventory_asset`, `grni_clearing`, `cogs`), Goods Receipt stock receipt posting (Dr Inventory Asset / Cr GRNI Clearing), Delivery Note stock issue posting (Dr COGS / Cr Inventory Asset), Supplier Bill stock line GRNI clearing, Customer Invoice stock line DN matching, read-only Inertia stock balances page (`StockBalances.tsx`), 13/13 passing feature tests, and 50-worker inventory concurrency stress command. |
| Phase 4 Slices 9-10 Operations | PLANNED | Returns/Credit Notes after owner decision, UX/reporting/stress close-out. |
| Removed relationship assumptions | COMPLETE | `company_user`, `branch.company_id`, Company/Branch Eloquent links, `fiscal_year.company_id`, `number_sequence.company_id`, `number_sequence.include_branch`, and unsupported audit/attachment/notification `company_id` removed or absent. |
| Removed tenant assumptions | COMPLETE | Tenant context/middleware/onboarding, currentCompany/currentBranch, and Spatie `company_id` teams are removed/disabled. |
| Concurrency hardening | COMPLETE | Idempotency keys, optimistic locks, PostgreSQL number allocation, bounded token GC, notification dedupe, attachment compensation, ledger/audit immutability, and stress/test coverage. |

## Current Audit Status

| Area | Status | Notes |
|---|---|---|
| Active audit backend | COMPLETE | `spatie/laravel-activitylog` 4.12.3 writes to `activity_log`. |
| Application API | COMPLETE | `App\Domain\Audit\AuditLogger::record(...)` keeps the old signature and writes through Spatie. |
| Legacy archive | COMPLETE | Existing `audit_log` is retained and append-only; no new application writes should target it. |
| Query/UI compatibility | COMPLETE | `AuditLogQueryService` maps Spatie rows to old aliases: `actor_id`, `actor_name`, `action`, `entity_type`, `before_json`, `after_json`, `request_id`, `ip`, `device`, `at`. |
| DB immutability | COMPLETE | PostgreSQL and SQLite triggers block UPDATE/DELETE on `activity_log` and legacy `audit_log`. |

## Verification Snapshot

Last full verification from `laravel/`:

```powershell
composer install
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --filter=Phase4Slice1CatalogTest
php artisan test --filter=Phase4Slice2SalesOrderTest
php artisan test --filter=Phase3Slice9StressIntegrityTest
php artisan test --filter=Phase3Slice8ReportsTest
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

Result summary:

- `php artisan migrate --force`: Nothing to migrate after Phase 4 Slice 6 migration was applied.
- `php artisan migrate:status`: all migrations Ran through `2026_08_22_070000_create_phase4_slice6_supplier_bill_tables`.
- `vendor/bin/pint --test`: passed after Phase 4 Slice 6 local hardening.
- `php artisan test`: 342 tests, 340 passed, 2 skipped / 2675 assertions.
- `php artisan test --filter=Phase4Slice6SupplierBillTest`: 19 tests / 100 assertions passed after source-line and posting hardening.
- `php artisan test --testsuite=Concurrency`: 7 tests / 16 assertions passed.
- `php artisan concurrency:stress --workers=100`: passed.
- `php artisan accounting:concurrency-stress --workers=50`: passed.
- `php artisan accounting:allocation-concurrency-stress --workers=50`: passed.
- `php artisan accounting:cheque-concurrency-stress --workers=50`: passed.
- `php artisan accounting:bank-reconciliation-concurrency-stress --workers=50`: passed.
- `php artisan accounting:phase3-integrity-check`: passed.
- `php artisan accounting:phase3-stress --workers=50`: passed.
- `php artisan tokens:gc --batch=100`: passed.
- `npm run typecheck`: passed.
- `npm run build`: passed.
- Supplier Bill backend forbidden float/rounding source scan: no results.

## Module Status

| Module / Area | Status | Notes |
|---|---|---|
| Accounting ledger spine | COMPLETE | Phase 2 accounting core is implemented as the current ledger backbone. |
| Settings | COMPLETE | Company profile, standalone branch references, numbering, users/roles. |
| Notifications | COMPLETE FOUNDATION | User-targeted notifications and read actions exist; future modules must add their own event triggers. |
| Attachments | COMPLETE FOUNDATION | Entity registry + service exists; future entities must register authorization rules. |
| Audit | COMPLETE FOUNDATION | Spatie Activitylog active with read-only viewer and append-only enforcement. |
| Sales | PARTIAL | Sales Orders, Delivery Notes, and Customer Invoice posting are complete for their bounded scopes. Returns, credit notes, stock-product invoicing, and COGS/inventory posting are not implemented yet. |
| Purchasing | PARTIAL | Purchase Orders, Goods Receipts, and Supplier Bill AP/GL posting are complete for their bounded operational scopes. Stock-product billing, landed cost, and inventory valuation are not implemented yet. |
| Inventory | PLANNED | Inventory valuation/stock movement requires later owner decisions; Slice 1 must not implement valuation, COGS, or stock ledgers. |
| AR/AP + Cash/Bank/Cheques | COMPLETE | Phase 3 Slices 1-10 are complete; Phase 3 AR/AP + Cash/Bank/Cheques track is fully closed out for agreed scope. |
| Payroll, Rentals, Fixed Assets, Taxes, Projects, Budgeting | SCAFFOLD ONLY | Not started. |
| Full financial statements | NOT IMPLEMENTED | General Journal, General Ledger, and Trial Balance exist; Balance Sheet/Income Statement/Cash Flow are later work. |

## Known Issues / Residual Risks

- No GitHub Actions workflow is connected for the Laravel migration track; verification is currently local.
- Browser E2E coverage for the Laravel UI is not yet equivalent to the old Next.js Playwright smoke suite.
- Branch exact business semantics remain undefined; do not add ownership, uniqueness, or authorization scope without owner decision.
- Production scheduler execution still needs deployment wiring, e.g. external cron calling Laravel `schedule:run`.
- Legacy specs and old `app/` docs can mention tenant/company scope; treat them as historical unless they match current owner corrections.

## Next Milestone

Phase 3 is 100% complete for the agreed scope, and Phase 4 Slices 1-7 are complete. The owner selected Moving Weighted Average Costing for inventory.

- Next execution: Phase 4 Slice 8 using `PHASE_4_SLICE_8_GEMINI_PROMPT.md`.

Other owner options:

- Optional: E2E Browser Testing Hardening
- Optional: Production Deployment Readiness
