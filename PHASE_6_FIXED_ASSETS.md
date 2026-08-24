# PHASE 6 - FIXED ASSETS

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


Status: PLANNED

This document is the Phase 6 planning contract for the active Laravel + Inertia Mini ERP migration.

Phase 6 must be implemented in bounded slices. Do not implement all fixed assets, depreciation, disposals, tax, insurance, maintenance, barcode tracking, or payroll/custodian workflows in one pass.

## Current Baseline

The Laravel target is complete and locally verified through:

- M10 Spatie Activitylog audit backend, scheduler, and jobs baseline.
- Phase 3 Slices 1-10 AR/AP + Cash/Bank/Cheques.
- Phase 4 Slices 1-10 Sales, Purchasing, Moving Weighted Average Inventory, Returns, Credit Notes, Supplier Adjustments, and Manual AR/AP Note Settlement.
- Phase 5 Slices 1-6 Financial Statement Mapping, Balance Sheet, Income Statement, Cash Flow, Period Close, Year-End Close decision pack, and UX/export/print close-out.

Read first:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_5_FINAL_VERIFICATION_REPORT.md`
- `PHASE_5_FINANCIAL_STATEMENTS_PERIOD_CLOSE.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `docs/CONCURRENCY_AUDIT.md`

Use the current Laravel code as the source of truth.

Do not treat old Next.js docs, generated specs, or historical prompts as proof of unsupported business relationships.

## Non-Negotiable Rules

Do not introduce:

- tenant context or tenant middleware
- `company_id`, `branch_id`, or `tenant_id`
- `company_user`
- `users.company_id`
- `branch.company_id`
- `fiscal_year.company_id`
- `currentCompany` or `currentBranch`
- Spatie Teams
- company/branch dimensions in number sequences
- company/branch reporting scope
- warehouse/location semantics
- employee/custodian ownership semantics
- asset-to-user ownership semantics
- payroll, rentals, full tax/VAT filing, projects, budgeting, or landed-cost modules

If a relationship is not explicitly supported by current owner requirements, classify it as:

`UNDEFINED - DO NOT ASSUME`

Preserve:

- single-installation ERP context
- Spatie Permission with teams disabled
- detailed permission checks already used by the Laravel app
- Spatie Activitylog through the existing `AuditLogger`
- append-only audit behavior
- existing attachment and notification services
- atomic global document numbering by key
- idempotent actions
- integer minor-unit money only
- no floats, no `(float)`, no `round()`, and no binary floating-point arithmetic in money/depreciation/disposal calculations
- immutable posted accounting records
- corrections through reversal, not mutation of posted ledgers
- period close protection through `PeriodGuard`

## Strict Review-Avoidance Contract

Every Phase 6 slice must satisfy these rules before reporting completion:

- Inspect actual migrations/models/services before coding and do not reference columns that do not exist.
- Do not use non-existent or non-fillable model fields in tests as if they prove schema behavior.
- Verify every new fixture field against the migration/model. Add regression tests for common mistaken fields.
- Financial posting dates must use explicit document/accounting dates, never `created_at` or `updated_at`.
- Depreciation calculations must use integer minor units and deterministic remainder allocation. Never use floats.
- Posted acquisition, depreciation, and disposal accounting entries must be immutable.
- Reversals must create reversing documents/journals; do not update/delete posted journal or ledger rows.
- Posting must be idempotent with existing idempotency patterns where repeated actions are possible.
- Posting must lock relevant rows deterministically (`lockForUpdate`) when state or cumulative totals can race.
- Closed financial periods must block asset capitalization, depreciation posting, and disposal posting.
- Server-side authorization must be tested for every route/action/export/print endpoint. UI `useCan` checks are not a substitute.
- New TSX pages/components must not contain hardcoded visible English/Arabic text. Translation keys/import names are allowed.
- Do not add or extend hardcoded permission/module/team label maps inside TSX pages. Fixed asset module labels/actions must come from dictionaries or backend-provided permission metadata. If a slice touches an existing hardcoded map, refactor the touched entries or document why it is unchanged.
- Backend messages that appear in the UI must be localization-ready (`code` plus params, or existing multilingual payloads).
- If a slice adds a bounded enum/status column, add database constraints where supported and test invalid values.
- New report/export totals must reconcile with service totals in tests.
- Run targeted source scans before the final report and classify every result as acceptable or fixed.
- A source scan is clean only when it prints no matches.
- Verification commands must complete synchronously before they are reported as passed.
- Documentation/status updates must use actual local command results from this slice, not older summaries.

## Permission & UI Contract

Phase 6 must preserve the current detailed permission model.

Use existing `fixedAssets.*` permissions from `config/erp_rbac.php` where applicable:

- asset register viewing: `fixedAssets.view`
- asset creation: `fixedAssets.create`
- asset edits while unposted/draft: `fixedAssets.edit`
- draft/unposted delete only: `fixedAssets.delete`
- capitalization/depreciation/disposal posting: `fixedAssets.post` and `view_financials`
- reversal actions: `fixedAssets.reverse` and `view_financials`
- fixed asset exports: `fixedAssets.export` or `reports.export` plus `view_financials`, depending on route location
- fixed asset financial reports: `reports.view` plus `view_financials`
- fixed asset financial report exports: `reports.export` plus `view_financials`
- accounting mappings used by fixed assets: `accounting.mappings`

If a slice needs a missing permission such as `fixedAssets.print`, add it deliberately in `config/erp_rbac.php`, seed it, test it, and document it. Do not silently reuse broad permissions.

Frontend pages must not hardcode user-facing labels, statuses, empty states, table headers, action names, or warnings. Use EN/AR dictionaries or backend multilingual master data.

## Phase 6 Business Scope

Phase 6 introduces a fixed asset register and depreciation workflow on top of the existing accounting foundation.

Target capabilities across the phase:

- fixed asset accounting policy decision pack
- fixed asset category and register foundation
- GL mapping keys for fixed asset cost, accumulated depreciation, depreciation expense, disposal gain/loss, and acquisition/disposal clearing if approved
- asset capitalization/opening registration model
- depreciation schedule generation
- depreciation run posting through `PostingEngine`
- disposal/sale/scrap workflow through reversal-safe documents
- fixed asset reports: register, depreciation schedule, net book value, disposal history
- Inertia pages/actions after backend behavior is stable
- close-out verification and documentation

## Must Not Be Built Yet Without Owner Decision

Do not implement these until explicitly approved:

- depreciation methods other than the selected Phase 6 policy
- asset custodian/employee ownership
- branch/location/warehouse fixed asset scope
- barcode/QR/serial scanning workflow beyond simple serial-number storage
- maintenance scheduling
- insurance, warranty, or lease accounting
- impairment testing
- revaluation model
- component depreciation
- asset transfer workflow
- full tax depreciation books
- IFRS/GAAP jurisdiction-specific compliance automation
- payroll integration

## Confirmed Integration Points

Use existing systems:

- `account`, `financial_statement_line`, and `AccountingAccountMappingService`
- `journal_entry`, `journal_line`, and `ledger_entry`
- `PostingEngine`
- `FinancialPeriod`, `FiscalYear`, and `PeriodGuard`
- existing number sequence allocation pattern
- existing `AuditLogger`
- existing `AttachmentService` and attachment entity registry
- existing `NotificationService` only for explicit user-targeted events
- existing RBAC config/seeding pattern
- existing EN/AR dictionaries
- existing `useCan`/`useCanAny` permission helpers

## Fixed Asset Accounting Baseline

The safe default path is straight-line accounting unless the owner selects otherwise in Slice 1.

Common posting patterns, subject to the Slice 1 owner decision:

- capitalization posting:
  - Dr fixed asset cost
  - Cr approved acquisition clearing or AP/cash integration account
- depreciation posting:
  - Dr depreciation expense
  - Cr accumulated depreciation
- disposal without proceeds:
  - Dr accumulated depreciation
  - Dr loss on disposal when net book value remains
  - Cr fixed asset cost
- disposal with proceeds:
  - Dr cash/bank or disposal clearing for proceeds
  - Dr accumulated depreciation
  - Cr fixed asset cost
  - Cr gain on disposal or Dr loss on disposal as required

Do not post any of these until the relevant slice explicitly implements and tests them.

## Phase 6 Slice Plan

1. `PHASE_6_SLICE_1_GEMINI_PROMPT.md`
   - Fixed Asset Policy Decision Pack.
   - Docs-only unless the owner has already approved all policy choices.

2. `PHASE_6_SLICE_2_GEMINI_PROMPT.md`
   - Fixed Asset Register Foundation.
   - Categories, assets, mappings, permissions, attachments, audit, no GL posting.

3. `PHASE_6_SLICE_3_GEMINI_PROMPT.md`
   - Capitalization and Opening Asset Posting.
   - Build only the owner-approved acquisition/opening model.

4. `PHASE_6_SLICE_4_GEMINI_PROMPT.md`
   - Depreciation Schedule Engine.
   - Deterministic integer schedule generation, no GL posting yet.

5. `PHASE_6_SLICE_5_GEMINI_PROMPT.md`
   - Depreciation Run Posting.
   - Idempotent monthly/period depreciation posting through `PostingEngine`.

6. `PHASE_6_SLICE_6_GEMINI_PROMPT.md`
   - Disposal, Sale, Scrap, and Reversal Workflow.

7. `PHASE_6_SLICE_7_GEMINI_PROMPT.md`
   - Reports, UX, Export/Print, E2E Smoke, and Phase 6 Close-Out.

## Verification Gate

Run from `laravel/` for every implementation slice:

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

Use `concurrency:stress --workers=100` only when the local workstation can handle it without Windows paging-file exhaustion.

Add Phase 6 specific stress tests when a slice introduces concurrency-sensitive capitalization, depreciation posting, or disposal behavior.
