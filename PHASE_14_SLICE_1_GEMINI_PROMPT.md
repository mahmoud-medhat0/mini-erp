# MINI ERP - PHASE 14 SLICE 1 RENTALS POLICY DECISION PACK

You are continuing the existing Mini ERP Laravel 13 + Inertia + React + PostgreSQL repository.

This is a **docs-only decision slice**.

Do **not** create migrations, models, services, controllers, routes, React pages, seeders, jobs, commands, or tests in this slice.

## Non-Negotiable Rules

- No multi-tenant architecture.
- No `company_id`, `tenant_id`, `currentCompany`, `currentBranch`, Spatie Teams, or company-owned RBAC.
- Branch may be discussed only as an operational/reporting dimension, not a tenant/security boundary.
- Do not assume rentable items are inventory products, fixed assets, or employees' custody items unless the owner explicitly decides.
- Do not introduce hardcoded visible TSX labels.
- Do not weaken existing Accounting, AR/AP, VAT, Inventory, Fixed Asset, Payroll, Attachment, Notification, Audit, or Period Close invariants.
- Deployment process is parked. Do not add deployment work.

## Source Of Truth

Use:

- `NO_MULTI_TENANT_POLICY.md`
- `PRODUCT_EXTENSIBILITY_ROADMAP.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `spec/MASTER_ERP_SPEC.md` section C7 Rental Management
- `spec/WORKFLOW_CATALOG.md` rental lifecycle section
- current Laravel code under `laravel/`

Treat older Next.js/Prisma references as historical only.

## Required Output

Create or update only:

- `PHASE_14_RENTALS_POLICY_DECISION.md`
- `NEXT_TASKS.md`
- `IMPLEMENTATION_STATUS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

## Decision Pack Requirements

Write the decision pack in clear owner-facing English with an Arabic executive summary.

Cover these decision areas:

1. **Rentable Item Source**
   - Option A: standalone rentable equipment register.
   - Option B: inventory product/serialized stock as rentable items.
   - Option C: fixed assets as rentable items.
   - Option D: hybrid item register with optional links to product/fixed asset.
   - Recommend the safest Mini ERP option and explain tradeoffs.

2. **Rental Availability**
   - reservation, allocation, delivered/rented, returned, damaged, lost, maintenance, retired.
   - overlapping contract prevention.
   - branch/warehouse operational placement without tenant scope.

3. **Rental Contract Lifecycle**
   - draft, submitted, approved, active, extended, return_pending, returned, closed, cancelled.
   - cancellation rules before/after delivery.
   - extension rules.

4. **Billing Model**
   - upfront billing.
   - periodic monthly billing.
   - billing on return.
   - mixed billing with deposits and extra charges.

5. **Deposits**
   - refundable liability.
   - apply to final invoice.
   - partial refund.
   - retained for damage/late fees.
   - never record deposit as revenue until earned.

6. **Charges**
   - rental charge.
   - late fee.
   - damage fee.
   - replacement/lost item charge.
   - discount.
   - manual adjustment.

7. **Accounting**
   - rental revenue mapping.
   - deposit liability mapping.
   - AR control integration.
   - VAT output tax integration.
   - damage/late fee revenue mappings.
   - inventory/fixed-asset impact options depending on rentable item source.

8. **Returns And Inspection**
   - full return.
   - partial return.
   - damaged return.
   - lost item.
   - inspection checklist.
   - repair/maintenance handoff.

9. **Permissions**
   - exact permissions for view/create/edit/submit/approve/deliver/return/inspect/invoice/post/cancel/export/print/configure.
   - financial actions must require `view_financials`.
   - no branch-scoped permission assumption.

10. **Reports**
    - active rentals.
    - ending soon.
    - overdue returns.
    - rental revenue.
    - rental profitability.
    - deposit liability aging.

11. **Future Integrations**
    - barcode/serial scanning.
    - maintenance.
    - customer portal.
    - delivery scheduling.
    - e-invoicing, if explicitly approved later.

## Required Verification

Because this is docs-only:

- Run `git diff --stat`.
- Run a source scan proving no Laravel implementation files were modified by this slice.
- Run a text scan proving no new tenant/company/branch ownership assumption is introduced in the new decision pack.
- Do not claim tests/build passed unless you actually run them.

## Final Response Format

Report:

1. files created/updated
2. decisions still required
3. recommended path
4. verification scans
5. explicit statement that no Laravel implementation was added
