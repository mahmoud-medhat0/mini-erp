# PERMISSION MATRIX

Model: **Role → Module → Action → Scope**. Actions: `View, Create, Edit, Delete, Submit, Approve, Reject, Post, Cancel, Reverse, Export, Print, Configure`. **Approval actions (Submit/Approve/Reject) are tracked separately from operational actions.** Scopes: company, branch, warehouse, project, cost center, document type. Enforced server-side; permission-denied is an explicit UI state.

## Role templates
`Admin` (all), `Accountant`, `Sales`, `Purchases`, `Warehouse`, `Management` — plus **custom roles** composed from the same permission catalog.

## Operational matrix (V=View C=Create E=Edit D=Delete Po=Post Rv=Reverse X=Export/Print)
| Module | Admin | Accountant | Sales | Purchases | Warehouse | Management |
|---|---|---|---|---|---|---|
|Accounting / Journal|all|V C E Po Rv X|—|—|—|V X|
|Financial Statements|all|V X|—|—|—|V X|
|Sales|all|V Po X|V C E X (own/branch)|—|V|V X|
|Purchasing|all|V Po X|—|V C E X (own/branch)|V (GRN C/E)|V X|
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
|Reports|all|all|Sales scope|Purchasing scope|Inventory scope|all|
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

## Scope rules
- **Branch scope:** a user limited to Branch B only sees/acts on Branch B records.
- **Warehouse scope:** warehouse users act only on assigned warehouses.
- **Project/Cost-center scope:** optional restriction for project managers.
- **Document-type scope:** e.g., a role may post Receipts but not Journal Entries.
- **Record-level examples (from brief):** Sales user may create invoices but not post journals; Warehouse user may create stock transfers but not touch accounting; Manager may approve but not edit.

## Defaults
Least-privilege: new custom roles start with no permissions. `Post`, `Reverse`, `Configure`, and period-close are never granted by default to non-accounting/admin roles.
