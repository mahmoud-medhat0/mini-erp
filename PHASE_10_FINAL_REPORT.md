# PHASE 10 - Branch, Warehouse, and Stock Transfer Foundation Report

Date: 2026-08-24

Status: COMPLETE for the implemented foundation pass.

Completion update: the follow-up operational inventory pass is also complete. See `PHASE_10_OPERATIONAL_COMPLETION_REPORT.md` for stock counts, stock adjustments, warehouse selectors on fulfillment/returns, and latest verification.

## Scope Implemented

This pass implements the approved operational branch/warehouse foundation without introducing multi-tenant architecture.

Implemented:

- warehouse master data with optional operational `branch_id`
- stock locations inside warehouses
- default `MAIN` warehouse seeding
- warehouse-aware `stock_balance`
- warehouse-aware immutable `stock_movement_ledger`
- warehouse-to-warehouse stock transfer lifecycle
- transfer issue and receipt stock movements
- partial transfer receipts
- exact weighted-average cost preservation during transfers
- no revenue, VAT, AR/AP, or GL posting for internal transfers
- warehouse filters on stock balances and stock movement reports
- Inertia pages for warehouses, stock transfers, stock balances, and stock movement reporting
- RBAC permissions for inventory transfer/receive workflows
- attachment registry support for warehouses and stock transfers
- PostgreSQL concurrency stress command for stock transfers

Follow-up pass now also implemented:

- stock counts and stock count lines
- stock adjustments and stock adjustment lines
- stock count variance posting through generated stock adjustments
- inventory adjustment gain/loss GL mappings
- warehouse selectors on Delivery Notes, Goods Receipts, Sales Returns, and Purchase Returns
- warehouse filters on Delivery Note and Goods Receipt reports

## Files Added

- `laravel/database/migrations/2026_08_24_010000_create_phase10_warehouse_and_stock_transfer_tables.php`
- `laravel/app/Models/Warehouse.php`
- `laravel/app/Models/StockLocation.php`
- `laravel/app/Models/StockTransfer.php`
- `laravel/app/Models/StockTransferLine.php`
- `laravel/app/Models/StockTransferReceipt.php`
- `laravel/app/Models/StockTransferReceiptLine.php`
- `laravel/app/Application/Inventory/WarehouseService.php`
- `laravel/app/Application/Inventory/StockTransferService.php`
- `laravel/app/Console/Commands/StockTransferConcurrencyStressCommand.php`
- `laravel/app/Http/Controllers/WarehouseController.php`
- `laravel/app/Http/Controllers/StockLocationController.php`
- `laravel/app/Http/Controllers/StockTransferController.php`
- `laravel/database/seeders/WarehouseSeeder.php`
- `laravel/resources/js/Pages/Inventory/Warehouses.tsx`
- `laravel/resources/js/Pages/Inventory/StockTransfers.tsx`
- `laravel/tests/Feature/Phase10BranchWarehouseOperationsTest.php`

## Existing Files Updated

- `laravel/app/Application/Inventory/MovingWeightedAverageInventoryService.php`
- `laravel/app/Application/Reports/StockMovementReportService.php`
- `laravel/app/Console/Commands/Phase3IntegrityCheckCommand.php`
- `laravel/app/Http/Controllers/Reports/StockMovementReportController.php`
- `laravel/app/Http/Controllers/StockBalanceController.php`
- `laravel/app/Models/StockBalance.php`
- `laravel/app/Models/StockMovementLedger.php`
- `laravel/config/erp_attachments.php`
- `laravel/config/erp_rbac.php`
- `laravel/database/seeders/DatabaseSeeder.php`
- `laravel/resources/js/Components/AppLayout.tsx`
- `laravel/resources/js/Pages/Inventory/StockBalances.tsx`
- `laravel/resources/js/Pages/Reports/StockMovementsReport.tsx`
- `laravel/resources/js/locales/en.json`
- `laravel/resources/js/locales/ar.json`
- `laravel/routes/web.php`
- `laravel/tests/Feature/Phase3Slice9StressIntegrityTest.php`
- `laravel/tests/Feature/Phase4Slice4FulfillmentTest.php`

## No Multi-Tenant Confirmation

Not introduced:

- `company_id`
- `tenant_id`
- Spatie Teams
- `currentCompany`
- `currentBranch`
- branch-owned users, roles, or permissions
- branch-scoped number sequences
- branch as a login/session/security boundary

Allowed operational reference:

- `warehouse.branch_id` only. It links a warehouse to an optional operational branch for reporting and transfer context. It is not tenancy, ownership, or authorization scope.

## Accounting Rules Preserved

- Internal stock transfers do not create revenue.
- Internal stock transfers do not create VAT.
- Internal stock transfers do not create AR/AP entries.
- Internal stock transfers do not create GL journals in this pass.
- Transfer issue preserves original weighted-average carrying cost.
- Transfer receipt moves the same carrying cost into the destination warehouse.
- Transfer movements remain append-only through the existing stock movement immutability rules.

## Verification Results

Commands executed from `laravel/`:

```powershell
php artisan migrate --force
php artisan migrate:status
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=WarehouseSeeder
vendor/bin/pint --test
php artisan test --testsuite=Unit
php artisan test --testsuite=Integration
php artisan test --testsuite=Invariants
php artisan test --testsuite=Concurrency
php artisan test tests/Feature/Phase10BranchWarehouseOperationsTest.php
php artisan test tests/Feature/Phase4Slice4FulfillmentTest.php
php artisan test tests/Feature/Phase4Slice10ReturnsCreditNotesTest.php
php artisan test tests/Feature/*.php
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan accounting:stock-transfer-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Result summary:

- migrations: 65 migrations applied
- Phase 10 feature test: 5/5 passed, 87 assertions
- impacted costing/report tests: passed
- fulfillment regression: fixed and passed, 17/17 tests, 138 assertions
- returns/credit note tests: passed, 38/38 tests, 230 assertions
- Unit suite: 5/5 passed, 15 assertions
- Integration suite: 8/8 passed, 70 assertions
- Invariants suite: 15/15 passed, 522 assertions
- Concurrency suite: 7/7 passed, 16 assertions
- all Feature test files were executed file-by-file after the regression fix and passed
- stock transfer stress: 50/50 workers succeeded
- inventory stress: 50 iterations succeeded
- Vite build: passed with the existing chunk-size warning only

Note: `php artisan test` as one monolithic command is silent for a long time in this repository because several feature files are slow and buffered. It was replaced by per-file Feature execution plus the configured Unit/Integration/Invariants/Concurrency suites to expose progress and failures clearly.

## Remaining Future Product Work

Not implemented in this pass:

- branch cash/bank account assignment and transfer workflow
- fixed asset branch/location transfer history
- branch profitability and branch-level P&L reporting
- branch-specific GL mappings
- branch-specific approval rules

These remain future operational extensions and must still avoid multi-tenant semantics.
