# MINI ERP - PHASE 5 SLICE 1 FINANCIAL STATEMENT MAPPING FOUNDATION

You are continuing the active Laravel + Inertia Mini ERP migration.

Execute only Phase 5 Slice 1.

Do not start Balance Sheet, Income Statement, Cash Flow, Period Close hardening, Year-End Close, E2E hardening, tax filing, payroll, fixed assets, or deployment work in this pass.

## Read First

Read and follow:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_5_FINANCIAL_STATEMENTS_PERIOD_CLOSE.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`

Then inspect:

- `laravel/config/erp_rbac.php`
- `laravel/database/seeders/AccountingCoreSeeder.php`
- `laravel/database/seeders/AccountCategorySeeder.php`
- `laravel/database/seeders/AccountTypeSeeder.php`
- `laravel/app/Models/Account.php`
- `laravel/app/Models/AccountGroup.php`
- `laravel/app/Models/AccountType.php`
- `laravel/app/Models/AccountCategory.php`
- existing accounting/report controllers and pages

Use the current Laravel code as the source of truth.

## Objective

Create the configurable foundation that lets financial statements group ledger balances into statement lines without hardcoding account codes or business labels inside report pages.

This slice prepares data and configuration only. It must not calculate or render final financial statements yet.

## Non-Negotiable Rules

Do not introduce:

- tenant context
- `company_id`, `branch_id`, or `tenant_id`
- Spatie Teams
- `currentCompany` or `currentBranch`
- company/branch reporting scope
- year-end close postings
- retained earnings postings
- cash flow classifications beyond this slice's explicit mapping foundation

Preserve:

- single-ERP FiscalYear/FinancialPeriod context
- integer money
- existing account category/type relationships
- Spatie Activitylog through `AuditLogger`
- detailed RBAC

## Required Scope

Inspect existing account metadata first:

- `account_category.statement_type`
- `account_category.normal_balance`
- `account_category.is_contra`
- `account_type.statement_type`
- `account_type.normal_balance`
- `account_type.category`
- `account_group.statement_section`
- `account.account_type_id`
- `account.account_group_id`
- `account.type`
- `account.nature`

Implement a bounded statement mapping model only where the current metadata is insufficient for financial statement line ordering and labels.

Expected model/table if needed:

- `financial_statement_line`
  - `id` UUID primary key
  - `code` globally unique
  - `statement_type`: `balance_sheet` or `income_statement`
  - `section_code`
  - `name` JSONB multilingual
  - `normal_balance`: `debit` or `credit`
  - `sort_order` integer
  - `is_system` boolean
  - `is_active` boolean
  - timestamps

Expected account link if needed:

- Add nullable `financial_statement_line_id` to `account`, restricted on delete.

Do not add amount columns or cached balances.

Seed default system statement lines idempotently, using multilingual labels, for safe starter reporting:

- current assets
- non-current assets
- current liabilities
- non-current liabilities
- equity
- revenue
- sales returns / contra revenue
- cost of goods sold
- operating expenses
- other income
- other expenses

Use existing chart/account types to assign obvious system accounts where safe. Leave ambiguous accounts unmapped and surface them in the UI/reporting payload as unmapped. Do not guess.

## RBAC

Use exact permissions:

- view mapping page: `accounting.mappings`
- create/update mapping lines: `accounting.mappings`
- assign accounts to lines: `accounting.mappings`

Do not enable Spatie teams.

Do not rely on broad `settings.configure` shortcuts for this slice.

## UI Scope

Add a small Inertia management page only if consistent with existing accounting pages.

Page requirements:

- permission-aware actions via `useCan`
- no hardcoded user-facing text in TSX
- all labels/statuses/headings/buttons/empty states in `en.json` and `ar.json`
- RTL support through existing layout
- show mapped and unmapped accounts
- allow assigning an account to a statement line
- show system/protected badges through translated strings
- no landing page

## Validation And Integrity

Required validation:

- statement line code required, normalized, globally unique
- multilingual name required in at least the active/default locale
- statement type must be `balance_sheet` or `income_statement`
- normal balance must be `debit` or `credit`
- cannot delete system statement lines
- cannot delete a line used by accounts
- account assignment must use an active statement line whose statement type matches the account's AccountType/AccountCategory statement type when known

Required schema checks:

- no `company_id`
- no `branch_id`
- no `tenant_id`

## Audit

Use `AuditLogger` for:

- statement line create/update/delete
- account statement line assignment changes

Do not write directly to legacy `audit_log`.

## Tests

Add focused tests for:

- migration/schema
- seed idempotency
- relationships
- assignment validation
- system line delete protection
- in-use line delete protection
- RBAC denial and allowance
- no company/branch/tenant columns
- Inertia props contain translated-ready data and unmapped accounts

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

Report files changed, migrations added, permissions added/reused, tests added, and remaining unmapped-account risks.
