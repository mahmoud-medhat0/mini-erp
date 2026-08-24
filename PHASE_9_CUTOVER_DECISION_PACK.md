# PHASE 9 SLICE 1 CUTOVER DECISION PACK / حزمة قرارات التحول والتشغيل الميداني

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


**Status:** COMPLETE & PENDING OWNER/OPERATOR DECISIONS  
**Date:** 2026-08-24  
**Scope:** Docs-Only Cutover Decision Pack for Staging & Production Deployment  

---

## Executive Summary / الملخص التنفيذي

### English
This Cutover Decision Pack establishes the operational framework, decision matrix, deployment responsibilities, go/no-go criteria, rollback approval process, and minimum smoke acceptance criteria for transitioning the verified Laravel ERP application to staging and production environments. It provides the owner and system operator with clear governance guidelines for controlled cutover execution.

### العربية
تقدم حزمة قرارات التحول والتشغيل هذه الإطار التشغيلي، ومصفوفة القرارات، ومسؤوليات النشر حسب الأدوار، ومعايير الموافقة/إيقاف التشغيل (Go/No-Go)، وإجراءات اعتماد التراجع (Rollback)، وأدنى معايير الفحص الأولي للجاهزية (Smoke Acceptance Criteria) لنقل نظام Laravel ERP المحقق منه إلى بيئتي الاختبار والإنتاج. وتوفر لمالك النظام ومشغله المبادئ التوجيهية للحوكمة التشغيلية لإجراء التحول المنضبط.

---

## 1. Staging vs Production Cutover Definitions / مفاهيم التحول للبيئات

### English
- **Staging Cutover:** The controlled deployment of the Laravel application to an isolated pre-production environment. Its purpose is to validate production-like web serving, PostgreSQL migrations, worker process management, environment variable parsing, background scheduler execution, and complete end-to-end user smoke testing without risking operational business data.
- **Production Cutover:** The final operational release of the verified Laravel application to the live production hosting environment. It establishes the live transactional database, initiates live background queue workers and cron schedulers, enables HTTPS domain routing, and mandates strict operational backup and monitoring protocols.

### العربية
- **التحول لبيئة الاختبار (Staging Cutover):** النشر المنضبط لتطبيق Laravel في بيئة خادمة معزولة سابقة للإنتاج. والهدف منه هو التحقق من خدمة الويب الشبيهة بالإنتاج، وتشغيل هجرات قاعدة بيانات PostgreSQL، وإدارة عمليات العمال الخفية، وقراءة متغيرات البيئة، وتنفيذ المجدول الآلي، وإجراء اختبارات الفحص الشاملة دون المخاطرة ببيانات العمل الفعلية.
- **التحول لبيئة الإنتاج (Production Cutover):** الإطلاق التشغيلي النهائي لنظام Laravel ERP المحقق منه إلى بيئة الاستضافة الحية للإنتاج. ويتضمن تشغيل قاعدة البيانات المعاملاتية الحية، وتفعيل عمال الطوابير ومجدول المهام الآلي، وتوجيه النطاق عبر HTTPS، وتطبيق بروتوكولات النسخ الاحتياطي والمراقبة التشغيلية الصارمة.

---

## 2. Current Laravel Technology Stack / البنية التكنولوجية الحالية

| Layer / الطبقة | Component / المكون | Technical Specification / المواصفات الفنية |
|---|---|---|
| **Core Framework** | Laravel Kernel | Laravel 13.x running on PHP 8.3+ |
| **Frontend Architecture** | Inertia.js + React | Inertia.js React SPA with TypeScript and Vanilla CSS / Tailwind |
| **Primary Database** | PostgreSQL | PostgreSQL 15+ (utilizing strict FKs, row locking, and JSONB audit logs) |
| **Session & Auth** | Laravel Session Auth | Argon2id password hashing, session throttling, CSRF protection |
| **RBAC & Security** | Spatie Permission | Non-tenant global roles & permissions (Spatie Teams explicitly disabled) |
| **Multilingual Support** | Spatie Translatable | Master data translation + Client-side JSON key dictionaries (`en.json`, `ar.json`) |
| **Audit Logging** | Spatie Activitylog | Immutable append-only audit trail logging system events and transactions |
| **Background Execution** | Queue & Scheduler | Laravel Queue Worker (`queue:work`) & Artisan Scheduler (`schedule:run`) |

---

## 3. Deployment Responsibilities by Role / مسؤوليات النشر حسب الأدوار

### English
- **System Owner (Owner):** Authorizes hosting targets, domain ownership, backup frequency, cutover execution windows, and final production go/no-go decisions.
- **Deployment Lead / System Operator (Operator):** Provisions server environments, configures host environment variable names, executes deployment runbooks, supervises queue workers/schedulers, and monitors application health.
- **QA / Lead Functional Tester:** Executes go-live smoke checklists, verifies localized UI workflows, tests permission boundaries, and confirms release acceptance.

### العربية
- **مالك النظام (System Owner):** يعتمد بيئات الاستضافة، وملكية النطاقات، وتكرار النسخ الاحتياطي، ونوافذ زمن التحول، ومالك قرار الموافقة/الرفض النهائي للتشغيل الحي.
- **قائد النشر / مشغل النظام (System Operator):** يجهز بيئات الخوادم، ويعد أسماء متغيرة البيئة على الخادم، وينفذ أدلة أدوات النشر، ويدير عمال الطوابير والمجدول الآلي، ويراقب صحة التطبيق.
- **فريق فحص الجودة والتجربة (QA / Functional Tester):** ينفذ قائمة مراجعة الفحص الأولي للتشغيل، ويتحقق من واجهات المستخدم المترجمة، ويختبر حدود الصلاحيات، ويعتمد جاهزية الإطلاق.

---

## 4. Pending Owner/Operator Decision Matrix / مصفوفة القرارات التشغيلية المعلقة

> [!NOTE]
> The options below require explicit selection by the System Owner and System Operator prior to staging and production deployment.

### 4.1 Hosting & Infrastructure Decisions
- **Hosting Target / بيئة استضافة التطبيق:**
  - [ ] Virtual Private Server (VPS / AWS EC2 / GCP Compute Engine)
  - [ ] Containerized Infrastructure (Docker / Kubernetes / AWS ECS / GCP Cloud Run)
  - [ ] Managed PaaS Platform (Laravel Forge / Vapor / Ploi)
- **PostgreSQL Hosting & Backup Owner / استضافة وإدارة قاعدة البيانات:**
  - [ ] Managed Cloud Database (AWS RDS / GCP Cloud SQL / DigitalOcean Managed DB)
  - [ ] Self-hosted PostgreSQL Instance on Dedicated VPS/Container
  - **Backup Ownership:** Managed by Cloud Provider automated snapshots [ ] OR Managed by System Operator scheduled `pg_dump` tasks [ ]
- **Domain & HTTPS Ownership / النطاق العام وتشفير SSL:**
  - [ ] Nginx / Caddy Reverse Proxy with automated Let's Encrypt certificates
  - [ ] Cloud CDN / Load Balancer (Cloudflare / AWS ALB) with managed SSL/TLS termination
- **Staging Environment Availability / توفير بيئة الاختبار الاستعدادية:**
  - [ ] Dedicated Staging Server Instance on isolated subdomain
  - [ ] Staging environment shared on developer testing infrastructure

### 4.2 Runtime Process & Operations Decisions
- **Scheduler Trigger Mechanism / آلية تشغيل مجدول المهام:**
  - [ ] System Crontab running `php artisan schedule:run` every minute
  - [ ] Systemd Timer Daemon
  - [ ] External HTTP Cron Pinger Service
- **Queue Worker Process Manager / مدير عمليات عمال الطوابير:**
  - [ ] Supervisor Process Daemon running `php artisan queue:work --tries=3 --backoff=5`
  - [ ] Systemd Service Manager
  - [ ] Docker Container restart policy (`restart: always`)
- **File Storage Location / موقع تخزين الملفات والمرفقات:**
  - [ ] Private Server Local Filesystem (`storage/app/private`)
  - [ ] Cloud Object Storage (Amazon S3 / S3-Compatible Storage)
- **Mail Provider & Mode / مزود البريد ونمط التشغيل:**
  - [ ] Transactional SMTP Provider (Amazon SES / SendGrid / Postmark / Mailgun)
  - [ ] Corporate Internal SMTP Relay Server
  - [ ] Log-only Mail Driver (`MAIL_MAILER=log` for Staging validation)
- **Log Retention & Review Owner / مالك الاحتفاظ بالسجلات ومراجعتها:**
  - [ ] Daily rotating file logs (`LOG_CHANNEL=daily`) with 30-day retention managed by Operator
  - [ ] Centralized Logging Service (Papertrail / Datadog / AWS CloudWatch) managed by Operator

### 4.3 Operational Governance Decisions
- **Restore Drill Frequency / تكرار اختبارات استعادة النسخ الاحتياطية:**
  - [ ] Monthly verified restore drill into staging environment
  - [ ] Quarterly verified restore drill into staging environment
- **Cutover Execution Window / النافذة الزمنية للتحول والتشغيل:**
  - [ ] Scheduled Off-Peak Maintenance Window (e.g. Weekend / Midnight)
  - [ ] Rolling Deployment during standard working hours
- **Rollback Approver / المعتمد لقرار التراجع إلغاء الإطلاق:**
  - [ ] System Owner exclusively
  - [ ] System Operator / Technical Lead delegated authority

---

## 5. Go / No-Go Criteria / معايير قرار التشغيل الحي أو الإلغاء

### English
Before executing production cutover, all mandatory criteria must be satisfied. If any mandatory criterion fails, the release status defaults to **NO-GO**.

1. **Build & Test Verification (Mandatory):** Full PHPUnit test suite, typecheck, static analysis, and Vite bundle compilation pass with 0 errors.
2. **Database Migration Status (Mandatory):** All 64 database migrations executed cleanly (`php artisan migrate:status` shows all batch numbers applied).
3. **Health Check Validation (Mandatory):** `/health` route returns HTTP 200 `{"status":"ok","database":"ok"}`.
4. **Environment Isolation (Mandatory):** `APP_DEBUG=false`, `APP_ENV=production`, valid `APP_KEY`, and HTTPS SSL active.
5. **Runtime Supervision (Mandatory):** Queue worker and scheduler processes verified active under process manager.
6. **Owner Authorization (Mandatory):** Written approval from System Owner on all pending decision items.

### العربية
قبل تنفيذ التحول الحي للإنتاج، يجب استيفاء جميع المعايير الإلزامية. وفي حالة فشل أي معيار إلزامي، تتغير حالة الإطلاق تلقائياً إلى **إلغاء التشغيل (NO-GO)**.

1. **التحقق من البناء والاختبارات (إلزامي):** نجاح كامل حزمة اختبارات PHPUnit وفحص الأنواع وتجميع ملحقات Vite بدون أي خطأ.
2. **حالة هجرات قاعدة البيانات (إلزامي):** تنفيذ كافة هجرات قاعدة البيانات الـ 64 بنجاح (`php artisan migrate:status` يظهر تطبيق جميع الدفعات).
3. **فحص السلامة والجاهزية (إلزامي):** مسار `/health` يرجع النتيجة `{"status":"ok","database":"ok"}` برمز استجابة 200.
4. **عزل وتأمين البيئة (إلزامي):** ضبط `APP_DEBUG=false` و `APP_ENV=production` وتوفر مفتاح تطبيق صحيح وتفعيل تشفير HTTPS.
5. **إشراف العمليات الخفية (إلزامي):** التأكد من فاعلية عمال الطوابير ومجدول المهام تحت مدير العمليات.
6. **اعتماد المالك (إلزامي):** موافقة كتابية من مالك النظام على جميع بنود مصفوفة القرارات التشغيلية.

---

## 6. Rollback Approval & Execution Criteria / معايير واعتماد التراجع

### English
If critical defects or operational failures occur post-cutover, a rollback is executed under the following conditions:

- **Rollback Trigger Conditions:** Database connectivity failure, severe data corruption, persistent HTTP 500 errors across primary workflows, or unresolvable queue execution deadlocks.
- **Rollback Approval Authority:** Authorized exclusively by the designated **Rollback Approver** (System Owner or Technical Lead).
- **Rollback Execution Steps:**
  1. Enable maintenance mode (`php artisan down --secret="..."`).
  2. Stop active queue worker processes (`php artisan queue:stop`).
  3. Restore database snapshot from pre-cutover backup dump.
  4. Deploy previous stable code tag / release commit.
  5. Restart web and background worker processes, verify `/health`, and disable maintenance mode (`php artisan up`).

### العربية
في حالة حدوث عيوب حرجة أو إخفاقات تشغيلية بعد التحول، يتم تنفيذ عملية التراجع وفقاً للشروط التالية:

- **دواعي التراجع:** انقطاع الاتصال بقاعدة البيانات، فساد البيانات المعاملاتية، ظهور أخطاء خادم متكررة (HTTP 500) في الوظائف الأساسية، أو استعصاء تنفيذ عمال الطوابير.
- **سلطة اعتماد التراجع:** تنحصر صلاحية القرار في **معتمد قرار التراجع** المسمى في المصفوفة (مالك النظام أو القائد الفني).
- **خطوات تنفيذ التراجع:**
  1. تفعيل نمط الصيانة (`php artisan down --secret="..."`).
  2. إيقاف عمال الطوابير النشطين (`php artisan queue:stop`).
  3. استعادة نسخة قاعدة البيانات الاحتياطية المأخوذة قبل البدء في التحول.
  4. نشر إصداره الكود السابقة المستقرة.
  5. إعادة تشغيل الويب والعمليات الخلفية، والتحقق من مسار `/health` وإلغاء نمط الصيانة (`php artisan up`).

---

## 7. Minimum Smoke Acceptance Criteria / أدنى معايير الفحص الأولي للقبول

### English
A cutover deployment is accepted as operational only after passing the following functional smoke checks:

1. **Authentication & Shell:** Successful user login, active session establishment, CSRF protection, localized navigation shell loading, and successful logout.
2. **Master Data & Settings:** Navigation to Users, Roles, Company Profile, Branches, and Chart of Accounts pages with zero JavaScript errors.
3. **Core Transactional Creation:** Successful creation and posting of an AR Customer Invoice or AP Supplier Bill with balanced GL journal generation.
4. **Reports Hub Access:** Successful view and CSV export generation for Trial Balance, General Ledger, and VAT Register reports.
5. **Background Token GC & Health:** Successful manual execution of `php artisan tokens:gc --batch=100` and verification of `/health`.

### العربية
يعتمد نشر التحول كجاهز للتشغيل فقط بعد اجتياز فحص الفحص الأولي التشغيلي التالي:

1. **التوثيق وواجهة النظام:** نجاح تسجيل دخول المستخدم، وإنشاء الجلسة النشطة، وحماية CSRF، وتحميل القوائم المترجمة، وتسجيل الخروج بنجاح.
2. **البيانات الأساسية والإعدادات:** فتح صفحات المستخدمين، والأدوار، وملف الشركة، والفروع، ودليل الحسابات بدون أي خطأ برمي.
3. **إنشاء المعاملات المالية:** نجاح إنشاء وترحيل فاتورة عملاء أو فاتورة مورد مع توليد قيد يومية متوازن في دفتر اليومية العام.
4. **مركز التقارير:** فتح تقارير ميزان المراجعة، والدفتر العام، وسجل ضريبة القيمة المضافة وتصدير ملفات CSV بنجاح.
5. **تنظيف التوكينات وصحة النظام:** نجاح التنفيذ اليدوي لأمر `php artisan tokens:gc --batch=100` والتحقق من مسار `/health`.

---

## 8. Verification & Security Scans / نتائج الفحص والتحقق الأمني

### 8.1 Sensitive Value & Credential Scan
Run docs-safe command:
```powershell
rg -n "DB_PASSWORD=|APP_KEY=base64|SECRET|TOKEN|PASSWORD|DATABASE_URL" PHASE_9_CUTOVER_DECISION_PACK.md
```
- **Scan Result:** **Controlled documentation match only**. The only match is the verification command text itself. Zero real passwords, API keys, tokens, base64 app keys, or connection strings exist in `PHASE_9_CUTOVER_DECISION_PACK.md`. All configuration references use generic variable names and placeholders.

### 8.2 Scope & Ownership Assumption Scan
Run docs-safe command:
```powershell
rg -n "company_id|branch_id|tenant_id|currentCompany|currentBranch|Spatie Teams" PHASE_9_CUTOVER_DECISION_PACK.md
```
- **Scan Result:** **Controlled documentation matches only**. Matches are limited to the verification command text and the explicit statement that Spatie Teams is disabled. Contains zero active multi-tenant scope assumptions, zero tenant context middleware, and zero `currentCompany`/`currentBranch` session keys.

---

## 9. Confirmation of Code Immutability / تأكيد عدم تغيير كود التطبيق

This slice is strictly **docs-only**. No application code, database migrations, controllers, services, routes, models, React pages, tests, or configuration behaviors were modified or created.
