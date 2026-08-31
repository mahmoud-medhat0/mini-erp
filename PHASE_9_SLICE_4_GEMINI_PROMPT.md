# MINI ERP - PHASE 9 SLICE 4 BACKUP AND RESTORE DRILL PACK

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


Execute only this slice.

This is an operations documentation slice. Do not run backup or restore commands against production.

## Objective

Create a PostgreSQL backup and restore drill pack for staging and production operations.

Create:

- `spec/BACKUP_RESTORE_DRILL.md`

Update only if needed:

- `spec/DEPLOYMENT.md`
- `NEXT_TASKS.md`
- `IMPLEMENTATION_STATUS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

## Read First

- `PHASE_9_STAGING_PRODUCTION_CUTOVER.md`
- `PHASE_9_CUTOVER_DECISION_PACK.md` if it exists
- `spec/ENVIRONMENT_CHECKLIST.md` if it exists
- `spec/DEPLOYMENT_RUNBOOK.md` if it exists
- `spec/ROLLBACK_RUNBOOK.md` if it exists
- `laravel/.env.example`
- `laravel/database/migrations`

## Required Content

Document:

- backup objectives
- backup frequency options
- retention options
- restore test frequency
- staging restore drill flow
- integrity checks after restore
- who approves production restore
- what information must be recorded after a restore drill
- how to verify Laravel can connect after restore

Include generic PostgreSQL command examples using placeholders only.

Examples may include placeholder forms of:

- `pg_dump`
- `pg_restore`
- `psql`

Do not include real usernames, hosts, database names, or passwords.

## Required Integrity Checks

Document post-restore checks:

- `php artisan migrate:status`
- `php artisan test --filter=Phase8`
- `php artisan accounting:phase3-integrity-check`
- `php artisan tokens:gc --batch=100`
- `/health`

## Prohibited

- no real database values
- no production command execution
- no provider account setup
- no destructive command execution
- no tenant/company/branch assumptions

## Verification

Run:

```powershell
git diff --stat
rg -n "DB_PASSWORD=|PASSWORD|SECRET|TOKEN|DATABASE_URL|postgres://|postgresql://" spec/BACKUP_RESTORE_DRILL.md
rg -n "DROP DATABASE|TRUNCATE|db:wipe" spec/BACKUP_RESTORE_DRILL.md
```

Classify any matches and remove unsafe text if needed.

## Final Report

Report:

1. files created/updated
2. backup policy choices left pending
3. restore drill flow
4. post-restore checks
5. sensitive-value scan result

