# MINI ERP - PHASE 9 SLICE 6 GO-LIVE SMOKE, SECURITY CHECKLIST, AND ACCEPTANCE GATE

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


Execute only this slice.

This slice prepares go-live acceptance documentation and local smoke verification. Do not connect CI/CD or external browser services unless explicitly approved later.

## Objective

Create a go-live smoke and security checklist for the Laravel ERP.

Create:

- `spec/GO_LIVE_ACCEPTANCE.md`

Update only if needed:

- `spec/DEPLOYMENT.md`
- `README.md`
- `NEXT_TASKS.md`
- `IMPLEMENTATION_STATUS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

## Read First

- `PHASE_9_STAGING_PRODUCTION_CUTOVER.md`
- `PHASE_8_FINAL_OPERATIONAL_READINESS_REPORT.md`
- `spec/ENVIRONMENT_CHECKLIST.md` if it exists
- `spec/DEPLOYMENT_RUNBOOK.md` if it exists
- `spec/RUNTIME_PROCESSES.md` if it exists
- `laravel/tests/Feature/Phase8Slice4RouteSmokeTest.php`
- `laravel/routes/web.php`
- `laravel/resources/js/Layouts/AppLayout.tsx`

## Required Content

Document:

- pre-go-live approvals
- environment sanity checks
- login smoke
- dashboard smoke
- accounting report smoke
- tax report smoke
- attachment smoke
- permission-denied smoke
- scheduler/queue status smoke
- backup availability confirmation
- rollback readiness confirmation
- final go/no-go checklist

Security checklist must cover:

- `APP_DEBUG=false`
- secure `APP_KEY` presence
- HTTPS enabled by chosen environment
- session/cookie settings reviewed
- file storage private by default
- audit logging active
- Spatie teams disabled
- no public exposure of storage/private files
- least-privilege operator access

## UI/Text Rules

Do not add new visible hardcoded text to React pages.

If any UI changes are absolutely required, visible text must use existing EN/AR dictionaries or add dictionary keys in both locales.

## Prohibited

- no provider account setup
- no GitHub Actions requirement
- no external service integration
- no production command execution
- no private values
- no tenant/company/branch assumptions
- no new ERP business module

## Verification

Run:

```powershell
git diff --stat
rg -n "DB_PASSWORD=|APP_KEY=base64|SECRET|TOKEN|PASSWORD|DATABASE_URL" spec/GO_LIVE_ACCEPTANCE.md
rg -n "company_id|branch_id|tenant_id|currentCompany|currentBranch|Spatie Teams" spec/GO_LIVE_ACCEPTANCE.md
rg -n "locale ===|[\\u0600-\\u06FF]" laravel/resources/js
php artisan test --filter=Phase8
```

If the Arabic scan prints existing dictionary-backed pages, classify the matches. Do not introduce new hardcoded visible text.

## Final Report

Report:

1. files created/updated
2. smoke checklist sections
3. security checklist sections
4. Phase 8 smoke test result
5. sensitive-value scan result
6. UI hardcoded text classification

