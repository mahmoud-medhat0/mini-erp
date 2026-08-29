# GO-LIVE GATE EXECUTION LOG

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.

**Date:** 2026-08-30  
**Status:** LOCAL RELEASE-CANDIDATE GATE COMPLETE / EXTERNAL STAGING-PRODUCTION EXECUTION PENDING  
**Target Stack:** Laravel 13.x + Inertia.js + React + PostgreSQL  
**Deployment Policy:** No production deployment was executed from this repository session.

---

## 1. What Was Executed Locally

The repository now includes an executable non-secret readiness command:

```powershell
php artisan ops:go-live-readiness --target=local --strict --include-route-audit
```

Local result on 2026-08-30:

| Check Area | Result |
|---|---|
| Application environment | PASS - local profile recognized |
| Application key | PASS - configured |
| Debug policy for local | PASS |
| Application URL | PASS - configured |
| Database connectivity | PASS - PostgreSQL connection available |
| Migration status | PASS - zero pending migrations |
| Queue configuration | PASS - database queue configured |
| Scheduler registration | PASS - `tokens:gc --batch=100` registered |
| Storage privacy | PASS - local private storage serving disabled |
| Mail configuration | PASS for local - `log` transport |
| Logging configuration | PASS |
| RBAC teams setting | PASS - Spatie Teams disabled |
| Operational tables | PASS - users, sessions, activity_log present |
| Route authorization audit | PASS - 457 routes scanned, 0 failing |

---

## 2. Runtime Smoke Executed Locally

| Command / Check | Result |
|---|---|
| `php artisan schedule:list` | PASS - `tokens:gc --batch=100` scheduled hourly |
| `php artisan tokens:gc --batch=100` | PASS - completed; deleted `sessions=0 password_reset_tokens=0 idempotency_keys=100` |
| `php artisan queue:failed` | PASS - no failed jobs found |
| `php artisan route:list --path=health` | PASS - `GET|HEAD /health` registered |
| `php artisan route:list --path=storage` | PASS - no direct storage route registered |
| `php artisan test --filter=HealthCheckTest --compact` | PASS - 1 test / 3 assertions |
| `php artisan test --filter=Phase8Slice3OperationalReadinessTest --compact` | PASS - 3 tests / 8 assertions |
| `php artisan test --filter=GoLiveReadinessCommandTest --compact` | PASS - 2 tests / 23 assertions |

---

## 3. Expanded Regression Verification

During the go-live gate pass, legacy Phase 5/6 feature tests were aligned with the current sensitive-action confirmation contract. The application security policy was not weakened: close/reopen period, fixed asset capitalization, depreciation runs, and fixed asset disposals still require exact confirmation codes and justification where configured.

| Regression Batch | Result |
|---|---|
| `php artisan test --testsuite=Unit --compact` | PASS - 5 tests / 15 assertions |
| `php artisan test --testsuite=Integration --compact` | PASS - 8 tests / 70 assertions |
| `php artisan test --testsuite=Invariants --compact` | PASS - 15 tests / 522 assertions |
| `php artisan test --testsuite=Concurrency --compact` | PASS - 7 tests / 16 assertions |
| Non-phase feature/regression batch | PASS - 177 tests / 1655 assertions |
| Phase 3 through Phase 8 batch | PASS - 407 tests / 3103 assertions, 3 skipped |
| Phase 10 through Phase 16 batch | PASS - 184 tests / 1851 assertions |
| Phase 15 product hardening | PASS - 192 tests / 26116 assertions |
| Phase 18 product acceptance | PASS - 16 tests / 1264 assertions |
| Phase 19 accountant acceptance | PASS - 23 tests / 459 assertions |
| Phase 20 hands-on acceptance | PASS - 14 tests / 289 assertions |
| `vendor/bin/pint --test` | PASS |
| `npm run typecheck` | PASS - 0 TypeScript errors |
| `npm run build` | PASS - Vite build completed with existing chunk-size warning only |

The monolithic `php artisan test --compact` command exceeds the local command timeout because the acceptance suite is now large. The same coverage was executed in bounded batches above.

---

## 4. Production Readiness Probe From Local

The staging and production profiles were intentionally executed from the local environment to prove they fail closed:

```powershell
php artisan ops:go-live-readiness --target=staging --strict --include-route-audit --json
php artisan ops:go-live-readiness --target=production --strict --include-route-audit --json
```

Staging result from local:

| Item | Result |
|---|---|
| Status | `not_ready` |
| Blocking failures | 3 |
| Expected local-only blockers | `APP_ENV` is not staging, `APP_DEBUG` is not false, `APP_URL` is not HTTPS |
| Route authorization | PASS - 457 routes / 0 failing |
| Database/migrations | PASS on current local PostgreSQL |

Production result from local:

| Item | Result |
|---|---|
| Status | `not_ready` |
| Blocking failures | 4 |
| Expected local-only blockers | `APP_ENV` is not production, `APP_DEBUG` is not false, `APP_URL` is not HTTPS, mail transport is `log` |
| Route authorization | PASS - 457 routes / 0 failing |
| Database/migrations | PASS on current local PostgreSQL |

This is the desired behavior. The staging and production gates must pass only on their real target hosts with real environment variables configured outside the repository.

---

## 5. External Actions Still Required On Staging/Production Hosts

These items cannot be completed from the local repository because they require real infrastructure:

| External Action | Required Owner/Operator Input |
|---|---|
| Staging deployment dry-run | Staging host, domain/URL, database endpoint, application runtime user |
| Production environment configuration | Real `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, DB credentials, queue/cache/session choices |
| Backup availability | Backup storage provider/path, backup ID or snapshot ID, restore operator |
| Restore drill | Isolated staging database and approved restore window |
| Queue worker supervision | Supervisor/systemd/container process manager on the target host |
| Scheduler external trigger | Cron/systemd/external scheduler configured every minute on the target host |
| Mail delivery | Approved SMTP/SES/provider credentials and verified sender domain |
| File storage | Local private disk permissions or private S3-compatible bucket configuration |
| HTTPS/domain | DNS target and certificate termination responsibility |
| Live owner walkthrough | Owner/accountant/auditor execution and written sign-off |

---

## 6. Target Host Acceptance Commands

Run these after deploying code and configuring target environment variables on staging:

```powershell
php artisan ops:go-live-readiness --target=staging --strict --include-route-audit
php artisan migrate:status
php artisan test --filter=Phase8 --compact
php artisan test --filter=HealthCheckTest --compact
php artisan tokens:gc --batch=100
php artisan queue:failed
```

Run these on production before opening the system to users:

```powershell
php artisan ops:go-live-readiness --target=production --strict --include-route-audit
php artisan migrate:status
php artisan schedule:list
php artisan queue:failed
```

The production smoke must remain read-only unless the System Owner explicitly approves a harmless test transaction and reversal/cleanup plan.

---

## 7. Go / No-Go State

| Gate | Current State |
|---|---|
| Product implementation | GO - Phase 20 complete |
| Local release-candidate verification | GO |
| Local operational readiness command | GO |
| Staging deployment dry-run | PENDING EXTERNAL HOST |
| Production environment readiness | PENDING EXTERNAL HOST |
| Backup/restore drill | PENDING EXTERNAL HOST |
| Live owner/accountant sign-off | PENDING OWNER WALKTHROUGH |
| Production deployment | NO-GO until all pending external gates pass |

Final local conclusion:

> The codebase is ready to be treated as a release candidate. Production deployment is not yet authorized because staging, production environment variables, backup/restore evidence, and owner sign-off require real infrastructure and operator approval.
