# MINI ERP - PHASE 6 SLICE 3 CAPITALIZATION AND OPENING ASSET POSTING

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


Execute only Phase 6 Slice 3 after Slice 2 is complete and the owner-approved acquisition/opening policy exists.

Do not implement depreciation schedules, depreciation run posting, disposals, supplier bill integration, or tax books in this pass.

## Read First

- `PHASE_6_FIXED_ASSETS.md`
- `PHASE_6_FIXED_ASSETS_POLICY_DECISION.md`
- `PHASE_6_SLICE_2` implementation/report
- current PostingEngine, PeriodGuard, AccountingAccountMappingService, Journal models, and idempotency patterns

## Objective

Implement the owner-approved way to bring assets into the fixed asset register financially.

Supported modes must be limited to the approved policy. If no policy is recorded, stop and report `OWNER DECISION REQUIRED`.

Recommended bounded modes:

1. `opening_already_capitalized`
   - no GL posting
   - asset starts active with cost and opening accumulated depreciation
   - used for assets already represented in opening balances or existing ledger
2. `manual_capitalization`
   - posts Dr fixed asset cost / Cr fixed asset clearing
   - uses `PostingEngine`
   - idempotent and period-guarded

Do not build supplier bill integration unless explicitly approved later.

## Accounting Requirements

For manual capitalization:

- debit owner-approved fixed asset cost account
- credit owner-approved fixed asset clearing account
- entry date = capitalization date
- financial period must be open/reopened
- currency must match linked GL accounts or be handled through existing FX rules explicitly
- journal source type must be fixed-asset-specific
- posted ledger entries immutable
- repeated post request must not duplicate journals or ledgers

Opening already capitalized:

- must not create journal/ledger entries
- must be clearly marked so reports can distinguish opening registration from posted capitalization
- opening accumulated depreciation must be validated between 0 and cost minus salvage where applicable

## Data Model

Add only fields/tables required by the approved model, such as:

- capitalization status/date/posting metadata on `fixed_asset`
- optional `fixed_asset_capitalization` document table if needed for audit/idempotency
- journal reference columns for posted capitalization if needed

Do not add company/branch/user/custodian/supplier/purchase-order relationships.

## Permissions

- preview/view: `fixedAssets.view` plus `view_financials`
- post capitalization: `fixedAssets.post` plus `view_financials`
- reverse capitalization if implemented: `fixedAssets.reverse` plus `view_financials`

## UI

Add user-facing actions only if backend actions exist:

- capitalization mode selector if both approved modes exist
- post capitalization action
- visible status badges
- linked journal number display

All visible text must come from dictionaries.

## Tests

Cover:

- opening asset registration creates no GL
- manual capitalization creates exactly one balanced journal
- repeated capitalization is idempotent
- closed period blocks posting
- missing mapping blocks posting with localization-ready error payload/message
- wrong permissions return 403
- no mutation of posted capitalization
- reversal if included
- no unsupported relationships/columns
- tests use actual schema fields only

## Verification

Run the full Phase 6 verification gate from `PHASE_6_FIXED_ASSETS.md`.

Add a targeted capitalization idempotency/concurrency stress test if posting can be triggered concurrently.

## Final Report

Report exact GL entries, mapping keys used, idempotency behavior, source scans, and verification results.

