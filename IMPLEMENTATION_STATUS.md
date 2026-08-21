# IMPLEMENTATION STATUS

- **Current phase:** Phase 4 Slice 1 complete (Product/Service Catalog Foundation). Phase 4 Slice 2 Sales Order Backend is prompt-ready in `PHASE_4_SLICE_2_GEMINI_PROMPT.md`.
- **Latest verified:** 2026-08-21, local Laravel + PostgreSQL after Phase 4 Slice 1 catalog implementation.
- **Tests passing:** Laravel PHPUnit 254 passing tests (2 skipped for PostgreSQL row locking on SQLite); Phase 4 Slice 1 suite 12/12 (66 assertions).
- **Stress passing:** `concurrency:stress --workers=100`, `accounting:concurrency-stress --workers=50`, `accounting:allocation-concurrency-stress --workers=50`, `accounting:cheque-concurrency-stress --workers=50`, `accounting:bank-reconciliation-concurrency-stress --workers=50`, `accounting:phase3-integrity-check`, and `accounting:phase3-stress --workers=50`.
- **Frontend verification:** `npm run typecheck` passed (0 TS errors), `npm run build` passed.
- **Remote/CI:** No GitHub Actions pipeline is connected for the Laravel migration track.
- **Latest verified code commit:** pending for Phase 4 Slice 1 worktree.
- **Handoff:** start with `CONTINUE_HERE.md`, then `NEXT_TASKS.md`, then `PHASE_4_SLICE_2_GEMINI_PROMPT.md`.

## Legend

`COMPLETE` fully implemented + tested · `PARTIAL` partially implemented · `PLANNED` prompt/contract prepared but not implemented · `SCAFFOLD ONLY` structure without full business logic · `LEGACY_REFERENCE` old Next.js reference material only.

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
| Phase 3 Slices 1-10 Foundation | COMPLETE | Master Data, AR/AP Subledgers, Receipts/Payments, Allocation Engine, Cheques, Bank Reconciliation, Inertia Pages/UX, Operational Reports, Concurrency Stress/Integrity, Close-Out Report. |
| Phase 4 Slice 1 Catalog Foundation | COMPLETE | UnitOfMeasure, ProductCategory, Product models/migrations/services/controllers, Spatie Activitylog audit, attachment entity registry for product, Inertia catalog management pages, 12/12 passing feature tests. |
| Phase 4 Slice 2 Sales Order Backend | PLANNED | `PHASE_4_SLICE_2_GEMINI_PROMPT.md` is ready. No Sales Order implementation has started yet. |
| Phase 4 Slices 3-10 Operations | PLANNED | Purchase Orders, Goods Receipts, Delivery Notes, Invoicing & Bills, Inventory Costing/Subledger after owner decision, Returns/Credit Notes after owner decision, UX/reporting/stress close-out. |
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
php artisan test --filter=Phase4Slice1CatalogTest
php artisan test --filter=Phase3Slice9StressIntegrityTest
php artisan test --filter=Phase3Slice8ReportsTest
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan accounting:phase3-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Result summary:

- `php artisan migrate --force`: nothing pending; applied through Phase 3 Slice 6 bank reconciliation tables and reconciliation immutability triggers; Slice 7 added UI/actions only.
- `php artisan migrate:status`: 34 migration files exist after Phase 4 Slice 1; latest detailed status output should be re-run in Slice 2 verification.
- `vendor/bin/pint --test`: passed after Phase 4 Slice 1.
- `php artisan test`: 254 passing tests / 2145 assertions reported after Phase 4 Slice 1.
- `php artisan test --filter=Phase3Slice9StressIntegrityTest`: 6 tests / 262 assertions passed.
- `php artisan test --filter=Phase3Slice8ReportsTest`: 12 tests / 180 assertions passed.
- `php artisan test --testsuite=Concurrency`: 7 tests / 16 assertions passed.
- `php artisan concurrency:stress --workers=100`: passed.
- `php artisan accounting:concurrency-stress --workers=50`: passed.
- `php artisan accounting:allocation-concurrency-stress --workers=50`: passed with true concurrent AR/AP workers, 3 accepted and 47 rejected cleanly for each side.
- `php artisan accounting:cheque-concurrency-stress --workers=50`: passed with concurrent clear replay, clear-vs-bounce race protection, and outgoing clear duplicate-post prevention.
- `php artisan accounting:bank-reconciliation-concurrency-stress --workers=50`: passed with duplicate-match protection and concurrent idempotent finalization.
- `php artisan accounting:phase3-integrity-check`: passed.
- `php artisan accounting:phase3-stress --workers=50`: passed.
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
| Sales | PLANNED | Phase 4 Slice 2 prompt is ready for Sales Orders. Sales Orders/Invoicing are not implemented yet. |
| Purchasing | PLANNED | Phase 4 contract exists, but Purchase Orders/Supplier Bills are not implemented. Slice 1 starts only catalog foundation. |
| Inventory | PLANNED | Inventory valuation/stock movement requires later owner decisions; Slice 1 must not implement valuation, COGS, or stock ledgers. |
| AR/AP + Cash/Bank/Cheques | COMPLETE | Phase 3 Slices 1-10 are complete; Phase 3 AR/AP + Cash/Bank/Cheques track is fully closed out for agreed scope. |
| Payroll, Rentals, Fixed Assets, Taxes, Projects, Budgeting | SCAFFOLD ONLY | Not started. |
| Full financial statements | NOT IMPLEMENTED | General Journal, General Ledger, and Trial Balance exist; Balance Sheet/Income Statement/Cash Flow are later work. |

## Known Issues / Residual Risks

- No GitHub Actions workflow is connected for the Laravel migration track; verification is currently local.
- Browser E2E coverage for the Laravel UI is not yet equivalent to the old Next.js Playwright smoke suite.
- Branch exact business semantics remain undefined; do not add ownership, uniqueness, or authorization scope without owner decision.
- Production scheduler execution still needs deployment wiring, e.g. external cron calling Laravel `schedule:run`.
- Legacy specs and old `app/` docs can mention tenant/company scope; treat them as historical unless they match current owner corrections.

## Next Milestone

Phase 3 is 100% complete for the agreed scope, and Phase 4 Slice 1 is complete. The next prepared execution step is:

- Phase 4 Slice 2: Sales Order Backend & UX using `PHASE_4_SLICE_2_GEMINI_PROMPT.md`.

Other owner options:

- Optional: E2E Browser Testing Hardening
- Optional: Production Deployment Readiness
