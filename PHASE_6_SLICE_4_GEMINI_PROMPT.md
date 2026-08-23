# MINI ERP - PHASE 6 SLICE 4 DEPRECIATION SCHEDULE ENGINE

Execute only Phase 6 Slice 4 after the asset register and owner-approved depreciation policy are complete.

Do not post depreciation to GL in this pass.

## Read First

- `PHASE_6_FIXED_ASSETS.md`
- `PHASE_6_FIXED_ASSETS_POLICY_DECISION.md`
- completed Slice 2 and Slice 3 reports
- current FinancialPeriod/FiscalYear models and PeriodGuard rules

## Objective

Build a deterministic depreciation schedule engine for active fixed assets, without creating journal or ledger entries.

## Scope

Create schedule storage only if needed:

- `fixed_asset_depreciation_schedule`

Suggested fields:

- `id`
- `fixed_asset_id`
- `financial_period_id`
- `period_start_date`
- `period_end_date`
- `depreciation_minor`
- `accumulated_depreciation_minor`
- `net_book_value_minor`
- `status` planned/posted/reversed/skipped
- timestamps

Rules:

- generate schedules using owner-approved depreciation method only
- integer minor units only
- deterministic remainder allocation; total depreciation must equal depreciable base
- depreciable base = cost minus salvage minus opening accumulated depreciation where applicable
- never depreciate below salvage value
- schedule dates must come from FinancialPeriod/FiscalYear or explicit in-service/disposal rules
- no `created_at` financial filtering
- no GL posting in this slice

## Concurrency/Idempotency

Schedule generation must be idempotent:

- repeated generation produces the same rows or updates only safe draft/planned rows
- posted rows must not be mutated
- lock the asset row when regenerating schedule

## UI

Add schedule preview on the asset detail page or a dedicated schedule page only if consistent with current UI.

Visible text must be dictionary-backed.

## Permissions

- view schedules: `fixedAssets.view` plus `view_financials`
- regenerate unposted schedule: `fixedAssets.edit` plus `view_financials`

## Tests

Cover:

- straight-line integer schedule totals exactly equal depreciable base
- remainder cents/minor units are allocated deterministically
- opening accumulated depreciation reduces remaining depreciable base
- salvage value floor is respected
- schedule generation starts according to owner policy
- repeated generation is idempotent
- posted schedule rows are not mutated
- closed period status does not matter for preview but must be visible to later posting checks
- no floats or `round()`
- no unsupported company/branch/custodian columns

## Source Scans

Run and classify:

```powershell
rg -n "\\/ 100|parseFloat|Number\\(|toFixed\\(|Math\\.round|\\(float\\)|round\\(" laravel/app laravel/resources/js laravel/tests
rg -n "created_at|updated_at" laravel/app/Application laravel/tests/Feature
rg -n "company_id|branch_id|tenant_id|custodian|employee_id|warehouse_id|location_id" laravel/app laravel/database laravel/routes laravel/resources/js laravel/tests
```

## Verification

Run the full Phase 6 verification gate from `PHASE_6_FIXED_ASSETS.md`.

## Final Report

Report schedule formulas, integer remainder handling, idempotency, scans, and verification results.

