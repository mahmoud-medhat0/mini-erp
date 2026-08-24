# PHASE 4 SLICE 2 CORRECTION - SALES ORDER INTEGER TOTALS

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


You are continuing the active Laravel + Inertia Mini ERP migration.

Execute only this correction pass for Phase 4 Slice 2.

Do not start Phase 4 Slice 3. Do not implement Purchase Orders, Delivery Notes, Goods Receipts, Customer Invoices, Supplier Bills, AR posting, GL posting, inventory movement, inventory valuation, COGS, VAT/tax, discounts, price lists, returns, reports, dashboard expansion, E2E hardening, or deployment work in this pass.

## Why This Correction Exists

Phase 4 Slice 2 was reported complete, but local review found this kind of calculation in `SalesOrderService`:

```php
round(($quantityE6 * $unitPriceMinor) / 1000000)
```

That violates the Phase 4 Slice 2 contract:

- no `float`
- no `(float)`
- no binary floating point arithmetic
- no `round()` for authoritative persisted money totals
- server-side totals must use exact integer arithmetic only

This correction must fix that before Phase 4 can continue to Purchase Orders.

## Read First

Read and follow:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `PHASE_4_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_3_FINAL_VERIFICATION_REPORT.md`

Use the current Laravel code as the source of truth.

## Required Fix

In `laravel/app/Application/Sales/SalesOrderService.php`, remove all authoritative float/rounding math from Sales Order totals.

The persisted values must be computed using exact integer arithmetic:

- `quantity_e6` is an integer scale where `1.000000 = 1000000`.
- `unit_price_minor` is an integer minor-unit amount.
- `line_total_minor` must be an integer minor-unit amount.
- `subtotal_minor` and `total_minor` must be the sum of integer line totals.

Implement a small private helper such as:

```php
private function calculateLineTotalMinor(int $quantityE6, int $unitPriceMinor, int $lineIndex): int
```

The helper must:

1. Reject non-positive values.
2. Prevent integer overflow before multiplying.
3. Multiply using integers only.
4. Use modulo and `intdiv`, not `/`, for scaling by `1000000`.
5. Reject cases where the scaled result would create a fractional minor unit unless an existing documented rounding rule already exists.

Recommended rule for this correction:

```text
if (($quantityE6 * $unitPriceMinor) % 1000000 !== 0) reject the line with a validation error.
line_total_minor = intdiv($quantityE6 * $unitPriceMinor, 1000000)
```

Do not introduce a new rounding policy in this correction.

## Forbidden In Corrected Authoritative Sales Code

The authoritative Sales Order service/model/test path must not contain:

- `round(`
- `(float)`
- `float`
- `double`
- `/ 1000000`
- `/1000000`

Client-side UI may show a non-authoritative preview, but persisted totals must always be recomputed on the server with exact integer logic. If the UI preview uses JS numbers, keep it clearly non-authoritative and never submit/store client-computed totals.

## Required Tests

Update `Phase4Slice2SalesOrderTest.php` or add a focused correction test proving:

- exact line total calculation works, for example `quantity_e6 = 1250000` and `unit_price_minor = 1000` produces `line_total_minor = 1250`.
- subtotal and total equal the sum of exact server-computed line totals.
- fractional-minor results are rejected, for example `quantity_e6 = 333333` and `unit_price_minor = 1`.
- invalid huge values that would overflow are rejected.
- no journal, ledger, receivable, payable, invoice, delivery, inventory, COGS, or tax rows are created.
- Sales Order confirmation numbering remains idempotent.

Add a source-scan assertion or a dedicated test that checks `SalesOrderService.php` does not contain:

- `round(`
- `(float)`
- `/ 1000000`
- `/1000000`

## Documentation Updates

After the fix passes verification, update:

- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `README.md`
- `CHANGELOG.md`
- `MD_DOCUMENTATION_AUDIT.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`

The corrected final status should be:

- Phase 4 Slice 2 complete after integer-total correction.
- Next prepared work: Phase 4 Slice 3 Purchase Order Backend.

Do not mark Phase 4 complete.

## Verification Commands

Run from `laravel/` and report exact results:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=Phase4Slice2SalesOrderTest
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan accounting:phase3-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Also run and report:

```powershell
rg -n "round\\(|\\(float\\)|float|double|/ 1000000|/1000000" laravel/app/Application/Sales laravel/app/Models/SalesOrder.php laravel/app/Models/SalesOrderLine.php laravel/tests/Feature/Phase4Slice2SalesOrderTest.php
```

Expected result for the source scan: no authoritative Sales Order float/rounding usage.

## Final Report Required

Return a concise correction report with:

1. Files changed.
2. Exact integer calculation rule implemented.
3. Overflow behavior.
4. Fractional-minor behavior.
5. Tests added/updated.
6. Source-scan result.
7. Full verification command results.
8. Confirmation that no company/branch/tenant scope was introduced.
9. Confirmation that no Purchase Orders, invoices, delivery, inventory, AR/GL posting, COGS, VAT, discounts, or reports were introduced.
10. Remaining risks.

