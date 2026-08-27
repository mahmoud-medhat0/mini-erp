# MINI ERP - PHASE 9 SLICE 5 RUNTIME PROCESSES, STORAGE, MAIL, AND LOGS

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


Execute only this slice.

This is an operations documentation slice. Do not configure a real server or provider account.

## Objective

Create provider-neutral runtime operations documentation for Laravel scheduler, queue workers, storage, mail, and logs.

Create:

- `spec/RUNTIME_PROCESSES.md`

Update only if needed:

- `spec/DEPLOYMENT.md`
- `NEXT_TASKS.md`
- `IMPLEMENTATION_STATUS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

## Read First

- `PHASE_9_STAGING_PRODUCTION_CUTOVER.md`
- `PHASE_8_FINAL_OPERATIONAL_READINESS_REPORT.md`
- `spec/ENVIRONMENT_CHECKLIST.md` if it exists
- `spec/DEPLOYMENT_RUNBOOK.md` if it exists
- `laravel/routes/console.php`
- `laravel/config/queue.php`
- `laravel/config/filesystems.php`
- `laravel/config/mail.php`
- `laravel/config/logging.php`
- `laravel/app/Console/Commands`

## Required Content

Document:

- scheduler purpose and external trigger requirement
- queue worker purpose and restart policy
- failed job review process
- token GC schedule
- attachment storage requirements
- mail delivery modes
- log location/retention expectations
- health check endpoint
- operator checklist after restart

Use generic examples with placeholders only.

Allowed examples:

- cron-style scheduler command using project path placeholder
- supervisor/systemd-style concepts using placeholders
- Laravel queue command examples using safe flags

## Prohibited

- no provider-specific account setup
- no private paths unless placeholder paths
- no real environment values
- no production execution
- no hardcoded visible UI text
- no new business logic
- no tenant/company/branch assumptions

## Verification

Run:

```powershell
git diff --stat
rg -n "DB_PASSWORD=|APP_KEY=base64|SECRET|TOKEN|PASSWORD|DATABASE_URL" spec/RUNTIME_PROCESSES.md
rg -n "company_id|branch_id|tenant_id|currentCompany|currentBranch|Spatie Teams" spec/RUNTIME_PROCESSES.md
```

Classify any matches.

## Final Report

Report:

1. files created/updated
2. scheduler operations documented
3. queue operations documented
4. storage/mail/logging operations documented
5. sensitive-value scan result
6. scope-assumption scan result
