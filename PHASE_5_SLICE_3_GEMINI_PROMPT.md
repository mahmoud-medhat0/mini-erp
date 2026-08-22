# MINI ERP - PHASE 5 SLICE 3 CASH FLOW STATEMENT FOUNDATION

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
- posting logic
- period close logic

Cash flow must read posted ledger entries only.

## Required Scope

Cash and cash equivalents:

- derive cash-equivalent GL accounts from active CashAccount and BankAccount records and their linked GL accounts
- do not hardcode cash/bank account codes
- if a cash/bank account has no valid GL link, show it as a configuration warning

Cash flow classifications:

- create a bounded classification foundation if not already present
- allowed activities: `operating`, `investing`, `financing`, `unclassified`
- classifications should be assigned to non-cash GL accounts or statement lines, not to company/branch/location
- unclassified movements must be shown separately and must not be silently rolled into operating cash flow

Report behavior:

- date range or selected FinancialPeriod/FiscalYear range
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

## Audit

Use `AuditLogger` for classification changes.

Do not audit read-only report viewing unless the existing reporting pattern already does so.

## Tests

Add tests for:

- cash-equivalent account derivation from CashAccount/BankAccount links
- opening and closing cash balance calculations
- operating/investing/financing grouping from explicit classifications
- unclassified movements are visible
- no guessed classification
- permissions for view/export/setup
- no company/branch/tenant columns or filters
- no ledger mutation

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
