# MINI ERP - PHASE 6 SLICE 2 FIXED ASSET REGISTER FOUNDATION

You are continuing the active Laravel + Inertia Mini ERP migration.

Execute only Phase 6 Slice 2 after Slice 1 has recorded the owner-approved fixed asset policy.

Do not implement capitalization posting, depreciation schedules, depreciation runs, disposals, tax books, maintenance, barcode scanning, or supplier bill integration in this pass.

## Read First

Read and follow:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_6_FIXED_ASSETS.md`
- `PHASE_6_FIXED_ASSETS_POLICY_DECISION.md`
- actual Laravel migrations/models/services for Account, Currency, FinancialPeriod, PostingEngine, RBAC, attachments, and audit

## Objective

Build the fixed asset register foundation without GL posting.

Expected result:

- fixed asset categories
- fixed asset register
- accounting mapping keys needed later
- attachment registry support
- audit logging
- permissions and UI
- no journal/ledger posting

## Scope

Create forward migrations only.

Suggested tables, subject to actual codebase naming conventions:

- `fixed_asset_category`
- `fixed_asset`

Suggested `fixed_asset_category` fields:

- `id`
- `code` unique
- multilingual `name`
- optional default useful life in months
- optional default salvage value minor
- `is_active`
- timestamps

Suggested `fixed_asset` fields:

- `id`
- `asset_number` or `code` unique, allocated globally
- multilingual `name`
- `description` nullable
- `fixed_asset_category_id`
- `currency` referencing `currency.code`
- `acquisition_date`
- `in_service_date`
- `cost_minor`
- `salvage_value_minor`
- `useful_life_months`
- `depreciation_method` limited to owner-approved method for Phase 6
- `opening_accumulated_depreciation_minor`
- `status` limited to draft/active/fully_depreciated/disposed
- `serial_number` nullable if useful
- optimistic `lock_version`
- created/updated actor IDs if current patterns support them
- timestamps

Do not add:

- `company_id`
- `branch_id`
- `tenant_id`
- `user_id` as custodian
- `employee_id`
- `warehouse_id`
- `location_id`
- supplier bill relationship
- purchase order relationship

If physical location or custodian is requested but not owner-approved, document it as `OWNER DECISION REQUIRED`.

## Accounting Mappings

Seed mapping keys only if consistent with current `AccountingAccountMappingService` pattern:

- `fixed_asset_cost`
- `accumulated_depreciation`
- `depreciation_expense`
- `fixed_asset_disposal_gain`
- `fixed_asset_disposal_loss`
- `fixed_asset_clearing` if owner-approved in Slice 1

Do not post GL in this slice.

## Services, Models, Routes, UI

Create:

- Eloquent models and relationships for category and asset
- application service for register CRUD
- controller/routes
- Inertia pages for categories and assets
- EN/AR dictionary keys
- attachment entity registry for fixed assets
- Spatie Activitylog through `AuditLogger`
- tests

Rules:

- asset numbers must use existing number sequence pattern if automatic numbering is implemented
- number sequence must be global; no company/branch dimension
- create/edit/delete routes must use exact permissions
- delete only draft/unposted assets that have no future schedules/postings
- visible TSX text must be dictionary-backed
- do not add fixed-asset labels/actions to hardcoded permission/module/team maps in TSX pages; use dictionaries or backend-provided permission metadata

## Permissions

Minimum:

- index/show: `fixedAssets.view`
- create: `fixedAssets.create`
- edit: `fixedAssets.edit`
- delete draft: `fixedAssets.delete`
- mapping setup: `accounting.mappings`
- financial values visibility: `view_financials` where amounts are shown

Test both allowed and forbidden cases.

## Tests

Add feature tests covering:

- table/schema creation and constraints
- no tenant/company/branch/user/custodian columns
- category CRUD and delete protection
- asset CRUD with real schema fields only
- currency FK validation
- depreciation method limited to approved method
- useful life and salvage validation
- number uniqueness/global numbering
- audit records
- attachment registry entry
- permissions
- Inertia props and dictionary-backed UI assumptions
- no new hardcoded TSX module/team/permission label maps

## Source Scans

Run and classify:

```powershell
rg -n "company_id|branch_id|tenant_id|currentCompany|currentBranch|Spatie Teams" laravel/app laravel/database laravel/routes laravel/resources/js laravel/tests
rg -n "custodian|employee_id|warehouse_id|location_id|supplier_bill_id|purchase_order_id" laravel/app laravel/database laravel/routes laravel/resources/js laravel/tests
rg -n "Fixed Asset|Depreciation|Asset Category|Useful Life|Salvage" laravel/resources/js/Pages laravel/resources/js/Components
rg -n "\\/ 100|parseFloat|Number\\(|toFixed\\(|Math\\.round|\\(float\\)|round\\(" laravel/app laravel/resources/js laravel/tests
```

Non-empty scans require classification.

## Required Verification

Run from `laravel/` and wait for completion:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=10
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:settlement-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan accounting:phase3-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

## Final Report

Report:

1. migrations added
2. schema diff
3. routes/pages/services/models added
4. permissions added/reused
5. accounting mappings seeded
6. unsupported assumptions avoided
7. source scan classifications
8. test and verification results
9. next slice
