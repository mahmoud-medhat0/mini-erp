# IMPLEMENTATION STATUS

- **Current phase:** Laravel migration through M10 complete; next recommended product phase is Phase 3 - AR/AP + Cash/Bank/Cheques Foundation.
- **Latest verified:** 2026-08-21, local Laravel + PostgreSQL after Spatie Activitylog M10 tightening.
- **Tests passing:** Laravel PHPUnit 145/145, 1185 assertions; Concurrency suite 7/7, 16 assertions.
- **Stress passing:** `concurrency:stress --workers=100` and `accounting:concurrency-stress --workers=50`.
- **Frontend verification:** `npm run typecheck` and `npm run build` passed.
- **Remote/CI:** No GitHub Actions pipeline is connected for the Laravel migration track.
- **Latest verified code commit:** pending for the current Laravel M7-M10 / Phase 2 worktree.
- **Handoff:** start with `CONTINUE_HERE.md`, then this file, then `NEXT_TASKS.md`.

## Legend

`COMPLETE` fully implemented + tested · `PARTIAL` partially implemented · `SCAFFOLD ONLY` structure without full business logic · `LEGACY_REFERENCE` old Next.js reference material only.

## Laravel Migration Track

| Item | Status | Notes |
|---|---|---|
| M2 Inertia foundation | COMPLETE | Laravel app boots with Inertia/Vite and health/foundation routes. |
| Domain model correction | COMPLETE | Company/Branch/User ownership is not assumed; undefined relationships remain `UNDEFINED - DO NOT ASSUME`. |
| M3 database foundation | COMPLETE | Native `users`, company profile, standalone branch references, global fiscal years/periods, currency, number sequence, attachments, notifications, legacy audit archive, and non-team Spatie RBAC. |
| M5 session auth backend | COMPLETE | Login/logout, Argon2id, throttling, active users, session regeneration/invalidation, protected routes, bootstrap admin seeding. |
| M6 migrated Inertia pages | COMPLETE | Dashboard, settings hub, company, branches, numbering, users/roles, notifications, app shell, and notification read action backed by real Laravel data. |
| M7 Laravel core kernel parity | COMPLETE | Money, currency registry, accounting invariant, domain errors, number formatter/config, and Laravel invariant tests. |
| Phase 2 accounting core | COMPLETE | Account categories/types, chart of accounts, FX rates, fiscal periods, manual journals, posting engine, immutable ledger, reversal, opening balances, General Journal, General Ledger, Trial Balance, demo seeder, and accounting stress command. |
| M8 page actions | COMPLETE | Company/branch/numbering actions and role assign/revoke use explicit IDs, validation, permissions, optimistic locks where applicable, and no tenant/current-company session. |
| M9 attachments + notifications | COMPLETE | Attachment upload/download/list/delete service/routes, explicit allowlisted entity authorization, MIME/extension/size checks, storage cleanup compensation, UI panels, and user-targeted notification service with dedupe/list/mark-read behavior. |
| M10 audit + jobs/scheduler | COMPLETE | Spatie Activitylog is the active audit backend, legacy `audit_log` is retained as archive, activity/audit tables are append-only, `/audit-log` viewer exists, `tokens:gc --batch=100` is scheduled hourly, and jobs/failed_jobs baseline is verified. |
| Removed relationship assumptions | COMPLETE | `company_user`, `branch.company_id`, Company/Branch Eloquent links, `fiscal_year.company_id`, `number_sequence.company_id`, `number_sequence.include_branch`, and unsupported audit/attachment/notification `company_id` removed or absent. |
| Removed tenant assumptions | COMPLETE | Tenant context/middleware/onboarding, currentCompany/currentBranch, and Spatie `company_id` teams are removed/disabled. |
| Concurrency hardening | COMPLETE | Idempotency keys, optimistic locks, PostgreSQL number allocation, bounded token GC, notification dedupe, attachment compensation, ledger/audit immutability, and stress/test coverage. |

## Current Audit Status

| Area | Status | Notes |
|---|---|---|
| Active audit backend | COMPLETE | `spatie/laravel-activitylog` 4.12.3 writes to `activity_log`. |
| Application API | COMPLETE | `App\Domain\Audit\AuditLogger::record(...)` keeps the old signature and writes through Spatie. |
| Legacy archive | COMPLETE | Existing `audit_log` is retained and append-only; no new application writes should target it. |
| Query/UI compatibility | COMPLETE | `AuditLogQueryService` maps Spatie rows to old aliases: `actor_id`, `actor_name`, `action`, `entity_type`, `before_json`, `after_json`, `request_id`, `ip`, `device`, `at`. |
| DB immutability | COMPLETE | PostgreSQL and SQLite triggers block UPDATE/DELETE on `activity_log` and legacy `audit_log`. |

## Verification Snapshot

Last full verification from `laravel/`:

```powershell
composer install
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Result summary:

- `php artisan migrate --force`: nothing to migrate.
- `php artisan migrate:status`: 24 migrations Ran.
- `vendor/bin/pint --test`: passed.
- `php artisan test`: 145 tests / 1185 assertions passed.
- `php artisan test --testsuite=Concurrency`: 7 tests / 16 assertions passed.
- `php artisan concurrency:stress --workers=100`: passed.
- `php artisan accounting:concurrency-stress --workers=50`: passed.
- `php artisan tokens:gc --batch=100`: passed.
- `npm run typecheck`: passed.
- `npm run build`: passed.

## Module Status

| Module / Area | Status | Notes |
|---|---|---|
| Accounting ledger spine | COMPLETE | Phase 2 accounting core is implemented as the current ledger backbone. |
| Settings | COMPLETE | Company profile, standalone branch references, numbering, users/roles. |
| Notifications | COMPLETE FOUNDATION | User-targeted notifications and read actions exist; future modules must add their own event triggers. |
| Attachments | COMPLETE FOUNDATION | Entity registry + service exists; future entities must register authorization rules. |
| Audit | COMPLETE FOUNDATION | Spatie Activitylog active with read-only viewer and append-only enforcement. |
| Sales | SCAFFOLD ONLY | Not started. |
| Purchasing | SCAFFOLD ONLY | Not started. |
| Inventory | SCAFFOLD ONLY | Not started. |
| AR/AP + Cash/Bank/Cheques | PLANNED NEXT | Recommended Phase 3. |
| Payroll, Rentals, Fixed Assets, Taxes, Projects, Budgeting | SCAFFOLD ONLY | Not started. |
| Full financial statements | NOT IMPLEMENTED | General Journal, General Ledger, and Trial Balance exist; Balance Sheet/Income Statement/Cash Flow are later work. |

## Known Issues / Residual Risks

- No GitHub Actions workflow is connected for the Laravel migration track; verification is currently local.
- Browser E2E coverage for the Laravel UI is not yet equivalent to the old Next.js Playwright smoke suite.
- Branch exact business semantics remain undefined; do not add ownership, uniqueness, or authorization scope without owner decision.
- Production scheduler execution still needs deployment wiring, e.g. external cron calling Laravel `schedule:run`.
- Legacy specs and old `app/` docs can mention tenant/company scope; treat them as historical unless they match current owner corrections.

## Next Milestone

Recommended: Phase 3 - AR/AP + Cash/Bank/Cheques Foundation. See `NEXT_TASKS.md` and `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`.
