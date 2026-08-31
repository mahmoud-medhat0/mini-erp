# Mini ERP - Phase 18 Slice 3 Agy Prompt

Execute ONLY Phase 18 Slice 3: Product Acceptance and Accountant Smoke Matrix.

This is an acceptance/readiness slice. Stop after this slice. Do not start Slice 4.

## Non-Negotiable Rules

- No multi-tenant architecture and no company/tenant/security scope changes.
- Do not create a new ERP module.
- Do not perform deployment/cutover work.
- Do not change accounting math, posting behavior, stock costing, tax, payroll, period close, numbering, idempotency, or locks.
- UI changes are optional and must be dictionary-backed if needed.

## Objective

Create a practical owner/accountant acceptance matrix that validates the product as a usable ERP, not just a set of technical tests.

## Required Artifact

Create `PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md` in the repository root.

It must include Arabic and English sections, with simple owner-facing wording, covering:

- Authentication and roles
- Dashboard and navigation
- Company/settings/numbering/RBAC
- Chart of accounts, account categories/types, currencies, FX rates
- Fiscal years, periods, opening balances, journal lifecycle, reversal
- General ledger, trial balance, balance sheet, income statement, cash flow
- Customers, suppliers, AR/AP opening balances
- Receipts/payments, allocations, settlements, cheques, bank reconciliation
- Products, UOMs, warehouses, stock balances, stock transfers, stock counts, stock adjustments
- Sales orders, delivery notes, invoices, sales returns, credit notes
- Purchase orders, goods receipts, supplier bills, purchase returns, adjustment notes, landed costs
- VAT/tax codes, rates, periods, filing, VAT reports and GL reconciliation
- Fixed assets, capitalization, depreciation schedules/runs, disposals, reports
- Expenses, prepaids, accruals
- Payroll employees/components/runs/posting
- Rentals items/contracts/handovers/returns/invoices/reports
- Projects, cost centers, budgets, budget variance
- Attachments, notifications, audit log
- Branch/warehouse operational workflows where implemented, explicitly not as tenancy
- Security controls from Phase 17

Each row should have:

- Area
- Scenario
- Expected result
- Required permission/role
- Test data needed
- Owner sign-off status placeholder

## Optional Browserless Smoke Tests

If feasible without adding heavy tooling, add or extend a Laravel feature test that checks authenticated GET access to key Inertia pages using a super-admin-like test user. Do not assert every detail; assert representative pages respond successfully and route authorization stays green.

## Verification Commands

Run from `laravel/` and report exact results:

```powershell
vendor/bin/pint --test
php artisan test --filter=Phase18ProductAcceptanceTest --compact
php artisan security:route-audit --strict
npm run typecheck
```

Run `npm run build` only if frontend files changed.

## Final Report

Create `PHASE_18_SLICE_3_REPORT.md` with:

- exact files changed
- acceptance matrix summary
- browserless smoke coverage if added
- verification results
- no-scope scan result
- remaining risks

Update:

- `PHASE_18_PRODUCT_ACCEPTANCE_UI_CLEAN_CODE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

Stop after Phase 18 Slice 3.
