# MASTER ERP SPECIFICATION — Final Target System

**Authority:** This document specifies the **complete, final** Mini ERP — every module, workflow, report, rule, and screen from the original brief. The 10-phase plan (`ROADMAP_10_PHASE`, end of this doc) is **implementation order only**; it does not partition scope into "now vs later." Nothing here is optional unless explicitly marked `DECISION REQUIRED`.

**Companion documents (this spec set):**
`ACCOUNTING_EVENT_MAP.md` · `DATABASE_DESIGN.md` · `BUSINESS_RULES.md` · `PERMISSION_MATRIX.md` · `SCREEN_CATALOG.md` · `WORKFLOW_CATALOG.md` · `REPORT_CATALOG.md` · `INTEGRATION_MAP.md` · `REQUIREMENTS_TRACEABILITY.md`. Foundations already delivered: `PROJECT_MAP.md`, `DESIGN_FOUNDATION.md`, `tokens.css`, `tailwind.tokens.js`, `style-guide.html`.

**Reading order:** Part A (global contracts) → Part B (core engines) → Part C (26 module specs). Modules inherit the contracts in Part A rather than repeating them; each module states only its *specifics and deviations*.

**Conflict resolution vs prior files:** where `PROJECT_MAP.md` was lighter than the brief, this spec supersedes it (multi-currency + FX, unit conversions, stock counts/reconciliation, budget versions, forecasting, maintenance on assets/equipment, partner loans, withholding tax, command palette, saved views are now all **in scope** and specified below). No requirement is dropped.

---

# PART A — GLOBAL ARCHITECTURE & CONTRACTS

## A1. Technology architecture (final)
| Layer | Choice | Notes |
|---|---|---|
| Framework | Next.js (App Router) + TypeScript | Route groups per module; RSC for report/query pages; Server Actions guarded by RBAC. |
| DB | PostgreSQL + Prisma | Referential integrity, DB transactions, unique constraints, partial indexes. |
| Service layer | Domain services (posting, inventory, numbering, approval, tax, FX) | **UI never writes ledger/stock directly** — only services do, inside DB transactions. |
| Auth | Auth.js + server-side RBAC middleware | Session → permission set → scope filters. |
| i18n | next-intl (AR + EN), RTL/LTR first-class | Locale segment; messages in `locales/`. |
| Money | **BigInt minor units** + `decimal.js` for intermediate math | No IEEE-754 float in any monetary path. |
| Validation | Zod schemas shared client/server | Same rule enforced both sides; server authoritative. |
| Tables | TanStack Table + server pagination/virtualization | Scales to thousands of rows. |
| Charts | Recharts, token- & direction-aware | Theming + RTL. |
| Jobs | Queue/cron worker | Recurring txns, depreciation, prepaid/accrual recognition, aging/notification sweeps, FX revaluation. |
| Files | Object storage + `Attachment` polymorphic table | Doc previews. |
| Print/PDF | Server-rendered print theme (light) | Never prints the dark UI. |

## A2. Universal UI Contract (applies to EVERY screen/component)
Every screen and component MUST implement, and is not "done" until it implements, all of:
- **States:** default, hover, focus, active, disabled, loading (skeleton), empty, error, success, read-only, **permission-denied** (render explicit state, never a dead/hidden-only button), no-search-results, no-filtered-results.
- **Localization:** all strings from `locales/{ar,en}/…`; zero hardcoded UI text. Business data carries `name_ar` + `name_en` where user-created.
- **Direction:** one component set, logical CSS props (`margin/padding/inset-inline`, `text-align:start/end`); identifiers (doc no., SKU, barcode, IBAN, email, phone, URL) wrapped in direction isolation; directional icons mirror, non-directional do not.
- **Theme:** light / dark / system, switch with no reload and no loss of page/filters/form/report state; language persisted per user.
- **Responsive:** desktop (primary), tablet, mobile — tables use priority columns + horizontal scroll + expandable rows + mobile cards/bottom sheets, not naive shrink. Mobile RTL intentionally adapted.
- **Accessibility:** keyboard nav (RTL+LTR), visible focus (both themes), semantic markup, labelled controls, accessible dialogs/menus/tables, AA contrast, color never the sole signal.
- **Numbers/dates:** central formatter (currency, precision, thousands sep, negatives, %, dates/ranges/times), locale-aware, tabular figures for money.
- **QA gate:** verified across the 4-way matrix (EN-LTR-Light, EN-LTR-Dark, AR-RTL-Light, AR-RTL-Dark) + mobile/tablet/desktop + long text + large/negative numbers + empty/error.

## A3. Universal Record Contract (applies to every persisted business entity)
- **Audit fields:** `created_by/at`, `updated_by/at`, and where the lifecycle applies `submitted_by/at`, `approved_by/at`, `posted_by/at`, `cancelled_by/at`, `reversed_by/at`. Full before/after change log in `AuditLog`.
- **Numbering:** human document numbers from the central numbering engine (B6); concurrency-safe & unique.
- **Deletion policy:** operational drafts → soft-delete allowed with permission; **posted financial records are immutable** — corrected only by reversal/credit-note, never edited or hard-deleted.
- **Scoping:** every business record carries `company_id`, `branch_id`; financial/operational lines optionally carry `project_id`, `cost_center_id`.
- **Traceability:** every posted event links bidirectionally to its `JournalEntry` (source_type/source_id ↔ journal), enabling drill-down from any figure to its source document.

## A4. Money, currency & FX
- Amounts: `bigint` minor units + `currency` (ISO). Presentation formats via central formatter. EGP is base currency; system is **multi-currency**: documents may be in a foreign currency with an `exchange_rate` captured at document date.
- GL stores both **transaction-currency** and **base-currency** amounts on each line. Realized FX gain/loss posted on settlement; unrealized FX gain/loss posted by a period-end revaluation job on open foreign-currency balances. FX accounts configured in Settings→Accounting.
- Rounding: half-up to currency minor unit; a rounding-difference account absorbs sub-unit residue on allocations.

## A5. Global cross-cutting subsystems (each specified as an engine in Part B)
Numbering · Approval workflow · Recurring transactions · Reporting · RBAC/permissions · Audit trail · Tax · Notifications · Period/fiscal management · Attachments · Global search & command palette.

---

# PART B — CORE ENGINES

## B1. Accounting Engine (spine)
**Chart of Accounts:** multi-level hierarchy; `Account(code, name_en, name_ar, type, group, parent_id, nature[debit|credit], is_control, currency?, status)`. Types: Asset, Liability, Equity, Revenue, Expense (+ contra). Groups roll up for statements. Control accounts (AR, AP, Inventory, Tax, Fixed Assets, Payroll clearing) are posted **only** by their subledgers, never by manual JV (enforced).

**Journal Entry / Lines:** `JournalEntry(number, date, description, reference, source_type, source_id, currency, fx_rate, status)`; `JournalLine(account_id, debit_base, credit_base, debit_txn, credit_txn, cost_center_id, project_id, branch_id, tax_id, memo)`.

**Posting rules:** a posting service resolves accounts from configurable mappings (per company/branch), builds balanced lines, asserts `Σdebit_base == Σcredit_base`, verifies the target period is **open**, writes JE + lines + `LedgerEntry` rows + subledger row + any stock movement — **atomically** in one DB transaction. Draft/Submitted/Approved may be edited; **Posted is immutable**.

**Reversal / unposting:** posted entries are corrected by an automatic **reversing entry** (mirror Dr/Cr, linked to original). Direct unposting is allowed only in an open period, with the `Reverse`/`Unpost` permission, and always leaves an audit trail; closed-period entries can only be reversed into an open period.

**Fiscal structure:** `FiscalYear` → `FinancialPeriod`(open/closed/reopened). Period close blocks new postings (override needs `Reopen` permission + audit). **Year-end close** rolls net P&L into Retained Earnings and opens the next year with carried balances (opening-balance JV). Opening balances entered via dedicated opening template.

**Subledgers reconciled to GL control accounts:** AR (customers), AP (suppliers), Cash, Bank, Inventory, Fixed Assets, Payroll, Tax, Equity/Partners. Each subledger balance must equal its GL control account (reconciliation report, B9 + BUSINESS_RULES).

**Multi-currency:** see A4. Exchange-rate table by date; realized/unrealized gain-loss accounts.

**Outputs:** General Journal, General Ledger, Trial Balance, Income Statement, Balance Sheet, Cash Flow, Statement of Changes in Equity — all derived **only** from posted data, all drill-down to source.

## B2. Inventory Engine (quantity + valuation)
**Master:** `Product(sku, barcode, name_en/ar, category, brand, base_uom, cost_method, standard_cost, sell_price, min_stock, reorder_level, is_stock_tracked)`; `Uom` + `UomConversion(from,to,factor)`; `Warehouse` + `Location`.
**Movements:** `StockMovement(product_id, warehouse_id, location_id?, type, qty_signed, uom, unit_cost_base, source_type, source_id, date)`. Types: opening, purchase, sale, sales_return, purchase_return, transfer_out, transfer_in, adjustment, damage, loss, consumption, count_adjustment.
**Stock ledger** (per product/warehouse) gives running qty + value; **valuation** per **costing method**.
**Costing method — `DECISION REQUIRED` (default proposed: Weighted Average, per-product override to FIFO).** Both fully specified: WAVG maintains running average cost; FIFO maintains `StockLayer` cost layers consumed oldest-first. COGS is emitted by the posting engine on each outbound movement using the method's cost.
**Negative stock policy — `DECISION REQUIRED` (default proposed: block outbound below zero; allow per-warehouse override with warning + later cost correction).**
**Stock counts / reconciliation:** `StockCount` sessions → variance → `count_adjustment` movements + adjustment JV (Inventory vs Inventory Adjustment account).
**Transfers:** two-legged (out/in) with in-transit option; adjustments/damage/loss/consumption each post the configured expense/variance account.

## B3. Sales Engine (end-to-end)
Flow: **Quotation → Sales Order → Delivery Note → Sales Invoice → Receipt**, plus **Credit Note / Sales Return**. Supports cash & credit sales, partial payments, customer advances, line/%-discounts, taxes, multi-warehouse, project/cost-center tagging, payment terms & due dates, over/underpayment, **payment allocation** (receipt→invoices), outstanding balances. Invoice posting emits AR + Revenue + Output VAT (+ COGS/Inventory for stock items). Returns/credit notes reverse proportionally. Cancellation (pre-post) vs reversal (post). Delivery drives stock-out; invoice may be from delivery or standalone.

## B4. Purchasing Engine (end-to-end)
Flow: **Purchase Request → Purchase Order → Goods Received Note → Purchase Invoice → Payment**, plus **Purchase Return / Debit Note**. Cash & credit purchases, partial payments, supplier advances, discounts, taxes (Input VAT + withholding), landed cost `DECISION REQUIRED` (proposed: supported via cost-allocation on GRN), cancellation/reversal, payment allocation. GRN drives stock-in at cost; invoice posts Inventory/Expense + Input VAT + AP.

## B5. Rental Engine
Lifecycle: **Customer → Contract → Equipment Allocation → Delivery → Rental Period → Extensions → Extra/Late/Damage Charges → Return → Inspection → Damage/Loss → Final Invoice → Payment**. Auto-calc: days/months, rental amount (daily/monthly rate), deposit, extra charges, late fees, discounts, paid, remaining. Deposit posts to Customer Deposit Liability (refunded/applied at close). Rental invoice posts AR + Rental Revenue + Output VAT. Equipment status is driven by rental events (integrates with Tools & Equipment, C6). Alerts: ending-soon, overdue returns, expiring contracts.

## B6. Document Numbering Engine
`NumberSequence(doc_type, prefix, include_year, include_branch, padding, next_value, reset_policy[never|yearly|monthly])`. Format e.g. `INV-2026-00001`, `PUR-`, `REC-`, `PAY-`, `JV-`, `RENT-`, `GRN-`, `DN-`, `CN-`, `PO-`, `PR-`, `EXP-`, `FA-`, `DEP-`, `PR-PAY-`. Allocation is **concurrency-safe** (DB row lock / sequence) and gap/reset policy configurable. Uniqueness enforced by DB constraint.

## B7. Approval Workflow Engine
Configurable per document type: `ApprovalFlow(doc_type, steps[])`, `ApprovalStep(order, approver_role/user, condition[amount>x, branch, project])`, `ApprovalAction(entry, actor, decision[approve|reject], reason, at)`. Documents expose current status, current approver, history, rejection reason, comments. Approval permissions are **separate** from operational permissions (PERMISSION_MATRIX). Examples in WORKFLOW_CATALOG.

## B8. Recurring Transaction Engine
`RecurringTemplate(doc_type, frequency, start, end, next_run, amount/lines, account, tax, cost_center, project, auto_create[bool], approval_required[bool])`. Worker generates the target document on `next_run`, with duplicate-prevention (idempotency key per period), failure handling + retry, and full audit. Applies to recurring expenses, revenue, rent, subscriptions, journal templates.

## B9. Reporting Engine
Reusable architecture: a report = definition(source query + parameters + columns + calc + grouping) + a viewer. Universal params where applicable: date range, fiscal period, branch, project, cost center, account, customer, supplier, warehouse, status, currency, comparison period. Universal features: search, sort, group, drill-down to source, export (XLSX/CSV/PDF), print (light print theme, company header/footer/page numbers), full AR/EN + RTL/LTR. All financial reports read posted data only. Full list in REPORT_CATALOG.

## B10. RBAC Engine
Permissions at **Module → Feature → Action**; actions: View, Create, Edit, Delete, Submit, Approve, Reject, Post, Cancel, Reverse, Export, Print, Configure. Scope restrictions: company, branch, warehouse, project, cost center, document type. Role templates: Admin, Accountant, Sales, Purchases, Warehouse, Management + custom roles. Enforced server-side; permission-denied is an explicit UI state. Full grid in PERMISSION_MATRIX.

## B11. Audit Engine
`AuditLog(entity_type, entity_id, action, actor, before_json, after_json, at, ip)`. Lifecycle actor/time stamps on records (A3). Financial audit history is immutable/append-only. Every create/modify/approve/post/reverse/cancel/delete recorded; before/after captured for edits.

## B12. Tax Engine (configurable — nothing hardcoded)
`Tax(name_en/ar, kind[input_vat|output_vat|withholding], rate, account_id, is_compound, effective_from/to, rounding)`. Documents reference tax codes on lines; engine computes tax amounts, posts to Input/Output VAT / Withholding Payable accounts, and feeds tax periods & reports. Adaptable to Egyptian VAT (currently 14% default, configurable) and withholding; calculations reviewable before filing. Tax periods with a filing/return workspace.

## B13. Notification Engine
`Notification(type, target_ref, actor, read, at)`. Types: invoice overdue, payment due, low stock, rental ending/overdue, approval pending, reconciliation pending, budget exceeded, tax deadline, recurring due, cheque due/returned. Each links to its record. Generated by jobs + event hooks; surfaced in header bell + notification center; per-user preferences.

---

# PART C — MODULE SPECIFICATIONS

> Every module inherits the **Universal UI Contract (A2)**, **Record Contract (A3)**, and relevant engines (Part B). Below, each module states purpose, users/roles, entities & key fields, screens/routes, workflow & statuses, validation & business rules, approvals, financial/inventory/AR-AP/tax/project impacts, notifications, and reports. "States/i18n/RTL/theme/responsive" are per A2 unless a deviation is noted.

## C1. Accounting & General Ledger
- **Purpose:** the ledger spine — CoA, journals, ledger, trial balance, periods.
- **Users/Roles:** Accountant (create/approve/post/reverse), Admin (full), Management (view). Sales/Purchasing/Warehouse: none (their docs auto-post).
- **Entities:** Account, AccountGroup, JournalEntry, JournalLine, LedgerEntry, FiscalYear, FinancialPeriod, ExchangeRate, OpeningBalance. Fields per B1.
- **Screens/routes:** `/accounting/coa` (tree + create/edit account), `/accounting/journal` (list/detail/create/edit — **journal line editor** with live Dr/Cr balance indicator), `/accounting/ledger` (account ledger w/ running balance, drill to source), `/accounting/trial-balance`, `/accounting/periods` (open/close/reopen, year-end close wizard), `/accounting/opening-balances`, `/accounting/fx-rates`.
- **Workflow/statuses (JE):** Draft → Submitted → Approved → Posted → (Reversed|Cancelled). Manual JV requires balanced Dr/Cr and open period.
- **Business rules:** Σdebit=Σcredit; no manual posting to control accounts; no posting to closed period; posted immutable → reverse only. See BUSINESS_RULES.
- **Impacts:** is the accounting target of all other modules; produces Financial Statements (C2).
- **Reports:** General Journal, General Ledger, Trial Balance (+ comparative), Account Statement.
- **Deviations:** journal line editor supports keyboard-fast entry, cost-center/project/tax per line, attachment per entry.

## C2. Financial Statements
- **Purpose:** Income Statement, Balance Sheet, Cash Flow, Statement of Changes in Equity.
- **Users/Roles:** Accountant, Management, Admin (view/export/print).
- **Entities:** derived views over posted `LedgerEntry` + `AccountGroup` mapping; `StatementLayout` config for grouping/order.
- **Screens/routes:** `/accounting/statements/income`, `/balance-sheet`, `/cash-flow`, `/equity`. Period selector (monthly/quarterly/yearly/custom), comparative & prior-year, **budget vs actual** toggle (integrates C20).
- **Business rules:** derive only from posted data; drill-down Statement → account → ledger → JE → source document; Balance Sheet must balance (Assets = Liabilities + Equity).
- **Impacts:** consumes GL; feeds Dashboard KPIs (C23) via the same query definitions (no independent recomputation).
- **Reports/Print:** each statement printable with company header, period, currency, generated timestamp, page numbers; AR/EN.

## C3. Sales
- **Purpose:** full order-to-cash. Engine B3.
- **Users/Roles:** Sales (create/edit/submit), Management (approve), Accountant (post/view), Warehouse (delivery).
- **Entities:** SalesDoc(type: quotation|order|delivery|invoice|credit_note), SalesDocLine, Receipt, Allocation, CustomerAdvance. Fields: customer, warehouse, project, cost_center, currency, fx_rate, payment_terms, due_date, discounts, tax per line, totals (subtotal/discount/tax/total/paid/remaining).
- **Screens/routes:** `/sales/quotations`, `/orders`, `/deliveries`, `/invoices`, `/returns`, `/receipts`; each list+detail+create+edit; detail has tabs **Overview · Items · Payments · Accounting · Attachments · Activity/Audit**. Convert actions (quote→order→delivery→invoice). Payment allocation drawer.
- **Workflow/statuses:** Draft→Submitted→Approved→Posted→(Partially Paid→Paid)→Closed; Cancelled/Reversed/Returned. Delivery: Draft→Confirmed→Delivered.
- **Business rules:** cannot sell unavailable stock (per negative-stock policy); paid ≤ invoice total; posted invoice immutable; return ≤ invoiced qty. Credit limit check on customer.
- **Impacts:** Inventory (stock-out + COGS), AR, Tax (Output VAT), GL, Project/CC. Events in ACCOUNTING_EVENT_MAP.
- **Reports:** Sales, Customer Sales, Invoice Report, Returns, Collections.

## C4. Purchasing
- **Purpose:** procure-to-pay. Engine B4.
- **Users/Roles:** Purchases (create/edit/submit), Management (approve PO), Warehouse (GRN), Accountant (post/pay/view).
- **Entities:** PurchaseDoc(type: request|order|grn|invoice|debit_note), PurchaseDocLine, Payment, Allocation, SupplierAdvance, LandedCost.
- **Screens/routes:** `/purchasing/requests`, `/orders`, `/goods-received`, `/invoices`, `/returns`, `/payments`; list+detail+create+edit; same tab set + Accounting tab. Convert PR→PO→GRN→Invoice.
- **Workflow/statuses:** Request→Approved→PO→Sent→GRN→Invoice→Posted→(Partially Paid→Paid)→Closed; Cancelled/Reversed/Returned.
- **Business rules:** GRN ≤ PO qty (over-receipt tolerance configurable); invoice matched to GRN/PO (3-way match); posted immutable.
- **Impacts:** Inventory (stock-in at cost + landed cost allocation), AP, Tax (Input VAT + withholding), GL, Project/CC.
- **Reports:** Purchases, Supplier Purchases, Purchase Invoices, Returns.

## C5. Inventory
- **Purpose:** quantity + valuation. Engine B2.
- **Users/Roles:** Warehouse (movements/transfers/adjust/count), Accountant (valuation/view), Management (view).
- **Entities:** Product, Category, Uom, UomConversion, Brand, Warehouse, Location, StockMovement, StockLayer, StockCount, StockCountLine.
- **Screens/routes:** `/inventory/products` (+ detail: **stock card**, movement timeline, warehouse balances, cost history), `/categories`, `/uom`, `/warehouses`, `/movements`, `/transfers`, `/adjustments`, `/counts` (count session wizard), `/valuation`, `/low-stock`, `/aging`.
- **Workflow/statuses:** Transfer: Draft→In-Transit→Received. Count: Draft→Counting→Reviewed→Posted. Adjustment: Draft→Approved→Posted.
- **Business rules:** every movement has a source; cannot transfer more than available; valuation reconciles to inventory GL control; count variance posts adjustment JV; costing per B2 method.
- **Impacts:** COGS + Inventory GL on outbound; Inventory GL on inbound; adjustments/damage/loss/consumption post to configured accounts; Project/CC on consumption.
- **Reports:** Inventory Valuation, Stock Movement, Stock Aging, Low Stock, Cost History.

## C6. Tools & Equipment
- **Purpose:** custody/asset tracking of operational equipment (feeds Rentals).
- **Users/Roles:** Warehouse/Ops (custody, maintenance), Management (view), Accountant (value link).
- **Entities:** Equipment(item, category, serial, condition, value, location, responsible_employee_id, status), CustodyEvent, MaintenanceRecord.
- **Screens/routes:** `/equipment` (list + detail with **custody history timeline**, maintenance log, availability), `/equipment/categories`, `/equipment/maintenance`.
- **Workflow/statuses (state machine):** Available → Assigned → Returned; Available → Rented → Returned; Assigned → Transferred; any → Maintenance → Available; any → Damaged → Maintenance/Disposed; any → Lost; Available/Damaged → Disposed. (WORKFLOW_CATALOG has the full transition table.)
- **Business rules:** status transitions restricted to valid edges + permission; serial-tracked items are unit-level; rented status driven by Rentals; disposal/loss may trigger Fixed-Asset disposal accounting if capitalized.
- **Impacts:** links to Rentals (allocation) and optionally Fixed Assets (if the equipment is a capitalized asset) for loss/damage/disposal accounting; maintenance cost → Expenses/Project.
- **Reports:** Equipment Register, Custody/Movement History, Availability, Maintenance, Loss/Damage.

## C7. Rental Management
- **Purpose:** rental lifecycle & profitability. Engine B5.
- **Users/Roles:** Rental user (contract/allocate/return), Management (approve), Accountant (invoice/post), Warehouse (deliver/return).
- **Entities:** RentalContract, RentalLine(equipment), RentalCharge(extra|late|damage|additional), Deposit, RentalInvoice(=SalesDoc rental type), Inspection.
- **Screens/routes:** `/rentals` workspace (Active, Ending Soon, Overdue Returns, Returned tabs), `/rentals/contracts` (create wizard: customer→equipment→period→rates→deposit), contract detail (allocation, extensions, charges, return + inspection, invoice, payments, Accounting tab), `/rentals/revenue`, `/rentals/profitability`.
- **Workflow/statuses:** Draft→Confirmed→Active→(Extended)→Return Requested→Inspected→Invoiced→Closed; Cancelled.
- **Business rules:** auto-calc days/months, amounts, late/damage/extra, deposit, paid, remaining; equipment must be Available to allocate; overdue detection; deposit liability tracked until close.
- **Impacts:** AR, Rental Revenue, Output VAT, deposit liability, GL, Project/CC; Equipment status (C6).
- **Reports:** Active Rentals, Ending Soon, Overdue Returns, Rental Revenue, Rental Profitability.
- **Notifications:** ending-soon, overdue return, contract expiring.

## C8. Customers & Accounts Receivable
- **Purpose:** customer master + AR subledger.
- **Users/Roles:** Sales (create/edit), Accountant (view/statement/allocation), Management (view).
- **Entities:** Customer(profile, contacts, opening_balance, credit_limit, payment_terms, name_ar/en), plus AR views over SalesDocs/Receipts/CreditNotes/Advances.
- **Screens/routes:** `/customers` (list + detail: overview balance/overdue/current, invoices, payments, credit notes, returns, advances, **statement**, **aging**), `/customers/statements`, `/customers/aging`.
- **Business rules:** AR subledger reconciles to AR control GL; aging buckets Current/1-30/31-60/61-90/90+; credit-limit enforcement on sales; advances allocatable.
- **Impacts:** AR control account; feeds Dashboard receivables + aging.
- **Reports:** Customer Statement, AR Aging, Customer Balances.

## C9. Suppliers & Accounts Payable
- **Purpose:** supplier master + AP subledger (mirror of C8).
- **Entities:** Supplier(profile, opening_balance, payment_terms, name_ar/en) + AP views over PurchaseDocs/Payments/DebitNotes/Advances.
- **Screens/routes:** `/suppliers` (list + detail mirroring customer: invoices, payments, returns, advances, statement, aging), `/suppliers/statements`, `/suppliers/aging`.
- **Business rules:** AP subledger reconciles to AP control GL; aging buckets identical; advances allocatable.
- **Reports:** Supplier Statement, AP Aging, Supplier Balances.

## C10. Cash Management
- **Purpose:** multiple cash accounts, receipts/payments/transfers, petty cash, cash book.
- **Users/Roles:** Cashier/Accountant (create/post), Management (view/approve).
- **Entities:** CashAccount, CashReceipt, CashPayment, CashTransfer, PettyCash(float, replenishment).
- **Screens/routes:** `/cash/accounts`, `/cash/receipts`, `/cash/payments`, `/cash/transfers`, `/cash/petty-cash`, `/cash/cash-book` (Opening + Receipts − Payments ± Transfers = Closing).
- **Workflow/statuses:** Draft→Approved→Posted; Cancelled/Reversed.
- **Business rules:** each movement posts to GL; cannot pay more than available (policy); transfers two-legged.
- **Impacts:** Cash GL, AR/AP (when settling), Expenses; Project/CC on payments.
- **Reports:** Cash Book, Cash Position, Petty Cash Ledger.

## C11. Banks
- **Purpose:** bank accounts, deposits/withdrawals/transfers/charges, reconciliation.
- **Entities:** BankAccount, BankTransaction(deposit|withdrawal|transfer|charge), BankReconciliation, ReconciliationLine, BankStatementImport.
- **Screens/routes:** `/banks/accounts`, `/banks/transactions`, `/banks/transfers`, `/banks/charges`, `/banks/reconciliation` (compare system vs statement; match exact/partial/manual; import statement), `/banks/bank-book`.
- **Workflow/statuses:** transaction Draft→Posted; reconciliation Draft→In-Progress→Reconciled.
- **Business rules:** bank movement posts to GL; reconciliation ties matched items; unreconciled surfaced; bank charges post to expense.
- **Reports:** Bank Book, Bank Reconciliation, Bank Position.

## C12. Cheques
- **Purpose:** incoming & outgoing cheque lifecycle.
- **Entities:** Cheque(direction[in|out], number, bank, amount, issue_date, due_date, party, status), ChequeEvent.
- **Screens/routes:** `/cheques/incoming`, `/cheques/outgoing`, cheque detail with **timeline**.
- **Workflow/statuses (state machine):** Issued/Received → Pending → Deposited → Cleared → (Returned) → (Cancelled). Each transition posts accounting (e.g., cleared incoming: Dr Bank / Cr Cheques-under-collection).
- **Business rules:** due-date alerts; returned cheque reverses; links to customer/supplier & receipt/payment.
- **Impacts:** Cheques-under-collection / Cheques-payable clearing accounts, Bank, AR/AP.
- **Reports:** Cheque Register (in/out), Due Cheques, Returned Cheques.
- **Notifications:** cheque due, cheque returned.

## C13. Expenses
- **Purpose:** expense capture, approval, payment, accounting; includes recurring expenses.
- **Users/Roles:** any submitter (create), Management (approve), Accountant (post/pay).
- **Entities:** Expense(category, account_id, supplier?/employee?, project, cost_center, branch, tax, method, attachment, status), ExpenseCategory.
- **Screens/routes:** `/expenses` (list+detail+create), `/expenses/categories`, recurring via C21.
- **Workflow/statuses:** Draft→Submitted→(Manager Approval)→(Accounting Approval)→Posted→Paid. (Configurable via B7.)
- **Business rules:** attachment/approval rules per category/amount; input VAT if applicable; posted immutable.
- **Impacts:** Expense account + Input VAT + Cash/Bank or AP; Project/CC.
- **Reports:** Expense by Category, by Project, by Cost Center, Recurring Expenses.

## C14. Prepaid & Accrued Expenses
- **Purpose:** period-correct recognition.
- **Entities:** PrepaidSchedule(total, start, months, recognized, remaining), PrepaidRecognition; AccrualSchedule, AccrualEntry.
- **Screens/routes:** `/expenses/prepaids` (schedule + remaining balance), `/expenses/accruals`.
- **Workflow:** Prepaid: create asset → monthly recognition job posts Dr Expense / Cr Prepaid. Accrual: recognize Dr Expense / Cr Accrued liability → later settlement reverses/pays.
- **Business rules:** schedule sums reconcile; recognition only in open periods; job-driven with audit.
- **Impacts:** Prepaid asset / Accrued liability / Expense; GL. Auto journals via B1+B8.
- **Reports:** Prepaid Schedule & Balances, Accruals Schedule.

## C15. Fixed Assets
- **Purpose:** asset register, depreciation, disposal.
- **Entities:** FixedAsset(code, category, purchase_date, cost, residual, useful_life, method, accum_depreciation, nbv, location, responsible), DepreciationEntry, AssetTransfer, AssetRevaluation, AssetDisposal.
- **Screens/routes:** `/assets/register` (+ detail: **depreciation schedule**, events), `/assets/categories`, `/assets/depreciation` (run + preview), `/assets/disposals`, `/assets/transfers`, `/assets/revaluations`.
- **Workflow/statuses:** Acquisition→Capitalized→(Depreciating)→(Transferred|Revalued|Maintenance)→Disposed/Sold.
- **Business rules:** depreciation method (straight-line/declining — configurable) posts monthly Dr Depreciation Expense / Cr Accumulated Depreciation; disposal computes gain/loss; NBV = cost − accum.
- **Impacts:** Fixed Asset, Accumulated Depreciation, Depreciation Expense, Cash/AP, Gain/Loss on disposal; Project/CC.
- **Reports:** Asset Register, Depreciation Schedule, Disposals, NBV by category.

## C16. Payroll
- **Purpose:** employee pay run to journal & payment.
- **Entities:** Employee(dept, position, salary, allowances, deductions, loans, advances), PayrollRun(period), PayrollLine(gross, allowances, overtime, bonus, deductions, loan, advance, net), PayrollLiability.
- **Screens/routes:** `/payroll/employees`, `/payroll/runs` (draft→review→approve→post→pay), `/payroll/loans`, `/payroll/advances`.
- **Workflow/statuses:** Draft→Review→Approved→Posted→Paid.
- **Business rules:** net = gross + allowances + overtime + bonus − deductions − loan/advance repayments; payroll JE balances; taxes/social as configured liabilities.
- **Impacts:** Salary/Wage Expense (Dr); Net Pay Payable + Deductions/Tax/Social Payable (Cr); on payment Dr payables / Cr Cash-Bank; Project/CC allocation of labor.
- **Reports:** Payroll Register, Payslips, Loan/Advance balances, Payroll liabilities.

## C17. Taxes
- **Purpose:** configurable tax administration + filing prep. Engine B12.
- **Entities:** Tax, TaxPeriod, TaxReturn(draft/filed), TaxTransaction (derived from posted docs).
- **Screens/routes:** `/taxes/config` (rates/kinds/accounts), `/taxes/periods`, `/taxes/returns` (review before filing), `/taxes/reports`.
- **Business rules:** no hardcoded rates; Input vs Output vs Withholding tracked to separate accounts; tax payable = Output − Input (± withholding); reviewable before filing; adaptable to Egyptian VAT.
- **Impacts:** Input/Output VAT, Withholding Payable, Tax Payable settlement.
- **Reports:** VAT Report (input/output/net), Withholding Report, Tax Liability.

## C18. Partners & Equity
- **Purpose:** capital, partner accounts, distributions, retained earnings.
- **Entities:** Partner, CapitalContribution, PartnerWithdrawal, PartnerCurrentAccount, PartnerLoan, ProfitDistribution, EquityMovement.
- **Screens/routes:** `/equity/partners` (+ **partner statement** & balance), `/equity/capital`, `/equity/contributions`, `/equity/withdrawals`, `/equity/loans`, `/equity/distributions`, `/equity/retained-earnings`.
- **Workflow/statuses:** transaction Draft→Approved→Posted.
- **Business rules:** contribution Dr Cash/Bank / Cr Capital-Equity; withdrawal Dr Partner Current / Cr Cash-Bank; distribution allocates retained earnings; partner loan tracked as receivable/payable; year-end retained earnings roll (B1).
- **Reports:** Partner Statement, Equity Statement (=C2 SoCE), Distributions.

## C19. Projects & Cost Centers
- **Purpose:** dimensional analysis + profitability.
- **Entities:** Project(revenue/cost rollups), CostCenter(type: dept|branch|project|unit), allocation rules.
- **Screens/routes:** `/projects` (+ detail: revenue, direct cost, indirect cost, expenses, **profitability**, drill-down), `/cost-centers` (+ department reporting), allocation config.
- **Business rules:** transactions optionally/mandatorily tagged with branch/project/cost center (configurable per doc type); indirect cost allocation rules; profitability = revenue − direct − allocated indirect.
- **Impacts:** every tagged posting rolls into project/cost-center reports; no double counting with GL.
- **Reports:** Project Profitability, Cost Center Report, Department Spend, Revenue by Project.

## C20. Budgeting & Forecasting
- **Purpose:** budgets, versions, variance, forecasts.
- **Entities:** Budget(year, version, status), BudgetLine(account/project/cost-center/period, amount), Forecast(sales|expense|cash|profit), ForecastLine.
- **Screens/routes:** `/budgeting/budgets` (annual & monthly, **versions**, approval), `/budgeting/variance` (Budget/Actual/Variance/Variance%), `/budgeting/forecasts` (sales/expense/cash/profit).
- **Workflow/statuses:** Budget Draft→Submitted→Approved→Active; versions retained.
- **Business rules:** actuals sourced from posted GL by the same report queries; variance drill-down to source transactions.
- **Impacts:** read-only over GL; feeds Financial Statements budget-vs-actual (C2) and Dashboard.
- **Reports:** Budget vs Actual, Variance Analysis, Forecast vs Actual.

## C21. Recurring Transactions
- **Purpose:** engine-backed recurring documents. Engine B8.
- **Entities:** RecurringTemplate, RecurringRun(log).
- **Screens/routes:** `/recurring` (templates list + create), `/recurring/runs` (history, failures, retry).
- **Workflow:** template Active/Paused; run Pending→Generated→(Posted if auto)/Failed→Retried.
- **Business rules:** duplicate prevention per period; approval-required gate; failure handling + audit.
- **Impacts:** generates Sales/Purchase/Expense/Journal docs which then post via their own engines.
- **Reports:** Recurring Schedule, Run History.

## C22. Reports (Reporting Center)
- **Purpose:** unified workspace over engine B9; all reports from the brief.
- **Screens/routes:** `/reports` (grouped: Accounting, Sales, Purchasing, Inventory, AR/AP, Cash & Banking, Assets, Payroll, Taxes, Projects, Budget), each report route with parameter panel + viewer.
- **Business rules:** every report supports the universal params/features (B9); drill-down to source; export/print (light theme); AR/EN + RTL/LTR.
- Full enumeration: REPORT_CATALOG.

## C23. Dashboard
- **Purpose:** management KPIs — all clickable, all traceable.
- **Entities:** none new; KPI definitions map to the same report/statement queries (no independent math).
- **Screens/routes:** `/dashboard` — Financial KPIs (Revenue, Expenses, Gross/Operating/Net Profit, Cash, Bank, Receivables, Payables, Inventory Value, Fixed Assets), Operational KPIs (Outstanding/Overdue Invoices, Overdue AR/AP, Active Rentals, Rentals Ending Soon, Low Stock, Pending POs/SOs, Pending Approvals), Charts (Revenue/Expense/Gross/Net trend, Cash Flow, AR/AP Aging, Sales by Customer/Product, Revenue by Project, Expense by Category).
- **Business rules:** every KPI has a defined formula, source tables, filters, period, currency, drill-down destination, and permission (see Dashboard section in REPORT_CATALOG). Clicking a KPI navigates to underlying records; each KPI shows "where the number came from."
- **Deviations:** period/branch/project global filters; role-scoped KPI visibility; mobile prioritizes KPIs + approvals + notifications.

## C24. Users & Permissions
- **Purpose:** identity, roles, granular RBAC, approval config. Engine B10 + B7.
- **Entities:** User, Role, Permission, RolePermission, Scope, ApprovalFlow/Step.
- **Screens/routes:** `/settings/users`, `/settings/roles` (role templates + custom), `/settings/permissions` (module→feature→action grid + scope), `/settings/approvals` (per-doc-type flows).
- **Business rules:** server-side enforcement; approval perms separate from operational; scope by company/branch/warehouse/project/cost-center/doc-type; least privilege defaults.
- **Reports:** Permission Matrix export, User Activity (with C25).

## C25. Audit Trail
- **Purpose:** immutable operational history. Engine B11.
- **Screens/routes:** `/audit` (global log w/ filters: entity, user, action, date), plus per-record **Activity/Audit tab** (A3).
- **Business rules:** every important op logged; before/after on edits; financial audit append-only/immutable; export/print.
- **Reports:** Audit Report, User Activity, Change History per record.

## C26. Document Numbering
- **Purpose:** centralized, concurrency-safe numbering. Engine B6.
- **Screens/routes:** `/settings/numbering` (per doc type: prefix, year, branch, padding, sequence, reset policy, preview).
- **Business rules:** unique + gapless-per-policy; concurrency-safe allocation; never duplicate.

## C27. Settings & Configuration *(brief §56 — included to keep scope complete)*
- **Purpose:** system configuration home.
- **Screens/routes:** `/settings` hub → Company, Branches, Financial Periods, Currencies & FX, Taxes, Numbering, Accounting mappings (control accounts, FX gain/loss, rounding), Inventory (costing, negative-stock policy), Warehouses, Payment Terms, Users, Roles, Permissions, Approval Workflows, Notifications, Localization, Appearance (theme defaults), Audit settings, Integrations.
- **Business rules:** config changes are permissioned (`Configure`), audited, and where financial (tax/accounting mapping) require confirmation showing consequences.

---

# ROADMAP_10_PHASE (implementation ORDER — full scope already specified above)

For every phase: **Scope · Dependencies · DB · Backend · Frontend · Design · Accounting · Reports · Permissions · Tests · Acceptance.**

1. **Foundation** — design system + shell; auth; Users/Roles/Permissions (B10); Company/Branches; Settings shell; numbering engine (B6); audit engine (B11); i18n/theme wiring. *Accept:* a permissioned user logs in, navigates the shell in AR/EN × light/dark, and RBAC blocks unauthorized actions with the permission-denied state.
2. **Accounting core** — CoA, Journal/Lines, Ledger, Trial Balance, Fiscal years/Periods, opening balances, FX rates, posting engine (B1). *Accept:* a balanced manual JV posts, appears in ledger & trial balance, cannot post to a closed period, and reverses correctly.
3. **Customers, Suppliers, Cash, Banks, Cheques** — AR/AP subledgers, cash/bank/cheque lifecycles + reconciliation. *Accept:* a receipt/payment posts, updates subledger + GL control, and subledger reconciles to GL.
4. **Sales & Purchasing** — B3/B4 end-to-end with posting rules, allocation, returns/credit-debit notes, approvals. *Accept:* posting a sales invoice creates AR + Revenue + VAT (+ COGS/Inventory) and every figure drills to source.
5. **Inventory** — products/UoM/warehouses, movements, transfers, adjustments, counts, valuation (B2 costing). *Accept:* valuation reconciles to inventory GL; COGS matches method.
6. **Tools & Equipment + Rentals** — custody state machine + rental engine (B5) with deposits/charges/late fees + equipment status integration. *Accept:* full rental cycle produces invoice + deposit liability + correct equipment status.
7. **Expenses, Fixed Assets, Prepaids, Accruals** — expense workflow, asset register + depreciation runs, prepaid/accrual schedules & recognition jobs. *Accept:* depreciation & prepaid recognition post automatically in open periods.
8. **Payroll, Taxes, Partners & Equity** — pay runs → JE → payment; tax periods/returns; equity movements & retained earnings. *Accept:* payroll posts balanced JE; VAT report ties to posted tax accounts.
9. **Projects, Cost Centers, Budgeting & Forecasting, Recurring** — dimensional tagging + profitability; budgets/versions/variance; forecasts; recurring engine. *Accept:* budget-vs-actual and project profitability derive from posted GL; a recurring template auto-generates without duplicates.
10. **Reports, Dashboard, Analytics, Audit UX, advanced workflows** — full report catalog, KPI dashboard wired to report queries, global audit UX, notification center, bank rec polish, print/PDF. *Accept:* every dashboard KPI drills to source; the end-to-end acceptance scenario (§ below) passes in all 4 QA combos.

**End-to-end acceptance (whole system):** create customer → create & post invoice → AR, revenue, tax, inventory, COGS, journal, ledger, trial balance, financial statements, and dashboard all update automatically, and **every number traces back to the source transaction** — in EN/AR × light/dark, desktop & mobile.

