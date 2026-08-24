# MINI ERP - PHASE 3 SLICE 6 GEMINI PROMPT

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


You are continuing the existing Laravel + Inertia + React Mini ERP repository.

Implement **Phase 3 Slice 6 only**.

Do not implement the whole Phase 3.

## CURRENT BASELINE

Slices already complete:

- Phase 3 Slice 1:
  - Customer
  - Supplier
  - CashAccount
  - BankAccount
  - GL/currency relationships
  - optimistic locking
  - RBAC permissions
  - Spatie Activitylog audit through `AuditLogger`
  - attachment registry entries
- Phase 3 Slice 2:
  - Customer/Supplier opening balances
  - `receivable_entry`
  - `payable_entry`
  - `accounting_account_mapping`
  - mappings for `ar_control`, `ap_control`, and `opening_balance_offset`
  - PostingEngine integration
  - subledger-to-GL reconciliation
  - DB integrity hardening
- Phase 3 Slice 3:
  - `customer_receipt`
  - `supplier_payment`
  - receipt/payment draft and post services
  - global `REC-YYYY-XXXXX` and `PAY-YYYY-XXXXX` numbering
  - PostingEngine GL effects
  - AR/AP subledger effects
  - receipt/payment unapplied tracking
  - idempotent posting
  - DB integrity hardening
- Phase 3 Slice 4:
  - `receivable_allocation`
  - `payable_allocation`
  - CustomerReceipt-to-ReceivableEntry allocation
  - SupplierPayment-to-PayableEntry allocation
  - allocation reversal
  - deterministic row locking
  - active allocation row locking
  - idempotency
  - true concurrent allocation stress command
- Phase 3 Slice 5:
  - `incoming_cheque`
  - `outgoing_cheque`
  - incoming receive/deposit/clear/bounce/return pre-clear workflows
  - outgoing issue/clear/return/cancel pre-clear workflows
  - configurable `cheques_under_collection` and `cheques_payable` mappings
  - PostingEngine GL effects
  - AR/AP subledger effects
  - idempotent cheque transitions
  - Spatie Activitylog audit
  - attachment registry entries
  - true concurrent cheque transition stress command

Read these first:

- `README.md`
- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`
- `PHASE_3_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_3_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_4_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_5_GEMINI_PROMPT.md`
- `DOMAIN_MODEL_REVIEW.md`
- `DOMAIN_RELATIONSHIP_AUDIT.md`
- `SCHEMA_ASSUMPTION_AUDIT.md`
- `docs/CONCURRENCY_AUDIT.md`

Then inspect the actual Laravel code under:

- `laravel/app`
- `laravel/config`
- `laravel/database`
- `laravel/routes`
- `laravel/resources/js`
- `laravel/tests`
- `laravel/composer.json`
- `laravel/package.json`

Do not rely on old Next.js code or generated specs when they conflict with the current Laravel implementation or owner corrections.

## OWNER DECISIONS

The ERP is not a multi-tenant SaaS.

Do not introduce:

- `company_id`
- `branch_id`
- `tenant_id`
- tenant middleware/context
- `currentCompany`
- `currentBranch`
- Company-owned users/roles/permissions
- Branch-owned users/roles/permissions
- Spatie Teams
- Company/Branch scope

If a relationship is not explicitly required, classify it as:

`UNDEFINED - DO NOT ASSUME`

Audit decision:

- Use Spatie Activitylog as the active audit backend.
- Use the existing `App\Domain\Audit\AuditLogger` API.
- Do not write new Phase 3 audit rows directly to legacy `audit_log`.
- Do not create another audit system.
- Do not add Company/Branch/Tenant audit scope.

Bank reconciliation owner-decision boundary:

- Implement ledger-backed manual bank reconciliation only.
- Do not implement bank statement file import, OCR, external bank API sync, or automatic feed parsing.
- Do not auto-create bank fee, interest, FX, or adjustment journal entries.
- Do not create a parallel bank transaction ledger.
- Do not implement cash-count reconciliation unless explicitly requested later.
- If a reconciliation requires an adjusting entry, stop and report that the user must post a normal accounting entry first.

## SLICE 6 OBJECTIVE

Implement only the Bank Reconciliation and Cash/Bank Statement foundation:

1. `BankReconciliation` header.
2. `BankReconciliationLine` statement/matching lines.
3. Matching bank statement lines to existing posted `ledger_entry` rows for the selected `BankAccount`.
4. Strict server-side lifecycle: `draft` -> `in_progress` -> `reconciled`.
5. Reconciliation summary calculation:
   - statement opening balance
   - statement movement
   - statement closing balance
   - system opening balance
   - system movement
   - system closing balance
   - matched system movement
   - difference
   - unmatched statement lines
   - unmatched system ledger entries
6. Read-only Cash Book and Bank Book query services derived from posted `ledger_entry` rows.
7. RBAC permissions for bank reconciliation actions.
8. Audit logging through `AuditLogger`.
9. Attachment registry entry for `bank_reconciliation`.
10. PostgreSQL duplicate-match/finalize stress coverage.
11. Docs/status update after successful verification.

## STRICT NON-GOALS

Do not implement:

- Sales Invoice
- Purchase Invoice
- Inventory movement
- VAT workflow
- bank statement import
- bank feed integration
- OCR parsing
- bank fee/interest automatic posting
- generic bank adjustments
- cash-count reconciliation
- cheque printing
- cheque book inventory
- post-clear cheque bounce/return
- broad customer/supplier statement pages
- aging reports
- broad Cash Book UI
- broad Bank Book UI
- broad Cheque Register UI
- broad Bank Reconciliation UI reserved for Slice 7
- full financial statements
- dashboard expansion
- generic manual AR/AP adjustments
- posted receipt reversal
- posted payment reversal
- new accounting posting engine
- new idempotency system
- new audit system

If tempted to implement any of these, stop.

## EXISTING CODE TO REUSE

Reuse current Laravel services and invariants:

- `App\Application\Accounting\PostingEngine`
- `App\Support\Concurrency\DatabaseIdempotencyStore`
- `App\Domain\Audit\AuditLogger`
- existing FinancialPeriod/FiscalYear open-period and date range rules
- existing integer minor-unit money rules
- existing `BankAccount` and `CashAccount` models
- existing `Account` and `Currency` models
- existing immutable `LedgerEntry` rows
- existing `JournalEntry`/`JournalLine` relationships
- existing Spatie Permission RBAC seed flow
- existing attachment entity registry
- existing PostgreSQL stress patterns using Laravel `Concurrency::run`

Do not create parallel ledger, journal, audit, numbering, or idempotency logic.

## SOURCE OF BANK/CASH MOVEMENTS

Cash Book and Bank Book must be derived from existing posted ledger rows:

- Cash Book source:
  - `ledger_entry.account_id = cash_account.gl_account_id`
  - join `journal_entry` for source type, source ID, number, reference, description, and status
  - include only posted/immutable ledger-derived rows
- Bank Book source:
  - `ledger_entry.account_id = bank_account.gl_account_id`
  - join `journal_entry` for source type, source ID, number, reference, description, and status
  - include only posted/immutable ledger-derived rows

Do not create a new cash/bank transaction table as an accounting source of truth.

For bank reconciliation matching:

- A statement line may match one and only one `ledger_entry`.
- A `ledger_entry` may be matched once globally.
- The selected `ledger_entry.account_id` must equal the selected `bank_account.gl_account_id`.
- The selected `ledger_entry.currency` must equal the selected `bank_account.currency`.
- The statement line signed amount must equal the ledger signed amount exactly.
- Use ERP accounting orientation:
  - debit increases a cash/bank asset account
  - credit decreases a cash/bank asset account
  - signed amount = `debit_minor - credit_minor`
- No float math.
- No exchange-rate guessing.

If multiple bank accounts point to the same GL account, do not invent new ownership or scoping. Use the selected `bank_account_id` and validate by its current `gl_account_id`.

## DATA MODEL REQUIREMENTS

Create a forward migration after the latest existing migration timestamp, for example:

`2026_08_22_000000_create_phase3_slice6_bank_reconciliation_tables.php`

Tables:

### `bank_reconciliation`

Required columns:

- `id` UUID primary key
- `bank_account_id` FK to `bank_account`, restrict delete
- `financial_period_id` FK to `financial_period`, restrict delete
- `statement_reference` nullable string
- `date_from` date
- `date_to` date
- `currency` char(3), FK to `currency(code)`, restrict delete
- `statement_opening_balance_minor` bigint
- `statement_closing_balance_minor` bigint
- `system_opening_balance_minor` bigint default 0
- `system_movement_minor` bigint default 0
- `system_closing_balance_minor` bigint default 0
- `statement_movement_minor` bigint default 0
- `matched_system_movement_minor` bigint default 0
- `difference_minor` bigint default 0
- `status` string default `draft`
- `reconciled_at` nullable timestamp
- `created_by`, `updated_by`, `reconciled_by` nullable user FKs
- `lock_version` unsigned integer default 0
- timestamps

Required checks:

- status in `draft`, `in_progress`, `reconciled`
- `date_from <= date_to`
- no `company_id`, no `branch_id`, no `tenant_id`

### `bank_reconciliation_line`

Required columns:

- `id` UUID primary key
- `bank_reconciliation_id` FK to `bank_reconciliation`, cascade delete only while not reconciled
- `line_no` unsigned integer
- `statement_date` date
- `reference` nullable string
- `description` nullable text
- `debit_minor` bigint default 0
- `credit_minor` bigint default 0
- `matched_ledger_entry_id` nullable FK to `ledger_entry`, restrict delete
- `matched_at` nullable timestamp
- `matched_by` nullable user FK
- `status` string default `unmatched`
- `lock_version` unsigned integer default 0
- timestamps

Required checks:

- status in `unmatched`, `matched`
- exactly one of debit or credit is positive
- no negative money values
- no `company_id`, no `branch_id`, no `tenant_id`

Required indexes/constraints:

- unique `(bank_reconciliation_id, line_no)`
- index `(bank_reconciliation_id, status)`
- index `matched_ledger_entry_id`
- PostgreSQL partial unique index preventing duplicate ledger matching:

```sql
UNIQUE (matched_ledger_entry_id)
WHERE matched_ledger_entry_id IS NOT NULL
```

For SQLite tests, use the closest supported equivalent.

## IMMUTABILITY RULES

Once `bank_reconciliation.status = reconciled`:

- the header must not be changed
- the header must not be deleted
- its lines must not be changed
- its lines must not be deleted
- matching/unmatching must be rejected

Implement this at service level and with DB triggers for PostgreSQL and SQLite where practical.

Do not mutate posted `journal_entry`, `journal_line`, or `ledger_entry` rows.

## SERVICE LAYER

Create service classes under `App\Application\Accounting`.

Suggested services:

- `BankBookQueryService`
- `CashBookQueryService`
- `BankReconciliationService`

Do not put business logic in controllers.

### BankBookQueryService

Responsibilities:

- Return opening balance, movements, closing balance for a BankAccount and date range.
- Source only from `ledger_entry` where `account_id = bank_account.gl_account_id`.
- Include journal metadata:
  - journal number
  - journal source_type
  - journal source_id
  - journal description
  - journal reference
  - entry date
  - debit/credit minor
  - signed movement
  - matched reconciliation status where applicable

### CashBookQueryService

Responsibilities:

- Return opening balance, movements, closing balance for a CashAccount and date range.
- Source only from `ledger_entry` where `account_id = cash_account.gl_account_id`.
- Include the same journal metadata as the bank book.

### BankReconciliationService

Required methods:

```php
createDraft(array $data, int $actorId): BankReconciliation
addLine(string $reconciliationId, array $data, int $actorId): BankReconciliationLine
updateLine(string $lineId, array $data, int $actorId): BankReconciliationLine
deleteLine(string $lineId, int $actorId): void
candidateLedgerEntries(string $reconciliationId): array
matchLine(string $lineId, string $ledgerEntryId, int $actorId, ?string $idempotencyKey = null): BankReconciliationLine
unmatchLine(string $lineId, int $actorId, ?string $idempotencyKey = null): BankReconciliationLine
summary(string $reconciliationId): array
finalize(string $reconciliationId, int $actorId, ?string $idempotencyKey = null): BankReconciliation
```

Use exact signatures if practical. If the existing code style prefers small differences, keep the same behavior.

Required behavior:

- `createDraft`:
  - validates bank account exists and is active
  - copies currency from bank account
  - validates financial period exists
  - validates `date_from/date_to` are inside the financial period date range
  - initializes computed summary fields
  - writes audit event
- `addLine`:
  - allowed only in `draft` or `in_progress`
  - validates line date is inside reconciliation date range
  - validates exactly one of debit/credit is positive
  - assigns deterministic next `line_no` under lock
  - writes audit event
- `matchLine`:
  - idempotent
  - locks reconciliation header first
  - locks line second
  - locks selected ledger entry third
  - rejects if reconciliation is `reconciled`
  - rejects if line is already matched to a different ledger entry
  - accepts replay of the same line/ledger match
  - rejects if ledger entry is already matched elsewhere
  - rejects if ledger account/currency/date/signed amount do not match
  - moves header status from `draft` to `in_progress`
  - writes audit event
- `unmatchLine`:
  - idempotent
  - allowed only before finalization
  - clears matched ledger reference
  - writes audit event
- `summary`:
  - recomputes from durable rows
  - does not trust stored totals while draft/in_progress
- `finalize`:
  - idempotent
  - locks reconciliation header
  - locks all reconciliation lines in deterministic `line_no` order
  - locks matched ledger entries in deterministic ID order
  - recomputes the summary inside the transaction
  - requires statement opening + statement movement = statement closing
  - requires all statement lines to be matched
  - requires all bank ledger entries for the selected bank account/date range to be matched by this reconciliation
  - requires difference = 0
  - stores computed summary snapshot
  - sets `status = reconciled`
  - sets `reconciled_at` and `reconciled_by`
  - writes audit event

If these strict finalization rules are too narrow for a real-world statement with outstanding items or bank-only charges, do not invent adjustment flows. Report that those workflows need an owner decision.

## RBAC

Update `config/erp_rbac.php` and RBAC seeding as needed.

Add only the minimum permissions required, such as:

- `banks.reconcile`
- existing `banks.view`
- existing `reports.view`

Do not add company-scoped or branch-scoped permissions.

## ATTACHMENTS

Register:

- `bank_reconciliation`

in `config/erp_attachments.php`.

Recommended permissions:

- view: `banks.view`
- attach: `banks.reconcile`
- delete: `banks.reconcile`

Unknown entity types must remain deny-by-default.

## ROUTES AND UI

Slice 6 is primarily backend/service foundation.

Do not build broad polished Inertia pages in this slice.

If adding routes/controllers for service coverage:

- keep them small
- validate server-side
- protect with RBAC
- do not expand the dashboard
- do not build the full Slice 7 UI

It is acceptable to defer rich Inertia pages for:

- Cash Book
- Bank Book
- Bank Reconciliation
- Reconciliation report/status

to Slice 7/8.

## TEST REQUIREMENTS

Add focused tests, for example:

- schema exists with no `company_id`, `branch_id`, or `tenant_id`
- bank reconciliation draft creation validates active bank account and period/date range
- bank/cash book services derive balances only from posted ledger entries
- statement line creation validates date range and debit/credit rules
- matching rejects ledger entries from the wrong GL account
- matching rejects currency mismatch
- matching rejects amount mismatch
- matching rejects duplicate ledger matching
- matching is idempotent for the same line/ledger/key
- unmatching clears the link before finalization
- finalization rejects unmatched statement lines
- finalization rejects unmatched bank ledger entries in the reconciliation date range
- finalization rejects non-zero statement self-check or reconciliation difference
- finalization succeeds when statement lines exactly match bank ledger entries
- finalized reconciliations and lines are immutable
- attachment registry accepts `bank_reconciliation`
- RBAC permissions are seeded
- no Spatie Teams / Company / Branch / Tenant scope is introduced

## POSTGRESQL CONCURRENCY STRESS

Create a PostgreSQL-only stress command, for example:

```text
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
```

It must use true concurrent workers through Laravel `Concurrency::run`.

Stress scenarios:

1. Duplicate match pressure:
   - create one reconciliation with two statement lines or two reconciliations with candidate lines
   - create one posted bank ledger entry candidate
   - many workers attempt to match the same ledger entry
   - assert exactly one line ends matched to that ledger entry
   - assert no duplicate match rows are possible
2. Finalize replay pressure:
   - create a valid reconciliation with all lines matched
   - many workers call finalize with the same idempotency key
   - assert one final state and no duplicate side effects
3. Match vs finalize race:
   - one worker attempts to finalize while another worker attempts a late match/unmatch
   - assert lock ordering serializes safely
   - final data is either rejected cleanly or validly reconciled

Report clear PASS/FAIL lines.

Do not fake concurrency with a sequential loop.

## DOCUMENTATION UPDATES

After implementation and verification, update:

- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`
- `README.md`
- `laravel/README.md`
- `CHANGELOG.md`
- `MD_DOCUMENTATION_AUDIT.md`
- `docs/CONCURRENCY_AUDIT.md`

Do not mark all Phase 3 complete. After Slice 6, later slices still include richer Inertia pages, statements/aging/reports, and final Phase 3 verification.

## VERIFICATION COMMANDS

Run from `laravel/` and report exact results:

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

## REQUIRED FINAL REPORT

Return a concise implementation report with:

1. Files changed.
2. New migrations.
3. Schema summary.
4. Service methods implemented.
5. Bank/Cash Book source rules.
6. Reconciliation lifecycle rules.
7. Matching/finalization concurrency strategy.
8. Removed or avoided assumptions.
9. Remaining owner decisions.
10. Exact verification command results.

Explicitly state:

```text
Slice implemented: Phase 3 Slice 6 only
Company scope introduced: NO
Branch scope introduced: NO
Tenant scope introduced: NO
Bank statement import implemented: NO
Automatic bank adjustment posting implemented: NO
Sales/Purchasing/Inventory started: NO
```
