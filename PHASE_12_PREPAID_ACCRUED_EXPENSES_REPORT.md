# Phase 12 Prepaid & Accrued Expenses Report

Date: 2026-08-25

Status: COMPLETE & VERIFIED

## Scope

Phase 12 adds scheduled prepaid expense recognition and accrued expense posting as bounded accounting workflows after Phase 11 Expense Management.

This phase does not introduce payroll, rentals, tenant/company ownership, or a new branch security model.

## Implemented

- Added `prepaid_schedule` and `prepaid_recognition` tables.
- Added `accrual_schedule` and `accrual_entry` tables.
- Added Phase 12 accounting mapping keys:
  - `prepaid_expense_asset`
  - `accrued_expense_liability`
- Added default seeded accounts:
  - `1800` Prepaid Expenses
  - `2500` Accrued Expenses Payable
- Added services:
  - `PrepaidScheduleService`
  - `AccrualScheduleService`
- Added controllers and routes:
  - `/expenses/prepaids`
  - `/expenses/accruals`
- Added Inertia pages:
  - `resources/js/Pages/Expenses/Prepaids.tsx`
  - `resources/js/Pages/Expenses/Accruals.tsx`
- Added Expense navigation links with EN/AR dictionary-backed labels.
- Added exact integer monthly amount splitting with deterministic remainder allocation.
- Added period close blockers for pending approved/active prepaid recognitions and accrual entries.
- Added integrity checks for posted recognition/accrual rows without journal links.
- Added tests in `Phase12PrepaidAccruedExpenseTest`.

## Accounting Behavior

Prepaid recognition posts monthly:

- Dr Expense Account
- Cr Prepaid Expense Asset

Accrual entry posts monthly:

- Dr Expense Account
- Cr Accrued Expense Liability

Both flows use the existing PostingEngine, period guards, ledger immutability, integer minor units, and Spatie Activitylog audit path.

## Guardrails

- No `company_id`.
- No `tenant_id`.
- No `currentCompany` or `currentBranch`.
- Spatie Teams remain disabled.
- `branch_id` exists only on schedule headers as an optional owner-approved operational/reporting reference.
- Child recognition/entry rows do not carry branch scope; GL branch tagging flows through the generated journal lines.
- Currency is validated against the existing currency registry. No unsupported `currency.is_active` column is assumed.

## Additional Fixes Made During Verification

- Removed stale `Currency::query()->where('is_active', true)` assumptions from controllers and Phase 12 services because `currency` is a registry table without `is_active`.
- Added migration `2026_08_25_081000_extend_accounting_mapping_keys_for_phase12_expenses.php` to extend the PostgreSQL `accounting_account_mapping_key_check` constraint for Phase 12 mapping keys.
- Updated `accounting:inventory-concurrency-stress` so it updates global accounting mappings with explicit `branch_id => null`; this prevents accidental branch override selection during stress setup.
- Set PHPUnit test `memory_limit` to `512M` in `phpunit.xml` so the combined 621-test suite can run in one process after the project growth.

## Verification

- `php artisan migrate --force`: passed, nothing pending after applying Phase 12 migrations.
- `php artisan migrate:status`: all migrations ran, including:
  - `2026_08_25_080000_create_phase12_prepaid_accrual_tables`
  - `2026_08_25_081000_extend_accounting_mapping_keys_for_phase12_expenses`
- `php artisan db:seed --class=RbacSeeder`: passed.
- `php artisan db:seed --class=AccountingCoreSeeder`: passed.
- `php artisan db:seed --class=ExpenseCategorySeeder`: passed.
- `vendor/bin/pint --test`: passed.
- `php artisan test --filter=Phase12PrepaidAccruedExpenseTest --stop-on-failure`: 6 tests / 74 assertions passed.
- `php artisan test --filter=SecurityHardeningTest --stop-on-failure`: 6 tests / 397 assertions passed.
- `php artisan test --filter=Phase11ExpenseManagementTest --stop-on-failure`: 8 tests / 60 assertions passed.
- `php artisan test --filter=Phase3Slice9StressIntegrityTest --stop-on-failure`: 6 tests / 572 assertions passed.
- `php artisan test --testsuite=Unit --stop-on-failure`: 5 tests / 15 assertions passed.
- `php artisan test --testsuite=Feature --stop-on-failure`: 586 tests, 583 passed, 3 skipped / 4528 assertions passed.
- `php artisan test --testsuite=Integration --stop-on-failure`: 8 tests / 70 assertions passed.
- `php artisan test --testsuite=Concurrency --stop-on-failure`: 7 tests / 16 assertions passed.
- `php artisan test --testsuite=Invariants --stop-on-failure`: 15 tests / 522 assertions passed.
- `php artisan test --stop-on-failure`: 621 tests, 618 passed, 3 skipped / 5151 assertions passed.
- `php artisan concurrency:stress --workers=100`: passed.
- `php artisan accounting:concurrency-stress --workers=50`: passed.
- `php artisan accounting:stock-transfer-stress --workers=50`: passed.
- `php artisan accounting:inventory-concurrency-stress --workers=50`: passed.
- `php artisan accounting:phase3-integrity-check`: passed.
- `php artisan tokens:gc --batch=100`: passed.
- `npm run typecheck`: passed with 0 errors.
- `npm run build`: passed with the existing Vite chunk-size warning only.

## Next Product Track

Deployment remains parked. The next bounded product module can be Payroll or Rentals, but either one should start with an owner-facing policy decision pack because both affect money, permissions, and operational approvals.
