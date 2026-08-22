# MINI ERP - PHASE 4 SLICE 10 SETTLEMENT CORRECTION PASS

You are continuing the existing Mini ERP Laravel + Inertia migration.

Execute only this bounded correction pass.

This is not a new phase and not a new business module.

## Why This Pass Exists

`PHASE_4_SLICE_10_FINAL_REPORT.md` marks Phase 4 Slice 10 complete, but also reports one intentional skipped test and remaining risk:

- manual settlement/allocation of note-created entries is not implemented;
- `ReceivableAllocationService` and `PayableAllocationService` remain receipt/payment-bound;
- `Phase4Slice10ReturnsCreditNotesTest::test_manual_settlement_allocates_credit_against_invoice_debit` is skipped.

The project owner explicitly requires settlement to be accounted for.

Therefore, close this gap before treating Slice 10 as fully closed.

## Read First

- `PHASE_4_SLICE_10_FINAL_REPORT.md`
- `PHASE_4_SLICE_10_GEMINI_PROMPT.md`
- `PHASE_4_RETURNS_CREDIT_DEBIT_DECISION.md`
- `CONTINUE_HERE.md`
- `NEXT_TASKS.md`
- `IMPLEMENTATION_STATUS.md`
- `laravel/app/Application/Accounting/ReceivableAllocationService.php`
- `laravel/app/Application/Accounting/PayableAllocationService.php`
- `laravel/database/migrations/2026_08_21_220000_create_phase3_slice4_allocation_tables.php`
- `laravel/tests/Feature/Phase4Slice10ReturnsCreditNotesTest.php`
- Phase 3/4 AR/AP reports and allocation pages/services.

Use current Laravel code as source of truth.

## Non-Negotiable Rules

Do not introduce:

- tenant/company/branch scope;
- `company_id`, `branch_id`, or `tenant_id`;
- Spatie Teams;
- warehouse/location semantics;
- VAT filing/reporting;
- new GL posting for settlement;
- mutation of posted invoices, bills, journals, ledger entries, receivable/payable entries, or stock movement ledger rows.

Preserve:

- single-installation ERP context;
- Spatie Activitylog through `AuditLogger`;
- manual/open settlement only;
- idempotency;
- deterministic locking;
- integer minor-unit money only;
- no floats, no `(float)`, no `round()`.

## Objective

Implement real manual settlement of note-created AR/AP entries:

1. Customer Credit Note settlement:
   - settle a posted `customer_credit_note` receivable credit entry against the original posted invoice receivable debit entry or another open customer receivable debit entry;
   - same customer and same currency only;
   - no automatic settlement; user action required;
   - no GL/journal/ledger creation on settlement.

2. Supplier Adjustment Note settlement:
   - settle a posted `supplier_adjustment_note` payable debit entry against the original supplier bill payable credit entry or another open supplier payable credit entry;
   - same supplier and same currency only;
   - no automatic settlement; user action required;
   - no GL/journal/ledger creation on settlement.

3. Reports and open balances:
   - AR/AP open balances, aging, statements, and GL reconciliation must account for these entry-to-entry settlements.

## Recommended Safe Schema

Prefer adding dedicated settlement tables instead of weakening the existing receipt/payment allocation tables.

### `receivable_entry_settlement`

Columns:

- `id` UUID primary key
- `customer_id` FK to `customer`
- `source_receivable_entry_id` FK to `receivable_entry`
  - must be a credit/open-negative AR item, usually `source_type = customer_credit_note`
- `target_receivable_entry_id` FK to `receivable_entry`
  - must be a debit/open-positive AR item, usually `source_type = customer_invoice`
- `currency` string(3) FK to `currency(code)`
- `amount_minor` positive bigInteger
- `status` string: `active`, `reversed`
- `settled_at` timestamp
- `reversed_at` nullable timestamp
- `reason` nullable text
- `reversed_reason` nullable text
- `created_by` nullable FK users
- `reversed_by` nullable FK users
- timestamps

Indexes:

- (`customer_id`, `settled_at`)
- (`source_receivable_entry_id`, `status`)
- (`target_receivable_entry_id`, `status`)
- `currency`

### `payable_entry_settlement`

Columns:

- `id` UUID primary key
- `supplier_id` FK to `supplier`
- `source_payable_entry_id` FK to `payable_entry`
  - must be a debit/open-negative AP item, usually `source_type = supplier_adjustment_note` with `direction = decrease_payable`
- `target_payable_entry_id` FK to `payable_entry`
  - must be a credit/open-positive AP item, usually `source_type = supplier_bill`
- `currency` string(3) FK to `currency(code)`
- `amount_minor` positive bigInteger
- `status` string: `active`, `reversed`
- `settled_at` timestamp
- `reversed_at` nullable timestamp
- `reason` nullable text
- `reversed_reason` nullable text
- `created_by` nullable FK users
- `reversed_by` nullable FK users
- timestamps

Indexes:

- (`supplier_id`, `settled_at`)
- (`source_payable_entry_id`, `status`)
- (`target_payable_entry_id`, `status`)
- `currency`

If you choose to extend existing allocation tables instead, justify why it is safer than dedicated settlement tables and preserve all existing Phase 3 receipt/payment allocation behavior.

## Services

Add or extend application services:

- `ReceivableEntrySettlementService`
- `PayableEntrySettlementService`

Required methods:

- `settleCredit(string $sourceCreditEntryId, array $lines, int $actorId, ?string $idempotencyKey = null)`
- `reverseSettlement(string $settlementId, string $reason, int $actorId, ?string $idempotencyKey = null)`
- payable equivalents.

Validation:

- settlement lines cannot be empty;
- no duplicate targets in one command;
- amount must be positive integer minor units;
- source and target must exist;
- source and target must belong to same customer/supplier;
- source and target must have same currency;
- source and target must be opposite economic directions;
- source remaining amount must be enough;
- target remaining amount must be enough;
- cannot settle an entry against itself;
- cannot settle draft/cancelled/unposted note-created entries.

Locking:

- use DB transaction;
- lock source entry and target entries deterministically by ID;
- lock active settlement rows for source and target before computing remaining balances;
- protect concurrent settlement pressure from over-settling either side;
- idempotent replay must create the same settlements only once.

Auditing:

- every create/reverse settlement writes via `AuditLogger`;
- no direct writes to legacy `audit_log`.

## Reports / Balance Helpers

Create or update central helpers so all reports use one open-balance calculation.

For AR:

- debit entry open amount = debit - credit - receipt allocations - target settlements;
- credit entry remaining amount = credit - debit - source settlements;
- net AR = sum debit opens - credit opens.

For AP:

- credit entry open amount = credit - debit - payment allocations - target settlements;
- debit entry remaining amount = debit - credit - source settlements;
- net AP = sum credit opens - debit opens.

Update at minimum:

- AR Aging
- AP Aging
- Customer Statement
- Supplier Statement
- AR to GL Reconciliation
- AP to GL Reconciliation
- any dashboard/report service that shows AR/AP open balances.

Settlement must not affect GL totals. It only changes subledger open/settled presentation.

## UI / Routes

Add compact ERP-style manual settlement actions:

- from Customer Credit Notes page: "Settle" action for posted notes with remaining credit;
- from Supplier Adjustment Notes page: "Settle" action for posted decrease-payable notes with remaining debit;
- optional links from existing Receivable/Payable Allocation pages if consistent with current navigation.

UI must show:

- source note number;
- remaining source credit/debit;
- eligible target invoices/bills/open entries;
- target remaining amount;
- selected settlement amount;
- validation errors;
- reverse settlement action if existing allocation pages support reversals.

Routes must be permission protected using existing conventions:

- use `sales.credit_notes` or a more specific permission only if local convention requires it;
- use `purchasing.adjustment_notes` for supplier note settlement.

## Tests Required

Remove the skip in:

- `Phase4Slice10ReturnsCreditNotesTest::test_manual_settlement_allocates_credit_against_invoice_debit`

Add tests covering:

### AR settlement

- posted credit note can be settled against original invoice receivable debit;
- settlement creates no journal or ledger entries;
- settlement reduces invoice open balance and credit note remaining balance;
- over-settlement of source credit is rejected;
- over-settlement of target invoice debit is rejected;
- wrong customer/currency is rejected;
- idempotent replay creates one settlement;
- reversal restores open balances.

### AP settlement

- posted supplier adjustment decrease-payable entry can be settled against supplier bill payable credit;
- settlement creates no journal or ledger entries;
- over-settlement and wrong supplier/currency are rejected;
- idempotent replay creates one settlement;
- reversal restores open balances.

### Reports

- AR Aging and Customer Statement reflect note settlement;
- AP Aging and Supplier Statement reflect note settlement;
- AR/AP to GL reconciliation still balances because settlement has no GL effect.

### Concurrency

- concurrent settlement pressure cannot over-settle a single credit note or debit target;
- implement either a focused test or artisan stress command and report it.

### Architecture

- no `company_id`, `branch_id`, `tenant_id`, `currentCompany`, `currentBranch`, `company_user`, or Spatie Teams introduced.

## Verification Gate

Run from `laravel/`:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=Phase4Slice10ReturnsCreditNotesTest
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan accounting:phase3-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

If `concurrency:stress --workers=100` is blocked by Windows paging-file exhaustion, report it explicitly and rerun with the highest supported worker count.

## Documentation Updates

Update:

- `PHASE_4_SLICE_10_FINAL_REPORT.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `CHANGELOG.md`

Docs must say that the previous skipped manual settlement test is now active and passing.

## Required Final Report

Report:

1. Files changed.
2. Migrations added.
3. Schema diff.
4. Settlement services and relationships.
5. AR settlement behavior.
6. AP settlement behavior.
7. Report/open-balance updates.
8. Concurrency protections.
9. Unsupported assumptions avoided.
10. Test results, including the formerly skipped test now passing.
11. Stress results.
12. Remaining risks.

Stop after this correction pass. Do not start Phase 5 or any new ERP module.
