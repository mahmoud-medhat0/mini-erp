# Mini ERP - Laravel Migration Foundation

Current target: Laravel + Inertia.js + React + TypeScript + Tailwind + PostgreSQL.

This repository is migrating a Mini ERP foundation from the older Next.js reference app into Laravel. The current Laravel target is a foundation, not a complete ERP. It includes authentication, RBAC foundations, settings pages, Money/accounting invariant primitives, atomic numbering, audit, attachments, notifications, idempotency, and token garbage collection.

## Current Architecture Rule

The Mini ERP must not be treated as a multi-tenant SaaS.

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

If a Company, Branch, User, FiscalYear, Warehouse, Project, Department, CostCenter, Customer, Supplier, Employee, or other relationship is not explicitly supported by owner requirements or later owner decisions, classify it as:

`UNDEFINED - DO NOT ASSUME`

FiscalYear ownership/context has an explicit later owner decision: `SINGLE-ERP CONTEXT`. Fiscal years are global to this ERP installation/business profile, `year` is globally unique, and FinancialPeriod belongs to FiscalYear.

## Implemented Laravel Foundation

- Laravel session authentication with throttling and active-user checks.
- Spatie Permission RBAC with teams disabled.
- Global role templates and module/action permissions.
- Inertia React app shell and settings/dashboard/notification pages.
- Company profile configuration and standalone Branch reference records.
- Global fiscal years with financial periods linked by `fiscal_year_id`.
- Atomic number-sequence allocation by global sequence `key`.
- Money value object and accounting draft-entry invariant checks.
- Audit log linked to actor and audited entity/event.
- Attachment service with allowlisted entity authorization and storage cleanup compensation.
- Notification service targeted to users with per-user dedupe.
- Idempotency store and bounded `tokens:gc`.

## Not Implemented Yet

The Laravel target does not yet implement:

- journal posting engine
- General Ledger persistence
- reversal workflow
- period close enforcement
- subledger posting
- financial statements
- Sales, Purchasing, Inventory, Payroll, Rentals, Reports, or other full ERP modules

Future business transactions should eventually be entered once and flow into accounting automatically, but that is not implemented in this correction pass.

## Setup

```bash
cd laravel
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --force
php artisan db:seed
npm run dev
php artisan serve --host=127.0.0.1 --port=8000
```

## Verification

```bash
cd laravel
php artisan migrate:status
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

## Documentation Status

Some files under `spec/` and the old `app/` directory describe the historical Next.js reference or generated target specifications. They are not authoritative when they conflict with owner corrections or current Laravel implementation.

Use these current review/correction documents first:

- `DOMAIN_MODEL_REVIEW.md`
- `PROJECT_LOGIC_AUDIT.md`
- `MD_DOCUMENTATION_AUDIT.md`
- `DOMAIN_RELATIONSHIP_AUDIT.md`
- `SCHEMA_ASSUMPTION_AUDIT.md`
- `docs/CONCURRENCY_AUDIT.md`

Historical files may mention tenant/company scope. Treat those mentions as legacy unless a later owner decision explicitly confirms the relationship.
