# MINI ERP - PHASE 7 SLICE 2 TAX CODE AND TAX RATE FOUNDATION

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


Execute only Phase 7 Slice 2 after Slice 1 policy decisions are recorded.

Do not implement sales/purchasing tax posting, tax filing, VAT returns, withholding tax, e-invoicing, or jurisdiction-specific compliance in this slice.

## Objective

Build the tax master-data foundation:

- tax code master data
- tax rate effective-date records
- validation services
- permissions
- audit
- Inertia CRUD pages
- no document posting changes yet

## Read First

Read and follow:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_7_TAX_VAT.md`
- `PHASE_7_TAX_VAT_POLICY_DECISION.md`
- current migrations/models/services for AccountingAccountMapping, Currency, Sales/Purchasing documents, RBAC, audit, reports, and dictionaries

## Required Implementation

Create only the bounded foundation needed for future slices.

Suggested schema, subject to local inspection and Slice 1 decisions:

- `tax_code`
  - UUID primary key
  - unique `code`
  - multilingual `name`
  - `tax_type` enum-like string such as `vat`
  - `calculation_mode` such as `exclusive`, `inclusive`, or `exempt`
  - `recoverability_mode` such as `full`, `none`
  - `is_system`
  - `is_active`
  - timestamps
- `tax_rate`
  - UUID primary key
  - `tax_code_id`
  - `rate_bps` integer
  - `effective_from`
  - nullable `effective_to`
  - `is_active`
  - timestamps

Rules:

- no `company_id`, `branch_id`, `tenant_id`, warehouse/location, or jurisdiction ownership columns
- no hardcoded rates in TSX
- enforce valid enum/status values with PostgreSQL check constraints where supported
- enforce non-overlapping active effective-date ranges for the same tax code if feasible; otherwise enforce in service tests and document DB limitation
- use integer `rate_bps`; no decimal/float rate storage
- seed only owner-approved default tax codes/rates
- if `output_tax_payable` / `input_tax_receivable` mappings already exist, verify them; if missing, add deliberately with tests

## Services

Create application services for:

- tax code CRUD
- tax rate CRUD
- effective-rate lookup by tax code and document date
- integer tax calculation helper returning:
  - taxable base minor
  - tax minor
  - gross minor
  - rate bps
  - rounding policy used

The helper must be side-effect free and must not touch sales/purchasing documents in this slice.

## UI

Create Inertia pages for tax code/rate management.

Requirements:

- no hardcoded visible text in TSX
- EN/AR dictionary keys for labels, statuses, empty states, validation summaries, buttons, and table headers
- rate inputs should make the integer scale clear without using floats internally
- active/inactive toggles
- system tax codes protected from deletion
- deletion blocked when rates or future document links exist
- permission-aware controls

## Permissions

Use exact permissions:

- view tax master data: `taxes.view`
- create/update tax configuration: existing `taxes.edit`, unless a more granular permission is explicitly added and tested
- delete/deactivate: `taxes.edit` or a deliberately added `taxes.delete`
- GL mapping setup: `accounting.mappings`

Do not use `settings.configure`.

If new permissions are added, update:

- `config/erp_rbac.php`
- seeders
- dictionaries
- tests
- docs

## Tests

Add feature/unit tests covering:

- schema exists and has no unsupported scope columns
- tax code CRUD
- tax rate CRUD
- invalid enum/status/rate rejection
- effective-date lookup
- exact integer tax calculation
- no float/rounding functions in tax services
- system tax code delete protection
- used tax code delete protection
- permissions for every route/action
- Inertia props and dictionary-backed option sourcing

## Required Source Scans

Run and classify:

```powershell
rg -n "company_id|branch_id|tenant_id|currentCompany|currentBranch|Spatie Teams" laravel/app laravel/database laravel/routes laravel/resources/js laravel/tests
rg -n "\\/ 100|parseFloat|Number\\(|toFixed\\(|Math\\.round|\\(float\\)|round\\(" laravel/app laravel/resources/js laravel/tests
rg -n "VAT|Tax Code|Tax Rate|Output Tax|Input Tax|ضريبة" laravel/resources/js/Pages laravel/resources/js/Components
rg -n "settings\\.configure|Gate::authorize\\('settings\\.configure'|can\\('settings\\.configure'" laravel/app laravel/resources/js laravel/tests
```

Non-empty scans require classification and fixes for unacceptable matches.

## Required Verification

Run from `laravel/` and wait for completion:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=Phase7Slice2
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

1. migrations added
2. tables/columns/indexes/constraints
3. services/controllers/routes/pages
4. permissions added/reused
5. seeded tax codes/rates and owner decision basis
6. remaining unsupported or owner-decision tax features
7. source scan classifications
8. test and verification results
