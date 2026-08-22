# NEXT TASKS - Current Laravel Track

Current status: Phase 4 Slice 10 (Sales Returns, Credit Notes & Manual Note Settlement) is fully implemented and locally verified on 2026-08-22, including the Manual Settlement Pass for note-created AR/AP entries (`PHASE_4_SLICE_10_SETTLEMENT_CORRECTION_PROMPT.md`).

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
- Phase 4 Slice 9 Operational Reports & Returns Decision Pack.
- Phase 4 Slice 10 Sales Returns, Credit Notes & Operations Close-Out (FULLY COMPLETE):
  - Five document families: `sales_return`, `customer_credit_note`, `customer_invoice_revision`, `purchase_return`, `supplier_adjustment_note` across seven migrations (including `receivable_entry_settlement` and `payable_entry_settlement`).
  - Services: `SalesReturnService`, `CustomerCreditNoteService`, `CustomerInvoiceRevisionService`, `PurchaseReturnService`, `SupplierAdjustmentNoteService`, `ReceivableEntrySettlementService`, `PayableEntrySettlementService`.
  - Manual AR credit note settlement against invoice debits & AP adjustment note settlement against bill credits.
  - Concurrency stress command: `accounting:settlement-concurrency-stress`.
  - Feature test suite `Phase4Slice10ReturnsCreditNotesTest.php` (38/38 passing tests, 0 skipped, 230 assertions).
  - GL mapping keys `sales_returns` (4200), `inventory_return_variance` (5200), `inventory_scrap_loss` (5300), `purchase_returns_allowances` (5400), `input_tax_receivable` (1300), `output_tax_payable` (2200) seeded idempotently in `AccountingCoreSeeder` with accounts.
  - Manual tax percentage in integer basis points (`intdiv(($baseMinor * $rateBps) + 5000, 10000)`) with modes `none`/`manual_rate`/`manual_amount`; manual/open credit/debit settlement with explicit settlement/reversal actions and no extra GL.

Latest verified baseline:

```text
php artisan migrate --force: Nothing to migrate
php artisan migrate:status: all migrations Ran through 2026_08_22_200000_create_phase4_slice10_settlement_tables
php artisan test --filter=Phase4Slice10ReturnsCreditNotesTest: 38 tests / 38 passed / 0 skipped / 230 assertions
php artisan test: 407 tests, 404 passed, 3 skipped / 3172 assertions
php artisan test --testsuite=Concurrency: 7 tests / 16 assertions
php artisan concurrency:stress --workers=10: PASSED CLEANLY
php artisan concurrency:stress --workers=100: BLOCKED LOCALLY by Windows paging-file memory exhaustion, not an application assertion failure
php artisan accounting:concurrency-stress --workers=50: PASSED CLEANLY
php artisan accounting:allocation-concurrency-stress --workers=50: PASSED CLEANLY
php artisan accounting:settlement-concurrency-stress --workers=50: PASSED CLEANLY
php artisan accounting:cheque-concurrency-stress --workers=50: PASSED CLEANLY
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50: PASSED CLEANLY
php artisan accounting:inventory-concurrency-stress --workers=50: PASSED CLEANLY
php artisan accounting:phase3-integrity-check: PASSED
php artisan accounting:phase3-stress --workers=50: 50 SUCCESS
php artisan tokens:gc --batch=100: OK
vendor/bin/pint --test: passed
npm run typecheck: passed (0 TS errors)
npm run build: passed (chunk size warning only)
Inventory backend forbidden float/rounding source scan: no results
```

## Next Execution

No required Phase 4 correction remains. Remaining optional items only, each requiring an explicit bounded owner prompt:

- Optional: E2E Browser Testing Hardening (Playwright/Dusk smoke coverage for the Laravel UI).
- Optional: Production Deployment Readiness (Nginx, Supervisor/queue workers, scheduler cron, Redis, backups).

Explicitly NOT STARTED modules requiring bounded owner prompts:

- Payroll.
- Rentals.
- Fixed assets.
- Full tax/VAT filing and reporting module beyond Slice 10 manual note tax fields.
- Warehouse/location semantics.
- Landed cost and freight allocation.
- Full financial statements (Balance Sheet, Income Statement, Cash Flow).

## Owner Decisions Still Needed

Still do not implement these without explicit owner approval:

- full VAT/tax filing/reporting workflow beyond Slice 10 manual note tax fields.
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
