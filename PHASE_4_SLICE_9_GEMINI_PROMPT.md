# MINI ERP - PHASE 4 SLICE 9 OPERATIONAL REPORTS + RETURNS DECISION PACK

You are continuing the existing Mini ERP Laravel + Inertia migration.

Execute only Phase 4 Slice 9.

This is a bounded mixed slice:

1. Implement read-only Sales/Purchasing/Inventory operational reports using already durable Phase 4 documents.
2. Create an owner-ready decision pack for Returns, Credit Notes, and Debit Notes.

Do not implement returns, credit notes, debit notes, tax/VAT, automatic reversal workflows, or new posting document types in this pass.

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
- Phase 4 Slice 8 Moving Weighted Average Inventory Costing & Stock Product Posting, locally hardened.

Use these files first:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `PHASE_4_INVENTORY_COSTING_DECISION.md`
- `PHASE_4_SLICE_8_GEMINI_PROMPT.md`
- `laravel/app/Application/Sales/SalesOrderService.php`
- `laravel/app/Application/Purchasing/PurchaseOrderService.php`
- `laravel/app/Application/Sales/DeliveryNoteService.php`
- `laravel/app/Application/Purchasing/GoodsReceiptService.php`
- `laravel/app/Application/Sales/CustomerInvoiceService.php`
- `laravel/app/Application/Purchasing/SupplierBillService.php`
- `laravel/app/Application/Inventory/MovingWeightedAverageInventoryService.php`
- `laravel/app/Application/Accounting/PostingEngine.php`
- existing Phase 3 report query services/controllers/pages
- relevant Phase 4 migrations, models, controllers, pages, and tests

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
- VAT/tax workflow
- price lists, discount engine, or contract pricing
- automatic invoice/bill generation
- new returns, credit note, or debit note operational tables
- stock adjustment modules
- financial statements

If a relationship is not explicitly supported by current owner requirements, classify it as:

`UNDEFINED - DO NOT ASSUME`

Preserve:

- single-installation ERP context
- Spatie Permission with teams disabled
- Spatie Activitylog through the existing `AuditLogger`
- append-only audit behavior
- attachment authorization through the existing entity registry/service
- user-targeted notifications
- atomic global document numbering by key
- idempotent actions and posting
- integer minor-unit money only
- no floats in money, FX, balances, allocations, reports, or inventory valuation
- immutable posted accounting records
- corrections through future correction documents, not mutation of posted ledgers
- Moving Weighted Average inventory costing from Slice 8

## Hard Scope Boundary

### Implement In This Slice

Implement read-only operational reporting for existing documents only:

- Sales Order report/register.
- Purchase Order report/register.
- Delivery Note report/register.
- Goods Receipt report/register.
- Customer Invoice report/register.
- Supplier Bill report/register.
- Stock Movement report/register based on `stock_movement_ledger`.
- Optional: compact operational dashboard widgets if they reuse the same query services and do not create new business behavior.

Create a documentation decision pack:

- `PHASE_4_RETURNS_CREDIT_DEBIT_DECISION.md`

Update handoff/status docs after implementation:

- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`

### Do Not Implement In This Slice

Do not create:

- sales return tables
- purchase return tables
- customer credit note tables
- supplier credit note tables
- debit note tables
- return posting services
- return stock movement services
- tax/VAT tables or fields
- reversal posting flows beyond existing mechanisms
- automatic reversal of customer invoices or supplier bills
- cancellation of posted documents
- mutation of posted journals, ledger entries, AR/AP entries, or stock movement ledger rows

Returns/Credit/Debit Notes need owner decisions first.

## Part 1 - Operational Reports Implementation

Build reports as read-only query services and Inertia pages.

Follow existing report patterns from Phase 3 where possible:

- query service under `laravel/app/Application/Reports`
- controller under `laravel/app/Http/Controllers/Reports`
- page under `laravel/resources/js/Pages/Reports`
- route under authenticated web routes
- navigation in `AppLayout.tsx`
- translations in `en.json` and `ar.json`
- feature tests under `laravel/tests/Feature`

### Required Report Pages

Use concise, work-focused ERP UI. No marketing sections.

Suggested routes:

- `GET /reports/sales-orders`
- `GET /reports/purchase-orders`
- `GET /reports/delivery-notes`
- `GET /reports/goods-receipts`
- `GET /reports/customer-invoices`
- `GET /reports/supplier-bills`
- `GET /reports/stock-movements`

If existing route conventions require a slightly different naming pattern, use the existing convention and report it.

### Report Filter Requirements

Support practical filters where the underlying model has the data:

- date range
- status
- customer for sales documents
- supplier for purchasing documents
- product
- currency
- document number/search text

Do not add schema solely to support filters.

### Report Output Requirements

Each report must include:

- rows with document identifiers and related party/product context
- status
- date
- currency
- quantity totals where relevant
- minor-unit amount totals where relevant
- linked accounting document IDs where relevant:
  - `journal_entry_id`
  - `receivable_entry_id`
  - `payable_entry_id`
- summary totals at top of the page
- empty state with bilingual copy
- permission-aware access

Use integer minor-unit formatting helpers already in the frontend if present.

Do not calculate money using floats in PHP or TypeScript.

### Specific Report Notes

#### Sales Orders

Read from:

- `sales_order`
- `sales_order_line`
- `customer`
- `product`

Show:

- order number
- customer
- order date
- status
- currency
- total amount
- ordered quantity
- delivered/invoiced indicators if available from current tables

Do not infer future return eligibility.

#### Purchase Orders

Read from:

- `purchase_order`
- `purchase_order_line`
- `supplier`
- `product`

Show:

- order number
- supplier
- order date
- status
- currency
- total amount
- ordered quantity
- received/billed indicators if available from current tables

#### Delivery Notes

Read from:

- `delivery_note`
- `delivery_note_line`
- `sales_order`
- `customer`
- `product`

Show:

- delivery number
- sales order number
- customer
- delivery date
- status
- delivered quantity

Do not post accounting from the report.

#### Goods Receipts

Read from:

- `goods_receipt`
- `goods_receipt_line`
- `purchase_order`
- `supplier`
- `product`

Show:

- GRN number
- purchase order number
- supplier
- receipt date
- status
- received quantity

Do not post accounting from the report.

#### Customer Invoices

Read from:

- `customer_invoice`
- `customer_invoice_line`
- `customer`
- `journal_entry`
- `receivable_entry`

Show:

- invoice number
- customer
- invoice date
- due date
- status
- currency
- total amount
- journal link/id
- AR entry link/id

Do not create credit notes in this slice.

#### Supplier Bills

Read from:

- `supplier_bill`
- `supplier_bill_line`
- `supplier`
- `journal_entry`
- `payable_entry`

Show:

- bill number
- supplier
- bill date
- due date
- status
- currency
- total amount
- journal link/id
- AP entry link/id

Do not create debit notes in this slice.

#### Stock Movements

Read from:

- `stock_movement_ledger`
- `stock_balance`
- `product`
- `unit_of_measure`
- `currency`
- `journal_entry`

Show:

- movement date
- movement type
- source type/source ID/source line ID
- product
- quantity delta
- value delta
- balance quantity after movement
- balance value after movement
- journal entry link/id

Do not mutate stock movement records.

## Part 2 - Returns/Credit/Debit Decision Pack

Create:

- `PHASE_4_RETURNS_CREDIT_DEBIT_DECISION.md`

This is documentation only.

The decision pack must explain the exact owner decisions required before any implementation.

Cover at least:

### Sales Returns

Questions to answer:

- Must a sales return reference a posted Customer Invoice, a confirmed Delivery Note, or both?
- Can a sales return be created before invoice posting?
- Is returned stock accepted back into inventory?
- If stock is accepted back, should inventory value use:
  - original issue cost from `stock_movement_ledger`;
  - current moving average cost;
  - explicit inspected return value;
  - another owner-approved rule?
- How should partial returns be tracked?
- Should damaged/non-resellable returns be allowed now or deferred?

Accounting implications to document:

- AR reduction
- revenue reversal
- COGS reversal
- inventory asset increase when stock is returned to inventory
- no mutation of original invoice/journal/ledger rows

### Customer Credit Notes

Questions to answer:

- Are credit notes always tied to a posted Customer Invoice?
- Can credit notes be service-only without stock movement?
- Can credit notes be financial-only price corrections?
- Are standalone credit notes allowed?
- Should credit notes allocate automatically against receivables or stay open for later allocation?

Accounting implications to document:

- Dr sales revenue or relevant contra revenue account if later approved
- Cr AR control
- AR subledger credit
- optional stock/COGS reversal only when linked to stock return

Do not create a contra-revenue mapping in this slice unless it already exists and is explicitly used by current code. If needed later, document it as a future mapping decision.

### Purchase Returns

Questions to answer:

- Must a purchase return reference a posted Supplier Bill, a confirmed Goods Receipt, or both?
- Can goods be returned after GRN but before bill posting?
- If stock is returned to supplier, should inventory value use current moving average or original receipt value?
- How should partial supplier returns be tracked?
- Should damaged/rejected goods be a separate workflow?

Accounting implications to document:

- inventory asset decrease when stock leaves inventory
- GRNI/AP handling depending on whether bill has been posted
- purchase expense reversal only for service/non-stock corrections
- no mutation of original bill/journal/ledger rows

### Supplier Debit/Credit Notes

Use clear terminology and avoid guessing business usage.

Document:

- supplier credit note use cases, if the owner prefers that terminology
- supplier debit note use cases, if the owner prefers that terminology
- whether the ERP should model both or only one normalized supplier adjustment document
- whether notes are tied to posted Supplier Bills
- whether notes allocate automatically against payables

### Tax/VAT

Tax/VAT is not implemented in the current baseline.

Decision pack must state:

- tax effects are deferred;
- no tax fields/tables should be added until the owner approves a tax model;
- returns/credit/debit implementation must not silently invent tax behavior.

### Recommended Next Slice

The decision pack may recommend a default route, but it must label it as recommendation only.

Recommended conservative path for a Mini ERP:

1. Implement Customer Credit Note first for posted Customer Invoices.
2. Implement Supplier Debit/Credit adjustment next for posted Supplier Bills.
3. Add physical stock returns only after owner confirms stock return valuation rule.

Do not treat that recommendation as owner-approved.

## Permissions

Use existing Spatie Permission patterns.

If adding permissions, keep them explicit and report them.

Suggested report permissions:

- `reports.sales.view`
- `reports.purchasing.view`
- `reports.inventory.view`

If the current RBAC config already has a better report permission convention, use it.

Do not add company/branch scoped permissions.

## Audit, Attachments, Notifications

Operational reports are read-only. They do not need audit rows for simple viewing unless existing project policy requires it.

Do not add attachments to reports.

Do not trigger notifications from report viewing.

The decision pack is documentation only.

## Tests Required

Add focused feature tests proving:

- each report route renders successfully for an authorized user;
- unauthorized users are denied;
- filters work for date/status/party where applicable;
- totals are calculated using integer minor-unit values;
- report rows include linked journal/subledger IDs where relevant;
- stock movement report reads immutable `stock_movement_ledger`;
- no operational report mutates source documents, accounting ledgers, AR/AP entries, or stock movement rows;
- no `company_id`, `branch_id`, `tenant_id`, `currentCompany`, `currentBranch`, or Spatie Teams usage is introduced;
- no floats, `round()`, or binary floating-point calculations are introduced in report query code.

Update existing tests only when their old expectation conflicts with completed Slice 8 behavior. Do not weaken important invariants.

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

Add a slice-specific report test command summary if you create a dedicated test class.

## Required Final Report

After implementation, report:

1. Files changed.
2. Migrations added. Expected: none unless a clear report-only need exists.
3. Routes/pages/query services added.
4. Decision pack created and exact owner decisions still required.
5. Relationships added and evidence for each. Expected: report read models only; no new ownership assumptions.
6. Unsupported assumptions avoided.
7. Remaining `company_id`, `branch_id`, `tenant_id` occurrences introduced by this slice, if any, with explicit justification. Expected: none.
8. RBAC changes.
9. Audit/attachment/notification impact.
10. Test results.
11. Stress results.
12. Remaining risks.

Stop after Slice 9. Do not start Slice 10.
