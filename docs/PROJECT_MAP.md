# Mini ERP — Project Map & Implementation Blueprint

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


**Status:** Legacy greenfield planning reference. Current Laravel source of truth is the owner corrections plus `DOMAIN_MODEL_REVIEW.md` / `IMPLEMENTATION_STATUS.md`.
**Owner:** Mahmoud Medhat · **Date:** 2026-08-20 · **Timezone:** Africa/Cairo
**Core principle:** *ENTER DATA ONCE → AUTOMATE EVERYTHING ELSE.*
One transaction → one source of truth → automatic inventory / AR-AP / cash-bank / accounting → automatic reporting → complete traceability.

> Session context: there is **no pre-existing codebase**. This is a fresh Next.js + PostgreSQL build. Sections below define *what* we will build and *in what order*, so no page is designed in isolation.

---

## 1. Technology Architecture

| Layer | Choice | Why |
|---|---|---|
| Framework | **Next.js (App Router) + TypeScript** | SSR for dense server-paginated tables; route groups per module; RSC for report queries. |
| Styling | **Tailwind CSS + CSS custom properties (design tokens)** | Logical properties for RTL/LTR; theme swap without re-render. |
| UI primitives | **Radix UI** (headless) wrapped in our own components | Accessible dialogs/menus/tabs; we own the visual layer. |
| Data | **PostgreSQL** via **Prisma** | Relational integrity is non-negotiable for accounting; transactions & constraints. |
| Server logic | **Next.js Route Handlers / Server Actions + a service layer** | Accounting side-effects live in services, never in UI. |
| Auth | **Auth.js (NextAuth)** + session + RBAC middleware | Roles/permissions enforced server-side. |
| i18n | **next-intl** (or equivalent) — AR + EN, RTL/LTR first-class | Locale in URL/segment; messages in `locales/`. |
| Money | **Integer minor units (piastres) + decimal.js** | Never float for money. |
| Validation | **Zod** shared client/server | Business rules enforced in one place. |
| Tables | **TanStack Table** + server pagination, virtualization where needed | Thousands of rows stay fast. |
| Charts | **Recharts** (token-aware, RTL-aware) | KPI/report visuals. |
| Background | **Job runner** (cron/queue) for recurring txns, depreciation, notifications | Scheduled accounting events. |

### Non-negotiable data rules
- Money stored as **integer minor units** + currency code; formatting is a presentation concern only.
- Every posted financial event writes an **immutable journal entry**; posted docs are **reversed**, never edited.
- **Double-entry invariant:** `SUM(debit) === SUM(credit)` enforced at the DB/service boundary.
- **Period lock:** no writes into a closed period without an explicit reopen permission + audit.
- All money-affecting writes run inside a **DB transaction** so side-effects are atomic (all or nothing).

---

## 2. Module Map (25 modules)

Grouped for the sidebar (see §7 of the master brief). Each module has: List · Detail · Create · Edit · plus its lifecycle/approval/posted/empty/error/loading/permission states.

**Core & Finance**
1. Dashboard
2. Accounting (Chart of Accounts, Journal, Ledger, Trial Balance, Financial Statements, Periods)
3. Cash
4. Banks
5. Cheques
6. Taxes
7. Partners & Equity

**Operations**
8. Sales (Quotation → Order → Delivery → Invoice → Receipt; Credit Note/Return)
9. Purchasing (Request → PO → Goods Received → Invoice → Payment; Debit Note/Return)
10. Inventory (Products, Warehouses, Stock movements, Valuation)
11. Tools & Equipment (custody/asset tracking)
12. Rentals (Contract → Allocation → Return → Inspection → Invoice)
13. Customers (AR)
14. Suppliers (AP)
15. Expenses (+ Prepaid / Accrued)
16. Fixed Assets (register, depreciation, disposal)
17. Payroll

**Planning & Control**
18. Projects
19. Cost Centers
20. Budgeting
21. Recurring Transactions

**Cross-cutting**
22. Reports
23. Approvals
24. Audit Trail
25. Settings

---

## 3. Data / Entity Model (key entities)

Only the load-bearing entities are listed; each has `id`, `created_at`, `created_by`, `updated_at`, `updated_by`, and soft-delete/void where relevant.

**Org & security**
- `Company` profile/configuration · standalone `Branch` reference concept · `User` · `Role` · `Permission` (module→feature→action) · `RolePermission`
- global `FiscalYear` → `FinancialPeriod` (open/closed/reopened) · `NumberSequence` (prefix, year option, next, reset policy; no company/branch dimension)

**Accounting (the spine)**
- `Account` (code, name_en, name_ar, type, parent_id, nature debit/credit, is_control, status)
- `JournalEntry` (number, date, description, reference, **source_type**, **source_id**, status: draft/submitted/approved/posted/reversed/cancelled)
- `JournalLine` (entry_id, account_id, debit_minor, credit_minor, cost_center_id, project_id, tax_id, memo)
- `LedgerEntry` (materialized posting rows for fast ledger/trial-balance) — derived from posted `JournalLine`s

> **Traceability keystone:** every `JournalEntry.source_type/source_id` points back to the originating document (invoice, payment, rental, depreciation run…). Every source document links forward to its journal entry. This bidirectional link powers the global drill-down (§39/63 of brief).

**Parties**
- `Customer` (profile, opening_balance, credit_limit, payment_terms) — derived: balance, aging buckets
- `Supplier` (mirror of Customer)

**Sales / Purchasing**
- `SalesDoc` (type: quotation|order|delivery|invoice|credit_note; customer_id, warehouse_id, project_id, cost_center_id, status, totals)
- `SalesDocLine` (product_id, qty, unit_price_minor, discount, tax_id, line_total)
- `PurchaseDoc` / `PurchaseDocLine` (mirror)
- `Receipt` / `Payment` (party, method cash|bank|cheque, account_id, allocations[])
- `Allocation` (links a receipt/payment to specific invoices)

**Inventory**
- `Product` (sku, barcode, name_en, name_ar, category, unit, brand, min_stock, reorder_level, cost_method FIFO|WAVG, standard_cost, sell_price)
- `Warehouse` (+ `Location`)
- `StockMovement` (product_id, warehouse_id, type: opening|purchase|sale|return|transfer|adjustment|damage|loss|consumption, qty_signed, unit_cost_minor, source_type/source_id)
- `StockLayer` (FIFO cost layers) / running WAVG cache

**Tools & Rentals**
- `Equipment` (item, category, serial, condition, value, location, responsible_employee_id, status)
- `CustodyEvent` (equipment_id, from/to, type assign|return|rent|maintenance|damage|lost)
- `RentalContract` (customer_id, start, end, daily_rate, monthly_rate, deposit, discount, charges) → `RentalLine` (equipment), `RentalCharge` (late/damage/additional)

**Assets / Payroll / Expenses**
- `FixedAsset` (code, category, cost, residual, useful_life, method, accum_depreciation) → `DepreciationEntry`
- `Employee` (dept, position, salary, allowances, deductions, loans) → `PayrollRun` → `PayrollLine`
- `Expense` (category, account_id, tax_id, cost_center_id, project_id, method, attachment) · `PrepaidSchedule` · `AccrualSchedule`

**Control**
- `Tax` (name_en/ar, rate, kind input|output|withholding, account_id) — **configurable, nothing hardcoded**
- `Project` (revenue/cost rollups) · `CostCenter` (dept|branch|project|unit)
- `Budget` / `BudgetLine` · `RecurringTemplate` (frequency, next_run, auto_create, approval_required)
- `ApprovalFlow` / `ApprovalStep` / `ApprovalAction`
- `AuditLog` (entity, entity_id, action, actor, before, after, at)
- `Notification` (type, target_ref, read, actor)
- `Attachment` (polymorphic: entity_type/entity_id, file ref)

---

## 4. Accounting Automation Engine — event map

The heart of the product. Each operational document, on **post**, calls a rule that emits a balanced `JournalEntry`. Users never hand-write these for normal operations. Every document exposes an **Accounting tab** ("View Accounting Entry / View Ledger Impact / View Source").

| Business event | Debit | Credit | Extra (if stock item) |
|---|---|---|---|
| **Sales Invoice** | Customer AR | Sales Revenue; Output VAT | Dr COGS / Cr Inventory |
| **Sales Return / Credit Note** | Sales Returns; Output VAT | Customer AR | Dr Inventory / Cr COGS |
| **Customer Receipt** | Cash/Bank | Customer AR | — |
| **Purchase Invoice** | Inventory/Expense; Input VAT | Supplier AP | — |
| **Purchase Return / Debit Note** | Supplier AP | Inventory/Expense; Input VAT | — |
| **Supplier Payment** | Supplier AP | Cash/Bank | — |
| **Expense** | Expense; Input VAT (if any) | Cash/Bank or AP | — |
| **Prepaid recognition (monthly)** | Expense | Prepaid asset | — |
| **Accrued expense** | Expense | Accrued liability → later Cr Cash/Bank | — |
| **Rental invoice** | Customer AR | Rental Revenue; Output VAT | — |
| **Rental deposit received** | Cash/Bank | Customer Deposit Liability | — |
| **Fixed asset purchase** | Fixed Asset; Input VAT | Cash/Bank or AP | — |
| **Depreciation run** | Depreciation Expense | Accumulated Depreciation | — |
| **Asset disposal/sale** | Cash/Bank; Accum Deprec | Fixed Asset; (gain/loss) | — |
| **Payroll post** | Salary/Wage Expense | Net Pay Payable; Deductions/Tax Payable | — |
| **Cash/Bank transfer** | Destination account | Source account | — |
| **Cheque cleared (incoming)** | Bank | Cheques-under-collection | — |
| **Inventory adjustment/loss/damage** | Inventory Loss Expense | Inventory | — |
| **Opening balances** | per opening template | per opening template | — |

**Engine design:** a `PostingRule(sourceType)` service resolves the accounts (from Settings → Accounting mapping, per company/branch), computes lines, asserts `debit==credit`, checks the period is open, writes `JournalEntry` + `JournalLine` + `LedgerEntry` + any `StockMovement`, all inside one DB transaction. Account resolution is **configurable mapping**, never literals in code.

---

## 5. Roles & Permissions matrix (starter)

Permissions operate at **Module → Feature → Action**. Actions: View, Create, Edit, Delete, Approve, Post, Reverse, Export, Print.

| Module area | Admin | Accountant | Sales | Purchasing | Warehouse | Management |
|---|---|---|---|---|---|---|
| Accounting / Journal / Post | Full | Create·Approve·Post·Reverse | — | — | — | View |
| Sales docs | Full | View·Post | Create·Edit·Submit | — | View | View·Approve |
| Purchasing docs | Full | View·Post | — | Create·Edit·Submit | Goods-receipt | View·Approve |
| Inventory / Stock moves | Full | View | View | View | Create·Transfer·Adjust | View |
| Rentals | Full | View·Post | Create·Edit | — | Allocate·Return | View·Approve |
| Cash / Bank / Cheques | Full | Create·Post·Reconcile | — | — | — | View·Approve |
| Payroll | Full | Create·Approve·Post | — | — | — | Approve |
| Settings / Tax / Numbering | Full | Tax (review) | — | — | — | View |
| Reports | Full | Full | Sales reports | Purchasing reports | Inventory reports | Full |
| Period close | Full | Close·Reopen(perm) | — | — | — | View |

Record-level authorization, where explicitly defined by a module, must be enforced **server-side**; UI shows a **permission-denied state**, never a dead button. Do not infer branch/company ownership from role names.

---

## 6. Document numbering & lifecycle

- Centralized `NumberSequence`: `PREFIX-YYYY-NNNNN` (e.g., `INV-2026-00001`, `PUR-`, `REC-`, `PAY-`, `JV-`, `RENT-`). Configurable prefix / year / reset policy. Company/branch numbering dimensions are not approved. Uniqueness guaranteed by DB constraint + sequence table (no client-side generation).
- Standard lifecycle: **Draft → Submitted → Approved → Posted → Paid/Completed → Closed**; alternates: Rejected, Cancelled, Reversed, Returned. Status is always visually explicit (badge + color + icon + label — never color alone).

---

## 7. Reports catalog

Accounting: General Journal, General Ledger, Trial Balance, Income Statement, Balance Sheet, Cash Flow, Equity. Sales: Sales, Customer Sales, Invoices, Returns, Collections. Purchasing: mirror. Inventory: Valuation, Movement, Aging, Low Stock. AR/AP: Statements, Aging. Cash/Bank: Cash Book, Bank Book, Reconciliation. Assets: Register, Depreciation. Payroll, Taxes, Projects (Profitability), Cost Center, Budget vs Actual / Variance.
Every report: date range · filters · search · grouping · sorting · export · print · **drill-down to source**. Print/PDF uses a light print theme (never the dark UI).

---

## 8. Notifications & scheduled events

Invoice overdue · payment due · low stock · rental ending/overdue · approval pending · reconciliation pending · budget exceeded · tax deadline · recurring txn due. Each links directly to its record. Backed by scheduled jobs that also run depreciation, prepaid recognition, and recurring-template creation.

---

## 9. Implementation phases (build order)

1. **Foundation** — design system, app shell, auth, users/roles/permissions, company/branches, settings. *(design foundation delivered this session)*
2. **Accounting core** — CoA, Journal, Ledger, Trial Balance, Periods.
3. **Customers, Suppliers, Cash, Banks.**
4. **Sales, Purchasing** (+ posting rules).
5. **Inventory** (movements, valuation FIFO/WAVG).
6. **Tools & Equipment, Rentals.**
7. **Expenses, Fixed Assets, Prepaids, Accruals.**
8. **Payroll, Taxes, Partners.**
9. **Projects, Cost Centers, Budgeting, Recurring.**
10. **Reports, Dashboard, Analytics, Audit, advanced workflows.**

Rule: do not advance a phase until the current foundation is stable. Per feature: understand → inspect → design UX → reusable components → frontend → backend → validation → permissions → audit → accounting/inventory side-effects → states → test → responsive → RTL/LTR → regression.

---

## 10. Prioritization when requirements conflict
1. Accounting integrity → 2. Data integrity → 3. Security → 4. User workflow → 5. Reporting → 6. Visual polish.

## 11. Acceptance criterion
A user creates a customer → creates an invoice → posts it → AR, revenue, tax, inventory, COGS, journal, ledger, trial balance, statements, and dashboard all update automatically — and **every number drills back to the source transaction**.

## 12. Open decisions to confirm before later phases
- Inventory costing default: **FIFO or Weighted Average** (per product override supported).
- Multi-currency now vs EGP-only first (formatting layer is currency-ready regardless).
- Egypt VAT specifics & withholding rules (configurable; accountant review before filing — nothing hardcoded).
- Approval flows: fixed roles vs fully configurable per document type at launch.
