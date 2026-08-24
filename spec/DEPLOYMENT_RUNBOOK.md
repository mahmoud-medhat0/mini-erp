# LARAVEL ERP DEPLOYMENT RUNBOOK

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


**Target Stack:** Laravel 13.x + Inertia.js + React + PostgreSQL 15+  
**Scope:** Provider-neutral release procedures for Staging and Production environments.  
**Execution Note:** Commands in this runbook are provider-neutral examples to be executed by a qualified System Operator in the target server host environment. Do not execute directly against un-approved environments.

---

## Overview & Deployment Principles

1. **Zero Downtime Optimization:** Asset building and composer dependency installation take place prior to enabling maintenance mode or swapping active code symlinks.
2. **Environment Isolation:** Configuration parameters are injected via environment variable names. Secrets are managed outside git tracking.
3. **Atomic Migration & Caching:** Database migrations run strictly after application code deployment, followed immediately by configuration and route cache optimization.
4. **Supervised Runtime:** Queue workers and scheduled tasks are restarted cleanly after release compilation.

---

## 12-Step Deployment Process

```
 [1. Pre-Release Checks] ──► [2. Maintenance Approval] ──► [3. Source Preparation]
            │
            ▼
 [4. Dependencies] ───────► [5. Build Assets] ─────────► [6. Validate Environment]
            │
            ▼
 [7. Run Migrations] ─────► [8. Optimize Caches] ──────► [9. Restart Workers]
            │
            ▼
 [10. Health Check] ──────► [11. Smoke Verification] ──► [12. Post-Release Monitor]
```

---

### Step 1: Pre-Release Checks / الفحص المسبق للإطلاق
Execute all verification checks in the local development / CI environment before initiating release:

```powershell
# 1. Verify clean PHPUnit test suite pass
php artisan test

# 2. Verify TypeScript type safety
npm run typecheck

# 3. Verify asset compilation
npm run build

# 4. Verify code formatting
vendor/bin/pint --test

# 5. Confirm pre-deployment database backup is complete and verified
```

- **Operator Requirement:** Release build MUST be 100% clean with 0 failing unit/feature tests and 0 TypeScript compilation errors.

---

### Step 2: Maintenance Window Approval / اعتماد نافذة الصيانة
- **Owner Action:** System Owner confirms written approval for the cutover window.
- **Operator Action:** Notify active users and enable maintenance mode on the target web server:

```powershell
# Enable maintenance mode with bypass secret
php artisan down --secret="OPERATOR_RELEASE_BYPASS_TOKEN" --retry=60 --refresh=15
```

---

### Step 3: Source / Artifact Preparation / تجهيز سورس الكود والملفات
Checkout the target release commit or pull the release tag into the host application directory (`laravel/`):

```powershell
# Fetch latest tags and checkout release tag
git fetch origin --tags
git checkout tags/v1.0.0
```

---

### Step 4: Dependency Installation / تثبيت الحزم والاعتمادات
Install production PHP dependencies and Node modules in non-interactive mode:

```powershell
# Install optimized PHP production dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# Install clean Node dependencies
npm ci
```

---

### Step 5: Asset Compilation / تجميع ملحقات الواجهة
Compile frontend React Inertia assets for production delivery:

```powershell
# Build production bundle (Vite outputs to public/build/)
npm run build
```

---

### Step 6: Environment Validation / التحقق من متغيرات البيئة
Verify that environment variable names match the production requirements outlined in `spec/ENVIRONMENT_CHECKLIST.md`:

```powershell
# Confirm active environment mode
php artisan env
# Expect: Current application environment: production

# Verify configuration parsing without exposing secrets
php artisan config:show app.debug
# Expect: false
```

- **Safety Check:** Ensure `APP_DEBUG=false` and `APP_ENV=production` (or `staging`).

---

### Step 7: Database Migration Step / تنفيذ هجرات قاعدة البيانات
Execute database schema updates within the controlled release window:

```powershell
# Run pending PostgreSQL migrations atomically
php artisan migrate --force

# Verify all migrations are applied
php artisan migrate:status
```

- **Safety Rule:** Never execute `migrate:fresh` or destructive database resets on staging or production database instances.

---

### Step 8: Cache & Configuration Optimization / تحسين مؤقت التكوين والمسارات
Compile framework caches to maximize production response speed:

```powershell
# Cache application configuration
php artisan config:cache

# Cache route registrations
php artisan route:cache

# Cache Inertia/Blade views
php artisan view:cache

# Cache event listeners
php artisan event:cache
```

---

### Step 9: Scheduler & Queue Worker Restart / إعادة تشغيل العمال والمجدول
Signal background worker processes to terminate gracefully and reload updated application code:

```powershell
# Signal queue workers to restart after completing active jobs
php artisan queue:restart

# If using Supervisor process manager:
# supervisorctl restart erp-worker:*

# Verify registered scheduled commands
php artisan schedule:list
```

---

### Step 10: Health Check Verification / التحقق من صحة النظام
Perform HTTP health request against the target host:

```text
GET /health
```

- **Expected Response:** `HTTP 200 OK`
- **Expected Payload:** `{"status":"ok","database":"ok"}`

---

### Step 11: Smoke Checks / الفحص التشغيلي الأولي
Execute the minimum operational smoke checklist using the bypass secret:

1. Log in with admin credentials via browser.
2. Navigate to Chart of Accounts, Company Profile, and User Management pages.
3. Post a test AR Customer Invoice or AP Supplier Bill with balanced GL entries.
4. Export Trial Balance CSV and VAT Register CSV report files.
5. Manually trigger expired token garbage collection:
   ```powershell
   php artisan tokens:gc --batch=100
   ```

---

### Step 12: Post-Release Monitoring & Handover / المراقبة بعد الإطلاق
Bring the application back online and initiate continuous monitoring:

```powershell
# Disable maintenance mode
php artisan up
```

- **Post-Release Checklist:**
  - Tail application log stream for unexpected exceptions (`tail -f storage/logs/laravel.log`).
  - Monitor queue worker throughput and inspect `failed_jobs` table.
  - Monitor server CPU, RAM, and PostgreSQL connection pool metrics for 60 minutes post-release.
