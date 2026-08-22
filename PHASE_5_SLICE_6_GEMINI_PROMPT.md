# MINI ERP - PHASE 5 SLICE 6 UX, EXPORT/PRINT, E2E SMOKE & CLOSE-OUT

You are continuing the active Laravel + Inertia Mini ERP migration.

Execute only Phase 5 Slice 6 after Slices 1-5 are complete or explicitly skipped.

Do not start payroll, taxes, fixed assets, rentals, budgeting, deployment, or unrelated ERP modules in this pass.

## Read First

Read and follow:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_5_FINANCIAL_STATEMENTS_PERIOD_CLOSE.md`
- all completed Phase 5 slice reports/prompts

## Objective

Close out Phase 5 with polished UX consistency, export/print behavior where bounded, browser smoke coverage for critical financial pages, and documentation/status updates.

## Non-Negotiable Rules

Do not introduce:

- tenant/company/branch scope
- Spatie Teams
- hardcoded user-facing text in TSX
- hardcoded team/tenant/currentCompany/currentBranch props
- broad permission shortcuts
- new accounting logic beyond fixes needed for Phase 5 verification

## UX Hardening

Review Phase 5 pages:

- financial statement mapping page
- Balance Sheet
- Income Statement
- Cash Flow Statement
- Accounting Periods / close readiness
- Reports Hub links
- AppLayout navigation entries

Requirements:

- all visible text comes from EN/AR dictionaries or backend-provided multilingual data
- no hardcoded labels, statuses, empty states, table headings, button names, or error text in TSX
- all actions are permission-aware using exact permissions
- pages remain usable in EN and AR/RTL
- no nested card clutter
- no landing pages
- empty states are useful and operational
- long account/report names do not overflow buttons/tables

## Export/Print

If export/print exists:

- export requires `reports.export` plus `view_financials`
- print requires `reports.print` plus `view_financials`
- CSV exports must not use float formatting for money calculations
- print pages must not mutate data
- report totals in UI, export, and backend service must match

## E2E Smoke

Use the existing browser testing setup if one exists.

If Playwright/Dusk is not installed, do not introduce a large new test framework casually. Instead:

- inspect package/composer setup
- add the smallest reasonable smoke coverage using available tooling
- document if browser E2E needs a separate owner-approved setup slice

Minimum smoke paths when tooling exists:

- login
- Reports Hub visible to a financial user
- Balance Sheet loads
- Income Statement loads
- Cash Flow loads if implemented
- Periods page shows close/reopen controls only for permitted users
- unauthorized user cannot access financial statements

## Close-Out Documentation

Update:

- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

Create:

- `PHASE_5_FINAL_VERIFICATION_REPORT.md`

Include:

- migrations applied
- permissions added/reused
- routes/pages added
- UI hardcoded text scan result
- remaining owner decisions
- test results
- skipped tests and why
- local PostgreSQL-only test coverage, if applicable

## Required Verification

Run:

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

Also run a targeted source scan:

```powershell
rg -n "company_id|branch_id|tenant_id|currentCompany|currentBranch|Spatie Teams" .
rg -n "Balance Sheet|Income Statement|Cash Flow|Close Period|Reopen Period" laravel/resources/js/Pages laravel/resources/js/Components
```

For the text scan, investigate results and distinguish translation keys/imports from hardcoded visible UI text.
