# PHASE 4 SLICE 2 - SALES ORDER BACKEND & UX

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


You are continuing the active Laravel + Inertia Mini ERP migration.

Execute only Phase 4 Slice 2.

Do not start Purchase Orders, Delivery Notes, Goods Receipts, Customer Invoices, Supplier Bills, AR posting, GL posting, Inventory Valuation, stock movement, COGS, VAT/tax, Returns/Credit Notes, Sales Reports, Dashboard expansion, E2E hardening, or deployment work in this pass.

## Read First

Read and follow:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `PHASE_4_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_3_FINAL_VERIFICATION_REPORT.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`

Use the current Laravel code as the source of truth.

Phase 4 Slice 1 is complete and provides:

- `unit_of_measure`
- `product_category`
- `product`
- `UnitOfMeasure`
- `ProductCategory`
- `Product`
- catalog services/controllers/pages
- `products.*` and `uom.*` RBAC
- `product` attachment entity registration

Do not treat old Next.js docs, generated specs, or historical prompts as proof of unsupported business relationships.

## Objective

Build the Sales Order foundation for customer commitments.

This slice creates operational Sales Orders only.

Sales Orders in this slice are not accounting documents and must not create journal, ledger, receivable, inventory, delivery, or invoice records.

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
- delivery note tables
- customer invoice tables
- sales quotation module as a separate table/workflow
- tax/VAT tables or fields
- discount engine
- price list engine
- approval engine beyond the bounded Sales Order lifecycle below
- any `post` method or PostingEngine integration

If a relationship is not explicitly supported, classify it as:

`UNDEFINED - DO NOT ASSUME`

## Required Slice Scope

Implement Sales Orders using existing Laravel patterns.

Expected backend concepts:

- SalesOrder header.
- SalesOrderLine lines.
- SalesOrderService application service.
- Sales Order controller/routes.
- Inertia Sales Orders page with create/edit/submit/confirm/cancel actions.

Use singular table naming style consistent with the current schema:

- `sales_order`
- `sales_order_line`

Do not use `sales_order_item` unless the existing Laravel code already establishes that naming style. If you choose a different name, document the reason in the implementation report.

## Data Model Requirements

### `sales_order`

Minimum columns:

- `id` UUID primary key.
- `number` nullable string, unique when present.
- `customer_id` foreign key to `customer.id`, restrict on delete.
- `order_date` date.
- `expected_delivery_date` nullable date.
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

Do not add fiscal year or financial period columns in this slice. Sales Orders are operational only. Accounting period selection belongs to invoice/posting slices.

Do not add AR, journal, ledger, receivable, delivery, invoice, tax, inventory, company, branch, tenant, warehouse, or cost center columns.

### `sales_order_line`

Minimum columns:

- `id` UUID primary key.
- `sales_order_id` foreign key to `sales_order.id`.
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
- JS number math for authoritative persisted totals

Quantity must be represented as `quantity_e6`, a positive integer where `1.000000 = 1000000`.

Prices and totals must be minor currency integers:

- `unit_price_minor`
- `line_total_minor`
- `subtotal_minor`
- `total_minor`

Server-side service must recompute all totals. Never trust client totals.

For line total calculation:

```text
line_total_minor = exact_integer_quantity_price_calculation(quantity_e6, unit_price_minor)
```

Use deterministic integer arithmetic and tests. If the multiplication cannot be represented exactly at the selected scale, either use a clearly documented deterministic rounding rule or reject the line with validation. Do not silently lose precision.

For Slice 2:

- subtotal equals sum of line totals.
- total equals subtotal.
- no discounts.
- no VAT/tax.
- no shipping.
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
- Confirmed order cancellation/reversal is out of scope until delivery/invoice rules exist.
- Cancelled orders are immutable.
- A Sales Order must have at least one valid line before submit.
- Submit does not allocate a final order number unless the implementation has a strong reason.
- Confirm allocates the final order number if missing.
- Confirm must be idempotent: repeated confirmation attempts must return the same confirmed order and number.

Numbering:

- Use the existing global `NumberSequenceAllocator`.
- Use key `sales.order`.
- Format as `SO-YYYY-XXXXX`, where `YYYY` comes from `order_date`.
- Do not add company or branch numbering dimensions.

## Validation

Required validations:

- Customer must exist and be active.
- Currency must exist in `currency.code`.
- `order_date` is required and valid.
- `expected_delivery_date`, if present, must be on or after `order_date`.
- `fx_rate_e6` must be a positive integer and must remain `1000000` unless exact non-posting FX support already exists.
- Every line product must exist, be active, and have `is_sales_enabled = true`.
- Every line UOM must exist, be active, and match the product default UOM unless UOM conversion already exists.
- `quantity_e6` must be positive.
- `unit_price_minor` must be positive.
- Line numbers must be deterministic and unique per order.
- Header totals must equal server-computed line totals.
- Draft update must use optimistic locking if the current document services use `lock_version`.
- Submitted, confirmed, and cancelled documents cannot be updated through the normal update action.

## Relationships Allowed

Allowed relationships:

- SalesOrder belongs to Customer.
- SalesOrder belongs to Currency by `currency -> code`.
- SalesOrder has many SalesOrderLine.
- SalesOrderLine belongs to SalesOrder.
- SalesOrderLine belongs to Product.
- SalesOrderLine belongs to UnitOfMeasure.
- SalesOrder creator/updater/submittedBy/confirmedBy/cancelledBy belongs to User.

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
- CustomerInvoice
- DeliveryNote

## RBAC

Use the existing `sales` module in `config/erp_rbac.php` unless the codebase strongly requires a different pattern.

Required permissions:

- `sales.view`
- `sales.create`
- `sales.edit`
- `sales.submit`
- `sales.approve` or `sales.confirm` if you add that action explicitly
- `sales.cancel`
- `sales.export` if the page supports export later

Do not add `sales.post` behavior in this slice even if the permission already exists historically.

Do not enable Spatie Teams.

## Audit

Use the existing `AuditLogger` API only.

Audit events:

- `sales_order.create`
- `sales_order.update`
- `sales_order.submit`
- `sales_order.confirm`
- `sales_order.cancel`

Do not write directly to legacy `audit_log`.

Spatie Activitylog is the active backend.

## Attachments

Register `sales_order` in the existing attachment entity registry.

Authorization must use Sales permissions:

- view: `sales.view`
- attach: `sales.edit` or `sales.create`
- delete: `sales.edit` or `sales.delete`

Do not authorize through company/branch scope.

## Notifications

Do not add notification triggers unless there is a concrete user target.

If no notification trigger is added, explicitly report that no notification trigger was needed for Slice 2.

## UI Scope

Add a production-quality Inertia Sales Orders page.

Expected route shape:

- `GET /sales/orders`
- `POST /sales/orders`
- `PATCH /sales/orders/{salesOrder}`
- `POST /sales/orders/{salesOrder}/submit`
- `POST /sales/orders/{salesOrder}/confirm`
- `POST /sales/orders/{salesOrder}/cancel`

If the current app uses a different route style, follow the app pattern and document the choice.

UI requirements:

- Use existing AppLayout patterns.
- Add Sales navigation group or Sales Orders link without breaking existing navigation.
- Support English and Arabic translations.
- Support RTL.
- List orders with customer, number, date, status, currency, total.
- Create/edit modal or page with customer selector, currency selector, order dates, notes, and dynamic lines.
- Product selector must show only active sales-enabled products.
- UOM should be derived from product default UOM in Slice 2.
- Totals displayed client-side for UX may be approximate, but persisted totals must come from server recomputation.
- Show clear empty state.
- Show status badges.
- Hide actions the user lacks permission for.
- Do not show invoice, delivery, posting, stock, tax, discount, or shipment actions.

## Tests

Add focused tests proving:

- migrations create `sales_order` and `sales_order_line`.
- no `company_id`, `branch_id`, or `tenant_id` columns exist.
- no fiscal year/financial period/journal/ledger/receivable/invoice/delivery/warehouse columns exist.
- SalesOrder relationships load correctly.
- creating a draft with valid active customer, currency, sales-enabled product, and active UOM works.
- server computes line totals and header totals.
- duplicate final order numbers cannot exist.
- submit requires at least one line.
- confirm allocates `SO-YYYY-XXXXX` once and is idempotent.
- invalid/inactive customer is rejected.
- inactive or non-sales-enabled product is rejected.
- mismatched/inactive UOM is rejected.
- invalid currency is rejected.
- submitted/confirmed/cancelled update restrictions are enforced.
- confirmed and cancelled records are immutable through normal service actions.
- audit entries are written through Spatie Activitylog via `AuditLogger`.
- `sales_order` attachment registry works.
- no `journal_entry`, `journal_line`, `ledger_entry`, `receivable_entry`, or inventory records are created by Sales Order operations.
- Inertia page renders required props.
- permission checks protect actions.

If adding concurrency behavior, add a Slice 2 stress command or a PostgreSQL feature test for duplicate confirmation number allocation. At minimum, test idempotent confirm replay.

## Verification Commands

Run from `laravel/` and report the exact results:

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
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan accounting:phase3-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

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

Do not mark Phase 4 complete. Mark only Phase 4 Slice 2 complete.

The next slice after successful Slice 2 should be Phase 4 Slice 3: Purchase Order Backend.

## Final Report Required

Return a concise implementation report with:

1. Files changed.
2. Migrations added.
3. Schema diff.
4. Models and relationships added.
5. Services/actions added.
6. RBAC permissions used or added.
7. Audit/attachment/notification changes.
8. Explicit confirmation that no company/branch/tenant scope was introduced.
9. Explicit confirmation that no invoice, delivery, inventory, AR, GL, tax, or posting behavior was introduced.
10. Test results.
11. Stress results.
12. Remaining risks or owner decisions needed.

