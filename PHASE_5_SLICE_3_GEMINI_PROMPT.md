# MINI ERP - PHASE 5 SLICE 3 CASH FLOW STATEMENT FOUNDATION

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


You are continuing the active Laravel + Inertia Mini ERP migration.

Execute only Phase 5 Slice 3.

Do not start Period Close hardening, Year-End Close, tax filing, payroll, fixed assets, deployment, or unrelated reporting work in this pass.

## Read First

Read and follow:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_5_FINANCIAL_STATEMENTS_PERIOD_CLOSE.md`
- `PHASE_5_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_5_SLICE_2_GEMINI_PROMPT.md`

Inspect:

- CashAccount and BankAccount models/services/pages
- ledger/report services
- existing Cash Book and Bank Book reports
- `financial_statement_line`, `account`, `ledger_entry`, `journal_entry`, and `journal_line` migrations/models

Before coding, write down in your internal notes and final report the exact columns you will use. Do not reference guessed columns such as `financial_period.name`, document aliases that do not exist, or timestamp fields as accounting dates.

## Objective

Build a safe Cash Flow Statement foundation using posted ledger movements and explicit cash-flow classifications.

This slice must not guess whether an account is operating, investing, or financing.

## Non-Negotiable Rules

Do not introduce:

- tenant/company/branch scope
- warehouse/location scope
- Spatie Teams
- hardcoded account codes as cash-flow logic
- hardcoded UI text in TSX
- floats
- frontend `minor / 100` or other floating-point money display
- posting logic
- period close logic
- report filtering by `created_at` / `updated_at`

Cash flow must read posted ledger entries only.
Cash flow must use `ledger_entry.entry_date` for movement ranges.

## Required Scope

Cash and cash equivalents:

- derive cash-equivalent GL accounts from active CashAccount and BankAccount records and their linked GL accounts
- do not hardcode cash/bank account codes
- if a cash/bank account has no valid GL link, show it as a configuration warning

Cash flow classifications:

- create a bounded classification foundation if not already present
- allowed activities: `operating`, `investing`, `financing`, `unclassified`
- classifications should be explicit and assigned to non-cash GL accounts and/or financial statement lines, not to company/branch/location
- preferred implementation: support line-level classification on `financial_statement_line` plus optional account-level override on `account`; if choosing a different model, justify why it is safer and keep it no-tenant/no-branch
- classification precedence must be deterministic: account override first, then mapped financial statement line, then `unclassified`
- cash-equivalent accounts themselves must not be assigned operating/investing/financing activity; their movements are classified from the non-cash side of the posted journal where possible
- unclassified movements must be shown separately and must not be silently rolled into operating cash flow

Cash-flow movement algorithm:

- Use base ledger amounts: `ledger_entry.debit_minor` and `ledger_entry.credit_minor`; do not use transaction-currency display fields for statement totals.
- Opening cash balance = sum of cash-equivalent ledger debits minus credits where `ledger_entry.entry_date < from_date`.
- Closing cash balance = sum of cash-equivalent ledger debits minus credits where `ledger_entry.entry_date <= to_date`.
- Period cash delta = closing cash balance minus opening cash balance.
- For each posted journal with cash-equivalent movement inside the selected date range:
  - cash net = cash-equivalent debits minus cash-equivalent credits for that journal.
  - if cash net is zero and the journal only transfers between cash-equivalent accounts, classify it as internal cash transfer and exclude it from operating/investing/financing totals.
  - inspect non-cash lines in the same journal and resolve their explicit cash-flow activity using the precedence above.
  - if all material non-cash lines resolve to one activity, classify the full cash net to that activity.
  - if material non-cash lines are mixed, missing, or contradictory, put the full cash net into `unclassified` and expose a warning with the journal number/id.
- Net cash change in the report must reconcile exactly: opening cash + operating net + investing net + financing net + unclassified net = closing cash.
- Inflows and outflows must be represented by signed integer minor units and/or separate integer inflow/outflow fields. Do not calculate with floats.

Report behavior:

- date range or selected FinancialPeriod/FiscalYear range
- when listing periods, use real `financial_period` columns (`fiscal_year_id`, `month`, `start_date`, `end_date`, `status`) and eager-load `fiscalYear`; do not use a non-existent period `name`
- calculate opening cash balance from posted ledger entries before range start
- calculate period cash inflows/outflows by activity
- calculate closing cash balance
- reconcile calculated closing cash to cash/bank ledger balance at range end
- show an explicit warning if unclassified cash movements exist

## RBAC

Viewing requires:

- `reports.view`
- `view_financials`

Export requires:

- `reports.export`
- `view_financials`

Classification setup requires:

- `accounting.mappings`

Do not use broad shortcuts instead of these exact permissions.

## UI Scope

Add or update Inertia pages only within the Reports/Accounting mapping patterns.

Frontend requirements:

- no hardcoded user-facing text in TSX
- all labels/actions/statuses/empty states in `en.json` and `ar.json`
- business-configured classification labels should come from backend payloads
- permission-aware links/actions via `useCan`
- RTL support
- show unclassified warnings prominently but calmly
- format all money from integer minor units without JS floating-point division
- export buttons must be hidden/disabled unless the user has both `reports.export` and `view_financials`
- classification setup actions must be hidden/disabled unless the user has `accounting.mappings`

## Audit

Use `AuditLogger` for classification changes.

Do not audit read-only report viewing unless the existing reporting pattern already does so.

## Tests

Add tests for:

- cash-equivalent account derivation from CashAccount/BankAccount links
- opening and closing cash balance calculations
- date filtering uses `ledger_entry.entry_date`, not `created_at`
- operating/investing/financing grouping from explicit classifications
- unclassified movements are visible
- mixed-classification cash journals become unclassified with warnings
- internal cash transfers are excluded from activity totals but still reconcile to closing cash
- no guessed classification
- permissions for view/export/setup
- UI/Inertia props include dictionary-ready labels and no English fallback payload dependency
- no company/branch/tenant columns or filters
- no ledger mutation

## Mandatory Source Scans Before Completion

Run and report the results:

```powershell
rg -n "created_at|updated_at" laravel/app/Application/Reports laravel/app/Http/Controllers/Reports laravel/tests/Feature
rg -n "\\/ 100|parseFloat|Number\\(|toFixed\\(|Math\\.round|\\(float\\)|round\\(" laravel/resources/js/Pages/Reports laravel/app/Application/Reports laravel/app/Http/Controllers/Reports
rg -n "Balance Sheet|Income Statement|Cash Flow|Operating|Investing|Financing|Unclassified|Export CSV" laravel/resources/js/Pages laravel/resources/js/Components
rg -n "company_id|branch_id|tenant_id|currentCompany|currentBranch|Spatie Teams" laravel/database/migrations laravel/app laravel/resources/js laravel/tests
```

Investigate every match. Translation keys/import names/test descriptions may be acceptable; hardcoded visible UI text, tenant assumptions, float money math, or timestamp date filtering must be fixed.

## Required Verification

Run:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --testsuite=Concurrency
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Report classification model chosen, formulas, permissions, warnings behavior, and tests.
