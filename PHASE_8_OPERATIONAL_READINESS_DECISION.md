# PHASE 8 SLICE 1 OPERATIONAL READINESS DECISION PACK / حزمة قرارات الجاهزية التشغيلية

**Status:** COMPLETE & PENDING OWNER DECISIONS  
**Date:** 2026-08-24  
**Scope:** Docs-Only Operational Readiness Decision Pack  

---

## Executive Summary / الملخص التنفيذي

### English
This operational readiness decision pack provides the system owner and deployment leads with a clear structural overview of the Laravel ERP application stack, runtime service requirements, environment variable names, deployment checklists, and pending owner operational decisions. It transitions the application from a locally verified codebase to an owner-directed deployment blueprint for staging and production environments.

### العربية
تقدم حزمة قرارات الجاهزية التشغيلية هذه لمالك النظام وفريق النشر نظرة هيكلية واضحة عن البنية التكنولوجية لـ Laravel ERP، ومتطلبات خدمات التشغيل، وأسماء متغيرة البيئة، وقوائم مراجعة النشر، والقرارات التشغيلية المعلقة المعروضة على المالك. ينقل هذا المستند التطبيق من حالة التحقق البرمجي المحلي إلى مخطط نشر موجه بقرارات المالك لبيئات الاختبار والإنتاج.

---

## 1. Current Laravel Stack / البنية التكنولوجية الحالية

### English
- **Framework & Core:** Laravel 13.x (PHP 8.3+)
- **Frontend Architecture:** Inertia.js + React + TypeScript + Vanilla CSS / Tailwind
- **Primary Database:** PostgreSQL 15+ (with row locking and native JSONB support)
- **Session & Security:** Laravel Session Auth (Argon2id password hashing) + CSRF protection
- **Authorization & RBAC:** Spatie Laravel Permission (Teams disabled, non-tenant global permissions)
- **Internationalization:** Spatie Translatable for master data + Client-side JSON dictionary key translations (`en.json`, `ar.json`)
- **Audit Logging:** Spatie Activitylog (append-only audit trail)
- **Background Operations:** Laravel Queue Worker & Artisan Scheduler baseline

### العربية
- **إطار العمل والأنواة:** Laravel 13.x (PHP 8.3+)
- **بنية الواجهة الأمامية:** Inertia.js + React + TypeScript + Vanilla CSS / Tailwind
- **قاعدة البيانات الرئيسية:** PostgreSQL 15+ (مع دعم قفل الصفوف والتخزين الهيكلي JSONB)
- **الجلسات والأمان:** توثيق جلسات Laravel (تشفير كلمات المرور Argon2id) وحماية CSRF
- **الصلاحيات والأدوار:** Spatie Laravel Permission (تعطيل الفرق، صلاحيات عامة غير متعددة المستأجرين)
- **التدويل ولغات النظام:** Spatie Translatable للبيانات الأساسية + القواميس المترجمة على الواجهة (`en.json` و `ar.json`)
- **سجل التدقيق:** Spatie Activitylog (سجل تدقيق ملحق غير قابل للتعديل)
- **العمليات الخلفية:** عمال الطوابير Laravel Queue Worker ومجدول المهام Artisan Scheduler

---

## 2. Required Runtime Services / خدمات التشغيل المطلوبة

| Service / الخدمة | Purpose / الغرض | Operational Requirement / المتطلب التشغيلي |
|---|---|---|
| **Web Application Server** | Serves PHP application routes and Inertia UI. | PHP 8.3+ FPM or approved PHP application runner behind an HTTPS reverse proxy (Nginx/Caddy/Apache). |
| **Asset Delivery** | Serves compiled Inertia React JavaScript/CSS bundles. | Compiled production assets in `public/build/` via `npm run build`. |
| **PostgreSQL Database** | Persistent transactional state, accounting ledgers, and JSON audit logs. | Dedicated PostgreSQL instance with automated backups and transaction isolation. |
| **Scheduler Daemon** | Triggers recurring tasks (e.g. `tokens:gc --batch=100` hourly). | External OS cron or process manager executing `php artisan schedule:run` every minute. |
| **Queue Worker Daemon** | Processes asynchronous background tasks and queued jobs. | Process supervisor (Supervisor/systemd/Docker) running `php artisan queue:work`. |
| **File Storage** | Persists system document attachments and exports. | Local filesystem storage (`storage/app/private`) or S3-compatible object storage. |
| **Mail Gateway** | Sends transactional notifications and password resets. | SMTP mail relay or transactional API provider. |

---

## 3. Environment Configuration Guide (Variable Names Only) / دليل إعدادات البيئة

> [!IMPORTANT]
> Do not expose real environment secret values in repository files or documentation. Configure these environment variable names on the target server host.

### Application Baseline / إعدادات التطبيق الأساسية
- `APP_NAME`
- `APP_ENV` (`staging` or `production`)
- `APP_KEY`
- `APP_DEBUG` (`false` for staging/production)
- `APP_URL`
- `VITE_APP_NAME`

### Localization / التدويل واللغة
- `APP_LOCALE` (`en` or `ar`)
- `APP_FALLBACK_LOCALE` (`en`)
- `APP_FAKER_LOCALE`

### Database / قاعدة البيانات
- `DB_CONNECTION` (`pgsql`)
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

### Session, Cache, & Queue / الجلسات والتخزين المؤقت والطوابير
- `SESSION_DRIVER` (`database` or `redis`)
- `SESSION_LIFETIME`
- `SESSION_ENCRYPT`
- `CACHE_STORE` (`database` or `redis`)
- `QUEUE_CONNECTION` (`database` or `redis`)

### Storage & Filesystem / التخزين والملفات
- `FILESYSTEM_DISK` (`local` or `s3`)
- `AWS_ACCESS_KEY_ID` *(if using S3)*
- `AWS_SECRET_ACCESS_KEY` *(if using S3)*
- `AWS_DEFAULT_REGION` *(if using S3)*
- `AWS_BUCKET` *(if using S3)*

### Mail & Logging / البريد والسجلات
- `LOG_CHANNEL`
- `LOG_LEVEL` (`info` or `error` for production)
- `MAIL_MAILER` (`smtp`, `sendmail`, or `log`)
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `MAIL_ENCRYPTION`
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`

---

## 4. Pending Decisions Required From Owner / القرارات التشغيلية المعلقة لمالك النظام

The following operational decisions must be decided by the system owner/deployment manager prior to production deployment:

1. **Hosting Target / بيئة استضافة التطبيق:**
   - [ ] Virtual Private Server (VPS / EC2 / Compute Engine)
   - [ ] Containerized Orchestration (Docker / Kubernetes / ECS / Cloud Run)
   - [ ] Managed PaaS (Laravel Forge / Vapor / Ploi)

2. **PostgreSQL Database Hosting & Backups / استضافة ونسخ قاعدة البيانات:**
   - [ ] Managed PostgreSQL Service (AWS RDS / GCP Cloud SQL / DigitalOcean Managed DB)
   - [ ] Self-hosted PostgreSQL on dedicated VPS/Container
   - [ ] Backup Frequency: Daily automated snapshots with point-in-time recovery (PITR)
   - [ ] Restore Testing Frequency: Monthly verified restore drills into staging

3. **Public Domain & SSL/TLS Termination / النطاق العام وتشفير SSL:**
   - [ ] Reverse Proxy Web Server (Nginx / Caddy / Traefik) managing Let's Encrypt / Commercial SSL
   - [ ] Cloud CDN / Load Balancer (Cloudflare / AWS ALB) terminating HTTPS

4. **External Cron / Scheduler Mechanism / آلية تشغيل مجدول المهام:**
   - [ ] System Crontab (`* * * * * php /path/to/laravel/artisan schedule:run >> /dev/null 2>&1`)
   - [ ] Systemd Timer
   - [ ] External HTTP Ping / Cron Monitoring Service

5. **Queue Worker Process Manager / مدير عمليات عمال الطوابير:**
   - [ ] Supervisor process daemon (`php artisan queue:work --tries=3 --backoff=5`)
   - [ ] Systemd service manager
   - [ ] Docker Container restart policy (`restart: always`)

6. **Mail Provider Decision / مزود خدمة البريد الإلكتروني:**
   - [ ] Transactional SMTP Relay (SendGrid / Postmark / Mailgun / Amazon SES)
   - [ ] Corporate Internal SMTP Server
   - [ ] Log-only mode (staging testing)

7. **File Storage Location / موقع تخزين الملفات المستندية:**
   - [ ] Private Local Server Filesystem (`storage/app/private`)
   - [ ] Cloud Object Storage (Amazon S3 / S3-compatible Object Storage)

8. **Staging Environment Availability / توفير بيئة الاختبار الاستعدادية:**
   - [ ] Isolated Staging Instance with sanitized production schema mirror
   - [ ] Staging domain/subdomain with restricted access

9. **Browser Smoke Testing Strategy / استراتيجية الاختبارات البصرية التشغيلية:**
   - [ ] Manual Owner/QA Smoke Testing using guided test suites
   - [ ] Automated Playwright / Cypress / Laravel Dusk E2E suite in CI/CD pipeline

---

## 5. Staging vs Production Checklist / قائمة مراجعة البيئات

| Checklist Item / بند المراجعة | Staging / بيئة الاختبار | Production / البيئة الإنتاجية |
|---|---|---|
| `APP_DEBUG` | `false` | `false` |
| `APP_ENV` | `staging` | `production` |
| HTTPS SSL/TLS | Enabled | Required |
| Database Migrations | `php artisan migrate --force` | `php artisan migrate --force` (during release window) |
| Optimization Caches | `config:cache`, `route:cache`, `view:cache` | `config:cache`, `route:cache`, `view:cache` |
| Queue Worker | Active | Active with multi-worker process supervision |
| Scheduler (`schedule:run`) | Active | Active every minute |
| Backup Validation | Verified restore from Production dump | Daily automated backups + weekly offsite copies |
| Log Aggregation | Single file / Stack | Stack / Log rotation / Cloud logger |

---

## 6. Verification & Historical Classification / التحقق والتصنيف التاريخي

### Docs-Safe Verification Executed
```powershell
git diff --stat
rg -n "Next.js|Prisma|pg-boss|DATABASE_URL|PGBOSS" PHASE_8_OPERATIONAL_READINESS_DECISION.md spec/DEPLOYMENT.md README.md NEXT_TASKS.md IMPLEMENTATION_STATUS.md CONTINUE_HERE.md
```

### Classification of Historical References
- `spec/DEPLOYMENT.md`: Clarifies that old Next.js/Prisma references are historical reference only. Active runtime deployment applies to `laravel/`.
- `README.md`, `IMPLEMENTATION_STATUS.md`, `NEXT_TASKS.md`, `CONTINUE_HERE.md`: Mention historical Next.js track explicitly as superseded by the active Laravel 13 + Inertia + PostgreSQL ERP kernel.
- `PHASE_8_OPERATIONAL_READINESS_DECISION.md`: Contains zero active references to Next.js, Prisma, pg-boss, or legacy environment variable names. All deployment directives target the active Laravel stack.

---

## 7. Confirmation of Code Immutability / تأكيد عدم تغيير كود التطبيق

This slice is strictly **docs-only**. No application code, database migrations, controllers, services, routes, models, React views, or unit/feature tests were modified or added.
