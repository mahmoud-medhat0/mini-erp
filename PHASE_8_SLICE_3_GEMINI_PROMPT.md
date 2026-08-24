# MINI ERP - PHASE 8 SLICE 3 SCHEDULER, QUEUE, AND HEALTH READINESS

Execute only after Slice 2 is complete.

This slice may add small operational checks if they are missing, but must not add ERP business behavior.

## Objective

Verify and document the operational runtime pieces:

- `/health` endpoint
- scheduled `tokens:gc`
- database queue baseline
- failed job visibility
- local verification command sequence

## Read First

- `PHASE_8_OPERATIONAL_READINESS.md`
- `PHASE_8_OPERATIONAL_READINESS_DECISION.md`
- `spec/DEPLOYMENT.md`
- `laravel/routes/console.php`
- `laravel/routes/web.php`
- `laravel/app/Http/Controllers/HealthCheckController.php`
- `laravel/database/migrations/*jobs*`
- `laravel/tests/Feature/HealthCheckTest.php`

## Required Work

Inspect before changing.

Allowed changes:

- add or improve feature tests for `/health`
- add or improve tests proving scheduler command registration where practical
- add a read-only operational checklist doc if needed
- add local command documentation

Not allowed:

- do not add provider-specific process manager config
- do not change queue semantics
- do not add external integrations
- do not expose environment values
- do not add business module logic

## Verification

Run sequentially:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=HealthCheck
php artisan test --filter=Phase8Slice3
php artisan tokens:gc --batch=100
php artisan test
npm run typecheck
npm run build
```

If `Phase8Slice3` does not exist because no test was needed, state why and run the broader applicable tests.

## Final Report

Report:

1. health readiness
2. scheduler readiness
3. queue readiness
4. docs/tests added or updated
5. commands run
6. features intentionally not implemented

