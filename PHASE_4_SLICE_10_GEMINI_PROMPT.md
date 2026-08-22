# MINI ERP - PHASE 4 SLICE 10 RETURNS, CREDIT/DEBIT NOTES & CLOSE-OUT

You are continuing the existing Mini ERP Laravel + Inertia migration.

Execute only Phase 4 Slice 10.

This slice implements the approved safe operating model recorded in:

- `PHASE_4_RETURNS_CREDIT_DEBIT_DECISION.md`

The model intentionally separates physical returns from financial adjustment notes so the ERP can handle pre-invoice/pre-bill and post-invoice/post-bill scenarios without mutating posted accounting or stock ledgers.

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
- Phase 4 Slice 8 Moving Weighted Average Inventory Costing & Stock Product Posting.
- Phase 4 Slice 9 Read-only Operational Reports + Returns/Credit/Debit Decision Pack.

Read first:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `PHASE_4_RETURNS_CREDIT_DEBIT_DECISION.md`
- `PHASE_4_SLICE_9_GEMINI_PROMPT.md`
- `laravel/app/Application/Sales/DeliveryNoteService.php`
- `laravel/app/Application/Sales/CustomerInvoiceService.php`
- `laravel/app/Application/Purchasing/GoodsReceiptService.php`
- `laravel/app/Application/Purchasing/SupplierBillService.php`
- `laravel/app/Application/Inventory/MovingWeightedAverageInventoryService.php`
- `laravel/app/Application/Accounting/PostingEngine.php`
- `laravel/app/Application/Accounting/AccountingAccountMappingService.php`
- existing Phase 3 AR/AP allocation services
- relevant Phase 4 migrations, models, controllers, pages, reports, and tests

Use the current Laravel code as the source of truth.

Do not treat old Next.js docs, generated specs, or historical prompts as proof of unsupported business relationships.

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
- warehouse/location semantics
- warehouse-to-branch relationship
- branch/location authorization
- FIFO layers
- Standard Costing
- landed cost/freight allocation
- price lists, discount engine, or contract pricing
- automatic allocation of credit/debit notes
- mutation of posted invoices, supplier bills, journals, ledger entries, AR/AP entries, or stock movement ledger rows

Preserve:

- single-installation ERP context
- Spatie Permission with teams disabled
- Spatie Activitylog through the existing `AuditLogger`
- append-only audit behavior
- attachment authorization through the existing entity registry/service
- user-targeted notifications only when meaningful
- atomic global document numbering by key
- idempotent posting/actions
- integer minor-unit money only
- integer `quantity_e6` stock math only
- no floats, no `(float)`, no `round()`, and no binary floating-point arithmetic
- immutable posted accounting records
- immutable stock movement ledger
- Moving Weighted Average inventory costing from Slice 8

If a relationship is not explicitly supported by current owner requirements, classify it as:

`UNDEFINED - DO NOT ASSUME`

## Approved Operating Model

Implement four document families plus one customer-facing corrected invoice snapshot:

1. `sales_return`
   - physical return of goods from customer;
   - sourced from confirmed `delivery_note_line`;
   - may optionally link to posted `customer_invoice_line` when the delivered goods were already invoiced.

2. `customer_credit_note`
   - financial AR/revenue/tax adjustment for a customer;
   - normally linked to posted `customer_invoice`;
   - may link to a `sales_return` when caused by physical return;
   - supports service/price-only credit notes with no stock movement.

3. `customer_invoice_revision`
   - immutable corrected invoice copy/snapshot generated after posted invoice returns or credits;
   - shows original invoiced quantities, returned/credited quantities, and net remaining quantities;
   - customer-facing printable/exportable artifact;
   - not a new accounting invoice and must not create duplicate GL, AR, stock, or tax postings.

4. `purchase_return`
   - physical return of goods to supplier;
   - sourced from confirmed `goods_receipt_line`;
   - may optionally link to posted `supplier_bill_line` when the received goods were already billed.

5. `supplier_adjustment_note`
   - normalized supplier financial adjustment document;
   - UI may label it "Supplier Credit Note" or "Supplier Debit Note";
   - primary Slice 10 use case: reduce AP after a posted supplier bill or record service/price-only supplier adjustment;
   - may link to a `purchase_return` when caused by physical return.

Manual allocation rule:

- Create AR/AP subledger credit/debit entries as open items.
- Do not auto-allocate them to invoices/bills.
- Implement explicit manual settlement/allocation support where needed by extending the existing allocation services/pages to handle credit/debit note entries.
- A user may manually allocate a posted `customer_credit_note` receivable credit against the original invoice receivable debit or another open customer receivable debit.
- A user may manually allocate a posted `supplier_adjustment_note` payable debit/credit against the original supplier bill payable entry or another open supplier payable entry.
- Allocation/settlement must not create GL, journal, ledger, stock movement, revenue, COGS, or inventory postings. The GL effect happens when the credit/debit note posts; allocation only settles open subledger items.

Accounting clarity rule:

- The original posted customer invoice remains the revenue recognition document:
  - Dr `ar_control`
  - Cr `sales_revenue`
  - Cr `output_tax_payable` only if tax was explicitly posted for that invoice in the current implementation.
- Customer returns/credits must not reverse revenue by editing the invoice. They must post a separate credit note:
  - Dr `sales_returns` as contra-revenue;
  - Dr `output_tax_payable` for manual tax reversal when applicable;
  - Cr `ar_control`;
  - create one customer receivable credit entry.
- Physical stock return accounting is separate from revenue reversal:
  - restock: Dr `inventory_asset`, Cr `cogs`;
  - manual restock value: Dr `inventory_asset`, Cr `cogs`, variance to `inventory_return_variance`;
  - scrap/damaged: Dr `inventory_scrap_loss`, Cr `cogs`.
- The corrected invoice revision displays net commercial result for the customer, but it is not the accounting revenue document.

Manual tax rule:

- Support manual tax percentage as integer basis points:
  - `0` = 0.00%
  - `1400` = 14.00%
  - `1500` = 15.00%
- Support exact manual tax amount override where needed.
- This is a bounded document-level/manual tax baseline only. Do not implement VAT filing, tax returns, tax reports, registration rules, exemptions, withholding, or jurisdiction logic.

## Required Customer Invoice Return Workflow

Implement the owner-requested customer workflow for posted invoices:

1. User opens a posted `customer_invoice`.
2. User chooses **Create Return From Invoice**.
3. UI shows all eligible invoice lines with:
   - product;
   - UOM;
   - original invoiced quantity;
   - already returned/credited quantity from posted returns/credit notes;
   - maximum still-returnable quantity;
   - unit price and line amount;
   - linked delivery note line when available.
4. User selects the returned line items and quantities.
5. User selects line disposition:
   - `restock_original_cost`: return to saleable stock at original issue cost;
   - `restock_manual_value`: return to saleable stock at a manually inspected value;
   - `scrap_no_restock`: record returned/damaged goods without increasing saleable stock.
6. System creates a `sales_return` linked to the selected `customer_invoice_line` rows and their source `delivery_note_line` rows.
7. System creates the related `customer_credit_note`.
8. Posting creates stock/accounting/subledger effects through new return/credit documents only.
9. System generates a new immutable customer-facing corrected invoice copy using `customer_invoice_revision`.

Important:

- Do not mutate the original posted invoice header, lines, totals, journal entry, ledger entries, or receivable entry.
- The corrected invoice copy is a revision/snapshot for display, print, PDF/export, and customer communication.
- The corrected invoice copy must show:
  - original invoice number;
  - revision number, e.g. `INV-2026-00001-R01`;
  - linked Sales Return number(s);
  - linked Customer Credit Note number(s);
  - original quantity;
  - returned quantity;
  - net remaining quantity;
  - original amount;
  - credited amount;
  - net amount.
- Multiple returns against the same invoice must create sequential revisions: `R01`, `R02`, `R03`.
- Latest revision must reflect cumulative posted returns/credits, not only the last transaction.
- Draft or cancelled returns/credits must not affect revision net quantities.
- If the invoice is already paid, the credit note still creates an open customer credit. Do not auto-refund or auto-allocate.

## Required Migrations

Use singular table naming consistent with the current schema.

Create forward migrations only.

### `sales_return`

Required columns:

- `id` UUID primary key
- `number` nullable string unique when present
- `customer_id` FK to `customer`, restrict on delete
- `delivery_note_id` nullable FK to `delivery_note`, restrict on delete
- `customer_invoice_id` nullable FK to `customer_invoice`, restrict on delete
- `fiscal_year_id` FK to `fiscal_year`, restrict on delete
- `financial_period_id` FK to `financial_period`, restrict on delete
- `return_date` date
- `status` string: `draft`, `submitted`, `approved`, `posted`, `cancelled`
- `currency` string(3), FK to `currency(code)`, restrict on delete
- `reason` nullable text
- `notes` nullable text
- `journal_entry_id` nullable FK to `journal_entry`, restrict or set null only if consistent with current immutability
- `created_by`, `updated_by`, `submitted_by`, `approved_by`, `posted_by`, `cancelled_by`
- timestamps for status transitions
- `lock_version` integer

No company/branch/tenant columns.

### `sales_return_line`

Required columns:

- `id` UUID primary key
- `sales_return_id` FK, cascade on delete only while draft; posted documents must be immutable through service rules
- `line_no`
- `delivery_note_line_id` FK to `delivery_note_line`, restrict on delete
- `customer_invoice_line_id` nullable FK to `customer_invoice_line`, restrict on delete
- `product_id` FK to `product`
- `unit_of_measure_id` FK to `unit_of_measure`
- `description` nullable text
- `quantity_e6` positive bigInteger
- `disposition` string:
  - `restock_original_cost`
  - `restock_manual_value`
  - `scrap_no_restock`
- `original_issue_cost_minor` bigInteger
- `manual_restock_value_minor` nullable bigInteger
- `stock_value_minor` bigInteger
- `variance_minor` bigInteger default 0
- timestamps

Rules:

- cumulative returned quantity for a `delivery_note_line_id` must never exceed delivered quantity;
- if `customer_invoice_line_id` is present, it must belong to `customer_invoice_id` and match product/UOM/source quantity boundaries;
- restock lines increase saleable stock;
- scrap lines do not increase saleable stock.

### `customer_credit_note`

Required columns:

- `id` UUID primary key
- `number` nullable string unique when present
- `customer_id` FK to `customer`
- `customer_invoice_id` nullable FK to `customer_invoice`
- `sales_return_id` nullable FK to `sales_return`
- `fiscal_year_id` FK to `fiscal_year`
- `financial_period_id` FK to `financial_period`
- `credit_date` date
- `due_date` nullable date
- `status` string: `draft`, `submitted`, `approved`, `posted`, `cancelled`
- `currency` string(3)
- `subtotal_minor` bigInteger default 0
- `tax_rate_bps` unsigned integer default 0
- `tax_minor` bigInteger default 0
- `total_minor` bigInteger default 0
- `tax_mode` string: `none`, `manual_rate`, `manual_amount`
- `reason` nullable text
- `notes` nullable text
- `journal_entry_id` nullable FK to `journal_entry`
- `receivable_entry_id` nullable FK to `receivable_entry`
- lifecycle user/timestamp fields
- `lock_version`
- timestamps

Rules:

- posted credit notes create AR credit entries but remain unallocated;
- no automatic allocation;
- no mutation of the original invoice or receivable entry.

### `customer_credit_note_line`

Required columns:

- `id` UUID primary key
- `customer_credit_note_id` FK
- `line_no`
- `customer_invoice_line_id` nullable FK
- `sales_return_line_id` nullable FK
- `product_id` nullable FK
- `unit_of_measure_id` nullable FK
- `description` text
- `quantity_e6` nullable bigInteger
- `unit_price_minor` bigInteger default 0
- `line_subtotal_minor` bigInteger
- `tax_rate_bps` unsigned integer default 0
- `tax_minor` bigInteger default 0
- `line_total_minor` bigInteger
- timestamps

Rules:

- support service/price-only credits without stock movement;
- support stock credits linked to posted invoice lines and/or sales return lines;
- cumulative credited quantity/amount must never exceed the source invoice line unless explicitly a price-only correction.

### `customer_invoice_revision`

Required columns:

- `id` UUID primary key
- `customer_invoice_id` FK to `customer_invoice`, restrict on delete
- `customer_credit_note_id` nullable FK to `customer_credit_note`, restrict on delete
- `sales_return_id` nullable FK to `sales_return`, restrict on delete
- `revision_no` unsigned integer
- `display_number` string unique, e.g. `INV-2026-00001-R01`
- `revision_date` date
- `currency` string(3), FK to `currency(code)`, restrict on delete
- `original_subtotal_minor` bigInteger default 0
- `credited_subtotal_minor` bigInteger default 0
- `net_subtotal_minor` bigInteger default 0
- `original_tax_minor` bigInteger default 0
- `credited_tax_minor` bigInteger default 0
- `net_tax_minor` bigInteger default 0
- `original_total_minor` bigInteger default 0
- `credited_total_minor` bigInteger default 0
- `net_total_minor` bigInteger default 0
- `snapshot_json` nullable json/jsonb for printable metadata only
- `created_by`
- timestamps

Required indexes/constraints:

- unique (`customer_invoice_id`, `revision_no`)
- unique (`display_number`)
- index `customer_invoice_id`
- index `customer_credit_note_id`
- index `sales_return_id`

Rules:

- append-only snapshot; never update an existing revision after creation;
- enforce revision/revision-line immutability with database triggers on PostgreSQL and SQLite where consistent with existing project immutability patterns;
- creates no GL, AR, stock, tax filing, or ledger effects;
- generated only from posted customer invoice plus posted returns/credit notes;
- latest revision reflects cumulative posted returns/credits for that invoice;
- use `display_number = original_invoice_number + '-R' + padded revision_no`;
- do not allocate a new `INV-YYYY-XXXXX` number because this is not a new accounting invoice.

### `customer_invoice_revision_line`

Required columns:

- `id` UUID primary key
- `customer_invoice_revision_id` FK to `customer_invoice_revision`
- `customer_invoice_line_id` nullable FK to `customer_invoice_line`
- `product_id` nullable FK to `product`
- `unit_of_measure_id` nullable FK to `unit_of_measure`
- `line_no`
- `description` text
- `original_quantity_e6` nullable bigInteger
- `returned_quantity_e6` nullable bigInteger
- `net_quantity_e6` nullable bigInteger
- `unit_price_minor` bigInteger default 0
- `original_subtotal_minor` bigInteger default 0
- `credited_subtotal_minor` bigInteger default 0
- `net_subtotal_minor` bigInteger default 0
- `original_tax_minor` bigInteger default 0
- `credited_tax_minor` bigInteger default 0
- `net_tax_minor` bigInteger default 0
- `original_total_minor` bigInteger default 0
- `credited_total_minor` bigInteger default 0
- `net_total_minor` bigInteger default 0
- `source_summary_json` nullable json/jsonb for linked return/credit note line references
- timestamps

Rules:

- for stock/service quantity credits, `net_quantity_e6 = original_quantity_e6 - cumulative_posted_returned_quantity_e6`;
- for price-only credits, quantity can remain unchanged while credited/net amounts change;
- net quantity and net amount must never go negative;
- line snapshots preserve text, product/UOM references, quantities, prices, and amounts as rendered to the customer.

### `purchase_return`

Required columns:

- `id` UUID primary key
- `number` nullable string unique when present
- `supplier_id` FK to `supplier`
- `goods_receipt_id` nullable FK to `goods_receipt`
- `supplier_bill_id` nullable FK to `supplier_bill`
- `fiscal_year_id` FK to `fiscal_year`
- `financial_period_id` FK to `financial_period`
- `return_date` date
- `status` string: `draft`, `submitted`, `approved`, `posted`, `cancelled`
- `currency` string(3)
- `reason` nullable text
- `notes` nullable text
- `journal_entry_id` nullable FK to `journal_entry`
- lifecycle user/timestamp fields
- `lock_version`
- timestamps

Rules:

- pre-bill purchase returns clear GRNI, not AP;
- post-bill purchase returns can be linked to supplier adjustment notes for AP reduction;
- no mutation of original GRN, supplier bill, GL, AP, or stock movement rows.

### `purchase_return_line`

Required columns:

- `id` UUID primary key
- `purchase_return_id` FK
- `line_no`
- `goods_receipt_line_id` FK to `goods_receipt_line`
- `supplier_bill_line_id` nullable FK to `supplier_bill_line`
- `product_id` FK to `product`
- `unit_of_measure_id` FK to `unit_of_measure`
- `description` nullable text
- `quantity_e6` positive bigInteger
- `original_receipt_cost_minor` bigInteger
- `stock_value_minor` bigInteger
- `variance_minor` bigInteger default 0
- timestamps

Rules:

- cumulative returned quantity for a `goods_receipt_line_id` must never exceed received quantity;
- default valuation is original receipt cost;
- if exact original-cost removal would make stock valuation unsafe, post the difference to an explicit variance/adjustment mapping rather than hiding it.

### `supplier_adjustment_note`

Required columns:

- `id` UUID primary key
- `number` nullable string unique when present
- `supplier_id` FK to `supplier`
- `supplier_bill_id` nullable FK to `supplier_bill`
- `purchase_return_id` nullable FK to `purchase_return`
- `fiscal_year_id` FK to `fiscal_year`
- `financial_period_id` FK to `financial_period`
- `adjustment_date` date
- `direction` string:
  - `decrease_payable`
  - `increase_payable`
- `ui_label` nullable string for display only, e.g. `supplier_credit_note`, `supplier_debit_note`
- `status` string: `draft`, `submitted`, `approved`, `posted`, `cancelled`
- `currency` string(3)
- `subtotal_minor` bigInteger default 0
- `tax_rate_bps` unsigned integer default 0
- `tax_minor` bigInteger default 0
- `total_minor` bigInteger default 0
- `tax_mode` string: `none`, `manual_rate`, `manual_amount`
- `reason` nullable text
- `notes` nullable text
- `journal_entry_id` nullable FK to `journal_entry`
- `payable_entry_id` nullable FK to `payable_entry`
- lifecycle user/timestamp fields
- `lock_version`
- timestamps

Rules:

- posted supplier adjustment notes create AP entries but remain unallocated;
- no automatic allocation;
- no mutation of original supplier bill or payable entry.

### `supplier_adjustment_note_line`

Required columns:

- `id` UUID primary key
- `supplier_adjustment_note_id` FK
- `line_no`
- `supplier_bill_line_id` nullable FK
- `purchase_return_line_id` nullable FK
- `product_id` nullable FK
- `unit_of_measure_id` nullable FK
- `description` text
- `quantity_e6` nullable bigInteger
- `unit_cost_minor` bigInteger default 0
- `line_subtotal_minor` bigInteger
- `tax_rate_bps` unsigned integer default 0
- `tax_minor` bigInteger default 0
- `line_total_minor` bigInteger
- timestamps

Rules:

- support service/price-only supplier adjustments without stock movement;
- support stock adjustments linked to posted bill lines and/or purchase return lines;
- cumulative adjusted quantity/amount must never exceed source bill line unless explicitly a price-only correction.

## Accounting Mappings

Extend `AccountingAccountMappingService`, config constraints, seeders, and tests for these keys:

- existing:
  - `ar_control`
  - `ap_control`
  - `inventory_asset`
  - `grni_clearing`
  - `cogs`
- new:
  - `sales_returns`: contra revenue / debit-normal account preferred; if local account validation lacks contra-revenue support, extend it through existing AccountCategory/AccountType structures, not a new domain enum.
  - `inventory_return_variance`: expense/debit account, can be debited or credited by posting lines.
  - `inventory_scrap_loss`: expense/debit account.
  - `purchase_returns_allowances`: expense/debit account used as a contra/allowance account for supplier price/service adjustments where inventory is not directly affected.
  - `output_tax_payable`: liability/credit account for manual sales tax reversal.
  - `input_tax_receivable`: asset/debit account for manual purchase tax reversal.

Seed default accounts idempotently only if the existing chart permits it. Do not break existing user-configured mappings.

## Posting Rules

All posting must go through `PostingEngine`.

Do not insert ledger entries directly.

### Sales Return Posting

For each line:

1. Resolve original stock issue movement:
   - source type `delivery_note`
   - source line ID = `delivery_note_line_id`
   - movement type = issue or equivalent current Slice 8 source.
2. Calculate original issue cost proportionally using integer math.
3. If disposition is `restock_original_cost`:
   - increase stock balance using a return/reversal stock movement;
   - Dr `inventory_asset`
   - Cr `cogs`
4. If disposition is `restock_manual_value`:
   - increase stock balance using manual inspected value;
   - Dr `inventory_asset` for manual value;
   - Cr `cogs` for original issue cost;
   - post difference to `inventory_return_variance` as debit or credit as needed.
5. If disposition is `scrap_no_restock`:
   - do not increase saleable stock balance;
   - Dr `inventory_scrap_loss`
   - Cr `cogs`

Use idempotency guards so one sales return line cannot create duplicate stock movements or duplicate journals.

### Customer Credit Note Posting

For net amount:

- Dr `sales_returns`

For manual sales tax:

- Dr `output_tax_payable`

For total credit:

- Cr `ar_control`

Create one `receivable_entry` credit linked to the customer credit note.

Do not auto-allocate.

Settlement behavior:

- expose an explicit manual settlement/allocation action after posting;
- allow allocation of the credit note's `receivable_entry` credit against the original invoice `receivable_entry` debit when both belong to the same customer and currency;
- allow allocation against another open customer receivable debit if selected by the user;
- reject allocation beyond remaining open debit/credit balance;
- allocation must update allocation/open-balance fields through the existing AR allocation pattern only;
- allocation must not create new journal entries or ledger entries because AR control was already reduced at credit note posting time.

Revenue/return reporting behavior:

- the original invoice remains in revenue history as originally posted;
- `sales_returns` carries the return/credit value for net sales reporting;
- reports should be able to derive:
  - gross sales from posted invoices;
  - sales returns from posted credit notes;
  - net sales = gross sales - sales returns;
  - AR balance from receivable debit/credit entries and allocations.

### Customer Invoice Revision Generation

After posting an invoice-linked `customer_credit_note`, generate a `customer_invoice_revision` snapshot.

Rules:

- original posted `customer_invoice` and `customer_invoice_line` records remain unchanged;
- calculate cumulative posted returned/credited quantities per invoice line;
- calculate net quantities and net amounts using integer math only;
- create `revision_no = max(existing revision_no for invoice) + 1`;
- create `display_number = original invoice number + '-R' + two-digit revision number`;
- if two users try to post returns/credits for the same invoice concurrently, lock the original invoice row and revision sequence calculation so revision numbers remain unique and ordered;
- the revision has no `journal_entry_id`, `receivable_entry_id`, or stock movement link because it is not an accounting document;
- printing/exporting should use the immutable revision snapshot, not live recalculation.

### Purchase Return Posting

For each line:

1. Resolve original stock receipt movement:
   - source type `goods_receipt`
   - source line ID = `goods_receipt_line_id`
   - movement type = receipt or equivalent current Slice 8 source.
2. Calculate original receipt cost proportionally using integer math.
3. Reduce stock balance through a return/reversal stock movement.
4. If no posted supplier bill line is linked:
   - Dr `grni_clearing`
   - Cr `inventory_asset`
5. If a posted supplier bill line is linked and this physical return itself carries the financial effect:
   - Dr `ap_control`
   - Cr `inventory_asset`
   - create one `payable_entry` debit linked to the purchase return.
6. If a separate `supplier_adjustment_note` is used for financial effect:
   - purchase return should handle only the approved stock/GRNI effect;
   - supplier adjustment note handles AP.

The implementation must choose one consistent post-bill path and document it in tests/docs. Prefer separate `supplier_adjustment_note` for post-bill AP impact if feasible.

### Supplier Adjustment Note Posting

For `direction = decrease_payable`:

- Dr `ap_control` for total adjustment.
- Cr `purchase_returns_allowances` or the linked return/variance account for net amount.
- Cr `input_tax_receivable` for manual tax amount.
- Create one `payable_entry` debit.

For `direction = increase_payable`:

- Dr relevant configured expense/adjustment account.
- Dr `input_tax_receivable` if manual tax applies.
- Cr `ap_control`.
- Create one `payable_entry` credit.

Do not auto-allocate.

## Tax Calculation

Do not use floats.

Tax rate is stored as basis points:

```php
// 14.00% = 1400 bps
$taxMinor = intdiv(($baseMinor * $taxRateBps) + 5000, 10000);
```

The integer half-up formula above is allowed; do not use PHP `round()`.

If exact tax amount override is provided, validate it is non-negative and use it directly.

Persist both rate and amount for auditability.

## Stock Movement Integration

Extend the existing inventory service rather than duplicating valuation logic.

Allowed approach:

- add explicit return/reversal methods to `MovingWeightedAverageInventoryService`;
- use `stock_movement_ledger.movement_type = reversal` if current DB constraints only allow `receipt`, `issue`, `reversal`;
- use `source_type` (`sales_return`, `purchase_return`) and `source_line_id` for idempotency and reporting clarity.

Do not mutate existing `stock_movement_ledger` rows.

Do not delete stock movement rows.

## Routes, Controllers, Pages

Add application services, controllers, routes, Inertia pages, and navigation entries for:

- `/sales/returns`
- `/sales/credit-notes`
- `/sales/invoice-revisions` or nested invoice revision routes under `/sales/invoices/{invoice}/revisions`
- `/purchasing/returns`
- `/purchasing/adjustment-notes`

Use project naming conventions if they differ, and report the exact routes.

Pages must support:

- list view with filters;
- posted invoice action: **Create Return From Invoice**;
- invoice-line selection grid with original quantity, previously returned quantity, max returnable quantity, selected return quantity, disposition, and manual inspected value when needed;
- corrected invoice revision view/print action after credit note posting;
- create draft;
- edit draft;
- submit;
- approve;
- post;
- cancel draft/submitted/approved where safe;
- clear validation errors;
- bilingual English/Arabic labels;
- no explanatory marketing text.

UI must be functional and compact, consistent with existing ERP pages.

## Attachments, Audit, Notifications

Register attachment authorization entities:

- `sales_return`
- `customer_credit_note`
- `customer_invoice_revision` if revisions can receive their own exported/printed file attachment; otherwise revision attachments can remain on the original invoice/credit note and this choice must be reported.
- `purchase_return`
- `supplier_adjustment_note`

Audit all lifecycle transitions through `AuditLogger`.

Notifications are optional but useful for approval/posting events. If added, keep them user-targeted and idempotent.

## RBAC

Use existing Spatie Permission patterns.

Add or reuse explicit permissions:

- `sales.returns`
- `sales.credit_notes`
- `sales.invoice_revisions` or reuse `sales.view` for read-only revision display/print if that matches local permission style
- `purchasing.returns`
- `purchasing.adjustment_notes`

If the current permission naming convention requires action-style permissions instead, follow the local convention and report it.

Do not add company/branch scoped permissions.

## Tests Required

Create focused feature tests covering:

### Sales Return

- pre-invoice sales return from confirmed Delivery Note posts stock/COGS reversal only;
- post-invoice sales return can link to invoice line and does not mutate original invoice/journal/ledger;
- partial returns cannot exceed delivered quantity cumulatively;
- restock original cost uses original issue cost;
- restock manual value posts explicit variance;
- damaged/scrap return does not increase saleable stock and posts scrap loss;
- idempotent post replay creates one journal and one stock movement per line.

### Customer Credit Note

- invoice-linked credit note posts Dr `sales_returns`, Dr `output_tax_payable` when tax applies, Cr `ar_control`;
- service/price-only credit note works without stock movement;
- tax percentage basis points and exact tax override use integer math only;
- creates one receivable credit entry;
- remains unallocated until manual allocation;
- manual settlement can allocate the credit note receivable credit against the original invoice receivable debit;
- settlement updates AR open/allocated balances without creating journal or ledger entries;
- cannot exceed source invoice line quantity/amount except explicit price-only correction rules.

### Customer Invoice Revision

- posted invoice action lets the user select returned invoice lines and quantities;
- maximum returnable quantity equals original invoiced quantity minus cumulative posted returned/credited quantity;
- over-return is rejected at validation and database/concurrency level;
- original invoice header, lines, totals, journal, ledger, and receivable entry remain unchanged;
- posting the return/credit creates `customer_invoice_revision` `R01`;
- a second posted return/credit creates `R02` reflecting cumulative returned quantity;
- cancelled/draft returns and credit notes do not affect the revision;
- revision lines show original quantity, returned quantity, and net remaining quantity;
- database immutability blocks update/delete of generated revision header/lines;
- paid invoice return creates open customer credit without auto-refund or auto-allocation;
- revision snapshot creates no GL/AR/stock entries.

### Purchase Return

- pre-bill purchase return from confirmed Goods Receipt posts Dr `grni_clearing`, Cr `inventory_asset`;
- post-bill path reduces AP through the chosen supplier adjustment path;
- original receipt cost is used;
- partial returns cannot exceed received quantity cumulatively;
- idempotent post replay creates one journal and one stock movement per line.

### Supplier Adjustment Note

- post-bill supplier adjustment decreasing payable posts Dr `ap_control`, Cr allowance/adjustment, Cr `input_tax_receivable` when tax applies;
- service/price-only supplier adjustment works without stock movement;
- creates one payable debit entry;
- remains unallocated until manual allocation;
- supports UI terminology without creating duplicate document models.

### Cross-Cutting

- unauthorized users are denied;
- attachments entity registry includes return/adjustment entities and includes `customer_invoice_revision` if revisions can receive their own exported/printed file attachments;
- audit entries are written through Spatie Activitylog via `AuditLogger`;
- gross sales, sales returns, net sales, AR open balance, and settled balance can be derived from posted invoices, posted credit notes, receivable entries, and allocations;
- no `company_id`, `branch_id`, `tenant_id`, `currentCompany`, `currentBranch`, `company_user`, or Spatie Teams usage is introduced;
- no floats, `(float)`, `round()`, or float division in backend note/return/tax/stock code;
- PostgreSQL stress test or command verifies idempotent posting/concurrent post replay for returns/notes;
- full suite remains green.

## Documentation Updates

Update:

- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `CHANGELOG.md`
- `PHASE_4_RETURNS_CREDIT_DEBIT_DECISION.md` only if implementation discovers a necessary correction.

## Verification Gate

Run from `laravel/`:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan accounting:phase3-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

If `concurrency:stress --workers=100` is blocked by the local Windows paging file, report it explicitly and rerun with the highest worker count the machine can support. Do not hide the limitation.

## Required Final Report

Report:

1. Files changed.
2. Migrations added.
3. Schema diff.
4. Document models and relationships added, with evidence.
5. Posting flows implemented.
6. Customer invoice return workflow and corrected invoice revision behavior.
7. Tax behavior implemented.
8. Allocation/settlement behavior: confirm manual/open only, with no extra GL on settlement.
9. Revenue/return accounting: confirm gross sales, sales returns, net sales, and AR/AP open balances are derivable from posted documents and subledger allocations.
10. Unsupported assumptions avoided.
11. Remaining `company_id`, `branch_id`, `tenant_id` occurrences introduced by this slice, if any, with explicit justification. Expected: none.
12. RBAC changes.
13. Audit/attachment/notification integration.
14. Test results.
15. Stress results.
16. Remaining risks.

Stop after Slice 10. Do not start payroll, rentals, fixed assets, taxes beyond bounded manual note tax fields, warehouses, landed cost, or financial statements.
