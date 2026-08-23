# MINI ERP - PHASE 7 SLICE 6 TAX PERIOD FILING AND LOCKING CONTROLS

Execute only Phase 7 Slice 6 after Slice 5 is complete and verified.

Do not implement online filing submission, e-invoicing, withholding tax, or jurisdiction-specific authority integration in this slice.

## Objective

Build bounded tax period and filing controls:

- tax period records
- draft tax return calculation from VAT reports
- filed status
- tax period locks for tax-affecting postings
- correction policy through later-period documents or explicit reversal rules

## Read First

Read and follow:

- `PHASE_7_TAX_VAT.md`
- `PHASE_7_TAX_VAT_POLICY_DECISION.md`
- Slices 2-5 implementation
- current `FinancialPeriod`, `FiscalYear`, and `PeriodGuard`
- current sales/purchasing posting services
- current reports/export patterns

## Required Implementation

Suggested schema, subject to local inspection and Slice 1 owner decisions:

- `tax_period`
  - UUID primary key
  - `period_label`
  - `start_date`
  - `end_date`
  - `status` such as `open`, `draft_return`, `filed`, `reopened`
  - filing metadata (`filed_at`, `filed_by`, `file_reference`, optional notes)
  - timestamps
- `tax_return`
  - UUID primary key
  - `tax_period_id`
  - `number`
  - output tax minor
  - input tax minor
  - net payable/refundable minor
  - status such as `draft`, `filed`, `reversed`
  - generated/approved/filed metadata
  - immutable JSON snapshot of report totals/details if approved

Rules:

- no `company_id`, `branch_id`, or `tenant_id`
- tax periods are global to this ERP installation unless owner later defines otherwise
- periods must not overlap
- filed tax return financial totals are immutable
- filed tax periods block new tax-affecting postings with document dates inside the filed range
- corrections after filing must use explicitly allowed later-period adjustment documents unless owner approved reopening
- do not mutate posted journals/ledgers

## Services

Create services for:

- tax period CRUD/opening
- tax return draft generation from Slice 5 services
- file tax return
- optional reopen/reverse only if approved by Slice 1 decision pack
- guard service used by sales/purchasing posting paths to block filed tax periods

Tax guard must be tested in every tax-affecting posting path touched by Slices 3-4.

## UI

Create Inertia pages for:

- tax periods list
- tax return detail/draft
- file action

Requirements:

- permission-aware controls
- no hardcoded visible text
- no raw backend English warnings
- export/print controls only with exact permissions

## Permissions

Use or deliberately add exact permissions:

- view: `taxes.view`
- configure/edit tax period: `taxes.edit` or explicitly added `taxes.configure`
- file tax return: explicitly added `taxes.file` is recommended
- reverse/reopen filed tax return: explicitly added `taxes.reverse` or a separate owner-approved permission
- reports: `reports.view`, `reports.export`, `reports.print`, `view_financials`

Do not use `settings.configure`.

## Tests

Add tests covering:

- tax period non-overlap
- tax return draft totals match VAT summary service
- filing stores immutable snapshot
- filed period blocks customer invoice/credit note/sales return tax posting
- filed period blocks supplier bill/adjustment note/purchase return tax posting
- permissions for view/edit/file/reopen/reverse
- no unsupported scope columns
- no created_at/updated_at financial filtering
- idempotent file action
- DB immutability for filed tax return totals/snapshots

## Required Source Scans

Run and classify:

```powershell
rg -n "company_id|branch_id|tenant_id|currentCompany|currentBranch|Spatie Teams" laravel/app laravel/database laravel/routes laravel/resources/js laravel/tests
rg -n "created_at|updated_at" laravel/app/Application laravel/app/Http/Controllers laravel/tests/Feature
rg -n "\\/ 100|parseFloat|Number\\(|toFixed\\(|Math\\.round|\\(float\\)|round\\(" laravel/app laravel/resources/js laravel/tests
rg -n "settings\\.configure|Gate::authorize\\('settings\\.configure'|can\\('settings\\.configure'" laravel/app laravel/resources/js laravel/tests
```

Non-empty scans require classification and fixes for unacceptable matches.

## Required Verification

Run from `laravel/` and wait for completion:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=Phase7Slice6
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=10
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:settlement-concurrency-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Add a tax filing stress command if concurrent filing can race.

## Final Report

Report:

1. schema changes
2. filing state machine
3. tax-period guard integration points
4. permissions added/reused
5. source scan classifications
6. tests and verification results
7. features intentionally not implemented
