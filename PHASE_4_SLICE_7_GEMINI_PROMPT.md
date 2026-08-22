# MINI ERP — PHASE 4 SLICE 7 INVENTORY COSTING DECISION PACK

You are continuing the existing Mini ERP Laravel + Inertia migration.

This is a bounded **decision and planning slice only**.

Do **not** implement inventory valuation code in this slice.

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

Phase 4 Slice 5 and Slice 6 intentionally block stock-product invoice/bill posting until inventory costing and COGS/valuation rules are explicitly approved by the owner.

## Non-Negotiable Rules

Do not introduce:

- tenant context or tenant middleware
- `company_id`, `branch_id`, or `tenant_id` on new or existing operational tables
- `company_user`
- `users.company_id`
- `branch.company_id`
- `fiscal_year.company_id`
- `currentCompany` or `currentBranch`
- Spatie Teams
- company/branch dimensions in number sequences
- warehouse-to-branch relationship
- branch/location authorization

If a relationship is not explicitly supported by current owner requirements, classify it as:

`UNDEFINED - DO NOT ASSUME`

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
- corrections through reversal or correction documents, not ledger mutation

## Mission

Create an owner-ready inventory costing decision pack for the next implementation slice.

This slice must answer:

1. What stock-product behavior is currently blocked or deferred?
2. Which inventory costing models are viable for this ERP now?
3. What accounting consequences does each model create?
4. Which owner decision is required before implementation?
5. What exact bounded implementation plan should follow once the owner chooses?

## Hard Boundary

Do **not** create or modify:

- inventory valuation migrations
- stock ledger tables
- warehouse/location tables
- COGS posting code
- stock movement services
- landed cost services
- tax/VAT code
- returns, credit notes, or debit notes
- customer invoice posting logic
- supplier bill posting logic
- delivery note or goods receipt posting logic
- AR/AP allocation/payment/receipt logic
- any production data mutation beyond optional documentation updates

The expected changed files for this slice are documentation only.

## Required Inspection

Inspect the current implementation before writing the decision pack:

- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `laravel/app/Models/Product.php`
- `laravel/app/Application/Catalog/ProductService.php`
- `laravel/app/Application/Sales/SalesOrderService.php`
- `laravel/app/Application/Purchasing/PurchaseOrderService.php`
- `laravel/app/Application/Sales/DeliveryNoteService.php`
- `laravel/app/Application/Purchasing/GoodsReceiptService.php`
- `laravel/app/Application/Sales/CustomerInvoiceService.php`
- `laravel/app/Application/Purchasing/SupplierBillService.php`
- `laravel/app/Application/Accounting/AccountingAccountMappingService.php`
- `laravel/app/Application/Accounting/PostingEngine.php`
- relevant Phase 4 migrations and tests

Use `rg` for repository searches.

## Decision Options To Compare

Compare at least these options:

1. Weighted Average Cost
2. FIFO valuation layers
3. Standard Cost
4. Non-valued/manual stock tracking

For each option, explain:

- accounting impact
- operational complexity
- concurrency/idempotency implications
- migration/data-model implications
- effect on Goods Receipts
- effect on Supplier Bills
- effect on Delivery Notes
- effect on Customer Invoices
- effect on returns/credit/debit notes later
- reporting impact
- implementation risk
- suitability for a small Mini ERP

Do not treat any option as selected by default.

You may recommend an option, but label it clearly as a recommendation requiring owner approval.

## Required Output File

Create:

- `PHASE_4_INVENTORY_COSTING_DECISION.md`

The file must include:

1. **Current State**
   - Current implemented Phase 4 stock boundaries.
   - Where `stock` products are allowed today.
   - Where `stock` products are intentionally rejected today.

2. **Decision Matrix**
   - Table comparing Weighted Average, FIFO, Standard Cost, and Non-valued/manual tracking.

3. **Accounting Consequences**
   - Required GL mappings for each serious implementation option, such as:
     - inventory asset
     - goods received not invoiced / inventory clearing, if needed
     - COGS
     - inventory adjustment
     - purchase price variance, only if standard cost is chosen
   - Do not implement these mappings yet.

4. **Operational Consequences**
   - How the choice affects:
     - purchase orders
     - goods receipts
     - supplier bills
     - delivery notes
     - customer invoices
     - returns/credit/debit notes later

5. **Concurrency & Integrity Requirements**
   - Required row-locking strategy.
   - Required idempotency boundaries.
   - Required immutable inventory transaction behavior.
   - Required stress tests for the future implementation slice.

6. **Owner Decision Required**
   - Present the exact decision needed in clear language.
   - Include recommended default only as a recommendation, not as accepted truth.

7. **Next Slice Contract After Owner Choice**
   - Draft the next implementation slice title and scope.
   - Keep it bounded and explicitly exclude unrelated modules.

## Optional Documentation Updates

After creating the decision document, update these docs only if needed:

- `NEXT_TASKS.md`
- `IMPLEMENTATION_STATUS.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `CONTINUE_HERE.md`

Do not mark inventory costing as implemented.

Only mark Slice 7 as a decision/documentation pack if the decision document is complete.

## Verification

Because this slice should be documentation-only:

Run:

```powershell
git diff --stat
rg -n "company_id|branch_id|tenant_id|currentCompany|currentBranch|Spatie Teams" PHASE_4_INVENTORY_COSTING_DECISION.md
```

If you modify any PHP/TS files by mistake, revert only your own accidental changes and then run the appropriate test/typecheck command.

Do not run destructive database commands.

## Final Report Required

Report:

1. Files inspected.
2. Files changed.
3. Confirmation that no migrations were added.
4. Confirmation that no Laravel PHP/TS implementation files were changed.
5. The inventory costing options compared.
6. The exact owner decision still required.
7. Any documentation status updates made.

Do not start Phase 4 Slice 8.
