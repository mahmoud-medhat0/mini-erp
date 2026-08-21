# IMPLEMENTATION STATUS

- **Current phase:** Phase 3 Slices 1-8 complete; all Phase 3 operational workflows, UI/actions, and operational/subledger reports are implemented and verified. Next recommended phase is Phase 3 Slice 9 (PostgreSQL stress / integrity tests).
- **Latest verified:** 2026-08-21, local Laravel + PostgreSQL after Phase 3 Slice 8 reports pass.
- **Tests passing:** Laravel PHPUnit 236 passing tests reported after Slice 8; Phase 3 Slice 8 reports suite 12/12, 180 assertions; Phase 3 Slice 7 UI suite 13/13.
- **Stress passing:** `concurrency:stress --workers=100`, `accounting:concurrency-stress --workers=50`, `accounting:allocation-concurrency-stress --workers=50`, `accounting:cheque-concurrency-stress --workers=50`, and `accounting:bank-reconciliation-concurrency-stress --workers=50`.
- **Frontend verification:** `npm run typecheck` passed (0 TS errors), `npm run build` passed.
- **Remote/CI:** No GitHub Actions pipeline is connected for the Laravel migration track.
- **Latest verified code commit:** pending for Phase 3 Slice 8 worktree.
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
| Phase 3 Slice 1 Master Data | COMPLETE | Customer, Supplier, CashAccount, BankAccount models, migrations, domain services, optimistic locking, RBAC permissions, Spatie Activitylog audit entries, and attachment entity registrations. |
| Phase 3 Slice 2 AR/AP Subledgers | COMPLETE | Customer & Supplier Opening Balances, `receivable_entry`, `payable_entry`, `accounting_account_mapping` (`ar_control`, `ap_control`, `opening_balance_offset`), PostingEngine integration, subledger-to-GL control account reconciliation, and idempotency lock safety. |
| Phase 3 Slice 3 Receipts & Payments | COMPLETE | `customer_receipt`, `supplier_payment`, draft/post flows, number allocation (`REC-YYYY-XXXXX`, `PAY-YYYY-XXXXX`), PostingEngine GL effects, subledger effects, unapplied tracking (`allocated_minor = 0`, `unapplied_minor = amount_minor`), and idempotency safety. |
| Phase 3 Slice 4 Allocation Engine | COMPLETE | `receivable_allocation`, `payable_allocation`, CustomerReceipt-to-ReceivableEntry allocations, SupplierPayment-to-PayableEntry allocations, unapplied/allocated tracking, over-allocation prevention, deterministic row locking, reversal support, and allocation concurrency stress command. |
| Phase 3 Slice 5 Cheque Lifecycle | COMPLETE | `incoming_cheque`, `outgoing_cheque`, pre-clear cheque state machines (`receive`, `deposit`, `clear`, `bounce`, `return`, `issue`, `cancel`), configurable mappings (`cheques_under_collection`, `cheques_payable`), number sequence allocation (`ICHQ-YYYY-XXXXX`, `OCHQ-YYYY-XXXXX`), PostingEngine integration, AR/AP subledger effects, owner-decision boundary for post-clear bounce/return, and cheque concurrency stress command. |
| Phase 3 Slice 6 Bank Reconciliation | COMPLETE | `bank_reconciliation`, `bank_reconciliation_line`, CashBook & BankBook query services derived from posted `ledger_entry` rows, statement line matching, draft -> in_progress -> reconciled lifecycle, summary snapshot computation, zero-difference finalization checks, DB-enforced immutable reconciled state, attachment registry entry, and PostgreSQL bank recon concurrency stress command. |
| Phase 3 Slice 7 Inertia Pages & UX | COMPLETE | 13 Controllers, 13 web route endpoints, 14 Inertia pages under `resources/js/Pages` (Customers, Suppliers, CashAccounts, BankAccounts, OpeningBalances, Receipts, Payments, Allocations, Cheques, BankReconciliations), `DatePicker.tsx` with zero emojis & RTL support, updated sidebar navigation with dropdown groups, full English/Arabic translations, and 13/13 passing UI feature tests. |
| Phase 3 Slice 8 Operational/Subledger Reports | COMPLETE | `reports.view` permission, Reports Hub, customer/supplier statements, AR/AP aging, Cash Book, Bank Book, Cheque Register, bank reconciliation status/detail, AR/AP to GL reconciliation, CSV exports, read-only report services under `App\Application\Reports`, and 12/12 report feature tests. |
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
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Result summary:

- `php artisan migrate --force`: nothing pending; applied through Phase 3 Slice 6 bank reconciliation tables and reconciliation immutability triggers; Slice 7 added UI/actions only.
- `php artisan migrate:status`: 33 migrations Ran.
- `vendor/bin/pint --test`: passed.
- `php artisan test`: 236 passing tests reported after Slice 8 implementation.
- `php artisan test --filter=Phase3Slice8ReportsTest`: 12 tests / 180 assertions passed.
- `php artisan test --testsuite=Concurrency`: 7 tests / 16 assertions passed.
- `php artisan concurrency:stress --workers=100`: passed.
- `php artisan accounting:concurrency-stress --workers=50`: passed.
- `php artisan accounting:allocation-concurrency-stress --workers=50`: passed with true concurrent AR/AP workers, 3 accepted and 47 rejected cleanly for each side.
- `php artisan accounting:cheque-concurrency-stress --workers=50`: passed with concurrent clear replay, clear-vs-bounce race protection, and outgoing clear duplicate-post prevention.
- `php artisan accounting:bank-reconciliation-concurrency-stress --workers=50`: passed with duplicate-match protection and concurrent idempotent finalization.
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
| AR/AP + Cash/Bank/Cheques | IN PROGRESS | Phase 3 Slices 1-8 are complete; Slice 9 PostgreSQL stress/integrity hardening is next. |
| Payroll, Rentals, Fixed Assets, Taxes, Projects, Budgeting | SCAFFOLD ONLY | Not started. |
| Full financial statements | NOT IMPLEMENTED | General Journal, General Ledger, and Trial Balance exist; Balance Sheet/Income Statement/Cash Flow are later work. |

## Known Issues / Residual Risks

- No GitHub Actions workflow is connected for the Laravel migration track; verification is currently local.
- Browser E2E coverage for the Laravel UI is not yet equivalent to the old Next.js Playwright smoke suite.
- Branch exact business semantics remain undefined; do not add ownership, uniqueness, or authorization scope without owner decision.
- Production scheduler execution still needs deployment wiring, e.g. external cron calling Laravel `schedule:run`.
- Legacy specs and old `app/` docs can mention tenant/company scope; treat them as historical unless they match current owner corrections.

## Next Milestone

Recommended: Phase 3 Slice 9 - PostgreSQL stress / integrity tests for the Phase 3 workflows and reports already implemented. Prepare a bounded Slice 9 prompt before implementation.
