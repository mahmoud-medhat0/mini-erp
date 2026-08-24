# MINI ERP - PHASE 9 SLICE 7 FINAL CUTOVER CLOSE-OUT

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


Execute only after Slices 1-6 are complete or explicitly skipped with a documented reason.

This is the Phase 9 close-out slice.

## Objective

Create the final staging/production cutover report and update handoff docs.

Create:

- `PHASE_9_FINAL_CUTOVER_REPORT.md`

Update:

- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`
- `README.md`

## Review Scope

Review:

- `PHASE_9_STAGING_PRODUCTION_CUTOVER.md`
- `PHASE_9_CUTOVER_DECISION_PACK.md`
- `spec/ENVIRONMENT_CHECKLIST.md`
- `spec/DEPLOYMENT_RUNBOOK.md`
- `spec/ROLLBACK_RUNBOOK.md`
- `spec/BACKUP_RESTORE_DRILL.md`
- `spec/RUNTIME_PROCESSES.md`
- `spec/GO_LIVE_ACCEPTANCE.md`
- `spec/DEPLOYMENT.md`
- `PHASE_8_FINAL_OPERATIONAL_READINESS_REPORT.md`

## Required Verification

Run sequentially from `laravel/`:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --testsuite=Concurrency
php artisan test --filter=Phase8
php artisan concurrency:stress --workers=10
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan accounting:sales-tax-stress --workers=50
php artisan accounting:purchasing-tax-stress --workers=50
php artisan accounting:tax-filing-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Run docs/source checks from repo root:

```powershell
rg -n "DB_PASSWORD=.+|APP_KEY=base64:.+|SECRET=.+|TOKEN=.+|PASSWORD=.+|DATABASE_URL=.+" PHASE_9_*.md spec README.md
rg -n "company_id|branch_id|tenant_id|currentCompany|currentBranch|Spatie Teams" PHASE_9_*.md spec README.md
rg -n "Next.js|Prisma|pg-boss|PGBOSS|prisma migrate" PHASE_9_*.md spec README.md laravel
rg -n "\\b(dump|dd|ray|fwrite|var_dump)\\(|console\\.log\\(" laravel/app laravel/resources/js laravel/tests
git status --short
git diff --stat
```

Classify all remaining matches.

## Final Report Must Include

1. slices completed
2. files created/updated
3. owner decisions still pending
4. deployment runbook status
5. rollback runbook status
6. backup/restore drill status
7. scheduler/queue/storage/mail/logging status
8. go-live smoke/security acceptance status
9. verification command results
10. sensitive-value scan result
11. tenant/company/branch assumption scan result
12. confirmation that no new ERP business module was introduced

Do not mark Phase 9 complete if a required command failed, timed out, or was not run.

