# ROADMAP

Dependency-ordered. Scope is the complete ERP; phases are implementation order only. A phase is COMPLETE only when its Definition of Done (spec/PHASE1_STATUS + master §15/§47) is genuinely met.

| Phase | Milestone | Scope | Status |
|---|---|---|---|
| 1 | Foundation | design system, theme, i18n/RTL, auth, RBAC, company/branch, settings, numbering, audit, attachments, notifications foundation, job runner, CI, test harness | **IN PROGRESS** |
| 2 | Accounting Core | CoA, journal, ledger, trial balance, periods, fiscal years, opening balances, closing, FX, posting engine | Planned |
| 3 | AR/AP + Cash/Bank/Cheques | subledgers, receipts, payments, cash, bank, reconciliation, cheques | Planned |
| 4 | Sales & Purchasing | quotes→orders→delivery→invoices→returns/credit notes, GRN, 2/3-way match, approvals, credit limits | Planned |
| 5 | Inventory | items, SKU/barcode, warehouses, transfers, adjustments, counts, valuation (WAVG/FIFO), landed cost | Planned |
| 6 | Tools & Rentals | equipment custody, rentals lifecycle + invoicing | Planned |
| 7 | Expenses & Assets | expenses, prepaids, accruals, fixed assets, depreciation | Planned |
| 8 | Payroll & Taxes | payroll runs, tax engine, partners & equity | Planned |
| 9 | Projects & Budgeting | projects, cost centers, budgeting, forecasting, recurring | Planned |
| 10 | Reports & Hardening | full report catalog, dashboard, notifications, print/PDF, audit UX, performance, security hardening | Planned |

Releases are tagged per completed phase: `v0.1.0-phase1-foundation` … `v1.0.0-erp-complete`.
