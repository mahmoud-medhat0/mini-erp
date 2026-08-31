# MINI ERP - PHASE 3 SLICE 3 GEMINI PROMPT

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


You are continuing the existing Laravel + Inertia + React Mini ERP repository.

Implement **Phase 3 Slice 3 only**.

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

Read these first:

- `README.md`
- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`
- `PHASE_3_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_2_GEMINI_PROMPT.md`
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

## SLICE 3 OBJECTIVE

Implement only:

1. Customer Receipt draft/post flow.
2. Supplier Payment draft/post flow.
3. Posting through the existing Accounting PostingEngine.
4. AR/AP subledger effects for receipts/payments.
5. CashAccount/BankAccount GL effects using their trusted backend GL links.
6. Unapplied receipt/payment amount tracking without allocations.
7. Idempotent and concurrency-safe posting.
8. Audit logging through `AuditLogger`.
9. Attachment registry additions for receipt/payment entities if appropriate.
10. Tests.
11. Docs/status update.

This slice posts receipts and payments only.

## STRICT NON-GOALS

Do not implement:

- allocation records
- allocation engine
- applying receipts/payments to specific AR/AP entries
- over-allocation logic
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
- posted receipt/payment reversal if it requires allocation semantics
- new accounting posting engine
- new idempotency system
- new audit system
- frontend pages unless there is an existing pattern that can be extended safely for this exact slice

If tempted to implement any of these, stop.

## EXISTING CODE TO REUSE

Reuse existing Laravel services and invariants wherever possible:

- `App\Application\Accounting\PostingEngine`
- `App\Application\Accounting\AccountingAccountMappingService`
- `App\Application\Accounting\ArApSubledgerService`
- `App\Application\Accounting\JournalDraftService`
- `App\Support\Concurrency\DatabaseIdempotencyStore`
- `App\Domain\Audit\AuditLogger`
- existing FinancialPeriod/FiscalYear open-period rules
- existing integer Money/accounting invariant primitives
- existing CashAccount and BankAccount models
- existing ReceivableEntry and PayableEntry models
- existing Spatie Permission RBAC seed flow
- existing attachment entity registry

Do not create parallel ledger or journal logic.

Every posted receipt/payment must produce:

- `journal_entry`
- `journal_line`
- `ledger_entry`
- AR/AP subledger entry
- receipt/payment document row
- audit record through `AuditLogger`

## ACCOUNTING BEHAVIOR

Customer Receipt posting:

```text
Dr Cash/Bank GL Account
Cr AR Control Account
```

Supplier Payment posting:

```text
Dr AP Control Account
Cr Cash/Bank GL Account
```

Trusted account sources:

- AR Control must come from `accounting_account_mapping.key = ar_control`.
- AP Control must come from `accounting_account_mapping.key = ap_control`.
- Cash GL account must come from the selected `cash_account.gl_account_id`.
- Bank GL account must come from the selected `bank_account.gl_account_id`.

Do not hardcode account IDs.

The browser may select a CashAccount or BankAccount record, but it must never submit or override the trusted GL account used for posting.

## UNAPPLIED AMOUNTS

Allocation is not implemented in Slice 3.

For posted Customer Receipt:

```text
amount_minor = posted receipt amount
allocated_minor = 0
unapplied_minor = amount_minor
```

For posted Supplier Payment:

```text
amount_minor = posted payment amount
allocated_minor = 0
unapplied_minor = amount_minor
```

Do not create allocation rows in this slice.

Do not add mutable remaining balances to `receivable_entry` or `payable_entry`.

If storing `allocated_minor` and `unapplied_minor` on receipt/payment documents:

- both must be integer minor units
- both must be non-negative
- `allocated_minor + unapplied_minor = amount_minor`
- in Slice 3, `allocated_minor` must remain `0`

Future Slice 4 may change these through a concurrency-safe allocation engine.

## EXPECTED TABLES

Use singular table names consistent with current Laravel schema style.

Expected new tables:

- `customer_receipt`
- `supplier_payment`

No new table may contain:

- `company_id`
- `branch_id`
- `tenant_id`

Do not create allocation tables in this slice.

## CUSTOMER RECEIPT TABLE

Minimum fields:

- `id` uuid primary key
- `number` nullable string unique, allocated on post
- `customer_id` uuid FK to `customer.id`
- `fiscal_year_id` uuid FK to `fiscal_year.id`
- `financial_period_id` uuid FK to `financial_period.id`
- `receipt_date` date
- `reference` nullable string
- `description` nullable text
- `cash_account_id` nullable uuid FK to `cash_account.id`
- `bank_account_id` nullable uuid FK to `bank_account.id`
- `currency` char/string(3), FK to `currency.code`
- `amount_minor` bigint positive
- `allocated_minor` bigint default 0
- `unapplied_minor` bigint default 0
- `fx_rate_e6` bigint default 1000000
- `status` string default `draft`
- `journal_entry_id` nullable uuid FK to `journal_entry.id`
- `receivable_entry_id` nullable uuid FK to `receivable_entry.id`
- `created_by`, `updated_by`, `posted_by` nullable FK to users
- `posted_at` nullable timestamp
- `lock_version` unsigned integer default 0
- timestamps

Allowed statuses:

- `draft`
- `posted`
- `cancelled` only if no posting exists

Rules:

- exactly one of `cash_account_id` or `bank_account_id` must be present
- amount must be integer minor units and greater than zero
- `allocated_minor` must be 0 in Slice 3
- `unapplied_minor` must equal `amount_minor` after posting
- receipt_date must belong to selected financial period
- selected financial period must belong to selected fiscal year
- period must be open at posting time
- selected CashAccount/BankAccount must exist and be active
- selected CashAccount/BankAccount currency must match document currency
- mapped AR control account currency must match document currency
- no float math
- no direct balance mutation on customer

## SUPPLIER PAYMENT TABLE

Mirror Customer Receipt:

- `id` uuid primary key
- `number` nullable string unique, allocated on post
- `supplier_id` uuid FK to `supplier.id`
- `fiscal_year_id` uuid FK to `fiscal_year.id`
- `financial_period_id` uuid FK to `financial_period.id`
- `payment_date` date
- `reference` nullable string
- `description` nullable text
- `cash_account_id` nullable uuid FK to `cash_account.id`
- `bank_account_id` nullable uuid FK to `bank_account.id`
- `currency` char/string(3), FK to `currency.code`
- `amount_minor` bigint positive
- `allocated_minor` bigint default 0
- `unapplied_minor` bigint default 0
- `fx_rate_e6` bigint default 1000000
- `status` string default `draft`
- `journal_entry_id` nullable uuid FK to `journal_entry.id`
- `payable_entry_id` nullable uuid FK to `payable_entry.id`
- provenance fields
- `lock_version`
- timestamps

Rules:

- exactly one of `cash_account_id` or `bank_account_id` must be present
- amount must be integer minor units and greater than zero
- `allocated_minor` must be 0 in Slice 3
- `unapplied_minor` must equal `amount_minor` after posting
- payment_date must belong to selected financial period
- selected financial period must belong to selected fiscal year
- period must be open at posting time
- selected CashAccount/BankAccount must exist and be active
- selected CashAccount/BankAccount currency must match document currency
- mapped AP control account currency must match document currency
- no float math
- no direct balance mutation on supplier

## NUMBERING

Use the existing number sequence infrastructure.

Register or allocate global sequence keys only as needed:

- receipt number prefix should be `REC`
- payment number prefix should be `PAY`

Do not add company/branch dimensions to numbering.

Do not create a new numbering engine.

If the existing NumberSequenceAllocator returns only numeric values, format numbers consistently with existing patterns:

- `REC-YYYY-00001`
- `PAY-YYYY-00001`

Document the exact keys used in the final report.

## POSTING BEHAVIOR

Customer Receipt posting:

1. Use `DatabaseIdempotencyStore`.
2. Start a DB transaction.
3. Lock the customer receipt row.
4. Recheck status is `draft` or return the already posted durable result if status is `posted`.
5. Lock/recheck FinancialPeriod is open.
6. Validate receipt_date is inside period.
7. Resolve selected CashAccount/BankAccount and lock/recheck it is active.
8. Resolve trusted AR control mapping through `AccountingAccountMappingService`.
9. Validate all posting accounts are active and currency-compatible.
10. Allocate receipt number if missing.
11. Create approved JournalEntry:
    - `source_type = customer_receipt`
    - `source_id = customer_receipt.id`
12. Create JournalLine rows:
    - Dr selected cash/bank GL account
    - Cr AR Control
13. Post through `PostingEngine` with explicit trusted control-account allowance where needed.
14. Create one `receivable_entry` linked to the customer and posted AR control journal line:
    - `source_type = customer_receipt`
    - `source_id = customer_receipt.id`
    - credit amount = receipt amount
15. Mark receipt as posted:
    - `status = posted`
    - `journal_entry_id`
    - `receivable_entry_id`
    - `allocated_minor = 0`
    - `unapplied_minor = amount_minor`
    - `posted_by`
    - `posted_at`
16. Audit through `AuditLogger`.

Supplier Payment posting:

1. Use `DatabaseIdempotencyStore`.
2. Start a DB transaction.
3. Lock the supplier payment row.
4. Recheck status is `draft` or return the already posted durable result if status is `posted`.
5. Lock/recheck FinancialPeriod is open.
6. Validate payment_date is inside period.
7. Resolve selected CashAccount/BankAccount and lock/recheck it is active.
8. Resolve trusted AP control mapping through `AccountingAccountMappingService`.
9. Validate all posting accounts are active and currency-compatible.
10. Allocate payment number if missing.
11. Create approved JournalEntry:
    - `source_type = supplier_payment`
    - `source_id = supplier_payment.id`
12. Create JournalLine rows:
    - Dr AP Control
    - Cr selected cash/bank GL account
13. Post through `PostingEngine` with explicit trusted control-account allowance where needed.
14. Create one `payable_entry` linked to the supplier and posted AP control journal line:
    - `source_type = supplier_payment`
    - `source_id = supplier_payment.id`
    - debit amount = payment amount
15. Mark payment as posted:
    - `status = posted`
    - `journal_entry_id`
    - `payable_entry_id`
    - `allocated_minor = 0`
    - `unapplied_minor = amount_minor`
    - `posted_by`
    - `posted_at`
16. Audit through `AuditLogger`.

## CANCELLATION AND REVERSAL

Implement draft cancellation only if needed:

- `draft -> cancelled`
- no journal
- no ledger
- no subledger entry

Do not implement posted reversal in Slice 3 unless it is already safely supported by current services without allocation semantics.

If posted reversal is not implemented, document it as deferred.

Do not implement behavior for reversal while active allocations exist; allocations do not exist in this slice and that workflow remains an owner-decision gate.

## IDEMPOTENCY AND CONCURRENCY

Use the existing `DatabaseIdempotencyStore`.

Do not create a new idempotency system.

Required behavior:

- repeated post command for the same receipt/payment returns the same durable result
- concurrent post attempts for the same receipt create exactly one journal and one receivable entry
- concurrent post attempts for the same payment create exactly one journal and one payable entry
- failed posting must not leave orphan journal/subledger rows
- use DB transactions and row locks
- lock order must be deterministic:
  1. receipt/payment row
  2. financial period row
  3. selected cash/bank account row
  4. mapped control account row

Add PostgreSQL-focused tests or extend existing tests to prove the critical idempotency path.

## MONEY AND FX

Amounts must be stored as integer minor units.

Do not use:

- float
- `(float)`
- `round()` for money conversion
- binary floating-point arithmetic

For Slice 3, prefer same-currency receipts/payments unless exact integer FX posting is already implemented.

If exact FX behavior is not already clear, restrict posting to:

- `fx_rate_e6 = 1000000`
- document currency matches selected CashAccount/BankAccount currency
- document currency matches mapped AR/AP control account currency

Document the limitation in the final report.

## MODELS AND RELATIONSHIPS

Create models as needed:

- `App\Models\CustomerReceipt`
- `App\Models\SupplierPayment`

Expected relationships:

- Customer has many customer receipts
- Supplier has many supplier payments
- CustomerReceipt belongs to Customer, FiscalYear, FinancialPeriod, CashAccount, BankAccount, JournalEntry, ReceivableEntry, Currency
- SupplierPayment belongs to Supplier, FiscalYear, FinancialPeriod, CashAccount, BankAccount, JournalEntry, PayableEntry, Currency

Do not add Company/Branch relationships.

## SERVICES / CONTROLLERS / ACTIONS

Implement service layer first.

Suggested services:

- `CustomerReceiptService`
- `SupplierPaymentService`

If adding controllers/routes:

- require explicit permissions
- use server-side validation
- never trust GL account IDs from browser
- audit create/update/cancel/post actions
- do not build allocation/cheque/reconciliation actions

If deferring UI/controllers to later slices, add model/service-level tests and document that UI comes later.

## RBAC

Extend `config/erp_rbac.php` only as needed.

Suggested permissions:

- `customers.receipts`
- `suppliers.payments`
- `cash.post`
- `banks.post`

Do not add branch/company scoped permissions.

Update seeders/tests so permissions are registered.

## ATTACHMENTS

Reuse the existing attachment entity registry.

For Slice 3, register only entities that exist and need attachments:

- `customer_receipt`
- `supplier_payment`

Do not add cheque or bank reconciliation attachment types in Slice 3.

Unknown entity types remain deny-by-default.

## AUDIT

Audit through `AuditLogger`:

- customer receipt create/update/cancel/post
- supplier payment create/update/cancel/post

Tests should assert new audit records are visible in the active Spatie Activitylog-backed audit path used by the current app.

Do not write new Phase 3 audit rows directly to legacy `audit_log`.

## REQUIRED TESTS

Add focused tests for:

- new tables exist
- no `company_id`, `branch_id`, `tenant_id`
- Spatie teams remain disabled
- no allocation table is introduced in Slice 3
- receipt requires valid customer
- payment requires valid supplier
- receipt/payment requires exactly one of cash_account_id or bank_account_id
- receipt/payment amount must be positive integer minor units
- currency must exist
- selected CashAccount/BankAccount must be active
- selected CashAccount/BankAccount currency must match document currency
- mapped AR/AP control account must exist, be active, and match document currency
- posting fails when financial period is closed
- posting fails when date is outside financial period
- posting fails when financial period does not belong to selected fiscal year
- customer receipt posts balanced JournalEntry through PostingEngine
- supplier payment posts balanced JournalEntry through PostingEngine
- customer receipt creates exactly one ReceivableEntry credit
- supplier payment creates exactly one PayableEntry debit
- receipt sets `allocated_minor = 0` and `unapplied_minor = amount_minor`
- payment sets `allocated_minor = 0` and `unapplied_minor = amount_minor`
- customer AR subledger remains reconciled to AR control GL after receipt posting
- supplier AP subledger remains reconciled to AP control GL after payment posting
- repeated receipt post command is idempotent
- repeated payment post command is idempotent
- concurrent receipt post attempts create exactly one journal and one subledger entry
- concurrent payment post attempts create exactly one journal and one subledger entry
- draft cancellation does not create journal/ledger/subledger rows if implemented
- audit records are written through current `AuditLogger`
- attachment registry accepts only explicit Slice 3 entities

Do not weaken existing tests.

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
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

If UI files are not touched, `npm run typecheck` and `npm run build` should still pass.

## REQUIRED FINAL REPORT

Report:

1. Files changed.
2. Migrations added.
3. Schema summary.
4. Models and relationships.
5. Numbering keys and formatting.
6. Posting behavior.
7. Unapplied amount behavior.
8. Subledger-to-GL reconciliation behavior.
9. RBAC additions.
10. Attachment registry additions.
11. Audit behavior and confirmation it uses Spatie Activitylog through `AuditLogger`.
12. Confirmation no Company/Branch/Tenant scope was introduced.
13. Confirmation no allocation engine/table was introduced.
14. Tests added.
15. Full verification command results.
16. Remaining deferred Phase 3 slices.

Explicitly confirm:

```text
Slice implemented: Phase 3 Slice 3 only
Receipts implemented: YES
Payments implemented: YES
Allocations implemented: NO
Cheques implemented: NO
Bank reconciliation implemented: NO
Sales/Purchasing/Inventory implemented: NO
Generic manual AR/AP adjustments implemented: NO
Posted reversal with allocation semantics implemented: NO
company_id introduced: NO
branch_id introduced: NO
tenant_id introduced: NO
Spatie teams enabled: NO
Audit backend changed away from Spatie Activitylog: NO
New idempotency system introduced: NO
```
