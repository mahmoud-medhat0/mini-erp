# PHASE 4 SLICE 6 - SUPPLIER BILL POSTING TO AP/GL

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


You are continuing the active Laravel + Inertia Mini ERP migration.

Execute only Phase 4 Slice 6.

Do not start inventory valuation, stock movement ledgers, COGS, VAT/tax, discounts, price lists, returns, credit notes, debit notes, payment allocation changes, reports, dashboard expansion, E2E hardening, or deployment work in this pass.

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
- `PHASE_4_SLICE_5_GEMINI_PROMPT.md`
- `PHASE_3_FINAL_VERIFICATION_REPORT.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`

Use the current Laravel code as the source of truth.

Phase 4 Slice 1 provides the Product/Service Catalog.

Phase 4 Slice 3 provides Purchase Orders.

Phase 4 Slice 4 provides Goods Receipts.

Phase 4 Slice 5 provides Customer Invoice posting. Mirror the good parts of that implementation, but do not copy any bug or mismatch blindly.

Do not treat old Next.js docs, generated specs, or historical prompts as proof of unsupported business relationships.

## Objective

Build Supplier Bill lifecycle and posting.

Supplier Bill posting must:

- create an approved Journal Entry with Purchase Expense debit and AP Control credit;
- post that Journal Entry through the existing `PostingEngine`;
- create one `payable_entry` credit linked to the supplier bill;
- preserve immutable accounting behavior and idempotent posting;
- expose a focused Inertia page for supplier bills.

This slice is the first Phase 4 slice that posts Purchasing documents to AP/GL.

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
- landed cost
- freight allocation
- VAT/tax tables or fields
- discount fields or discount engine
- price list engine
- purchase return workflow
- supplier credit/debit note workflow
- automatic bill generation
- bill reversal or mutation of posted ledgers

If a relationship is not explicitly supported, classify it as:

`UNDEFINED - DO NOT ASSUME`

## Required Slice Scope

Implement Supplier Bill using existing Laravel patterns.

Expected backend concepts:

- `supplier_bill` header.
- `supplier_bill_line` lines.
- `SupplierBill` model.
- `SupplierBillLine` model.
- `SupplierBillService` application service.
- `SupplierBillController`.
- Routes under `/purchasing/bills`.
- Inertia page `resources/js/Pages/Purchasing/SupplierBills.tsx`.
- Attachment registry entity `supplier_bill`.
- Accounting mapping key `purchase_expense`.
- Feature test suite `Phase4Slice6SupplierBillTest`.

Use singular table naming style consistent with current schema:

- `supplier_bill`
- `supplier_bill_line`

## Data Model Requirements

### `supplier_bill`

Minimum columns:

- `id` UUID primary key.
- `number` nullable string, unique when present.
- `supplier_id` foreign key to `supplier.id`, restrict on delete.
- `purchase_order_id` nullable foreign key to `purchase_order.id`, restrict on delete.
- `goods_receipt_id` nullable foreign key to `goods_receipt.id`, restrict on delete.
- `fiscal_year_id` foreign key to `fiscal_year.id`, restrict on delete.
- `financial_period_id` foreign key to `financial_period.id`, restrict on delete.
- `bill_date` date.
- `due_date` nullable date.
- `supplier_reference` nullable string.
- `reference` nullable string.
- `description` nullable text.
- `currency` string(3), foreign key to `currency.code`, restrict on delete.
- `fx_rate_e6` bigint default `1000000`.
- `subtotal_minor` bigint.
- `total_minor` bigint.
- `status` string with allowed values: `draft`, `submitted`, `approved`, `posted`, `cancelled`.
- `journal_entry_id` nullable foreign key to `journal_entry.id`, null on delete.
- `payable_entry_id` nullable foreign key to `payable_entry.id`, null on delete.
- `submitted_by`, `submitted_at` nullable.
- `approved_by`, `approved_at` nullable.
- `posted_by`, `posted_at` nullable.
- `cancelled_by`, `cancelled_at` nullable.
- `created_by`, `updated_by` nullable.
- `lock_version` integer.
- timestamps.

Do not add tax, VAT, discount, COGS, inventory, warehouse, branch, company, tenant, project, department, cost center, stock valuation, receivable, customer invoice, return, credit note, or debit note columns.

### `supplier_bill_line`

Minimum columns:

- `id` UUID primary key.
- `supplier_bill_id` foreign key to `supplier_bill.id`, cascade delete.
- `purchase_order_line_id` nullable foreign key to `purchase_order_line.id`, restrict on delete.
- `goods_receipt_line_id` nullable foreign key to `goods_receipt_line.id`, restrict on delete.
- `line_no` integer.
- `product_id` foreign key to `product.id`, restrict on delete.
- `unit_of_measure_id` foreign key to `unit_of_measure.id`, restrict on delete.
- `description` nullable text.
- `quantity_e6` bigint.
- `unit_cost_minor` bigint.
- `line_total_minor` bigint.
- timestamps.

No tax columns.

No discount columns.

No stock valuation columns.

No COGS columns.

## Source Modes

Support these source modes only:

1. Manual supplier bill line for active purchase-enabled `service` or `non_stock` products.
2. Bill from confirmed Purchase Order line.
3. Bill from confirmed Goods Receipt line.

For source-based lines:

- The Supplier Bill supplier and currency must match the source Purchase Order supplier and currency.
- `purchase_order_line_id`, when present, must belong to the selected `purchase_order_id`.
- `goods_receipt_line_id`, when present, must belong to the selected `goods_receipt_id`.
- A Purchase Order source must be `confirmed`.
- A Goods Receipt source must be `confirmed`.
- Product and UOM must match the source line.
- Do not perform UOM conversion in this slice.
- Unit cost must come from the Purchase Order line for source-based bill lines.
- For Goods Receipt source lines, derive unit cost from the linked Purchase Order line.
- Do not invent cost override semantics.

Reject ambiguous source usage:

- A bill may reference either a Purchase Order or a Goods Receipt, not both.
- A line may reference either a Purchase Order line or a Goods Receipt line, not both.
- A source line cannot be accepted without its matching source header.
- If a source header is selected, every bill line must reference a matching source line for that source mode.

## Stock Product Boundary

Supplier Bill posting in this slice must not post inventory valuation, landed cost, or COGS.

Therefore:

- Bill lines for `stock` products must be rejected in this slice.
- Allow only `service` and `non_stock` products for Supplier Bill posting.
- Do not create stock movements.
- Do not increase inventory.
- Do not create inventory valuation entries.
- Do not create goods-in-transit, GR/IR, landed cost, or inventory clearing entries.

If a confirmed Purchase Order or Goods Receipt contains a stock product, Supplier Bill creation or posting must fail with a clear validation message explaining that stock billing needs the later inventory costing/valuation decision slice.

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
line_total_minor = (quantity_e6 * unit_cost_minor) / 1000000
```

But implement it without floating division:

- use `intdiv($quantityE6 * $unitCostMinor, 1000000)`;
- reject overflow before multiplying;
- reject fractional minor units when the product is not exactly divisible by `1000000`;
- reject negative or zero quantities and negative costs.

Copy the exact integer approach from `SalesOrderService`, `PurchaseOrderService`, and the corrected `CustomerInvoiceService`; do not reintroduce `round()` or floating division.

## Bill Lifecycle

Implement:

- `create`
- `update`
- `submit`
- `approve`
- `post`
- `cancel`

Rules:

- Only `draft` bills can be updated.
- `draft` can be submitted.
- `submitted` can be approved.
- `approved` can be posted.
- `draft`, `submitted`, and `approved` can be cancelled.
- `posted` bills cannot be edited, cancelled, deleted, reposted as a new accounting document, or mutated through normal actions.
- Posting an already posted bill must be idempotent and return the existing posted bill.
- Posted bill correction belongs to later debit-note/credit-note/reversal work; do not implement it now.

Use optimistic locking (`lock_version`) for update and state transitions where appropriate.

## Source Quantity Limits

Prevent over-billing.

When billing from a Purchase Order line:

- Cumulative active bill quantity for that `purchase_order_line_id` must not exceed the Purchase Order line `quantity_e6`.
- Active bill lines are lines whose parent bill is not `cancelled`.

When billing from a Goods Receipt line:

- Cumulative active bill quantity for that `goods_receipt_line_id` must not exceed the Goods Receipt line `quantity_e6`.
- Active bill lines are lines whose parent bill is not `cancelled`.

Do not mutate Purchase Order lines or Goods Receipt lines to store billed quantities.

Derive billed quantities by summing `supplier_bill_line` rows through non-cancelled bills.

Use deterministic transaction locks:

1. Lock the Supplier Bill row for state transitions.
2. Lock the relevant Supplier, FiscalYear, and FinancialPeriod rows where needed.
3. Lock source PurchaseOrder / GoodsReceipt headers in deterministic order.
4. Lock referenced PurchaseOrderLine / GoodsReceiptLine rows ordered by ID.
5. Validate cumulative bill quantities inside the same transaction.

## Accounting Mapping

Do not hardcode expense account IDs.

Extend the existing accounting mapping system with a new key:

```text
purchase_expense
```

Required changes:

- Add `purchase_expense` to `AccountingAccountMappingService::ALLOWED_KEYS`.
- Update account-type validation so `purchase_expense` requires an active expense account with debit nature.
- Update database check constraints on `accounting_account_mapping.key` with a forward migration so existing keys remain valid and `purchase_expense` is allowed.
- Seed/configure `purchase_expense` idempotently in local/testing/demo seed paths when account `5100` General & Administrative Expenses exists.
- Do not create a UI page for mappings in this slice unless such a page already exists.

Posting must fail clearly if:

- `ap_control` is missing;
- `purchase_expense` is missing;
- either mapped account is inactive;
- either mapped account has the wrong type/nature;
- either mapped account currency does not match bill currency.

## Posting Requirements

Posting an approved Supplier Bill must happen in a single database transaction and through existing accounting infrastructure.

Required accounting effect:

```text
Dr Purchase Expense
Cr AP Control
```

Use:

- `JournalEntry` with `source_type = supplier_bill` and `source_id = supplier_bill.id`.
- `JournalLine` rows for Purchase Expense and AP Control.
- Use `journal_line.memo`, not a non-existent `description` field.
- Do not write non-existent JournalEntry fields such as `journal_number` or `fiscal_year_id`.
- `PostingEngine::post(...)` with explicit system posting to control accounts.
- `PayableEntry` credit row with `source_type = supplier_bill` and `source_id = supplier_bill.id`.

Posting constraints:

- FinancialPeriod must be open or reopened.
- `bill_date` must be inside the selected FinancialPeriod.
- `financial_period.fiscal_year_id` must match `supplier_bill.fiscal_year_id`.
- `fx_rate_e6` must be `1000000` in this slice unless exact FX conversion posting already exists and is proven by tests. Do not invent FX conversion rules.
- Journal and PayableEntry amounts must equal `supplier_bill.total_minor`.
- Transaction currency amounts must be preserved in journal, ledger, and payable rows.
- The posted JournalEntry must contain exactly the expected accounting lines for this slice.
- The Supplier Bill must link to the posted `journal_entry_id` and `payable_entry_id`.

Use existing idempotency patterns:

- Use `DatabaseIdempotencyStore` or the same proven pattern used by receipt/payment/opening-balance/customer-invoice posting services.
- Use `AccountingKernel::postingIdempotencyKey('supplier_bill', $billId, 'post')` or equivalent existing convention.
- Replayed post calls must not create duplicate journal entries, ledger entries, or payable entries.

Bill number allocation:

- Allocate number atomically at posting if missing.
- Use global number sequence key `supplier.bill`.
- Format: `BILL-YYYY-XXXXX`, where `YYYY` comes from `bill_date`.
- Do not include company or branch dimensions in numbering.

## Audit, Attachments, Notifications

Audit:

- Use the existing `AuditLogger` API backed by Spatie Activitylog.
- Log create, update, submit, approve, post, and cancel actions.
- Do not write new application records to legacy `audit_log`.

Attachments:

- Register `supplier_bill` in `config/erp_attachments.php`.
- Authorization must use purchasing/supplier bill permissions, not company or branch scope.

Notifications:

- No notification trigger is required in this slice unless the current codebase already has a simple user-targeted pattern for approval/post events.
- Do not invent company/branch notification ownership.

## RBAC

Use existing Spatie Permission module actions where possible:

- `purchasing.view`
- `purchasing.create`
- `purchasing.edit`
- `purchasing.submit`
- `purchasing.approve`
- `purchasing.post`
- `purchasing.cancel`

Do not enable Spatie Teams.

Do not add company-scoped or branch-scoped permissions.

## Routes

Add focused routes under `/purchasing/bills`:

- `GET /purchasing/bills`
- `POST /purchasing/bills`
- `PATCH` or `PUT /purchasing/bills/{supplierBill}` consistent with existing route style
- `POST /purchasing/bills/{supplierBill}/submit`
- `POST /purchasing/bills/{supplierBill}/approve`
- `POST /purchasing/bills/{supplierBill}/post`
- `POST /purchasing/bills/{supplierBill}/cancel`

Use policy/middleware checks consistent with existing Phase 4 controllers.

Do not add delete routes for posted accounting documents.

## Inertia UX

Create `resources/js/Pages/Purchasing/SupplierBills.tsx`.

UX requirements:

- English and Arabic support.
- RTL support.
- ERP-workspace layout, not a marketing page.
- Bill list with number, supplier, bill date, due date, status, total, source, supplier reference, and posting links.
- Create/edit modal or page with:
  - supplier selector;
  - bill date and due date;
  - currency selector;
  - source mode: manual, purchase order, goods receipt;
  - purchase order selector for confirmed purchase orders;
  - goods receipt selector for confirmed goods receipts;
  - line editor with product, UOM, quantity, unit cost, and server-computed total display.
- Product selectors for manual lines must show active purchase-enabled `service` and `non_stock` products only.
- Stock products should not be selectable for bill posting in this slice.
- Source line selectors must display remaining unbilled quantity.
- Show validation errors clearly.
- Show status badges.
- Hide actions the user lacks permission for.
- Show a polished empty state when no bills exist.
- Do not add tax, discount, inventory, COGS, landed cost, returns, credit notes, debit notes, or customer invoice UI.

Frontend preview math may be approximate for display, but server-side PHP integer validation is authoritative. Do not rely on JavaScript number math for persisted totals.

## Tests

Add focused tests proving:

- migrations create `supplier_bill` and `supplier_bill_line`.
- `purchase_expense` mapping key is allowed at service and DB constraint levels.
- no `company_id`, `branch_id`, or `tenant_id` columns exist.
- no tax, VAT, discount, COGS, inventory, stock movement, warehouse, receivable, customer invoice, return, credit note, or debit note columns exist.
- models and relationships load correctly.
- create/update Supplier Bill works for manual `service` product lines.
- create/update Supplier Bill works for manual `non_stock` product lines.
- `stock` product bill lines are rejected in this slice.
- confirmed Purchase Order source lines can be billed within remaining quantity.
- confirmed Goods Receipt source lines can be billed within remaining quantity.
- over-billing Purchase Order lines is rejected.
- over-billing Goods Receipt lines is rejected.
- source order/receipt supplier and currency must match bill header.
- source line cannot be accepted without its matching source header.
- source line product, UOM, and unit cost must match the source document.
- draft bill can be submitted.
- submitted bill can be approved.
- approved bill posts successfully.
- posting allocates `BILL-YYYY-XXXXX` once and is idempotent.
- posting creates exactly one JournalEntry, the expected JournalLine rows, expected LedgerEntry rows, and one PayableEntry credit.
- PostingEngine immutable ledger behavior remains intact.
- FinancialPeriod must be open/reopened.
- bill_date must be inside selected FinancialPeriod.
- `financial_period.fiscal_year_id` must match bill `fiscal_year_id`.
- missing `ap_control` mapping fails clearly.
- missing `purchase_expense` mapping fails clearly.
- mapped account wrong type/nature/currency fails clearly.
- `fx_rate_e6` other than `1000000` is rejected in this slice unless exact FX posting is already proven.
- cancelled bills cannot post.
- posted bills cannot update/cancel/repost into duplicates.
- audit entries are written through Spatie Activitylog via `AuditLogger`.
- attachment registry works for `supplier_bill`.
- Inertia page renders required props and permission-aware actions.
- no customer invoice, receivable entry, inventory entry, stock movement, COGS, tax, discount, landed cost, or return rows are created by Supplier Bill operations.

Add a source-scan assertion or dedicated test proving the authoritative Supplier Bill backend code contains no forbidden money/quantity float/rounding patterns:

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
php artisan test --filter=Phase4Slice6SupplierBillTest
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
rg -n "round\\(|\\(float\\)|float|double|/ 1000000|/1000000" laravel/app/Application/Purchasing laravel/app/Models/SupplierBill.php laravel/app/Models/SupplierBillLine.php laravel/tests/Feature/Phase4Slice6SupplierBillTest.php
```

Expected result: no authoritative Supplier Bill float/rounding usage and no false positives from the test file.

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

Do not mark Phase 4 complete. Mark only Phase 4 Slice 6 complete.

The next slice after successful Slice 6 should be an owner decision slice for Inventory Costing / Stock Product Posting, unless the owner chooses reports or another bounded correction first.

## Final Report Required

Return a concise implementation report with:

1. Files changed.
2. Migrations added.
3. Schema diff.
4. Models and relationships added.
5. Services/actions added.
6. Source line and over-billing rules.
7. Exact integer money/quantity approach.
8. Posting and idempotency approach.
9. RBAC permissions used or added.
10. Audit/attachment/notification changes.
11. Explicit confirmation that no company/branch/tenant scope was introduced.
12. Explicit confirmation that no inventory valuation, stock movement, COGS, VAT/tax, discounts, landed cost, returns, credit notes, debit notes, warehouse, customer invoice, receivable posting, or reports were introduced.
13. Test results.
14. Source-scan result.
15. Stress results.
16. Remaining risks or owner decisions needed.

