# PHASE 4 SLICE 5 - CUSTOMER INVOICE POSTING TO AR/GL

You are continuing the active Laravel + Inertia Mini ERP migration.

Execute only Phase 4 Slice 5.

Do not start Supplier Bills, AP bill posting, inventory valuation, stock movement ledgers, COGS, VAT/tax, discounts, price lists, returns, credit notes, debit notes, reports, dashboard expansion, E2E hardening, or deployment work in this pass.

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
- `PHASE_4_SLICE_4_GEMINI_PROMPT.md`
- `PHASE_3_FINAL_VERIFICATION_REPORT.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`

Use the current Laravel code as the source of truth.

Phase 4 Slice 1 provides the Product/Service Catalog.

Phase 4 Slice 2 provides Sales Orders.

Phase 4 Slice 3 provides Purchase Orders.

Phase 4 Slice 4 provides Delivery Notes and Goods Receipts.

Do not treat old Next.js docs, generated specs, or historical prompts as proof of unsupported business relationships.

## Objective

Build Customer Invoice lifecycle and posting.

Customer Invoice posting must:

- create an approved Journal Entry with AR Control debit and Sales Revenue credit;
- post that Journal Entry through the existing `PostingEngine`;
- create one `receivable_entry` debit linked to the customer invoice;
- preserve immutable accounting behavior and idempotent posting;
- expose a focused Inertia page for customer invoices.

This slice is the first Phase 4 slice that posts Sales documents to AR/GL.

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
- supplier bill tables
- payable posting
- VAT/tax tables or fields
- discount fields or discount engine
- price list engine
- sales return workflow
- credit note workflow
- debit note workflow
- automatic invoice generation
- invoice reversal or mutation of posted ledgers

If a relationship is not explicitly supported, classify it as:

`UNDEFINED - DO NOT ASSUME`

## Required Slice Scope

Implement Customer Invoice using existing Laravel patterns.

Expected backend concepts:

- `customer_invoice` header.
- `customer_invoice_line` lines.
- `CustomerInvoice` model.
- `CustomerInvoiceLine` model.
- `CustomerInvoiceService` application service.
- `CustomerInvoiceController`.
- Routes under `/sales/invoices`.
- Inertia page `resources/js/Pages/Sales/CustomerInvoices.tsx`.
- Attachment registry entity `customer_invoice`.
- Accounting mapping key `sales_revenue`.
- Feature test suite `Phase4Slice5CustomerInvoiceTest`.

Use singular table naming style consistent with current schema:

- `customer_invoice`
- `customer_invoice_line`

## Data Model Requirements

### `customer_invoice`

Minimum columns:

- `id` UUID primary key.
- `number` nullable string, unique when present.
- `customer_id` foreign key to `customer.id`, restrict on delete.
- `sales_order_id` nullable foreign key to `sales_order.id`, restrict on delete.
- `delivery_note_id` nullable foreign key to `delivery_note.id`, restrict on delete.
- `fiscal_year_id` foreign key to `fiscal_year.id`, restrict on delete.
- `financial_period_id` foreign key to `financial_period.id`, restrict on delete.
- `invoice_date` date.
- `due_date` nullable date.
- `reference` nullable string.
- `description` nullable text.
- `currency` string(3), foreign key to `currency.code`, restrict on delete.
- `fx_rate_e6` bigint default `1000000`.
- `subtotal_minor` bigint.
- `total_minor` bigint.
- `status` string with allowed values: `draft`, `submitted`, `approved`, `posted`, `cancelled`.
- `journal_entry_id` nullable foreign key to `journal_entry.id`, null on delete.
- `receivable_entry_id` nullable foreign key to `receivable_entry.id`, null on delete.
- `submitted_by`, `submitted_at` nullable.
- `approved_by`, `approved_at` nullable.
- `posted_by`, `posted_at` nullable.
- `cancelled_by`, `cancelled_at` nullable.
- `created_by`, `updated_by` nullable.
- `lock_version` integer.
- timestamps.

Do not add customer duplicate name/code columns unless the current codebase already has a strong denormalization pattern for these documents.

Do not add tax, VAT, discount, COGS, inventory, warehouse, branch, company, tenant, project, department, cost center, stock, payable, supplier bill, return, credit note, or debit note columns.

### `customer_invoice_line`

Minimum columns:

- `id` UUID primary key.
- `customer_invoice_id` foreign key to `customer_invoice.id`, cascade delete.
- `sales_order_line_id` nullable foreign key to `sales_order_line.id`, restrict on delete.
- `delivery_note_line_id` nullable foreign key to `delivery_note_line.id`, restrict on delete.
- `line_no` integer.
- `product_id` foreign key to `product.id`, restrict on delete.
- `unit_of_measure_id` foreign key to `unit_of_measure.id`, restrict on delete.
- `description` nullable text.
- `quantity_e6` bigint.
- `unit_price_minor` bigint.
- `line_total_minor` bigint.
- timestamps.

No tax columns.

No discount columns.

No stock valuation columns.

No COGS columns.

## Source Modes

Support these source modes only:

1. Manual customer invoice line for active sales-enabled `service` or `non_stock` products.
2. Invoice from confirmed Sales Order line.
3. Invoice from confirmed Delivery Note line.

For source-based lines:

- The Customer Invoice customer and currency must match the source Sales Order customer and currency.
- `sales_order_line_id`, when present, must belong to the selected `sales_order_id`.
- `delivery_note_line_id`, when present, must belong to the selected `delivery_note_id`.
- A Delivery Note source must be `confirmed`.
- A Sales Order source must be `confirmed`.
- Product and UOM must match the source line.
- Do not perform UOM conversion in this slice.
- Unit price should come from the Sales Order line for source-based invoice lines unless the existing UX already has a verified override rule. Do not invent price override semantics.

## Stock Product Boundary

Customer Invoice posting in this slice must not post COGS or inventory valuation.

Therefore:

- Invoice lines for `stock` products must be rejected in this slice.
- Allow only `service` and `non_stock` products for Customer Invoice posting.
- Do not create stock movements.
- Do not reduce inventory.
- Do not create inventory valuation entries.

If a confirmed Sales Order or Delivery Note contains a stock product, Customer Invoice creation or posting must fail with a clear validation message explaining that stock invoicing needs the later inventory costing/COGS decision slice.

## Money And Quantity Rules

Use integer math only.

Do not use:

- `float`
- `(float)`
- `double`
- binary floating point arithmetic
- `round()`
- JS number math for authoritative persisted amounts or quantities
- `/ 1000000`
- `/1000000`

Quantity is `quantity_e6`, where `1.000000 = 1000000`.

Money is integer minor units.

Line total must be calculated exactly:

```text
line_total_minor = (quantity_e6 * unit_price_minor) / 1000000
```

But implement it without floating division:

- use `intdiv($quantityE6 * $unitPriceMinor, 1000000)`;
- reject overflow before multiplying;
- reject fractional minor units when the product is not exactly divisible by `1000000`;
- reject negative or zero quantities and negative prices.

Copy the exact integer approach from `SalesOrderService` and `PurchaseOrderService`; do not reintroduce the earlier `round()` bug.

## Invoice Lifecycle

Implement:

- `create`
- `update`
- `submit`
- `approve`
- `post`
- `cancel`

Rules:

- Only `draft` invoices can be updated.
- `draft` can be submitted.
- `submitted` can be approved.
- `approved` can be posted.
- `draft`, `submitted`, and `approved` can be cancelled.
- `posted` invoices cannot be edited, cancelled, deleted, reposted as a new accounting document, or mutated through normal actions.
- Posting an already posted invoice must be idempotent and return the existing posted invoice.
- Posted invoice correction belongs to later credit-note/reversal work; do not implement it now.

Use optimistic locking (`lock_version`) for update and state transitions where appropriate.

## Source Quantity Limits

Prevent over-invoicing.

When invoicing from a Sales Order line:

- Cumulative active invoice quantity for that `sales_order_line_id` must not exceed the Sales Order line `quantity_e6`.
- Active invoice lines are lines whose parent invoice is not `cancelled`.

When invoicing from a Delivery Note line:

- Cumulative active invoice quantity for that `delivery_note_line_id` must not exceed the Delivery Note line `quantity_e6`.
- Active invoice lines are lines whose parent invoice is not `cancelled`.

Do not mutate Sales Order lines or Delivery Note lines to store invoiced quantities.

Derive invoiced quantities by summing `customer_invoice_line` rows through non-cancelled invoices.

Use deterministic transaction locks:

1. Lock the Customer Invoice row for state transitions.
2. Lock the relevant Customer, FiscalYear, and FinancialPeriod rows where needed.
3. Lock source SalesOrder / DeliveryNote headers in deterministic order.
4. Lock referenced SalesOrderLine / DeliveryNoteLine rows ordered by ID.
5. Validate cumulative invoice quantities inside the same transaction.

## Accounting Mapping

Do not hardcode revenue account IDs.

Extend the existing accounting mapping system with a new key:

```text
sales_revenue
```

Required changes:

- Add `sales_revenue` to `AccountingAccountMappingService::ALLOWED_KEYS`.
- Update account-type validation so `sales_revenue` requires an active revenue account with credit nature.
- Update database check constraints on `accounting_account_mapping.key` with a forward migration so existing keys remain valid and `sales_revenue` is allowed.
- Seed/configure `sales_revenue` idempotently in local/testing/demo seed paths when account `4100` Sales Revenue exists.
- Do not create a UI page for mappings in this slice unless such a page already exists.

Posting must fail clearly if:

- `ar_control` is missing;
- `sales_revenue` is missing;
- either mapped account is inactive;
- either mapped account has the wrong type/nature;
- either mapped account currency does not match invoice currency.

## Posting Requirements

Posting an approved Customer Invoice must happen in a single database transaction and through existing accounting infrastructure.

Required accounting effect:

```text
Dr AR Control
Cr Sales Revenue
```

Use:

- `JournalEntry` with `source_type = customer_invoice` and `source_id = customer_invoice.id`.
- `JournalLine` rows for AR Control and Sales Revenue.
- `PostingEngine::post(...)` with explicit system posting to control accounts.
- `ReceivableEntry` debit row with `source_type = customer_invoice` and `source_id = customer_invoice.id`.

Posting constraints:

- FinancialPeriod must be open or reopened.
- `invoice_date` must be inside the selected FinancialPeriod.
- `financial_period.fiscal_year_id` must match `customer_invoice.fiscal_year_id`.
- `fx_rate_e6` must be `1000000` in this slice unless exact FX conversion posting already exists and is proven by tests. Do not invent FX conversion rules.
- Journal and ReceivableEntry amounts must equal `customer_invoice.total_minor`.
- Transaction currency amounts must be preserved in journal, ledger, and receivable rows.
- The posted JournalEntry must contain exactly the expected accounting lines for this slice.
- The Customer Invoice must link to the posted `journal_entry_id` and `receivable_entry_id`.

Use existing idempotency patterns:

- Use `DatabaseIdempotencyStore` or the same proven pattern used by receipt/payment/opening-balance posting services.
- Use `AccountingKernel::postingIdempotencyKey('customer_invoice', $invoiceId, 'post')` or equivalent existing convention.
- Replayed post calls must not create duplicate journal entries, ledger entries, or receivable entries.

Invoice number allocation:

- Allocate number atomically at posting if missing.
- Use global number sequence key `customer.invoice`.
- Format: `INV-YYYY-XXXXX`, where `YYYY` comes from `invoice_date`.
- Do not include company or branch dimensions in numbering.

## Audit, Attachments, Notifications

Audit:

- Use the existing `AuditLogger` API backed by Spatie Activitylog.
- Log create, update, submit, approve, post, and cancel actions.
- Do not write new application records to legacy `audit_log`.

Attachments:

- Register `customer_invoice` in `config/erp_attachments.php`.
- Authorization must use sales/customer invoice permissions, not company or branch scope.

Notifications:

- No notification trigger is required in this slice unless the current codebase already has a simple user-targeted pattern for approval/post events.
- Do not invent company/branch notification ownership.

## RBAC

Use existing Spatie Permission module actions where possible:

- `sales.view`
- `sales.create`
- `sales.edit`
- `sales.submit`
- `sales.approve`
- `sales.post`
- `sales.cancel`

Do not enable Spatie Teams.

Do not add company-scoped or branch-scoped permissions.

## Routes

Add focused routes under `/sales/invoices`:

- `GET /sales/invoices`
- `POST /sales/invoices`
- `PATCH /sales/invoices/{customerInvoice}`
- `POST /sales/invoices/{customerInvoice}/submit`
- `POST /sales/invoices/{customerInvoice}/approve`
- `POST /sales/invoices/{customerInvoice}/post`
- `POST /sales/invoices/{customerInvoice}/cancel`

Use policy/middleware checks consistent with existing Phase 4 controllers.

Do not add delete routes for posted accounting documents.

## Inertia UX

Create `resources/js/Pages/Sales/CustomerInvoices.tsx`.

UX requirements:

- English and Arabic support.
- RTL support.
- ERP-workspace layout, not a marketing page.
- Invoice list with number, customer, invoice date, due date, status, total, source, and posting links.
- Create/edit modal or page with:
  - customer selector;
  - fiscal year/financial period selector;
  - invoice date and due date;
  - currency selector;
  - source mode: manual, sales order, delivery note;
  - sales order selector for confirmed sales orders;
  - delivery note selector for confirmed delivery notes;
  - line editor with product, UOM, quantity, unit price, and server-computed total display.
- Product selectors for manual lines must show active sales-enabled `service` and `non_stock` products only.
- Stock products should not be selectable for invoice posting in this slice.
- Source line selectors must display remaining uninvoiced quantity.
- Show validation errors clearly.
- Show status badges.
- Hide actions the user lacks permission for.
- Show a polished empty state when no invoices exist.
- Do not add tax, discount, inventory, COGS, returns, credit notes, or supplier bill UI.

## Tests

Add focused tests proving:

- migrations create `customer_invoice` and `customer_invoice_line`.
- `sales_revenue` mapping key is allowed at service and DB constraint levels.
- no `company_id`, `branch_id`, or `tenant_id` columns exist.
- no tax, VAT, discount, COGS, inventory, stock movement, warehouse, payable, supplier bill, return, credit note, or debit note columns exist.
- models and relationships load correctly.
- create/update Customer Invoice works for manual `service` product lines.
- create/update Customer Invoice works for manual `non_stock` product lines.
- `stock` product invoice lines are rejected in this slice.
- confirmed Sales Order source lines can be invoiced within remaining quantity.
- confirmed Delivery Note source lines can be invoiced within remaining quantity.
- over-invoicing Sales Order lines is rejected.
- over-invoicing Delivery Note lines is rejected.
- source order/delivery customer and currency must match invoice header.
- draft invoice can be submitted.
- submitted invoice can be approved.
- approved invoice posts successfully.
- posting allocates `INV-YYYY-XXXXX` once and is idempotent.
- posting creates exactly one JournalEntry, the expected JournalLine rows, expected LedgerEntry rows, and one ReceivableEntry debit.
- PostingEngine immutable ledger behavior remains intact.
- FinancialPeriod must be open/reopened.
- invoice_date must be inside selected FinancialPeriod.
- `financial_period.fiscal_year_id` must match invoice `fiscal_year_id`.
- missing `ar_control` mapping fails clearly.
- missing `sales_revenue` mapping fails clearly.
- mapped account wrong type/nature/currency fails clearly.
- `fx_rate_e6` other than `1000000` is rejected in this slice unless exact FX posting is already proven.
- cancelled invoices cannot post.
- posted invoices cannot update/cancel/repost into duplicates.
- audit entries are written through Spatie Activitylog via `AuditLogger`.
- attachment registry works for `customer_invoice`.
- Inertia page renders required props and permission-aware actions.
- no supplier bill, payable entry, inventory entry, stock movement, COGS, tax, discount, or return rows are created by Customer Invoice operations.

Add a source-scan assertion or dedicated test proving the authoritative Customer Invoice backend code contains no forbidden money/quantity float/rounding patterns:

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
php artisan test --filter=Phase4Slice5CustomerInvoiceTest
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
rg -n "round\\(|\\(float\\)|float|double|/ 1000000|/1000000" laravel/app/Application/Sales laravel/app/Models/CustomerInvoice.php laravel/app/Models/CustomerInvoiceLine.php laravel/tests/Feature/Phase4Slice5CustomerInvoiceTest.php
```

Expected result: no authoritative Customer Invoice float/rounding usage and no false positives from the test file.

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

Do not mark Phase 4 complete. Mark only Phase 4 Slice 5 complete.

The next slice after successful Slice 5 should be Phase 4 Slice 6: Supplier Bill Posting to AP/GL.

## Final Report Required

Return a concise implementation report with:

1. Files changed.
2. Migrations added.
3. Schema diff.
4. Models and relationships added.
5. Services/actions added.
6. Source line and over-invoicing rules.
7. Exact integer money/quantity approach.
8. Posting and idempotency approach.
9. RBAC permissions used or added.
10. Audit/attachment/notification changes.
11. Explicit confirmation that no company/branch/tenant scope was introduced.
12. Explicit confirmation that no Supplier Bill, AP posting, inventory valuation, stock movement, COGS, VAT/tax, discounts, returns, credit notes, debit notes, warehouse, or reports were introduced.
13. Test results.
14. Source-scan result.
15. Stress results.
16. Remaining risks or owner decisions needed.

