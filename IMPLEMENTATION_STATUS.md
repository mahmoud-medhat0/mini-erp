# IMPLEMENTATION STATUS

- **Current phase:** Phase 4 Slice 9 complete and locally verified (Read-only Sales, Purchasing, Fulfillment, Invoicing, Billing & Stock Movement Operational Reports + Returns Decision Pack).
- **Latest verified:** 2026-08-22, local Laravel + PostgreSQL after Phase 4 Slice 9 completion.
- **Tests passing:** Laravel PHPUnit 363 tests, 360 passed, 3 skipped / 2870 assertions; Phase 4 Slice 9 suite 7/7 passing tests / 85 assertions.
- **Stress passing:** `concurrency:stress --workers=10`, `accounting:concurrency-stress --workers=50`, `accounting:inventory-concurrency-stress --workers=50`, and the PHPUnit Concurrency suite. `concurrency:stress --workers=100` is currently blocked locally by Windows paging-file exhaustion.
- **Frontend verification:** `npm run typecheck` passed (0 TS errors), `npm run build` passed.
- **Remote/CI:** No GitHub Actions pipeline is connected for the Laravel migration track.
- **Latest verified code commit:** pending for Phase 4 Slice 9 implementation.
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
| Phase 4 Slice 8 Moving Weighted Average Inventory Costing & Posting | COMPLETE | `stock_balance` and `stock_movement_ledger` tables/models, `MovingWeightedAverageInventoryService`, GL mappings (`inventory_asset`, `grni_clearing`, `cogs`), Goods Receipt stock receipt posting (Dr Inventory Asset / Cr GRNI Clearing), Delivery Note stock issue posting (Dr COGS / Cr Inventory Asset), Supplier Bill stock line GRNI clearing, Customer Invoice stock line DN matching, read-only Inertia stock balances page (`StockBalances.tsx`), PostgreSQL integrity constraints via `2026_08_22_090000_harden_phase4_slice8_inventory_integrity.php`, 14-test Slice 8 feature suite, and 50-iteration inventory integrity stress command. |
| Phase 4 Slice 9 Operational Reports & Returns Decision Pack | COMPLETE | 7 Query Services (`SalesOrderReportService`, `PurchaseOrderReportService`, `DeliveryNoteReportService`, `GoodsReceiptReportService`, `CustomerInvoiceReportService`, `SupplierBillReportService`, `StockMovementReportService`), 7 Controllers, 7 Inertia Pages, Reports Hub links, `PHASE_4_RETURNS_CREDIT_DEBIT_DECISION.md` decision pack, schema-aligned report numbers, and 7/7 passing feature tests (`Phase4Slice9OperationalReportsTest`, 85 assertions). |
| Phase 4 Slice 10 Sales Returns, Credit Notes & Operations Close-Out | PLANNED | Implementation of Returns, Customer Credit Notes, Supplier Credit/Debit Notes after owner decision approval, followed by final operational close-out audit. |
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

- `php artisan migrate --force`: Nothing to migrate after Phase 4 Slice 9 local correction.
- `php artisan migrate:status`: all migrations Ran through `2026_08_22_090000_harden_phase4_slice8_inventory_integrity`.
- `vendor/bin/pint --test`: passed after Phase 4 Slice 9 local correction.
- `php artisan test`: 363 tests, 360 passed, 3 skipped / 2870 assertions.
- `php artisan test --filter=Phase4Slice9OperationalReportsTest`: 7 tests / 85 assertions passed after report schema-alignment correction.
- `php artisan test --testsuite=Concurrency`: 7 tests / 16 assertions passed.
- `php artisan concurrency:stress --workers=10`: passed; `--workers=100` is blocked locally by Windows paging-file `VirtualAlloc` exhaustion.
- `php artisan accounting:concurrency-stress --workers=50`: passed.
- `php artisan accounting:inventory-concurrency-stress --workers=50`: passed.
- `php artisan tokens:gc --batch=100`: passed.
- `npm run typecheck`: passed.
- `npm run build`: passed.
- Phase 4 Slice 9 report services use current `number`/`journal_entry.number` schema fields.

## Module Status

| Module / Area | Status | Notes |
|---|---|---|
| Accounting ledger spine | COMPLETE | Phase 2 accounting core is implemented as the current ledger backbone. |
| Settings | COMPLETE | Company profile, standalone branch references, numbering, users/roles. |
| Notifications | COMPLETE FOUNDATION | User-targeted notifications and read actions exist; future modules must add their own event triggers. |
| Attachments | COMPLETE FOUNDATION | Entity registry + service exists; future entities must register authorization rules. |
| Audit | COMPLETE FOUNDATION | Spatie Activitylog active with read-only viewer and append-only enforcement. |
| Sales | PARTIAL | Sales Orders, Delivery Notes, Customer Invoice posting, stock-product invoice reporting, and COGS/inventory posting through Delivery Notes are complete for their bounded scopes. Returns and credit notes are not implemented yet. |
| Purchasing | PARTIAL | Purchase Orders, Goods Receipts, Supplier Bill AP/GL posting, stock-product bill reporting, GRNI clearing, and inventory valuation through Goods Receipts are complete for their bounded scopes. Purchase returns and supplier credit/debit notes are not implemented yet. |
| Inventory | PARTIAL | Moving Weighted Average stock balance and immutable stock movement ledger are implemented. Warehouse/location, stock counts, stock adjustments, and return valuation workflows are not implemented. |
| AR/AP + Cash/Bank/Cheques | COMPLETE | Phase 3 Slices 1-10 are complete; Phase 3 AR/AP + Cash/Bank/Cheques track is fully closed out for agreed scope. |
| Payroll, Rentals, Fixed Assets, Taxes, Projects, Budgeting | SCAFFOLD ONLY | Not started. |
| Full financial statements | NOT IMPLEMENTED | General Journal, General Ledger, and Trial Balance exist; Balance Sheet/Income Statement/Cash Flow are later work. |

## Known Issues / Residual Risks

- No GitHub Actions workflow is connected for the Laravel migration track; verification is currently local.
- Browser E2E coverage for the Laravel UI is not yet equivalent to the old Next.js Playwright smoke suite.
- Branch exact business semantics remain undefined; do not add ownership, uniqueness, or authorization scope without owner decision.
- Production scheduler execution still needs deployment wiring, e.g. external cron calling Laravel `schedule:run`.
- `php artisan concurrency:stress --workers=100` can exhaust the local Windows paging file in this workstation; lower worker counts pass and accounting/inventory stress checks pass.
- Legacy specs and old `app/` docs can mention tenant/company scope; treat them as historical unless they match current owner corrections.

## Next Milestone

Phase 3 is 100% complete for the agreed scope, and Phase 4 Slices 1-9 are complete. Moving Weighted Average inventory costing and read-only operational reports are implemented and locally hardened.

- Next execution: Phase 4 Slice 10 after owner decisions in `PHASE_4_RETURNS_CREDIT_DEBIT_DECISION.md`.

Other owner options:

- Optional: E2E Browser Testing Hardening
- Optional: Production Deployment Readiness
