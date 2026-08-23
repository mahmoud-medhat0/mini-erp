# MINI ERP - PHASE 6 SLICE 1 FIXED ASSET POLICY DECISION PACK

You are continuing the active Laravel + Inertia Mini ERP migration.

Execute only Phase 6 Slice 1.

This slice is docs-only unless all owner decisions below are already explicitly recorded in current project documents.

Do not implement migrations, models, services, controllers, routes, TSX pages, seeders, commands, or tests in this slice unless the owner has explicitly instructed implementation in the same request.

## Reada First

Read and follow:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_6_FIXED_ASSETS.md`
- `PHASE_5_FINAL_VERIFICATION_REPORT.md`
- current Laravel code for accounting mappings, PostingEngine, PeriodGuard, RBAC, attachments, audit, and reports

## Objective

Create a fixed asset policy decision pack that explains the business choices required before implementing fixed asset accounting.

Create:

- `PHASE_6_FIXED_ASSETS_POLICY_DECISION.md`

Update:

- `NEXT_TASKS.md`
- `IMPLEMENTATION_STATUS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

## Required Owner Decisions

The decision pack must ask the owner to approve:

1. Depreciation method:
   - recommended: straight-line only for Mini ERP Phase 6
   - not implemented now: declining balance, units of production, component depreciation
2. Depreciation start rule:
   - in-service date month
   - next month after in-service date
   - exact day proration
3. Partial month convention:
   - full month
   - no month
   - daily proration
4. Salvage/residual value:
   - required manually per asset
   - optional default zero
5. Useful life:
   - manual months per asset
   - default by category with asset-level override
6. Existing/opening assets:
   - register only with opening accumulated depreciation and no GL capitalization
   - post capitalization through a clearing account
7. New acquisitions:
   - manual fixed asset capitalization voucher
   - supplier bill integration in a later owner-approved slice
   - no purchasing integration in Phase 6
8. GL mapping strategy:
   - fixed asset cost account
   - accumulated depreciation account
   - depreciation expense account
   - gain on disposal account
   - loss on disposal account
   - optional acquisition/disposal clearing account
9. Disposal policy:
   - sale proceeds through cash/bank
   - sale proceeds through clearing
   - scrap with zero proceeds
10. Reversal policy:
   - corrections by reversal only
   - no mutation of posted journals/ledgers
11. Asset identity:
   - global asset code
   - no company/branch/location/user/custodian relationship unless approved later
12. Permissions:
   - exact use of `fixedAssets.*`, `reports.*`, `view_financials`, and `accounting.mappings`

## Recommended Path

Recommend the safest default for Mini ERP:

- straight-line depreciation
- useful life in months
- optional salvage value defaulting to zero
- depreciation starts in the month after in-service date unless owner prefers in-service month
- existing assets can be registered with opening accumulated depreciation and no GL capitalization
- new assets can be capitalized through a fixed asset clearing account, not direct AP/cash integration in the first implementation
- no supplier bill integration until a later bounded owner-approved slice
- no branch/location/user/custodian relationships
- corrections by reversal only

Make the recommendation clear, but do not present it as approved.

## Non-Negotiable Rules

Do not introduce:

- tenant/company/branch scope
- Spatie Teams
- warehouse/location semantics
- employee/custodian ownership
- supplier bill integration unless explicitly approved
- tax depreciation books
- multiple depreciation methods as implemented scope
- any Laravel implementation code

Do not replace one assumption with another.

If a relationship or policy is not explicitly approved, classify it as:

`OWNER DECISION REQUIRED - DO NOT IMPLEMENT`

## Decision Pack Content

`PHASE_6_FIXED_ASSETS_POLICY_DECISION.md` must include:

- Arabic owner-facing summary
- English technical summary
- plain-language explanation of fixed asset accounting
- comparison table for depreciation methods
- comparison table for depreciation start/partial-period rules
- acquisition/opening asset options
- disposal options
- GL mapping requirements
- exact owner decision checklist
- recommended path
- "not implemented yet" declaration
- future slice dependency notes

## Verification

Because this is docs-only:

- run `git diff --stat`
- run a source scan proving no Phase 6 Laravel implementation was added:

```powershell
rg -n "fixed_asset|fixedAssets|depreciation|asset disposal|asset_disposal|accumulated_depreciation" laravel/app laravel/database laravel/routes laravel/resources/js laravel/tests
```

If matches already exist from old baseline, classify them. Do not call non-empty scans clean.

Run formatting/check commands only if code files were changed. If this remains docs-only, do not claim code tests prove implementation.

## Final Report

Report:

1. files created/changed
2. implementation added: must be zero unless owner explicitly approved otherwise
3. owner decisions still required
4. source scan results and classification
5. next file to execute after owner decision

