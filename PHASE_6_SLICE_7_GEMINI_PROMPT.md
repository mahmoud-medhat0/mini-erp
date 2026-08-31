# MINI ERP - PHASE 6 SLICE 7 REPORTS, UX, EXPORT/PRINT, E2E SMOKE, AND CLOSE-OUT

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


Execute only Phase 6 Slice 7 after Slices 1-6 are complete or explicitly skipped with reason.

This is the Phase 6 finalization slice.

Do not add new fixed asset accounting behavior except targeted fixes required for internal consistency.

## Objective

Close out Phase 6 with:

- fixed asset reports
- polished UX
- export/print consistency
- permission review
- source scans
- final verification report

Create:

- `PHASE_6_FINAL_VERIFICATION_REPORT.md`

Update:

- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

## Reports

Implement or verify bounded reports:

- fixed asset register
- depreciation schedule
- depreciation run history
- net book value report
- disposal history

Report requirements:

- read service-calculated values, not duplicated formulas in TSX
- use accounting dates and financial periods
- integer minor units only
- export totals must match service totals
- financial report viewing requires `reports.view` plus `view_financials`
- fixed asset operational pages require `fixedAssets.view`
- export requires `reports.export` or `fixedAssets.export` as documented, plus `view_financials` when financial values are shown
- print requires existing or explicitly added print permission

## UX

Review:

- fixed asset categories
- fixed asset register
- asset detail
- capitalization/opening action
- schedule preview
- depreciation run page
- disposal page
- reports hub/app navigation

Requirements:

- no hardcoded visible TSX text
- no hardcoded fixed-asset module/team/permission label maps inside TSX pages
- EN/AR dictionaries updated
- RTL works
- permission-aware actions
- no empty action containers
- no nested card clutter
- no raw backend English warnings rendered directly
- long asset names/codes do not overflow controls

## E2E Smoke

Use existing browser tooling if available. Do not introduce a large new framework without owner approval.

Minimum smoke paths when tooling exists:

- login as financial/fixed-assets user
- fixed asset register loads
- asset detail loads
- depreciation schedule/report loads
- depreciation run page loads if implemented
- unauthorized user cannot access financial asset pages

If browser tooling is absent, document what is missing and add route/feature coverage instead.

## Required Source Scans

Run and classify:

```powershell
rg -n "company_id|branch_id|tenant_id|currentCompany|currentBranch|Spatie Teams" laravel/app laravel/database laravel/routes laravel/resources/js laravel/tests
rg -n "custodian|employee_id|warehouse_id|location_id|branch_id|supplier_bill_id|purchase_order_id" laravel/app laravel/database laravel/routes laravel/resources/js laravel/tests
rg -n "Fixed Asset|Depreciation|Disposal|Net Book|Useful Life|Salvage|Asset Register" laravel/resources/js/Pages laravel/resources/js/Components
rg -n "created_at|updated_at" laravel/app/Application laravel/app/Http/Controllers laravel/tests/Feature
rg -n "\\/ 100|parseFloat|Number\\(|toFixed\\(|Math\\.round|\\(float\\)|round\\(" laravel/app laravel/resources/js laravel/tests
rg -n "settings\\.configure|Gate::authorize\\('settings\\.configure'|can\\('settings\\.configure'" laravel/app laravel/resources/js laravel/tests
```

Non-empty scans require a classification table with match, classification, and action.

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

Also run every Phase 6 stress command added by Slices 5-6.

## Final Report

`PHASE_6_FINAL_VERIFICATION_REPORT.md` must include:

1. slices completed
2. migrations applied
3. routes/pages/services/models
4. permissions added/reused
5. GL mappings
6. accounting examples for capitalization, depreciation, and disposal
7. source scan classifications
8. test results
9. stress results
10. remaining owner decisions
11. explicit statement that no tenant/company/branch/custodian scope was introduced

Do not mark Phase 6 complete if any required command failed, timed out, or was not run.
