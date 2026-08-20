# WORKFLOW CATALOG — State Machines

Every major workflow as explicit states + valid transitions + who may perform them (permission) + the accounting/inventory effect. Illegal transitions are rejected (BR-D3). "Post" is where accounting is emitted (ACCOUNTING_EVENT_MAP).

Notation: `State --action[permission]--> State`.

## 1. Generic financial document (Sales/Purchase Invoice, Expense, JV, Cash/Bank, Payroll)
```
Draft --submit[Submit]--> Submitted --approve[Approve]--> Approved --post[Post]--> Posted
Draft --cancel[Cancel]--> Cancelled
Submitted --reject[Reject]--> Draft
Approved --cancel[Cancel]--> Cancelled
Posted --reverse[Reverse]--> Reversed        (creates linked reversing JE; original preserved)
Posted --settle(payment)--> Partially Paid --> Paid --> Closed
```
Rules: edit allowed only in Draft/Submitted/Approved; Posted immutable; post blocked if period closed or Dr≠Cr; approval step(s) per B7 config.

## 2. Sales order-to-cash
```
Quotation: Draft -> Sent -> Accepted -> (convert) Sales Order | Expired | Rejected
Sales Order: Draft -> Submitted -> Approved -> Confirmed -> (convert) Delivery/Invoice -> Closed | Cancelled
Delivery Note: Draft -> Confirmed -> Delivered[stock-out]
Sales Invoice: Draft -> Approved -> Posted[AR+Rev+VAT(+COGS)] -> Partially Paid -> Paid -> Closed ; Posted -> Reversed
Credit Note / Return: Draft -> Approved -> Posted[reverse Rev/VAT, stock-in]
Receipt: Draft -> Posted[Cash/Bank + AR down]; allocation to invoices
```

## 3. Purchasing procure-to-pay
```
Purchase Request: Draft -> Submitted -> Approved -> (convert) PO | Rejected
Purchase Order: Draft -> Approved -> Sent -> (partially) Received -> Closed | Cancelled
GRN: Draft -> Confirmed[stock-in @cost, GRN clearing]
Purchase Invoice: Draft -> Approved -> Posted[Inventory/Exp+InputVAT+AP] -> Partially Paid -> Paid -> Closed ; Reversed
Debit Note / Return: Draft -> Approved -> Posted[AP down, stock-out]
Payment: Draft -> Posted[AP down + Cash/Bank/Withholding]; allocation
```

## 4. Inventory
```
Transfer: Draft -> In-Transit[stock-out src] -> Received[stock-in dest] | Cancelled
Adjustment: Draft -> Approved -> Posted[adjustment JV]
Stock Count: Draft -> Counting -> Reviewed -> Posted[variance adjustments] | Cancelled
Damage/Loss/Consumption: Draft -> Approved -> Posted[expense/variance JV]
```

## 5. Tools & Equipment (custody state machine)
```
Available --assign[custody]--> Assigned --return--> Available
Available --allocate(rental)--> Rented --return+inspect--> Available | Damaged
Assigned --transfer--> Assigned(new custody)
Any --send-maintenance--> Maintenance --complete--> Available
Any --mark-damaged--> Damaged --repair--> Maintenance | --write-off--> Disposed
Any --mark-lost--> Lost
Available/Damaged --dispose--> Disposed[FA disposal accounting if capitalized]
```

## 6. Rental lifecycle
```
Draft -> Confirmed[deposit received: Cash/Bank vs Deposit Liability]
       -> Active[equipment=Rented]
       -> Extended (adds period/charges)
       -> Return Requested -> Inspected[damage/late charges computed]
       -> Invoiced[AR+Rental Rev+VAT; deposit applied]
       -> Closed[deposit refunded/settled]
Any pre-active -> Cancelled
```

## 7. Cash / Bank
```
Receipt/Payment/Transfer/Charge: Draft -> Approved -> Posted -> Reversed
Bank Reconciliation: Draft -> In-Progress(match exact/partial/manual) -> Reconciled
Petty Cash: Float set -> Spend -> Replenish (top-up JV)
```

## 8. Cheques
```
Incoming: Received -> Pending -> Deposited -> Cleared[Bank vs clearing] | Returned[reverse] | Cancelled
Outgoing: Issued -> Pending -> Presented -> Cleared[clearing vs Bank] | Returned | Cancelled
```

## 9. Expenses (2-step approval example)
```
Draft -> Submitted -> Manager Approved -> Accounting Approved -> Posted -> Paid
Submitted/Manager -> Rejected -> Draft
```

## 10. Prepaid / Accrual
```
Prepaid: Created(asset) -> [monthly job] Recognized(n/total) ... -> Fully Recognized
Accrual: Accrued(liability) -> Settled(payment) | Reversed(next period)
```

## 11. Fixed Assets
```
Acquired -> Capitalized -> Depreciating([monthly deprec job]) 
Depreciating -> Transferred | Revalued | Maintenance
Depreciating/Any -> Disposed[gain/loss] | Sold[proceeds+gain/loss+VAT]
```

## 12. Payroll
```
Draft(run) -> Review -> Approved -> Posted[payroll JE] -> Paid[net pay settled] -> Closed
```

## 13. Taxes
```
Tax Period: Open -> (accrue via posted docs) -> Return Draft -> Reviewed -> Filed -> Settled[VAT paid]
```

## 14. Budget
```
Draft -> Submitted -> Approved -> Active ; new Version -> Draft ... (prior versions retained)
```

## 15. Recurring
```
Template: Active <-> Paused
Run: Pending -> Generated -> (auto) Posted | Failed -> Retried
```

## 16. Period / Year close
```
Period: Open -> Closed[block postings] -> Reopened[Reopen perm, audited] -> Closed
Year: Open -> Year-End Close[roll P&L to Retained Earnings, carry forward] -> Locked
```

Each transition records actor + timestamp (audit) and, where it posts, is atomic with its GL/subledger/stock effect.
