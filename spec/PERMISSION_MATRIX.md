# PERMISSION MATRIX

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


Model: **Role → Module → Action** with explicit server-side authorization rules. Actions: `View, Create, Edit, Delete, Submit, Approve, Reject, Post, Cancel, Reverse, Export, Print, Configure`. **Approval actions (Submit/Approve/Reject) are tracked separately from operational actions.** Optional business dimensions such as warehouse, project, cost center, or document type are owner-decision items and must not be treated as tenant/company/branch scope. Enforced server-side; permission-denied is an explicit UI state.

## Role templates
`Admin` (all), `Accountant`, `Sales`, `Purchases`, `Warehouse`, `Management` — plus **custom roles** composed from the same permission catalog.

## Operational matrix (V=View C=Create E=Edit D=Delete Po=Post Rv=Reverse X=Export/Print)
| Module | Admin | Accountant | Sales | Purchases | Warehouse | Management |
|---|---|---|---|---|---|---|
|Accounting / Journal|all|V C E Po Rv X|—|—|—|V X|
|Financial Statements|all|V X|—|—|—|V X|
|Sales|all|V Po X|V C E X|—|V|V X|
|Purchasing|all|V Po X|—|V C E X|V (GRN C/E)|V X|
|Inventory|all|V X|V|V|V C E (moves/transfers/adjust/count)|V X|
|Tools & Equipment|all|V X|—|—|V C E (custody/maint)|V X|
|Rentals|all|V Po X|V C E|—|V (deliver/return)|V X|
|Customers / AR|all|V E X|V C E|—|—|V X|
|Suppliers / AP|all|V E X|—|V C E|—|V X|
|Cash|all|V C E Po X|—|—|—|V X|
|Banks|all|V C E Po X (reconcile)|—|—|—|V X|
|Cheques|all|V C E Po X|V (in)|V (out)|—|V X|
|Expenses|all|V Po X|C (own)|C (own)|C (own)|V X|
|Prepaid/Accrual|all|V C E Po X|—|—|—|V X|
|Fixed Assets|all|V C E Po X|—|—|V (custody)|V X|
|Payroll|all|V C E Po X|—|—|—|V X|
|Taxes|all|V E X (review)|—|—|—|V X|
|Partners & Equity|all|V C E Po X|—|—|—|V X|
|Projects/Cost Centers|all|V C E X|V (tag)|V (tag)|V (tag)|V X|
|Budgeting/Forecasting|all|V C E X|—|—|—|V C E X|
|Recurring|all|V C E X|—|—|—|V X|
|Reports|all|all|Sales reports|Purchasing reports|Inventory reports|all|
|Audit Trail|all|V X|—|—|—|V X|
|Settings/Numbering/RBAC|Configure|Tax config (review)|—|—|—|V|

## Approval matrix (Submit / Approve / Reject) — separate from operational
| Document | Submit | Approve | Reject |
|---|---|---|---|
|Sales Invoice / Order|Sales|Management (or Accountant per flow)|Management|
|Purchase Request / PO|Purchases|Management (amount tiers via B7)|Management|
|Expense|any submitter|Manager then Accounting (2-step)|either approver|
|Journal Entry|Accountant|Senior Accountant / Admin|approver|
|Payroll Run|Accountant|Management + Accounting|approver|
|Budget|Accountant|Management|Management|
|Period Close / Reopen|Accountant|Admin (Reopen perm)|Admin|
|Rental Contract|Rental user|Management|Management|

## Authorization dimension rules
- **No Company/Branch scope:** Company and Branch are not tenant/security boundaries in the active Laravel ERP.
- **Warehouse restrictions:** owner decision required before implementing assigned-warehouse restrictions.
- **Project/Cost-center restrictions:** owner decision required before implementing project manager or cost-center restrictions.
- **Document-type restriction:** e.g., a role may post Receipts but not Journal Entries.
- **Record-level examples (from brief):** Sales user may create invoices but not post journals; Warehouse user may create stock transfers but not touch accounting; Manager may approve but not edit.

## Defaults
Least-privilege: new custom roles start with no permissions. `Post`, `Reverse`, `Configure`, and period-close are never granted by default to non-accounting/admin roles.
