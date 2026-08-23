# PHASE 7 - TAX / VAT

Status: PLANNED

This document is the Phase 7 planning contract for the active Laravel + Inertia Mini ERP migration.

Phase 7 must be implemented in bounded slices. Do not implement all taxes, VAT filing, withholding tax, e-invoicing, jurisdiction-specific compliance, payroll tax, rentals tax, or external integrations in one pass.

## Current Baseline

The Laravel target is complete and locally verified through:

- M10 Spatie Activitylog audit backend, scheduler, and jobs baseline.
- Phase 3 Slices 1-10 AR/AP + Cash/Bank/Cheques.
- Phase 4 Slices 1-10 Sales, Purchasing, Moving Weighted Average Inventory, Returns, Credit Notes, Supplier Adjustments, Manual AR/AP Note Settlement, and document revision behavior.
- Phase 5 Slices 1-6 Financial Statement Mapping, Balance Sheet, Income Statement, Cash Flow, Period Close, Year-End Close decision pack, and UX/export/print close-out.
- Phase 6 Slices 1-7 Fixed Assets, capitalization, depreciation schedules/runs, disposal, reports/export/print, and final verification.

Read first:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `PHASE_4_SLICE_10_FINAL_REPORT.md`
- `PHASE_5_FINANCIAL_STATEMENTS_PERIOD_CLOSE.md`
- `PHASE_6_FINAL_VERIFICATION_REPORT.md`
- `docs/CONCURRENCY_AUDIT.md`

Use the current Laravel code as the source of truth.

Do not treat old Next.js docs, generated specs, historical prompts, or natural ERP assumptions as proof of unsupported relationships or tax policy.

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
- jurisdiction-specific tax filing/e-invoicing integrations without owner approval
- payroll tax, rentals tax, projects, budgeting, or landed-cost modules

If a relationship, tax policy, jurisdiction rule, or compliance workflow is not explicitly supported by current owner requirements, classify it as:

`OWNER DECISION REQUIRED - DO NOT IMPLEMENT`

Preserve:

- single-installation ERP context
- Spatie Permission with teams disabled
- detailed permission checks already used by the Laravel app
- Spatie Activitylog through the existing `AuditLogger`
- append-only audit behavior
- existing attachment and notification services
- atomic global document numbering by key
- idempotent posting actions
- integer minor-unit money only
- integer tax rates only, preferably basis points (`rate_bps`) unless Slice 1 decides otherwise
- no floats, no `(float)`, no `round()`, and no binary floating-point arithmetic in tax calculations
- immutable posted accounting records
- immutable stock movement ledger
- corrections through reversal/credit/debit documents, not mutation of posted ledgers
- period close protection through `PeriodGuard`

## Strict Review-Avoidance Contract

Every Phase 7 slice must satisfy these rules before reporting completion:

- Inspect actual migrations/models/services before coding and do not reference columns that do not exist.
- Do not use non-existent or non-fillable model fields in tests as if they prove schema behavior.
- Verify every new fixture field against the migration/model. Add regression tests for common mistaken fields.
- Tax/reporting dates must use explicit document/accounting dates and tax period dates, never `created_at` or `updated_at` unless the feature is explicitly about creation timestamps.
- Tax calculations must use integer minor units and integer rates. Never use floats.
- If using basis points, compute percentage tax with integer math such as `intdiv(($baseMinor * $rateBps) + 5000, 10000)` only when the approved rounding policy is half-up to nearest minor unit.
- Do not hardcode tax percentages or tax labels in TSX pages. Tax code/rate options must come from the database or dictionaries.
- Posted tax register entries and filed tax returns must be immutable except through explicit reversal/adjustment workflows.
- Posting must be idempotent with existing idempotency patterns where repeated actions are possible.
- Posting must lock relevant rows deterministically (`lockForUpdate`) when state, filing, or cumulative document totals can race.
- Closed financial periods must block tax-affecting posting through existing `PeriodGuard`.
- Filed tax periods must block new or reversed tax-affecting postings in that tax period unless Slice 6 explicitly defines a correction/amendment workflow.
- Server-side authorization must be tested for every route/action/export/print endpoint. UI `useCan` checks are not a substitute.
- New TSX pages/components must not contain hardcoded visible English/Arabic text. Translation keys/import names are allowed.
- Do not add or extend hardcoded permission/module/team label maps inside TSX pages. Tax labels/actions must come from dictionaries or backend-provided permission metadata.
- Backend messages that appear in the UI must be localization-ready (`code` plus params, or existing multilingual payloads).
- If a slice adds a bounded enum/status column, add database constraints where supported and test invalid values.
- New report/export totals must reconcile with service totals in tests.
- Run targeted source scans before the final report and classify every result as acceptable or fixed.
- A source scan is clean only when it prints no matches.
- Verification commands must complete synchronously before they are reported as passed.
- Documentation/status updates must use actual local command results from this slice, not older summaries.

## Permission & UI Contract

Phase 7 must preserve the current detailed permission model.

Use existing `taxes.*` permissions from `config/erp_rbac.php` where applicable:

- tax page viewing: `taxes.view`
- tax configuration editing: `taxes.edit`
- tax report export: `taxes.export` or `reports.export`, depending on route location
- tax print: `taxes.print` or `reports.print`, depending on route location
- tax financial report viewing: `reports.view` plus `view_financials`
- tax financial report exports: `reports.export` plus `view_financials`
- tax-related GL mappings: `accounting.mappings`

If a slice needs missing permissions such as `taxes.create`, `taxes.delete`, `taxes.post`, `taxes.file`, `taxes.reverse`, or `taxes.configure`, add them deliberately in `config/erp_rbac.php`, seed them, test them, update dictionary labels, and document why the existing permissions were insufficient.

Do not silently reuse broad permissions such as `settings.configure` for tax actions.

Frontend pages must not hardcode user-facing labels, statuses, empty states, table headers, action names, tax code labels, tax percentages, or warnings. Use EN/AR dictionaries or backend multilingual master data.

## Phase 7 Business Scope

Phase 7 introduces tax/VAT foundation on top of the existing sales, purchasing, returns, accounting, and reporting foundation.

Target capabilities across the phase:

- tax policy decision pack
- tax code and tax rate master data
- output tax integration for sales documents
- input tax integration for purchasing documents
- tax register generated from posted documents
- VAT report and reconciliation against GL mapping accounts
- tax period/filing controls
- export/print and close-out verification

## Must Not Be Built Yet Without Owner Decision

Do not implement these until explicitly approved:

- withholding tax
- payroll tax
- reverse-charge VAT
- import VAT/customs duties
- landed cost/freight tax allocation
- e-invoicing authority integration
- online filing submission
- multi-jurisdiction tax nexus
- company/branch tax registration scope
- tax depreciation books
- tax exemptions requiring certificate workflow
- partial input VAT recovery formulas
- tax audit packet beyond bounded reports

## Confirmed Integration Points

Use existing systems:

- `customer_invoice`, `customer_invoice_line`
- `customer_credit_note`, `sales_return`, and related receivable settlement behavior
- `supplier_bill`, `supplier_bill_line`
- `supplier_adjustment_note`, `purchase_return`, and related payable settlement behavior
- `receivable_entry` and `payable_entry`
- `journal_entry`, `journal_line`, and `ledger_entry`
- `PostingEngine`
- `FinancialPeriod`, `FiscalYear`, and `PeriodGuard`
- `AccountingAccountMappingService`
- existing mapping keys if present: `output_tax_payable`, `input_tax_receivable`
- existing EN/AR dictionaries
- existing `AuditLogger`
- existing RBAC config/seeding pattern
- existing `useCan`/`useCanAny` permission helpers

## Tax Accounting Baseline

The safe default path, subject to Slice 1 owner approval:

- Sales invoice with output VAT:
  - Dr AR control for gross invoice total
  - Cr sales revenue for net taxable base
  - Cr output tax payable for tax amount
- Customer credit note / sales return tax reversal:
  - Debit output tax payable for the credited tax amount
  - Reverse/settle AR according to existing credit note/return behavior
- Supplier bill with recoverable input VAT:
  - Dr purchase expense or inventory/GRNI clearing according to current document source
  - Dr input tax receivable for recoverable tax amount
  - Cr AP control for gross bill total
- Supplier adjustment note / purchase return tax reversal:
  - Credit input tax receivable for reversed recoverable tax
  - Reverse/settle AP according to existing adjustment/return behavior

Do not post any of these until the relevant slice explicitly implements and tests them.

## Phase 7 Slice Plan

1. `PHASE_7_SLICE_1_GEMINI_PROMPT.md`
   - Tax/VAT Policy Decision Pack.
   - Docs-only unless the owner has explicitly approved all tax policy choices.

2. `PHASE_7_SLICE_2_GEMINI_PROMPT.md`
   - Tax Code and Tax Rate Foundation.
   - Master data, validation, permissions, audit, UI, no sales/purchase posting yet.

3. `PHASE_7_SLICE_3_GEMINI_PROMPT.md`
   - Sales Output VAT Integration.
   - Customer invoices, sales returns, customer credit notes, output tax payable posting.

4. `PHASE_7_SLICE_4_GEMINI_PROMPT.md`
   - Purchasing Input VAT Integration.
   - Supplier bills, purchase returns, supplier adjustment notes, input tax receivable posting.

5. `PHASE_7_SLICE_5_GEMINI_PROMPT.md`
   - VAT Register, VAT Reports, and GL Reconciliation.
   - Read-only tax register and report exports.

6. `PHASE_7_SLICE_6_GEMINI_PROMPT.md`
   - Tax Period Filing and Locking Controls.
   - Draft/posted/filed tax return state, tax period locks, correction policy.

7. `PHASE_7_SLICE_7_GEMINI_PROMPT.md`
   - Phase 7 UX, Export/Print, E2E Smoke, Source Scans, and Close-Out.

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
php artisan accounting:fixed-asset-depreciation-stress --workers=50
php artisan accounting:fixed-asset-disposal-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan accounting:phase3-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Use `concurrency:stress --workers=100` only when the local workstation can handle it without Windows paging-file exhaustion.

Add Phase 7 specific stress tests when a slice introduces concurrency-sensitive tax posting, filing, or reversal behavior.
