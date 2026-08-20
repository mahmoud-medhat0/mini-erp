# ACCOUNTING EVENT MAP

Every financial transaction type, its trigger, and the exact journal it produces. All entries are **balanced** (Σ Dr = Σ Cr, base currency), posted **atomically** with any subledger/stock effect, into an **open period**, and are **reversible** (mirror entry, original preserved). All carry `source_type/source_id`, `branch`, optional `project`/`cost_center`, `currency`/`fx_rate`. Accounts are **resolved from configurable mappings** (Settings → Accounting), never literals.

**Legend:** Dr = debit, Cr = credit. "Subledger" = the party/asset ledger updated in the same transaction. Multi-currency lines store txn + base amounts; settlement posts realized FX gain/loss.

---

## 1. Sales
| # | Event / Trigger | Source doc | Dr | Cr | Tax | Inventory | Subledger | Reversal |
|---|---|---|---|---|---|---|---|---|
|1.1|**Sales invoice posted** (credit)|Sales Invoice|Customer AR|Sales Revenue; Output VAT|Output VAT on taxable lines|If stock item: Dr COGS / Cr Inventory (at costing cost) + stock-out movement|AR ↑ (customer)|Reversing JE + stock-in|
|1.2|**Cash sale posted**|Sales Invoice (cash)|Cash/Bank|Sales Revenue; Output VAT|Output VAT|COGS/Inventory as 1.1|— (no AR)|Reverse|
|1.3|**Customer receipt** (settle invoices)|Receipt|Cash/Bank/Cheques-in|Customer AR|—|—|AR ↓; allocation to invoices|Reverse|
|1.4|**Customer advance received**|Receipt (advance)|Cash/Bank|Customer Advances (liability)|—|—|Advance ↑|Reverse / apply|
|1.5|**Advance applied to invoice**|Allocation|Customer Advances|Customer AR|—|—|Advance ↓, AR ↓|Reverse|
|1.6|**Sales return / credit note**|Credit Note|Sales Returns; Output VAT|Customer AR|Reverse Output VAT|Dr Inventory / Cr COGS + stock-in|AR ↓|Reverse|
|1.7|**Overpayment**|Receipt|Cash/Bank|Customer AR (to zero) + Customer Advances (excess)|—|—|AR ↓, Advance ↑|Reverse|
|1.8|**Invoice discount** (line/total)|Sales Invoice|reduces Revenue (or Discount Allowed) |—|VAT on net|—|—|within 1.1|

## 2. Purchasing
| # | Event | Source | Dr | Cr | Tax | Inventory | Subledger | Reversal |
|---|---|---|---|---|---|---|---|---|
|2.1|**Purchase invoice posted** (credit)|Purchase Invoice|Inventory or Expense; Input VAT|Supplier AP|Input VAT|Stock item: Dr Inventory + stock-in at cost|AP ↑|Reverse + stock-out|
|2.2|**Cash purchase**|Purchase Invoice (cash)|Inventory/Expense; Input VAT|Cash/Bank|Input VAT|Stock-in|—|Reverse|
|2.3|**Goods received (GRN)** before invoice|GRN|Inventory|GRN Clearing (accrual)|—|Stock-in at cost|—|Reverse|
|2.4|**Invoice matched to GRN**|Purchase Invoice|GRN Clearing; Input VAT|Supplier AP|Input VAT|—|AP ↑|Reverse|
|2.5|**Supplier payment**|Payment|Supplier AP|Cash/Bank/Cheques-out|—|—|AP ↓; allocation|Reverse|
|2.6|**Supplier advance paid**|Payment (advance)|Supplier Advances (asset)|Cash/Bank|—|—|Advance ↑|Reverse/apply|
|2.7|**Advance applied**|Allocation|Supplier AP|Supplier Advances|—|—|AP ↓, Advance ↓|Reverse|
|2.8|**Purchase return / debit note**|Debit Note|Supplier AP|Inventory/Expense; Input VAT|Reverse Input VAT|Stock-out|AP ↓|Reverse|
|2.9|**Withholding tax on payment**|Payment|Supplier AP|Cash/Bank; Withholding Tax Payable|Withholding|—|AP ↓|Reverse|
|2.10|**Landed cost allocation**|GRN/LandedCost|Inventory (capitalize)|Cash/Bank or AP|Input VAT if any|Adjust unit cost|—|Reverse|

## 3. Inventory (non-purchase/sale)
| # | Event | Dr | Cr | Notes |
|---|---|---|---|---|
|3.1|Opening stock|Inventory|Opening Balance Equity|Per opening template|
|3.2|Transfer (out/in)|Inventory (dest wh)|Inventory (source wh)|In-transit account optional|
|3.3|Positive adjustment / count gain|Inventory|Inventory Adjustment (gain)|From stock count variance|
|3.4|Negative adjustment / count loss|Inventory Adjustment (loss)|Inventory|—|
|3.5|Damage|Inventory Damage Expense|Inventory|Project/CC optional|
|3.6|Loss/shrinkage|Inventory Loss Expense|Inventory|—|
|3.7|Consumption (internal)|Expense/Project cost|Inventory|Project/CC|

## 4. Cash & Banks
| # | Event | Dr | Cr |
|---|---|---|---|
|4.1|Cash receipt (misc)|Cash|Target (Revenue/AR/etc.)|
|4.2|Cash payment (misc)|Target (Expense/AP/etc.)|Cash|
|4.3|Cash transfer|Cash (dest)|Cash (source)|
|4.4|Petty cash replenishment|Petty Cash|Cash/Bank|
|4.5|Bank deposit|Bank|Cash|
|4.6|Bank withdrawal|Cash|Bank|
|4.7|Bank transfer|Bank (dest)|Bank (source)|
|4.8|Bank charges|Bank Charges Expense|Bank|
|4.9|FX revaluation (unrealized)|FX Loss / (Cr FX Gain)|Foreign-currency account|Period-end job|
|4.10|Realized FX gain/loss on settlement|FX Loss / Cr FX Gain|balancing to settlement|On receipt/payment|

## 5. Cheques
| # | Event | Dr | Cr |
|---|---|---|---|
|5.1|Incoming cheque received (from customer)|Cheques-under-collection|Customer AR|
|5.2|Incoming cheque deposited|Cheques-in-bank (clearing)|Cheques-under-collection|
|5.3|Incoming cheque cleared|Bank|Cheques-in-bank|
|5.4|Incoming cheque returned|Customer AR|Cheques-under-collection/Bank|
|5.5|Outgoing cheque issued (to supplier)|Supplier AP|Cheques-payable|
|5.6|Outgoing cheque cleared|Cheques-payable|Bank|
|5.7|Outgoing cheque cancelled|Cheques-payable|Supplier AP (restore)|

## 6. Expenses / Prepaid / Accrual
| # | Event | Dr | Cr |
|---|---|---|---|
|6.1|Expense (cash)|Expense; Input VAT|Cash/Bank|
|6.2|Expense (on account)|Expense; Input VAT|AP/Accrued|
|6.3|Prepaid creation|Prepaid Asset; Input VAT|Cash/Bank/AP|
|6.4|Prepaid monthly recognition|Expense|Prepaid Asset|
|6.5|Accrue expense (period-end)|Expense|Accrued Liability|
|6.6|Accrual settlement|Accrued Liability|Cash/Bank|

## 7. Fixed Assets
| # | Event | Dr | Cr |
|---|---|---|---|
|7.1|Asset purchase|Fixed Asset; Input VAT|Cash/Bank or AP|
|7.2|Depreciation run (monthly)|Depreciation Expense|Accumulated Depreciation|
|7.3|Revaluation up|Fixed Asset|Revaluation Surplus (equity)|
|7.4|Transfer (location/CC)|—|—|memo/dimension only (no GL) unless inter-branch|
|7.5|Disposal (scrap)|Accumulated Depreciation; Loss on Disposal|Fixed Asset|
|7.6|Sale of asset|Cash/Bank; Accumulated Depreciation; (Loss)|Fixed Asset; (Gain); Output VAT|

## 8. Rentals
| # | Event | Dr | Cr |
|---|---|---|---|
|8.1|Rental deposit received|Cash/Bank|Customer Deposit Liability|
|8.2|Rental invoice (period)|Customer AR|Rental Revenue; Output VAT|
|8.3|Extra/late/damage charge|Customer AR|Other Rental Revenue; Output VAT|
|8.4|Deposit applied at close|Customer Deposit Liability|Customer AR|
|8.5|Deposit refunded|Customer Deposit Liability|Cash/Bank|
|8.6|Equipment damage/loss (if capitalized)|Loss Expense; Accum Deprec|Fixed Asset|links C6↔C15|

## 9. Payroll
| # | Event | Dr | Cr |
|---|---|---|---|
|9.1|Payroll run posted|Salary/Wage Expense (+ Employer contributions)|Net Pay Payable; PAYE/Tax Payable; Social/Insurance Payable; Loan/Advance Recovery|
|9.2|Salary payment|Net Pay Payable|Cash/Bank|
|9.3|Remit tax/social liabilities|Tax/Social Payable|Cash/Bank|
|9.4|Employee loan issued|Employee Loan (receivable)|Cash/Bank|
|9.5|Loan repayment via payroll|(within 9.1)|Loan Recovery reduces receivable|

## 10. Taxes
| # | Event | Dr | Cr |
|---|---|---|---|
|10.1|Output VAT accrued|(within sales)|Output VAT Payable|
|10.2|Input VAT accrued|Input VAT Recoverable|(within purchase)|
|10.3|VAT settlement (period)|Output VAT Payable|Input VAT Recoverable; VAT Payable (net)|
|10.4|VAT paid to authority|VAT Payable|Cash/Bank|
|10.5|Withholding remitted|Withholding Payable|Cash/Bank|

## 11. Partners & Equity
| # | Event | Dr | Cr |
|---|---|---|---|
|11.1|Capital / partner contribution|Cash/Bank/Asset|Capital (Equity)|
|11.2|Partner withdrawal|Partner Current Account|Cash/Bank|
|11.3|Partner loan to company|Cash/Bank|Partner Loan (liability)|
|11.4|Profit distribution declared|Retained Earnings|Partner Current Account / Dividends Payable|
|11.5|Year-end close|Revenue & Expense → |Retained Earnings|automatic closing entry|

## 12. Projects / Cost Centers
No standalone journals — every tagged line in events 1–11 carries `project_id`/`cost_center_id`, rolling into project/cost-center reports without duplicating GL. Indirect-cost allocation runs post reclassification entries between cost centers where configured.

---

## Posting invariants (enforced by the engine — see BUSINESS_RULES)
- Σ Dr(base) = Σ Cr(base) for every posted JE.
- Control accounts (AR/AP/Inventory/Tax/FA/Payroll clearing) posted **only** by their subledger events above.
- Period must be **open**; posted entries are immutable → corrected by reversal only.
- Every event is atomic with its subledger/stock side-effect (all-or-nothing DB transaction).
- Every posted JE links to its source document (bidirectional) for drill-down.
