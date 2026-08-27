# Phase 10 Treasury Transfer and Branch Cash/Bank Extension Report

Date: 2026-08-25

## Scope

This pass extends the owner-approved branch-capable operating model to cash and bank accounts.

It is not multi-tenant work. Branch is used only as an optional operational/reporting reference.

## Implemented

### Cash / Bank Operational Branch Assignment

Added nullable `branch_id` to:

- `cash_account`
- `bank_account`

Updated:

- `CashAccount` and `BankAccount` models with optional `branch()` relation.
- `CashAccountService` and `BankAccountService` to validate active branch references.
- `CashAccountController` and `BankAccountController` to load, filter, create, and update branch references.
- `CashAccounts/Index.tsx` and `BankAccounts/Index.tsx` to show/filter/select branch using dictionary-backed UI text.

### Treasury Transfer Workflow

Added `treasury_transfer` document table and workflow for internal fund movement between:

- Cash -> Cash
- Cash -> Bank
- Bank -> Cash
- Bank -> Bank

Workflow:

- Draft creation.
- Draft update.
- Draft cancellation.
- Posting through `PostingEngine`.

Posting behavior:

- Dr destination linked GL account.
- Cr source linked GL account.
- No AR/AP entries.
- No VAT.
- No revenue or expense recognition.
- Source and destination branch references are snapshotted from the selected cash/bank accounts.

### New Files

- `laravel/database/migrations/2026_08_25_010000_create_phase10_treasury_transfer_tables.php`
- `laravel/app/Models/TreasuryTransfer.php`
- `laravel/app/Application/Accounting/TreasuryTransferService.php`
- `laravel/app/Http/Controllers/TreasuryTransferController.php`
- `laravel/resources/js/Pages/TreasuryTransfers/Index.tsx`
- `laravel/tests/Feature/Phase10TreasuryTransferTest.php`

### Updated Files

- Cash/bank models, services, controllers, pages, routes, navigation, translations, and integrity guards.
- `Phase3IntegrityCheckCommand` and `Phase3Slice9StressIntegrityTest` now classify `cash_account.branch_id` and `bank_account.branch_id` as owner-approved operational references, alongside `warehouse.branch_id`.

## Scope Confirmation

No company/tenant scope was introduced:

- No `company_id`.
- No `tenant_id`.
- No `currentCompany`.
- No `currentBranch`.
- No Spatie Teams.

Branch references added in this pass are bounded to operational cash/bank assignment and transfer reporting only.

## Verification

Executed:

```powershell
php artisan migrate --force
php artisan migrate:status
php artisan test --filter=Phase10TreasuryTransferTest
php artisan test --filter=Phase3Slice1MasterDataTest
php artisan test --filter=Phase3Slice9StressIntegrityTest
php artisan test --filter=Phase3Slice7UiTest
php artisan test --filter=SecurityHardeningTest
php artisan accounting:phase3-integrity-check
vendor/bin/pint --test
npm run typecheck
npm run build
php artisan qa:verify-local --timeout=300
php artisan qa:verify-local --only-feature-files --filter=Phase10 --stop-on-failure --timeout=300
```

Results:

- Migration applied: `2026_08_25_010000_create_phase10_treasury_transfer_tables`.
- `migrate:status`: 68 migrations Ran.
- `Phase10TreasuryTransferTest`: 4 tests / 34 assertions passed.
- `Phase3Slice1MasterDataTest`: 14 tests / 58 assertions passed.
- `Phase3Slice9StressIntegrityTest`: 6 tests / 512 assertions passed.
- `Phase3Slice7UiTest`: 13 tests / 112 assertions passed.
- `SecurityHardeningTest`: 6 tests / 350 assertions passed.
- `accounting:phase3-integrity-check`: passed.
- Pint passed.
- TypeScript typecheck passed.
- Vite production build passed with the existing chunk-size warning only.
- `qa:verify-local`: Unit, Integration, Invariants, and Concurrency suites passed.
- Phase 10 feature gate passed:
  - `Phase10BranchWarehouseOperationsTest`: 5 tests / 87 assertions passed.
  - `Phase10StockCountAdjustmentTest`: 5 tests / 57 assertions passed.
  - `Phase10TreasuryTransferTest`: 4 tests / 34 assertions passed.

## Remaining Phase 10 Extensions

- Branch-level profitability views only after explicit owner approval of the GL branch-dimension accounting model.
- Optional branch-specific GL mappings only after explicit owner approval.
- Optional branch-aware approval rules only after explicit owner approval.
