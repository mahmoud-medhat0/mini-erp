# MINI ERP — PHASE 4 INVENTORY COSTING DECISION PACK

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


**Document Version**: 1.1  
**Status**: OWNER DECISION CONFIRMED - OPTION 1 MOVING WEIGHTED AVERAGE  
**Date**: 2026-08-22  

---

## Executive Summary

This document serves as the formal decision and planning pack for **Phase 4 Slice 7: Inventory Costing Decision**. 

In Phase 4 Slice 5 (Customer Invoice Posting) and Slice 6 (Supplier Bill Posting), accounting GL posting was fully established for service and non-stock items. However, posting for **stock items** (`type === 'stock'`) was intentionally blocked with explicit validation guardrails:
- Invoicing stock products: `"Invoicing stock products requires inventory costing/valuation logic which is not enabled in this slice."`
- Billing stock products: `"Billing stock products requires inventory costing/valuation logic which is not enabled in this slice."`

To enable stock item invoicing, billing, and financial inventory valuation, the business owner selected **Option 1: Moving Weighted Average Costing** on 2026-08-22. This decision pack compares four viable models, records the selected model, details its accounting and operational consequences, outlines concurrency requirements, and defines the contract for the subsequent implementation slice (**Phase 4 Slice 8**).

## Owner Decision

**Selected option:** Option 1 - Moving Weighted Average Costing.

Phase 4 Slice 8 must implement only the moving weighted average model. Do not implement FIFO layers, Standard Costing, or Non-Valued / Manual Stock Tracking branches in the same slice.

---

## 1. Current State & Stock Boundaries

### 1.1 Where Stock Products Are Allowed Today
1. **Product Catalog** (`Product`):
   - Supports `type` values: `'service'`, `'non_stock'`, and `'stock'`.
2. **Sales Orders** (`SalesOrder` & `SalesOrderLine`):
   - Allows stock items on lines. Stores quantities in integer `quantity_e6` format (`1000000 = 1.000000`).
3. **Purchase Orders** (`PurchaseOrder` & `PurchaseOrderLine`):
   - Allows stock items on lines. Stores quantities in `quantity_e6` and prices in `unit_price_minor`.
4. **Delivery Notes** (`DeliveryNote` & `DeliveryNoteLine`):
   - Allows stock items. Enforces cumulative over-delivery checks (`quantity_e6`) against sales orders. Does **not** post to GL or record COGS.
5. **Goods Receipts** (`GoodsReceipt` & `GoodsReceiptLine`):
   - Allows stock items. Enforces cumulative over-receipt checks (`quantity_e6`) against purchase orders. Does **not** post to GL or adjust inventory asset accounts.

### 1.2 Where Stock Products Are Intentionally Blocked Today
1. **Customer Invoice Service** (`CustomerInvoiceService`):
   - Rejects any invoice line where `product.type === 'stock'`.
2. **Supplier Bill Service** (`SupplierBillService`):
   - Rejects any bill line where `product.type === 'stock'`.

---

## 2. Decision Matrix

The table below evaluates the four inventory valuation models for Mini ERP:

| Evaluation Dimension | Option 1: Moving Weighted Average Cost | Option 2: FIFO (First-In, First-Out) Layers | Option 3: Standard Costing | Option 4: Non-Valued Stock Tracking |
| :--- | :--- | :--- | :--- | :--- |
| **Accounting Accuracy** | High (reflects real purchase cost trends dynamically) | Very High (exact historical cost tracking) | Low-Medium (fixed target cost, ignores purchase variations) | None (no inventory asset or COGS on balance sheet) |
| **Operational Complexity** | Low-Medium (single moving average unit cost per product) | High (requires tracking distinct batch/layer queues per product) | Low (unit cost is fixed in product master) | Very Low (quantity tracking only) |
| **Data Model & Schema Impact** | Low (stores `current_stock_qty_e6` & `avg_unit_cost_minor` on Product or Stock Balance table) | High (requires `stock_fifo_layer` table with remaining qty and unit cost) | Very Low (uses `standard_cost_minor` on Product table) | None (uses existing physical document lines) |
| **Goods Receipt Effect** | Updates moving average cost: `New Avg = (Old Value + GR Value) / New Qty`. Posts Dr Inventory Asset / Cr GRNI Clearing. | Creates a new FIFO cost layer row (`remaining_qty_e6`, `unit_cost_minor`). Posts Dr Inventory Asset / Cr GRNI Clearing. | Posts Dr Inventory Asset @ Standard / Cr GRNI Clearing @ PO Cost / Dr/Cr Purchase Price Variance (PPV). | Records physical receipt quantity (`quantity_e6`). No financial posting. |
| **Supplier Bill Effect** | Posts Dr GRNI Clearing / Cr AP Control. If Bill cost differs from GR cost, adjusts Inventory Asset / Average Cost. | Posts Dr GRNI Clearing / Cr AP Control. If Bill cost differs from GR cost, adjusts specific FIFO layer cost. | Posts Dr GRNI Clearing / Cr AP Control. No inventory adjustment needed (PPV captured at GRN). | Posts Dr Purchase Expense / Cr AP Control directly on bill posting. |
| **Delivery Note Effect** | Calculates COGS = `DN Qty * Moving Avg Cost`. Posts Dr COGS / Cr Inventory Asset. | Consumes FIFO layers in order. Calculates COGS from layer costs. Posts Dr COGS / Cr Inventory Asset. | Calculates COGS = `DN Qty * Standard Cost`. Posts Dr COGS / Cr Inventory Asset. | Records physical issue quantity (`quantity_e6`). No financial posting. |
| **Customer Invoice Effect** | Allows stock lines on invoice. Financial posting: Dr AR Control / Cr Sales Revenue. | Allows stock lines on invoice. Financial posting: Dr AR Control / Cr Sales Revenue. | Allows stock lines on invoice. Financial posting: Dr AR Control / Cr Sales Revenue. | Allows stock lines on invoice. Financial posting: Dr AR Control / Cr Sales Revenue. |
| **Returns & Adjustments** | Re-calculates moving average upon stock return or manual adjustment. | Adds/consumes FIFO layers upon return/adjustment. | Uses Standard Cost for returns and posts Inventory Adjustment. | Adjusts quantity balances only. |
| **Concurrency & Row Locking** | Requires `lockForUpdate()` on product stock balance row during GR/DN posting. | Requires `lockForUpdate()` on FIFO layer rows during DN consumption. | No lock required for costing calculation (uses static standard cost). | Requires row locking on stock balance if quantity balance is tracked. |
| **Suitability for Mini ERP** | **EXCELLENT (Recommended)** | Medium (Overkill for simple ERP; higher concurrency bottleneck) | Low (Requires regular standard cost maintenance and variance accounting) | High (Simplest, but lacks balance sheet inventory asset tracking) |

---

## 3. Accounting Consequences & Required GL Mappings

If an inventory valuation model (Option 1, 2, or 3) is selected, the system will require new mapped account keys in `AccountingAccountMappingService`:

### 3.1 New GL Account Mapping Keys
1. **`inventory_asset`**:
   - Account Type: `asset`
   - Account Nature: `debit`
   - Description: Stores the balance sheet monetary value of physical stock on hand.
2. **`grni_clearing`** (Goods Received Not Invoiced):
   - Account Type: `liability` (or `clearing`)
   - Account Nature: `credit`
   - Description: Temporary liability account balancing physical receipts before supplier bill arrival.
3. **`cogs`** (Cost of Goods Sold):
   - Account Type: `expense`
   - Account Nature: `debit`
   - Description: Cost of inventory delivered to customers upon shipment/fulfillment.
4. **`inventory_adjustment`**:
   - Account Type: `expense` (or `revenue`)
   - Account Nature: `debit` / `credit`
   - Description: Balances manual inventory write-offs, stock counts, or cost adjustments.
5. **`purchase_price_variance`** (PPV - Required ONLY if Option 3 Standard Costing is selected):
   - Account Type: `expense`
   - Account Nature: `debit` / `credit`
   - Description: Captures variance between PO purchase cost and static standard cost.

### 3.2 Financial Posting Flows per Transaction

#### A. Option 1: Moving Weighted Average Costing (Recommended)
1. **Goods Receipt (GRN Confirmation)**:
   - Debit: `inventory_asset` (`GRN Qty * PO Unit Cost`)
   - Credit: `grni_clearing` (`GRN Qty * PO Unit Cost`)
   - Stock Balance Action: Increments stock quantity and updates moving average unit cost.
2. **Supplier Bill (Bill Posting)**:
   - Debit: `grni_clearing` (`Billed Qty * PO Unit Cost`)
   - Credit: `ap_control` (`Billed Qty * Bill Unit Price`)
   - *If Bill Unit Price != PO Unit Cost*: Debit/Credit `inventory_asset` (or `inventory_adjustment` if stock was already consumed) for the price difference.
3. **Delivery Note (DN Confirmation)**:
   - Debit: `cogs` (`DN Qty * Current Moving Avg Cost`)
   - Credit: `inventory_asset` (`DN Qty * Current Moving Avg Cost`)
   - Stock Balance Action: Decrements stock quantity.
4. **Customer Invoice (Invoice Posting)**:
   - Debit: `ar_control` (`Invoice Qty * Selling Price`)
   - Credit: `sales_revenue` (`Invoice Qty * Selling Price`)

#### B. Option 4: Non-Valued / Manual Stock Tracking (Alternative Option)
1. **Goods Receipt (GRN Confirmation)**:
   - No GL posting. Increments physical stock quantity (`quantity_e6`).
2. **Supplier Bill (Bill Posting)**:
   - Debit: `purchase_expense` (`Billed Qty * Bill Unit Price`)
   - Credit: `ap_control` (`Billed Qty * Bill Unit Price`)
3. **Delivery Note (DN Confirmation)**:
   - No GL posting. Decrements physical stock quantity (`quantity_e6`).
4. **Customer Invoice (Invoice Posting)**:
   - Debit: `ar_control` (`Invoice Qty * Selling Price`)
   - Credit: `sales_revenue` (`Invoice Qty * Selling Price`)

---

## 4. Operational Consequences

### 4.1 Purchase Orders & Sales Orders
- No operational changes to order entry. Orders continue using integer prices and `quantity_e6`.

### 4.2 Goods Receipts
- Under Valued Costing (Options 1–3): Goods Receipts become financial posting documents (creating `JournalEntry`: Dr `inventory_asset` / Cr `grni_clearing`). They require an open financial period at receipt date.
- Under Non-Valued Tracking (Option 4): Goods Receipts remain purely physical operational documents.

### 4.3 Supplier Bills
- Under Valued Costing (Options 1–3): Supplier Bills clear the `grni_clearing` liability account instead of posting directly to `purchase_expense`.
- Under Non-Valued Tracking (Option 4): Supplier Bills post directly to `purchase_expense` (as implemented in Slice 6).

### 4.4 Delivery Notes
- Under Valued Costing (Options 1–3): Delivery Notes become financial posting documents (creating `JournalEntry`: Dr `cogs` / Cr `inventory_asset`). They require an open financial period at shipment date.
- Under Non-Valued Tracking (Option 4): Delivery Notes remain purely physical operational documents.

### 4.5 Customer Invoices
- Under all options: Stock products can be invoiced once valuation/costing rules are established. Customer Invoices handle AR and Sales Revenue (Dr `ar_control` / Cr `sales_revenue`).

---

## 5. Concurrency & Integrity Requirements

Any future inventory costing implementation (Slice 8) must strictly adhere to the following architecture rules:

1. **Pessimistic Row Locking (`lockForUpdate()`)**:
   - Stock balance rows (`product_stock_balance`) or FIFO layer rows must be queried with `lockForUpdate()` inside database transactions before calculating new average costs or consuming layers.
2. **Strict Integer Arithmetic**:
   - All quantities must use `quantity_e6` (`bigInteger`, 6 decimal places: `1000000 = 1.000000`).
   - All monetary values, unit costs, and inventory balances must use minor units (`bigInteger`).
   - Integer division via `intdiv($qtyE6 * $unitCostMinor, 1000000)` with zero-remainder check (`% 1000000 === 0`).
   - **Zero floats**, **zero `round()`**, **zero float division** in PHP backend logic.
3. **Immutable Append-Only Stock Movement Ledger**:
   - Stock movements must be recorded in an immutable ledger table (`stock_movement_ledger` or `stock_ledger_entry`).
   - Stock entries are append-only. Corrections must occur via reversing movement entries.
4. **Idempotency**:
   - Double-confirming a Goods Receipt or Delivery Note must return the existing recorded transaction without duplicating stock movements or journal entries.
5. **Concurrency Stress Testing**:
   - Future implementation must pass worker stress tests (`workers=50`) proving zero stock race conditions, zero negative stock glitches under concurrent shipments, and zero unhandled deadlock crashes.

---

## 6. Owner Decision

The project lead/business owner explicitly chose **Option 1: Moving Weighted Average Costing** before Phase 4 Slice 8 implementation.

> [!IMPORTANT]
> **OWNER DECISION CONFIRMED**:
> 
> - **[RECOMMENDED] Option 1: Moving Weighted Average Costing**
>   - *Summary*: Automatically updates moving average product cost on Goods Receipt. Delivery Notes post COGS using the current moving average cost. Supplier Bills clear GRNI clearing.
>   - *Pros*: Ideal for Mini ERP; accurate inventory asset valuation on Balance Sheet without FIFO layer complexity.
> 
> - **Option 2: FIFO (First-In, First-Out) Valuation Layers**
>   - *Summary*: Tracks individual purchase batches/layers. Delivery Notes consume oldest available layers.
>   - *Pros*: Precise batch-level historical costing.
>   - *Cons*: High database complexity and concurrency locking overhead.
> 
> - **Option 3: Standard Costing**
>   - *Summary*: Product costs are manually fixed in catalog. Purchase price variances (PPV) are posted to variance accounts.
>   - *Pros*: Simple delivery costing.
>   - *Cons*: Requires ongoing manual cost updates and variance management.
> 
> - **Option 4: Non-Valued / Manual Stock Tracking**
>   - *Summary*: Tracks physical stock quantities only. No GL postings for inventory assets, GRNI, or COGS. Supplier Bills post directly to Purchase Expense.
>   - *Pros*: Zero accounting complexity; unlocks stock billing/invoicing immediately without GL inventory tracking.
>   - *Cons*: Inventory value is not reflected on the Balance Sheet.

*Note: Option 1 is now the accepted implementation direction for Phase 4 Slice 8.*

---

## 7. Implemented Slice Contract (Phase 4 Slice 8)

After owner decision, **Phase 4 Slice 8: Moving Weighted Average Inventory Costing & Stock Product Posting** was executed only for the selected model.

The details below remain the bounded contract for the implemented Moving Weighted Average Costing path.

### Slice 8 Bounded Scope
1. **Common Scope For All Options**:
   - Define stock balance and/or movement history needed by the selected option.
   - Keep stock movements append-only.
   - Preserve `quantity_e6` integer quantity math and minor-unit money math.
   - Add deterministic row locks around stock availability/cost calculations.
   - Unlock stock-product invoice/bill behavior only according to the selected model.

2. **Selected Schema Direction**:
   - Moving Weighted Average: implement `stock_balance` with `quantity_e6`, `valuation_amount_minor`, derived/stored average-cost fields where useful, and `lock_version`.
   - Append all stock movements to an immutable `stock_movement_ledger`.
   - Do not create FIFO layer tables.
   - Do not add Standard Cost fields.
   - Do not implement Non-Valued-only logic.

3. **Selected Account Mappings**:
   - Register `inventory_asset`, `grni_clearing`, and `cogs`.
   - Do not add `purchase_price_variance` in Slice 8.
   - Keep `purchase_expense` for service and non-stock supplier bill lines.

4. **Services**:
   - Implement a Moving Weighted Average stock costing service.
   - Update Goods Receipt, Delivery Note, Supplier Bill, and Customer Invoice behavior only as required by the selected model.
   - Do not implement unselected costing branches in the same slice.

5. **Testing & Verification**:
   - Feature tests covering the selected stock movement/costing behavior.
   - Source scans proving no floats, `round()`, or float division in inventory costing logic.
   - Concurrency stress testing with 50 parallel workers for availability, duplicate posting, and costing race prevention.

---

## 8. Document Inspection & Verification Log

### Files Inspected
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

### Verification Confirmation
- **Migrations Added**: `0` (Documentation slice only)
- **PHP/TS Source Files Modified**: `0` (Documentation slice only)
- **Database Mutations**: `0`
