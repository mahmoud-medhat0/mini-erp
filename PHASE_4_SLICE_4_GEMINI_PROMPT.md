# PHASE 4 SLICE 4 - DELIVERY NOTES & GOODS RECEIPTS OPERATIONAL FOUNDATION

You are continuing the active Laravel + Inertia Mini ERP migration.

Execute only Phase 4 Slice 4.

Do not start Customer Invoices, Supplier Bills, AR posting, AP posting, GL posting, inventory valuation, stock balance ledger, COGS, VAT/tax, discounts, price lists, returns/credit notes/debit notes, reports, dashboard expansion, E2E hardening, or deployment work in this pass.

## Read First

Read and follow:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `PHASE_4_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_2_CORRECTION_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_3_GEMINI_PROMPT.md`
- `PHASE_3_FINAL_VERIFICATION_REPORT.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`

Use the current Laravel code as the source of truth.

Phase 4 Slice 1 provides the Product/Service Catalog.

Phase 4 Slice 2 provides Sales Orders.

Phase 4 Slice 3 provides Purchase Orders.

Do not treat old Next.js docs, generated specs, or historical prompts as proof of unsupported business relationships.

## Objective

Build operational fulfillment documents:

- Delivery Notes for confirmed Sales Orders.
- Goods Receipts for confirmed Purchase Orders.

These documents track operational quantities only.

They must not create accounting, AR/AP, inventory valuation, COGS, stock balance ledger, invoice, bill, tax, or warehouse records.

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
- warehouse/location/branch relationship
- inventory valuation
- stock balance table
- stock movement ledger
- `inventory_entry`
- `stock_movement`
- COGS posting
- customer invoice tables
- supplier bill tables
- tax/VAT tables or fields
- discount engine
- price list engine
- return/credit/debit note workflow
- any `post` method or PostingEngine integration

If a relationship is not explicitly supported, classify it as:

`UNDEFINED - DO NOT ASSUME`

## Required Slice Scope

Implement operational fulfillment using existing Laravel patterns.

Expected backend concepts:

- DeliveryNote header.
- DeliveryNoteLine lines.
- DeliveryNoteService application service.
- GoodsReceipt header.
- GoodsReceiptLine lines.
- GoodsReceiptService application service.
- Controllers/routes for Delivery Notes and Goods Receipts.
- Inertia pages for Delivery Notes and Goods Receipts.

Use singular table naming style consistent with the current schema:

- `delivery_note`
- `delivery_note_line`
- `goods_receipt`
- `goods_receipt_line`

## Data Model Requirements

### `delivery_note`

Minimum columns:

- `id` UUID primary key.
- `number` nullable string, unique when present.
- `sales_order_id` foreign key to `sales_order.id`, restrict on delete.
- `delivery_date` date.
- `status` string with allowed values: `draft`, `confirmed`, `cancelled`.
- `reference` nullable string.
- `notes` nullable text.
- `confirmed_by`, `confirmed_at` nullable.
- `cancelled_by`, `cancelled_at` nullable.
- `created_by`, `updated_by` nullable.
- `lock_version` integer.
- timestamps.

Do not add customer duplicate columns unless the codebase has a strong existing denormalization pattern. Customer can be reached through SalesOrder.

Do not add fiscal year, financial period, journal, ledger, receivable, invoice, inventory, warehouse, company, branch, tenant, project, department, cost center, COGS, or tax columns.

### `delivery_note_line`

Minimum columns:

- `id` UUID primary key.
- `delivery_note_id` foreign key to `delivery_note.id`.
- `sales_order_line_id` foreign key to `sales_order_line.id`, restrict on delete.
- `line_no` integer.
- `product_id` foreign key to `product.id`, restrict on delete.
- `unit_of_measure_id` foreign key to `unit_of_measure.id`, restrict on delete.
- `description` nullable text.
- `quantity_e6` integer.
- timestamps.

No monetary columns.

No stock valuation columns.

No stock balance columns.

### `goods_receipt`

Minimum columns:

- `id` UUID primary key.
- `number` nullable string, unique when present.
- `purchase_order_id` foreign key to `purchase_order.id`, restrict on delete.
- `receipt_date` date.
- `status` string with allowed values: `draft`, `confirmed`, `cancelled`.
- `reference` nullable string.
- `notes` nullable text.
- `confirmed_by`, `confirmed_at` nullable.
- `cancelled_by`, `cancelled_at` nullable.
- `created_by`, `updated_by` nullable.
- `lock_version` integer.
- timestamps.

Do not add supplier duplicate columns unless the codebase has a strong existing denormalization pattern. Supplier can be reached through PurchaseOrder.

Do not add fiscal year, financial period, journal, ledger, payable, bill, inventory, warehouse, company, branch, tenant, project, department, cost center, COGS, landed cost, freight, or tax columns.

### `goods_receipt_line`

Minimum columns:

- `id` UUID primary key.
- `goods_receipt_id` foreign key to `goods_receipt.id`.
- `purchase_order_line_id` foreign key to `purchase_order_line.id`, restrict on delete.
- `line_no` integer.
- `product_id` foreign key to `product.id`, restrict on delete.
- `unit_of_measure_id` foreign key to `unit_of_measure.id`, restrict on delete.
- `description` nullable text.
- `quantity_e6` integer.
- timestamps.

No monetary columns.

No stock valuation columns.

No stock balance columns.

## Quantity Rules

Use integer quantity math only.

Do not use:

- `float`
- `(float)`
- `double`
- binary floating point arithmetic
- `round()`
- JS number math for authoritative persisted quantities

Quantity must be represented as `quantity_e6`, a positive integer where `1.000000 = 1000000`.

Server-side services must validate and persist authoritative quantities.

No UOM conversion in this slice. The line UOM must match the source order line UOM.

## Fulfillment Rules

Delivery Notes:

- Must reference a confirmed Sales Order.
- Every Delivery Note line must reference a Sales Order line from the same Sales Order.
- Delivery quantity must be positive.
- Cumulative active delivered quantity for a Sales Order line must never exceed the Sales Order line quantity.
- Active delivered quantity means quantities from non-cancelled Delivery Notes.
- Do not mutate confirmed Sales Order lines to store delivered quantity. Derive fulfillment by summing Delivery Note lines.

Goods Receipts:

- Must reference a confirmed Purchase Order.
- Every Goods Receipt line must reference a Purchase Order line from the same Purchase Order.
- Received quantity must be positive.
- Cumulative active received quantity for a Purchase Order line must never exceed the Purchase Order line quantity.
- Active received quantity means quantities from non-cancelled Goods Receipts.
- Do not mutate confirmed Purchase Order lines to store received quantity. Derive fulfillment by summing Goods Receipt lines.

## Lifecycle

Delivery Note lifecycle:

```text
draft -> confirmed
draft -> cancelled
```

Goods Receipt lifecycle:

```text
draft -> confirmed
draft -> cancelled
```

Rules:

- Draft documents are editable.
- Confirmed documents are immutable for this slice.
- Cancelled documents are immutable.
- Confirm allocates the final number if missing.
- Confirm must be idempotent: repeated confirmation attempts must return the same confirmed document and number.
- Confirm must re-check over-delivery/over-receipt inside a transaction with row locks.
- Cancelling confirmed documents is out of scope until returns/reversal rules exist.

Numbering:

- Delivery Notes: use global key `delivery.note`, format `DN-YYYY-XXXXX`, where `YYYY` comes from `delivery_date`.
- Goods Receipts: use global key `goods.receipt`, format `GRN-YYYY-XXXXX`, where `YYYY` comes from `receipt_date`.
- Do not add company or branch numbering dimensions.

## Concurrency And Locking

Prevent over-delivery and over-receipt under concurrent requests.

Use transactions and deterministic locking.

Recommended lock order:

1. Lock the parent SalesOrder/PurchaseOrder row.
2. Lock referenced source order lines sorted by ID.
3. Lock existing active fulfillment lines for those source lines.
4. Validate cumulative quantities.
5. Update/confirm the fulfillment document.

Do not rely only on application reads without locks.

If practical, add a focused PostgreSQL concurrency test or artisan stress command for duplicate/competing fulfillment pressure.

At minimum, add a feature test proving over-delivery/over-receipt is rejected when multiple active documents already exist.

## Relationships Allowed

Allowed Delivery Note relationships:

- DeliveryNote belongs to SalesOrder.
- DeliveryNote has many DeliveryNoteLine.
- DeliveryNoteLine belongs to DeliveryNote.
- DeliveryNoteLine belongs to SalesOrderLine.
- DeliveryNoteLine belongs to Product.
- DeliveryNoteLine belongs to UnitOfMeasure.
- DeliveryNote creator/updater/confirmedBy/cancelledBy belongs to User.

Allowed Goods Receipt relationships:

- GoodsReceipt belongs to PurchaseOrder.
- GoodsReceipt has many GoodsReceiptLine.
- GoodsReceiptLine belongs to GoodsReceipt.
- GoodsReceiptLine belongs to PurchaseOrderLine.
- GoodsReceiptLine belongs to Product.
- GoodsReceiptLine belongs to UnitOfMeasure.
- GoodsReceipt creator/updater/confirmedBy/cancelledBy belongs to User.

Do not create relationships to:

- Company
- Branch
- Tenant
- Warehouse
- FiscalYear
- FinancialPeriod
- JournalEntry
- LedgerEntry
- ReceivableEntry
- PayableEntry
- CustomerInvoice
- SupplierBill
- StockMovement
- InventoryEntry

## RBAC

Use existing module permissions unless the codebase strongly requires a different pattern.

Delivery Notes should use Sales permissions:

- `sales.view`
- `sales.create`
- `sales.edit`
- `sales.approve` or `sales.confirm` if explicitly added
- `sales.cancel`

Goods Receipts should use Purchasing permissions:

- `purchasing.view`
- `purchasing.create`
- `purchasing.edit`
- `purchasing.approve` or `purchasing.confirm` if explicitly added
- `purchasing.cancel`

Do not add `sales.post` or `purchasing.post` behavior in this slice.

Do not enable Spatie Teams.

## Audit

Use the existing `AuditLogger` API only.

Audit events:

- `delivery_note.create`
- `delivery_note.update`
- `delivery_note.confirm`
- `delivery_note.cancel`
- `goods_receipt.create`
- `goods_receipt.update`
- `goods_receipt.confirm`
- `goods_receipt.cancel`

Do not write directly to legacy `audit_log`.

Spatie Activitylog is the active backend.

## Attachments

Register both entities in the existing attachment entity registry:

- `delivery_note`
- `goods_receipt`

Authorization:

- Delivery Note view: `sales.view`
- Delivery Note attach/delete: `sales.edit` or `sales.create`
- Goods Receipt view: `purchasing.view`
- Goods Receipt attach/delete: `purchasing.edit` or `purchasing.create`

Do not authorize through company/branch scope.

## Notifications

Do not add notification triggers unless there is a concrete user target.

If no notification trigger is added, explicitly report that no notification trigger was needed for Slice 4.

## UI Scope

Add production-quality Inertia pages:

- Delivery Notes.
- Goods Receipts.

Expected route shape:

- `GET /sales/delivery-notes`
- `POST /sales/delivery-notes`
- `PATCH /sales/delivery-notes/{deliveryNote}`
- `POST /sales/delivery-notes/{deliveryNote}/confirm`
- `POST /sales/delivery-notes/{deliveryNote}/cancel`
- `GET /purchasing/goods-receipts`
- `POST /purchasing/goods-receipts`
- `PATCH /purchasing/goods-receipts/{goodsReceipt}`
- `POST /purchasing/goods-receipts/{goodsReceipt}/confirm`
- `POST /purchasing/goods-receipts/{goodsReceipt}/cancel`

If the current app uses a different route style, follow the app pattern and document the choice.

UI requirements:

- Use existing AppLayout patterns.
- Add navigation links without breaking existing groups.
- Support English and Arabic translations.
- Support RTL.
- List documents with source order, number, date, status, and line count/quantity summary.
- Create/edit modal or page with source order selector and dynamic lines.
- Source order selectors must show only confirmed orders.
- Lines should be populated from source order lines, with remaining quantity displayed.
- Persisted quantities must come from server validation.
- Show clear empty states.
- Show status badges.
- Hide actions the user lacks permission for.
- Do not show invoice, bill, posting, stock, tax, discount, freight, landed-cost, or warehouse actions.

## Tests

Add focused tests proving:

- migrations create `delivery_note`, `delivery_note_line`, `goods_receipt`, and `goods_receipt_line`.
- no `company_id`, `branch_id`, or `tenant_id` columns exist.
- no fiscal year/financial period/journal/ledger/receivable/payable/invoice/bill/warehouse/inventory/COGS/tax columns exist.
- DeliveryNote relationships load correctly.
- GoodsReceipt relationships load correctly.
- creating draft Delivery Note from confirmed Sales Order works.
- creating draft Goods Receipt from confirmed Purchase Order works.
- draft/submitted/cancelled Sales Orders cannot be delivered.
- draft/submitted/cancelled Purchase Orders cannot be received.
- source order line must belong to the selected source order.
- UOM must match the source order line UOM.
- `quantity_e6` must be positive.
- partial delivery/receipt works.
- cumulative over-delivery is rejected.
- cumulative over-receipt is rejected.
- confirm allocates `DN-YYYY-XXXXX` once and is idempotent.
- confirm allocates `GRN-YYYY-XXXXX` once and is idempotent.
- confirmed and cancelled documents are immutable through normal update actions.
- audit entries are written through Spatie Activitylog via `AuditLogger`.
- attachment registry works for `delivery_note` and `goods_receipt`.
- no `journal_entry`, `journal_line`, `ledger_entry`, `receivable_entry`, `payable_entry`, invoice, bill, stock movement, inventory, COGS, or tax rows are created by these operations.
- Inertia pages render required props.
- permission checks protect actions.

Add a source-scan assertion or dedicated test proving the authoritative Delivery/Goods Receipt backend code contains no forbidden quantity/money float/rounding patterns:

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
php artisan test --filter=Phase4Slice4FulfillmentTest
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
rg -n "round\\(|\\(float\\)|float|double|/ 1000000|/1000000" laravel/app/Application/Sales laravel/app/Application/Purchasing laravel/app/Models/DeliveryNote.php laravel/app/Models/DeliveryNoteLine.php laravel/app/Models/GoodsReceipt.php laravel/app/Models/GoodsReceiptLine.php laravel/tests/Feature/Phase4Slice4FulfillmentTest.php
```

Expected result: no authoritative Delivery/Goods Receipt float/rounding usage and no false positives from the test file.

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

Do not mark Phase 4 complete. Mark only Phase 4 Slice 4 complete.

The next slice after successful Slice 4 should be Phase 4 Slice 5: Customer Invoice Posting to AR/GL.

## Final Report Required

Return a concise implementation report with:

1. Files changed.
2. Migrations added.
3. Schema diff.
4. Models and relationships added.
5. Services/actions added.
6. Quantity fulfillment rule.
7. Concurrency/locking approach.
8. RBAC permissions used or added.
9. Audit/attachment/notification changes.
10. Explicit confirmation that no company/branch/tenant scope was introduced.
11. Explicit confirmation that no invoice, bill, inventory valuation, stock balance ledger, AR/AP/GL posting, COGS, VAT, discounts, freight/landed cost, warehouse, or reports were introduced.
12. Test results.
13. Source-scan result.
14. Stress results.
15. Remaining risks or owner decisions needed.

