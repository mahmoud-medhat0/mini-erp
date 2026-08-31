# Phase 10 Fixed Asset Location and Movement History Report

Date: 2026-08-25

## Scope

This pass implements fixed asset branch/location movement history as an owner-approved Phase 10 operational capability.

It is not multi-tenant work. Branch and location are used only for asset operations, physical tracking, and future reporting.

## Implemented

### Fixed Asset Locations

Added `fixed_asset_location` as a fixed-asset-specific operational location list:

- code
- multilingual name
- optional operational `branch_id`
- active/inactive flag
- optimistic `lock_version`

Locations can be created, edited, deactivated, and deleted only when unused by assets or movement history.

### Current Asset Position

Added optional current operational references to `fixed_asset`:

- `branch_id`
- `fixed_asset_location_id`

These fields represent current physical/operational position only. They do not create tenancy, ownership, login scope, branch security, or company relationship behavior.

### Movement History

Added append-only `fixed_asset_movement` history:

- movement number `FAM-YYYY-XXXXX`
- movement date
- from/to branch
- from/to asset location
- from/to JSON snapshots
- reason and notes
- actor reference

Movement behavior:

- Locks the asset row before moving.
- Rejects disposed asset movements.
- Rejects branch/location mismatches.
- Allows clearing assignment if the selected destination genuinely changes the asset position.
- Increments `fixed_asset.lock_version`.
- Records Spatie Activitylog through `AuditLogger`.
- Does not post GL journals.
- Does not change depreciation, capitalization, disposal, VAT, AR/AP, or revenue/expense records.

### UI / Routes

Added:

- Fixed Asset Locations management page.
- Branch/location filters on Fixed Assets register.
- Current branch/location display on Fixed Asset detail.
- Movement modal on Fixed Asset detail.
- Movement history table on Fixed Asset detail.
- Fixed Assets navigation group with Register, Categories, Locations, Depreciation Runs, and Disposals.

All new visible UI text is dictionary-backed in EN/AR.

### New Files

- `laravel/database/migrations/2026_08_25_020000_create_phase10_fixed_asset_location_movement_tables.php`
- `laravel/app/Models/FixedAssetLocation.php`
- `laravel/app/Models/FixedAssetMovement.php`
- `laravel/app/Application/FixedAssets/FixedAssetLocationService.php`
- `laravel/app/Application/FixedAssets/FixedAssetMovementService.php`
- `laravel/app/Http/Controllers/FixedAssets/FixedAssetLocationController.php`
- `laravel/app/Http/Controllers/FixedAssets/FixedAssetMovementController.php`
- `laravel/resources/js/Pages/FixedAssets/Locations.tsx`
- `laravel/tests/Feature/Phase10FixedAssetMovementTest.php`

## Scope Confirmation

No tenant/company scope was introduced:

- No `company_id`.
- No `tenant_id`.
- No `currentCompany`.
- No `currentBranch`.
- No Spatie Teams.
- No Company-to-Branch, Company-to-User, User-to-Branch, or User-to-Asset ownership relationship.

Operational branch/location references are limited to fixed asset position tracking and movement history.

## Verification

Executed:

```powershell
php artisan migrate --force
php artisan migrate:status
php artisan route:list --path=fixed-asset -v
php artisan test tests/Feature/Phase10FixedAssetMovementTest.php
php artisan test --filter=Phase6Slice2FixedAssetRegisterTest
php artisan test --filter=Phase6Slice7FixedAssetReportsTest
php artisan test --filter=SecurityHardeningTest
php artisan qa:verify-local --timeout=300
php artisan qa:verify-local --only-feature-files --filter=Phase10 --stop-on-failure --timeout=300
php artisan tokens:gc --batch=100
vendor/bin/pint --test
npm run typecheck
npm run build
```

Results:

- Migration applied: `2026_08_25_020000_create_phase10_fixed_asset_location_movement_tables`.
- `migrate:status`: 69 migrations Ran.
- Fixed asset route list shows explicit middleware, including `can:fixedAssets.transfer`.
- `Phase10FixedAssetMovementTest`: 5 tests / 50 assertions passed.
- `Phase6Slice2FixedAssetRegisterTest`: 9 tests / 71 assertions passed.
- `Phase6Slice7FixedAssetReportsTest`: 6 tests / 151 assertions passed.
- `SecurityHardeningTest`: 6 tests / 355 assertions passed.
- `qa:verify-local`: Unit, Integration, Invariants, and Concurrency suites passed.
- Phase 10 feature gate passed:
  - `Phase10BranchWarehouseOperationsTest`: 5 tests / 87 assertions passed.
  - `Phase10FixedAssetMovementTest`: 5 tests / 50 assertions passed.
  - `Phase10StockCountAdjustmentTest`: 5 tests / 57 assertions passed.
  - `Phase10TreasuryTransferTest`: 4 tests / 34 assertions passed.
- `tokens:gc --batch=100`: completed cleanly.
- Pint passed.
- TypeScript typecheck passed.
- Vite production build passed with the existing chunk-size warning only.
- New Fixed Asset TSX source scan found no hardcoded Arabic or visible English movement/location labels; new labels are dictionary-backed.
- New Fixed Asset movement code scope scan found no tenant/company/current context terms.

## Remaining Phase 10 Extensions

- Branch-level profitability views only after explicit owner approval of the GL branch-dimension accounting model.
- Optional branch-specific GL mappings only after explicit owner approval.
- Optional branch-aware approval rules only after explicit owner approval.
- Landed cost / freight allocation.
- Payroll.
- Rentals.
