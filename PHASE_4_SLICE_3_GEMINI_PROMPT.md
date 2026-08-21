# PHASE 4 SLICE 3 - PURCHASE ORDER BACKEND & UX

You are continuing the active Laravel + Inertia Mini ERP migration.

Execute only Phase 4 Slice 3.

Do not start Delivery Notes, Goods Receipts, Customer Invoices, Supplier Bills, AP posting, GL posting, inventory movement, inventory valuation, COGS, VAT/tax, discounts, price lists, returns/debit notes, reports, dashboard expansion, E2E hardening, or deployment work in this pass.

## Read First

Read and follow:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `PHASE_4_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_2_CORRECTION_GEMINI_PROMPT.md`
- `PHASE_3_FINAL_VERIFICATION_REPORT.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`

Use the current Laravel code as the source of truth.

Phase 4 Slice 1 provides the catalog foundation.

Phase 4 Slice 2 provides Sales Orders and the corrected exact integer total pattern. Reuse that exact integer rule for Purchase Orders.

Do not treat old Next.js docs, generated specs, or historical prompts as proof of unsupported business relationships.

## Objective

Build the Purchase Order foundation for supplier commitments.

This slice creates operational Purchase Orders only.

Purchase Orders in this slice are not accounting documents and must not create journal, ledger, payable, inventory, goods receipt, bill, or posting records.

## Hard Boundaries

Do not introduce:

- tenant context
- company scope
- branch scope
- `company_id`
- `branch_id`
- `tenant_id`
- Spatie Teams
- `currentCompany`
- `currentBranch`
- warehouse/branch relationship
- inventory valuation
- stock quantity ledger
- goods receipt tables
- supplier bill tables
- purchase requisition module as a separate table/workflow
- tax/VAT tables or fields
- discount engine
- price list engine
- approval workflow engine beyond the bounded Purchase Order lifecycle below
- any `post` method or PostingEngine integration

If a relationship is not explicitly supported, classify it as:

`UNDEFINED - DO NOT ASSUME`

## Required Slice Scope

Implement Purchase Orders using existing Laravel patterns and mirror the corrected Sales Order implementation where appropriate.

Expected backend concepts:

- PurchaseOrder header.
- PurchaseOrderLine lines.
- PurchaseOrderService application service.
- Purchase Order controller/routes.
- Inertia Purchase Orders page with create/edit/submit/confirm/cancel actions.

Use singular table naming style consistent with the current schema:

- `purchase_order`
- `purchase_order_line`

Do not use `purchase_order_item` unless the existing Laravel code already establishes that naming style. If you choose a different name, document the reason in the implementation report.

## Data Model Requirements

### `purchase_order`

Minimum columns:

- `id` UUID primary key.
- `number` nullable string, unique when present.
- `supplier_id` foreign key to `supplier.id`, restrict on delete.
- `order_date` date.
- `expected_receipt_date` nullable date.
- `currency` string(3), foreign key to `currency.code`.
- `fx_rate_e6` integer default `1000000`.
- `status` string with allowed values: `draft`, `submitted`, `confirmed`, `cancelled`.
- `reference` nullable string.
- `notes` nullable text.
- `subtotal_minor` integer default 0.
- `total_minor` integer default 0.
- `submitted_by`, `submitted_at` nullable.
- `confirmed_by`, `confirmed_at` nullable.
- `cancelled_by`, `cancelled_at` nullable.
- `created_by`, `updated_by` nullable.
- `lock_version` integer.
- timestamps.

Do not add fiscal year or financial period columns in this slice. Purchase Orders are operational only. Accounting period selection belongs to supplier bill/posting slices.

Do not add AP, journal, ledger, payable, goods receipt, supplier bill, tax, inventory, company, branch, tenant, warehouse, project, department, or cost center columns.

### `purchase_order_line`

Minimum columns:

- `id` UUID primary key.
- `purchase_order_id` foreign key to `purchase_order.id`.
- `line_no` integer.
- `product_id` foreign key to `product.id`, restrict on delete.
- `unit_of_measure_id` foreign key to `unit_of_measure.id`, restrict on delete.
- `description` nullable text.
- `quantity_e6` integer.
- `unit_price_minor` integer.
- `line_total_minor` integer.
- timestamps.

No UOM conversion in this slice. The line UOM must equal the product default UOM unless an existing conversion system already exists in the current Laravel code.

No tax columns.

No discount columns.

No stock movement columns.

## Money And Quantity Rules

Use integer math only.

Do not use:

- `float`
- `(float)`
- `double`
- binary floating point arithmetic
- `round()` for authoritative persisted totals
- `/ 1000000`
- `/1000000`
- JS number math for authoritative persisted totals

Quantity must be represented as `quantity_e6`, a positive integer where `1.000000 = 1000000`.

Prices and totals must be minor currency integers:

- `unit_price_minor`
- `line_total_minor`
- `subtotal_minor`
- `total_minor`

Server-side service must recompute all totals. Never trust client totals.

Use the corrected Slice 2 exact rule:

```text
product = quantity_e6 * unit_price_minor
if quantity_e6 > intdiv(PHP_INT_MAX, unit_price_minor), reject overflow
if product % 1000000 !== 0, reject fractional minor unit
line_total_minor = intdiv(product, 1000000)
```

For Slice 3:

- subtotal equals sum of line totals.
- total equals subtotal.
- no discounts.
- no VAT/tax.
- no freight/landed cost.
- no FX posting.
- keep `fx_rate_e6 = 1000000` unless exact non-posting FX requirements already exist in code.

## Lifecycle

Required lifecycle:

```text
draft -> submitted -> confirmed
draft -> cancelled
submitted -> cancelled
```

Rules:

- Draft orders are editable.
- Submitted orders are not editable except cancellation or confirmation.
- Confirmed orders are immutable for this slice.
- Confirmed order cancellation/reversal is out of scope until goods receipt/bill rules exist.
- Cancelled orders are immutable.
- A Purchase Order must have at least one valid line before submit.
- Submit does not allocate a final order number unless the implementation has a strong reason.
- Confirm allocates the final order number if missing.
- Confirm must be idempotent: repeated confirmation attempts must return the same confirmed order and number.

Numbering:

- Use the existing global `NumberSequenceAllocator`.
- Use key `purchase.order`.
- Format as `PO-YYYY-XXXXX`, where `YYYY` comes from `order_date`.
- Do not add company or branch numbering dimensions.

## Validation

Required validations:

- Supplier must exist and be active.
- Currency must exist in `currency.code`.
- `order_date` is required and valid.
- `expected_receipt_date`, if present, must be on or after `order_date`.
- `fx_rate_e6` must be a positive integer and must remain `1000000` unless exact non-posting FX support already exists.
- Every line product must exist, be active, and have `is_purchase_enabled = true`.
- Every line UOM must exist, be active, and match the product default UOM unless UOM conversion already exists.
- `quantity_e6` must be positive.
- `unit_price_minor` must be positive.
- line total calculation must use the exact integer rule above.
- fractional-minor totals must be rejected.
- overflow must be rejected before multiplication.
- Line numbers must be deterministic and unique per order.
- Header totals must equal server-computed line totals.
- Draft update must use optimistic locking if the current document services use `lock_version`.
- Submitted, confirmed, and cancelled documents cannot be updated through the normal update action.

## Relationships Allowed

Allowed relationships:

- PurchaseOrder belongs to Supplier.
- PurchaseOrder belongs to Currency by `currency -> code`.
- PurchaseOrder has many PurchaseOrderLine.
- PurchaseOrderLine belongs to PurchaseOrder.
- PurchaseOrderLine belongs to Product.
- PurchaseOrderLine belongs to UnitOfMeasure.
- PurchaseOrder creator/updater/submittedBy/confirmedBy/cancelledBy belongs to User.

Do not create relationships to:

- Company
- Branch
- Tenant
- Warehouse
- FiscalYear
- FinancialPeriod
- JournalEntry
- LedgerEntry
- PayableEntry
- SupplierBill
- GoodsReceipt

## RBAC

Use the existing `purchasing` module in `config/erp_rbac.php` unless the codebase strongly requires a different pattern.

Required permissions:

- `purchasing.view`
- `purchasing.create`
- `purchasing.edit`
- `purchasing.submit`
- `purchasing.approve` or `purchasing.confirm` if you add that action explicitly
- `purchasing.cancel`
- `purchasing.export` if the page supports export later

Do not add `purchasing.post` behavior in this slice even if the permission already exists historically.

Do not enable Spatie Teams.

## Audit

Use the existing `AuditLogger` API only.

Audit events:

- `purchase_order.create`
- `purchase_order.update`
- `purchase_order.submit`
- `purchase_order.confirm`
- `purchase_order.cancel`

Do not write directly to legacy `audit_log`.

Spatie Activitylog is the active backend.

## Attachments

Register `purchase_order` in the existing attachment entity registry.

Authorization must use Purchasing permissions:

- view: `purchasing.view`
- attach: `purchasing.edit` or `purchasing.create`
- delete: `purchasing.edit` or `purchasing.delete`

Do not authorize through company/branch scope.

## Notifications

Do not add notification triggers unless there is a concrete user target.

If no notification trigger is added, explicitly report that no notification trigger was needed for Slice 3.

## UI Scope

Add a production-quality Inertia Purchase Orders page.

Expected route shape:

- `GET /purchasing/orders`
- `POST /purchasing/orders`
- `PATCH /purchasing/orders/{purchaseOrder}`
- `POST /purchasing/orders/{purchaseOrder}/submit`
- `POST /purchasing/orders/{purchaseOrder}/confirm`
- `POST /purchasing/orders/{purchaseOrder}/cancel`

If the current app uses a different route style, follow the app pattern and document the choice.

UI requirements:

- Use existing AppLayout patterns.
- Add Purchasing navigation group or Purchase Orders link without breaking existing navigation.
- Support English and Arabic translations.
- Support RTL.
- List orders with supplier, number, date, status, currency, total.
- Create/edit modal or page with supplier selector, currency selector, order dates, notes, and dynamic lines.
- Product selector must show only active purchase-enabled products.
- UOM should be derived from product default UOM in Slice 3.
- Totals displayed client-side for UX may be approximate, but persisted totals must come from server recomputation.
- Show clear empty state.
- Show status badges.
- Hide actions the user lacks permission for.
- Do not show bill, goods receipt, posting, stock, tax, discount, freight, or landed-cost actions.

## Tests

Add focused tests proving:

- migrations create `purchase_order` and `purchase_order_line`.
- no `company_id`, `branch_id`, or `tenant_id` columns exist.
- no fiscal year/financial period/journal/ledger/payable/bill/goods-receipt/warehouse columns exist.
- PurchaseOrder relationships load correctly.
- creating a draft with valid active supplier, currency, purchase-enabled product, and active UOM works.
- server computes line totals and header totals with exact integer arithmetic.
- exact line calculation works, for example `quantity_e6 = 1250000` and `unit_price_minor = 1000` produces `line_total_minor = 1250`.
- fractional-minor results are rejected.
- overflow is rejected before multiplication.
- duplicate final order numbers cannot exist.
- submit requires at least one line.
- confirm allocates `PO-YYYY-XXXXX` once and is idempotent.
- invalid/inactive supplier is rejected.
- inactive or non-purchase-enabled product is rejected.
- mismatched/inactive UOM is rejected.
- invalid currency is rejected.
- submitted/confirmed/cancelled update restrictions are enforced.
- confirmed and cancelled records are immutable through normal service actions.
- audit entries are written through Spatie Activitylog via `AuditLogger`.
- `purchase_order` attachment registry works.
- no `journal_entry`, `journal_line`, `ledger_entry`, `payable_entry`, `supplier_bill`, `goods_receipt`, or inventory records are created by Purchase Order operations.
- Inertia page renders required props.
- permission checks protect actions.

Add a source-scan assertion or dedicated test proving the authoritative Purchase Order backend code contains no forbidden float/rounding patterns:

- `round(`
- `(float)`
- `float`
- `double`
- `/ 1000000`
- `/1000000`

Avoid putting those literal forbidden strings directly in the test file in a way that makes repository-level `rg` scans report false positives.

## Verification Commands

Run from `laravel/` and report the exact results:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=Phase4Slice3PurchaseOrderTest
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
rg -n "round\\(|\\(float\\)|float|double|/ 1000000|/1000000" laravel/app/Application/Purchasing laravel/app/Models/PurchaseOrder.php laravel/app/Models/PurchaseOrderLine.php laravel/tests/Feature/Phase4Slice3PurchaseOrderTest.php
```

Expected result: no authoritative Purchase Order float/rounding usage and no false positives from the test file.

If a command fails, fix the cause before reporting completion.

## Documentation Updates

After implementation, update:

- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `README.md`
- `CHANGELOG.md`
- `MD_DOCUMENTATION_AUDIT.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md` if the slice plan needs a status note

Do not mark Phase 4 complete. Mark only Phase 4 Slice 3 complete.

The next slice after successful Slice 3 should be Phase 4 Slice 4: Delivery Notes and Goods Receipts operational foundation.

## Final Report Required

Return a concise implementation report with:

1. Files changed.
2. Migrations added.
3. Schema diff.
4. Models and relationships added.
5. Services/actions added.
6. Exact integer total calculation rule.
7. RBAC permissions used or added.
8. Audit/attachment/notification changes.
9. Explicit confirmation that no company/branch/tenant scope was introduced.
10. Explicit confirmation that no bill, goods receipt, inventory, AP/GL posting, COGS, VAT, discounts, freight/landed cost, or reports were introduced.
11. Test results.
12. Source-scan result.
13. Stress results.
14. Remaining risks or owner decisions needed.

