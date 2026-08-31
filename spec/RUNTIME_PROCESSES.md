# LARAVEL ERP RUNTIME PROCESSES, STORAGE, MAIL, AND LOGS OPERATIONS

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


**Target Application:** Laravel 13.x + Inertia.js + React + PostgreSQL 15+  
**Scope:** Provider-neutral operational guide for background processes, scheduler, queue workers, attachment storage, email delivery, logging, and health monitoring.  
**Execution Note:** Use generic configuration templates and placeholders only (`/path/to/laravel`, `$APP_USER`). Do not store provider secrets or execute commands on un-approved production hosts.

---

## 1. Artisan Scheduler Operations & External Trigger

```
 [OS Cron / Systemd Timer] ──(every 1 min)──► [php artisan schedule:run]
                                                      │
                                                      ▼
                                       ┌──────────────────────────────┐
                                       │ tokens:gc --batch=100        │
                                       │ (Hourly, without overlapping)│
                                       └──────────────────────────────┘
```

### Purpose:
The Laravel Artisan Scheduler executes recurring console tasks registered in `laravel/routes/console.php`. In this ERP kernel, the primary scheduled task is the bounded garbage collector for expired authentication and idempotency tokens (`tokens:gc --batch=100`).

### External Trigger Requirement:
Laravel's scheduler cannot run itself continuously; it depends on an external OS cron job or systemd timer executing every minute to trigger pending tasks.

#### Cron Configuration Example (Placeholder Path):
```cron
# Edit crontab for application service user (e.g. crontab -u www-data -e)
* * * * * cd /path/to/laravel && php artisan schedule:run >> /dev/null 2>&1
```

#### Scheduled Command Registry Verification:
To verify scheduled task registration and upcoming execution times:
```powershell
php artisan schedule:list
php artisan ops:go-live-readiness --target=staging --strict
```

- **Expected Scheduled Tasks:**
  - `tokens:gc --batch=100`: Scheduled hourly, configured with `withoutOverlapping()`.

---

## 2. Queue Worker Operations & Process Supervision

### Purpose:
Queue workers process asynchronous background tasks (such as notification delivery, attachment cleanup, and heavy report background jobs) dispatched to the database queue (`QUEUE_CONNECTION=database`).

### Supervised Worker Command:
Run the worker with explicit failure bounds and backoff retry timeouts:
```powershell
php artisan queue:work --tries=3 --backoff=5 --timeout=90
```

### Process Supervision Templates (Placeholders Only):

#### Scenario A: Supervisor Daemon Configuration Template (`/etc/supervisor/conf.d/erp-worker.conf`)
```ini
[program:erp-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/laravel/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/laravel/storage/logs/worker.log
stopwaitsecs=3600
```

#### Scenario B: Systemd Unit File Template (`/etc/systemd/system/erp-worker.service`)
```ini
[Unit]
Description=Laravel ERP Queue Worker Process
After=network.target postgresql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/path/to/laravel
ExecStart=/usr/bin/php /path/to/laravel/artisan queue:work database --tries=3 --backoff=5
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

### Worker Restart Policy Post-Release:
When code or configuration is updated on the server, queue workers MUST be restarted so they drop stale in-memory PHP classes and reload the new code bundle:
```powershell
# Signal running workers to complete current job and exit gracefully
php artisan queue:restart

# If using Supervisor:
# supervisorctl restart erp-worker:*
```

---

## 3. Failed Job Review & Recovery Process

Failed queue jobs are automatically written to the PostgreSQL `failed_jobs` table when maximum retry attempts (`--tries=3`) are exhausted.

### Operator Inspection & Retry Commands:

```powershell
# 1. List failed jobs with ID, connection, queue, and failure timestamp
php artisan queue:failed
php artisan ops:go-live-readiness --target=staging --strict

# 2. Retry a specific failed job by UUID / ID
php artisan queue:retry <job_uuid>

# 3. Retry all failed jobs
php artisan queue:retry all

# 4. Delete a specific failed job
php artisan queue:forget <job_uuid>

# 5. Flush all failed jobs from table
php artisan queue:flush
```

Production caution: `queue:flush` removes all failed job records and should only be used after the operator has recorded the failure IDs, root cause notes, and owner/operator approval for clearing diagnostic history.

---

## 4. Token & Session Garbage Collection (`tokens:gc`)

### Purpose & Scope:
The `tokens:gc` command deletes expired records in bounded batches to prevent database bloat without holding long table locks:

- **Expired Web Sessions:** Rows in `sessions` table where `last_activity < (now - SESSION_LIFETIME)`.
- **Expired Password Reset Tokens:** Rows in `password_reset_tokens` table created older than expiration limit.
- **Expired Idempotency Keys:** Rows in `idempotency_keys` table where `expires_at < now()`.

### Manual Operator Execution:
```powershell
# Execute garbage collection manually with a batch size of 100 rows per table
php artisan tokens:gc --batch=100
```

---

## 5. Attachment Storage Operations

### Filesystem Configuration (`FILESYSTEM_DISK`):
- **Local Disk Mode (`FILESYSTEM_DISK=local`):**
  - Storage path: `storage/app/private/`
  - Host Requirement: Web server application user (`www-data`) must have read/write permissions on `storage/app/private/`.
  - Security Requirement: keep `FILESYSTEM_LOCAL_SERVE=false` so framework `/storage/*` routes are not registered for the private disk.
  - Validation: run `php artisan route:list` and confirm no direct `storage.local` route exists.
- **Cloud Object Storage Mode (`FILESYSTEM_DISK=s3`):**
  - Configured via environment variable names: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`.
  - Bucket Privacy: S3 bucket MUST be configured as private. File downloads are delivered strictly through application-authenticated controller routes using `AttachmentService`.

---

## 6. Mail Delivery Operations

### Delivery Modes (`MAIL_MAILER`):
- **Log Transport Mode (`MAIL_MAILER=log`):**
  - Writes outgoing mail content to `storage/logs/laravel.log`. Ideal for Staging environments.
- **SMTP Transport Mode (`MAIL_MAILER=smtp`):**
  - Sends transactional mail via external SMTP relay host.
  - Requires: `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`.
- **Amazon SES Mode (`MAIL_MAILER=ses`):**
  - Sends transactional mail via AWS SES API using AWS SDK credentials.

### Sender Identity Verification:
Ensure `MAIL_FROM_ADDRESS` matches domain SPF, DKIM, and DMARC DNS authorizations to prevent email delivery blockage.

---

## 7. Logging & Observability

### Log Configuration (`LOG_CHANNEL` & `LOG_LEVEL`):
- Log output path: `storage/logs/laravel.log`.
- **Staging / Local Mode:** `LOG_CHANNEL=stack`, `LOG_STACK=single`, `LOG_LEVEL=debug`.
- **Production Mode (Recommended):** `LOG_CHANNEL=daily` (automatically rotates daily files), `LOG_LEVEL=info` or `LOG_LEVEL=warning` to prevent storage disk fill-up.

### Log Review Operator Commands:
```powershell
# Tail live application log output
tail -f storage/logs/laravel.log

# Search application logs for unhandled errors
grep -i "error\|exception" storage/logs/laravel.log
```

---

## 8. Application Health Endpoint & Monitoring

The health check endpoint provides a lightweight, automated check of web application responsiveness and PostgreSQL database connectivity.

```text
GET /health
```

- **Healthy Response:** `HTTP 200 OK`
  ```json
  {
    "status": "ok",
    "database": "ok"
  }
  ```
- **Unhealthy / Degraded Response:** `HTTP 503 Service Unavailable`
  ```json
  {
    "status": "degraded",
    "database": "unavailable"
  }
  ```

---

## 9. Operator Checklist Post-Server Restart / Release

Whenever the application server or runtime services are restarted, the System Operator must complete this 5-point verification checklist:

1. [ ] **Health Endpoint:** Confirm `GET /health` returns `HTTP 200` with `{"status":"ok","database":"ok"}`.
2. [ ] **Scheduler Registration:** Confirm `php artisan schedule:list` displays active scheduled tasks.
3. [ ] **Queue Worker Supervision:** Confirm queue worker processes are running under Supervisor/systemd (`supervisorctl status`).
4. [ ] **Log Stream:** Inspect `storage/logs/laravel.log` to confirm no startup crashes or connection failures occurred.
5. [ ] **Garbage Collection Execution:** Run `php artisan tokens:gc --batch=100` to verify DB write capability.
