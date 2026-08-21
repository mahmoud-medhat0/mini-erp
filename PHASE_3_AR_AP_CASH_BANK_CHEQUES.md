# PHASE 3 - AR/AP + Cash + Banks + Cheques Foundation

Status: corrected planning contract only.

Do not implement code from this document until the owner explicitly requests Phase 3 execution.

## 1. Corrected Scope

Phase 3 sits on top of the existing Phase 2 Accounting Engine.

Build only:

- Customer and Supplier master data.
- CashAccount and BankAccount master data linked to GL accounts.
- AR/AP subledger foundation.
- Customer and Supplier opening balances.
- Receipt and Payment posting through the existing Accounting Engine.
- Allocation Engine for AR/AP settlement.
- Cheque lifecycle and accounting effects.
- Bank Reconciliation foundation.
- Inertia pages for the above.
- Customer/Supplier statements, aging, cash/bank books, cheque register, and reconciliation reports.
- PostgreSQL concurrency and reconciliation/integrity tests.

Do not start:

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

Every financial effect must reuse the existing Accounting Posting Engine. Do not create parallel journal or ledger logic.

## 2. Audit Decision - Owner Approved

Do not change this decision in Phase 3.

Use the existing:

- `audit_log`
- existing Audit service

for Phase 3 entities and actions.

Do not:

- rename `audit_log`
- replace `audit_log`
- create `activity_log`
- introduce another audit backend

If another planning document says Phase 3 should write to `activity_log`, treat that Phase 3 wording as stale. This contract preserves the owner-approved `audit_log` decision and does not modify Laravel code or schema by itself.

Required Phase 3 audit coverage:

- customer create/update/status changes
- supplier create/update/status changes
- cash/bank account changes
- receipt create/post/reverse
- payment create/post/reverse
- allocation create/reverse/remove according to approved workflow
- cheque transitions
- bank reconciliation actions

## 3. Bank Reconciliation

Bank Reconciliation belongs in Phase 3.

Add:

- `BankReconciliation`
- `ReconciliationLine`
- statement opening balance
- statement closing balance
- matched transactions
- unmatched transactions
- system balance
- reconciliation difference
- reconciliation status
- audit trail
- permissions

Minimum lifecycle:

- `draft`
- `in_progress`
- `reconciled`

Do not implement advanced bank statement import unless explicitly required.

Do not assume Company or Branch scope.

Phase 3 must not be marked complete if the agreed Bank Reconciliation foundation is missing.

## 4. AR/AP Source Rules

Do not automatically introduce generic manual AR/AP adjustments.

Safe initial AR/AP sources:

- Customer opening balance.
- Supplier opening balance.
- Receipt/payment related subledger effects.
- Cheque-related effects where the accounting workflow requires them.

Generic manual receivable/payable adjustment remains:

`UNDEFINED - DO NOT ASSUME`

If an explicit manual AR/AP adjustment workflow is required, stop and request owner approval before implementing it.

Do not create a generic `adjustment` source type merely because ERP systems often have one.

## 5. Cheque Accounting Lifecycle

Cheque must not behave as immediate Cash/Bank settlement before it clears.

Exact account IDs must come from configurable accounting mappings. Do not hardcode account IDs.

### Incoming Cheque

When customer cheque is received:

- Dr Cheques Under Collection
- Cr Customer AR

When cheque clears:

- Dr Bank
- Cr Cheques Under Collection

If cheque bounces before/after deposit as applicable:

- restore receivable using correct reversal/state transition
- example: Dr Customer AR / Cr Cheques Under Collection

### Outgoing Cheque

When supplier cheque is issued:

- Dr Supplier AP
- Cr Cheques Payable / Outstanding Cheques

When cheque clears:

- Dr Cheques Payable / Outstanding Cheques
- Cr Bank

Cancellation, bounce, or return must create the correct accounting reversal according to the previous posted state.

Do not mutate or delete posted journal entries. Corrections use reversal/new posting.

## 6. Cheque State Machine

Incoming and outgoing cheque states may differ where accounting semantics require it.

Minimum state concepts:

- `draft`
- `received` for incoming cheques
- `issued` for outgoing cheques
- `deposited` where applicable
- `cleared`
- `bounced`
- `returned`
- `cancelled`

Rules:

- Allowed transitions must be explicit.
- Invalid transitions must be rejected server-side.
- `cleared -> cleared` must not create another posting.
- `cancelled` cheques must not later clear.
- `cleared -> bounced` requires explicit business support for post-clear reversal.
- Browser state values are never trusted as authorization or transition authority.

## 7. Allocation Invariants

Allocation must be PostgreSQL concurrency-safe.

Required invariant for every AR/AP item:

```text
SUM(active allocations) <= allocatable amount
remaining amount >= 0
```

It is not enough to:

1. read remaining balance
2. check amount
3. insert allocation

because concurrent requests can over-allocate.

Required behavior:

- Lock target AR/AP entries before calculating remaining balance.
- When allocating across multiple entries, lock target rows in deterministic order, such as ascending primary key or service-defined order.
- Do not use Company or Branch locks.
- Reuse the existing IdempotencyStore for retried allocation commands.
- Duplicate delivery of the same allocation command must not create duplicate allocation rows.

## 8. Receipt/Payment Reversal With Allocations

Behavior is not owner-finalized.

Mark as:

`OWNER DECISION REQUIRED`

Safe options:

1. Prevent reversal while active allocations exist until they are reversed/unallocated.
2. Atomically reverse allocations and the financial transaction according to explicit business rules.

Do not silently leave inconsistent allocations.

Invariant:

A reversed receipt/payment must never leave an impossible AR/AP remaining balance.

Stop before implementing reversal semantics that affect allocated documents until the owner chooses the workflow.

## 9. Unapplied Receipts / Payments

Posting receipt/payment must not require immediate allocation.

Example:

```text
receipt total = 1000
allocated = 600
unapplied = 400
```

Invariant:

```text
allocated + unapplied = posted transaction amount
```

Do not lose unapplied balances. Future Sales/Purchasing phases can allocate them to invoices later.

## 10. Opening AR/AP Balances

Customer/Supplier opening balances must use the existing Accounting Engine.

Do not directly mutate customer/supplier balance fields as accounting source of truth.

Opening balances must produce:

- AR/AP subledger entry
- balanced JournalEntry
- LedgerEntry
- audit trail
- traceability

They must obey:

- open-period rules
- idempotency
- posted immutability
- reversal rules where supported

## 11. Cash/Bank Account GL Link Rules

CashAccount and BankAccount must reference a GL Account.

Required:

- referenced GL account exists
- referenced GL account is active
- posting uses the configured account
- browser cannot substitute a trusted accounting mapping during posting

Do not automatically create a new `manual_posting_allowed` flag.

Use the existing Accounting Core control-account rules.

If Cash/Bank GL account is classified as a control/system account:

- Manual Journal restrictions remain intact.
- Trusted Receipt/Payment/Cheque posting services may post through the Accounting Engine where allowed by backend source classification.
- The browser must never claim a trusted system posting source.

## 12. Subledger To GL Reconciliation

Add explicit Phase 3 invariants:

- AR subledger total reconciles to the configured AR control account.
- AP subledger total reconciles to the configured AP control account.
- Cash/Bank transaction ledgers reconcile to their posted GL accounts where applicable.

Do not create duplicated independent balances that can drift from GL.

Reports must derive from durable posted/subledger data.

## 13. Remaining Balance Model

Be careful with mutable `remaining_minor`.

Preferred source of truth:

```text
original amount - valid allocations/reversals
```

If `remaining_minor` is stored for performance:

- it must be transactionally protected
- it must be reconstructable
- it must be covered by integrity/reconciliation tests
- it must never silently drift

Document the chosen approach before implementation.

## 14. Attachments

Reuse the existing AttachmentEntityAuthorizer registry.

Register Phase 3 entity types explicitly as appropriate:

- `customer`
- `supplier`
- `receipt`
- `payment`
- `cheque`
- `bank_reconciliation`

Do not add generic dynamic class resolution.

Unknown entity types remain deny-by-default.

No Company/Branch authorization shortcut.

## 15. Phase 3 Reports

Reports should include:

- Customer Statement
- Supplier Statement
- AR Aging where Phase 3 sources support it correctly
- AP Aging
- Cash Book
- Bank Book
- Cheque Register
- Bank Reconciliation report/status
- AR to GL reconciliation
- AP to GL reconciliation

Do not implement Sales/Purchase reports.

Do not fabricate invoice aging if Sales/Purchasing invoices do not exist yet.

Aging must operate only on actual Phase 3 AR/AP source entries available at that point.

## 16. Concurrency Matrix

Every operation must document:

- invariant
- transaction boundary
- rows locked
- lock ordering
- DB constraint
- idempotency key/strategy
- retry behavior
- PostgreSQL test

| Operation | Invariant | Required locking / idempotency | PostgreSQL test |
|---|---|---|---|
| Receipt posting race | Same Receipt posts exactly once. | Lock receipt/source row, use existing Posting Engine, IdempotencyStore, source uniqueness, NumberSequence allocator. | Two concurrent POST commands produce one durable posting. |
| Payment posting race | Same Payment posts exactly once. | Same as receipt. | Two concurrent POST commands produce one durable posting. |
| AR allocation race | Allocated amount never exceeds AR item amount. | Lock AR target rows in deterministic order before remaining calculation; idempotent command key. | AR remaining 100; concurrent 80 + 80; committed allocations <= 100. |
| AP allocation race | Allocated amount never exceeds AP item amount. | Lock AP target rows in deterministic order before remaining calculation; idempotent command key. | AP remaining 100; concurrent 80 + 80; committed allocations <= 100. |
| Multi-target allocation | No deadlock and no over-allocation. | Lock all target rows in service-defined deterministic order. | Concurrent overlapping multi-target allocations complete or conflict safely. |
| Allocation vs receipt reversal | Allocation cannot attach to a receipt that is concurrently reversed. | Lock receipt and target AR entries; recheck status inside transaction. | One transaction wins; no impossible balance. |
| Allocation vs payment reversal | Allocation cannot attach to a payment that is concurrently reversed. | Lock payment and target AP entries; recheck status inside transaction. | One transaction wins; no impossible balance. |
| Cheque clear vs clear | Same cheque clears exactly once. | Lock cheque row; compare-and-swap state; idempotency key for clear command. | Two workers clear same cheque; one transition and one accounting effect. |
| Cheque clear vs bounce | Clear and bounce cannot both commit. | Lock cheque row; validate state transition after lock; idempotent transition command. | One clear worker and one bounce worker; only one valid effect persists. |
| Bank reconciliation duplicate matching | Same bank transaction cannot belong to incompatible active/final reconciliations. | Lock transaction/reconciliation rows; DB/service uniqueness for active/final matching. | Concurrent sessions cannot match same transaction incompatibly. |
| Reconciliation finalization | Same reconciliation finalizes once. | Lock reconciliation header and lines; idempotency key. | Concurrent finalize produces one final state. |
| Period close vs Phase 3 posting | Once period close commits, no Phase 3 posting may commit into it. | Reuse Accounting Engine FinancialPeriod lock strategy. | Concurrent close vs receipt/payment/cheque posting serializes safely. |
| Duplicate idempotency delivery | Retry does not duplicate rows. | Existing IdempotencyStore only. | Same command key replay returns same result or deterministic conflict. |

## 17. Implementation Slices

Do not combine all slices into one giant implementation.

### Slice 1 - Master Schema / Models

- Customer
- Supplier
- CashAccount
- BankAccount
- GL account links and validations

### Slice 2 - AR/AP Subledger + Opening Balances

- AR/AP entry model
- customer/supplier opening balances
- accounting posting through existing engine
- no generic manual adjustments without owner approval

### Slice 3 - Receipt/Payment Posting

- receipt/payment draft/post flow
- source links
- idempotency
- existing Accounting Engine only

### Slice 4 - Allocation Engine

- allocation records
- unapplied balances
- deterministic locking
- AR/AP over-allocation prevention
- idempotency

### Slice 5 - Cheque Lifecycle

- incoming cheque lifecycle
- outgoing cheque lifecycle
- state machine
- clear/bounce/cancel accounting effects

### Slice 6 - Bank Reconciliation

- reconciliation header
- reconciliation lines
- matched/unmatched transactions
- reconciliation difference
- status lifecycle

### Slice 7 - Inertia Pages

- customer/supplier pages
- cash/bank pages
- receipt/payment pages
- allocation UX
- cheque register/actions
- bank reconciliation page

### Slice 8 - Reports

- statements
- aging
- cash book
- bank book
- cheque register
- bank reconciliation report/status
- AR/AP to GL reconciliation

### Slice 9 - PostgreSQL Stress / Integrity Tests

- posting races
- allocation races
- cheque transition races
- bank reconciliation duplicate matching
- period close races
- subledger to GL reconciliation

### Slice 10 - Docs / Status / Final Verification

- update docs
- update status
- run final verification gate

## 18. Required Tests

Required coverage:

- customer/supplier CRUD permissions
- cash/bank GL account link validation
- inactive GL account rejected for setup/posting where applicable
- customer opening balance posts AR subledger + journal + ledger
- supplier opening balance posts AP subledger + journal + ledger
- receipt posting creates journal + ledger + subledger effect
- payment posting creates journal + ledger + subledger effect
- posted receipt/payment idempotency
- closed period rejects receipt/payment/cheque postings
- allocations cannot over-allocate
- allocation command is idempotent
- unapplied receipt/payment amount is preserved
- receipt/payment reversal with allocations follows owner-approved workflow or is blocked pending decision
- cheque state machine rejects invalid transitions
- cheque clear creates exactly one accounting effect
- cheque bounce/return creates correct reversal/new posting
- bank reconciliation draft/in_progress/reconciled lifecycle
- bank transaction cannot be matched into incompatible reconciliations
- AR subledger reconciles to AR control GL account
- AP subledger reconciles to AP control GL account
- attachments authorize Phase 3 entities through registry
- audit writes through existing `audit_log`/Audit service
- no `company_id`, `branch_id`, or tenant semantics introduced

Required PostgreSQL concurrency tests:

- receipt post vs receipt post
- payment post vs payment post
- AR allocation race
- AP allocation race
- allocation vs receipt reversal
- allocation vs payment reversal
- cheque clear vs clear
- cheque clear vs bounce
- bank reconciliation duplicate matching
- period close vs receipt/payment/cheque posting

## 19. Verification Gate

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

Add a Phase 3 PostgreSQL stress command or test path before marking Phase 3 complete.

## 20. Unresolved Owner Decisions

Owner decision required before implementation where relevant:

1. Receipt/payment reversal behavior when active allocations exist:
   - block until unallocated/reversed
   - or atomically reverse allocations and transaction
2. Whether post-clear cheque bounce/return is supported and under what workflow.
3. Exact accounting mappings for:
   - AR control
   - AP control
   - Cheques Under Collection
   - Cheques Payable / Outstanding Cheques
   - cash accounts
   - bank accounts
4. Whether generic manual AR/AP adjustments are needed. Default: `UNDEFINED - DO NOT ASSUME`.
5. Whether bank statement import is required. Default: not in Phase 3.
6. Aging basis for Phase 3 sources without Sales/Purchasing invoices.

## 21. Explicit Confirmations

```text
audit_log decision changed: NO
activity_log introduced: NO
company_id introduced: NO
branch_id introduced: NO
tenant semantics introduced: NO
Sales/Purchasing/Inventory started: NO
```
