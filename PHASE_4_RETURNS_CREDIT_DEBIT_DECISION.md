# PHASE 4: RETURNS, CREDIT NOTES & DEBIT NOTES DECISION PACK

**Document Status:** OWNER REVIEW REQUIRED  
**Created:** 2026-08-22  
**Target Architecture:** Laravel + Inertia.js Single-Installation Mini ERP  

---

## Executive Summary

Phase 4 Slices 1 through 8 established durable operational and financial pipelines for Sales Orders, Purchase Orders, Delivery Notes, Goods Receipts, Customer Invoices, Supplier Bills, and Moving Weighted Average Inventory Costing.

Before implementing any return workflows, credit notes, or debit notes in Phase 4 Slice 10, explicit owner decisions are required regarding document sourcing, stock valuation on return, subledger allocation rules, and terminology normalization.

This document outlines the operational choices, accounting consequences, and recommended execution plan. **No database migrations or code logic for returns, credit notes, or debit notes have been added in Phase 4 Slice 9.**

---

## 1. Sales Returns & Customer Credit Notes

### 1.1 Sales Returns Operational & Valuation Choices

#### Question 1.1.1: Sourcing Boundary
- **Option A (Strict Invoice Sourcing):** A Sales Return must reference a posted `customer_invoice` and its corresponding `delivery_note` line.
- **Option B (Fulfillment Sourcing):** A Sales Return can be created directly from a confirmed `delivery_note` before invoicing.
- **Decision Required:** Does the owner require pre-invoice physical returns, or should returns be restricted to posted invoices?

#### Question 1.1.2: Stock Return Valuation Rule
- **Option A (Original Issue Cost):** Returned stock is valued at the exact historical unit cost recorded in `stock_movement_ledger` when shipped.
- **Option B (Current Moving Average Cost):** Returned stock is re-valued at the current `avg_unit_cost_e6` of the stock balance at the time of return.
- **Option C (Inspected / Custom Scrap Cost):** Damaged or non-resellable returns are recorded at 0 cost or a custom scrap valuation.
- **Decision Required:** Which valuation rule should govern stock re-entry?

#### Question 1.1.3: Partial Returns & Damaged Goods
- Should partial returns be tracked cumulatively against original source lines (preventing over-return)?
- Should non-resellable/damaged returns bypass `stock_balance` re-entry and post directly to a scrap/loss account?

### 1.2 Sales Return & Credit Note Accounting Flows

#### Standard Stock Return with Financial Credit Note
1. **Inventory Asset Re-entry**:
   - Dr `inventory_asset`
   - Cr `cogs`
2. **Revenue & AR Adjustment**:
   - Dr `sales_revenue` (or contra-revenue `sales_returns`)
   - Cr `ar_control`
3. **Subledger**:
   - Credit entry added to `receivable_entry` for the customer.

#### Service / Financial Credit Note (No Stock Movement)
1. **Revenue Adjustment**:
   - Dr `sales_revenue`
   - Cr `ar_control`
2. **Subledger**:
   - Credit entry added to `receivable_entry`.

---

## 2. Purchase Returns & Supplier Credit / Debit Notes

### 2.1 Purchase Returns Operational & Valuation Choices

#### Question 2.1.1: Sourcing Boundary
- **Option A (Bill Sourcing):** A Purchase Return must reference a posted `supplier_bill` and its corresponding `goods_receipt` line.
- **Option B (Receipt Sourcing):** A Purchase Return can be created directly from a confirmed `goods_receipt` before billing.
- **Decision Required:** Can goods be returned to a supplier prior to receiving the supplier bill?

#### Question 2.1.2: Stock Return Valuation Rule
- **Option A (Current Moving Average Cost):** Stock leaving inventory reduces `stock_balance` at the current `avg_unit_cost_e6`.
- **Option B (Original Purchase Cost):** Stock leaving inventory reduces `stock_balance` at the original GRN purchase cost, with any variance posted to a Purchase Price Variance / Stock Adjustment account.
- **Decision Required:** Which valuation rule should govern supplier returns?

### 2.2 Terminology & Document Normalization

- **Supplier Credit Note vs. Supplier Debit Note:**
  - Some ERP systems model a supplier return as a "Supplier Credit Note" (issued by supplier), while others model it as a "Debit Note" (issued by buyer to debit the supplier's account).
  - **Decision Required:** Should the ERP implement a single normalized document type (e.g., `supplier_credit_note`) or support both user-facing terms?

### 2.3 Purchase Return & Supplier Adjustment Accounting Flows

#### Standard Stock Return to Supplier (Post-Bill)
1. **Inventory Asset Reduction**:
   - Dr `grni_clearing` (or `ap_control`)
   - Cr `inventory_asset`
2. **AP Adjustment**:
   - Dr `ap_control`
   - Cr `grni_clearing`
3. **Subledger**:
   - Debit entry added to `payable_entry` for the supplier.

---

## 3. Tax / VAT Status & Rules

- **Current Status:** Tax / VAT logic is **not implemented** in the current Mini ERP baseline.
- **Constraint:** Returns, Credit Notes, and Debit Notes must **not** invent or assume tax calculation fields or automatic tax postings.
- **Future Integration:** When a formal Tax / VAT module is approved by the owner, credit/debit note posting engines will be updated to reverse output/input tax accordingly.

---

## 4. Summary of Owner Decisions Required Before Slice 10 Implementation

| Area | Decision Item | Options | Status |
| :--- | :--- | :--- | :--- |
| **Sales Returns** | Sourcing Document | Invoice-only vs. Pre-invoice Delivery Note | **PENDING OWNER SELECTION** |
| **Sales Returns** | Stock Valuation | Original Issue Cost vs. Current Moving Average Cost | **PENDING OWNER SELECTION** |
| **Customer Credit Notes** | Contra-Revenue Account | Direct `sales_revenue` vs. Dedicated `sales_returns` | **PENDING OWNER SELECTION** |
| **Purchase Returns** | Sourcing Document | Bill-only vs. Pre-bill Goods Receipt | **PENDING OWNER SELECTION** |
| **Purchase Returns** | Stock Valuation | Current Moving Average vs. Original Purchase Cost | **PENDING OWNER SELECTION** |
| **Supplier Adjustments**| Terminology | `supplier_credit_note` vs. `supplier_debit_note` | **PENDING OWNER SELECTION** |

---

## 5. Recommended Next Slice (Phase 4 Slice 10 Execution Plan)

Upon owner selection of the choices above, **Phase 4 Slice 10** is proposed as follows:

1. **Customer Credit Notes & Sales Returns**:
   - Implement `customer_credit_note` and `customer_credit_note_line` tables/models.
   - Implement `CustomerCreditNoteService` supporting invoice-linked returns and service price adjustments.
   - Perform stock issue reversal (Dr `inventory_asset` / Cr `cogs`) and AR credit posting (Dr `sales_revenue` / Cr `ar_control`).
2. **Supplier Credit/Debit Notes & Purchase Returns**:
   - Implement `supplier_credit_note` and `supplier_credit_note_line` tables/models.
   - Implement `SupplierCreditNoteService` supporting bill-linked returns and price adjustments.
   - Perform stock receipt reversal (Dr `ap_control` / Cr `inventory_asset`).
3. **Subledger Allocation Engine Integration**:
   - Allow customer credit notes to be allocated against open `receivable_entry` invoices via `ReceivableAllocationService`.
   - Allow supplier credit notes to be allocated against open `payable_entry` bills via `PayableAllocationService`.
4. **Operational Close-Out & Concurrency Hardening**:
   - Comprehensive feature test suite & multi-worker stress test suite verifying zero invariant violations.
