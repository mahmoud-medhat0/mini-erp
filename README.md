# Mini ERP - Laravel Migration

Current target: Laravel + Inertia.js + React + TypeScript + Tailwind + PostgreSQL.

The repository still contains the older Next.js reference app under `app/`, but the active migration target is `laravel/`.

## Current Rule

The Mini ERP is not currently a multi-tenant SaaS.

Do not introduce or restore:

- tenant context or tenant middleware
- `company_user`
- `users.company_id`
- `branch.company_id`
- `fiscal_year.company_id`
- Company-owned users, branches, roles, or permissions
- Spatie Teams
- `currentCompany` or `currentBranch`
- company/branch dimensions in document numbering

If a relationship is not explicitly supported by owner requirements or a later owner decision, classify it as:

`UNDEFINED - DO NOT ASSUME`

## Implemented Laravel Scope

- Laravel session authentication with throttling, active-user checks, and bootstrap admin seeding.
- Spatie Permission RBAC with teams disabled.
- Global role templates and module/action permissions.
- Inertia React app shell and settings/dashboard/notification pages.
- Company profile configuration and standalone Branch reference records.
- Global FiscalYear with FinancialPeriod linked by `fiscal_year_id`.
- Atomic document number sequence allocation by global `key`.
- Money value object, currency registry, accounting invariant kernel, and number formatting/config primitives.
- Phase 2 Accounting Core:
  - account categories and account types
  - chart of accounts
  - FX rates
  - fiscal periods
  - manual journal workflow
  - posting engine
  - immutable ledger entries
  - reversal workflow
  - opening balances
  - General Journal, General Ledger, Trial Balance
- M8 settings/user actions for company, branch, numbering, and role assign/revoke.
- M9 attachment and notification services.
- M10 Spatie Activitylog active audit backend, read-only audit viewer, scheduler, and queue/jobs baseline.
- Phase 3 Slice 1 master data foundation:
  - Customer and Supplier models/services.
  - CashAccount and BankAccount models/services linked to GL accounts and currencies.
  - optimistic locking, RBAC permissions, Spatie Activitylog audit, and attachment registry entries.
- Idempotency store, bounded `tokens:gc`, and PostgreSQL stress commands.

## Not Implemented Yet

- AR/AP operational subledgers beyond the accounting ledger spine.
- Cash/Bank operational posting, statements, reconciliation, and Cheques lifecycle.
- Sales and Purchasing workflows.
- Inventory.
- Payroll, Rentals, Fixed Assets, Projects, Budgeting, Recurring workflows.
- Full financial statements such as Balance Sheet, Income Statement, Cash Flow, and Equity Statement.
- Laravel browser E2E parity with the old Next.js Playwright suite.

## Setup

```powershell
cd laravel
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run dev
composer run serve:no-xdebug
```

Open:

```text
http://127.0.0.1:8000
```

Default development login:

```text
Email: admin@mini-erp.local
Password: Password123!
Role: SUPER_ADMIN
```

## Verification

Run from `laravel/`:

```powershell
composer install
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Latest verified result:

- 25 migrations Ran.
- 159 PHPUnit tests / 1243 assertions passed.
- 7 Concurrency suite tests / 16 assertions passed.
- PostgreSQL concurrency and accounting stress commands passed.
- TypeScript typecheck and Vite build passed.

## Documentation Entry Points

Use these first:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`
- `PHASE_3_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_2_GEMINI_PROMPT.md`
- `DOMAIN_MODEL_REVIEW.md`
- `DOMAIN_RELATIONSHIP_AUDIT.md`
- `SCHEMA_ASSUMPTION_AUDIT.md`
- `PROJECT_LOGIC_AUDIT.md`
- `docs/CONCURRENCY_AUDIT.md`

Historical files may mention tenant/company scope. Treat those mentions as legacy unless a later owner decision explicitly confirms the relationship.
