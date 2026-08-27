# POSTGRESQL BACKUP & RESTORE DRILL PACK

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


**Target Database:** PostgreSQL 15+  
**Application Target:** Laravel 13.x + Inertia.js + React  
**Scope:** Operational backup policies, disaster recovery objectives, restore drill procedures, and post-restore integrity validation.  
**Execution Note:** Commands in this pack use generic placeholders (`$DB_HOST`, `$DB_USERNAME`, `$DB_DATABASE`, `/path/to/backup.dump`). Do not execute restore commands directly against live production database instances.

---

## 1. Backup Objectives & Governance

```
 [Production DB] ──(pg_dump / PITR)──► [Encrypted Backup Storage]
                                                │
                                                ▼ (Scheduled Restore Drill)
 [Staging DB] ◄──(pg_restore / psql)────────────┘
       │
       ▼
 [Post-Restore Verification Suite (php artisan commands & /health)]
```

### Objectives:
- **Recovery Point Objective (RPO):** Maximum allowable data loss window (Target: < 24 hours for daily dumps, < 5 minutes for WAL-archived PITR).
- **Recovery Time Objective (RTO):** Maximum allowable recovery downtime (Target: < 2 hours for full database restore into staging/production).
- **Data Integrity Preservation:** Ensure full transactional consistency across accounting ledgers (`journal_entry`, `ledger_entry`), subledgers (`receivable_entry`, `payable_entry`), stock movements (`stock_movement_ledger`), and audit logs (`activity_log`).

---

## 2. Backup Frequency & Retention Policy Options

> [!NOTE]
> Backup frequency and retention options are pending final owner/operator selection in `PHASE_9_CUTOVER_DECISION_PACK.md`.

### Backup Frequency Options / خيارات تكرار النسخ الاحتياطي:
- [ ] **Option A (Daily Dumps):** Automated daily PostgreSQL logical dumps (`pg_dump`) executed during low-traffic maintenance windows.
- [ ] **Option B (Continuous WAL Archiving / PITR):** Continuous Write-Ahead Logging (WAL) archiving paired with weekly full base backups for Point-in-Time Recovery (PITR).
- [ ] **Option C (Hybrid Cloud Managed):** Automated daily cloud snapshots (e.g. AWS RDS / GCP Cloud SQL snapshots) + daily logical `pg_dump` offsite exports.

### Retention Policy Options / خيارات الاحتفاظ بالنسخ:
- [ ] **Option 1 (30-Day Retention):** Keep daily backups for 30 days, then automatically expire.
- [ ] **Option 2 (90-Day Enterprise Retention):** Keep daily backups for 30 days, weekly backups for 90 days, and monthly backups for 1 year.
- [ ] **Option 3 (7-Year Compliance Retention):** Keep daily backups for 30 days, monthly backups for 1 year, and fiscal year-end closing dumps for 7 years.

---

## 3. Restore Test Frequency Options / تكرار اختبارات الاستعادة

- [ ] **Monthly Staging Restore Drill (Recommended):** System Operator executes a full restore drill into an isolated staging host once every 30 days.
- [ ] **Quarterly Staging Restore Drill:** System Operator executes a full restore drill into an isolated staging host once every 90 days.

---

## 4. Staging Restore Drill Flow / خطوات تنفيذ فحص الاستعادة التجريبي

The restore drill validates that backup dumps can be restored into an isolated staging environment and that Laravel connects and functions cleanly.

### Step 1: Pre-Drill Isolation & Safety Check
- **Operator Requirement:** Confirm target environment is an **ISOLATED STAGING HOST**. Never execute restore commands against the production host.
- **Maintenance Isolation:** Enable maintenance mode on staging:
  ```powershell
  php artisan down --secret="STAGING_DRILL_BYPASS_TOKEN"
  ```

### Step 2: Acquire Target Backup Dump
Locate and verify the target backup dump file from secure backup storage (SQL text dump or custom directory dump format):

```powershell
# Example: Verify backup dump file existence and file size
ls -lh /path/to/backup_dumps/backup_YYYYMMDD.dump
```

### Step 3: Database Restore Execution (Placeholder Commands)
Restore the target snapshot into the staging PostgreSQL database instance:

```powershell
# Scenario A: Restoring a Custom Format Dump (.dump / .tar) using pg_restore
pg_restore -h $DB_HOST -p $DB_PORT -U $DB_USERNAME -d $DB_DATABASE --clean --if-exists --no-owner --no-acl /path/to/backup_dumps/backup_YYYYMMDD.dump

# Scenario B: Restoring a Standard SQL Text Dump (.sql) using psql
psql -h $DB_HOST -p $DB_PORT -U $DB_USERNAME -d $DB_DATABASE -f /path/to/backup_dumps/backup_YYYYMMDD.sql
```

- **Placeholder Guide:**
  - `$DB_HOST`: Hostname of the staging PostgreSQL server.
  - `$DB_PORT`: Staging PostgreSQL port (e.g. `5432` / `55432`).
  - `$DB_USERNAME`: Staging database user account name.
  - `$DB_DATABASE`: Target staging database name (e.g. `mini_erp_staging`).

---

## 5. Post-Restore Verification Suite / فحص التحقق من سلامة البيانات

After restoring the database into the staging host, execute the required 5-step post-restore verification suite:

### 1. Verify Database Migration Status
Confirm database schema matches the active application codebase:

```powershell
php artisan migrate:status
```
- **Acceptance:** All 64 migrations must display status `Ran` with zero missing migration files.

### 2. Run Operational Readiness Feature Tests
Execute the operational readiness test suite:

```powershell
php artisan test --filter=Phase8
```
- **Acceptance:** All Phase 8 operational readiness tests must pass with 0 errors.

### 3. Run Accounting Invariant & Ledger Integrity Check
Execute the deep accounting ledger balance and invariant verifier:

```powershell
php artisan accounting:phase3-integrity-check
```
- **Acceptance:** Accounting kernel integrity check must pass with 0 debit/credit discrepancies.

### 4. Execute Garbage Collection & Token Cleanup
Verify database table write permissions and scheduled task execution:

```powershell
php artisan tokens:gc --batch=100
```
- **Acceptance:** Command completes cleanly and outputs deleted token totals without database lock or permission exceptions.

### 5. Verify Laravel Connectivity & Health Endpoint
Confirm Laravel HTTP kernel connectivity:

```text
GET /health
```
- **Acceptance:** Returns `HTTP 200 OK` with JSON payload `{"status":"ok","database":"ok"}`.

---

## 6. How to Verify Laravel Connectivity After Restore

Use Artisan commands to verify application database connection parameters:

```powershell
# 1. Inspect database connection properties
php artisan db:show

# 2. Test active database connection and query response time
php artisan db:monitor
```

---

## 7. Restore Approval Protocol & Drill Log Record

### Production Restore Approval Authority / سلطة اعتماد الاستعادة للإنتاج
- **Emergency Production Restore:** Authorized exclusively by the **System Owner** following an unrecoverable system incident.
- **Staging Restore Drill:** Authorized and conducted by the **System Operator** per the scheduled drill frequency.

### Restore Drill Record Template / نموذج سجل الفحص التجريبي
Upon completion of each restore drill, the Operator must record the following log entry in `spec/BACKUP_RESTORE_DRILL.md` or the operations logging repository:

```markdown
### Restore Drill Log Entry / سجل فحص الاستعادة

- **Drill Date:** YYYY-MM-DD
- **Operator Name:** [System Operator Name]
- **Source Backup Dump ID / Timestamp:** [backup_YYYYMMDD_HHMMSS.dump]
- **Target Host:** Staging Environment [staging.example.com]
- **Start Time:** HH:MM:SS UTC
- **Completion Time:** HH:MM:SS UTC
- **Execution Duration:** XX minutes
- **Migration Status Check:** PASSED (All 64 migrations verified)
- **Phase 8 Test Suite:** PASSED (All tests green)
- **Accounting Integrity Check:** PASSED (0 ledger discrepancies)
- **Health Check (/health):** PASSED (HTTP 200 OK)
- **Drill Status:** PASSED / FAILED
- **Sign-Off:** [System Operator Signature / Approver Signature]
```

---

## 8. Safety & Policy Warnings / تحذيرات الأمان والسياسات

> [!CAUTION]
> **RESTORE DRILL SAFETY RULES:**
> 1. **Zero Production Impact:** Never run `pg_restore` or `psql` restore commands against live production database host instances during a staging drill.
> 2. **No Data Wipe Commands:** Do NOT use unqualified destructive commands such as `db:wipe`, `DROP DATABASE`, or `TRUNCATE` against live operational databases.
> 3. **No Private Secrets:** Never record real database passwords, secret keys, or production connection strings in drill log records.
