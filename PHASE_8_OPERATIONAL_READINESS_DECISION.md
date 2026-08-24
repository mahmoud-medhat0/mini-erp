# PHASE 8 - OPERATIONAL READINESS DECISION PACK

**Status:** OWNER / DEPLOYMENT DECISIONS REQUIRED  
**Execution Type:** Documentation and operations planning  
**ERP Context:** Single-installation Laravel ERP  

---

## Arabic Executive Summary

المرحلة 8 ليست مرحلة موديول محاسبي أو تجاري جديد. الهدف منها تجهيز النظام للتشغيل المنظم خارج بيئة التطوير المحلية.

النظام الحالي مكتمل محليا حتى Phase 7، لكن قبل staging/production نحتاج قرارات تشغيلية من مالك المشروع أو مسؤول النشر:

- أين سيتم تشغيل Laravel؟
- أين سيتم تشغيل PostgreSQL؟
- من المسؤول عن الدومين و HTTPS؟
- كيف سيتم تشغيل Laravel scheduler؟
- كيف سيتم تشغيل queue worker؟
- أين سيتم حفظ الملفات المرفوعة؟
- ما سياسة النسخ الاحتياطي وتجربة الاسترجاع؟
- هل يوجد staging environment قبل production؟
- ما الحد الأدنى المقبول لاختبارات browser smoke؟

هذه الوثيقة لا تحتوي على أي قيم بيئة فعلية. يتم ذكر أسماء الإعدادات فقط.

---

## English Executive Summary

Phase 8 is not a new ERP business module. It prepares the already verified Laravel ERP for controlled staging and production operation.

The Laravel target is locally complete through Phase 7. Deployment now needs owner/operator choices around runtime hosting, PostgreSQL hosting, scheduler execution, queue worker execution, file storage, mail mode, backups, and browser smoke acceptance.

No private environment values are recorded in this document.

---

## Current Runtime Stack

- Laravel 13.x
- PHP 8.3+
- PostgreSQL
- Inertia.js
- React + TypeScript
- Tailwind / Vite
- Laravel session auth
- Spatie Permission with teams disabled
- Spatie Activitylog
- Database-backed sessions, cache, queues, and scheduled cleanup

---

## Runtime Components

| Component | Required | Current Local Evidence |
|---|---:|---|
| Laravel web process | Yes | `laravel/routes/web.php` |
| PostgreSQL database | Yes | `.env.example` uses `DB_CONNECTION=pgsql` |
| Vite asset build | Yes | `npm run build` |
| Scheduler runner | Yes | `routes/console.php` schedules `tokens:gc --batch=100` |
| Queue worker | Yes, when async jobs are used | jobs tables exist and queue baseline is verified |
| Health endpoint | Yes | `GET /health` |
| File storage | Yes | attachments use Laravel storage |
| Mail transport | Deployment decision | local default uses log/array mode |

---

## Environment Variable Names

Application:

- `APP_NAME`
- `APP_ENV`
- `APP_KEY`
- `APP_DEBUG`
- `APP_URL`

Locale:

- `APP_LOCALE`
- `APP_FALLBACK_LOCALE`

Database:

- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

Runtime:

- `SESSION_DRIVER`
- `CACHE_STORE`
- `QUEUE_CONNECTION`
- `FILESYSTEM_DISK`

Mail and logging:

- `MAIL_MAILER`
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`
- `LOG_CHANNEL`
- `LOG_LEVEL`

Frontend:

- `VITE_APP_NAME`

No values should be copied into documentation or prompts.

---

## Owner Decisions Required

| Decision | Options / Notes | Status |
|---|---|---|
| Hosting target | VPS, managed app platform, container platform, or other approved host | Pending |
| PostgreSQL target | Managed PostgreSQL or self-managed PostgreSQL | Pending |
| Domain and HTTPS | Owner/operator must choose routing and certificate mechanism | Pending |
| Scheduler mechanism | External cron or platform scheduler running `php artisan schedule:run` every minute | Pending |
| Queue worker manager | Platform worker, service manager, or container process | Pending |
| File storage | Local persistent storage or object storage adapter | Pending |
| Mail provider | Log-only for staging or real provider for production | Pending |
| Backup frequency | Daily or stricter based on business requirement | Pending |
| Restore test frequency | Monthly, quarterly, or release-based | Pending |
| Staging environment | Required before production cutover | Pending |
| Browser smoke acceptance | Define minimum flows that must pass before release | Pending |

---

## Recommended Defaults

- Use a staging environment before production.
- Keep `APP_DEBUG=false` outside local development.
- Use PostgreSQL for staging and production.
- Run `php artisan migrate --force` during controlled releases.
- Run `php artisan schedule:run` every minute through the chosen scheduler.
- Run `php artisan queue:work --tries=3 --backoff=5` when async workers are enabled.
- Verify `GET /health` after each deployment.
- Run smoke checks for login, dashboard, reports hub, tax pages, and permission-denied behavior.

---

## Not Implemented In Phase 8

- new ERP business modules
- provider account configuration
- external filing integration
- external collection integration
- e-invoicing integration
- payroll
- rentals
- projects
- budgeting
- tenant/company/branch ownership scope

---

## Next Slice

Execute:

```text
PHASE_8_SLICE_2_GEMINI_PROMPT.md
```

Slice 2 refreshes deployment documentation for the active Laravel track.

