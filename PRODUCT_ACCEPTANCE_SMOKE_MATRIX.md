# Mini ERP - Product Acceptance and Accountant Smoke Matrix
# مصفوفة القبول النهائي واختبارات الدخان للمحاسبين وأصحاب الأعمال

**Version:** 1.0  
**Phase:** Phase 18 Slice 3  
**Status:** READY FOR OWNER & ACCOUNTANT ACCEPTANCE SIGN-OFF  
**Architecture:** Single-Installation Commercial ERP (No Multi-Tenancy)  
**Supported Locales:** Arabic (ar) / English (en)

---

## English Section: Product Acceptance & Smoke Testing Matrix

This matrix provides a comprehensive, practical verification plan for business owners, financial controllers, and head accountants to validate the end-to-end functionality, operational workflows, internal accounting controls, and security measures of Mini ERP before operational sign-off.

### Legend & Sign-off Status Codes
- `[ ] PENDING`: Test not yet executed.
- `[x] PASSED`: Executed and verified successfully by accountant/owner.
- `[!] FAILED`: Discrepancy observed; issue logged for review.
- `[N/A] NOT APPLICABLE`: Skipped under approved operating exception.

---

### 1. Authentication, Sessions & RBAC Governance
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| Auth & Sessions | User Login with valid credentials | User authenticates successfully, session is regenerated with a new session ID, user is redirected to `/dashboard`, active locale is loaded. | Any active user | Valid user credentials (`admin@example.com` / password) | `[ ] PENDING` |
| Auth & Sessions | User Login with invalid credentials | System throttles brute-force attempts, returns clear validation message, no session created. | Unauthenticated | Incorrect email/password | `[ ] PENDING` |
| Auth & Sessions | Inactive user login attempt | Authentication fails with inactive account notice; login is blocked. | Inactive user | User with `is_active = false` | `[ ] PENDING` |
| Auth & Sessions | User Logout | Session invalidated, CSRF token refreshed, user redirected to `/login`. | Authenticated user | Active session | `[ ] PENDING` |
| RBAC Governance | Role Assignment & Privilege Enforcement | User with `SALES` role can access Sales modules but receives `403 Forbidden` when attempting direct access to `/accounting/journal` or `/payroll/runs`. | `SALES` role vs `ACCOUNTANT` role | Test user assigned to `SALES` role template | `[ ] PENDING` |
| RBAC Governance | First-User Bootstrap Protection | Bootstrap admin seeder is disabled in production unless exact confirmation phrase is supplied; audited via Spatie Activitylog. | `SUPER_ADMIN` | Production environment flag | `[ ] PENDING` |

---

### 2. Dashboard, Navigation & Diagnostic Baseline
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| Dashboard | Executive KPI Summary Display | Dashboard loads key metrics (Accounts count, Posted journals count, Ledger entries, Unread notifications) with integer formatting and zero errors. | `dashboard.view` | Standard seed or active demo company data | `[ ] PENDING` |
| Navigation | App Shell & Multi-lingual Switching | Navigation bar renders localized links (EN/AR). Switching locale instantly updates UI language and persists preference to user profile/session. | Authenticated user | User profile | `[ ] PENDING` |
| Foundation | System Health & Diagnostics Probe | `/health` responds `200 OK` with database ping. `/foundation` renders diagnostic checks for admin. | `settings.configure` or `audit.view` | System services running | `[ ] PENDING` |

---

### 3. Company Settings, Branch Definitions & Numbering Sequences
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| Company Settings | Company Profile Management | Updates company legal name, tax number (TRN), address, base currency, and contact info. Changes audit-logged to Activitylog. | `settings.company` | Legal entity info, VAT registration number | `[ ] PENDING` |
| Branch Settings | Operational Branch Definition | Create/edit operational branches (e.g., `MAIN`, `ALEX`, `WH-01`). Branches function strictly as operational and reporting dimensions (not multi-tenant silos). | `settings.branches` | Branch code, branch bilingual names | `[ ] PENDING` |
| Numbering | Automated Document Number Sequences | Configure sequences for Invoices (`INV-YYYY-XXXXX`), Bills (`BILL-YYYY-XXXXX`), Journals (`JRN-YYYY-XXXXX`), POs, SOs. Sequence generates contiguous preview. | `settings.numbering` | Sequence prefix, yearly reset policy, padding | `[ ] PENDING` |
| User Settings | User Management & Role Mapping | Admin creates new user, sets password complying with policy (min 8 chars, mixed case, numbers, symbols), assigns role template. | `users.configure` | New user details, selected role template | `[ ] PENDING` |

---

### 4. Chart of Accounts, Categories, Types, Currencies & FX Rates
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| Chart of Accounts | Account Category & Type Setup | Standard categories (Assets, Liabilities, Equity, Revenue, Expenses) and Account Types (Current Assets, Accounts Receivable, etc.) configure GL behaviors. | `accounting.account_types`, `accounting.account_categories` | Account type definitions, category codes | `[ ] PENDING` |
| Chart of Accounts | Account Tree Creation & Validation | Create leaf accounts under parent groups (e.g. `10101 Main Cash`, `40100 Sales Revenue`). Code uniqueness enforced; cannot post to non-leaf groups. | `accounting.create` | Account code, bilingual name, currency, classification | `[ ] PENDING` |
| Currencies & FX | Multi-Currency & Daily Exchange Rates | Define foreign currencies (USD, EUR, SAR) with exact decimal precision; record dated exchange rates (`EGP` per unit) for revaluation and foreign postings. | `manage_currencies`, `manage_fx_rates` | Currency ISO code, decimal precision, exchange rate | `[ ] PENDING` |
| GL Mapping | System GL Account Mapping | Map default system accounts for AR Control, AP Control, Retained Earnings, GRNI Clearing, Inventory Asset, COGS, VAT Output/Input, FX Gain/Loss. | `accounting.mappings` | Valid active GL accounts | `[ ] PENDING` |

---

### 5. Fiscal Years, Periods, Opening Balances & Journal Lifecycle
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| Fiscal Periods | Fiscal Year & Monthly Period Creation | Create fiscal year (e.g. 2026) with 12 calendar monthly periods (`2026-01` to `2026-12`). Initial status is `open`. | `accounting.periods` | Year dates (`2026-01-01` to `2026-12-31`) | `[ ] PENDING` |
| Opening Balances | GL Opening Balances Capture & Posting | Record opening debit/credit balances across all accounts; verify total Debits == total Credits; post opening balances to create initial GL ledger entries. | `accounting.opening_balances`, `view_financials` | Trial balance from prior accounting system | `[ ] PENDING` |
| Journal Entries | Manual Journal Creation & Draft State | Create manual journal entry with lines, descriptions, debit/credit minor amounts, optional project/cost center tags; verify draft state. | `accounting.create` | Journal date, account lines, balanced amounts | `[ ] PENDING` |
| Journal Entries | Journal Submission & Approval Workflow | Submit draft journal (`draft` -> `submitted`), authorized manager approves (`submitted` -> `approved`). Unapproved journals cannot post. | `accounting.submit`, `accounting.approve` | Submitted journal entry | `[ ] PENDING` |
| Journal Entries | Journal Posting & Immutability | Post approved journal with sensitive action confirmation. System assigns contiguous `JRN-YYYY-XXXXX`, creates immutable `ledger_entry` records. Editing blocked. | `accounting.post`, `view_financials` | Approved journal entry | `[ ] PENDING` |
| Journal Entries | Journal Full Reversal | Reverse posted journal entry with sensitive action confirmation and audit reason. Creates mirror reversal entry Dr/Cr swapped, referencing original journal ID. | `accounting.reverse`, `view_financials` | Posted journal entry ID | `[ ] PENDING` |
| Period Close | Monthly Period Closing Blocker Check | Run Close Readiness check on period. System verifies zero unposted approved journals, unposted bills, or open draft transactions before allowing close. | `accounting.periods` | Period ID | `[ ] PENDING` |
| Period Close | Period Close & Post-Lock | Close period with `close_period` capability. Subsequent postings dated within closed period are strictly blocked by `PeriodGuard` with database locks. | `close_period` | Ready period | `[ ] PENDING` |
| Period Close | Controlled Period Reopen | Reopen closed period with exact `reopen_period` permission and sensitive action reason. Audited in Activitylog. | `reopen_period` | Closed period ID | `[ ] PENDING` |

---

### 6. General Ledger, Trial Balance & Financial Statements
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| General Ledger | Account Ledger Inspection | Filter GL by account, date range, branch, project, or cost center. Displays opening balance, chronological debit/credit movements, and running balance. | `accounting.view` | Account code, date range | `[ ] PENDING` |
| Trial Balance | Summary & Detailed Trial Balance | Generate Trial Balance for period. Confirms Total Initial Balance + Total Debits - Total Credits == Total Ending Balance. Total Debits strictly equals Total Credits. | `accounting.view` | Date range / fiscal period | `[ ] PENDING` |
| Financial Statements | Balance Sheet Generation & Export | Balance Sheet displays Assets == Liabilities + Equity. Unmapped account warnings shown if active. CSV export produces identical minor-unit figures. | `reports.view`, `view_financials`, `reports.export` | As-of date | `[ ] PENDING` |
| Financial Statements | Income Statement (P&L) Generation | Generates Revenues, Cost of Goods Sold, Gross Profit, Operating Expenses, and Net Profit for chosen period. Matches GL account movements. | `reports.view`, `view_financials`, `reports.export` | From date / To date | `[ ] PENDING` |
| Financial Statements | Statement of Cash Flows | Categorizes Operating, Investing, and Financing cash flows derived from posted ledger movements; excludes internal cash transfers. | `reports.view`, `view_financials`, `reports.export` | From date / To date | `[ ] PENDING` |

---

### 7. Customers, Suppliers & AR/AP Opening Balances
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| Master Data | Customer Creation & Credit Limit | Create customer with commercial name, tax registration number, credit limit, payment terms (e.g., Net 30), and default currency. | `customers.create` | Customer details, tax number | `[ ] PENDING` |
| Master Data | Supplier Creation & Bank Details | Create supplier with legal name, tax number, bank account details, and payment terms. | `suppliers.create` | Supplier details, IBAN/bank | `[ ] PENDING` |
| Subledgers | Customer AR Opening Balances | Record individual unpaid customer invoices from previous system; post to create initial `receivable_entry` subledger balances without double-counting GL. | `customers.opening_balances`, `view_financials` | Customer list, invoice numbers, open amounts | `[ ] PENDING` |
| Subledgers | Supplier AP Opening Balances | Record individual unpaid supplier bills from previous system; post to create initial `payable_entry` subledger balances. | `suppliers.opening_balances`, `view_financials` | Supplier list, bill numbers, open amounts | `[ ] PENDING` |

---

### 8. Receipts, Payments, Allocations, Cheques & Bank Reconciliation
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| Treasury | Cash & Bank Account Setup | Define cash safe accounts and corporate bank accounts with GL account linkage, bank branch, and IBAN details. | `cash.create`, `banks.create` | Bank name, account number, GL code | `[ ] PENDING` |
| Treasury | Treasury Transfer (Safe to Bank / Inter-Bank) | Record fund transfer between cash safe and bank account. Post with sensitive confirmation. Creates Dr Destination / Cr Source GL journal. | `cash.create`, `banks.create`, `cash.post` | Source account, destination account, transfer amount | `[ ] PENDING` |
| AR Receipts | Customer Receipt & Auto/Manual Allocation | Record cash/bank receipt from customer. Post receipt (Dr Cash/Bank / Cr AR Control). Allocate receipt against open invoice(s); updates remaining invoice balance. | `customers.receipts`, `customers.allocations`, `view_financials` | Customer, open invoice, payment amount | `[ ] PENDING` |
| AP Payments | Supplier Payment & Allocation | Record payment to supplier from bank/cash account. Post payment (Dr AP Control / Cr Cash/Bank). Allocate against open supplier bill(s); updates remaining balance. | `suppliers.payments`, `suppliers.allocations`, `view_financials` | Supplier, open bill, payment amount | `[ ] PENDING` |
| Allocations | Allocation Reversal | Reverse an allocation between a receipt/payment and an invoice/bill. Restores original open balances on both documents. | `customers.allocations` / `suppliers.allocations`, `sensitive.confirm` | Existing allocation ID | `[ ] PENDING` |
| Cheques | Incoming Cheque Lifecycle | Record customer cheque (`received` -> `deposited` -> `cleared` or `bounced`/`returned`). Clearing posts Dr Bank / Cr Cheques Under Collection. | `cheques.view`, `cheques.receive`, `cheques.deposit`, `cheques.clear` | Cheque number, bank name, due date, amount | `[ ] PENDING` |
| Cheques | Outgoing Cheque Lifecycle | Issue company cheque to supplier (`issued` -> `cleared` or `cancelled`/`returned`). Clearing posts Dr AP Notes Payable / Cr Bank. | `cheques.view`, `cheques.issue`, `cheques.clear` | Chequebook leaf, payee, due date, amount | `[ ] PENDING` |
| Bank Reconciliation | Bank Statement Import & Line Matching | Create bank reconciliation for bank account and statement period; add statement lines; match against posted ERP cash book entries; compute un-reconciled diff. | `banks.view`, `banks.reconcile` | Bank statement, ERP bank account, period | `[ ] PENDING` |
| Bank Reconciliation | Reconciliation Finalization | Finalize reconciliation when discrepancy == 0.00. Locks reconciliation period from duplicate matching. | `banks.reconcile`, `sensitive.confirm` | Balanced reconciliation session | `[ ] PENDING` |
| Subledger Reports | AR & AP Aging / Partner Statements | Generate Customer/Supplier Statements and 30/60/90/120-day Aging reports. Reconcile subledger totals with AR/AP Control GL balances. | `reports.view`, `view_financials` | As-of date | `[ ] PENDING` |

---

### 9. Products, Units of Measure, Warehouses & Stock Operations
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| Catalog | Units of Measure (UOM) & Categories | Define standard UOMs (Piece, Box, Kg, Meter) with base conversions; create hierarchical product categories. | `uom.create`, `products.create` | UOM name/symbol, Category code | `[ ] PENDING` |
| Catalog | Product Master Setup | Create inventory product with SKU, barcode, category, primary UOM, sales price, purchase price, and default warehouse. | `products.create` | SKU, product name, cost/price, UOM | `[ ] PENDING` |
| Inventory | Warehouses & Storage Locations | Define main warehouse and sub-locations (Aisles, Shelves, Bins). Assign default branch operational reference. | `inventory.create` | Warehouse code, name, branch reference | `[ ] PENDING` |
| Inventory | Moving Weighted Average Stock Valuation | System calculates real-time unit cost upon goods receipt. Stock balance shows quantity on hand, average unit cost, and total inventory valuation. | `inventory.view` | Product SKU, warehouse ID | `[ ] PENDING` |
| Inventory | Inter-Warehouse Stock Transfer | Create transfer (`draft` -> `submitted` -> `approved` -> `issued` -> `received`). Decrements source stock, increments destination stock with valuation intact. | `inventory.transfer`, `inventory.approve`, `inventory.post`, `inventory.receive` | Source/destination warehouse, items, quantities | `[ ] PENDING` |
| Inventory | Physical Stock Count & Variance Analysis | Record physical inventory count session; system computes book vs physical count variance. | `inventory.count`, `inventory.approve` | Warehouse, counted item list | `[ ] PENDING` |
| Inventory | Stock Adjustment Posting | Post stock adjustment for count discrepancy (Surplus/Deficit). System adjusts stock quantity and posts Dr Inventory Adjustment Loss / Cr Inventory Asset (or reverse). | `inventory.adjust`, `inventory.post`, `view_financials` | Approved stock count variance | `[ ] PENDING` |

---

### 10. Sales Orders, Delivery Notes, Invoices, Returns & Credit Notes
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| Sales Operations | Sales Order Confirmation | Create sales order with customer, item lines, unit prices, VAT rate. Submit and confirm (`SO-YYYY-XXXXX`). Tracks unfulfilled quantities. | `sales.create`, `sales.submit`, `sales.approve` | Customer, item SKU, quantity, unit price | `[ ] PENDING` |
| Sales Operations | Delivery Note Stock Fulfillment | Generate delivery note referencing confirmed SO (`DN-YYYY-XXXXX`). Confirm delivery note; decrements inventory stock and posts Dr COGS / Cr Inventory Asset. | `sales.create`, `sales.approve` | Confirmed SO, warehouse with sufficient stock | `[ ] PENDING` |
| Sales Operations | Customer Invoice Posting | Generate customer invoice referencing delivery note (`INV-YYYY-XXXXX`). Post invoice; creates Dr AR Control / Cr Sales Revenue & Cr VAT Output. Updates AR subledger. | `sales.create`, `sales.submit`, `sales.approve`, `sales.post`, `view_financials` | Confirmed DN or SO | `[ ] PENDING` |
| Sales Operations | Sales Return Workflow | Record customer return against posted invoice. Confirm return; restocks inventory at original cost and posts Dr Sales Returns / Cr AR (or clearing). | `sales.returns`, `sales.returns.post`, `view_financials` | Posted customer invoice ID, returned quantities | `[ ] PENDING` |
| Sales Operations | Customer Credit Note Posting | Issue credit note for billing adjustment or return (`CN-YYYY-XXXXX`). Post credit note; reduces customer AR balance and VAT Output liability. | `sales.credit_notes`, `sales.post`, `view_financials` | Customer, reason, credit amount | `[ ] PENDING` |
| Sales Operations | Receivable Note Settlement | Settle open credit note against outstanding customer invoice; eliminates reciprocal open amounts in AR subledger without cash movement. | `sales.credit_notes`, `sensitive.confirm` | Open invoice, open credit note | `[ ] PENDING` |

---

### 11. Purchase Orders, Goods Receipts, Bills, Returns, Adjustment Notes & Landed Costs
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| Purchasing | Purchase Order Lifecycle | Create purchase order (`PO-YYYY-XXXXX`), submit, and confirm with supplier agreed prices and expected delivery date. | `purchasing.create`, `purchasing.submit`, `purchasing.approve` | Supplier, items, purchase prices, tax code | `[ ] PENDING` |
| Purchasing | Goods Receipt Note (GRN) | Receive ordered goods into warehouse (`GRN-YYYY-XXXXX`). Increments stock balance, updates Moving Weighted Average cost, posts Dr Inventory Asset / Cr GRNI Clearing. | `purchasing.create`, `purchasing.approve` | Confirmed PO, receiving warehouse | `[ ] PENDING` |
| Purchasing | Landed Cost Allocation | Allocate freight, customs, and insurance expenses to a GRN by value/quantity. Revalues unit cost and posts Dr Inventory Asset / Cr Landed Cost Accruals. | `purchasing.landed_costs`, `purchasing.approve`, `purchasing.post`, `view_financials` | Posted GRN, freight bill amounts, allocation basis | `[ ] PENDING` |
| Purchasing | Supplier Bill Posting | Record supplier tax invoice (`BILL-YYYY-XXXXX`) matching GRN. Post bill; clears GRNI Clearing, Debits VAT Input, Credits AP Control. Updates AP subledger. | `purchasing.create`, `purchasing.approve`, `purchasing.post`, `view_financials` | Posted GRN, supplier invoice number | `[ ] PENDING` |
| Purchasing | Purchase Return Note | Return defective goods to supplier. Decrements stock and posts Dr GRNI Clearing / Cr Inventory Asset. | `purchasing.returns`, `purchasing.post`, `view_financials` | Posted GRN / Bill, return quantities | `[ ] PENDING` |
| Purchasing | Supplier Adjustment (Debit) Note | Issue supplier adjustment note for discount or returned items. Reduces AP Control balance and reconciles with supplier statement. | `purchasing.adjustment_notes`, `purchasing.post`, `view_financials` | Supplier, bill reference, adjustment amount | `[ ] PENDING` |
| Purchasing | Payable Note Settlement | Settle open supplier adjustment note against outstanding supplier bill. | `purchasing.adjustment_notes`, `sensitive.confirm` | Open bill, open adjustment note | `[ ] PENDING` |

---

### 12. VAT / Tax Codes, Rates, Periods, Filing & GL Reconciliation
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| Tax Configuration | Tax Code & Standard/Zero/Exempt Rates | Configure standard VAT (e.g. 14% or 15%), Zero-Rated (0%), and Exempt tax codes with linked Output/Input GL accounts. | `taxes.view`, `taxes.edit` | Tax code, rate percentage, GL account links | `[ ] PENDING` |
| Tax Operations | Tax Calculation on Sales & Purchases | Invoices and bills compute tax minor units with integer rounding; VAT Output and VAT Input accumulate to respective GL sub-accounts. | Standard sales/purchase workflow | Invoices/bills with taxable lines | `[ ] PENDING` |
| Tax Periods | Tax Period Creation & Draft Return | Create monthly/quarterly tax period (e.g. `2026-Q1`); generate draft tax return aggregating Box 1-8 taxable sales, Box 9-14 taxable purchases, and net payable. | `taxes.periods`, `taxes.edit` | Fiscal tax period dates | `[ ] PENDING` |
| Tax Filing | Tax Return Filing & GL Locking | File tax return with `taxes.file` sensitive capability. Creates tax settlement journal (clearing VAT Input and VAT Output into VAT Payable); locks period. | `taxes.file`, `sensitive.confirm` | Draft tax return | `[ ] PENDING` |
| Tax Reports | VAT Register & GL Reconciliation Report | Run VAT Register and VAT to GL Reconciliation report. Verifies subledger tax breakdown exactly equals General Ledger tax control account balances. | `reports.view`, `view_financials` | Filed tax period | `[ ] PENDING` |

---

### 13. Fixed Assets, Capitalization, Depreciation & Disposals
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| Asset Setup | Asset Categories & Depreciation Methods | Define asset categories (Vehicles, Machinery, IT Hardware) with useful life (months), straight-line method, asset cost GL, and accum dep GL. | `fixedAssets.view`, `fixedAssets.create` | Category name, useful life in months, GL codes | `[ ] PENDING` |
| Asset Register | Asset Creation & Tagging | Register new asset with serial number, purchase cost, salvage value, in-service date, branch, and physical location. | `fixedAssets.create` | Asset specifications, purchase date, cost | `[ ] PENDING` |
| Capitalization | Asset Capitalization Posting | Capitalize asset (Manual Mode: Dr Asset Cost / Cr Asset Clearing; Opening Mode: zero GL impact). Idempotent; generates asset tag. | `fixedAssets.post`, `view_financials`, `sensitive.confirm` | Draft fixed asset record | `[ ] PENDING` |
| Depreciation | Monthly Depreciation Schedule Generation | Generate deterministic straight-line monthly depreciation schedule based on cost, salvage value, and useful life with integer minor-unit balancing. | `fixedAssets.edit`, `view_financials` | Capitalized active asset | `[ ] PENDING` |
| Depreciation | Monthly Depreciation Run Posting | Execute monthly depreciation run for open fiscal period. Posts Dr Depreciation Expense / Cr Accumulated Depreciation across all active assets. | `fixedAssets.post`, `view_financials`, `sensitive.confirm` | Open fiscal period, active asset schedules | `[ ] PENDING` |
| Depreciation | Depreciation Run Reversal | Reverse a posted depreciation run if needed; resets schedule line status to unposted while preserving audit history. | `fixedAssets.reverse`, `view_financials`, `sensitive.confirm` | Posted depreciation run ID | `[ ] PENDING` |
| Disposals | Asset Disposal (Sale / Scrap / Retirement) | Record disposal. Computes Net Book Value, proceeds, and gain/loss. Posts Dr Accum Dep, Dr Cash/AR (if sale), Dr/Cr Disposal Loss/Gain, Cr Asset Cost. | `fixedAssets.post`, `view_financials`, `sensitive.confirm` | Asset ID, disposal type, proceeds | `[ ] PENDING` |
| Asset Reports | Fixed Asset Register & NBV Reports | Generate Fixed Asset Register, Net Book Value Report, and Depreciation Run Summary; reconcile with GL Balance Sheet. | `reports.view`, `fixedAssets.export`, `view_financials` | Date range / asset category | `[ ] PENDING` |

---

### 14. Expenses, Prepaid Amortization & Accrual Recognition
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| Expenses | Direct Operating Expense Recording | Record direct expense (Utilities, Travel, Office Supplies), link to branch/project/cost-center. Post Dr Expense / Cr Bank/Cash. | `expenses.create`, `expenses.submit`, `expenses.approve`, `expenses.post` | Expense category, payment account, amount | `[ ] PENDING` |
| Prepaids | Prepaid Expense Amortization Schedule | Record upfront prepaid payment (e.g. Annual Rent / Insurance). System creates monthly recognition schedule. | `expenses.create`, `expenses.approve` | Total prepaid amount, start date, duration (months) | `[ ] PENDING` |
| Prepaids | Monthly Prepaid Recognition Posting | Post monthly recognition entry (Dr Expense / Cr Prepaid Asset) for the open period. | `expenses.post`, `view_financials` | Open schedule recognition line | `[ ] PENDING` |
| Accruals | Accrued Expense Recognition & Reversal | Record accrued expense for unbilled services in current period (Dr Expense / Cr Accrued Liability). Post reversal in following period upon bill receipt. | `expenses.create`, `expenses.approve`, `expenses.post` | Estimated amount, expense GL, accrual GL | `[ ] PENDING` |

---

### 15. Payroll Employees, Components, Runs & GL Posting
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| HR / Payroll | Employee Profile & Salary Structure | Define employee profile with national ID, hire date, basic salary, standard allowances (housing, transport), and deductions (social insurance, tax). | `payroll.create`, `view_payroll` | Employee personal and contract data | `[ ] PENDING` |
| HR / Payroll | Monthly Payroll Run Generation | Generate payroll run for period. Computes gross pay, total deductions, and net salary payable per employee based on assigned components. | `payroll.create`, `payroll.edit`, `view_payroll` | Pay period month/year | `[ ] PENDING` |
| HR / Payroll | Payroll Run Approval & GL Posting | Submit, approve, and post payroll run. Posts Dr Salaries & Wages Expense, Dr Employer Social Insurance / Cr Net Salaries Payable, Cr Deductions Payable. | `payroll.submit`, `payroll.approve`, `payroll.post`, `view_payroll`, `view_financials` | Approved payroll run | `[ ] PENDING` |
| HR / Payroll | Payroll Disbursal | Record bank transfer payout to employees (Dr Net Salaries Payable / Cr Bank Account). | `banks.create`, `banks.post`, `view_financials` | Posted payroll run, company bank account | `[ ] PENDING` |

---

### 16. Rentals: Items, Contracts, Handovers, Invoices & Returns
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| Rentals | Rentable Item Definition & Status | Define heavy equipment / rental assets with serial numbers, daily/monthly rates, and rental availability status (`available`, `rented`, `maintenance`). | `rentals.create` | Rentable item details, rental rates | `[ ] PENDING` |
| Rentals | Rental Contract Lifecycle | Create contract with customer, items, rental duration, deposit terms. Submit and approve contract (`draft` -> `submitted` -> `approved` -> `active`). | `rentals.create`, `rentals.submit`, `rentals.approve` | Customer, items, rental period | `[ ] PENDING` |
| Rentals | Equipment Handover Note | Record delivery of equipment to customer job site (`draft` -> `confirmed`). Updates item status to `rented`. | `rentals.deliver` | Active contract, dispatch details | `[ ] PENDING` |
| Rentals | Periodic Rental Invoicing | Generate billing invoice for rental duration. Post invoice (Dr AR Control / Cr Rental Revenue & Cr VAT Output). | `rentals.invoice`, `rentals.post`, `view_financials` | Active rental contract billing period | `[ ] PENDING` |
| Rentals | Equipment Return & Inspection | Record return of equipment; inspect condition (record damages/penalties if applicable); complete return note. Item status reverts to `available`. | `rentals.return`, `rentals.inspect` | Contract ID, returned equipment | `[ ] PENDING` |
| Rentals | Rental Operations Report | Generate rental utilization, contract status, and revenue report; reconcile with rental revenue GL accounts. | `reports.view`, `view_financials` | Date range / customer filter | `[ ] PENDING` |

---

### 17. Projects, Cost Centers, Budgets & Variance Analysis
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| Master Data | Project & Cost Center Creation | Create commercial projects with client, budget, start/end dates. Create operational cost centers (e.g., Marketing, IT, Logistics). | `projects.create`, `costCenters.create` | Project code/name, Cost Center code/name | `[ ] PENDING` |
| Multi-Dimensional Posting | Transaction Tagging with Project & Cost Center | Post journals, expenses, bills, or invoices tagged with Project and/or Cost Center. Ledger entries record dimensions without altering double-entry balance. | Standard posting permissions | Transaction payload with `project_id`/`cost_center_id` | `[ ] PENDING` |
| Budgeting | Budget Formulation & Approval | Create annual/quarterly budget broken down by GL account, cost center, and monthly period. Submit, approve, and activate budget. | `budgeting.create`, `budgeting.approve`, `view_financials` | Fiscal year, account line budget amounts | `[ ] PENDING` |
| Reporting | Budget vs Actual Variance Report | Generate Budget Variance Report comparing budgeted amounts vs actual posted GL ledger amounts; computes absolute and percentage variances. | `budgeting.view`, `reports.view`, `view_financials`, `budgeting.export` | Fiscal year, period, cost center | `[ ] PENDING` |
| Reporting | Project Profitability & Cost Center Reports | Generate Project Profitability Report (Revenues - Direct Costs - Allocated Expenses = Gross Margin) and Cost Center Actuals Report. | `reports.view`, `view_financials`, `reports.export` | Project ID / Cost Center ID | `[ ] PENDING` |

---

### 18. Attachments, Notifications & Audit Log Integrity
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| Attachments | Private Document Attachment & Upload | Upload supporting PDF/image (e.g. signed contract, scanned invoice) to allowed entity. File stored securely in private storage with sanitized name. | Entity edit permission | Valid PDF/PNG file (< 10MB) | `[ ] PENDING` |
| Attachments | Private Download & Directory Traversal Protection | Download attachment via authorized controller; system sends file with `nosniff` headers. Path traversal attempts (`../../`) strictly rejected. | Entity view permission | Uploaded attachment ID | `[ ] PENDING` |
| Notifications | User Alert Feed & Mark-as-Read | Actions trigger targeted notifications (e.g. pending approval). User notification panel updates unread count; mark read updates state. | Authenticated session | Triggered workflow event | `[ ] PENDING` |
| Audit Trail | Spatie Activitylog Append-Only Trail | View `/audit-log`. System records actor, event type, model, timestamp, and before/after attributes for every sensitive modification. Deletion disabled. | `audit.view` | System activity history | `[ ] PENDING` |

---

### 19. Branch & Warehouse Operational Workflows (Non-Tenancy)
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| Branch Operations | Operational Branch Assignment | Transactions (sales, purchases, inventory transfers) can be tagged with operational branch references for localized reporting. | Standard module permissions | Branch records (`MAIN`, `ALEX`) | `[ ] PENDING` |
| Branch Operations | Branch Profitability & Operations Report | Generate Branch Profitability Report showing revenues and direct expenses by branch without data segregation or database tenant separation. | `reports.view`, `view_financials` | Date range, branch selector | `[ ] PENDING` |
| Non-Tenancy Check | Single Database & Shared Master Data | Verify that all users operate within single shared master data catalogs (COA, customers, suppliers, products) under global enterprise governance. | `SUPER_ADMIN` | Entire system database | `[ ] PENDING` |

---

### 20. Phase 17 Security Controls Verification
| Area | Scenario | Expected Result | Required Permission / Role | Test Data Needed | Owner Sign-Off Status |
|---|---|---|---|---|---|
| Security Controls | Configurable Password Policy Enforcement | Enforce minimum length, uppercase, lowercase, numbers, and symbols during user creation and update without external network dependencies. | `users.configure` | Weak password payload (rejected) | `[ ] PENDING` |
| Security Controls | Sensitive Financial Action Confirmation | Executing high-impact actions (posting journals, closing periods, finalizing reconciliations, asset disposals) requires explicit modal confirmation and reason. | Posting capability + `sensitive.confirm` | High-impact action request | `[ ] PENDING` |
| Security Controls | Route Authorization Gate Integrity | `php artisan security:route-audit --strict` scans all 457 routes; verifies zero unprotected or unauthorized routes. | Command line CLI | Application route table | `[ ] PENDING` |

---

## القسم العربي: مصفوفة القبول النهائي واختبارات الدخان للمحاسبين

توفر هذه المصفوفة خطة تحقق عملية وشاملة لأصحاب الأعمال، والمدراء الماليين، والمحاسبين القانونيين للتأكد من جاهزية النظام، واكتمال الدورات المستندية، وصحة المعالجات المحاسبية، والضوابط الرقابية قبل بدء التشغيل الفعلي.

### دليل حالات الاعتماد
- `[ ] قيد الانتظار`: الاختبار لم يتم تنفيذه بعد.
- `[x] تم الاجتياز`: تم تنفيذ الاختبار واعتماده بنجاح من قبل المحاسب/المالك.
- `[!] غير مطابق`: توجد ملاحظة أو عدم تطابق قيد المراجعة.
- `[N/A] لا ينطبق`: تم التجاوز بموجب استثناء تشغيلي معتمد.

---

### 1. المصادقة والجلسات وإدارة الصلاحيات (RBAC)
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| الدخول والجلسات | تسجيل دخول المستخدم ببيانات صحيحة | يتم تسجيل الدخول بنجاح، تجديد معرف الجلسة لمنع الاختراق، التوجيه إلى لوحة التحكم `/dashboard`، وتحميل لغة واجهة المستخدم المفضلة. | أي مستخدم نشط | اسم المستخدم وكلمة المرور الصحيحة | `[ ] قيد الانتظار` |
| الدخول والجلسات | محاولة تسجيل الدخول ببيانات خاطئة | يتم تقييد المحاولات المتكررة (Rate Limiting)، وعرض رسالة خطأ واضحة، مع رفض إنشاء الجلسة. | غير مسجل | بريد إلكتروني أو كلمة مرور غير صحيحة | `[ ] قيد الانتظار` |
| الدخول والجلسات | محاولة دخول مستخدم معطل (Inactive) | يتم رفض تسجيل الدخول مع تنبيه بأن الحساب غير مفعل. | مستخدم معطل | مستخدم بحالة `is_active = false` | `[ ] قيد الانتظار` |
| الدخول والجلسات | تسجيل الخروج الآمن | يتم إنهاء الجلسة، إبطال بيانات المصادقة، وتحديث رمز الحماية CSRF والتوجيه إلى `/login`. | مستخدم مسجل | جلسة نشطة | `[ ] قيد الانتظار` |
| إدارة الصلاحيات | تقييد الصلاحيات حسب الدور الوظيفي | المستخدم المعين له دور `SALES` يصل لشاشات المبيعات، ويُمنع تماماً برمز `403 Forbidden` من الوصول لشاشات القيود اليومية أو الرواتب. | دور `SALES` مقابل دور `ACCOUNTANT` | حساب مستخدم بدور مبيعات | `[ ] قيد الانتظار` |
| إدارة الصلاحيات | حماية حساب المسؤول الرئيسي | بذور المدير العام (Bootstrap Seeder) معطلة تلقائياً في بيئة الإنتاج وتتطلب عبارة تأكيد صريحة ومسجلة في سجل النشاطات. | `SUPER_ADMIN` | متغير بيئة الإنتاج | `[ ] قيد الانتظار` |

---

### 2. لوحة التحكم والتنقل والفحص التشخيصي
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| لوحة التحكم | عرض المؤشرات المالية والتشغيلية الرئيسية | تعرض الشاشة إحصائيات دقيقة ومحدثة (عدد الحسابات، القيود المرحلة، حركات الأستاذ العام، الإشعارات غير المقروءة) بتنسيق أرقام سليم وبدون أخطاء. | `dashboard.view` | بيانات الشركة الافتراضية أو الحقيقية | `[ ] قيد الانتظار` |
| التنقل واللغة | شريط التنقل والتبديل اللغوي (عربي / إنجليزي) | تظهر جميع القوائم مترجمة بالكامل. التبديل بين اللغتين يغير الواجهة فوراً مع دعم كامل للاتجاه من اليمين لليسار (RTL). | مستخدم مسجل | ملف المستخدم | `[ ] قيد الانتظار` |
| الفحص التشخيصي | فحص سلامة النظام وقاعدة البيانات | الرابط `/health` يعيد رمز `200 OK` واستجابة اتصال قاعدة البيانات. الرابط `/foundation` يعرض شاشة الفحص التشخيصي للمسؤول. | `settings.configure` أو `audit.view` | تشغيل خادم النظام | `[ ] قيد الانتظار` |

---

### 3. إعدادات المنشأة، الفروع التشغيلية، والترقيم التلقائي
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| إعدادات المنشأة | تعديل بيانات المنشأة والرقم الضريبي | حفظ الاسم التجاري، الرقم الضريبي (TRN)، العنوان، العملة الأساسية، ومعلومات الاتصال مع تسجيل التعديل في سجل التدقيق. | `settings.company` | البيانات القانونية للمنشأة، الرقم الضريبي | `[ ] قيد الانتظار` |
| الفروع التشغيلية | تعريف الفروع ومراكز العمل | إضافة وتعديل الفروع التشغيلية (الفرع الرئيسي، فرع الإسكندرية، المستودع المركزي) كأبعاد تشغيلية وتقارير (وليس كعزل بيانات متعدد المستأجرين). | `settings.branches` | كود الفرع، الاسم باللغتين | `[ ] قيد الانتظار` |
| الترقيم التلقائي | ضبط تسلسل ترقيم المستندات | إعداد تسلسلات الفواتير (`INV-YYYY-XXXXX`)، قيود اليومية (`JRN-YYYY-XXXXX`)، أذون الاستلام، وأوامر الشراء مع معاينة الترقيم التلقائي. | `settings.numbering` | البادئة، سياسة التصفير السنوي، عدد الخانات | `[ ] قيد الانتظار` |
| إدارة المستخدمين | إنشاء المستخدمين وتعيين الأدوار | إضافة مستخدم جديد بكلمة مرور تطابق شروط الأمان المعقدة، وربطه بقالب صلاحيات محدد. | `users.configure` | بيانات المستخدم الجديد والدور المحدد | `[ ] قيد الانتظار` |

---

### 4. دليل الحسابات، تصنيفات وأنواع الحسابات، العملات وأسعار الصرف
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| دليل الحسابات | إعداد التصنيفات الرئيسية وأنواع الحسابات | تعريف وتفعيل التصنيفات الخمسة (أصول، خصوم، حقوق ملكية، إيرادات، مصروفات) والأنواع الفرعية (أصول متداولة، عملاء، بنوك...). | `accounting.account_types` | كود التصنيف ونوع الحساب | `[ ] قيد الانتظار` |
| شجرة الحسابات | بناء شجرة الحسابات والتحقق من التفرع | إنشاء حسابات فرعية تحت مجموعات رئيسية؛ منع الترحيل على الحسابات التجميعية، وضمان عدم تكرار كود الحساب. | `accounting.create` | كود الحساب، الاسم، طبيعة الحساب (مدين/دائن) | `[ ] قيد الانتظار` |
| العملات وأسعار الصرف | إدارة العملات الأجنبية وأسعار الصرف اليومية | تعريف العملات (USD, EUR, SAR) مع تحديد المنازل العشرية، وإدخال أسعار الصرف اليومية مقابل العملة المحلية (EGP). | `manage_currencies`, `manage_fx_rates` | رمز العملة، سعر الصرف التاريخي | `[ ] قيد الانتظار` |
| الربط المحاسبي | ربط الحسابات الافتراضية للنظام | ربط حسابات التحكم الآلي (مراقبة العملاء، مراقبة الموردين، بضاعة في الطريق GRNI، الأرباح المبقاة، فروق العملة، وضريبة المبيعات/المشتريات). | `accounting.mappings` | حسابات دليل الحسابات المفعلة | `[ ] قيد الانتظار` |

---

### 5. السنوات والفترات المالية، الأرصدة الافتتاحية، ودورة قيود اليومية
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| الفترات المالية | إنشاء السنة المالية والفترات الشهرية | إنشاء سنة مالية (مثل 2026) مقسمة إلى 12 فترة شهرية تبدأ بحالة `مفتوحة`. | `accounting.periods` | تاريخ بداية ونهاية السنة المالية | `[ ] قيد الانتظار` |
| الأرصدة الافتتاحية | إدخال وترحيل الأرصدة الافتتاحية | تسجيل الأرصدة الافتتاحية لجميع الحسابات؛ التحقق من تساوي إجمالي المدين مع إجمالي الدائن، وترحيلها لإنشاء رصيد البداية للأستاذ العام. | `accounting.opening_balances`, `view_financials` | ميزان المراجعة الافتتاحي | `[ ] قيد الانتظار` |
| قيود اليومية | إنشاء مسودة قيد يومية يدوي | إدخال أسطر القيد، الوصف، المبالغ المدينة والدائنة، وتحديد المشروع ومركز التكلفة اختياريًا؛ حفظ كمسودة. | `accounting.create` | تاريخ القيد، الحسابات، المبالغ المتزنة | `[ ] قيد الانتظار` |
| دورة القيد | اعتماد وترحيل قيد اليومية | اعتماد القيد من المدير المالي ثم ترحيله مع تأكيد الإجراء الحساس؛ توليد رقم تسلسلي غير قابل للتكرار (`JRN-YYYY-XXXXX`) وقفل التعديل. | `accounting.post`, `view_financials` | قيد معتمد | `[ ] قيد الانتظار` |
| عكس القيود | عكس قيد مرحل بالكامل | عكس القيد المرحل مع إدخال سبب العكس؛ توليد قيد عكسي تلقائي بمبالغ معكوسة مع ربطه برقم القيد الأصلي في سجل التدقيق. | `accounting.reverse`, `view_financials` | رقم القيد المرحل | `[ ] قيد الانتظار` |
| إقفال الفترات | فحص موانع إقفال الفترة الشهرية | تشغيل فحص الجاهزية؛ النظام يكتشف تلقائياً أي قيود أو فواتير غير مرحلة تمنع الإقفال ويعرض تقريراً تفصيلياً بها. | `accounting.periods` | رقم الفترة المالية | `[ ] قيد الانتظار` |
| إقفال الفترات | إقفال الفترة ومنع الترحيل | إقفال الفترة بصلاحية `close_period`؛ حظر ترحيل أي مستند أو حركة بتاريخ يقع داخل الفترة المغلقة بواسطة `PeriodGuard`. | `close_period` | فترة جاهزة للإقفال | `[ ] قيد الانتظار` |
| إعادة فتح الفترة | إعادة فتح فترة مغلقة بموافقة معتمدة | إعادة فتح الفترة بصلاحية `reopen_period` المحددة مع تأكيد الحساب وتسجيل السبب في سجل الرقابة والتدقيق. | `reopen_period` | معرف الفترة المغلقة | `[ ] قيد الانتظار` |

---

### 6. دفتر الأستاذ العام، ميزان المراجعة، والقوائم المالية الختامية
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| الأستاذ العام | كشف حساب الأستاذ العام وتفاصيل الحركات | استعراض كشف الحساب لأي حساب محدد خلال فترة؛ عرض الرصيد الافتتاحي، الحركات المدينة والدائنة بالتاريخ، والرصيد التراكمي المتحرك. | `accounting.view` | كود الحساب، الفترة الزمنية | `[ ] قيد الانتظار` |
| ميزان المراجعة | ميزان المراجعة بالمجاميع والأرصدة | توليد ميزان المراجعة للفترة؛ التحقق من المعادلة المحاسبية: الرصيد الافتتاحي + الحركات المدينة - الحركات الدائنة = رصيد الإغلاق، وتطابق المدين والدائن. | `accounting.view` | الفترة المالية المحددة | `[ ] قيد الانتظار` |
| الميزانية العمومية | تقرير الميزانية العمومية والتصدير | عرض الأصول والخصوم وحقوق الملكية؛ التحقق التام من توازن الميزانية: الأصول = الخصوم + حقوق الملكية. تصدير ملف CSV بنفس الأرقام. | `reports.view`, `view_financials` | تاريخ الميزانية (As-of Date) | `[ ] قيد الانتظار` |
| قائمة الدخل | تقرير الأرباح والخسائر (قائمة الدخل) | حساب الإيرادات، تكلفة البضاعة المباعة، إجمالي الربح، المصروفات التشغيلية، وصافي الربح/الخسارة للفترة بدقة متطابقة مع قيود الأستاذ العام. | `reports.view`, `view_financials` | تاريخ البداية والنهاية | `[ ] قيد الانتظار` |
| التدفقات النقدية | قائمة التدفقات النقدية التشغيلية والاستثمارية والتمويلية | تصنيف التدفقات النقدية تلقائياً حسب نوع النشاط من واقع الحركات المقفلة، مع استبعاد التحويلات الداخلية بين الخزن والبنوك. | `reports.view`, `view_financials` | فترة التقرير | `[ ] قيد الانتظار` |

---

### 7. العملاء والموردون والأرصدة الافتتاحية للمساعدين (AR / AP)
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| بيانات العملاء | إضافة عميل وتحديد سقف الائتمان | تسجيل بيانات العميل، الاسم التجاري، الرقم الضريبي، الحد الائتماني، وفترة السداد (مثلاً 30 يوماً). | `customers.create` | بيانات العميل والرقم الضريبي | `[ ] قيد الانتظار` |
| بيانات الموردين | إضافة مورد والبيانات البنكية | تسجيل بيانات المورد، الرقم الضريبي، الحساب البنكي، وشروط الدفع المعتمدة. | `suppliers.create` | بيانات المورد والحساب البنكي | `[ ] قيد الانتظار` |
| مساعد المدينين | إدخال الأرصدة الافتتاحية للعملاء | تسجيل الفواتير المفتوحة للعملاء من النظام السابق وترحيلها لإنشاء سجلات الأستاذ المساعد للمدينين دون مضاعفة رصيد الأستاذ العام. | `customers.opening_balances` | قائمة فواتير العملاء المفتوحة | `[ ] قيد الانتظار` |
| مساعد الدائنين | إدخال الأرصدة الافتتاحية للموردين | تسجيل فواتير الموردين المفتوحة السابقة وترحيلها لإنشاء سجلات الأستاذ المساعد للدائنين. | `suppliers.opening_balances` | قائمة فواتير الموردين المفتوحة | `[ ] قيد الانتظار` |

---

### 8. سندات القبض والصرف، التخصيصات، الشيكات، والتسوية البنكية
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| الخزينة والبنوك | تعريف الخزن وحسابات البنوك | إضافة الخزن النقدية والحسابات البنكية مع ربطها بحسابات الأستاذ العام المقابلة وكتابة رقم الآيبان (IBAN). | `cash.create`, `banks.create` | اسم الحساب، البنك، كود الأستاذ العام | `[ ] قيد الانتظار` |
| التحويلات النقدية | التحويل بين الخزن والبنوك | تسجيل حركة تحويل نقدي من الخزينة للبنك أو بين البنوك؛ الترحيل يولد قيداً تلقائياً: من حـ/ البنك إلى حـ/ الخزينة. | `treasury-transfers.post` | الخزينة المحولة، البنك المستلم، المبلغ | `[ ] قيد الانتظار` |
| سندات القبض | قبض نقدية/بنك من عميل وتخصيص الدفعة | تسجيل سند قبض؛ ترحيل السند (من حـ/ النقدية أو البنك إلى حـ/ مراقبة العملاء)؛ وتخصيص المبلغ لسداد فاتورة أو أكثر وتحديث رصيدها المتبقي. | `customers.receipts`, `customers.allocations` | العميل، الفواتير المفتوحة، المبلغ | `[ ] قيد الانتظار` |
| سندات الصرف | صرف دفعة لمورد وتخصيصها | تسجيل سند صرف لمورد؛ الترحيل (من حـ/ مراقبة الموردين إلى حـ/ البنك أو الخزينة) وتخصيص المبلغ على فواتير المشتريات المستحقة. | `suppliers.payments`, `suppliers.allocations` | المورد، فواتير الشراء، المبلغ | `[ ] قيد الانتظار` |
| إلغاء التخصيص | عكس تخصيص سند قبض أو صرف | فك تخصيص دفعة من فاتورة؛ إعادة الرصيد المستحق على الفاتورة والسند كأرصدة غير مسددة دون التأثير على قيود الأستاذ العام. | `customers.allocations` / `suppliers.allocations` | معرف حركة التخصيص | `[ ] قيد الانتظار` |
| دورة الشيكات | الشيكات الواردة (تحصيل / ارتداد) | استلام شيك عميل (`مستلم` -> `مودع بالبنك` -> `محصل` أو `مرتد`). التحصيل يولد قيد: من حـ/ البنك إلى حـ/ شيكات تحت التحصيل. | `cheques.receive`, `cheques.clear` | رقم الشيك، البنك الساحب، تاريخ الاستحقاق | `[ ] قيد الانتظار` |
| دورة الشيكات | الشيكات الصادرة (إصدار / صرف) | إصدار شيك لمورد (`صادر` -> `مصروف من البنك` أو `ملغي`). الصرف يولد قيد: من حـ/ أوراق دفع إلى حـ/ البنك. | `cheques.issue`, `cheques.clear` | رقم الشيك، المستفيد، تاريخ الاستحقاق | `[ ] قيد الانتظار` |
| التسوية البنكية | استيراد ومطابقة كشف حساب البنك | فتح جلسة تسوية بنكية لحساب بنكي وفترة محددة؛ إدخال بنود كشف الحساب ومطابقتها مع حركات دفتر البنك وحساب الفروقات. | `banks.reconcile` | كشف حساب البنك، حركات دفتر البنك | `[ ] قيد الانتظار` |
| التسوية البنكية | اعتماد وقفل مذكرة التسوية البنكية | اعتماد التسوية عند وصول الفارق إلى 0.00 وقفل الحركات المطابقة لمنع التكرار في الفترات اللاحقة. | `banks.reconcile`, `sensitive.confirm` | جلسة تسوية متوازنة | `[ ] قيد الانتظار` |
| تقارير المساعدين | كشوف حسابات العملاء والموردين وأعمار الديون | استخراج كشف حساب تفصيلي للعميل/المورد، وتقارير أعمار الديون (30-60-90-120 يوماً)، ومطابقة إجمالي المساعد مع حساب المراقبة بالأستاذ العام. | `reports.view`, `view_financials` | العميل أو المورد، تاريخ التقرير | `[ ] قيد الانتظار` |

---

### 9. المنتجات، وحدات القياس، المستودعات، وعمليات المخزون
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| بطاقة الصنف | وحدات القياس وتصنيفات المنتجات | تعريف وحدات القياس (قطعة، كرتونة، كجم، متر) ومعاملات التحويل؛ وإنشاء شجرة تصنيفات المنتجات. | `uom.create`, `products.create` | رمز واسم الوحدة، كود التصنيف | `[ ] قيد الانتظار` |
| بطاقة الصنف | إضافة منتج مخزني جديد | تسجيل منتج بكود الصنف (SKU)، الباركود، وحدة القياس الأساسية، سعر البيع وسعر الشراء الافتراضي، والمستودع المفضل. | `products.create` | كود SKU، اسم الصنف، الأسعار، الوحدة | `[ ] قيد الانتظار` |
| المستودعات | تعريف المستودعات وأماكن التخزين | إضافة المستودعات الرئيسية والفرعية وأماكن التخزين الداخلية (رفوف / ممرات) وربطها بالفرع التشغيلي. | `inventory.create` | كود المستودع، الاسم، الفرع | `[ ] قيد الانتظار` |
| تقييم المخزون | التقييم بالمتوسط المرجح المتحرك (Moving Weighted Average) | النظام يعيد احتساب متوسط تكلفة الوحدة بدقة عند كل إذن استلام بضاعة؛ شاشة الأرصدة تظهر الكمية الفعلية ومتوسط التكلفة وإجمالي القيمة. | `inventory.view` | كود الصنف، المستودع | `[ ] قيد الانتظار` |
| التحويل المخزني | تحويل بضاعة بين المستودعات | إنشاء إذن تحويل (`مسودة` -> `معتمد` -> `صرف` -> `استلام`)؛ خصم الكمية من مستودع المصدر وإضافتها لمستودع الاستلام بنفس القيمة. | `inventory.transfer`, `inventory.receive` | مستودع الصرف، مستودع الاستلام، الكميات | `[ ] قيد الانتظار` |
| الجرد المخزني | جلسة جرد المخزون الفعلي ومقارنة الفروقات | فتح جلسة جرد لمستودع؛ إدخال الكميات الفعلية؛ والنظام يظهر تلقائياً فروقات الجرد (عجز / زيادة) مقارنة بالرصيد الدفتري. | `inventory.count` | المستودع، الأصناف المجرودة | `[ ] قيد الانتظار` |
| التسويات المخزنية | ترحيل تسوية فروقات الجرد | ترحيل تسوية الجرد؛ تعديل كميات الأصناف وتوليد القيد المحاسبي: من حـ/ خسائر تسوية الجرد إلى حـ/ مخزون البضاعة (أو العكس في الزيادة). | `inventory.adjust`, `inventory.post` | فروقات جرد معتمدة | `[ ] قيد الانتظار` |

---

### 10. دورة المبيعات: أوامر البيع، أذون التسليم، الفواتير، المرتجعات، والإشعارات الدائنة
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| أوامر البيع | تسجيل وتأكيد أمر بيع (Sales Order) | إنشاء أمر بيع مع العميل، الأصناف، الأسعار، ونسبة الضريبة؛ اعتماده وتوليد رقمه (`SO-YYYY-XXXXX`) وتتبع الكميات غير المسلمة. | `sales.create`, `sales.approve` | العميل، كود الصنف، الكمية، السعر | `[ ] قيد الانتظار` |
| أذون التسليم | صرف البضاعة بإذن تسليم (Delivery Note) | إنشاء إذن تسليم بناءً على أمر البيع (`DN-YYYY-XXXXX`)؛ اعتماده يخصم المخزون ويرحل قيد تكلفة المبيعات: من حـ/ تكلفة المبيعات COGS إلى حـ/ المخزون. | `sales.approve` | أمر بيع مؤكد، رصيد كافي بالمستودع | `[ ] قيد الانتظار` |
| فواتير المبيعات | إصدار وترحيل فاتورة المبيعات الضريبية | إنشاء فاتورة المبيعات مرتبطة بإذن التسليم (`INV-YYYY-XXXXX`)؛ ترحيلها يولد قيد: من حـ/ مراقبة العملاء إلى حـ/ إيرادات المبيعات وحـ/ ضريبة المبيعات. | `sales.post`, `view_financials` | إذن تسليم مؤكد | `[ ] قيد الانتظار` |
| مرتجعات المبيعات | تسجيل وترحيل إذن مرتجع مبيعات | تسجيل مرتجع مبيعات مقابل فاتورة مرحلة؛ اعتماده يعيد البضاعة للمخزون بالتكلفة الأصلية ويرحل قيد تخفيض المبيعات ورصيد العميل والضريبة. | `sales.returns.post`, `view_financials` | رقم فاتورة المبيعات الأصلية، الكمية المرتجعة | `[ ] قيد الانتظار` |
| الإشعارات الدائنة | إصدار وترحيل إشعار دائن (Credit Note) | إصدار إشعار دائن لخصم تجاري أو تسوية قيمة فاتورة (`CN-YYYY-XXXXX`)؛ ترحيله يخفض مديونية العميل والتزام ضريبة المخرجات. | `sales.credit_notes`, `sales.post` | العميل، سبب الإشعار، المبلغ المخصوم | `[ ] قيد الانتظار` |
| تسوية الإشعارات | تسوية الإشعار الدائن مع فاتورة مفتوحة | إجراء مقاصة بين إشعار دائن وفاتورة مبيعات لنفس العميل؛ تسوية الرصيدين في كشف حساب العميل بدون حركة نقدية. | `sales.credit_notes`, `sensitive.confirm` | فاتورة مفتوحة، إشعار دائن مفتوح | `[ ] قيد الانتظار` |

---

### 11. دورة المشتريات: أوامر الشراء، أذون الاستلام، فواتير الموردين، وتكاليف الشحن
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| أوامر الشراء | تسجيل وتأكيد أمر شراء (Purchase Order) | تسجيل أمر شراء (`PO-YYYY-XXXXX`) مع المورد، الأصناف، أسعار الشراء، ونسبة الضريبة، واعتماده. | `purchasing.create`, `purchasing.approve` | المورد، الأصناف، أسعار الشراء | `[ ] قيد الانتظار` |
| أذون الاستلام | استلام البضاعة بالمستودع (Goods Receipt Note) | استلام البضاعة المشتراة (`GRN-YYYY-XXXXX`)؛ زيادة رصيد المخزون، تحديث متوسط التكلفة، وترحيل قيد: من حـ/ مخزون البضاعة إلى حـ/ وسيط استلام بضاعة (GRNI). | `purchasing.approve` | أمر شراء مؤكد، مستودع الاستلام | `[ ] قيد الانتظار` |
| تكاليف الشحن والجمارك | توزيع التكاليف الإضافية (Landed Costs) على الواردات | توزيع تكاليف الشحن والجمارك والتأمين على إذن الاستلام؛ إعادة تقييم تكلفة الوحدة المخزنية وترحيل قيد: من حـ/ المخزون إلى حـ/ مخصص تكاليف شحن. | `purchasing.landed_costs`, `purchasing.post` | إذن استلام مرحل، فواتير الشحن والتخليص | `[ ] قيد الانتظار` |
| فواتير الموردين | تسجيل وترحيل فاتورة المشتريات الضريبية | تسجيل فاتورة المورد (`BILL-YYYY-XXXXX`) بناءً على إذن الاستلام؛ ترحيلها يقفل حـ/ وسيط الاستلام (GRNI)، ويثبت ضريبة المدخلات، ويثبت مديونية حـ/ الموردين. | `purchasing.post`, `view_financials` | إذن استلام مرحل، رقم فاتورة المورد | `[ ] قيد الانتظار` |
| مرتجعات المشتريات | تسجيل إذن مرتجع مشتريات لمورد | إرجاع بضاعة تالفة للمورد؛ خصم المخزون وترحيل قيد: من حـ/ وسيط الاستلام إلى حـ/ المخزون. | `purchasing.returns.post`, `view_financials` | إذن الاستلام أو الفاتورة، الكميات المرتجعة | `[ ] قيد الانتظار` |
| إشعارات التسوية | إصدار وترحيل إشعار مدين للمورد (Debit Note) | تسجيل إشعار مدين على المورد لخصم كمية أو تسوية أسعار؛ تخفيض رصيد المورد بالأستاذ المساعد. | `purchasing.adjustment_notes`, `purchasing.post` | المورد، الفاتورة المرتبطة، المبلغ | `[ ] قيد الانتظار` |
| تسوية الإشعارات | مقاصة الإشعار المدين مع فاتورة مشتريات | تسوية الإشعار المدين مع فاتورة مشتريات مفتوحة لنفس المورد. | `purchasing.adjustment_notes`, `sensitive.confirm` | فاتورة شراء مفتوحة، إشعار مدين مفتوح | `[ ] قيد الانتظار` |

---

### 12. ضريبة القيمة المضافة، الإقرارات الضريبية، ومطابقة الأستاذ العام
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| إعدادات الضريبة | تعريف أكواد ونسب الضريبة (أساسية، صفرية، معفاة) | إعداد نسب الضريبة (14% أو 15%) ونسب الصفر والمعفى وربطها بحسابات ضريبة المخرجات وضريبة المدخلات بدليل الحسابات. | `taxes.view`, `taxes.edit` | كود الضريبة، النسبة المئوية، حسابات GL | `[ ] قيد الانتظار` |
| احتساب الضريبة | احتساب الضريبة في الفواتير وحسابات المراقبة | احتساب الضريبة بدقة في الفواتير؛ تجميع الضريبة في حـ/ ضريبة المبيعات المحصلة وحـ/ ضريبة المشتريات القابلة للخصم. | دورات المبيعات والمشتريات | فواتير مبيعات وفواتير شراء خاضعة | `[ ] قيد الانتظار` |
| الإقرارات الضريبية | توليد مسودة الإقرار الضريبي للفترة | إنشاء الفترة الضريبية (مثل الربع الأول 2026)؛ توليد مسودة الإقرار بتجميع المبيعات والمشتريات الخاضعة والضريبة المستحقة والصافية. | `taxes.periods`, `taxes.edit` | تواريخ الفترة الضريبية | `[ ] قيد الانتظار` |
| تقديم الإقرار | اعتماد وتقديم الإقرار الضريبي وقفل الفترة | تقديم الإقرار بصلاحية `taxes.file` وتأكيد الإجراء الحساس؛ ترحيل قيد تسوية الضريبة (إقفال المدخلات والمخرجات في حـ/ جاري مصلحة الضرائب) وقفل الفترة. | `taxes.file`, `sensitive.confirm` | مسودة إقرار ضريبي مكتملة | `[ ] قيد الانتظار` |
| مطابقة الضريبة | تقرير سجل الضريبة ومطابقة الإقرار مع الأستاذ العام | استخراج سجل فواتير الضريبة التفصيلي ومطابقة مبالغ الإقرار مع أرصدة حسابات الضريبة بالأستاذ العام لضمان عدم وجود فوارق. | `reports.view`, `view_financials` | الفترة الضريبية المقدمة | `[ ] قيد الانتظار` |

---

### 13. الأصول الثابتة، الرأسمالية، جداول وإهلاك الأصول، واستبعاد الأصول
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| بطاقة الأصل | تعريف فئات الأصول ونسب الإهلاك | إعداد فئات الأصول (سيارات، أجهزة حاسب، آلات) مع تحديد العمر الإنتاجي بالشهور، طريقة القسط الثابت، وحسابات التكلفة ومجمع الإهلاك ومصروف الإهلاك. | `fixedAssets.create` | اسم الفئة، العمر بالشهور، حسابات GL | `[ ] قيد الانتظار` |
| بطاقة الأصل | تسجيل أصل ثابت جديد وبياناته | إضافة أصل ثابت مع الرقم التسلسلي، تاريخ الشراء، تكلفة الاقتناء، القيمة التخريدية، الفرع، والموقع الفعلي للأصل. | `fixedAssets.create` | مواصفات الأصل، التكلفة، الموقع | `[ ] قيد الانتظار` |
| الرأسمالية | ترحيل رسملة الأصل الثابت (Capitalization) | رسملة الأصل (الوضع اليدوي: من حـ/ تكلفة الأصل إلى حـ/ وسيط شراء أصول؛ أو وضع رصيد البداية)؛ توليد كود التتبع وقفل تعديل التكلفة. | `fixedAssets.post`, `view_financials`, `sensitive.confirm` | أصل ثابت في حالة مسودة | `[ ] قيد الانتظار` |
| جدول الإهلاك | توليد جدول الإهلاك الشهري بطريقة القسط الثابت | احتساب جدول الإهلاك الشهري للأصل وتوزيع القسط الثابت بالتساوي مع معالجة الكسور وتثبيت الأقساط المرحلة. | `fixedAssets.edit`, `view_financials` | أصل مرسمل ونشط | `[ ] قيد الانتظار` |
| مسير الإهلاك | ترحيل مسير الإهلاك الشهري المجمع | تنفيذ وترحيل مسير الإهلاك الشهري لجميع الأصول النشطة عن فترة مفتوحة؛ ترحيل قيد مجمع: من حـ/ مصروف الإهلاك إلى حـ/ مجمع الإهلاك. | `fixedAssets.post`, `view_financials`, `sensitive.confirm` | فترة مالية مفتوحة، جداول أصول نشطة | `[ ] قيد الانتظار` |
| عكس مسير الإهلاك | إلغاء وعكس مسير إهلاك مرحل | عكس مسير الإهلاك عند الحاجة؛ إعادة أسطر جدول الإهلاك لحالة غير مرحل مع الاحتفاظ بسجل العمليات. | `fixedAssets.reverse`, `view_financials`, `sensitive.confirm` | معرف مسير الإهلاك المرحل | `[ ] قيد الانتظار` |
| استبعاد الأصل | استبعاد أصل ثابت (بيع / تخريد / إتلاف) | تسجيل استبعاد أصل؛ احتساب القيمة الدفترية المتبقية وقيمة البيع؛ ترحيل قيد: إقفال تكلفة الأصل، إقفال مجمع الإهلاك، وإثبات أرباح/خسائر الاستبعاد. | `fixedAssets.post`, `view_financials`, `sensitive.confirm` | الأصل، نوع الاستبعاد، قيمة البيع إن وجدت | `[ ] قيد الانتظار` |
| تقارير الأصول | سجل الأصول وصافي القيمة الدفترية ومطابقة الميزانية | استخراج سجل الأصول، تقرير صافي القيمة الدفترية، ومطابقة إجمالي تكلفة الأصول ومجمعات الإهلاك مع أرقام الميزانية العمومية. | `reports.view`, `fixedAssets.export`, `view_financials` | تاريخ التقرير / فئة الأصول | `[ ] قيد الانتظار` |

---

### 14. المصروفات، المصروفات المدفوعة مقدماً، والمصروفات المستحقة
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| المصروفات المباشرة | تسجيل وترحيل مصروف تشغيلي مباشر | إدخال مصروف (صيانة، ضيافة، انتقالات) مع ربطه بالفرع أو المشروع أو مركز التكلفة؛ الترحيل يولد قيد: من حـ/ المصروف إلى حـ/ الخزينة أو البنك. | `expenses.create`, `expenses.post` | فئة المصروف، حساب الدفع، المبلغ | `[ ] قيد الانتظار` |
| المصروفات المقدمة | إثبات مصروف مدفوع مقدماً وتوليد جدول الإطفاء | تسجيل دفعة مقدمة (مثل إيجار سنوي أو وثيقة تأمين)؛ إنشاء جدول إطفاء شهري تلقائي على شهور الاستفادة. | `expenses.create`, `expenses.approve` | إجمالي المبلغ، تاريخ البدء، عدد الشهور | `[ ] قيد الانتظار` |
| إطفاء المقدمات | ترحيل قسط الإطفاء الشهري للمصروف المقدم | ترحيل قيد الإطفاء الشهري للفترة المفتوحة: من حـ/ المصروف التشغيلي إلى حـ/ أصل مصروفات مدفوعة مقدماً. | `expenses.post`, `view_financials` | سطر إطفاء مستحق للفترة | `[ ] قيد الانتظار` |
| المصروفات المستحقة | إثبات المصروف المستحق وعكسه في الفترة التالية | إثبات مصروف مستحق لخدمات تمت ولم ترد فواتيرها: من حـ/ المصروف إلى حـ/ مخصص مصروفات مستحقة؛ وعكسه في الفترة اللاحقة عند استلام الفاتورة. | `expenses.create`, `expenses.post` | المبلغ المقدر، حساب المصروف، حساب الاستحقاق | `[ ] قيد الانتظار` |

---

### 15. الرواتب، بنود الأجور، مسيرات الرواتب، والترحيل للحسابات
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| بيانات الموظفين | تسجيل ملف الموظف وهيكل الراتب | إضافة بيانات الموظف، الراتب الأساسي، البدلات الثابتة (سكن، انتقال)، والاستقطاعات (تأمينات اجتماعية، ضرائب كسب عمل). | `payroll.create`, `view_payroll` | البيانات التعاقدية للموظف وهيكل الراتب | `[ ] قيد الانتظار` |
| مسير الرواتب | إعداد مسير الرواتب الشهري واحتساب المستحقات | توليد مسير الرواتب للشهر؛ احتساب إجمالي الاستحقاقات، إجمالي الاستقطاعات، وصافي الراتب المستحق لكل موظف بدقة. | `payroll.create`, `payroll.edit`, `view_payroll` | شهر وسنة مسير الرواتب | `[ ] قيد الانتظار` |
| ترحيل الرواتب | اعتماد وترحيل مسير الرواتب للأستاذ العام | اعتماد وترحيل المسير؛ توليد قيد الرواتب: من حـ/ مصروفات الرواتب والأجور وحـ/ مساهمة التأمينات إلى حـ/ صافي الرواتب المستحقة وحـ/ وسيط التأمينات والضرائب. | `payroll.post`, `view_payroll`, `view_financials`, `sensitive.confirm` | مسير رواتب معتمد | `[ ] قيد الانتظار` |
| صرف الرواتب | إثبات صرف الرواتب من حساب البنك | تسجيل التحويل البنكي لرواتب الموظفين: من حـ/ صافي الرواتب المستحقة إلى حـ/ البنك. | `banks.post`, `view_financials` | مسير رواتب مرحل، كشف التحويل البنكي | `[ ] قيد الانتظار` |

---

### 16. الإيجارات: المعدات، العقود، محاضر التسليم والإرجاع، والفواتير
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| الأصول المؤجرة | تعريف المعدات والأصول القابلة للتأجير | إضافة المعدات برقم الشاسيه/المسلسل، أسعار الإيجار اليومية والشهرية، وحالة التوفر (`متاح`، `مؤجر`، `صيانة`). | `rentals.create` | بيانات المعدة وأسعار الإيجار | `[ ] قيد الانتظار` |
| عقود الإيجار | إنشاء واعتماد عقد إيجار معدات | تسجيل عقد إيجار مع العميل والمعدات والمدة المحددة ومبلغ التأمين؛ اعتماده وتحويله لحالة `نشط`. | `rentals.create`, `rentals.approve` | العميل، المعدات، مدة العقد | `[ ] قيد الانتظار` |
| محضر التسليم | محضر تسليم المعدة لموقع العميل | تسجيل محضر تسليم المعدة للعميل في موقع العمل وتأكيده؛ تحديث حالة المعدة إلى `مؤجر`. | `rentals.deliver` | عقد نشط، بيانات السائق والموقع | `[ ] قيد الانتظار` |
| فواتير الإيجار | إصدار وترحيل فاتورة الإيجار الدورية | إصدار فاتورة الإيجار عن فترة التشغيل وترحيلها: من حـ/ مراقبة العملاء إلى حـ/ إيرادات تأجير معدات وحـ/ ضريبة القيمة المضافة. | `rentals.invoice`, `rentals.post`, `view_financials` | عقد إيجار نشط، فترة الفوترة | `[ ] قيد الانتظار` |
| محضر الإرجاع | محضر إرجاع المعدة وفحص الحالة الفنية | تسجيل إرجاع المعدة، فحص سلامتها الفنية (وإثبات أي غرامات تلفيات إن وجدت)؛ استعادة حالة المعدة إلى `متاح`. | `rentals.return`, `rentals.inspect` | العقد، المعدة المرجعة، تقرير الفحص | `[ ] قيد الانتظار` |
| تقارير الإيجارات | تقرير تشغيل وإيرادات عقود الإيجار | استخراج تقرير معدلات تشغيل المعدات، حالة العقود، ومطابقة إيرادات التأجير مع حسابات الأستاذ العام. | `reports.view`, `view_financials` | الفترة الزمنية / فلتر العميل | `[ ] قيد الانتظار` |

---

### 17. المشاريع، مراكز التكلفة، الموازنات التقديرية، وانحراف الموازنة
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| مراكز المسؤولية | إنشاء المشاريع ومراكز التكلفة | تعريف المشاريع ببيانات العميل والموازنة التقديرية وتواريخ العمل؛ وتعريف مراكز التكلفة التشغيلية (تسويق، تقنية معلومات، لوجستيات). | `projects.create`, `costCenters.create` | كود واسم المشروع، كود واسم مركز التكلفة | `[ ] قيد الانتظار` |
| التوجيه المحاسبي | توجيه الحركات اليومية على المشاريع ومراكز التكلفة | تسجيل قيود اليومية أو المصروفات أو الفواتير مع تحديد المشروع ومركز التكلفة؛ تثبيت الأبعاد في أسطر الأستاذ العام دون التأثير على توازن القيد. | صلاحيات الترحيل القياسية | حركة مالية محدد بها المشروع ومركز التكلفة | `[ ] قيد الانتظار` |
| الموازنات التقديرية | إعداد واعتماد وتفعيل الموازنة التقديرية | إدخال الموازنة التقديرية السنوية/الشهرية موزعة على حسابات المصروفات ومراكز التكلفة؛ واعتمادها وتفعيلها. | `budgeting.create`, `budgeting.approve`, `view_financials` | السنة المالية، بنود موازنة الحسابات | `[ ] قيد الانتظار` |
| انحراف الموازنة | تقرير مقارنة الموازنة التقديرية بالمصاريف الفعلية | توليد تقرير انحراف الموازنة (Budget vs Actual) لمقارنة المبالغ المعتمدة بالحركات الفعلية المرحلة بالأستاذ العام وحساب نسبة الانحراف. | `budgeting.view`, `reports.view`, `view_financials` | السنة المالية، مركز التكلفة | `[ ] قيد الانتظار` |
| ربحية المشاريع | تقرير ربحية المشاريع وتحليل التكاليف | استخراج تقرير ربحية المشروع (الإيرادات المحققة - التكاليف المباشرة - المصروفات المحملة = مجمل ربح المشروع). | `reports.view`, `view_financials` | كود المشروع | `[ ] قيد الانتظار` |

---

### 18. المرفقات، الإشعارات، وسجل التدقيق والرقابة
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| إدارة المرفقات | رفع وتخزين المستندات المؤيدة بأمان | إرفاق مستند (عقد موقع، صورة فاتورة مورد، إيصال تحويل)؛ حفظ الملف في مسار تخزين خاص غير متاح للعامة مع تنقية اسم الملف ومنع الاختراق. | صلاحية تعديل المستند | ملف PDF أو صورة صالحة (< 10 ميجابايت) | `[ ] قيد الانتظار` |
| إدارة المرفقات | تحميل المرفقات والحماية من مسارات الاختراق | تحميل الملف عبر وحدة التحكم المعتمدة مع تطبيق ترويسة `nosniff` ومنع محاولات التلاعب بالمسارات (`../../`). | صلاحية عرض المستند | معرف المرفق المرفوع | `[ ] قيد الانتظار` |
| نظام الإشعارات | تنبيهات النظام وتحديث حالة القراءة | إرسال تنبيهات فورية للمستخدمين المعنيين عند طلب اعتماد أو ترحيل؛ تحديث عداد التنبيهات وإمكانية تمييزها كمقروءة. | مستخدم مسجل | حدث يتطلب تنبيهاً | `[ ] قيد الانتظار` |
| سجل التدقيق | الرقابة وسجل النشاطات غير القابل للحذف | استعراض شاشة `/audit-log`؛ تسجيل هوية المستخدم، نوع الإجراء، الوقت، والقيم قبل وبعد التعديل لكل حركة حساسة؛ ومنع تعديل أو حذف السجل. | `audit.view` | حركات مسجلة بالنظام | `[ ] قيد الانتظار` |

---

### 19. العمليات التشغيلية للفروع والمستودعات كأبعاد تشغيلية (بدون عزل مستأجرين)
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| العمليات بالفروع | تخصيص الفروع في المستندات التشغيلية | إمكانية تحديد الفرع في فواتير المبيعات والمشتريات والتحويلات كبُعد تشغيلي لإصدار تقارير الأداء المحلية. | صلاحيات الوحدات القياسية | فروع معرفة بالنظام | `[ ] قيد الانتظار` |
| تقارير الفروع | تقرير أرباح وخسائر الفروع التشغيلية | استخراج تقرير ربحية الفرع موضحاً إيرادات ومصروفات كل فرع على حدة دون عزل قاعدة البيانات أو تعدد المستأجرين. | `reports.view`, `view_financials` | الفترة الزمنية، كود الفرع | `[ ] قيد الانتظار` |
| سلامة المعمارية | التحقق من قاعدة البيانات الموحدة | التأكد من أن جميع المستخدمين يعملون على قاعدة بيانات تجارية موحدة مع مشاركة أدلة الحسابات والعملاء والموردين وفق حوكمة مركزية. | `SUPER_ADMIN` | هيكل قاعدة البيانات | `[ ] قيد الانتظار` |

---

### 20. التحقق من الضوابط الأمنية المطبقة (Phase 17 Security Controls)
| المجال | السيناريو | النتيجة المتوقعة | الصلاحية / الدور المطلوب | بيانات الاختبار المطلوبة | حالة الاعتماد |
|---|---|---|---|---|---|
| الضوابط الأمنية | فرض سياسة تعقيد كلمات المرور | التحقق من تطبيق شروط طول كلمة المرور (8 خانات على الأقل، أحرف كبيرة وصغيرة، أرقام، ورموز) عند إنشاء أو تعديل المستخدمين دون الاعتماد على خدمات خارجية. | `users.configure` | محاولة إدخال كلمة مرور ضعيفة (تُرفض) | `[ ] قيد الانتظار` |
| الضوابط الأمنية | نافذة تأكيد العمليات المالية الحساسة | ظهور نافذة تأكيد إجبارية تطلب إدخال سبب العملية عند تنفيذ الإجراءات المالية الكبرى (ترحيل القيود، إقفال الفترات، استبعاد الأصول، تقديم الإقرار). | صلاحية الترحيل + `sensitive.confirm` | طلب ترحيل حركة مالية حساسة | `[ ] قيد الانتظار` |
| الضوابط الأمنية | تدقيق حماية مسارات الروابط البرمجية | تشغيل أمر `php artisan security:route-audit --strict` للتأكد من أن جميع الـ 457 مساراً محمية بالكامل بصلاحيات معتمدة وخلو النظام من ثغرات. | سطر الأوامر (CLI) | جدول مسارات النظام | `[ ] قيد الانتظار` |

---

## Owner / Head Accountant Sign-Off Block
## محضر اعتماد واستلام النظام من المالك والمحاسب القانوني

| Role / الدور | Name / الاسم | Signature / التوقيع | Date / التاريخ | Overall Decision / القرار النهائي |
|---|---|---|---|---|
| Business Owner / المالك | ____________________ | ____________________ | ____ / ____ / 2026 | `[ ] ACCEPTED / معتمد` \| `[ ] REJECTED / مرفوض` |
| Chief Financial Officer / المدير المالي | ____________________ | ____________________ | ____ / ____ / 2026 | `[ ] ACCEPTED / معتمد` \| `[ ] REJECTED / مرفوض` |
| Lead Accountant / المحاسب الرئيسي | ____________________ | ____________________ | ____ / ____ / 2026 | `[ ] ACCEPTED / معتمد` \| `[ ] REJECTED / مرفوض` |
| System Auditor / المراجع الداخلي | ____________________ | ____________________ | ____ / ____ / 2026 | `[ ] ACCEPTED / معتمد` \| `[ ] REJECTED / مرفوض` |

---
