# Project Logic Audit

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


Date: 2026-08-21

Scope: current Laravel target after M10 Spatie Activitylog tightening. Older Next.js code and generated specs are historical unless they match owner corrections and the current Laravel implementation.

## Executive Summary

The Laravel application is now a verified migration foundation plus Phase 2 accounting ledger spine.

Implemented and verified:

- Laravel session authentication.
- Global Spatie Permission RBAC with teams disabled.
- Company profile settings and standalone Branch reference records.
- Global FiscalYear and FinancialPeriod.
- Exact integer-minor-unit Money and accounting invariants.
- Atomic number allocation by global sequence key.
- Idempotency store and PostgreSQL stress tests.
- Phase 2 accounting core: account categories/types, chart of accounts, FX rates, periods, journals, posting, immutable ledger entries, reversal, opening balances, General Journal, General Ledger, Trial Balance.
- M8 settings/user actions.
- M9 attachments and notifications services.
- M10 Spatie Activitylog active audit backend, read-only audit viewer, scheduler registration, and queue/jobs baseline.

Not implemented:

- Sales, Purchasing, Inventory, Payroll, Rentals, Fixed Assets, Projects, Budgeting, and full financial statements.
- AR/AP + Cash/Bank/Cheques are the recommended next phase.
- Laravel browser E2E parity with the old Next.js smoke suite.

## Owner Corrections Still Binding

The ERP is not currently a multi-tenant SaaS.

Confirmed removed/absent:

- `company_user`
- `users.company_id`
- `branch.company_id`
- `fiscal_year.company_id`
- `number_sequence.company_id`
- `number_sequence.include_branch`
- Company-owned roles/permissions
- Spatie teams
- `currentCompany` / `currentBranch`
- unsupported `company_id` on audit, attachment, notification

FiscalYear is `SINGLE-ERP CONTEXT`: global to this installation/business profile with globally unique `year`; FinancialPeriod belongs to FiscalYear.

Branch exact semantics remain undefined. Do not treat Branch as tenant/security boundary.

## Current Route Surface

Implemented route areas:

- Auth: login/logout.
- Locale.
- Health/foundation.
- Dashboard.
- Settings: company, branches, numbering, users/roles.
- Notifications.
- Attachments.
- Audit log viewer.
- Accounting: dashboard, chart of accounts, account categories/types, periods, FX rates, journals, journal detail/actions, ledger, trial balance, opening balances.

No routes exist for Sales, Purchasing, Inventory, Payroll, Rentals, Fixed Assets, or full financial statements.

## Domain Logic Review

### Money

Status: COMPLETE FOUNDATION.

Money uses exact minor units and currency exponent rules. No authoritative monetary path should use PHP floats.

### Currency / FX

Status: COMPLETE FOR PHASE 2.

Currency registry and DB-backed currencies exist. FX rates use exact scaled integer `rate_e6` parsing and reject invalid/over-precision rates.

### Accounting Core

Status: COMPLETE FOR PHASE 2 LEDGER SPINE.

Implemented:

- account categories/types and chart of accounts
- account control/manual-posting checks
- manual journal draft/update/submit/approve/post workflow
- period date validation
- closed-period posting rejection
- immutable ledger entries
- reversal workflow
- opening balance posting
- General Journal, General Ledger, Trial Balance
- accounting demo data seeder
- PostgreSQL accounting stress command

Out of scope:

- AR/AP documents
- Sales/Purchasing/Inventory posting
- full financial statements
- realized/unrealized FX jobs
- year-end retained earnings close

### Audit

Status: COMPLETE FOUNDATION.

Spatie Activitylog is the active audit backend.

- New writes go to `activity_log`.
- Legacy `audit_log` remains as archive.
- `AuditLogger::record(...)` preserves the old application API and routes writes through Spatie.
- Query service maps Spatie rows to old UI aliases.
- PostgreSQL/SQLite triggers prevent UPDATE/DELETE on both `activity_log` and legacy `audit_log`.

### Attachments

Status: COMPLETE FOUNDATION.

Attachment service enforces:

- entity-type allowlist
- entity existence check
- server-side permission map
- extension/MIME/size validation
- sanitized paths/names
- private storage disk
- cleanup compensation if persistence/audit fails

Future modules must register their own attachment entity authorization rules.

### Notifications

Status: COMPLETE FOUNDATION.

Notifications are user-targeted with per-user dedupe, list/unread count/mark-read/mark-all-read behavior, and role assign/revoke triggers.

## Concurrency Review

| Operation | Status | Protection |
|---|---|---|
| Number allocation | COMPLETE | PostgreSQL `INSERT ... ON CONFLICT ... DO UPDATE RETURNING` by global `key`. |
| Idempotency | COMPLETE | Unique operation/key/scope claims with replay/conflict handling. |
| Company/branch edits | COMPLETE | Optimistic `lock_version` protection. |
| Notifications | COMPLETE | Per-user dedupe key. |
| Attachments | COMPLETE FOUNDATION | Storage cleanup compensation around metadata/audit persistence. |
| Audit | COMPLETE | Append-only DB triggers on `activity_log` and legacy `audit_log`. |
| Ledger posting | COMPLETE FOR PHASE 2 | Idempotent posting, immutable ledger entries, duplicate-post/reversal protection, accounting stress command. |
| Token cleanup | COMPLETE | `tokens:gc --batch=100`, scheduled hourly with `withoutOverlapping()`. |

## Security Review

Implemented:

- Laravel session auth.
- CSRF.
- active-user login checks.
- rate limiting.
- session regeneration/invalidation.
- global RBAC.
- deny-by-default authorization.
- attachment entity authorization registry.
- audit viewer protected by `audit.view` or `settings.configure`.

Remaining production decisions:

- Bootstrap admin policy for production.
- MFA workflow, despite `mfa_enabled` field.
- Browser E2E security coverage.
- Production scheduler execution wiring.

## Test Audit

Latest full verification:

- `php artisan test`: 145 tests / 1185 assertions passed.
- `php artisan test --testsuite=Concurrency`: 7 tests / 16 assertions passed.
- `php artisan concurrency:stress --workers=100`: passed.
- `php artisan accounting:concurrency-stress --workers=50`: passed.
- `php artisan tokens:gc --batch=100`: passed.
- `npm run typecheck`: passed.
- `npm run build`: passed.

## Documentation Notes

Some `spec/` files and old `app/` docs still describe the historical Next.js architecture or future ERP behavior. They are not authoritative when they conflict with:

- owner corrections
- `DOMAIN_MODEL_REVIEW.md`
- `DOMAIN_RELATIONSHIP_AUDIT.md`
- `SCHEMA_ASSUMPTION_AUDIT.md`
- current Laravel code

## Recommended Next Work

Start Phase 3 only when requested:

- AR/AP foundations
- Cash and Bank
- Cheques lifecycle
- receipts/payments posting through the existing accounting engine
- allocation rules
- source-to-ledger drilldown
