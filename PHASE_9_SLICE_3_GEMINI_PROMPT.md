# MINI ERP - PHASE 9 SLICE 3 DEPLOYMENT AND ROLLBACK RUNBOOKS

Execute only this slice.

This is a runbook slice. Do not deploy anything and do not run production commands.

## Objective

Create repeatable staging/production deployment and rollback runbooks for the Laravel target.

Create:

- `spec/DEPLOYMENT_RUNBOOK.md`
- `spec/ROLLBACK_RUNBOOK.md`

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
- `PHASE_8_FINAL_OPERATIONAL_READINESS_REPORT.md`
- `spec/DEPLOYMENT.md`
- `README.md`
- `laravel/composer.json`
- `laravel/package.json`
- `laravel/artisan`

## Deployment Runbook Requirements

Document a generic provider-neutral release process:

1. pre-release checks
2. maintenance window approval
3. source/artifact preparation
4. dependency install
5. asset build
6. environment validation
7. database migration step
8. cache/config optimization step
9. scheduler/queue restart checklist
10. health check
11. smoke checks
12. post-release monitoring

Use commands as examples only and clearly mark that they must be run by an operator in the chosen environment.

## Rollback Runbook Requirements

Document:

- when rollback is allowed
- who approves rollback
- code rollback steps
- asset rollback steps
- migration rollback policy
- database backup restore escalation path
- queue and scheduler pause/restart notes
- audit and incident record notes

Important: Do not recommend automatic destructive database rollback for production. If schema rollback is risky, document escalation and restore-from-backup decision steps.

## Prohibited

- no provider-specific account setup
- no GitHub Actions requirement
- no production execution
- no private values
- no destructive command execution
- no new business module
- no tenant/company/branch assumptions

## Verification

Run:

```powershell
git diff --stat
rg -n "DB_PASSWORD=|APP_KEY=base64|SECRET|TOKEN|PASSWORD|DATABASE_URL" spec/DEPLOYMENT_RUNBOOK.md spec/ROLLBACK_RUNBOOK.md
rg -n "git reset --hard|migrate:rollback|db:wipe|DROP DATABASE|TRUNCATE" spec/DEPLOYMENT_RUNBOOK.md spec/ROLLBACK_RUNBOOK.md
```

If destructive-looking text is present, classify it as documentation-only warning/avoidance or remove it.

## Final Report

Report:

1. files created/updated
2. deployment runbook sections
3. rollback runbook sections
4. sensitive-value scan result
5. destructive-command text classification
