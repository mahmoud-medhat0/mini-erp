# Phase 14 Rentals

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.

**Status:** COMPLETE & VERIFIED - Slices 1-6 complete  
**Deployment Process:** Parked until owner/operator resumes staging/production cutover.

## Purpose

Phase 14 introduces Rental Management only after the owner confirms the rental operating model. Rentals can touch customers, inventory, fixed assets, warehouses, deposits, VAT, AR, revenue, damage charges, and branch operations, so schema and posting rules must not be guessed.

## Bounded Scope

Phase 14 should eventually cover:

- rentable item identity and availability
- rental quotations/contracts
- reservation/allocation
- delivery/handover
- return and inspection
- extensions
- late fees, damage charges, and extra charges
- refundable deposits
- rental invoicing and settlement
- customer statement impact through AR
- VAT integration where applicable
- branch/warehouse operational movement where applicable
- reporting for active rentals, ending soon, overdue returns, revenue, and profitability

## Guardrails

- Do not create Company/Tenant ownership.
- Do not infer Branch as a tenant, login context, or security boundary.
- Do not link rental users to branches unless a later explicit owner decision defines that security model.
- Do not assume rentable items are always inventory products or always fixed assets.
- Do not create financial postings outside PostingEngine.
- Do not use floats for rates, durations, taxes, deposits, charges, or revenue.
- Do not hardcode visible UI text in TSX pages.
- Do not add broad permissions; use exact rental permissions plus sensitive financial permissions where needed.

## Completed Slices

- Slice 1: Rentals policy decision pack. See `PHASE_14_RENTALS_POLICY_DECISION.md`.
- Slice 2: Rentable item and availability foundation. See `PHASE_14_RENTALS_FOUNDATION_REPORT.md`.
- Slice 3: Rental contract lifecycle and item reservation/allocation. See `PHASE_14_RENTAL_CONTRACTS_REPORT.md`.
- Slice 4: Rental delivery, return, and inspection workflow. See `PHASE_14_RENTAL_FULFILLMENT_REPORT.md`.
- Slice 5: Rental billing, deposits, charges, VAT, AR, and GL posting. See `PHASE_14_RENTAL_BILLING_REPORT.md`.
- Slice 6: Rental reports, UX, permissions, source scans, and close-out. See `PHASE_14_FINAL_VERIFICATION_REPORT.md`.

## Future Extensions

- Rental quotations.
- Rental contract extensions and amendments.
- Automated recurring rental invoice generation.
- Rental deposit refund workflow.
- Rental profitability by item.
- Maintenance scheduling for returned or damaged rental items.
