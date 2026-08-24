# DEPLOYMENT

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


Current target: Laravel + Inertia + React + TypeScript + PostgreSQL.

For step-by-step deployment and rollback operations, see:
- `spec/DEPLOYMENT_RUNBOOK.md`
- `spec/ROLLBACK_RUNBOOK.md`
- `spec/ENVIRONMENT_CHECKLIST.md`
- `spec/BACKUP_RESTORE_DRILL.md`
- `spec/RUNTIME_PROCESSES.md`
- `spec/GO_LIVE_ACCEPTANCE.md`

The old Next.js application under `app/` is historical reference only. Active runtime and deployment planning apply to `laravel/`.

## Topology

- Web process: Laravel application served by PHP-FPM or another approved PHP application runtime behind a reverse proxy.
- Asset build: Vite production assets built from `laravel/` with `npm run build`.
- Database: PostgreSQL.
- Queue process: Laravel queue worker using the configured `QUEUE_CONNECTION`.
- Scheduler: external cron or process scheduler runs `php artisan schedule:run` every minute.
- Storage: Laravel filesystem disk configured through environment variables and deployment policy.

## Environments

Recommended lifecycle:

- local
- staging
- production

Configuration is environment-based. Document required variable names, but do not store private values in the repository. See `spec/ENVIRONMENT_CHECKLIST.md` for the complete variable-by-variable checklist.

Minimum environment groups:

- application: `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`
- locale: `APP_LOCALE`, `APP_FALLBACK_LOCALE`
- database: `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSLMODE`
- session/cache/queue: `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`
- mail/logging/storage: `MAIL_*`, `LOG_*`, `FILESYSTEM_DISK`
- frontend name: `VITE_APP_NAME`

## Build

Run from `laravel/`:

```powershell
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

For local development, keep using the repository setup instructions in `README.md`.

## Migrations

Run Laravel migrations during a controlled release window:

```powershell
php artisan migrate --force
php artisan migrate:status
```

Do not run destructive database reset commands against staging or production.

## Runtime Commands

Web process:

```powershell
php artisan serve
```

Production deployments normally use a PHP application server or PHP-FPM process instead of `artisan serve`.

Queue worker:

```powershell
php artisan queue:work --tries=3 --backoff=5
```

Scheduler:

```powershell
php artisan schedule:run
```

The scheduler currently includes bounded cleanup for expired auth/idempotency records:

```powershell
php artisan tokens:gc --batch=100
```

## Health Check

The Laravel target exposes:

```text
GET /health
```

The route returns JSON and checks database connectivity.

## Verification

Run from `laravel/` before release:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=10
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Phase-specific stress commands from completed modules should also be run when their modules were touched.

## Release Notes

- The ERP is a single-installation system unless the owner later defines otherwise.
- Do not add tenant/company/branch ownership assumptions during deployment work.
- GitHub Actions are not currently required for this local migration track.
- Production backup, restore testing, mail provider, storage provider, domain, HTTPS termination, queue process manager, and external scheduler mechanism remain deployment-owner decisions.
