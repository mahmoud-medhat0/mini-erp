# MINI ERP - PHASE 6 SLICE 5 DEPRECIATION RUN POSTING

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


Execute only Phase 6 Slice 5 after the depreciation schedule engine is complete and verified.

Do not implement disposals, sale proceeds, tax depreciation books, or supplier bill integration in this pass.

## Objective

Implement period depreciation run posting through the existing `PostingEngine`.

## Accounting Requirements

For each depreciation run:

- Dr depreciation expense
- Cr accumulated depreciation
- entry date must be owner-approved period date rule
- financial period must be open/reopened
- source type must identify fixed asset depreciation
- post only planned/unposted schedule rows
- no duplicate posting for the same asset and period
- repeated requests must be idempotent
- posted schedule rows must become posted and linked to journal/ledger
- posted journal and ledger rows remain immutable

## Data Model

Add only necessary structures, such as:

- `fixed_asset_depreciation_run`
- links from schedule rows to run/journal where needed

Do not mutate posted ledger rows.

Do not add company/branch/location/custodian scopes.

## Concurrency

This slice is concurrency-sensitive.

Use deterministic locks:

- lock the financial period
- lock run key / idempotency key
- lock selected asset schedule rows in stable order

Add a stress command:

- `accounting:fixed-asset-depreciation-stress --workers=50`

Stress must prove:

- exactly one durable depreciation run for same period/key
- no duplicate schedule row postings
- journal totals match schedule totals

## Permissions

- preview: `fixedAssets.view` plus `view_financials`
- post run: `fixedAssets.post` plus `view_financials`
- reverse run if included: `fixedAssets.reverse` plus `view_financials`

## UI

Add period depreciation run page/actions only if backend is complete:

- select financial period
- preview assets/schedule rows
- post run
- show linked journal
- show blocked closed period state

No hardcoded visible TSX text.

## Tests

Cover:

- successful run posts balanced journal
- closed period blocks posting
- missing GL mappings block posting
- idempotent repeated posting
- concurrent posting cannot duplicate run
- schedule rows link to journal
- no posting for disposed/fully depreciated assets
- reversal if implemented
- unauthorized users get 403
- service totals equal journal totals

## Verification

Run the full Phase 6 verification gate from `PHASE_6_FIXED_ASSETS.md`.

Also run:

```powershell
php artisan accounting:fixed-asset-depreciation-stress --workers=50
```

If the command is not added, explicitly justify why the slice did not introduce concurrency-sensitive behavior. In normal implementation, it should be added.

## Final Report

Report GL effects, locking strategy, stress results, source scan classifications, and verification results.

