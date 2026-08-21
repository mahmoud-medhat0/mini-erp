# NEXT TASKS - Current Laravel Track

Current status: Laravel migration through M10 plus Phase 3 Slice 1 master data is complete and verified locally on PostgreSQL.

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
- M10 audit, scheduler, and jobs baseline.
- Phase 3 Slice 1 master data:
  - Customer and Supplier models/services.
  - CashAccount and BankAccount models/services.
  - GL account and currency relationships.
  - optimistic locking, RBAC permissions, Spatie Activitylog audit, and attachment registry entries.

Latest verified:

```text
php artisan test: 159 tests / 1243 assertions passed
Concurrency suite: 7 tests / 16 assertions passed
Phase 3 Slice 1 suite: 14 tests / 58 assertions passed
PostgreSQL stress: concurrency + accounting stress passed
TypeScript typecheck: passed
Vite build: passed
```

## Next Recommended Phase

Phase 3 Slice 2:

```text
AR/AP Subledger + Customer/Supplier Opening Balances
```

The corrected Phase 3 contract is:

- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`

The Slice 1 execution prompt for Gemini has already been used:

- `PHASE_3_SLICE_1_GEMINI_PROMPT.md`

Use the Phase 3 contract and current code as the source of truth for Slice 2.

## Phase 3 Must Include

- Customer and Supplier master data.
- CashAccount and BankAccount master data linked to GL accounts.
- AR/AP subledger foundation.
- Customer/Supplier opening balances through the existing Accounting Engine.
- Receipt and Payment posting through the existing Accounting Engine.
- Allocation Engine with PostgreSQL row locking, deterministic lock ordering, and IdempotencyStore.
- Unapplied receipt/payment balances.
- Cheque lifecycle and accounting effects.
- Bank Reconciliation.
- Statements, aging where supported by available sources, Cash Book, Bank Book, Cheque Register, reconciliation reports.
- PostgreSQL concurrency and reconciliation/integrity tests.

## Phase 3 Must Not Include

- Sales Invoice.
- Purchase Invoice.
- Inventory movement.
- COGS.
- VAT workflow.
- Sales Returns.
- Purchase Returns.
- Payroll.
- Rentals.
- Fixed Assets.
- Full Financial Statements.
- Dashboard expansion.
- Company scope.
- Branch scope.
- Tenant scope.

## Phase 3 Audit Rule

For Phase 3 planning, the owner-approved audit decision is:

- use Spatie Activitylog as the active audit backend
- write through the existing `AuditLogger` API
- keep legacy `audit_log` as archive only
- do not create a second audit system
- do not add Company/Branch/Tenant audit scope

If another planning note says Phase 3 should write new rows to legacy `audit_log`, treat that wording as stale.

## Owner Decisions Needed Before/During Phase 3

Do not implement these without owner approval:

- generic manual AR/AP adjustments
- receipt/payment reversal behavior when active allocations exist
- post-clear cheque bounce/return workflow
- exact accounting mappings for AR/AP control, cheques under collection, cheques payable/outstanding, cash, and bank
- bank statement import
- aging basis when Sales/Purchasing invoices do not exist

## Verification Gate

Run from `laravel/`:

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

Add Phase 3-specific PostgreSQL stress coverage before marking Phase 3 complete.
