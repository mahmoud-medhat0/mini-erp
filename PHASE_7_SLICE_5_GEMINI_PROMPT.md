# MINI ERP - PHASE 7 SLICE 5 VAT REGISTER, REPORTS, AND GL RECONCILIATION

Execute only Phase 7 Slice 5 after Slices 2-4 are complete and verified.

Do not implement tax filing locks, online filing, e-invoicing, withholding tax, or jurisdiction-specific forms in this slice.

## Objective

Build read-only VAT register and VAT reporting:

- sales/output VAT register
- purchases/input VAT register
- VAT summary report
- VAT to GL reconciliation
- CSV export and print where permitted

Reports must read posted source documents and immutable posted accounting records only.

## Read First

Read and follow:

- `PHASE_7_TAX_VAT.md`
- `PHASE_7_TAX_VAT_POLICY_DECISION.md`
- Slices 2-4 implementation
- existing report services/controllers/pages under `App\Application\Reports`
- existing financial statement report authorization and CSV patterns
- existing EN/AR dictionaries

## Required Implementation

Implement services under `App\Application\Reports` or a tax reporting namespace consistent with the codebase:

- `VatRegisterReportService`
- `VatSummaryReportService`
- `VatToGlReconciliationService`

Report requirements:

- filter by document/accounting date, not `created_at`
- include only posted tax-impacting documents
- output and input VAT totals must reconcile to source document tax detail rows
- GL reconciliation must compare tax register totals against posted ledger movement in `output_tax_payable` and `input_tax_receivable` mapping accounts
- all amounts are integer minor units
- CSV exports preserve integer minor units
- report pages are dictionary-backed
- no company/branch/tenant scope

## Routes and UI

Add report routes under `/reports` or `/taxes/reports` according to current project conventions.

Minimum pages:

- VAT Register
- VAT Summary
- VAT to GL Reconciliation

Requirements:

- `reports.view` plus `view_financials` for financial report viewing
- `reports.export` plus `view_financials` for CSV export
- print uses `reports.print` plus `view_financials`
- `taxes.view` may be required in addition if routes live under `/taxes`
- no hardcoded visible text
- no hardcoded tax code/rate options
- warning payloads must be localization-ready

## Tests

Add tests covering:

- register includes posted sales/purchase tax documents
- register excludes drafts/cancelled documents
- date filters use document/accounting dates
- VAT summary totals equal register detail totals
- GL reconciliation matches mapped ledger movement
- missing tax mappings produce localized warning codes, not raw English
- CSV export totals match service totals
- authorization for view/export/print route access
- no unsupported scope columns
- no float or `/100` formatting in new report code

## Required Source Scans

Run and classify:

```powershell
rg -n "company_id|branch_id|tenant_id|currentCompany|currentBranch|Spatie Teams" laravel/app laravel/database laravel/routes laravel/resources/js laravel/tests
rg -n "created_at|updated_at" laravel/app/Application laravel/app/Http/Controllers laravel/tests/Feature
rg -n "\\/ 100|parseFloat|Number\\(|toFixed\\(|Math\\.round|\\(float\\)|round\\(" laravel/app laravel/resources/js laravel/tests
rg -n "VAT Register|VAT Summary|Input VAT|Output VAT|ضريبة" laravel/resources/js/Pages laravel/resources/js/Components
```

Non-empty scans require classification and fixes for unacceptable matches.

## Required Verification

Run from `laravel/` and wait for completion:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=Phase7Slice5
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=10
php artisan accounting:concurrency-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

## Final Report

Report:

1. report services/controllers/routes/pages
2. report filters and date fields used
3. reconciliation formula
4. warning codes
5. source scan classifications
6. test and verification results
7. features intentionally not implemented
