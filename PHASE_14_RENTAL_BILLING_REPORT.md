# Phase 14 Slice 5 - Rental Billing, Deposits, Charges, and Accounting Integration

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. No company, tenant, Spatie Teams, currentCompany, or currentBranch context was introduced. `branch_id` on rental invoices is an optional operational/reporting reference inherited from the rental contract, not a tenant/security boundary.

**Status:** COMPLETE & LOCALLY VERIFIED  
**Date:** 2026-08-25  
**Scope:** Rental invoices, deposits, damage/late/other charges, VAT output integration, AR subledger, GL posting, period close blockers, UI, and tests.

## Implemented

- Added `rental_invoice` and `rental_invoice_line` tables.
- Added `RentalInvoice` and `RentalInvoiceLine` models and relationships from rental contracts, customers, branches, returns, tax codes, journals, and receivable entries.
- Added `RentalInvoiceService` lifecycle:
  - `draft`
  - `submitted`
  - `approved`
  - `posted`
  - `cancelled`
- Added supported invoice types:
  - `periodic_rent`
  - `deposit`
  - `final_charges`
  - `mixed`
- Added supported line types:
  - `rent`
  - `deposit`
  - `damage_charge`
  - `late_fee`
  - `other_charge`
- Added billing controls:
  - rent lines require a billing period
  - duplicate rent billing for the same contract line and same billing period is rejected
  - deposit billing cannot exceed the contract line deposit
  - damage billing requires a completed rental return line
  - damage billing cannot exceed the inspected damage amount
  - posted invoices cannot be cancelled directly
- Added accounting mappings and seeded accounts:
  - `rental_revenue`
  - `rental_damage_revenue`
  - `rental_late_fee_revenue`
  - `rental_other_revenue`
  - `rental_deposit_liability`
- Posting uses the existing `PostingEngine`:
  - Dr `ar_control`
  - Cr rental revenue / damage revenue / late fee revenue / other revenue
  - Cr `rental_deposit_liability` for refundable deposits
  - Cr `output_tax_payable` when VAT applies
  - creates AR `receivable_entry` with `source_type = rental_invoice`
- Added `RINV-YYYY-XXXXX` numbering using the existing atomic number sequence allocator.
- Added tax-period guard and financial period guard before posting.
- Added rental invoice period-close readiness blocker.
- Added VAT register output tax inclusion for posted rental invoices.
- Registered `rental_invoice` in attachment entity authorization.
- Added `/rentals/invoices` routes and `RentalInvoiceController`.
- Added `Rentals/Invoices.tsx` with dictionary-backed EN/AR text, filters, line builder, totals preview, and permission-aware lifecycle actions.
- Added Rentals navigation entry for Rental Invoices.
- Added feature coverage in `Phase14RentalBillingTest`.

## Verification

Commands run locally:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=Phase14RentalBillingTest --stop-on-failure
php artisan accounting:phase3-integrity-check
php artisan test --filter=Phase14 --stop-on-failure
php artisan test --stop-on-failure
npm run typecheck
npm run build
```

Results:

- Migration `2026_08_25_103000_create_phase14_rental_invoice_tables` applied successfully.
- `Phase14RentalBillingTest`: 8 tests / 56 assertions passed.
- `Phase14` feature suite after close-out: 27 tests / 256 assertions passed.
- Full Laravel suite after close-out: 654 tests, 651 passed, 3 skipped, 5,632 assertions.
- `vendor/bin/pint --test`: passed.
- `php artisan accounting:phase3-integrity-check`: passed.
- `npm run typecheck`: passed with 0 errors.
- `npm run build`: passed with the existing Vite chunk-size warning only.

## Scope Confirmation

- No `company_id` or `tenant_id` columns were added.
- No current-company/current-branch context was added.
- No Spatie Teams scope was added.
- `rental_invoice.branch_id` is an operational/reporting reference copied from the contract.
- Financial posting remains inside `PostingEngine`.
- Visible page text is dictionary-backed.

## Close-Out

Phase 14 Slice 6 is complete. See `PHASE_14_FINAL_VERIFICATION_REPORT.md` for the rental operations report, export/print UX, source scans, stress verification, and final close-out results.
