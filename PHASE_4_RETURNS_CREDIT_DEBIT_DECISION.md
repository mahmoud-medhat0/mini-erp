# PHASE 4: RETURNS, CREDIT NOTES & DEBIT NOTES DECISION PACK

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


**Document Status:** IMPLEMENTATION MODEL SELECTED  
**Created:** 2026-08-22  
**Target Architecture:** Laravel + Inertia.js Single-Installation Mini ERP  

---

## Executive Summary

Phase 4 Slices 1 through 8 established durable operational and financial pipelines for Sales Orders, Purchase Orders, Delivery Notes, Goods Receipts, Customer Invoices, Supplier Bills, and Moving Weighted Average Inventory Costing.

The owner asked to use the safest model that covers the practical scenarios without weakening accounting or stock integrity.

This document records the selected operating model for Phase 4 Slice 10. **No database migrations or code logic for returns, credit notes, or debit notes were added in Phase 4 Slice 9.**

---

## 0. Selected Safe Operating Model

Use a two-layer model:

1. **Physical return documents**
   - `sales_return`: goods received back from customer.
   - `purchase_return`: goods returned to supplier.
   - These handle stock quantity, stock valuation, COGS/inventory/GRNI/AP impact depending on source state.

2. **Financial adjustment documents**
   - `customer_credit_note`: reduces customer receivable and reverses revenue/tax.
   - `supplier_adjustment_note`: normalized supplier adjustment document. UI may label it "Supplier Credit Note" or "Supplier Debit Note" depending on business wording, but the internal model must be one consistent document.
   - These handle AR/AP subledger entries and manual allocation later.

This split is safer than forcing all scenarios into one document because:

- pre-invoice/pre-bill physical returns can happen without inventing AR/AP financial impact;
- posted invoice/bill corrections remain explicit and auditable;
- the customer can receive a corrected invoice copy that shows reduced quantities without mutating or replacing the original posted invoice;
- stock ledger remains immutable;
- posted GL is corrected through new documents only;
- manual allocation remains under accountant control.

### Selected Rules

| Area | Selected Rule |
|---|---|
| Sales return source | Case-based: a return can reference a confirmed `delivery_note_line`; if already invoiced, it can also link to the posted `customer_invoice_line`. |
| Customer credit note source | Prefer posted `customer_invoice`; may link to a `sales_return` when the credit is caused by a physical return. |
| Customer corrected invoice copy | Required for posted-invoice returns: generate an immutable invoice revision/snapshot that shows original quantities, returned quantities, and net remaining quantities. This is a printable/customer-facing version, not a second accounting invoice. |
| Sales returned stock valuation | Support both original issue cost and manual inspected value. Original issue cost is the default. |
| Damaged returns | Supported through line disposition. Damaged/scrap lines do not increase saleable stock. |
| Customer credit allocation | Manual settlement. Create open credit receivable entries, then allow explicit user allocation against the original invoice or another open receivable. Do not silently auto-allocate. |
| Revenue reversal | Use a dedicated contra-revenue mapping `sales_returns` for clearer reporting. |
| Purchase return source | Case-based: a return can reference a confirmed `goods_receipt_line`; if already billed, it can also link to the posted `supplier_bill_line`. |
| Purchase return stock valuation | Original receipt cost is the selected rule. If exact inventory valuation cannot be kept safe, post any required difference to an explicit inventory return variance/adjustment mapping instead of hiding it. |
| Supplier financial document terminology | Use one normalized `supplier_adjustment_note` internally. UI can display "Supplier Credit Note" or "Supplier Debit Note" as labels. |
| Supplier adjustment allocation | Manual. Create open payable debit/credit entries; do not auto-allocate. |
| Tax/VAT | Manual percentage per document/line using integer basis points. This is not a full tax module and must not imply tax filing/reporting. |

---

## 1. Sales Returns & Customer Credit Notes

### 1.1 Sales Returns Operational & Valuation Choices

#### Question 1.1.1: Sourcing Boundary
- **Selected:** Case-based sourcing.
- Physical stock returns reference confirmed `delivery_note_line`.
- If the sale is already invoiced, the return can also link to the posted `customer_invoice_line`.
- A pre-invoice sales return must not create AR/revenue impact until a financial credit note exists.

#### Question 1.1.2: Stock Return Valuation Rule
- **Selected default:** Original issue cost from `stock_movement_ledger`.
- **Selected alternate:** Manual inspected value when the user chooses it.
- Any difference between original cost and manual restock value must be posted to an explicit inventory return variance/adjustment account.

#### Question 1.1.3: Partial Returns & Damaged Goods
- **Selected:** Partial returns are allowed but must be cumulatively checked against original source line quantity.
- **Selected:** Damaged/non-resellable returns are allowed with explicit line disposition.
- Damaged/scrap returns must bypass saleable `stock_balance` re-entry and post to an explicit scrap/loss account.

### 1.2 Sales Return & Credit Note Accounting Flows

#### Standard Stock Return with Financial Credit Note
Original invoice revenue remains recorded by the posted invoice. The return is recorded separately.

1. **Inventory Asset Re-entry**:
   - Dr `inventory_asset`
   - Cr `cogs`
2. **Revenue & AR Adjustment**:
   - Dr `sales_returns`
   - Dr `output_tax_payable` if manual tax reversal applies
   - Cr `ar_control`
3. **Subledger**:
   - Credit entry added to `receivable_entry` for the customer.
4. **Settlement**:
   - Manual allocation can settle the credit entry against the original invoice debit entry.
   - Allocation does not create GL because AR control was already reduced by the credit note posting.

#### Service / Financial Credit Note (No Stock Movement)
1. **Revenue Adjustment**:
   - Dr `sales_returns`
   - Dr `output_tax_payable` if manual tax reversal applies
   - Cr `ar_control`
2. **Subledger**:
   - Credit entry added to `receivable_entry`.
3. **Settlement**:
   - Manual allocation can settle the credit against an open customer invoice/debit.

### 1.2.1 Revenue, Return, And Settlement Reporting

The accounting model must support:

- gross revenue from posted customer invoices;
- returns/credits from posted customer credit notes using `sales_returns`;
- net revenue/sales as gross revenue minus sales returns;
- AR balance from `receivable_entry` debits, credits, and allocations;
- original invoices remaining immutable while corrected invoice revisions show customer-facing net quantities and net totals.

### 1.3 Posted Invoice Return Workflow & Corrected Invoice Copy

When the customer returns goods from an already posted invoice, the selected workflow is:

1. User opens the posted customer invoice and chooses **Create Return From Invoice**.
2. The UI loads invoice lines with:
   - original invoiced quantity;
   - previously returned/credited quantity;
   - maximum returnable quantity;
   - unit price, currency, product, UOM, and linked delivery note line where available.
3. User selects the returned items and returned quantities.
4. User selects line disposition:
   - `restock_original_cost`;
   - `restock_manual_value`;
   - `scrap_no_restock`.
5. System creates a `sales_return` linked to the invoice lines and delivery note lines.
6. System creates/posts the related `customer_credit_note` for the financial reduction.
7. System generates an immutable corrected invoice copy / invoice revision:
   - original invoice number remains unchanged;
   - revision number increments per invoice, for example `INV-2026-00001-R01`;
   - the corrected copy shows original quantity, returned quantity, and net quantity;
   - totals show original amount, credited amount, and net amount;
   - the copy references the Sales Return and Credit Note numbers;
   - it is printable/customer-facing only and must not create duplicate GL, AR, or stock postings.

The original posted invoice must remain immutable. The corrected invoice copy is a presentation/audit artifact built from the original invoice plus posted returns/credit notes.

---

## 2. Purchase Returns & Supplier Credit / Debit Notes

### 2.1 Purchase Returns Operational & Valuation Choices

#### Question 2.1.1: Sourcing Boundary
- **Selected:** Case-based sourcing.
- Physical purchase returns reference confirmed `goods_receipt_line`.
- If the purchase is already billed, the return can also link to the posted `supplier_bill_line`.
- A pre-bill purchase return must clear GRNI, not AP.

#### Question 2.1.2: Stock Return Valuation Rule
- **Selected:** Original receipt cost.
- If exact original-cost removal would create an unsafe inventory valuation state, the implementation must use an explicit variance/adjustment posting rather than silently corrupting stock value.

### 2.2 Terminology & Document Normalization

- **Selected:** One normalized internal document: `supplier_adjustment_note`.
- The UI may display "Supplier Credit Note" or "Supplier Debit Note" depending on the selected direction/context.
- For supplier returns after billing, the primary financial effect is reducing AP.

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

- **Selected:** Manual tax percentage support for Slice 10.
- Store manual tax rate as integer basis points, e.g. `1400` = 14.00%.
- Compute tax using integer minor-unit math only; no floats and no PHP `round()`.
- Also allow explicit manual tax amount override where the business document requires exact value.
- This is a bounded document-level/manual tax baseline only. It is **not** a full VAT module, filing module, or tax reporting engine.

---

## 4. Final Decision Summary For Slice 10

| Area | Decision Item | Options | Status |
| :--- | :--- | :--- | :--- |
| **Sales Returns** | Sourcing Document | Confirmed Delivery Note, optionally linked to posted Invoice | **SELECTED** |
| **Sales Returns** | Stock Valuation | Original Issue Cost by default; Manual Inspected Value allowed | **SELECTED** |
| **Sales Returns** | Damaged Goods | Supported with scrap/damaged disposition | **SELECTED** |
| **Customer Credit Notes** | Source | Posted Invoice preferred; can link to Sales Return | **SELECTED** |
| **Customer Invoice Revision** | Corrected customer copy | Immutable version showing original, returned, and net quantities after posted returns/credits | **SELECTED** |
| **Customer Credit Notes** | Allocation | Manual allocation only | **SELECTED** |
| **Customer Credit Notes** | Revenue Account | Dedicated `sales_returns` contra-revenue mapping | **SELECTED** |
| **Purchase Returns** | Sourcing Document | Confirmed Goods Receipt, optionally linked to posted Supplier Bill | **SELECTED** |
| **Purchase Returns** | Stock Valuation | Original Receipt Cost, with explicit variance/adjustment if required | **SELECTED** |
| **Supplier Adjustments** | Terminology | Internal `supplier_adjustment_note`, UI aliases allowed | **SELECTED** |
| **Supplier Adjustments** | Allocation | Manual allocation only | **SELECTED** |
| **Tax/VAT** | Entry Method | Manual percentage in basis points plus optional exact amount override | **SELECTED** |

---

## 5. Recommended Next Slice (Phase 4 Slice 10 Execution Plan)

Using the selected model above, **Phase 4 Slice 10** should implement:

1. **Sales Returns + Customer Credit Notes**:
   - Implement physical `sales_return` documents for goods received back.
   - Implement `customer_credit_note` and `customer_credit_note_line` tables/models.
   - Support invoice-linked financial credits, service price corrections, stock returns, damaged returns, and manual inspected stock value.
   - Add posted-invoice return flow where the user selects returned invoice lines/quantities and receives an immutable corrected invoice revision/print copy showing net remaining quantities.
   - Perform stock return accounting without mutating original Delivery Note, Customer Invoice, Journal Entry, Ledger Entry, or Stock Movement rows.
2. **Purchase Returns + Supplier Adjustment Notes**:
   - Implement physical `purchase_return` documents for goods returned to supplier.
   - Implement `supplier_adjustment_note` and `supplier_adjustment_note_line`.
   - Support pre-bill returns clearing GRNI and post-bill adjustments reducing AP.
   - Use original receipt cost and explicit variance/adjustment postings where necessary.
3. **Subledger Allocation Engine Integration**:
   - Create AR/AP credit/debit entries as open items.
   - Do not auto-allocate. Manual allocation remains a separate accountant action.
4. **Operational Close-Out & Concurrency Hardening**:
   - Comprehensive feature test suite & multi-worker stress test suite verifying zero invariant violations.
