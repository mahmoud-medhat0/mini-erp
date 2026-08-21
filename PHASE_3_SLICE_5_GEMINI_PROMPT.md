# MINI ERP - PHASE 3 SLICE 5 GEMINI PROMPT

You are continuing the existing Laravel + Inertia + React Mini ERP repository.

Implement **Phase 3 Slice 5 only**.

Do not implement the whole Phase 3.

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

Cheque owner-decision boundary:

- Implement pre-clear cheque lifecycle only.
- Do not implement `cleared -> bounced`, `cleared -> returned`, or any post-clear reversal workflow.
- If a post-clear bounce/return is requested, stop and report `OWNER DECISION REQUIRED`.

## SLICE 5 OBJECTIVE

Implement only the Cheque Lifecycle foundation:

1. Incoming customer cheque records.
2. Outgoing supplier cheque records.
3. Explicit server-side state machines.
4. Posting through the existing Accounting PostingEngine only.
5. Cheques Under Collection and Cheques Payable/Outstanding configurable account mappings.
6. AR/AP subledger effects where cheque state transitions affect customer/supplier balances.
7. Idempotent and concurrency-safe cheque transitions.
8. Audit logging through `AuditLogger`.
9. Attachment registry additions for cheque entities if appropriate.
10. PostgreSQL transition stress coverage.
11. Docs/status update after successful verification.

Cheque posting must not behave as immediate Cash/Bank settlement before clearing.

## STRICT NON-GOALS

Do not implement:

- Sales Invoice
- Purchase Invoice
- Inventory movement
- VAT workflow
- cheque printing
- cheque book inventory
- bank statement import
- bank reconciliation
- customer/supplier statement pages
- aging reports
- cash book
- bank book
- broad Cheque Register UI reserved for Slice 7
- full financial statements
- dashboard expansion
- generic manual AR/AP adjustments
- posted receipt reversal
- posted payment reversal
- post-clear cheque bounce/return
- new accounting posting engine
- new idempotency system
- new audit system

If tempted to implement any of these, stop.

## EXISTING CODE TO REUSE

Reuse current Laravel services and invariants:

- `App\Application\Accounting\PostingEngine`
- `App\Application\Accounting\AccountingAccountMappingService`
- `App\Application\Accounting\ArApSubledgerService`
- `App\Support\Concurrency\DatabaseIdempotencyStore`
- `App\Domain\Audit\AuditLogger`
- existing FinancialPeriod/FiscalYear open-period and date range rules
- existing integer minor-unit money rules
- existing Customer/Supplier models
- existing CashAccount/BankAccount models and trusted GL links
- existing ReceivableEntry and PayableEntry models
- existing Spatie Permission RBAC seed flow
- existing attachment entity registry
- existing PostgreSQL stress patterns using Laravel `Concurrency::run`

Do not create parallel ledger, journal, audit, numbering, or idempotency logic.

## ACCOUNTING MAPPINGS

Extend the existing accounting mapping service only as needed.

Required configurable mapping keys:

- `cheques_under_collection`
- `cheques_payable`

Validation:

- `cheques_under_collection` must point to an active Asset account with debit nature.
- `cheques_payable` must point to an active Liability account with credit nature.

Continue using existing:

- `ar_control`
- `ap_control`

Bank GL account must come from the selected `bank_account.gl_account_id`.

Do not hardcode account IDs.

Do not let browser/user input override trusted mapped GL account IDs.

Do not add Company/Branch/Tenant dimensions to mappings.

## CHEQUE NUMBERING

Distinguish between:

- system document number
- physical cheque number

Physical cheque number is user-entered and must not be generated by the ERP.

If a system document number is needed, use global number sequence keys only:

- `incoming_cheque`
- `outgoing_cheque`

Suggested formatting:

- `ICHQ-YYYY-00001`
- `OCHQ-YYYY-00001`

Do not add company/branch dimensions to cheque numbering.

Do not create a new numbering engine.

## ACCOUNTING BEHAVIOR

### Incoming Customer Cheque

Receiving a customer cheque:

```text
Dr Cheques Under Collection
Cr Customer AR Control
```

Required effects:

- create approved JournalEntry
- create JournalLine rows
- post through PostingEngine
- create one ReceivableEntry credit linked to the cheque source
- set cheque status to `received`

Depositing a customer cheque:

- optional custody/status transition only
- no GL posting unless the current implementation has an explicit accounting reason
- do not debit Bank merely because a cheque was deposited

Clearing a customer cheque:

```text
Dr Bank GL Account
Cr Cheques Under Collection
```

Required effects:

- create approved JournalEntry
- create JournalLine rows
- post through PostingEngine
- set cheque status to `cleared`
- do not create another ReceivableEntry

Pre-clear bounce or return of a customer cheque:

```text
Dr Customer AR Control
Cr Cheques Under Collection
```

Required effects:

- create approved JournalEntry
- create JournalLine rows
- post through PostingEngine
- create one ReceivableEntry debit linked to the cheque source
- set cheque status to `bounced` or `returned`

Do not implement post-clear bounce/return.

### Outgoing Supplier Cheque

Issuing a supplier cheque:

```text
Dr Supplier AP Control
Cr Cheques Payable / Outstanding Cheques
```

Required effects:

- create approved JournalEntry
- create JournalLine rows
- post through PostingEngine
- create one PayableEntry debit linked to the cheque source
- set cheque status to `issued`

Clearing a supplier cheque:

```text
Dr Cheques Payable / Outstanding Cheques
Cr Bank GL Account
```

Required effects:

- create approved JournalEntry
- create JournalLine rows
- post through PostingEngine
- set cheque status to `cleared`
- do not create another PayableEntry

Pre-clear cancellation or return of a supplier cheque:

```text
Dr Cheques Payable / Outstanding Cheques
Cr Supplier AP Control
```

Required effects:

- create approved JournalEntry
- create JournalLine rows
- post through PostingEngine
- create one PayableEntry credit linked to the cheque source
- set cheque status to `cancelled` or `returned`

Do not implement post-clear cancellation/return.

## EXPECTED TABLES

Use singular table names consistent with current Laravel schema style.

Expected new tables:

- `incoming_cheque`
- `outgoing_cheque`

No new table may contain:

- `company_id`
- `branch_id`
- `tenant_id`

Do not create bank reconciliation tables in this slice.

Do not create sales/purchase invoice tables in this slice.

## INCOMING CHEQUE TABLE

Minimum fields:

- `id` uuid primary key
- `number` nullable string unique, internal ERP number allocated on receive/post
- `customer_id` uuid FK to `customer.id`
- `cheque_number` string, physical cheque number from the customer/bank
- `drawer_bank_name` nullable string
- `received_fiscal_year_id` nullable uuid FK to `fiscal_year.id`
- `received_financial_period_id` nullable uuid FK to `financial_period.id`
- `received_date` nullable date
- `deposited_date` nullable date
- `deposit_bank_account_id` nullable uuid FK to `bank_account.id`
- `cleared_fiscal_year_id` nullable uuid FK to `fiscal_year.id`
- `cleared_financial_period_id` nullable uuid FK to `financial_period.id`
- `cleared_date` nullable date
- `returned_fiscal_year_id` nullable uuid FK to `fiscal_year.id`
- `returned_financial_period_id` nullable uuid FK to `financial_period.id`
- `returned_date` nullable date
- `bounced_fiscal_year_id` nullable uuid FK to `fiscal_year.id`
- `bounced_financial_period_id` nullable uuid FK to `financial_period.id`
- `bounced_date` nullable date
- `currency` string(3), FK to `currency.code`
- `amount_minor` bigint positive
- `fx_rate_e6` bigint default `1000000`
- `status` string default `draft`
- `receive_journal_entry_id` nullable uuid FK to `journal_entry.id`
- `clear_journal_entry_id` nullable uuid FK to `journal_entry.id`
- `return_journal_entry_id` nullable uuid FK to `journal_entry.id`
- `bounce_journal_entry_id` nullable uuid FK to `journal_entry.id`
- `receivable_entry_id` nullable uuid FK to `receivable_entry.id`
- `return_receivable_entry_id` nullable uuid FK to `receivable_entry.id`
- `bounce_receivable_entry_id` nullable uuid FK to `receivable_entry.id`
- `reference` nullable string
- `description` nullable text
- `created_by`, `updated_by`, `received_by`, `deposited_by`, `cleared_by`, `returned_by`, `bounced_by`, `cancelled_by` nullable FK to users
- `cancelled_at` nullable timestamp
- `lock_version` unsigned integer default 0
- timestamps

Allowed statuses:

- `draft`
- `received`
- `deposited`
- `cleared`
- `bounced`
- `returned`
- `cancelled`

Foreign key deletes must be `restrict` for business/financial references and `nullOnDelete` only for user provenance fields.

PostgreSQL checks:

- amount is positive
- `fx_rate_e6 > 0`
- status is one of the allowed statuses
- no Company/Branch/Tenant columns exist

Do not add uniqueness constraints on physical `cheque_number` unless an explicit owner requirement exists.

## OUTGOING CHEQUE TABLE

Minimum fields:

- `id` uuid primary key
- `number` nullable string unique, internal ERP number allocated on issue/post
- `supplier_id` uuid FK to `supplier.id`
- `bank_account_id` uuid FK to `bank_account.id`
- `cheque_number` string, physical cheque number from the cheque book
- `payee_name` nullable string
- `issued_fiscal_year_id` nullable uuid FK to `fiscal_year.id`
- `issued_financial_period_id` nullable uuid FK to `financial_period.id`
- `issued_date` nullable date
- `cleared_fiscal_year_id` nullable uuid FK to `fiscal_year.id`
- `cleared_financial_period_id` nullable uuid FK to `financial_period.id`
- `cleared_date` nullable date
- `returned_fiscal_year_id` nullable uuid FK to `fiscal_year.id`
- `returned_financial_period_id` nullable uuid FK to `financial_period.id`
- `returned_date` nullable date
- `cancelled_fiscal_year_id` nullable uuid FK to `fiscal_year.id`
- `cancelled_financial_period_id` nullable uuid FK to `financial_period.id`
- `cancelled_date` nullable date
- `currency` string(3), FK to `currency.code`
- `amount_minor` bigint positive
- `fx_rate_e6` bigint default `1000000`
- `status` string default `draft`
- `issue_journal_entry_id` nullable uuid FK to `journal_entry.id`
- `clear_journal_entry_id` nullable uuid FK to `journal_entry.id`
- `return_journal_entry_id` nullable uuid FK to `journal_entry.id`
- `cancel_journal_entry_id` nullable uuid FK to `journal_entry.id`
- `payable_entry_id` nullable uuid FK to `payable_entry.id`
- `return_payable_entry_id` nullable uuid FK to `payable_entry.id`
- `cancel_payable_entry_id` nullable uuid FK to `payable_entry.id`
- `reference` nullable string
- `description` nullable text
- `created_by`, `updated_by`, `issued_by`, `cleared_by`, `returned_by`, `cancelled_by` nullable FK to users
- `lock_version` unsigned integer default 0
- timestamps

Allowed statuses:

- `draft`
- `issued`
- `cleared`
- `returned`
- `cancelled`

Foreign key deletes must be `restrict` for business/financial references and `nullOnDelete` only for user provenance fields.

PostgreSQL checks:

- amount is positive
- `fx_rate_e6 > 0`
- status is one of the allowed statuses
- no Company/Branch/Tenant columns exist

Do not add uniqueness constraints on physical `cheque_number` unless an explicit owner requirement exists.

## STATE MACHINE

All transitions must be enforced server-side.

Incoming cheque allowed transitions:

```text
draft -> received
draft -> cancelled
received -> deposited
received -> cleared
received -> bounced
received -> returned
deposited -> cleared
deposited -> bounced
deposited -> returned
```

Incoming cheque forbidden transitions:

```text
cleared -> bounced
cleared -> returned
cleared -> cancelled
bounced -> any posting state
returned -> any posting state
cancelled -> any posting state
```

Outgoing cheque allowed transitions:

```text
draft -> issued
draft -> cancelled
issued -> cleared
issued -> returned
issued -> cancelled
```

Outgoing cheque forbidden transitions:

```text
cleared -> returned
cleared -> cancelled
returned -> any posting state
cancelled -> any posting state
```

Invalid transitions must throw validation/domain exceptions and must not create journal, ledger, or subledger rows.

## SERVICE API

Implement service layer first.

Suggested services:

- `App\Application\Accounting\IncomingChequeService`
- `App\Application\Accounting\OutgoingChequeService`

Suggested methods:

```php
createDraft(array $data, int $actorId): IncomingCheque
receive(string $chequeId, string $fiscalYearId, string $financialPeriodId, string $receivedDate, int $actorId, ?string $idempotencyKey = null): IncomingCheque
deposit(string $chequeId, string $bankAccountId, string $depositedDate, int $actorId): IncomingCheque
clear(string $chequeId, string $fiscalYearId, string $financialPeriodId, string $clearedDate, string $bankAccountId, int $actorId, ?string $idempotencyKey = null): IncomingCheque
bounceBeforeClear(string $chequeId, string $fiscalYearId, string $financialPeriodId, string $bouncedDate, string $reason, int $actorId, ?string $idempotencyKey = null): IncomingCheque
returnBeforeClear(string $chequeId, string $fiscalYearId, string $financialPeriodId, string $returnedDate, string $reason, int $actorId, ?string $idempotencyKey = null): IncomingCheque
cancelDraft(string $chequeId, string $reason, int $actorId): IncomingCheque
```

```php
createDraft(array $data, int $actorId): OutgoingCheque
issue(string $chequeId, string $fiscalYearId, string $financialPeriodId, string $issuedDate, int $actorId, ?string $idempotencyKey = null): OutgoingCheque
clear(string $chequeId, string $fiscalYearId, string $financialPeriodId, string $clearedDate, int $actorId, ?string $idempotencyKey = null): OutgoingCheque
returnBeforeClear(string $chequeId, string $fiscalYearId, string $financialPeriodId, string $returnedDate, string $reason, int $actorId, ?string $idempotencyKey = null): OutgoingCheque
cancelBeforeClear(string $chequeId, string $fiscalYearId, string $financialPeriodId, string $cancelledDate, string $reason, int $actorId, ?string $idempotencyKey = null): OutgoingCheque
cancelDraft(string $chequeId, string $reason, int $actorId): OutgoingCheque
```

If method names differ, keep the same behavioral surface and document it.

## POSTING RULES

Every accounting transition must:

1. Use `DatabaseIdempotencyStore`.
2. Start a DB transaction.
3. Lock the cheque row with `lockForUpdate()`.
4. Recheck state after lock.
5. Lock/recheck the FinancialPeriod is open.
6. Validate event date is inside the selected FinancialPeriod.
7. Resolve trusted mapped accounts.
8. Resolve trusted BankAccount GL account where clearing requires bank.
9. Validate all accounts are active and currency-compatible.
10. Create an approved JournalEntry with source type:
    - `incoming_cheque`
    - `outgoing_cheque`
11. Create balanced JournalLine rows.
12. Post through `PostingEngine` with explicit trusted control-account allowance where needed.
13. Create AR/AP subledger entries where the transition changes AR/AP.
14. Update cheque status and provenance fields.
15. Audit through `AuditLogger`.

Do not mutate or delete posted journal/ledger entries.

Corrections must use reversal/new posting.

## LOCK ORDER

Use deterministic lock order for every transition:

Incoming receive:

1. cheque row
2. financial period row
3. customer row if needed
4. `cheques_under_collection` mapped account
5. `ar_control` mapped account

Incoming clear:

1. cheque row
2. financial period row
3. bank account row
4. bank GL account
5. `cheques_under_collection` mapped account

Incoming bounce/return before clear:

1. cheque row
2. financial period row
3. `ar_control` mapped account
4. `cheques_under_collection` mapped account

Outgoing issue:

1. cheque row
2. financial period row
3. supplier row if needed
4. `ap_control` mapped account
5. `cheques_payable` mapped account

Outgoing clear:

1. cheque row
2. financial period row
3. bank account row
4. bank GL account
5. `cheques_payable` mapped account

Outgoing return/cancel before clear:

1. cheque row
2. financial period row
3. `cheques_payable` mapped account
4. `ap_control` mapped account

If implementation requires additional locks, document the order and keep it deterministic.

## IDEMPOTENCY

Use the existing `DatabaseIdempotencyStore`.

Do not create a new idempotency system.

Suggested operation names:

- `incoming_cheque.receive`
- `incoming_cheque.clear`
- `incoming_cheque.bounce`
- `incoming_cheque.return`
- `outgoing_cheque.issue`
- `outgoing_cheque.clear`
- `outgoing_cheque.return`
- `outgoing_cheque.cancel`

Required behavior:

- Replaying the same transition with the same idempotency key must not create duplicate journals, ledger entries, subledger entries, or state changes.
- Conflicting payloads with the same idempotency key must be rejected according to current idempotency behavior.
- Concurrent clear attempts on the same cheque must produce exactly one clear posting.
- Concurrent clear and bounce/return on the same cheque must allow only one valid transition to commit.
- Idempotency must not log raw sensitive keys.

## MODELS AND RELATIONSHIPS

Create models:

- `App\Models\IncomingCheque`
- `App\Models\OutgoingCheque`

Expected relationships:

- Customer has many incoming cheques.
- Supplier has many outgoing cheques.
- IncomingCheque belongs to Customer, Currency, FinancialPeriod/FiscalYear event references, BankAccount for deposit/clear, JournalEntry event references, ReceivableEntry event references, User provenance actors.
- OutgoingCheque belongs to Supplier, BankAccount, Currency, FinancialPeriod/FiscalYear event references, JournalEntry event references, PayableEntry event references, User provenance actors.
- JournalEntry source relationships may remain generic; do not force polymorphic relationships if not used elsewhere.

Do not add Company/Branch relationships.

## RBAC

Extend `config/erp_rbac.php` only as needed.

Suggested permissions:

- `cheques.view`
- `cheques.create`
- `cheques.receive`
- `cheques.issue`
- `cheques.deposit`
- `cheques.clear`
- `cheques.return`
- `cheques.cancel`

Do not add branch/company scoped permissions.

Update seeders/tests so permissions are registered.

## CONTROLLERS / ROUTES / UI

Slice 5 is primarily a backend/service integrity slice.

If adding HTTP routes:

- keep them minimal
- require explicit permissions
- validate server-side
- never trust browser-calculated state or account IDs
- do not build broad Cheque Register UX reserved for Slice 7

It is acceptable to defer full Inertia cheque pages to Slice 7 if service-level behavior is complete and tested.

## ATTACHMENTS

Reuse the existing attachment entity registry.

Register only explicit cheque entities if implemented:

- `incoming_cheque`
- `outgoing_cheque`

Do not add bank reconciliation attachment types in Slice 5.

Unknown entity types remain deny-by-default.

## NOTIFICATIONS

Do not add notification triggers unless there is an existing explicit requirement.

## AUDIT

Audit through `AuditLogger`:

- incoming cheque create/update
- incoming cheque receive/deposit/clear/bounce/return/cancel
- outgoing cheque create/update
- outgoing cheque issue/clear/return/cancel

Tests should assert new audit records are visible in the active Spatie Activitylog-backed audit path used by the current app.

Do not write new Phase 3 audit rows directly to legacy `audit_log`.

## REQUIRED TESTS

Add focused tests for:

- new cheque tables exist
- no `company_id`, `branch_id`, or `tenant_id`
- Spatie teams remain disabled
- cheque mapping keys exist and validate account type/nature
- incoming cheque requires valid customer
- outgoing cheque requires valid supplier
- outgoing cheque requires active BankAccount with matching currency
- incoming cheque amount must be positive integer minor units
- outgoing cheque amount must be positive integer minor units
- currency must exist
- receive fails when financial period is closed
- issue fails when financial period is closed
- clear fails when financial period is closed
- event date must be inside selected financial period
- selected financial period must belong to selected fiscal year
- incoming receive posts Dr Cheques Under Collection / Cr AR Control
- incoming receive creates exactly one ReceivableEntry credit
- incoming deposit does not create journal/ledger rows
- incoming clear posts Dr Bank / Cr Cheques Under Collection
- incoming clear does not create another ReceivableEntry
- incoming pre-clear bounce posts Dr AR Control / Cr Cheques Under Collection
- incoming pre-clear bounce creates exactly one ReceivableEntry debit
- incoming pre-clear return behaves like the approved pre-clear return workflow
- incoming post-clear bounce/return is rejected as owner decision required
- outgoing issue posts Dr AP Control / Cr Cheques Payable
- outgoing issue creates exactly one PayableEntry debit
- outgoing clear posts Dr Cheques Payable / Cr Bank
- outgoing clear does not create another PayableEntry
- outgoing pre-clear return/cancel posts Dr Cheques Payable / Cr AP Control
- outgoing pre-clear return/cancel creates exactly one PayableEntry credit
- outgoing post-clear return/cancel is rejected as owner decision required
- invalid state transitions are rejected without journal/ledger/subledger rows
- repeated transition command with same idempotency key is idempotent
- concurrent clear attempts create exactly one clear journal
- concurrent clear vs bounce/return allows only one valid transition
- audit records are written through current `AuditLogger`
- attachment registry accepts only explicit Slice 5 cheque entities if registered

Do not weaken existing tests.

## POSTGRESQL STRESS COVERAGE

Add a PostgreSQL-focused stress test or Artisan command for cheque transition concurrency.

Preferred command name:

```powershell
php artisan accounting:cheque-concurrency-stress --workers=50
```

It should prove at minimum:

- two or more workers cannot clear the same incoming cheque twice
- two or more workers cannot clear the same outgoing cheque twice
- incoming clear vs bounce/return cannot both commit
- outgoing clear vs return/cancel cannot both commit
- repeated idempotency keys do not duplicate transition postings
- no deadlocks under deterministic lock ordering

If you choose not to add a new command, explain why the PHPUnit concurrency coverage is equivalent.

## DB INTEGRITY

PostgreSQL DB constraints must enforce simple row-local invariants:

- cheque amount is positive
- `fx_rate_e6 > 0`
- cheque status is one of the allowed statuses
- no Company/Branch/Tenant columns exist
- FKs are restrict/null-on-delete only where appropriate
- financial cheque records must not be cascade-deleted

Aggregate/state transition invariants must be enforced by transaction + row locks + tests.

Do not fake transition safety with application-only reads outside locks.

## DOCS / STATUS UPDATE

After implementation and verification, update:

- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `README.md`
- `ROADMAP.md`
- `CHANGELOG.md`
- `MD_DOCUMENTATION_AUDIT.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`

Only mark Slice 5 complete if verification passes.

Next recommended slice after Slice 5 should be:

```text
Phase 3 Slice 6 - Bank Reconciliation
```

Do not mark all Phase 3 complete.

## VERIFICATION

Run and report exact results from `laravel/`:

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
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

If `accounting:cheque-concurrency-stress` is not implemented because PHPUnit concurrency coverage is equivalent, state the exact replacement command/test and why.

If UI files are not touched, `npm run typecheck` and `npm run build` should still pass.

## REQUIRED FINAL REPORT

Report:

1. Files changed.
2. Migrations added.
3. Schema summary.
4. Models and relationships.
5. Cheque service APIs.
6. Incoming cheque state machine and accounting behavior.
7. Outgoing cheque state machine and accounting behavior.
8. Accounting mapping keys and validation.
9. Numbering keys and physical cheque-number handling.
10. Lock ordering and transaction boundaries.
11. Idempotency behavior.
12. AR/AP subledger effects.
13. Confirmation no post-clear bounce/return was implemented.
14. RBAC additions.
15. Attachment registry additions.
16. Audit behavior and confirmation it uses Spatie Activitylog through `AuditLogger`.
17. Confirmation no Company/Branch/Tenant scope was introduced.
18. Tests added.
19. PostgreSQL stress coverage.
20. Full verification command results.
21. Remaining deferred Phase 3 slices.

Explicitly confirm:

```text
Slice implemented: Phase 3 Slice 5 only
Incoming cheques implemented: YES
Outgoing cheques implemented: YES
Cheque receive/issue implemented: YES
Cheque clear implemented: YES
Pre-clear bounce/return/cancel behavior implemented: YES, as applicable
Post-clear bounce/return/cancel implemented: NO
Bank reconciliation implemented: NO
Sales/Purchasing/Inventory implemented: NO
Generic manual AR/AP adjustments implemented: NO
Cheque transitions create accounting through PostingEngine: YES
New posting engine introduced: NO
company_id introduced: NO
branch_id introduced: NO
tenant_id introduced: NO
Spatie teams enabled: NO
Audit backend changed away from Spatie Activitylog: NO
New idempotency system introduced: NO
```
