# MINI ERP - PHASE 4 SLICE 8 MOVING WEIGHTED AVERAGE INVENTORY COSTING

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


You are continuing the existing Mini ERP Laravel + Inertia migration.

This is a bounded implementation slice.

The owner selected **Option 1: Moving Weighted Average Costing** on 2026-08-22.

Implement only Moving Weighted Average. Do not implement FIFO, Standard Costing, or Non-Valued alternate branches.

## Current Baseline

The Laravel target is complete through:

- M10 Spatie Activitylog audit backend, scheduler, and jobs baseline.
- Phase 3 Slices 1-10 AR/AP + Cash/Bank/Cheques.
- Phase 4 Slice 1 Product/Service Catalog Foundation.
- Phase 4 Slice 2 Sales Orders.
- Phase 4 Slice 3 Purchase Orders.
- Phase 4 Slice 4 Delivery Notes & Goods Receipts.
- Phase 4 Slice 5 Customer Invoice Posting to AR/GL.
- Phase 4 Slice 6 Supplier Bill Posting to AP/GL.
- Phase 4 Slice 7 Inventory Costing Decision Pack.

Use these files first:

- `PHASE_4_INVENTORY_COSTING_DECISION.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `laravel/app/Models/Product.php`
- `laravel/app/Application/Purchasing/GoodsReceiptService.php`
- `laravel/app/Application/Sales/DeliveryNoteService.php`
- `laravel/app/Application/Purchasing/SupplierBillService.php`
- `laravel/app/Application/Sales/CustomerInvoiceService.php`
- `laravel/app/Application/Accounting/AccountingAccountMappingService.php`
- `laravel/app/Application/Accounting/PostingEngine.php`
- relevant Phase 4 migrations and tests

## Non-Negotiable Rules

Do not introduce:

- tenant context or tenant middleware
- `company_id`, `branch_id`, or `tenant_id`
- `company_user`
- `users.company_id`
- `branch.company_id`
- `fiscal_year.company_id`
- `currentCompany` or `currentBranch`
- Spatie Teams
- company/branch dimensions in number sequences
- warehouse-to-branch relationship
- branch/location authorization

Preserve:

- single-installation ERP context
- Spatie Permission with teams disabled
- Spatie Activitylog through the existing `AuditLogger`
- append-only audit behavior
- attachment authorization through entity registry/service
- atomic global document numbering by key
- idempotent actions and posting
- integer minor-unit money only
- immutable posted accounting records
- corrections through reversal/correction documents, not ledger mutation

## Hard Scope Boundary

Implement:

- Moving Weighted Average stock costing only.
- Stock balance and append-only stock movement ledger.
- GL mappings for selected model only:
  - `inventory_asset`
  - `grni_clearing`
  - `cogs`
- Goods Receipt stock receipt financial posting.
- Delivery Note stock issue and COGS financial posting.
- Supplier Bill stock-product behavior through GRNI clearing.
- Customer Invoice stock-product behavior when sourced from Delivery Note.
- Concurrency/idempotency hardening for stock movements.

Do not implement:

- FIFO layers.
- Standard Costing.
- Non-Valued alternate branch.
- `purchase_price_variance`.
- landed cost or freight allocation.
- VAT/tax.
- returns, credit notes, or debit notes.
- warehouses, locations, bins, batches, serial numbers, or expiry dates.
- stock adjustments or stock counts unless strictly needed for tests.
- retroactive valuation of historical confirmed documents.
- financial statements.
- broad reports or broad UI dashboards.

## Selected Costing Model

Use Moving Weighted Average with these rules:

1. `stock_balance.valuation_amount_minor` is the authoritative inventory value.
2. `stock_balance.quantity_e6` is the authoritative physical quantity.
3. Average unit cost is derived from balance value and quantity. If stored, it must be treated as a derived/cache/display value, not as the source of truth.
4. Receipt value is calculated exactly from source quantity and unit cost:

```php
$lineValueMinor = intdiv($quantityE6 * $unitCostMinor, 1000000);
```

Reject the operation if:

```php
($quantityE6 * $unitCostMinor) % 1000000 !== 0
```

5. Issue cost must avoid fractional minor units and preserve total valuation:

```php
if ($issueQuantityE6 === $currentQuantityE6) {
    $issueCostMinor = $currentValuationAmountMinor;
} else {
    $issueCostMinor = intdiv($issueQuantityE6 * $currentValuationAmountMinor, $currentQuantityE6);
}
```

The remaining residual value stays in inventory and is cleared by the final issue.

6. Prevent negative stock:

```text
issue_quantity_e6 <= current_stock_quantity_e6
```

7. No floats, no `round()`, no binary floating-point arithmetic, and no float division in backend inventory logic or tests.

8. Multi-currency valuation is not part of this slice. Keep stock valuation currency explicit and simple:
   - include `currency` on stock balance and stock movement rows;
   - require stock-related document currency and mapped GL account currencies to match;
   - reject stock valuation operations that would mix currencies for the same product while a balance exists;
   - do not implement FX revaluation or currency conversion.

## Database Schema

Create a forward migration for selected-model inventory tables.

Use singular table naming style consistent with the existing schema.

### `stock_balance`

Required columns:

- `id` UUID primary key
- `product_id` UUID, FK to `product`, restrict on delete
- `unit_of_measure_id` UUID, FK to `unit_of_measure`, restrict on delete
- `currency` string(3), FK to `currency(code)`, restrict on delete
- `quantity_e6` bigInteger, default 0
- `valuation_amount_minor` bigInteger, default 0
- `avg_unit_cost_e6` bigInteger, default 0, derived/cache value in minor-units scaled to 6 decimals
- `lock_version` integer, default 1
- timestamps

Indexes/constraints:

- unique product balance for the selected valuation currency strategy. Prefer `unique(product_id)` if you enforce one valuation currency per product; otherwise `unique(product_id, currency)`.
- checks preventing negative `quantity_e6` and `valuation_amount_minor`.
- no company/branch/tenant columns.

### `stock_movement_ledger`

Required columns:

- `id` UUID primary key
- `movement_date` date
- `source_type` string
- `source_id` UUID
- `source_line_id` UUID nullable
- `movement_type` string: `receipt`, `issue`, or `reversal`
- `product_id` UUID, FK to `product`, restrict on delete
- `unit_of_measure_id` UUID, FK to `unit_of_measure`, restrict on delete
- `currency` string(3), FK to `currency(code)`, restrict on delete
- `quantity_delta_e6` bigInteger
  - positive for receipt
  - negative for issue
- `value_delta_minor` bigInteger
  - positive for receipt
  - negative for issue
- `unit_cost_e6` bigInteger, derived/cache value in minor-units scaled to 6 decimals
- `balance_quantity_e6` bigInteger snapshot after movement
- `balance_valuation_amount_minor` bigInteger snapshot after movement
- `journal_entry_id` UUID nullable, FK to `journal_entry`, restrict or set null only if consistent with existing accounting immutability
- `created_by` nullable FK to users
- timestamps

Indexes/constraints:

- unique idempotency guard on source movement identity. A source line must not produce duplicate stock movements for the same movement type.
- movement quantity/value cannot be zero.
- no UPDATE/DELETE allowed after insert. Add PostgreSQL and SQLite immutability triggers like the existing ledger/audit pattern.
- no company/branch/tenant columns.

## Accounting Mappings

Extend `AccountingAccountMappingService` and related migration constraints for:

- `inventory_asset`: account type `asset`, nature `debit`
- `grni_clearing`: account type `liability`, nature `credit`
- `cogs`: account type `expense`, nature `debit`

Do not add `purchase_price_variance`.

Update local/testing/demo seeders idempotently:

- create or reuse account `1300` Inventory Asset, asset/debit
- create or reuse account `2200` GRNI Clearing, liability/credit
- create or reuse account `5200` Cost of Goods Sold, expense/debit
- map the three new keys to those accounts

Keep existing mappings:

- `ar_control`
- `ap_control`
- `sales_revenue`
- `purchase_expense`
- Phase 3 cheque/opening-balance mappings

## Application Services

Create a dedicated inventory application service, for example:

- `App\Application\Inventory\MovingWeightedAverageInventoryService`

Responsibilities:

1. Lock stock balance rows deterministically with `lockForUpdate()`.
2. Create missing zero balances safely inside a transaction.
3. Record stock receipts from confirmed Goods Receipt lines.
4. Record stock issues from confirmed Delivery Note lines.
5. Calculate receipt value and issue COGS with exact integer math.
6. Create immutable stock movement ledger rows.
7. Create/post accounting journal entries through the existing `PostingEngine`.
8. Write audit records via `AuditLogger`.
9. Preserve idempotency: replaying the same source line returns existing movement/posting instead of duplicating rows.

## Goods Receipt Integration

Update `GoodsReceiptService::confirm()`:

- Existing non-stock/service behavior remains valid.
- For stock lines only:
  - source cost comes from linked Purchase Order line `unit_price_minor`;
  - calculate receipt value exactly;
  - increment stock balance quantity and valuation;
  - derive/cache average cost after receipt;
  - append `stock_movement_ledger` receipt rows;
  - create approved JournalEntry and post through PostingEngine:

```text
Dr inventory_asset
Cr grni_clearing
```

- Use receipt date as movement/accounting date.
- Financial period must be open and must cover receipt date.
- All mapped account currencies must match movement currency.
- Confirm replay must not duplicate stock movements or journal entries.
- Do not retro-post stock movements for already confirmed historical Goods Receipts; if status is already confirmed, return the existing document.

## Delivery Note Integration

Update `DeliveryNoteService::confirm()`:

- Existing non-stock/service behavior remains valid.
- For stock lines only:
  - require sufficient stock balance before confirmation;
  - calculate COGS using the selected residual-safe issue formula;
  - decrement stock balance quantity and valuation;
  - derive/cache average cost after issue;
  - append `stock_movement_ledger` issue rows;
  - create approved JournalEntry and post through PostingEngine:

```text
Dr cogs
Cr inventory_asset
```

- Use delivery date as movement/accounting date.
- Financial period must be open and must cover delivery date.
- All mapped account currencies must match movement currency.
- Confirm replay must not duplicate stock movements or journal entries.
- Do not allow negative stock.
- Do not retro-post stock movements for already confirmed historical Delivery Notes; if status is already confirmed, return the existing document.

## Supplier Bill Integration

Update `SupplierBillService`:

- Keep existing service and non-stock bill behavior:

```text
Dr purchase_expense
Cr ap_control
```

- Allow stock bill lines only when sourced from a confirmed Goods Receipt line.
- Reject manual stock bill lines.
- Reject stock bill lines sourced only from Purchase Order without Goods Receipt.
- In this slice, reject stock bill unit-cost mismatches against the source Purchase Order / Goods Receipt cost. Do not implement purchase price variance or landed cost.
- For stock bill posting:

```text
Dr grni_clearing
Cr ap_control
```

- For mixed bills:
  - stock lines debit `grni_clearing`;
  - service/non-stock lines debit `purchase_expense`;
  - one AP control credit equals total bill amount.
- Preserve exact integer totals, source matching, idempotency, and lock_version behavior.

## Customer Invoice Integration

Update `CustomerInvoiceService`:

- Keep existing service and non-stock invoice behavior:

```text
Dr ar_control
Cr sales_revenue
```

- Allow stock invoice lines only when sourced from a confirmed Delivery Note line.
- Reject manual stock invoice lines.
- Reject stock invoice lines sourced only from Sales Order without Delivery Note.
- Customer Invoice posting for stock lines still posts revenue/AR only. COGS is posted by Delivery Note confirmation.
- Preserve exact integer totals, source matching, idempotency, and lock_version behavior.

## Models And Relationships

Create models:

- `StockBalance`
- `StockMovementLedger`

Relationships:

- StockBalance belongs to Product, UnitOfMeasure, Currency.
- StockMovementLedger belongs to Product, UnitOfMeasure, Currency, JournalEntry, User.
- Product has one/many stock balance relation according to selected currency strategy.
- Product has many stock movements.

Do not add company/branch/tenant relationships.

## UI Scope

Do not build a broad inventory UI in this slice.

Optional minimal read-only Inertia page is allowed only if small and useful:

- Stock Balances list
- Product, quantity, valuation, average cost, currency

If added, it must be permission protected and bilingual EN/AR. Do not add warehouse/location screens.

## RBAC

If new permissions are needed, keep them global and non-team:

- `inventory.view`
- `inventory.manage` only if needed for future adjustments, but do not implement adjustments in this slice

Use existing sales/purchasing/accounting permissions for document actions where appropriate.

## Tests Required

Add feature/unit coverage proving:

1. Inventory tables exist and contain no company/branch/tenant columns.
2. Stock movement ledger is append-only at DB level on PostgreSQL and SQLite.
3. Accounting mapping keys `inventory_asset`, `grni_clearing`, `cogs` are allowed and validate account type/nature.
4. Goods Receipt confirmation for stock line:
   - creates stock balance;
   - appends receipt movement;
   - posts Dr Inventory Asset / Cr GRNI Clearing;
   - updates moving average valuation.
5. Multiple receipts update weighted average correctly:
   - example: receive 10 units at 100 minor, then 10 units at 200 minor => quantity 20 units, valuation 3000 minor, derived average 150 minor.
6. Delivery Note confirmation:
   - rejects insufficient stock;
   - appends issue movement;
   - posts Dr COGS / Cr Inventory Asset;
   - decrements quantity and valuation;
   - final issue clears residual valuation exactly.
7. Goods Receipt confirmation replay is idempotent.
8. Delivery Note confirmation replay is idempotent.
9. Supplier Bill:
   - allows stock line from Goods Receipt;
   - rejects manual stock bill;
   - rejects PO-only stock bill;
   - rejects stock source unit-cost mismatch;
   - posts Dr GRNI / Cr AP for stock lines;
   - supports mixed stock plus service/non-stock bill lines.
10. Customer Invoice:
    - allows stock line from Delivery Note;
    - rejects manual stock invoice;
    - rejects SalesOrder-only stock invoice;
    - posts AR/Sales only for invoice; COGS remains tied to Delivery Note.
11. No duplicate stock movements or journal entries under idempotent replay.
12. Source scan verifies no `round(`, `(float)`, `float`, `double`, `/ 1000000`, or `/1000000` in authoritative backend inventory costing code and related tests. Build the forbidden strings dynamically in tests to avoid false positives.
13. No unsupported relationship/scope terms introduced.

## Concurrency Stress

Add a PostgreSQL stress command, for example:

```powershell
php artisan accounting:inventory-concurrency-stress --workers=50
```

It must verify:

- concurrent receipts for the same product produce correct final quantity and valuation;
- concurrent delivery attempts cannot drive stock negative;
- idempotent replay creates exactly one stock movement per source line;
- no deadlocks or lock timeout failures under normal test pressure.

Keep the command cleanup compatible with append-only stock movement ledger behavior.

## Verification Commands

Run from `laravel/`:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=Phase4Slice8InventoryCostingTest
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan accounting:phase3-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Also run targeted source scans for forbidden binary floating-point patterns in the new inventory application code and tests.

## Documentation Updates

After implementation, update:

- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `CHANGELOG.md`
- `README.md`

Do not mark FIFO, Standard Costing, Non-Valued tracking, returns, credit notes, debit notes, landed cost, tax, or warehouse/location management as implemented.

## Final Report Required

Report:

1. Files changed.
2. Migrations added.
3. Schema diff.
4. Relationships added and evidence for each.
5. Moving Weighted Average math implementation details.
6. Accounting mappings added.
7. Posting flows implemented.
8. Unsupported assumptions avoided.
9. Remaining company/branch/tenant occurrences introduced by the slice, if any, with explicit justification.
10. Test results.
11. Concurrency stress results.
12. Source scan results for forbidden float/rounding patterns.
13. Remaining risks.

Do not start Phase 4 Slice 9.
