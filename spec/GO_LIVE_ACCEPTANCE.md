# GO-LIVE SMOKE, SECURITY CHECKLIST, AND ACCEPTANCE GATE

**Target Application:** Laravel 13.x + Inertia.js + React + PostgreSQL 15+  
**Scope:** Provider-neutral go-live approval checklist for staging and production cutover.  
**Execution Note:** This document defines acceptance checks only. It does not configure servers, connect providers, or execute production commands.

---

## 1. Pre-Go-Live Approvals

Before a staging or production cutover is approved, the System Owner and System Operator must confirm:

- [ ] Hosting target selected in `PHASE_9_CUTOVER_DECISION_PACK.md`.
- [ ] PostgreSQL hosting and backup owner selected.
- [ ] Domain and HTTPS responsibility selected.
- [ ] Scheduler trigger mechanism selected.
- [ ] Queue worker process manager selected.
- [ ] File storage location selected.
- [ ] Mail provider/mode selected.
- [ ] Backup frequency and restore drill cadence selected.
- [ ] Cutover window approved.
- [ ] Rollback approver confirmed.

No production cutover should proceed while any mandatory owner/operator decision remains unresolved.

---

## 2. Environment Sanity Checks

Perform these checks on the target host without printing secret values:

| Check | Acceptance |
|---|---|
| `APP_ENV` | `staging` or `production` as appropriate |
| `APP_DEBUG` | `false` outside local development |
| `APP_KEY` | present and unique for the environment |
| `APP_URL` | canonical HTTPS URL outside local development |
| `DB_CONNECTION` | `pgsql` |
| `SESSION_DRIVER` | approved deployment value |
| `CACHE_STORE` | approved deployment value |
| `QUEUE_CONNECTION` | approved deployment value |
| `FILESYSTEM_DISK` | approved deployment value |
| `MAIL_MAILER` | approved deployment value |
| `LOG_CHANNEL` | approved deployment value |

Suggested non-secret checks:

```powershell
php artisan env
php artisan config:show app.debug
php artisan migrate:status
```

Do not paste `.env` contents into tickets, docs, prompts, or chat logs.

---

## 3. Login And Shell Smoke

The QA/functional tester validates:

- [ ] login page renders.
- [ ] valid active user can log in.
- [ ] session is established.
- [ ] CSRF-protected actions still submit correctly.
- [ ] dashboard renders after authentication.
- [ ] main navigation shell renders.
- [ ] language toggle works for English and Arabic.
- [ ] logout completes and protected pages redirect/deny correctly.

Evidence to record:

- tester name
- environment URL
- timestamp
- pass/fail status
- any screenshot reference stored outside this repository if needed

---

## 4. Dashboard Smoke

Validate:

- [ ] `/dashboard` returns HTTP 200 for an authenticated user with required permissions.
- [ ] no browser console error blocks rendering.
- [ ] navigation groups open/close.
- [ ] permission-aware menu visibility matches the test user's roles.

---

## 5. Accounting Report Smoke

Validate read-only reporting paths:

- [ ] Reports Hub opens.
- [ ] General Ledger page opens.
- [ ] Trial Balance page opens.
- [ ] Balance Sheet page opens, if mappings/data exist.
- [ ] Income Statement page opens, if mappings/data exist.
- [ ] CSV export works for at least one approved report.
- [ ] user without report permissions receives a forbidden response.

Acceptance rule:

- Reports must use accounting dates and posted ledger/source documents, not row timestamps.
- No report smoke should mutate production accounting data.

---

## 6. Tax Report Smoke

Validate:

- [ ] Tax Codes page opens for a user with `taxes.view`.
- [ ] VAT Register opens.
- [ ] VAT Summary opens.
- [ ] VAT-to-GL reconciliation opens.
- [ ] CSV export works for VAT Register or VAT Summary when export permission is granted.
- [ ] filed tax period rules remain enforced by application tests before go-live.

Acceptance rule:

- Smoke checks may read live/staging data.
- Do not create real production tax filings during smoke unless the owner explicitly approves the exact operation.

---

## 7. Attachment Smoke

Validate attachment behavior in staging first:

- [ ] upload an allowed test file to an allowed entity.
- [ ] download the uploaded test file through authenticated application route.
- [ ] delete the uploaded test file if the user has delete permission.
- [ ] direct public access to private storage paths is not available.
- [ ] rejected file types are blocked.

Production note:

- Production attachment smoke should use a harmless approved test entity and a non-sensitive test file.

---

## 8. Permission-Denied Smoke

Validate least-privilege behavior:

- [ ] user without `reports.view` cannot open financial reports.
- [ ] user without tax permissions cannot open tax admin pages.
- [ ] user without posting permission cannot post financial documents.
- [ ] user without admin/settings permissions cannot modify users, roles, company profile, or numbering.

Acceptance rule:

- Permission failure should return a controlled forbidden response or redirect, not expose stack traces.

---

## 9. Scheduler And Queue Status Smoke

Validate:

- [ ] scheduler list displays registered tasks.
- [ ] external scheduler trigger is configured by operator.
- [ ] `tokens:gc --batch=100` can run manually.
- [ ] queue worker process is supervised.
- [ ] failed job list can be inspected.

Suggested checks:

```powershell
php artisan schedule:list
php artisan tokens:gc --batch=100
php artisan queue:failed
```

Do not clear failed jobs until the operator has recorded the failure IDs and root cause notes.

---

## 10. Backup Availability Confirmation

Before production cutover:

- [ ] pre-cutover PostgreSQL backup exists.
- [ ] backup file/snapshot ID recorded.
- [ ] backup timestamp recorded.
- [ ] storage location recorded by operator.
- [ ] restore drill cadence approved.
- [ ] latest staging restore drill status is known.

Reference:

- `spec/BACKUP_RESTORE_DRILL.md`

---

## 11. Rollback Readiness Confirmation

Before production cutover:

- [ ] rollback approver available.
- [ ] previous stable release identifier known.
- [ ] rollback runbook reviewed.
- [ ] queue/scheduler pause procedure known.
- [ ] database restore escalation path known.
- [ ] maintenance mode bypass placeholder agreed outside repository docs.

Reference:

- `spec/ROLLBACK_RUNBOOK.md`

---

## 12. Security Checklist

| Security Item | Acceptance |
|---|---|
| `APP_DEBUG=false` | Required outside local development |
| Secure `APP_KEY` | Present and unique per environment |
| HTTPS | Enabled by chosen deployment environment |
| Session/cookie settings | Reviewed for target environment |
| File storage | Private by default |
| Private storage exposure | No public direct access to `storage/app/private` |
| Audit logging | Spatie Activitylog active |
| Spatie teams | Disabled |
| Operator access | Least-privilege access only |
| Secrets handling | No secrets committed or pasted in docs/prompts |
| Logs | Retention and review owner selected |

---

## 13. Final Go / No-Go Checklist

Production cutover is **GO** only when all mandatory checks are true:

- [ ] owner/operator decisions complete.
- [ ] environment sanity checks pass.
- [ ] deployment runbook reviewed.
- [ ] rollback runbook reviewed.
- [ ] backup availability confirmed.
- [ ] scheduler and queue status smoke pass.
- [ ] health route returns `HTTP 200` with database `ok`.
- [ ] login/dashboard smoke pass.
- [ ] accounting report smoke pass.
- [ ] tax report smoke pass.
- [ ] attachment smoke pass or is formally deferred by owner.
- [ ] permission-denied smoke pass.
- [ ] security checklist pass.
- [ ] rollback approver signs off.
- [ ] System Owner signs final go-live approval.

If any mandatory check fails, the release is **NO-GO** until corrected and re-verified.

