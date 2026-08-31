# INTEGRATION MAP — Module Interconnections

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


No module is an isolated CRUD island. Every operational event fans out to inventory, subledgers, tax, dimensions, GL, and reporting through the shared engines. Arrows = "produces effect in."

## Central hub
```
                         ┌────────────────────────────┐
   Sales ─┐              │   ACCOUNTING ENGINE (GL)    │
Purchasing┤              │  JournalEntry + Ledger      │◄── Numbering, Approval,
 Inventory┤─ posting ───►│  Trial Balance              │     Audit, Tax, FX
  Rentals ┤   service    │  → Financial Statements     │──► Reporting Engine ──► Dashboard
 Expenses ┤              │  → Subledgers (AR/AP/Cash/  │
  Payroll ┤              │     Bank/Inv/FA/Payroll/Eq) │
   Assets ┘              └────────────────────────────┘
```

## Module-to-module effects
| From | To | Effect |
|---|---|---|
|Sales|Inventory|stock-out on delivery/invoice; COGS at cost method|
|Sales|AR|invoice ↑ receivable; receipt ↓; credit note ↓|
|Sales|Tax|Output VAT on taxable lines|
|Sales|Projects/Cost Centers|revenue tagged to dimensions|
|Sales|GL → Statements → Dashboard|revenue, COGS, VAT, AR postings|
|Purchasing|Inventory|stock-in at cost + landed cost on GRN|
|Purchasing|AP|invoice ↑ payable; payment ↓; debit note ↓|
|Purchasing|Tax|Input VAT + withholding|
|Purchasing|Projects/Cost Centers|expense/asset tagged|
|Inventory|GL|inventory value, COGS, adjustments/damage/loss|
|Inventory|Reporting|valuation, movement, aging, low-stock; feeds Dashboard inventory value|
|Rentals|Equipment (Tools)|allocation sets Rented; return sets Available/Damaged|
|Rentals|AR + Tax + GL|rental invoice, deposit liability, charges|
|Tools & Equipment|Fixed Assets|capitalized equipment disposal/loss → FA accounting|
|Tools & Equipment|Expenses/Projects|maintenance cost|
|Cash/Bank|AR/AP|settle receipts/payments|
|Cash/Bank|GL|all movements post|
|Cheques|Bank + AR/AP|clearing lifecycle|
|Expenses|GL + Tax + Projects/Cost Centers|expense + input VAT + dimensions|
|Prepaid/Accrual|GL|scheduled recognition journals|
|Fixed Assets|GL|acquisition, depreciation, disposal|
|Payroll|GL|salary expense + liabilities; payment settles|
|Payroll|Projects/Cost Centers|labor cost allocation|
|Taxes|GL + Reporting|VAT/withholding accounts; tax reports|
|Partners & Equity|GL|capital, withdrawals, distributions, RE|
|Projects/Cost Centers|Reporting|profitability/variance rollups (no double GL)|
|Budgeting|Reporting|budget-vs-actual vs posted GL|
|Recurring|Sales/Purchase/Expense/Journal|generates docs that post via their engines|
|All modules|Numbering|document numbers|
|All modules|Approval|status gating before post|
|All modules|Audit|full history|
|All modules|Notifications|events → alerts linking to records|
|All financial modules|RBAC|server-side permission + scope|

## Shared services consumed by all
Numbering (B6) · Approval (B7) · Recurring (B8) · Reporting (B9) · RBAC (B10) · Audit (B11) · Tax (B12) · Notifications (B13) · Posting/GL (B1) · Inventory (B2) · FX (A4).

## Data-flow invariant
Operational document → posting service → (JournalEntry + Subledger + StockMovement, atomic) → Ledger → Trial Balance → Financial Statements → Dashboard KPIs → drill-down back to the operational document. One source of truth; every number traceable.
