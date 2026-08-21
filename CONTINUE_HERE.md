# CONTINUE HERE - Mini ERP Laravel handoff

Current date/context: 2026-08-21. This is the current handoff for the Laravel + Inertia + React migration track.

The old Next.js app under `app/` remains historical reference only. Do not restore old tenant/company-scope behavior from it.

## Source Of Truth

Use the current Laravel code and these documents first:

- `README.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `DOMAIN_MODEL_REVIEW.md`
- `DOMAIN_RELATIONSHIP_AUDIT.md`
- `SCHEMA_ASSUMPTION_AUDIT.md`
- `PROJECT_LOGIC_AUDIT.md`
- `docs/CONCURRENCY_AUDIT.md`

Historical specs can still be useful for ERP scope, but owner corrections override old generated architecture.

## Current Stack

- Laravel 13.x + PHP 8.3+
- PostgreSQL
- Inertia.js + React + TypeScript + Tailwind
- Laravel session auth and CSRF
- Spatie Permission with teams disabled
- Spatie Translatable for multilingual master data
- Spatie Activitylog as the active audit backend
- Laravel scheduler/queues baseline

## Non-Negotiable Corrections

This ERP is not currently a multi-tenant SaaS.

Do not introduce:

- tenant context or tenant middleware
- `company_user`
- `users.company_id`
- `branch.company_id`
- `fiscal_year.company_id`
- `currentCompany` or `currentBranch`
- company-owned roles/permissions
- Spatie teams
- company/branch dimensions in document numbering
- branch/company security scopes unless explicitly defined later

If a relationship is not explicitly supported by owner requirements or a later owner decision, classify it as:

`UNDEFINED - DO NOT ASSUME`

Confirmed later owner decision:

- FiscalYear is `SINGLE-ERP CONTEXT`.
- Fiscal years are global to this installation/business profile.
- `fiscal_year.year` is globally unique.
- FinancialPeriod belongs to FiscalYear.

## Current Verified Status

The Laravel migration through M10 is complete and locally verified on PostgreSQL.

Implemented:

- M2 Inertia foundation.
- M3 schema foundation and global RBAC.
- M5 Laravel session authentication.
- M6 migrated Inertia shell/pages.
- M7 Laravel core kernel parity.
- Phase 2 accounting core ledger spine:
  - currencies and FX rates
  - fiscal years and periods
  - account categories and account types
  - chart of accounts
  - manual journals
  - posting engine
  - immutable ledger entries
  - reversal workflow
  - opening balances
  - General Journal, General Ledger, Trial Balance
  - demo accounting data seeder and empty states
- M8 page actions:
  - company create/update
  - branch create/update
  - numbering create/update
  - role assign/revoke
- M9 attachments and notifications services.
- M10 Spatie Activitylog migration, audit viewer, scheduler, and jobs baseline.

Latest verified commands:

```powershell
cd laravel
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

Latest results:

- `php artisan migrate:status`: 24 migrations Ran.
- `php artisan test`: 145 tests / 1185 assertions passed.
- `php artisan test --testsuite=Concurrency`: 7 tests / 16 assertions passed.
- `php artisan concurrency:stress --workers=100`: passed.
- `php artisan accounting:concurrency-stress --workers=50`: passed.
- `npm run typecheck`: passed.
- `npm run build`: passed.

## Audit Status

Spatie Activitylog is now the active audit backend.

- New writes go to `activity_log`.
- Legacy `audit_log` is retained as a read-only archive.
- `AuditLogger::record(...)` keeps the old application API but writes through Spatie Activitylog.
- `AuditLogQueryService` maps Spatie rows back to the old UI aliases:
  - `actor_id`
  - `actor_name`
  - `actor_email`
  - `action`
  - `entity_type`
  - `entity_id`
  - `before_json`
  - `after_json`
  - `reason`
  - `request_id`
  - `ip`
  - `device`
  - `at`
- `activity_log` and legacy `audit_log` are protected by append-only DB triggers.

Spatie Activitylog installed version:

```text
spatie/laravel-activitylog 4.12.3
```

## Local Login

Default development bootstrap user:

```text
Email: admin@mini-erp.local
Password: Password123!
Role: SUPER_ADMIN
```

The bootstrap user is not tied to a company, branch, tenant, or current-company context.

## Run Locally

```powershell
cd laravel
composer install
npm install
php artisan migrate --seed
npm run dev
composer run serve:no-xdebug
```

Open:

```text
http://127.0.0.1:8000
```

On this Windows/WAMP setup, direct `php artisan serve` may exit when Xdebug is enabled. Prefer `composer run serve:no-xdebug`.

## Current DB Counts From Last Verification

```text
audit_log: 17
activity_log: 0
users: 2
jobs: 0
failed_jobs: 0
```

`activity_log` can be zero immediately after verification because test writes are rolled back and legacy seed records remain in `audit_log`.

## Next Work

Recommended next product phase: Phase 3 - AR/AP + Cash/Bank/Cheques Foundation.

Use `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md` as the corrected Phase 3 contract.

Do not start Sales, Purchasing, Inventory, Payroll, Rentals, Fixed Assets, or full financial statements unless explicitly requested.

Before Phase 3, keep these invariants:

- no tenant/company/branch scope
- no float money math
- posted journal and ledger data immutable
- corrections by reversal
- numbering atomic
- posting idempotent
- Phase 3 audit uses the owner-approved Spatie Activitylog decision through the existing `AuditLogger` API
- attachment authorization through entity registry
- notifications targeted to users
