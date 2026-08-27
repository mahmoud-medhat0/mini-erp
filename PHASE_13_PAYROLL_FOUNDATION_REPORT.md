# Phase 13 Payroll Foundation Report

**Status:** COMPLETE & VERIFIED  
**Date:** 2026-08-25  
**Scope:** Payroll employee master data, payroll components, payroll run calculation, approval, posting, permissions, attachments, audit, and period-close integration.

> **No Multi-Tenant Policy:** Phase 13 preserves the single-installation ERP model. No `company_id`, no `tenant_id`, no Spatie Teams, no `currentCompany`, no `currentBranch`, and no Employee-to-User relationship were introduced.

## 1. Delivered Scope

- Added payroll master data:
  - `employee`
  - `payroll_component`
  - `employee_payroll_component`
  - `payroll_period`
  - `payroll_run`
  - `payroll_run_line`
  - `payroll_run_line_component`
- Added optional operational `branch_id` on employee/payroll run/payroll run line only for branch payroll filtering/reporting.
- Added payroll account mapping keys:
  - `payroll_expense`
  - `payroll_payable`
  - `payroll_deductions_payable`
- Seeded payroll GL accounts and default payroll components.
- Added payroll services for:
  - employee create/update
  - payroll component create/update/delete
  - employee recurring component assignment/removal
  - payroll run create/regenerate/submit/approve/post/cancel
- Added PostingEngine-backed payroll posting:
  - Dr payroll expense
  - Cr payroll payable for net salary
  - Cr payroll deductions payable for deductions
- Added period close blockers for unposted payroll runs.
- Added attachment entity authorization for `employee` and `payroll_run`.
- Added Inertia pages:
  - `/payroll/employees`
  - `/payroll/components`
  - `/payroll/runs`
- Added Payroll navigation group with permission-aware visibility.
- Added EN/AR dictionary entries for visible payroll UI text.

## 2. Security And Permission Rules

Payroll routes require granular permissions and the sensitive payroll visibility permission:

- Payroll pages/actions require payroll permissions plus `view_payroll`.
- Payroll posting requires `payroll.post`, `view_payroll`, and `view_financials`.
- Payroll navigation is hidden unless the user has `payroll.view` and `view_payroll`.
- Attachments on payroll entities require entity-specific payroll permissions and `view_payroll`.

## 3. Accounting And Integrity Rules

- Payroll amounts use integer minor units only.
- Percentage components use integer basis-points math:
  - `intdiv(($baseSalaryMinor * $rateBps) + 5000, 10000)`
- Payroll deductions cannot exceed payroll gross.
- Payroll posting is idempotent and state-guarded.
- Posted payroll journals use the existing immutable ledger spine and PostingEngine.
- Payroll component account snapshots are preserved on run lines.
- Payroll run approval allocates an atomic global number using the existing sequence allocator.
- Payroll does not create AR/AP subledger entries.

## 4. Scope Guardrails

Confirmed absent from Payroll schema and services:

- `company_id`
- `tenant_id`
- `employee.user_id`
- Company-owned payroll
- branch tenancy/security scope
- branch-based document numbering
- Spatie Teams

Allowed operational reference:

- `employee.branch_id`
- `payroll_run.branch_id`
- `payroll_run_line.branch_id`

These are reporting/operation dimensions only.

## 5. Verification Results

All commands were run locally from `laravel/`.

```powershell
php artisan migrate --force
php artisan migrate:status
php artisan db:seed --class=AccountingCoreSeeder
php artisan db:seed --class=PayrollComponentSeeder
vendor/bin/pint --test
php artisan test --filter=Phase13PayrollFoundationTest --stop-on-failure
php artisan test --filter=SecurityHardeningTest --stop-on-failure
php artisan test --filter=Phase3Slice9StressIntegrityTest --stop-on-failure
php artisan test --testsuite=Concurrency --stop-on-failure
php artisan test --stop-on-failure
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:stock-transfer-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Result summary:

- `migrate --force`: nothing to migrate after Phase 13 migrations.
- `migrate:status`: all migrations Ran, including `2026_08_25_090000_create_phase13_payroll_tables` and `2026_08_25_091000_extend_accounting_mapping_keys_for_phase13_payroll`.
- `AccountingCoreSeeder`: completed.
- `PayrollComponentSeeder`: completed.
- Pint: passed.
- `Phase13PayrollFoundationTest`: 6 tests / 90 assertions passed.
- `SecurityHardeningTest`: 6 tests / 413 assertions passed.
- `Phase3Slice9StressIntegrityTest`: 6 tests / 607 assertions passed.
- Full Laravel PHPUnit suite: 627 tests, 624 passed, 3 skipped / 5292 assertions.
- Concurrency suite: 7 tests / 16 assertions passed.
- `concurrency:stress --workers=100`: passed; number sequence values unique and contiguous; idempotency callback executed exactly once.
- `accounting:concurrency-stress --workers=50`: passed.
- `accounting:stock-transfer-stress --workers=50`: passed.
- `accounting:inventory-concurrency-stress --workers=50`: passed.
- `accounting:phase3-integrity-check`: passed.
- `tokens:gc --batch=100`: passed.
- TypeScript typecheck: passed with 0 errors.
- Vite production build: passed with the existing chunk-size warning only.

## 6. Remaining Product Work

Payroll foundation is complete. Future payroll extensions should be separate bounded slices, for example:

- salary payment execution against cash/bank accounts
- payroll payslip generation/export
- payroll reports
- employee loans/advances
- attendance or time-based payroll inputs
- statutory payroll tax/social insurance rules, if explicitly approved

Deployment remains parked until the owner/operator resumes staging or production cutover decisions.
