# SCREEN CATALOG

Every screen adheres to the **Universal UI Contract (MASTER_ERP_SPEC A2)**: all states (default/hover/focus/active/disabled/loading/empty/error/success/read-only/permission-denied/no-results), AR/EN, RTL/LTR, light/dark/system, responsive (desktop/tablet/mobile), drill-down, export/print where relevant. Below: route · type · primary user · required permission. Types: L=List, D=Detail, C=Create, E=Edit, W=Wizard, R=Report, Cfg=Config, Dash=Dashboard, Rec=Reconciliation, M=Modal/Drawer.

## Shell & global (7)
| Route | Type | User | Permission |
|---|---|---|---|
|`/login`, `/forgot`|—|all|public|
|app shell (sidebar+header+command palette+global search)|—|all authed|session|
|`/notifications`|L|all|self|
|`/quick-create` (drawer)|M|per doc perms|create perms|
|`/search` (global results)|L|all|scoped|
|`/profile` (locale/theme/prefs)|E|self|self|

## Dashboard (1)
`/dashboard` — Dash — Management/all — `dashboard.view` (KPIs role-scoped).

## Accounting (10)
`/accounting/coa` L·`account.view`; `…/coa/new` C, `…/coa/:id` E — `account.configure`; `/accounting/journal` L, `…/new` C, `…/:id` D/E (journal line editor) — `journal.*`; `/accounting/ledger` R; `/accounting/trial-balance` R; `/accounting/periods` Cfg (+ year-end close W) `period.configure`; `/accounting/opening-balances` C `opening.create`; `/accounting/fx-rates` Cfg.

## Financial Statements (4)
`/accounting/statements/income` R, `/balance-sheet` R, `/cash-flow` R, `/equity` R — `statements.view` (params: period, comparative, budget-vs-actual).

## Sales (14)
`/sales/quotations` L/C/D/E, `/orders` L/C/D/E, `/deliveries` L/C/D, `/invoices` L/C/D/E, `/returns` L/C/D, `/receipts` L/C/D + payment-allocation drawer M. Detail tabs: Overview/Items/Payments/Accounting/Attachments/Activity.

## Purchasing (14)
`/purchasing/requests`, `/orders`, `/goods-received`, `/invoices`, `/returns`, `/payments` — each L/C/D(/E) + allocation drawer. Same tab set.

## Inventory (13)
`/inventory/products` L/C/D(stock card)/E, `/categories` L/C/E, `/uom` Cfg, `/warehouses` L/C/E, `/movements` L, `/transfers` L/C/D, `/adjustments` L/C/D, `/counts` L/W(count session)/D, `/valuation` R, `/low-stock` R, `/aging` R.

## Tools & Equipment (6)
`/equipment` L/C/D(custody timeline)/E, `/equipment/categories` Cfg, `/equipment/maintenance` L/C/D.

## Rentals (8)
`/rentals` Dash(Active/Ending/Overdue/Returned tabs), `/rentals/contracts` L/W(create)/D/E, `/rentals/inspections` D, `/rentals/revenue` R, `/rentals/profitability` R.

## Customers / AR (6)
`/customers` L/C/D(profile w/ statement+aging)/E, `/customers/statements` R, `/customers/aging` R.

## Suppliers / AP (6)
`/suppliers` L/C/D/E, `/suppliers/statements` R, `/suppliers/aging` R.

## Cash (7)
`/cash/accounts` L/C/E, `/cash/receipts` L/C/D, `/cash/payments` L/C/D, `/cash/transfers` L/C, `/cash/petty-cash` L/C/D, `/cash/cash-book` R.

## Banks (7)
`/banks/accounts` L/C/E, `/banks/transactions` L/C/D, `/banks/transfers` L/C, `/banks/charges` L/C, `/banks/reconciliation` Rec/W (+ statement import), `/banks/bank-book` R.

## Cheques (4)
`/cheques/incoming` L/C/D(timeline), `/cheques/outgoing` L/C/D(timeline).

## Expenses (4)
`/expenses` L/C/D/E, `/expenses/categories` Cfg.

## Prepaid & Accrued (3)
`/expenses/prepaids` L/C/D(schedule), `/expenses/accruals` L/C/D.

## Fixed Assets (8)
`/assets/register` L/C/D(deprec schedule)/E, `/assets/categories` Cfg, `/assets/depreciation` L/W(run+preview), `/assets/disposals` L/C/D, `/assets/transfers` L/C, `/assets/revaluations` L/C.

## Payroll (7)
`/payroll/employees` L/C/D/E, `/payroll/runs` L/W(draft→pay)/D, `/payroll/loans` L/C, `/payroll/advances` L/C, `/payroll/payslips` R.

## Taxes (5)
`/taxes/config` Cfg, `/taxes/periods` L, `/taxes/returns` L/D(review), `/taxes/reports` R.

## Partners & Equity (9)
`/equity/partners` L/C/D(statement), `/equity/capital` L, `/equity/contributions` L/C, `/equity/withdrawals` L/C, `/equity/loans` L/C, `/equity/distributions` L/C, `/equity/retained-earnings` R.

## Projects & Cost Centers (7)
`/projects` L/C/D(profitability)/E, `/cost-centers` L/C/E, `/cost-centers/allocation` Cfg, `/projects/reports` R.

## Budgeting & Forecasting (7)
`/budgeting/budgets` L/C/D/E(versions), `/budgeting/variance` R, `/budgeting/forecasts` L/C/D (sales/expense/cash/profit).

## Recurring (3)
`/recurring` L/C/E, `/recurring/runs` L/D.

## Reports Center (1 hub + report routes)
`/reports` hub (grouped) → each report route counted within its module above; plus report-specific routes: General Journal, GL, Trial Balance, statements, sales/purchasing/inventory/AR-AP/cash-bank/assets/payroll/tax/project/budget reports (full list in REPORT_CATALOG, ~40 report screens).

## Users / RBAC / Approvals (5)
`/settings/users` L/C/E, `/settings/roles` L/C/E, `/settings/permissions` Cfg(grid), `/settings/approvals` Cfg(flows), `/settings/scopes` Cfg.

## Audit Trail (2)
`/audit` L(global, filters) + per-record Activity tab (embedded).

## Settings & Configuration (16)
`/settings` hub → Company, Branches, Financial Periods, Currencies & FX, Taxes, Numbering, Accounting mappings, Inventory policy, Warehouses, Payment Terms, Notifications, Localization, Appearance, Audit settings, Integrations. Each Cfg — `*.configure`.

---

## Screen count (target)
| Area | Screens |
|---|---|
|Shell & global|7|
|Dashboard|1|
|Accounting|10|
|Financial Statements|4|
|Sales|14|
|Purchasing|14|
|Inventory|13|
|Tools & Equipment|6|
|Rentals|8|
|Customers/AR|6|
|Suppliers/AP|6|
|Cash|7|
|Banks|7|
|Cheques|4|
|Expenses|4|
|Prepaid/Accrual|3|
|Fixed Assets|8|
|Payroll|7|
|Taxes|5|
|Partners/Equity|9|
|Projects/Cost Centers|7|
|Budgeting/Forecasting|7|
|Recurring|3|
|Reports (dedicated report screens)|~40|
|Users/RBAC/Approvals|5|
|Audit|2|
|Settings|16|
| **Total** | **≈ 233 screens** (excluding embedded modals/drawers/tabs, which add ~60+) |

No module is dashboard-only; every module has full List/Detail/Create/Edit plus its workflow, approval, posting, reconciliation, configuration, and report screens as applicable.
