# MINI ERP - PHASE 7 SLICE 7 UX, EXPORT/PRINT, E2E SMOKE, SOURCE SCANS, AND CLOSE-OUT

Execute only Phase 7 Slice 7 after Slices 1-6 are complete or explicitly skipped with reason.

This is the Phase 7 finalization slice.

Do not add new tax accounting behavior except targeted fixes required for internal consistency.

Important execution rule:

- run verification commands sequentially; do not launch overlapping/background PHPUnit, migration, stress, or build jobs against the same database/filesystem
- if a command fails, stop, fix the root cause, rerun the failed command, then rerun the affected broader suite
- do not declare success from an earlier async/background result if later code changed

## Objective

Close out Phase 7 with:

- tax/VAT UX review
- export/print consistency
- permission review
- tax register/report close-out
- source scans
- final verification report

Create:

- `PHASE_7_FINAL_VERIFICATION_REPORT.md`

Update:

- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

## Review Scope

Review all Phase 7 surfaces:

- tax code/rate pages
- sales tax UX
- purchasing tax UX
- VAT register reports
- VAT summary reports
- VAT to GL reconciliation
- tax period filing pages if implemented
- reports hub/app navigation
- permissions and exports
- Phase 7 test suites and stress commands
- migrations and seeders added by Slices 2-6

Requirements:

- no hardcoded visible TSX text
- no hardcoded tax code/rate option lists in TSX
- no hardcoded tax module/team/permission label maps inside TSX pages or shared navigation/components
- EN/AR dictionaries updated
- RTL works
- permission-aware actions
- no empty action containers
- no nested card clutter
- no raw backend English warnings rendered directly
- long tax codes/document numbers do not overflow controls
- all exports match service totals
- all report/filter dates use document/accounting/tax-period dates, not row timestamps
- tax report/register pages read persisted tax snapshots only, not current tax rates
- filed tax return pages are read-only with edit/destructive controls absent
- tax filing/period lock UI must not imply online authority submission, e-invoicing, withholding tax, jurisdiction support, or tax authority payment posting unless implemented

## Required Source Scans

Run and classify:

```powershell
rg -n "company_id|branch_id|tenant_id|currentCompany|currentBranch|Spatie Teams" laravel/app laravel/database laravel/routes laravel/resources/js laravel/tests
rg -n "warehouse_id|location_id|jurisdiction_id|tax_registration_id|withholding|reverse_charge|e_invoice|e-invoice" laravel/app laravel/database laravel/routes laravel/resources/js laravel/tests
rg -n "VAT|Tax Code|Tax Rate|Input Tax|Output Tax|Tax Period|Tax Return|ضريبة" laravel/resources/js/Pages laravel/resources/js/Components
rg -n "created_at|updated_at" laravel/app/Application laravel/app/Http/Controllers laravel/tests/Feature
rg -n "\\/ 100|parseFloat|Number\\(|toFixed\\(|Math\\.round|\\(float\\)|round\\(" laravel/app laravel/resources/js laravel/tests
rg -n "settings\\.configure|Gate::authorize\\('settings\\.configure'|can\\('settings\\.configure'" laravel/app laravel/resources/js laravel/tests
rg -n "dump\\(|dd\\(|ray\\(|fwrite\\(|var_dump\\(|console\\.log\\(" laravel/app laravel/resources/js laravel/tests
```

Non-empty scans require a classification table with match, classification, and action.

Additional close-out checks:

```powershell
git status --short
git diff --stat
php artisan route:list --path=taxes
php artisan route:list --path=reports
```

Classify all remaining changed/untracked Phase 7 files. Do not revert user work.

## Required Verification

Run from `laravel/` and wait for completion:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=Phase7Slice2
php artisan test --filter=Phase7Slice3
php artisan test --filter=Phase7Slice4
php artisan test --filter=Phase7Slice5
php artisan test --filter=Phase7Slice6
php artisan test --filter=Phase7
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=10
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:settlement-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan accounting:fixed-asset-depreciation-stress --workers=50
php artisan accounting:fixed-asset-disposal-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan accounting:phase3-stress --workers=50
php artisan accounting:sales-tax-stress --workers=50
php artisan accounting:purchasing-tax-stress --workers=50
php artisan accounting:tax-filing-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Also run every Phase 7 stress command added by Slices 3-6 if any command name differs from the list above.

Test hygiene requirements:

- no test may depend on execution order
- no test may repair global state by broad `DatabaseSeeder` reruns at the end of the test; create isolated records in `setUp()` or inside the test instead
- if a test intentionally deletes mappings/permissions/master data, it must use local test records or explicit transaction/database refresh isolation
- run Phase 7 tests after any such fix and then the full Laravel test suite

## E2E Smoke

Use existing browser tooling if available. Do not introduce a large new framework without owner approval.

Minimum smoke paths when tooling exists:

- login as tax/financial user
- tax code/rate page loads
- sales document tax summary loads
- purchasing document tax summary loads
- VAT register/report loads
- unauthorized user cannot access tax financial pages

If browser tooling is absent, document what is missing and add route/feature coverage instead.

## Final Report

`PHASE_7_FINAL_VERIFICATION_REPORT.md` must include:

1. slices completed
2. migrations applied
3. routes/pages/services/models
4. permissions added/reused
5. GL mappings
6. sales and purchasing tax posting examples
7. VAT report/reconciliation formulas
8. source scan classifications
9. test results
10. stress results
11. remaining owner decisions
12. explicit statement that no tenant/company/branch/jurisdiction/warehouse scope was introduced
13. any source scan matches that remain and why they are acceptable
14. test hygiene/pollution fixes, if any
15. exact commands not run, if any, with reason and risk

Do not mark Phase 7 complete if any required command failed, timed out, or was not run.
