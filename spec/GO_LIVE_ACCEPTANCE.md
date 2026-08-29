# GO-LIVE SMOKE, SECURITY CHECKLIST, AND ACCEPTANCE GATE

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.

> **Branch-Capable Product Direction:** Future product work must support multiple operational branches and branch transfer workflows without treating branches as tenants. See root `PRODUCT_EXTENSIBILITY_ROADMAP.md`.


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

Product scope note:

- Current go-live acceptance covers the implemented ERP scope through Phase 13 Payroll Foundation.
- Warehouses, stock transfers, stock counts, stock adjustments, warehouse-aware fulfillment/return selectors, branch cash/bank transfers, fixed asset branch/location movement, branch operational reports, branch-filtered ledger review, branch profitability, Branch Profitability export/print, optional branch-specific GL mapping overrides, optional branch-aware approval rules for inventory approvals, and landed cost/freight allocation are implemented product scope.
- Expense management, prepaid/accrued expense scheduling, and payroll foundation workflows are implemented product scope.
- Adding future capabilities must follow `PRODUCT_EXTENSIBILITY_ROADMAP.md` and must not introduce multi-tenant architecture.

---

## 2. Environment Sanity Checks

Executable non-secret gate:

```powershell
php artisan ops:go-live-readiness --target=staging --strict --include-route-audit
php artisan ops:go-live-readiness --target=production --strict --include-route-audit
```

The command must pass on the real target host before staging or production is treated as ready. It does not print secret values.

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
| `FILESYSTEM_LOCAL_SERVE` | `false` unless a formally reviewed signed-file-serving policy is approved |
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
- [ ] inactive authenticated user cannot access protected pages and is logged out.
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

## 7. Inventory And Operations Smoke

Validate:

- [ ] Warehouses page opens for a user with `inventory.view`.
- [ ] Stock Transfers page opens and shows source/destination warehouse fields.
- [ ] Stock Counts page opens and shows count lifecycle actions according to permissions.
- [ ] Stock Adjustments page opens and shows adjustment lifecycle actions according to permissions.
- [ ] Stock Balances report opens with warehouse filter.
- [ ] Stock Movement report opens with warehouse filter.
- [ ] Delivery Note and Goods Receipt screens require/select an operational warehouse.
- [ ] Sales Return and Purchase Return screens require/select an operational warehouse.
- [ ] user without inventory permissions cannot mutate inventory workflows.

Acceptance rule:

- Inventory smoke in production should be read-only unless the owner approves a harmless test document and reversal/cleanup plan.
- Warehouse and branch references are operational dimensions only, not tenant/security context.

---

## 8. Attachment Smoke

Validate attachment behavior in staging first:

- [ ] upload an allowed test file to an allowed entity.
- [ ] download the uploaded test file through authenticated application route.
- [ ] delete the uploaded test file if the user has delete permission.
- [ ] direct public access to private storage paths is not available.
- [ ] rejected file types are blocked.

Production note:

- Production attachment smoke should use a harmless approved test entity and a non-sensitive test file.

---

## 9. Payroll Smoke

Validate payroll behavior with a test user that has approved payroll permissions:

- [ ] Payroll Employees page opens only for users with `payroll.view` and `view_payroll`.
- [ ] Payroll Components page opens only for users with `payroll.view` and `view_payroll`.
- [ ] Payroll Runs page opens only for users with `payroll.view` and `view_payroll`.
- [ ] employee create/update actions require the matching payroll write permission and `view_payroll`.
- [ ] payroll run generation, submit, approve, post, and cancel actions follow permission-aware UI visibility.
- [ ] payroll posting requires `payroll.post`, `view_payroll`, and `view_financials`.
- [ ] payroll attachments on employees or payroll runs are delivered only through authenticated application routes.
- [ ] a user without `view_payroll` cannot see payroll navigation or open payroll pages.

Acceptance rule:

- Payroll smoke in production should be read-only unless the owner approves a harmless test payroll run and reversal/cancellation plan.
- Payroll branch references are operational/reporting dimensions only, not tenant/security context.

---

## 10. Permission-Denied Smoke

Validate least-privilege behavior:

- [ ] user without `reports.view` cannot open financial reports.
- [ ] user without tax permissions cannot open tax admin pages.
- [ ] user without posting permission cannot post financial documents.
- [ ] user without `view_payroll` cannot open payroll pages or payroll attachments.
- [ ] user without admin/settings permissions cannot modify users, roles, company profile, or numbering.

Acceptance rule:

- Permission failure should return a controlled forbidden response or redirect, not expose stack traces.

---

## 11. Scheduler And Queue Status Smoke

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

## 12. Backup Availability Confirmation

Before production cutover:

- [ ] pre-cutover PostgreSQL backup exists.
- [ ] backup file/snapshot ID recorded.
- [ ] backup timestamp recorded.
- [ ] storage location recorded by operator.
- [ ] restore drill cadence approved.
- [ ] latest staging restore drill status is known.

Reference:

- `spec/BACKUP_RESTORE_DRILL.md`
- `spec/GO_LIVE_EXECUTION_LOG.md`

---

## 13. Rollback Readiness Confirmation

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

## 14. Security Checklist

| Security Item | Acceptance |
|---|---|
| `APP_DEBUG=false` | Required outside local development |
| Secure `APP_KEY` | Present and unique per environment |
| HTTPS | Enabled by chosen deployment environment |
| Session/cookie settings | Reviewed for target environment |
| File storage | Private by default |
| Private storage exposure | No public direct access to `storage/app/private` |
| Framework storage route | `/storage/*` is absent unless explicitly approved |
| Security headers | Baseline web security headers are present on web responses |
| Route authorization | Protected application routes have explicit `can` / `permission.any` / `permission.all` middleware or documented service-level entity authorization |
| Tax filing permission | `taxes.file` exists as a sensitive capability and is assigned only to approved filing roles |
| Payroll visibility permission | `view_payroll` exists as a sensitive capability and is required alongside payroll module permissions |
| Inactive users | Protected access rechecks `users.is_active` |
| Audit logging | Spatie Activitylog active |
| Spatie teams | Disabled |
| Operator access | Least-privilege access only |
| Secrets handling | No secrets committed or pasted in docs/prompts |
| Logs | Retention and review owner selected |

---

## 15. Final Go / No-Go Checklist

Production cutover is **GO** only when all mandatory checks are true:

- [ ] `ops:go-live-readiness --target=production --strict --include-route-audit` passes on the production host.
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
- [ ] inventory and operations smoke pass.
- [ ] payroll smoke pass or payroll production smoke is formally deferred by owner.
- [ ] attachment smoke pass or is formally deferred by owner.
- [ ] permission-denied smoke pass.
- [ ] security checklist pass.
- [ ] rollback approver signs off.
- [ ] System Owner signs final go-live approval.

If any mandatory check fails, the release is **NO-GO** until corrected and re-verified.

