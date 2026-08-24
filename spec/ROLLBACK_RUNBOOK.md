# LARAVEL ERP ROLLBACK RUNBOOK

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


**Target Stack:** Laravel 13.x + Inertia.js + React + PostgreSQL 15+  
**Scope:** Controlled rollback, recovery procedures, and backup restore escalation for Staging & Production.  
**Execution Note:** Rollback procedures must be authorized by the designated Rollback Approver and executed by a qualified System Operator.

---

## 1. When Rollback is Allowed / الحالات المسموح فيها بالتراجع

Rollback execution is authorized under the following operational failure scenarios post-release:

- **Database Connectivity / Migration Failure:** Schema migration deadlocks, migration failure mid-execution, or PostgreSQL connection errors preventing application boot.
- **Critical Application Failure (HTTP 500 Loop):** Widespread application exceptions rendering primary workflows (login, invoicing, reports) inaccessible.
- **Data Corruption or Invariant Violation:** Unintended state mutations or severe ledger imbalance detected during post-release smoke verification.
- **Unresolved Queue / Async Deadlocks:** Worker loops crashing continuously, corrupting job payloads, or failing to process queue jobs.

---

## 2. Rollback Approval Authority / سلطة اعتماد التراجع

- **Rollback Approver:** Authorized exclusively by the **System Owner** or delegated **Technical Lead / System Operator** designated in `PHASE_9_CUTOVER_DECISION_PACK.md`.
- **Decision Protocol:** If a critical defect cannot be resolved via a minor hotfix within a 15-minute maintenance window, the Rollback Approver issues an immediate **ROLLBACK** directive.

---

## 3. Maintenance Isolation / تفعيل الصيانة والإيقاف المؤقت

Before initiating rollback steps, isolate the application to prevent further user transaction attempts:

```powershell
# 1. Enable application maintenance mode with operator secret
php artisan down --secret="OPERATOR_ROLLBACK_BYPASS_TOKEN" --message="System maintenance rollback in progress."

# 2. Stop queue worker process manager
# If using Supervisor:
# supervisorctl stop erp-worker:*

# Direct Artisan command:
php artisan queue:stop
```

---

## 4. Code & Asset Rollback Steps / خطوات التراجع عن الكود والملفات

Revert the application deployment checkout to the previous stable release commit or tag:

```powershell
# 1. Fetch tags and checkout previous stable production tag
git fetch origin --tags
git checkout tags/v0.9.0-previous

# 2. Re-install PHP production dependencies for previous release
composer install --no-dev --optimize-autoloader --no-interaction

# 3. Re-install Node dependencies and re-compile frontend production assets
npm ci
npm run build

# 4. Re-optimize Laravel application caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 5. Migration Rollback Policy / سياسة الهجرات وقواعد البيانات

> [!CAUTION]
> **CRITICAL PRODUCTION SAFETY RULE:**
> Do NOT execute unqualified destructive commands (such as `migrate:fresh`, `db:wipe`, `git reset --hard` with data loss, `DROP DATABASE`, or `TRUNCATE`) against production databases containing live transactional records.

### Evaluating Migration Rollback Paths:

1. **Non-Destructive Additive Migrations (Safe):** If the failed release only added new columns or tables without altering existing data, schema rollback via `php artisan migrate:rollback --step=1` may be executed after validating that zero live data was written to the new structures.
2. **Data-Altering or Destructive Migrations (Escalation Required):** If the release modified existing column types, deleted columns, or executed data transformations, **DO NOT RUN `migrate:rollback`**. Proceed immediately to Section 6: **Database Backup Restore Escalation Path**.

---

## 6. Database Backup Restore Escalation Path / مسار استعادة النسخة الاحتياطية

When database schema or transactional state cannot be cleanly reverted via migration rollback, restore the PostgreSQL database from the verified pre-release backup dump (`pg_dump` snapshot):

```powershell
# Step 1: Confirm maintenance mode is active and queue workers are stopped.
php artisan down

# Step 2: Terminate active PostgreSQL database connections (run by DB Operator)
# SQL: SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = 'mini_erp_production' AND pid <> pg_backend_pid();

# Step 3: Restore database snapshot from pre-release backup file
# Example using psql for SQL text dumps:
# psql -h $DB_HOST -U $DB_USERNAME -d $DB_DATABASE -f /backups/pre_cutover_backup.sql

# Example using pg_restore for custom/directory format dumps:
# pg_restore -h $DB_HOST -U $DB_USERNAME -d $DB_DATABASE --clean --if-exists /backups/pre_cutover_backup.dump

# Step 4: Verify database integrity and status
php artisan migrate:status
```

---

## 7. Queue & Scheduler Restart Notes / إعادة تشغيل المجدول والعاملين

Once code, assets, and database state are restored to the previous stable release:

```powershell
# 1. Clear stale cached queue jobs or failed job references if corrupt
php artisan cache:clear

# 2. Restart queue worker processes
php artisan queue:restart
# If using Supervisor:
# supervisorctl start erp-worker:*

# 3. Verify Artisan scheduler registration
php artisan schedule:list

# 4. Verify system health route
# GET /health -> Expect {"status":"ok","database":"ok"}

# 5. Disable maintenance mode
php artisan up
```

---

## 8. Incident Reporting & Post-Mortem Audit / التوثيق وسجل الحوادث

Following any rollback execution, the System Operator and Deployment Lead must submit a Post-Mortem Incident Report to the System Owner within 24 hours:

### Incident Report Checklist:
1. **Incident Timeline:** Exact timestamps for release initiation, failure detection, rollback approval, and service restoration.
2. **Root Cause Analysis (RCA):** Technical details of the exception, migration failure, or configuration error.
3. **Data Reconciliation Audit:** Verification that no user transactions were lost or left in an inconsistent state during the rollback window.
4. **Preventive Action Items:** Engineering and testing updates to prevent recurrence in future release cycles.
