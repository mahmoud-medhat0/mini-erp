# Mini ERP Laravel App

This is the active Laravel + Inertia + React migration app for the Mini ERP.

## Stack

- Laravel 13.x
- PHP 8.3+
- PostgreSQL
- Inertia.js + React + TypeScript
- Tailwind 4
- Spatie Permission with teams disabled
- Spatie Translatable
- Spatie Activitylog

## Local Development

```powershell
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev
composer run serve:no-xdebug
```

Then open:

```text
http://127.0.0.1:8000
```

On the current Windows/WAMP PHP setup, Xdebug can make the PHP development server exit after the first request. Use:

```powershell
composer run serve:no-xdebug
```

Do not run `php artisan serve` directly on this machine unless Xdebug is disabled first.

## Development Login

```text
Email: admin@mini-erp.local
Password: Password123!
Role: SUPER_ADMIN
```

The bootstrap user is a local migration/development entrypoint. It is not tied to a company, branch, tenant, or current-company context.

Useful env controls:

- `ERP_SEED_BOOTSTRAP_USER`
- `ERP_BOOTSTRAP_USER_EMAIL`
- `ERP_BOOTSTRAP_USER_PASSWORD`
- `ERP_BOOTSTRAP_USER_ASSIGN_ROLE`
- `ERP_BOOTSTRAP_USER_ROLE`

## Implemented Scope

- Session auth with throttling and active-user checks.
- Global RBAC through Spatie Permission.
- Settings pages and actions for company profile, standalone branches, numbering, users, roles.
- Phase 2 Accounting Core: account categories/types, chart of accounts, FX rates, periods, journals, posting, ledger, reversal, opening balances, General Journal, General Ledger, Trial Balance.
- Attachments service and UI panel with entity authorization.
- Notifications service and notification center.
- Spatie Activitylog-backed audit service and `/audit-log` viewer.
- Scheduler entry for `tokens:gc --batch=100`.
- Queue/jobs baseline tables and tests.
- Phase 3 Slices 1-5: Customer/Supplier, CashAccount/BankAccount, AR/AP subledgers and opening balances, receipt/payment posting, allocation settlement, and cheque lifecycle.

## Verification

```powershell
composer install
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Latest verified result:

- 31 migrations Ran.
- 202 PHPUnit tests total / 200 passed / 2 PostgreSQL-specific skipped, 1464 assertions.
- Concurrency suite passed.
- PostgreSQL concurrency/accounting/allocation/cheque stress commands passed.
- TypeScript typecheck and Vite build passed.

## Audit Backend

Spatie Activitylog is the active audit backend.

- New audit writes go to `activity_log`.
- Legacy `audit_log` is retained as a read-only archive.
- Both tables are protected by append-only DB triggers.
- The app-level `AuditLogger::record(...)` API is preserved.

## Health

```text
http://127.0.0.1:8000/health
```
