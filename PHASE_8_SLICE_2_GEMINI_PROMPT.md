# MINI ERP - PHASE 8 SLICE 2 LARAVEL DEPLOYMENT DOCUMENTATION REFRESH

Execute only after Slice 1 is complete.

This slice updates documentation only unless a broken link or stale command must be corrected in docs.

## Objective

Replace obsolete deployment guidance that still describes the old Next.js/Prisma implementation with current Laravel + Inertia + React + PostgreSQL guidance.

## Read First

- `PHASE_8_OPERATIONAL_READINESS.md`
- `PHASE_8_OPERATIONAL_READINESS_DECISION.md`
- `PHASE_7_FINAL_VERIFICATION_REPORT.md`
- `spec/DEPLOYMENT.md`
- `README.md`
- `laravel/README.md`
- `laravel/.env.example`
- `laravel/composer.json`
- `laravel/package.json`

## Required Updates

Update or create docs that cover:

- application root is `laravel/`
- PHP and Composer requirements
- Node/npm build requirements
- PostgreSQL requirement
- Laravel database migrations
- Laravel scheduler operation
- Laravel queue worker operation
- Vite asset build
- health route
- local verification commands
- staging deployment checklist
- production deployment checklist

Environment section must list names only, not actual values.

## Must Correct

In `spec/DEPLOYMENT.md`, remove or clearly mark obsolete references to:

- Next.js runtime
- Prisma migrations
- pg-boss worker
- `DATABASE_URL` as the active Laravel database source

Replace with Laravel equivalents:

- `php artisan migrate --force`
- `php artisan schedule:run`
- `php artisan queue:work`
- Laravel `.env` database variables
- `npm run build`

## Prohibited

- do not introduce GitHub Actions as required
- do not add provider-specific hosting commands
- do not expose values from `.env`
- do not modify application behavior
- do not add tenant/company/branch assumptions

## Verification

Run:

```powershell
git diff --stat
rg -n "Next.js|Prisma|pg-boss|PGBOSS|prisma migrate|DATABASE_URL" spec/DEPLOYMENT.md README.md laravel/README.md
```

Any remaining match must be classified as either `HISTORICAL_REFERENCE` or corrected.

## Final Report

Report:

1. docs updated
2. obsolete references corrected
3. environment names documented
4. commands documented
5. confirmation that no Laravel behavior changed

