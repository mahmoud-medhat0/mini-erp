# MINI ERP - PHASE 3 SLICE 2 GEMINI PROMPT

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


You are continuing the existing Laravel + Inertia + React Mini ERP repository.

Implement **Phase 3 Slice 2 only**.

Do not implement the whole Phase 3.

Slice 1 is already complete:

- Customer
- Supplier
- CashAccount
- BankAccount
- GL/currency relationships
- optimistic locking
- RBAC permissions
- Spatie Activitylog audit through `AuditLogger`
- attachment registry entries

Read these first:

- `README.md`
- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`
- `PHASE_3_SLICE_1_GEMINI_PROMPT.md`
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

## SLICE 2 OBJECTIVE

Implement only:

1. AR subledger foundation for Customers.
2. AP subledger foundation for Suppliers.
3. Customer opening balance documents.
4. Supplier opening balance documents.
5. Posting customer/supplier opening balances through the existing Accounting Posting Engine.
6. Minimal global accounting account mappings required for Slice 2 posting, if no suitable mapping facility already exists.
7. Tests proving subledger-to-GL reconciliation for opening balances.
8. Audit logging through `AuditLogger`.
9. Attachment registry additions for opening balance entities if appropriate.
10. Docs/status update.

This slice creates opening AR/AP balances only.

## STRICT NON-GOALS

Do not implement:

- receipts
- payments
- allocations
- cheques
- bank reconciliation
- customer/supplier statements UI
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
- frontend pages unless there is an existing pattern that can be extended safely for this exact slice

If tempted to implement any of these, stop.

## EXISTING CODE TO REUSE

Reuse existing Laravel services and invariants wherever possible:

- `App\Application\Accounting\PostingEngine`
- `App\Application\Accounting\OpeningBalanceService` patterns
- `App\Application\Accounting\JournalDraftService` date/period validation where applicable
- `App\Support\Concurrency\DatabaseIdempotencyStore`
- `App\Domain\Audit\AuditLogger`
- existing FinancialPeriod/FiscalYear open-period rules
- existing integer Money/accounting invariant primitives
- existing Spatie Permission RBAC seed flow
- existing attachment entity registry

Do not create parallel ledger or journal logic.

Every accounting effect must produce:

- `journal_entry`
- `journal_line`
- `ledger_entry`
- AR/AP subledger entry
- audit record through `AuditLogger`

## ACCOUNTING MAPPINGS

Opening balances require configured GL accounts.

For Customer opening balance:

```text
Dr AR Control Account
Cr Opening Balance Offset Account
```

For Supplier opening balance:

```text
Dr Opening Balance Offset Account
Cr AP Control Account
```

Do not hardcode account IDs.

First inspect whether a suitable global posting/account mapping table or config already exists.

If no suitable facility exists, add a minimal global table for accounting mappings, for example:

- `accounting_account_mapping`

Minimum fields:

- `id` uuid primary key
- `key` string unique
- `account_id` uuid FK to `account.id`
- `description` nullable
- `is_system` boolean default false
- `created_by` nullable FK to users
- `updated_by` nullable FK to users
- timestamps

Allowed keys for Slice 2 only:

- `ar_control`
- `ap_control`
- `opening_balance_offset`

Do not add cash, bank, cheque, sales, purchasing, inventory, VAT, payroll, or branch/company mapping keys in this slice.

Validation rules:

- mapped account must exist
- mapped account must be active
- AR Control should be an asset account
- AP Control should be a liability account
- Opening Balance Offset should be equity or another explicitly named opening-offset account category already supported by the chart of accounts
- no Company/Branch/Tenant fields
- no browser-provided account ID may override trusted backend mappings during posting

If exact account category validation cannot be determined from current account types/categories, validate active account existence and document the remaining classification risk in the final report.

## EXPECTED TABLES

Use singular table names consistent with current Laravel schema style.

Expected new tables:

- `receivable_entry`
- `payable_entry`
- `customer_opening_balance`
- `supplier_opening_balance`
- `accounting_account_mapping` only if no suitable existing mapping table exists

No new table may contain:

- `company_id`
- `branch_id`
- `tenant_id`

## RECEIVABLE ENTRY

This is the Customer AR subledger entry table.

Minimum fields:

- `id` uuid primary key
- `customer_id` uuid FK to `customer.id`
- `source_type` string
- `source_id` uuid/string
- `journal_entry_id` uuid FK to `journal_entry.id`
- `journal_line_id` uuid nullable FK to `journal_line.id`
- `financial_period_id` uuid FK to `financial_period.id`
- `entry_date` date
- `due_date` nullable date
- `description` nullable text
- `currency` char/string(3), FK to `currency.code`
- `debit_minor` bigint default 0
- `credit_minor` bigint default 0
- `debit_txn_minor` bigint default 0
- `credit_txn_minor` bigint default 0
- `fx_rate_e6` bigint default 1000000
- `created_by` nullable FK to users
- timestamps

Rules:

- source type for this slice is only `customer_opening_balance`
- exactly one side must be positive for each row
- no negative amounts
- no mutable `remaining_minor`
- no allocation fields in Slice 2
- subledger rows should not be updated/deleted by normal application flows

## PAYABLE ENTRY

This is the Supplier AP subledger entry table.

Minimum fields:

- `id` uuid primary key
- `supplier_id` uuid FK to `supplier.id`
- `source_type` string
- `source_id` uuid/string
- `journal_entry_id` uuid FK to `journal_entry.id`
- `journal_line_id` uuid nullable FK to `journal_line.id`
- `financial_period_id` uuid FK to `financial_period.id`
- `entry_date` date
- `due_date` nullable date
- `description` nullable text
- `currency` char/string(3), FK to `currency.code`
- `debit_minor` bigint default 0
- `credit_minor` bigint default 0
- `debit_txn_minor` bigint default 0
- `credit_txn_minor` bigint default 0
- `fx_rate_e6` bigint default 1000000
- `created_by` nullable FK to users
- timestamps

Rules:

- source type for this slice is only `supplier_opening_balance`
- exactly one side must be positive for each row
- no negative amounts
- no mutable `remaining_minor`
- no allocation fields in Slice 2
- subledger rows should not be updated/deleted by normal application flows

## CUSTOMER OPENING BALANCE

Minimum fields:

- `id` uuid primary key
- `customer_id` uuid FK to `customer.id`
- `fiscal_year_id` uuid FK to `fiscal_year.id`
- `financial_period_id` uuid FK to `financial_period.id`
- `entry_date` date
- `due_date` nullable date
- `reference` nullable or required string, unique where appropriate
- `description` nullable text
- `currency` char/string(3), FK to `currency.code`
- `amount_minor` bigint positive
- `fx_rate_e6` bigint default 1000000
- `status` string default `draft`
- `journal_entry_id` nullable uuid FK to `journal_entry.id`
- `receivable_entry_id` nullable uuid FK to `receivable_entry.id`
- `created_by`, `updated_by`, `posted_by` nullable FK to users
- `posted_at` nullable timestamp
- `lock_version` unsigned integer default 0 if using existing optimistic locking
- timestamps

Allowed statuses for this slice:

- `draft`
- `posted`
- `cancelled` only if no posting exists

Do not implement reversal unless it can be implemented safely through existing reversal patterns without starting allocation/payment logic.

If reversal is not implemented, explicitly document it as deferred to a later Phase 3 slice.

Rules:

- one active customer opening balance per customer/fiscal year unless existing requirements prove otherwise
- entry_date must belong to the selected financial period
- financial period must be open at posting time
- amount must be integer minor units and greater than zero
- currency must exist
- no float math
- no direct balance mutation on customer

## SUPPLIER OPENING BALANCE

Mirror Customer Opening Balance:

- `id`
- `supplier_id`
- `fiscal_year_id`
- `financial_period_id`
- `entry_date`
- `due_date`
- `reference`
- `description`
- `currency`
- `amount_minor`
- `fx_rate_e6`
- `status`
- `journal_entry_id`
- `payable_entry_id`
- provenance fields
- `lock_version`
- timestamps

Rules:

- one active supplier opening balance per supplier/fiscal year unless existing requirements prove otherwise
- entry_date must belong to the selected financial period
- financial period must be open at posting time
- amount must be integer minor units and greater than zero
- currency must exist
- no float math
- no direct balance mutation on supplier

## POSTING BEHAVIOR

Customer opening balance posting:

1. Lock the opening balance row.
2. Recheck status is `draft`.
3. Lock/recheck FinancialPeriod is open.
4. Resolve trusted backend mappings:
   - `ar_control`
   - `opening_balance_offset`
5. Create approved JournalEntry with source:
   - `source_type = customer_opening_balance`
   - `source_id = opening_balance.id`
6. Create JournalLine rows:
   - Dr AR Control
   - Cr Opening Balance Offset
7. Post through `PostingEngine` with explicit trusted control-account allowance where needed.
8. Create one `receivable_entry` linked to the customer and posted journal/line.
9. Mark opening balance as posted.
10. Audit through `AuditLogger`.

Supplier opening balance posting:

1. Lock the opening balance row.
2. Recheck status is `draft`.
3. Lock/recheck FinancialPeriod is open.
4. Resolve trusted backend mappings:
   - `ap_control`
   - `opening_balance_offset`
5. Create approved JournalEntry with source:
   - `source_type = supplier_opening_balance`
   - `source_id = opening_balance.id`
6. Create JournalLine rows:
   - Dr Opening Balance Offset
   - Cr AP Control
7. Post through `PostingEngine` with explicit trusted control-account allowance where needed.
8. Create one `payable_entry` linked to the supplier and posted journal/line.
9. Mark opening balance as posted.
10. Audit through `AuditLogger`.

## IDEMPOTENCY AND CONCURRENCY

Use the existing `DatabaseIdempotencyStore`.

Do not create a new idempotency system.

Required behavior:

- repeated post command for the same opening balance returns the same durable result
- concurrent post attempts for the same opening balance create exactly one journal and one subledger entry
- failed posting must not leave orphan journal/subledger rows
- use DB transactions and row locks
- lock order must be deterministic:
  1. opening balance row
  2. financial period row
  3. mapping/account rows as needed

Add PostgreSQL-focused tests or extend existing tests to prove the critical idempotency path.

## MONEY AND FX

Amounts must be stored as integer minor units.

Do not use:

- float
- `(float)`
- `round()` for money conversion
- binary floating-point arithmetic

For Slice 2, prefer same-currency opening balances unless an exact integer FX path already exists.

If supporting non-base currency:

- require `fx_rate_e6`
- compute base minor amounts using exact integer arithmetic only
- preserve transaction-currency minor amounts in journal and subledger rows

If exact FX behavior is not already clear, restrict posting to matching account/document currency and document the limitation.

## MODELS AND RELATIONSHIPS

Create models as needed:

- `App\Models\ReceivableEntry`
- `App\Models\PayableEntry`
- `App\Models\CustomerOpeningBalance`
- `App\Models\SupplierOpeningBalance`
- `App\Models\AccountingAccountMapping` only if the mapping table is added

Expected relationships:

- Customer has many receivable entries
- Supplier has many payable entries
- Customer has many customer opening balances
- Supplier has many supplier opening balances
- CustomerOpeningBalance belongs to Customer
- SupplierOpeningBalance belongs to Supplier
- ReceivableEntry belongs to Customer, JournalEntry, JournalLine, FinancialPeriod, Currency
- PayableEntry belongs to Supplier, JournalEntry, JournalLine, FinancialPeriod, Currency
- AccountingAccountMapping belongs to Account

Do not add Company/Branch relationships.

## SERVICES / CONTROLLERS / ACTIONS

Implement service layer first.

Suggested services:

- `CustomerOpeningBalanceService`
- `SupplierOpeningBalanceService`
- `ArApSubledgerService` or separate receivable/payable services if clearer
- `AccountingAccountMappingService` only if adding mapping table

If adding controllers/routes:

- require explicit permissions
- use server-side validation
- never trust account IDs from browser for posting mappings
- audit create/update/post/cancel actions
- do not build receipt/payment/allocation/cheque actions

If deferring UI/controllers to later slices, add model/service-level tests and document that UI comes later.

## RBAC

Extend `config/erp_rbac.php` only as needed.

Suggested permissions:

- `customers.opening_balances`
- `suppliers.opening_balances`
- `accounting.mappings` if adding mapping management

Do not add branch/company scoped permissions.

Update seeders/tests so permissions are registered.

## ATTACHMENTS

Reuse the existing attachment entity registry.

For Slice 2, register only entities that exist and need attachments:

- `customer_opening_balance`
- `supplier_opening_balance`

Do not add receipt, payment, cheque, or bank reconciliation attachment types in Slice 2.

Unknown entity types remain deny-by-default.

## AUDIT

Audit through `AuditLogger`:

- accounting mapping create/update if mapping management is added
- customer opening balance create/update/cancel/post
- supplier opening balance create/update/cancel/post

Tests should assert new audit records are visible in the active Spatie Activitylog-backed audit path used by the current app.

Do not write new Phase 3 audit rows directly to legacy `audit_log`.

## REQUIRED TESTS

Add focused tests for:

- new tables exist
- no `company_id`, `branch_id`, `tenant_id`
- Spatie teams remain disabled
- mapping table has only global keys if added
- posting fails when AR/AP/offset mappings are missing
- posting fails when mapped account is inactive
- posting fails when financial period is closed
- posting fails when entry_date is outside financial period
- customer opening balance requires valid customer
- supplier opening balance requires valid supplier
- opening balance amount must be positive integer minor units
- currency must exist
- customer opening balance posts balanced JournalEntry through PostingEngine
- supplier opening balance posts balanced JournalEntry through PostingEngine
- customer opening balance creates exactly one ReceivableEntry
- supplier opening balance creates exactly one PayableEntry
- customer receivable subledger total reconciles to AR control ledger effect for opening balances
- supplier payable subledger total reconciles to AP control ledger effect for opening balances
- repeated post command is idempotent
- concurrent post attempts for the same opening balance create exactly one journal and one subledger entry
- audit records are written through current `AuditLogger`
- attachment registry accepts only explicit Slice 2 entities

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
5. Mapping behavior and configured keys.
6. Posting behavior.
7. Subledger-to-GL reconciliation behavior.
8. RBAC additions.
9. Attachment registry additions.
10. Audit behavior and confirmation it uses Spatie Activitylog through `AuditLogger`.
11. Confirmation no Company/Branch/Tenant scope was introduced.
12. Tests added.
13. Full verification command results.
14. Remaining deferred Phase 3 slices.

Explicitly confirm:

```text
Slice implemented: Phase 3 Slice 2 only
Customer/Supplier master data changed beyond needed relationships: NO
Receipts implemented: NO
Payments implemented: NO
Allocations implemented: NO
Cheques implemented: NO
Bank reconciliation implemented: NO
Sales/Purchasing/Inventory implemented: NO
Generic manual AR/AP adjustments implemented: NO
company_id introduced: NO
branch_id introduced: NO
tenant_id introduced: NO
Spatie teams enabled: NO
Audit backend changed away from Spatie Activitylog: NO
New idempotency system introduced: NO
```
