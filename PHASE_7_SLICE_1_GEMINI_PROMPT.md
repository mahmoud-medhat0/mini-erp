# MINI ERP - PHASE 7 SLICE 1 TAX / VAT POLICY DECISION PACK

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


You are continuing the active Laravel + Inertia Mini ERP migration.

Execute only Phase 7 Slice 1.

This slice is docs-only unless all owner decisions below are already explicitly recorded in current project documents.

Do not implement migrations, models, services, controllers, routes, TSX pages, seeders, commands, or tests in this slice unless the owner has explicitly instructed implementation in the same request.

## Read First

Read and follow:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_7_TAX_VAT.md`
- `PHASE_4_SLICE_10_FINAL_REPORT.md`
- `PHASE_5_FINAL_VERIFICATION_REPORT.md`
- `PHASE_6_FINAL_VERIFICATION_REPORT.md`
- current Laravel code for sales, purchasing, returns, credit notes, supplier adjustment notes, PostingEngine, PeriodGuard, RBAC, reports, and accounting mappings

## Objective

Create a tax/VAT policy decision pack that explains the business choices required before implementing tax accounting and reporting.

Create:

- `PHASE_7_TAX_VAT_POLICY_DECISION.md`

Update:

- `NEXT_TASKS.md`
- `IMPLEMENTATION_STATUS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

## Required Owner Decisions

The decision pack must ask the owner to approve:

1. Tax scope:
   - VAT only for Phase 7
   - VAT plus withholding tax
   - other jurisdiction-specific taxes
2. Jurisdiction assumptions:
   - no jurisdiction-specific filing rules yet
   - Egypt VAT rules
   - other jurisdiction selected by owner
3. Tax rate representation:
   - integer basis points (`rate_bps`, 10000 = 100%)
   - another integer scale if explicitly justified
4. Tax calculation mode:
   - exclusive tax (net + tax = gross)
   - inclusive tax (gross includes tax)
   - both, selected per document or tax code
5. Rounding policy:
   - nearest minor unit, half-up
   - always down
   - always up
   - document-level versus line-level rounding
6. Tax codes:
   - standard VAT
   - zero-rated
   - exempt / out of scope
   - future reverse charge or withholding as not implemented
7. Tax applicability:
   - line-level tax code on each document line
   - header default with line override
   - customer/supplier/product defaults in later slice only
8. Sales output tax posting:
   - customer invoice posts output tax payable
   - credit notes and sales returns reverse output tax
9. Purchasing input tax posting:
   - supplier bill posts recoverable input tax receivable
   - purchase returns and supplier adjustment notes reverse input tax
10. Recoverability:
   - 100% recoverable input VAT only for Phase 7
   - partial recovery requires later owner-approved slice
11. Tax period model:
   - monthly
   - quarterly
   - manual date ranges
12. Filing/locking behavior:
   - filed tax periods block new tax-affecting postings
   - amendments require reversal/adjustment in later period
   - reopening filed tax periods is not implemented unless explicitly approved
13. GL mapping strategy:
   - `output_tax_payable`
   - `input_tax_receivable`
   - optional tax clearing or rounding account
14. Reporting:
   - VAT register
   - output/input VAT summary
   - GL reconciliation against tax accounts
   - CSV export and print view
15. Permissions:
   - exact use of `taxes.*`, `reports.*`, `view_financials`, and `accounting.mappings`

## Recommended Path

Recommend the safest default for Mini ERP:

- VAT only for Phase 7.
- No withholding tax in Phase 7.
- No e-invoicing or online filing in Phase 7.
- Integer `rate_bps` for rates.
- Tax-exclusive default, with tax-inclusive kept as an owner decision.
- Line-level tax code with optional document header default.
- Line-level tax calculation with document totals matching the sum of tax lines.
- Half-up rounding to nearest minor unit using integer math only.
- 100% input VAT recoverability only.
- Monthly tax periods.
- Filed tax periods are locked; corrections are handled by credit/debit/return documents or future amendment slice.
- Use existing `output_tax_payable` and `input_tax_receivable` mappings if already present, otherwise add them deliberately in Slice 2.
- No company, branch, warehouse, customer-tax-registration, or supplier-tax-registration scope unless explicitly approved later.

Make the recommendation clear, but do not present it as approved.

## Non-Negotiable Rules

Do not introduce:

- tenant/company/branch scope
- Spatie Teams
- jurisdiction-specific filing behavior without owner approval
- online tax authority integrations
- e-invoicing
- withholding tax
- payroll tax
- landed cost/freight tax allocation
- reverse charge
- partial input VAT recovery
- any Laravel implementation code

Do not replace one assumption with another.

If a policy is not explicitly approved, classify it as:

`OWNER DECISION REQUIRED - DO NOT IMPLEMENT`

## Decision Pack Content

`PHASE_7_TAX_VAT_POLICY_DECISION.md` must include:

- Arabic owner-facing summary
- English technical summary
- plain-language explanation of VAT/input tax/output tax
- comparison table for tax scope options
- comparison table for tax calculation/rounding options
- sales tax posting explanation
- purchasing tax posting explanation
- tax period and filing options
- GL mapping requirements
- permission requirements
- exact owner decision checklist
- recommended path
- "not implemented yet" declaration
- future slice dependency notes

## Verification

Because this is docs-only:

- run `git diff --stat`
- run a source scan proving no Phase 7 Laravel implementation was added:

```powershell
rg -n "tax_code|tax_rate|tax_period|tax_return|vat|VAT|input_tax|output_tax|withholding" laravel/app laravel/database laravel/routes laravel/resources/js laravel/tests
```

If matches already exist from baseline, classify them. Do not call non-empty scans clean.

Run formatting/check commands only if code files were changed. If this remains docs-only, do not claim code tests prove implementation.

## Final Report

Report:

1. files created/changed
2. implementation added: must be zero unless owner explicitly approved otherwise
3. owner decisions still required
4. source scan results and classification
5. next file to execute after owner decision
