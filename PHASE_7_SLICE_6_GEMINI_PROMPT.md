# MINI ERP - PHASE 7 SLICE 6 TAX PERIOD FILING AND LOCKING CONTROLS

Execute only Phase 7 Slice 6 after Slice 5 is complete and verified.

Do not implement online filing submission, e-invoicing, withholding tax, or jurisdiction-specific authority integration in this slice.

## Objective

Build bounded tax period and filing controls:
al
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
- `TaxCalculationService`, tax snapshot fields from Slices 3-4, VAT report services from Slice 5
- existing `accounting:sales-tax-stress` and `accounting:purchasing-tax-stress`
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
- tax period identity is the date range and/or monthly `period_label`; do not infer fiscal-year/company ownership
- tax return numbers must be generated through the existing atomic numbering/idempotency patterns; no ad hoc max+1
- tax return draft generation must read Slice 5 report services and persisted posted tax snapshots; do not recalculate historical tax from current tax rates
- draft returns may be regenerated while the tax period is not filed; filed returns must preserve their immutable snapshot
- filed tax return financial totals are immutable
- filed tax periods block new tax-affecting postings with document dates inside the filed range
- the guard must check the business document/accounting date, never `created_at` or `updated_at`
- corrections after filing must use explicitly allowed later-period adjustment documents unless owner approved reopening
- for this slice, do not implement reopen/reverse unless `PHASE_7_TAX_VAT_POLICY_DECISION.md` contains explicit owner approval; otherwise show it as intentionally not implemented
- do not mutate posted journals/ledgers
- filing must not post settlement/payment JVs unless explicitly required by the policy decision; it only locks and snapshots VAT results in this slice
- if payment/settlement of tax authority is not already modeled, report it as intentionally not implemented

## Services

Create services for:

- tax period CRUD/opening
- tax return draft generation from Slice 5 services
- file tax return
- optional reopen/reverse only if explicitly approved by Slice 1 decision pack
- guard service used by sales/purchasing posting paths to block filed tax periods

Tax guard must be tested in every tax-affecting posting path touched by Slices 3-4.

Concurrency rules:

- file action must run in a database transaction and lock the tax period/return row(s)
- concurrent file attempts for the same period must create exactly one filed return and return the same durable result or a stable already-filed response
- DB constraints or transactional guards must prevent multiple active filed returns for the same tax period
- failed filing must not leave a partially filed period or mutable snapshot

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
- filing confirmation must show totals from the immutable draft snapshot that will be filed
- filed returns must render as read-only; destructive/edit controls must be absent, not only disabled
- all warning/error payloads must use stable codes plus dictionary keys

## Permissions

Use or deliberately add exact permissions:

- view: `taxes.view`
- configure/edit tax period: `taxes.edit` or explicitly added `taxes.configure`
- file tax return: explicitly added `taxes.file` is recommended
- reverse/reopen filed tax return: do not add unless owner explicitly approved reopen/reverse; if approved, use `taxes.reverse` or a separate owner-approved permission
- reports: `reports.view`, `reports.export`, `reports.print`, `view_financials`

Do not use `settings.configure`.

## Tests

Add tests covering:

- tax period non-overlap
- tax return draft totals match VAT summary service
- tax return draft snapshot uses persisted posted tax snapshots and does not change when tax rates change later
- filing stores immutable snapshot
- filed period blocks customer invoice/credit note/sales return tax posting
- filed period blocks supplier bill/adjustment note/purchase return tax posting
- filed period allows non-tax-affecting documents only if the existing document lifecycle already permits them and no tax lines/postings are involved
- permissions for view/edit/file/reopen/reverse
- no unsupported scope columns
- no created_at/updated_at financial filtering
- idempotent file action
- concurrent filing creates exactly one filed return and one immutable snapshot
- DB immutability for filed tax return totals/snapshots
- no tax settlement/payment posting is created in this slice unless explicitly approved

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
php artisan test --filter=Phase7Slice3
php artisan test --filter=Phase7Slice4
php artisan test --filter=Phase7Slice5
php artisan test --filter=Phase7Slice6
php artisan accounting:sales-tax-stress --workers=50
php artisan accounting:purchasing-tax-stress --workers=50
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=10
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:settlement-concurrency-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan accounting:tax-filing-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Add `accounting:tax-filing-stress` if it does not exist; it must prove concurrent filing/idempotency for the same period and verify no partial mutable state is left behind.

## Final Report

Report:

1. schema changes
2. filing state machine
3. tax-period guard integration points
4. permissions added/reused
5. concurrency/idempotency behavior for filing
6. tax return snapshot source and immutability protections
7. source scan classifications
8. tests and verification results
9. features intentionally not implemented
