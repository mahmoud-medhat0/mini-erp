# MINI ERP - PHASE 3 SLICE 4 GEMINI PROMPT

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


You are continuing the existing Laravel + Inertia + React Mini ERP repository.

Implement **Phase 3 Slice 4 only**.

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
  - receipt/payment `allocated_minor` and `unapplied_minor`
  - idempotent posting
  - DB integrity hardening for receipt/payment status, amounts, cash/bank choice, linked GL currency, and delete restriction

Read these first:

- `README.md`
- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`
- `PHASE_3_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_3_GEMINI_PROMPT.md`
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

## SLICE 4 OBJECTIVE

Implement only the AR/AP Allocation Engine:

1. Allocate posted Customer Receipts to posted positive Receivable Entries.
2. Allocate posted Supplier Payments to posted positive Payable Entries.
3. Maintain receipt/payment unapplied balances transactionally.
4. Prevent AR/AP over-allocation under PostgreSQL concurrency.
5. Use deterministic row locking and existing idempotency infrastructure.
6. Add allocation records with append-only/correction-by-reversal behavior.
7. Add service-layer tests and PostgreSQL stress coverage.
8. Audit allocation create/reverse actions through `AuditLogger`.
9. Update docs/status after successful verification.

Allocation is settlement metadata only.

Allocation must not create new JournalEntry, JournalLine, LedgerEntry, ReceivableEntry, or PayableEntry rows.

## STRICT NON-GOALS

Do not implement:

- receipt posting
- payment posting
- posted receipt reversal
- posted payment reversal
- cheque lifecycle
- bank reconciliation
- customer/supplier statement pages
- aging reports
- cash book
- bank book
- cheque register
- Sales Invoice
- Purchase Invoice
- Inventory movement
- VAT workflow
- full financial statements
- dashboard expansion
- generic manual AR/AP adjustments
- new accounting posting engine
- new idempotency system
- new audit system
- broad Inertia pages or UX flows reserved for Slice 7

If tempted to implement any of these, stop.

## EXISTING CODE TO REUSE

Reuse current Laravel services and invariants:

- `App\Support\Concurrency\DatabaseIdempotencyStore`
- `App\Domain\Audit\AuditLogger`
- `App\Models\CustomerReceipt`
- `App\Models\SupplierPayment`
- `App\Models\ReceivableEntry`
- `App\Models\PayableEntry`
- existing PostgreSQL transaction and `lockForUpdate()` patterns
- existing integer minor-unit money rules
- existing Spatie Permission RBAC seed flow
- existing test setup helpers and seeders

Do not create parallel posting, ledger, audit, numbering, or idempotency logic.

## ALLOCATION SEMANTICS

### Customer Receipt Allocation

A posted Customer Receipt reduces an outstanding AR item.

Allowed target:

- `receivable_entry` belonging to the same customer
- same currency as the receipt
- positive AR item where:

```text
receivable_entry.debit_minor > receivable_entry.credit_minor
```

Target allocatable amount:

```text
receivable_entry.debit_minor - receivable_entry.credit_minor
```

Target allocated amount:

```text
SUM(active receivable_allocation.amount_minor for that receivable_entry_id)
```

Target remaining amount:

```text
target allocatable amount - target allocated amount
```

Allocation must reject the command if:

- receipt is not `posted`
- receipt currency does not match target currency
- target customer does not match receipt customer
- target is not a positive AR item
- requested allocation amount is not positive
- requested allocation amount exceeds receipt `unapplied_minor`
- requested allocation amount exceeds target remaining amount

On success:

```text
customer_receipt.allocated_minor += allocation total
customer_receipt.unapplied_minor -= allocation total
```

Preserve:

```text
allocated_minor + unapplied_minor = amount_minor
```

### Supplier Payment Allocation

A posted Supplier Payment reduces an outstanding AP item.

Allowed target:

- `payable_entry` belonging to the same supplier
- same currency as the payment
- positive AP item where:

```text
payable_entry.credit_minor > payable_entry.debit_minor
```

Target allocatable amount:

```text
payable_entry.credit_minor - payable_entry.debit_minor
```

Target allocated amount:

```text
SUM(active payable_allocation.amount_minor for that payable_entry_id)
```

Target remaining amount:

```text
target allocatable amount - target allocated amount
```

Allocation must reject the command if:

- payment is not `posted`
- payment currency does not match target currency
- target supplier does not match payment supplier
- target is not a positive AP item
- requested allocation amount is not positive
- requested allocation amount exceeds payment `unapplied_minor`
- requested allocation amount exceeds target remaining amount

On success:

```text
supplier_payment.allocated_minor += allocation total
supplier_payment.unapplied_minor -= allocation total
```

Preserve:

```text
allocated_minor + unapplied_minor = amount_minor
```

## EXPECTED TABLES

Use singular table names consistent with current Laravel schema style.

Expected new tables:

- `receivable_allocation`
- `payable_allocation`

No new table may contain:

- `company_id`
- `branch_id`
- `tenant_id`

Do not add allocation columns to `receivable_entry` or `payable_entry`.

Do not store mutable remaining balances on `receivable_entry` or `payable_entry`.

Remaining balances must be derived from original entry amount minus active allocations.

## RECEIVABLE ALLOCATION TABLE

Minimum fields:

- `id` uuid primary key
- `customer_id` uuid FK to `customer.id`
- `customer_receipt_id` uuid FK to `customer_receipt.id`
- `receivable_entry_id` uuid FK to `receivable_entry.id`
- `currency` string(3), FK to `currency.code`
- `amount_minor` bigint positive
- `status` string default `active`
- `allocated_at` timestamp
- `reversed_at` nullable timestamp
- `reason` nullable text
- `reversed_reason` nullable text
- `created_by` nullable FK to users
- `reversed_by` nullable FK to users
- timestamps

Allowed statuses:

- `active`
- `reversed`

Foreign key deletes must be `restrict` for customer, receipt, receivable entry, and currency.

Do not cascade-delete financial allocation records.

Recommended indexes:

- `customer_receipt_id, status`
- `receivable_entry_id, status`
- `customer_id, allocated_at`
- `currency`

PostgreSQL checks:

- `amount_minor > 0`
- `status IN ('active', 'reversed')`

## PAYABLE ALLOCATION TABLE

Mirror Receivable Allocation:

- `id` uuid primary key
- `supplier_id` uuid FK to `supplier.id`
- `supplier_payment_id` uuid FK to `supplier_payment.id`
- `payable_entry_id` uuid FK to `payable_entry.id`
- `currency` string(3), FK to `currency.code`
- `amount_minor` bigint positive
- `status` string default `active`
- `allocated_at` timestamp
- `reversed_at` nullable timestamp
- `reason` nullable text
- `reversed_reason` nullable text
- `created_by` nullable FK to users
- `reversed_by` nullable FK to users
- timestamps

Allowed statuses:

- `active`
- `reversed`

Foreign key deletes must be `restrict` for supplier, payment, payable entry, and currency.

Do not cascade-delete financial allocation records.

Recommended indexes:

- `supplier_payment_id, status`
- `payable_entry_id, status`
- `supplier_id, allocated_at`
- `currency`

PostgreSQL checks:

- `amount_minor > 0`
- `status IN ('active', 'reversed')`

## SERVICE API

Implement service layer first.

Suggested services:

- `App\Application\Accounting\ReceivableAllocationService`
- `App\Application\Accounting\PayableAllocationService`

Suggested methods:

```php
allocateReceipt(string $receiptId, array $lines, int $actorId, ?string $idempotencyKey = null): array
reverseReceiptAllocation(string $allocationId, string $reason, int $actorId, ?string $idempotencyKey = null): ReceivableAllocation

allocatePayment(string $paymentId, array $lines, int $actorId, ?string $idempotencyKey = null): array
reversePaymentAllocation(string $allocationId, string $reason, int $actorId, ?string $idempotencyKey = null): PayableAllocation
```

Line shape:

```php
[
    'receivable_entry_id' => 'uuid',
    'amount_minor' => 10000,
]
```

or:

```php
[
    'payable_entry_id' => 'uuid',
    'amount_minor' => 10000,
]
```

Rules:

- Reject empty allocation lines.
- Reject duplicate target entry IDs inside one command.
- Reject non-integer or non-positive amounts.
- Treat a multi-line command as atomic: all allocations succeed or none are persisted.
- Do not silently partially allocate.
- Do not use float math.
- Do not use `round()` for money calculations.

## LOCKING ORDER

Allocation must be PostgreSQL concurrency-safe.

For `allocateReceipt`:

1. Enter `DatabaseIdempotencyStore`.
2. Start DB transaction.
3. Lock the `customer_receipt` row with `lockForUpdate()`.
4. Recheck receipt status, currency, and `unapplied_minor`.
5. Load and lock all target `receivable_entry` rows in deterministic ascending ID order.
6. Recheck target customer/currency/positive-AR rules after locks.
7. Calculate active allocation sums after locks.
8. Insert allocation rows.
9. Update receipt `allocated_minor` and `unapplied_minor`.
10. Audit through `AuditLogger`.

For `allocatePayment`:

1. Enter `DatabaseIdempotencyStore`.
2. Start DB transaction.
3. Lock the `supplier_payment` row with `lockForUpdate()`.
4. Recheck payment status, currency, and `unapplied_minor`.
5. Load and lock all target `payable_entry` rows in deterministic ascending ID order.
6. Recheck target supplier/currency/positive-AP rules after locks.
7. Calculate active allocation sums after locks.
8. Insert allocation rows.
9. Update payment `allocated_minor` and `unapplied_minor`.
10. Audit through `AuditLogger`.

For reversing an allocation:

1. Start DB transaction through idempotency.
2. Read allocation metadata.
3. Lock the parent receipt/payment row.
4. Lock the target receivable/payable entry row.
5. Lock the allocation row.
6. Recheck allocation status is `active`.
7. Mark allocation `reversed`.
8. Restore parent receipt/payment `allocated_minor` and `unapplied_minor`.
9. Audit through `AuditLogger`.

Do not delete allocation rows.

Do not mutate ledger entries or posted journal entries.

## IDEMPOTENCY

Use the existing `DatabaseIdempotencyStore`.

Do not create a new idempotency system.

Suggested operation names:

- `receivable_allocation.allocate`
- `receivable_allocation.reverse`
- `payable_allocation.allocate`
- `payable_allocation.reverse`

Required behavior:

- Replaying the same allocation command with the same idempotency key must not create duplicate rows.
- Replaying the same reversal command with the same idempotency key must not reverse twice.
- Conflicting payloads with the same idempotency key must be rejected according to current idempotency behavior.
- Idempotency must not log raw sensitive keys.

## MODELS AND RELATIONSHIPS

Create models:

- `App\Models\ReceivableAllocation`
- `App\Models\PayableAllocation`

Add relationships:

- Customer has many receivable allocations.
- Supplier has many payable allocations.
- CustomerReceipt has many receivable allocations.
- SupplierPayment has many payable allocations.
- ReceivableEntry has many receivable allocations.
- PayableEntry has many payable allocations.
- ReceivableAllocation belongs to Customer, CustomerReceipt, ReceivableEntry, Currency, createdBy User, reversedBy User.
- PayableAllocation belongs to Supplier, SupplierPayment, PayableEntry, Currency, createdBy User, reversedBy User.

Do not add Company/Branch relationships.

## RBAC

Extend `config/erp_rbac.php` only as needed.

Suggested permissions:

- `customers.allocations`
- `suppliers.allocations`

Do not add branch/company scoped permissions.

Update seeders/tests so permissions are registered.

## CONTROLLERS / ROUTES / UI

Slice 4 is primarily a backend/service integrity slice.

If adding HTTP routes:

- keep them minimal
- require explicit permissions
- validate server-side
- do not trust browser-calculated remaining amounts
- do not accept GL account IDs
- do not build broad allocation UX reserved for Slice 7

It is acceptable to defer full Inertia allocation pages to Slice 7 if service-level behavior is complete and tested.

## ATTACHMENTS

Do not add attachment entity types for allocations unless the current code already has a clear business requirement for allocation attachments.

Unknown attachment entity types remain deny-by-default.

## NOTIFICATIONS

Do not add notification triggers unless there is an existing explicit requirement.

## AUDIT

Audit through `AuditLogger`:

- receivable allocation create
- receivable allocation reverse
- payable allocation create
- payable allocation reverse

Tests should assert new audit records are visible in the active Spatie Activitylog-backed audit path used by the current app.

Do not write new Phase 3 audit rows directly to legacy `audit_log`.

## REQUIRED TESTS

Add focused tests for:

- new allocation tables exist
- no `company_id`, `branch_id`, or `tenant_id`
- Spatie teams remain disabled
- allocation records use `restrict` foreign keys, not cascade deletes
- receipt allocation requires posted receipt
- payment allocation requires posted payment
- receipt allocation rejects customer mismatch
- payment allocation rejects supplier mismatch
- receipt/payment allocation rejects currency mismatch
- receipt allocation rejects credit/zero receivable target entries
- payment allocation rejects debit/zero payable target entries
- allocation amount must be positive integer minor units
- multi-line allocation rejects duplicate target entries
- allocation command is atomic when any line fails
- receipt allocation reduces `unapplied_minor` and increases `allocated_minor`
- payment allocation reduces `unapplied_minor` and increases `allocated_minor`
- receipt invariant remains `allocated_minor + unapplied_minor = amount_minor`
- payment invariant remains `allocated_minor + unapplied_minor = amount_minor`
- receipt allocation cannot exceed receipt unapplied amount
- payment allocation cannot exceed payment unapplied amount
- receivable target cannot be over-allocated
- payable target cannot be over-allocated
- allocation does not create journal, journal line, ledger, receivable entry, or payable entry rows
- repeated allocation command with same idempotency key is idempotent
- repeated reversal command with same idempotency key is idempotent
- reversing active receivable allocation restores receipt unapplied balance
- reversing active payable allocation restores payment unapplied balance
- reversing an already reversed allocation is rejected or idempotently returns the durable result only for the same command key
- concurrent receipt allocations against the same receivable target cannot over-allocate
- concurrent payment allocations against the same payable target cannot over-allocate
- concurrent allocations against the same receipt/payment cannot over-spend its unapplied amount
- overlapping multi-target allocations do not deadlock due to deterministic lock ordering
- audit records are written through current `AuditLogger`

Do not weaken existing tests.

## POSTGRESQL STRESS COVERAGE

Add a PostgreSQL-focused stress test or Artisan command for allocation concurrency.

Preferred command name:

```powershell
php artisan accounting:allocation-concurrency-stress --workers=50
```

It should prove at minimum:

- two or more workers cannot over-allocate the same receivable entry
- two or more workers cannot over-allocate the same payable entry
- two or more workers cannot spend the same receipt/payment unapplied amount twice
- repeated idempotency keys do not duplicate allocation rows
- no deadlocks under deterministic multi-target lock ordering

If you choose not to add a new command, explain why the PHPUnit concurrency coverage is equivalent.

## DB INTEGRITY

PostgreSQL DB constraints must enforce simple row-local invariants:

- allocation amount is positive
- allocation status is one of the allowed statuses
- no Company/Branch/Tenant columns exist
- FKs are restrict/null-on-delete only where appropriate; do not cascade-delete financial allocation records

Aggregate invariants such as over-allocation must be enforced by transaction + row locks + tests.

Do not fake aggregate safety with application-only reads outside locks.

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

Only mark Slice 4 complete if verification passes.

Next recommended slice after Slice 4 should be:

```text
Phase 3 Slice 5 - Cheque Lifecycle
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
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

If `accounting:allocation-concurrency-stress` is not implemented because PHPUnit concurrency coverage is equivalent, state the exact replacement command/test and why.

If UI files are not touched, `npm run typecheck` and `npm run build` should still pass.

## REQUIRED FINAL REPORT

Report:

1. Files changed.
2. Migrations added.
3. Schema summary.
4. Models and relationships.
5. Allocation service APIs.
6. Allocation and reversal behavior.
7. Lock ordering and transaction boundaries.
8. Idempotency behavior.
9. Over-allocation prevention.
10. Receipt/payment unapplied balance behavior.
11. Confirmation no GL/journal/ledger rows are created by allocation.
12. RBAC additions.
13. Audit behavior and confirmation it uses Spatie Activitylog through `AuditLogger`.
14. Confirmation no Company/Branch/Tenant scope was introduced.
15. Tests added.
16. PostgreSQL stress coverage.
17. Full verification command results.
18. Remaining deferred Phase 3 slices.

Explicitly confirm:

```text
Slice implemented: Phase 3 Slice 4 only
Receivable allocations implemented: YES
Payable allocations implemented: YES
Receipt/payment posting changed: NO, unless required bug fix documented
Posted receipt reversal implemented: NO
Posted payment reversal implemented: NO
Cheques implemented: NO
Bank reconciliation implemented: NO
Sales/Purchasing/Inventory implemented: NO
Generic manual AR/AP adjustments implemented: NO
Allocation creates GL/journal/ledger rows: NO
company_id introduced: NO
branch_id introduced: NO
tenant_id introduced: NO
Spatie teams enabled: NO
Audit backend changed away from Spatie Activitylog: NO
New idempotency system introduced: NO
```
