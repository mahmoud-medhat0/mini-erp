# REPORT CATALOG

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


Engine: MASTER_ERP_SPEC B9. Every report supports (where applicable) date range, fiscal period, branch, project, cost center, account, customer, supplier, warehouse, status, currency, comparison period, search, sort, group, **drill-down to source**, export (XLSX/CSV/PDF), print (light theme: company header, title, filters, generated timestamp, currency, totals, page numbers), and full AR/EN + RTL/LTR. All financial reports read **posted data only**. Labels are localized (`title_en/ar`).

## Accounting
| Report | Source | Key columns | Calc / group | Drill-down |
|---|---|---|---|---|
|General Journal|journal_entry/line|date, JE#, account, Dr, Cr, memo|by date/JE|→ JE → source doc|
|General Ledger|ledger_entry|date, ref, Dr, Cr, running balance|per account|→ JE → source|
|Trial Balance|ledger_entry|account, opening, Dr, Cr, closing|group by type; must balance|→ ledger|
|Account Statement|ledger_entry|movements + balance|per account/period|→ JE|

## Financial Statements
| Report | Source | Notes |
|---|---|---|
|Income Statement|posted P&L accounts|monthly/qtr/yearly, comparative, budget-vs-actual|
|Balance Sheet|posted BS accounts|Assets=Liab+Equity; comparative|
|Cash Flow Statement|cash/bank + posted flows|operating/investing/financing|
|Statement of Changes in Equity|equity accounts|capital, contributions, withdrawals, RE|

## Sales
Sales Report, Customer Sales, Invoice Report, Sales Returns, Collections — source sales_doc/receipt; group by customer/product/project/period; drill to invoice.

## Purchasing
Purchases Report, Supplier Purchases, Purchase Invoices, Purchase Returns — source purchase_doc/payment; drill to PO/invoice.

## Inventory
Inventory Valuation (qty × cost per method), Stock Movement, Stock Aging, Low Stock (below reorder), Cost History — source stock_movement/stock_layer; reconciles to inventory GL.

## AR / AP
Customer Statement, Supplier Statement, AR Aging (Current/1-30/31-60/61-90/90+), AP Aging — source ar_entry/ap_entry; drill to invoice/receipt.

## Cash & Banking
Cash Book (Opening+Receipts−Payments±Transfers=Closing), Bank Book, Bank Reconciliation, Cash/Bank Position — source cash_txn/bank_txn.

## Cheques
Cheque Register (in/out), Due Cheques, Returned Cheques — source cheque/cheque_event.

## Assets
Asset Register, Depreciation Schedule, Disposals, NBV by Category — source fixed_asset/depreciation_entry.

## Payroll
Payroll Register, Payslips, Loan/Advance Balances, Payroll Liabilities — source payroll_run/line.

## Taxes
VAT Report (Input/Output/Net), Withholding Report, Tax Liability by period — source tax + posted tax lines; reviewable before filing.

## Projects & Cost Centers
Project Profitability (revenue − direct − indirect), Cost Center Report, Department Spend, Revenue by Project — source tagged posted lines.

## Budget
Budget vs Actual, Variance Analysis (amount + %), Forecast vs Actual — budget_line vs posted GL (same queries as statements).

## Expenses
Expense by Category / Project / Cost Center, Recurring Expenses.

## Rentals
Active Rentals, Ending Soon, Overdue Returns, Rental Revenue, Rental Profitability.

## Audit
Audit Report, User Activity, Change History (per record).

---

## Dashboard KPI definitions (each: formula · source · period · drill-down · permission)
| KPI | Formula | Source | Drill-down |
|---|---|---|---|
|Total Revenue|Σ posted revenue accounts|ledger_entry (revenue)|Income Statement → revenue accounts|
|Total Expenses|Σ posted expense accounts|ledger_entry (expense)|Income Statement|
|Gross Profit|Revenue − COGS|ledger_entry|Income Statement|
|Operating Profit|Gross − Operating Expenses|ledger_entry|Income Statement|
|Net Profit|Operating ± other ± tax|ledger_entry|Income Statement|
|Cash Balance|Σ cash accounts|ledger_entry (cash)|Cash Book|
|Bank Balance|Σ bank accounts|ledger_entry (bank)|Bank Book|
|Receivables|AR control balance|ar_entry|AR Aging → customer → invoice|
|Payables|AP control balance|ap_entry|AP Aging → supplier → invoice|
|Inventory Value|Σ stock ledger value|stock_movement/layer|Inventory Valuation → product → movement|
|Fixed Assets (NBV)|cost − accum deprec|fixed_asset|Asset Register|
|Outstanding Invoices|count/Σ unpaid sales invoices|sales_doc|Sales invoices (unpaid filter)|
|Overdue Receivables|AR past due date|ar_entry|AR Aging (overdue)|
|Overdue Payables|AP past due date|ap_entry|AP Aging|
|Active Rentals|contracts status=Active|rental_contract|Rentals (Active)|
|Rentals Ending Soon|end_date within N days|rental_contract|Rentals (Ending Soon)|
|Low Stock Items|qty ≤ reorder level|stock ledger|Low Stock report|
|Pending POs/SOs|status in approval|purchase/sales_doc|respective lists|
|Pending Approvals|approval queue for user|approval_action|Approvals inbox|
Charts: Revenue/Expense/Gross/Net trend, Cash Flow, AR/AP Aging, Sales by Customer/Product, Revenue by Project, Expense by Category — all from the same posted-data queries. **No KPI recomputes independently of the reporting engine (BR-A5).**
