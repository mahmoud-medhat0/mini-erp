# NEXT TASKS - current Laravel track

Current status: Laravel migration through M10 is complete and verified locally on PostgreSQL.

Do not use the old Next.js tenant/company-scope checklist as implementation guidance. The ERP is single-installation context unless a later owner decision explicitly defines otherwise.

## Completed

- M2 Laravel/Inertia foundation.
- M3 foundation schema, global RBAC, and no-team Spatie Permission.
- M5 Laravel session auth.
- M6 migrated app shell/pages.
- M7 core kernel parity.
- Phase 2 accounting core ledger spine.
- M8 actions for migrated settings/users pages.
- M9 attachments and notifications services.
- M10 Spatie Activitylog audit backend, audit viewer, scheduler, and jobs baseline.

Latest verified:

```text
php artisan test: 145 tests / 1185 assertions passed
Concurrency suite: 7 tests / 16 assertions passed
PostgreSQL stress: concurrency + accounting stress passed
TypeScript typecheck: passed
Vite build: passed
```

## Immediate Next Recommendation - Phase 3

Phase 3 should implement AR/AP + Cash/Bank/Cheques on top of the existing accounting posting engine.

### T1. Customer And Supplier Master Data

Build:

- customer CRUD
- supplier CRUD
- status/active flags
- contact and tax fields only where requirements support them
- attachments through existing entity registry
- audit via Spatie Activitylog

Accept:

- server-side validation and permissions
- no company/branch ownership invented
- no opening-balance subledger posting unless explicitly included in Phase 3 design

### T2. Cash And Bank Accounts

Build:

- cash account setup
- bank account setup
- link each cash/bank record to a valid GL account
- prevent posting to inactive/control accounts unless a service-level rule explicitly allows it

Accept:

- all monetary values use integer minor units
- account relations are DB-enforced
- audit events are written to `activity_log`

### T3. Receipts And Payments

Build:

- customer receipt draft/post flow
- supplier payment draft/post flow
- cash/bank method selection
- balanced journal creation through the existing posting engine
- idempotency for posting
- reversal support

Accept:

- no duplicate posting under concurrency
- closed periods reject posting
- ledger rows are immutable
- source document links to journal entry

### T4. AR/AP Allocation Foundation

Build:

- allocation table/service to match receipts/payments against invoices or opening items when those source docs exist
- for now, avoid inventing full Sales/Purchasing invoices if Phase 3 does not explicitly include them
- support unapplied receipts/payments if needed

Accept:

- allocations are idempotent
- cannot over-allocate
- audit events are append-only

### T5. Cheques Lifecycle

Build:

- incoming/outgoing cheque records
- lifecycle statuses such as draft, received/issued, deposited, cleared, bounced/cancelled where requirements support them
- GL posting rules for clear/bounce events

Accept:

- status transitions validated server-side
- posting uses the same accounting invariants
- no branch/company dimensions invented

### T6. AR/AP And Cash/Bank Reports

Build:

- customer statement
- supplier statement
- cash book
- bank book
- basic AR/AP aging only if source documents exist

Accept:

- reports read posted ledger/subledger data only
- every number drills back to source where implemented

### T7. Notifications, Attachments, Audit

Extend:

- attachment entity registry for new Phase 3 entities
- notification triggers for due/cleared/bounced/payment events only where meaningful
- audit viewer filters should keep working without schema changes

Accept:

- authorization comes from entity-specific server-side rules
- notifications remain user-targeted and deduped
- no company scope added

### T8. Verification Gate

Required commands from `laravel/`:

```powershell
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

Add Phase 3-specific PostgreSQL stress tests before marking Phase 3 complete.

## Optional Hardening Before Phase 3

- Browser QA for core accounting pages.
- Production scheduler deployment note for `schedule:run`.
- Bootstrap admin production policy.
- Print/export design for accounting reports.
- Legacy `audit_log` archive/import decision.
