# Phase 10 Landed Cost / Freight Allocation Report

Status: COMPLETE & VERIFIED  
Date: 2026-08-25

## Scope

Implemented landed cost allocation for confirmed Goods Receipts in the active Laravel ERP.

This is an operational purchasing/inventory extension. It does not introduce tenant, company, or branch ownership scope.

## Delivered

- Added `landed_cost_allocation` and `landed_cost_allocation_line` tables.
- Added `LandedCostAllocation` and `LandedCostAllocationLine` models and Goods Receipt relationships.
- Added `LandedCostAllocationService` with draft/update/submit/approve/post/cancel lifecycle.
- Supported allocation methods:
  - `by_value`
  - `by_quantity`
  - `manual`
- Posting creates:
  - Dr Inventory Asset for cost still capitalizable into remaining warehouse stock.
  - Dr COGS for cost related to already-issued stock.
  - Dr Input Tax Receivable when a tax amount exists.
  - Cr AP Control for the total payable amount.
- Posting creates a `payable_entry` for the landed cost supplier.
- Posting creates `stock_movement_ledger` rows with `movement_type = landed_cost`, `quantity_delta_e6 = 0`, and positive `value_delta_minor` for capitalized portions.
- Updated PostgreSQL stock movement constraints to allow only this bounded zero-quantity value movement.
- Added `/purchasing/landed-costs` Inertia page with EN/AR dictionary-backed UI text.
- Added `purchasing.landed_costs` RBAC permission and attachment entity registry support.
- Added period close blocker for unposted landed cost allocations.

## Scope Guardrails

- No `company_id` added.
- No `tenant_id` added.
- No `currentCompany` or `currentBranch` context added.
- No Spatie Teams behavior added.
- No `branch_id` column added to landed cost allocation tables.
- Branch-specific GL mapping is used only through the existing operational warehouse branch reference when present.

## Verification

Verification commands executed from `laravel/`:

```powershell
php artisan migrate --force
php artisan migrate:status
php artisan db:seed --class=RbacSeeder
vendor/bin/pint --test
php artisan test --filter=Phase10LandedCostAllocationTest
php artisan test --filter=SecurityHardeningTest
php artisan test --filter=Phase10 --stop-on-failure
php artisan test --stop-on-failure
php artisan test --testsuite=Concurrency --stop-on-failure
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:stock-transfer-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Final results:

- Migrations are fully applied; nothing left to migrate.
- `2026_08_25_060000_create_landed_cost_allocation_tables` is applied.
- RBAC seeder refreshed successfully.
- Pint passed.
- `Phase10LandedCostAllocationTest`: 5 tests / 40 assertions passed.
- `SecurityHardeningTest`: 6 tests / 372 assertions passed.
- Phase 10 focused suite: 42 tests / 425 assertions passed.
- Full Laravel suite: 607 tests / 4957 assertions passed, 3 skipped.
- Concurrency suite: 7 tests / 16 assertions passed.
- General concurrency stress: 100 workers passed; sequence values unique and contiguous; idempotency callback executed exactly once.
- Accounting stress: 50 JV allocations passed; posting and reversal idempotency passed.
- Stock transfer stress: 50 workers passed.
- Inventory integrity stress: 50 receipt iterations and 50 issue iterations passed.
- Phase 3 integrity check passed.
- Token GC completed with 0 deleted rows.
- TypeScript typecheck passed with 0 errors.
- Vite production build passed; Vite reported only the existing large chunk size warning.

## Stabilization Fixes During Verification

- Re-applied `ledger_entry` immutability triggers after the Phase 10 operational branch ledger migration so SQLite test-table rebuilds preserve DB-level append-only protection.
- Updated historical source-scan tests that predated approved Phase 10 operational branch capability:
  - `accounting_account_mapping.branch_id` is now accepted only as the approved optional branch-specific GL mapping override.
  - `MovingWeightedAverageInventoryService` may derive an operational branch from warehouse for GL mapping lookup, while `stock_balance` and `stock_movement_ledger` remain warehouse-scoped.
  - `fixed_asset.branch_id` and `fixed_asset.fixed_asset_location_id` are accepted as approved operational asset position references.

These updates do not introduce multi-tenancy, company ownership, branch security scope, `currentBranch`, or Spatie Teams behavior.
