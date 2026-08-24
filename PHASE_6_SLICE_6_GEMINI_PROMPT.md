# MINI ERP - PHASE 6 SLICE 6 DISPOSAL, SALE, SCRAP, AND REVERSAL WORKFLOW

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


Execute only Phase 6 Slice 6 after depreciation run posting is complete.

Do not implement maintenance, insurance, barcode scanning, tax books, or asset transfer workflow.

## Objective

Implement fixed asset disposal workflows:

- scrap / retirement with zero proceeds
- sale with proceeds if owner-approved
- reversal of disposal

## Accounting Requirements

For disposal:

- remove fixed asset cost from books
- remove accumulated depreciation
- recognize gain or loss on disposal
- optionally recognize proceeds through owner-approved cash/bank/disposal clearing account
- post through `PostingEngine`
- use open/reopened financial period only
- no mutation of prior depreciation/capitalization ledgers

Suggested source types:

- `fixed_asset_disposal`
- `fixed_asset_disposal_reversal`

Do not use direct cash/bank posting unless owner policy and mappings are explicit.

## Data Model

Add only needed structures, such as:

- `fixed_asset_disposal`

Suggested fields:

- `fixed_asset_id`
- `disposal_date`
- `financial_period_id`
- `disposal_type` sale/scrap/retirement
- `proceeds_minor`
- `net_book_value_minor`
- `gain_minor`
- `loss_minor`
- `status`
- `journal_entry_id`
- `reversal_journal_entry_id`
- `lock_version`

Do not add branch/company/user/custodian/location FKs.

## Concurrency

Prevent:

- double disposal of same asset
- disposal while depreciation run for same period is posting
- depreciation posting after disposal date

Use row locks and idempotency keys.

Add or extend fixed asset stress coverage if concurrent disposal/reversal is possible.

## Permissions

- view: `fixedAssets.view` plus `view_financials`
- post disposal: `fixedAssets.post` plus `view_financials`
- reverse disposal: `fixedAssets.reverse` plus `view_financials`

## UI

Add disposal action/page:

- disposal date
- disposal type
- proceeds amount if sale
- preview gain/loss
- post disposal
- reverse disposal if allowed
- linked journal display

All visible text must be dictionary-backed.

## Tests

Cover:

- scrap disposal posts loss equal net book value
- sale disposal posts gain/loss correctly with integer minor units
- fully depreciated disposal handles zero NBV
- closed period blocks disposal
- missing mappings block disposal
- cannot dispose twice
- cannot depreciate after disposal date
- reversal creates reversing journal and restores asset status according to policy
- concurrent disposal creates exactly one durable disposal
- unauthorized users get 403
- no unsupported columns

## Verification

Run the full Phase 6 verification gate from `PHASE_6_FIXED_ASSETS.md`.

Add disposal-specific stress verification if implemented.

## Final Report

Report disposal accounting examples, reversal behavior, concurrency results, source scan classifications, and verification results.

