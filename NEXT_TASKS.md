# NEXT TASKS - Current Laravel Track

Current status: Laravel migration through M10 plus Phase 3 Slices 1-8 is complete and verified locally on PostgreSQL.

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
- Phase 3 Slice 2 AR/AP subledger and opening balances:
  - Customer/Supplier opening balances.
  - `receivable_entry` and `payable_entry` subledger tables.
  - global accounting mappings for `ar_control`, `ap_control`, and `opening_balance_offset`.
  - PostingEngine integration, idempotent posting, subledger-to-GL reconciliation, and DB integrity hardening.
- Phase 3 Slice 3 receipt/payment posting:
  - `customer_receipt` and `supplier_payment` draft/post flows.
  - global numbering through `REC-YYYY-XXXXX` and `PAY-YYYY-XXXXX`.
  - PostingEngine integration for Cash/Bank GL vs AR/AP control effects.
  - AR/AP subledger effects and unapplied balance tracking.
  - idempotency, linked GL currency validation, FK delete restriction, and DB integrity checks.
- Phase 3 Slice 4 allocation engine:
  - `receivable_allocation` and `payable_allocation` settlement metadata.
  - CustomerReceipt-to-ReceivableEntry and SupplierPayment-to-PayableEntry allocation services.
  - unapplied/allocated balance updates without creating GL/journal/ledger rows.
  - deterministic row locking, active allocation row locking, idempotent allocation/reversal, and over-allocation prevention.
  - true concurrent AR/AP allocation stress command.
- Phase 3 Slice 5 cheque lifecycle:
  - `incoming_cheque` and `outgoing_cheque` records.
  - pre-clear state machines for incoming receive/deposit/clear/bounce/return and outgoing issue/clear/return/cancel.
  - configurable mappings for `cheques_under_collection` and `cheques_payable`.
  - PostingEngine journals/ledger effects, AR/AP subledger restoration entries, Spatie Activitylog audit, attachment registry entries, and idempotent transition commands.
  - true PostgreSQL cheque transition stress command.
- Phase 3 Slice 6 bank reconciliation:
  - `bank_reconciliation` and `bank_reconciliation_line`.
  - CashBook & BankBook query services derived from immutable posted `ledger_entry` rows.
  - manual statement line matching, lifecycle and summary rules, attachment registry integration, and DB-enforced immutability after finalization.
  - true PostgreSQL reconciliation concurrency stress command.
- Phase 3 Slice 7 Inertia pages and UX actions:
  - Customer, Supplier, CashAccount, BankAccount, opening balance, receipt/payment, allocation, cheque, and bank reconciliation controllers/routes.
  - Inertia pages for the implemented Phase 3 workflows.
  - expandable sidebar navigation groups and full English/Arabic translations.
  - custom RTL-aware `DatePicker.tsx`.
  - permission-aware actions, validation feedback, empty states, and UI feature tests.
- Phase 3 Slice 8 operational/subledger reports:
  - `reports.view` permission and protected report routes.
  - report hub plus customer/supplier statements, AR/AP aging, cash book, bank book, cheque register, bank reconciliation status/detail, and AR/AP to GL reconciliation reports.
  - dedicated report query services under `App\Application\Reports`.
  - streaming CSV exports for report downloads.
  - read-only reporting over existing durable Phase 2/Phase 3 data only.

Latest verified:

```text
php artisan test: 236 passing tests reported after Slice 8 implementation
Concurrency suite: 7 tests / 16 assertions passed
Phase 3 Slice 1 suite: 14 tests / 58 assertions passed
Phase 3 Slice 2 suite: 14 tests / 61 assertions passed
Phase 3 Slice 3 suite: 14 total / 12 passed / 2 PostgreSQL-specific skipped, 73 assertions
Phase 3 Slice 4 suite: 7 tests / 38 assertions passed
Phase 3 Slice 5 suite: 8 tests / 51 assertions passed
Phase 3 Slice 6 suite: 11 tests / 46 assertions passed
Phase 3 Slice 7 UI suite: 13 tests passed
Phase 3 Slice 8 reports suite: 12 tests / 180 assertions passed
PostgreSQL stress: concurrency + accounting + allocation + cheque + bank reconciliation stress passed
Pint: passed after formatting cleanup
TypeScript typecheck: passed
Vite build: passed
```

## Next Recommended Phase

Phase 3 Slice 9:

```text
PostgreSQL Stress / Integrity Tests
```

The corrected Phase 3 contract is:

- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`

The Slice 1 execution prompt for Gemini has already been used:

- `PHASE_3_SLICE_1_GEMINI_PROMPT.md`

Use the Phase 3 contract and current code as the source of truth for Slice 9.

The Slice 2 execution prompt for Gemini has already been used:

- `PHASE_3_SLICE_2_GEMINI_PROMPT.md`

The Slice 3 execution prompt for Gemini has already been used:

- `PHASE_3_SLICE_3_GEMINI_PROMPT.md`

The Slice 4 execution prompt for Gemini has already been used:

- `PHASE_3_SLICE_4_GEMINI_PROMPT.md`

The Slice 5 execution prompt for Gemini has already been used:

- `PHASE_3_SLICE_5_GEMINI_PROMPT.md`

The Slice 6 execution prompt for Gemini has already been used:

- `PHASE_3_SLICE_6_GEMINI_PROMPT.md`

The Slice 7 execution prompt for Gemini has already been used:

- `PHASE_3_SLICE_7_GEMINI_PROMPT.md`

The Slice 8 execution prompt for Gemini has already been used:

- `PHASE_3_SLICE_8_GEMINI_PROMPT.md`

Prepare a new bounded Slice 9 prompt before implementation.

Slice 9 should harden and verify the existing Phase 3 workflows and reports with PostgreSQL stress/integrity coverage: posting races, allocation races, cheque transition races, bank reconciliation duplicate matching/finalization, period close races, subledger-to-GL reconciliation consistency, and report read consistency. It must not start sales, purchasing, inventory, payroll, full financial statements, bank import, automatic adjustment posting, or tenant/company/branch scope.

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
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Add Phase 3-specific PostgreSQL stress coverage before marking Phase 3 complete.
