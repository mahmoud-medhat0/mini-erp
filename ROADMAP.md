# ROADMAP

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


Dependency-ordered. Scope is the complete ERP; phases are implementation order only. A phase is COMPLETE only when its Definition of Done (spec/PHASE1_STATUS + master §15/§47) is genuinely met.

| Phase | Milestone | Scope | Status |
|---|---|---|---|
| 1 | Foundation | design system, theme, i18n/RTL, auth, RBAC, company/branch, settings, numbering, audit, attachments, notifications foundation, job runner, local test harness | **COMPLETE (Laravel local verification)** |
| 2 | Accounting Core | CoA, journal, ledger, trial balance, periods, fiscal years, opening balances, closing/reopen, FX, posting engine, reversal | **COMPLETE (ledger spine)** |
| 3 | AR/AP + Cash/Bank/Cheques | customer/supplier masters, AR/AP subledger, receipts, payments, cash, banks, cheques, bank reconciliation, statements/aging/cash-bank reports | **COMPLETE (Slices 1-10 complete)** |
| 4 | Sales & Purchasing | quotes→orders→delivery→invoices→returns/credit notes, GRN, 2/3-way match, approvals, credit limits | In progress - Slices 1-7 complete, Slice 8 prompt ready for Moving Weighted Average |
| 5 | Inventory | items, SKU/barcode, warehouses, transfers, adjustments, counts, valuation (WAVG/FIFO), landed cost | Planned |
| 6 | Tools & Rentals | equipment custody, rentals lifecycle + invoicing | Planned |
| 7 | Expenses & Assets | expenses, prepaids, accruals, fixed assets, depreciation | Planned |
| 8 | Payroll & Taxes | payroll runs, tax engine, partners & equity | Planned |
| 9 | Projects & Budgeting | projects, cost centers, budgeting, forecasting, recurring | Planned |
| 10 | Reports & Hardening | full report catalog, dashboard, notifications, print/PDF, performance, security hardening | Planned |

Releases are tagged per completed phase: `v0.1.0-phase1-foundation` … `v1.0.0-erp-complete`.

Note: the Laravel migration pass named `M10` already delivered Spatie Activitylog, audit viewer, scheduler, and jobs baseline. That does not mean product roadmap Phase 10 is complete.
