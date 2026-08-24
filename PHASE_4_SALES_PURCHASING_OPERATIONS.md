> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.

Status: FULLY IMPLEMENTED & VERIFIED THROUGH SLICE 10 (INCLUDING MANUAL AR/AP SETTLEMENT PASS)

This document is the Phase 4 planning contract for the active Laravel + Inertia Mini ERP migration.

Phase 4 must be implemented in bounded slices, like Phase 3. Do not implement all Sales, Purchasing, Inventory, and accounting integrations in one pass.

## Current Baseline

The Laravel target is verified through:

- Phase 2 Accounting Core.
- Phase 3 AR/AP + Cash/Bank/Cheques Slices 1-10.
- M10 Spatie Activitylog audit backend, scheduler, and jobs baseline.

Use these current files first:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_3_FINAL_VERIFICATION_REPORT.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`

Phase 3 prompts remain historical traceability references only.

## Non-Negotiable Rules

Do not introduce:

- tenant context or tenant middleware
- `company_user`
- `users.company_id`
- `branch.company_id`
- `fiscal_year.company_id`
- company-owned users, roles, permissions, branches, sales documents, purchase documents, inventory locations, or audit records
- Spatie Teams
- `currentCompany` or `currentBranch`
- company/branch dimensions in number sequences
- company/branch security scopes

If a relationship is not explicitly supported by current owner requirements, classify it as:

`UNDEFINED - DO NOT ASSUME`

Phase 4 must preserve:

- global single-installation ERP context
- Spatie Permission with teams disabled
- Spatie Activitylog through the existing `AuditLogger` API
- append-only audit behavior
- attachment authorization through the existing entity registry/service
- user-targeted notifications
- atomic global document numbering by key
- idempotent posting/actions
- integer minor-unit money
- no floats in money, FX, balances, allocations, or stock valuation
- immutable posted accounting records
- corrections through reversal/credit/debit documents, not mutation of posted ledgers

## Phase 4 Business Scope

Phase 4 introduces operational Sales and Purchasing workflows on top of the existing accounting and AR/AP foundation.

Target capabilities across the whole phase:

- Product/service catalog foundation.
- Unit of measure foundation.
- Sales order lifecycle.
- Purchase order lifecycle.
- Delivery note / goods issue foundation.
- Goods receipt note foundation.
- Customer invoice posting to AR and GL through the existing PostingEngine.
- Supplier bill posting to AP and GL through the existing PostingEngine.
- Returns / credit notes / supplier adjustment notes using the approved Slice 10 operating model.
- Operational reports for Sales and Purchasing after durable documents exist.
- Inertia pages/actions after backend behavior is stable.

## Must Not Be Built Yet Without Owner Decision

Do not implement these until explicitly approved:

- full VAT/tax filing or reporting workflow beyond Slice 10 manual note tax fields.
- FIFO, Standard Costing, Non-Valued Stock Tracking, or any inventory valuation method other than the owner-selected Moving Weighted Average path.
- warehouse-to-branch relationship.
- branch/location authorization.
- multi-warehouse costing assumptions.
- price lists and contract pricing complexity.
- sales quotations if the owner chooses to start directly from sales orders.
- purchase requisitions if the owner chooses to start directly from purchase orders.
- approval workflow engine.
- credit limit blocking rules.
- return/credit/debit semantics outside the approved Slice 10 model.
- automatic invoice generation from delivery/receipt.
- 2-way/3-way match enforcement rules.
- full financial statements.
- production deployment.
- browser E2E test hardening.

## Confirmed Integration Points

Use the existing implementation instead of recreating parallel systems:

- Customer master data from Phase 3.
- Supplier master data from Phase 3.
- CashAccount and BankAccount from Phase 3 where settlement is needed later.
- AR/AP subledgers from Phase 3.
- CustomerReceipt/SupplierPayment from Phase 3 for settlement. Do not duplicate receipt/payment modules.
- PostingEngine from Phase 2 for any GL posting.
- Journal/ledger immutability from Phase 2.
- NumberSequence global allocation from current foundation.
- Accounting account mappings pattern from Phase 3.
- AuditLogger backed by Spatie Activitylog.
- AttachmentService and entity registry.
- NotificationService for user-targeted events.
- existing Laravel Models + Application Services + Inertia patterns.

## Accounting Boundary

Sales and Purchasing documents are operational until explicitly posted.

Only posting services may create:

- `journal_entry`
- `journal_line`
- `ledger_entry`
- `receivable_entry`
- `payable_entry`

Customer Invoice posting creates:

- AR subledger debit.
- GL debit to AR control.
- GL credit to revenue.
- returns, credits, and bounded manual note tax effects must be handled through separate Slice 10 documents, not by mutating posted invoices.

Supplier Bill posting creates:

- AP subledger credit.
- GL credit to AP control.
- GL debit to expense or inventory/clearing.
- purchase returns, supplier adjustments, and bounded manual note tax effects must be handled through separate Slice 10 documents, not by mutating posted bills.

Stock COGS is implemented only through the owner-selected Moving Weighted Average inventory path. Do not add alternate costing branches without a new owner decision.

## Phase 4 Data Modeling Guardrails

Use singular table naming style consistent with current Laravel schema where practical.

Allowed Phase 4 concepts, subject to slice-specific prompts:

- product/service item catalog
- unit of measure
- sales order
- sales order line
- purchase order
- purchase order line
- delivery note
- delivery note line
- goods receipt
- goods receipt line
- customer invoice
- customer invoice line
- supplier bill
- supplier bill line
- sales return
- sales return line
- customer credit note
- customer credit note line
- purchase return
- purchase return line
- supplier adjustment note
- supplier adjustment note line

Do not add `company_id`, `branch_id`, or `tenant_id` to these tables unless a later explicit owner decision changes the model.

Do not infer that "warehouse" means "branch".

If a stock location/warehouse is introduced later, it is an inventory/logistics concept only unless owner requirements explicitly define otherwise.

## Phase 4 Slice Plan

### Slice 1 - Product/Service Catalog Foundation

Create the safe foundation for sellable/purchasable items without posting, stock quantity, valuation, invoices, orders, or tax.

Status: COMPLETE

Expected output:

- Product/service catalog schema, model, service, tests.
- Unit of measure schema, model, service, tests.
- Product category or classification only if kept simple and useful for catalog management.
- RBAC permissions for catalog administration.
- Attachment entity registry entries where appropriate.
- Spatie audit via `AuditLogger`.
- No accounting posting.
- No inventory valuation.
- No Sales/Purchase documents.

Execution file:

- `PHASE_4_SLICE_1_GEMINI_PROMPT.md`

### Slice 2 - Sales Order Backend

Status: COMPLETE (with exact integer totals)

Expected scope:

- sales order header/lines
- customer relation
- product relation
- minor-unit totals
- status lifecycle: draft -> confirmed -> cancelled
- no delivery
- no invoice
- no AR/GL posting
- no stock movement
- no tax unless owner approves

Original execution file:

- `PHASE_4_SLICE_2_GEMINI_PROMPT.md`

### Slice 3 - Purchase Order Backend

Create supplier-facing purchase order draft lifecycle only.

Status: COMPLETE (with exact integer totals)

Expected scope:

- purchase order header/lines
- supplier relation
- product relation
- minor-unit totals
- status lifecycle: draft -> confirmed -> cancelled
- no goods receipt
- no supplier bill
- no AP/GL posting
- no stock movement
- no tax unless owner approves

Execution file:

- `PHASE_4_SLICE_3_GEMINI_PROMPT.md`

### Slice 4 - Delivery & Goods Receipt Operational Foundation

Create delivery note and goods receipt document lifecycles.

Status: COMPLETE

Expected scope:

- delivery note from sales order
- goods receipt from purchase order
- quantity fulfillment tracking
- no COGS
- no inventory valuation
- no invoice auto-generation
- no branch/warehouse assumptions unless approved

Execution file:

- `PHASE_4_SLICE_4_GEMINI_PROMPT.md`

### Slice 5 - Customer Invoice Posting

Create customer invoice lifecycle and post approved invoices to AR and GL.

Status: COMPLETE

Expected scope:

- customer invoice header/lines
- source from sales order/delivery where available, plus manual service/non-stock invoices
- global invoice numbering `INV-YYYY-XXXXX`
- PostingEngine integration
- AR subledger integration
- `sales_revenue` accounting mapping integration
- idempotent posting
- reversal/credit-note boundary
- reject stock products until inventory costing/COGS is approved
- no stock COGS until costing is approved
- no VAT/tax unless approved

Execution file:

- `PHASE_4_SLICE_5_GEMINI_PROMPT.md`

### Slice 6 - Supplier Bill Posting

Create supplier bill lifecycle and post approved bills to AP and GL.

Status: COMPLETE

Expected scope:

- supplier bill header/lines
- source from purchase order/goods receipt where available, plus manual service/non-stock bills
- global bill numbering `BILL-YYYY-XXXXX`
- PostingEngine integration
- AP subledger integration
- `purchase_expense` accounting mapping integration
- idempotent posting
- reject stock products until inventory costing/valuation is approved
- no landed cost/inventory valuation until approved
- no VAT/tax unless approved

Execution file:

- `PHASE_4_SLICE_6_GEMINI_PROMPT.md`

### Slice 7 - Inventory Costing Decision Slice

Prepare the owner decision pack for the future inventory valuation approach. Do not implement valuation code in this slice.

Status: COMPLETE

Owner decision:

- **Selected: Moving Weighted Average Costing**
- Not selected for Slice 8: FIFO, Standard Costing, Manual/Non-Valued Stock Tracking

Do not implement unselected costing branches in Slice 8.

Execution file:

- `PHASE_4_SLICE_7_GEMINI_PROMPT.md`

Decision pack:

- `PHASE_4_INVENTORY_COSTING_DECISION.md`

Owner selected Moving Weighted Average Costing, and Slice 8 implemented that model only.

### Slice 8 - Moving Weighted Average Inventory Costing & Stock Product Posting

Implement the owner-selected Moving Weighted Average inventory valuation/tracking path.

Status: COMPLETE

This slice followed `PHASE_4_INVENTORY_COSTING_DECISION.md` and implemented only:

- Moving Weighted Average Costing

Execution file:

- `PHASE_4_SLICE_8_GEMINI_PROMPT.md`

Implemented scope:

- stock balance / stock ledger behavior
- goods receipt stock effect
- delivery note stock effect
- supplier bill stock-product behavior
- customer invoice stock-product behavior
- COGS / inventory asset / GRNI impact where applicable
- deterministic locks, idempotency, and stress tests

FIFO layers, Standard Costing, and Non-Valued alternate branches were not implemented.

### Slice 9 - Operational Reports + Returns/Credit/Debit Decision Pack

Status: COMPLETE

Execution file:

- `PHASE_4_SLICE_9_GEMINI_PROMPT.md`

Implemented read-only operational reports for existing durable documents, and created an owner decision pack for returns, credit notes, and debit notes.

This slice implemented reports for:

- sales orders
- purchase orders
- customer invoices
- supplier bills
- delivery notes
- goods receipts
- stock movements

This slice documented, but did not implement, return/credit/debit note decisions:

- relation to original invoice/bill
- inventory effect
- AR/AP effect
- revenue/expense reversal effect
- tax effect if tax exists
- posting/reversal invariants

The owner selected the safe operating model after Slice 9. Implement it in Slice 10 only.

### Slice 10 - Sales Returns, Credit Notes, Supplier Adjustments & Close-Out

Status: COMPLETE (2026-08-22)

Execution file:

- `PHASE_4_SLICE_10_GEMINI_PROMPT.md`

Decision file:

- `PHASE_4_RETURNS_CREDIT_DEBIT_DECISION.md`

Selected scope:

- physical `sales_return` documents for customer goods returned after confirmed delivery, optionally linked to posted customer invoice lines;
- `customer_credit_note` documents for revenue/AR reduction and manual/open customer credits;
- immutable corrected customer invoice revisions/print copies after posted-invoice returns, showing original quantities, returned quantities, and net remaining quantities without creating a second accounting invoice;
- physical `purchase_return` documents for supplier goods returned after confirmed goods receipt, optionally linked to posted supplier bill lines;
- normalized `supplier_adjustment_note` documents for supplier credit/debit style AP adjustments;
- original issue cost as the default sales return stock valuation, with manual inspected value allowed and variance posted explicitly;
- original receipt cost for purchase return stock valuation, with explicit variance/adjustment posting when needed;
- damaged/scrap sales return disposition without increasing saleable stock;
- manual tax rate stored in integer basis points and optional exact tax amount override;
- manual allocation only for created AR/AP credit/debit entries;
- full feature tests, PostgreSQL stress tests, docs sync, and close-out report.

Do not implement:

- full VAT/tax filing or reporting module;
- warehouse/location semantics;
- FIFO, Standard Costing, or Non-Valued costing branches;
- landed cost/freight allocation;
- automatic credit/debit note allocation;
- mutation of posted invoices, bills, journals, ledgers, AR/AP entries, or stock movement ledger rows.

## Verification Gate For Every Slice

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
php artisan accounting:phase3-integrity-check
php artisan accounting:phase3-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Add slice-specific tests and stress commands when the slice introduces concurrency-sensitive behavior.

## Required Implementation Report After Each Slice

Gemini must report:

1. Files changed.
2. Migrations added.
3. Schema diff.
4. Relationships added and their evidence.
5. Unsupported assumptions avoided.
6. Remaining `company_id`, `branch_id`, `tenant_id` occurrences introduced by the slice, if any, with explicit justification.
7. RBAC changes.
8. Audit/attachment/notification integration.
9. Test results.
10. Stress results.
11. Remaining risks.

## Phase 4 Slice 10 Implementation Record

Status: COMPLETE (verified 2026-08-22, including Manual AR/AP Settlement Pass)

Implemented the owner-selected model from `PHASE_4_RETURNS_CREDIT_DEBIT_DECISION.md` using `PHASE_4_SLICE_10_GEMINI_PROMPT.md` and closed the manual settlement gap using `PHASE_4_SLICE_10_SETTLEMENT_CORRECTION_PROMPT.md`. Seven migrations: sales return tables (2026_08_22_100000), customer credit note tables (...100010), customer invoice revision tables (...100020), purchase return tables (...100030), supplier adjustment note tables (...100040), accounting mapping update (...100050), and note settlement tables (...200000).

Implemented document families:

| Document | Nature | Numbering key / prefix | Primary posting flow |
|---|---|---|---|
| `sales_return` | physical goods return | `sales.return` / `SR-` | reversal stock movement; Dr `inventory_return_variance` (5200) when manual inspected value differs from original issue cost |
| `customer_credit_note` | financial AR/revenue reduction | `customer.credit_note` / `CN-` | Dr `sales_returns` (4200) + tax side / Cr `ar_control`; AR credit entry |
| `customer_invoice_revision` | immutable corrected print copy | revision sequence `R01`/`R02` | none - no GL effects |
| `purchase_return` | physical goods return to supplier | `purchase.return` / `PRT-` | reversal stock movement; GRNI vs post-bill path chosen per case |
| `supplier_adjustment_note` | normalized AP adjustment | `supplier.adjustment_note` / `SAN-` | AP impact through separate note: Cr `purchase_returns_allowances` (5400) + tax side / Dr `ap_control`; AP debit entry |

Key behaviors:

- Sales Return + Customer Credit Note posting flows use the existing PostingEngine (Dr `sales_returns` / Cr `ar_control`, etc.); scrap dispositions post Dr `inventory_scrap_loss` (5300) without increasing saleable stock.
- Invoice revisions are cumulative snapshots (`R01` original, `R02` after first return, showing original/returned/net quantities) and create no GL effects.
- Purchase returns choose between GRNI clearing and post-bill correction per case; where an AP impact is required after bill posting, a separate `supplier_adjustment_note` carries it instead of mutating posted bills.
- Manual tax is stored in integer basis points with modes `none`/`manual_rate`/`manual_amount`; computed exactly as `intdiv(($baseMinor * $rateBps) + 5000, 10000)` with optional exact manual amount override. Tax sides map to `input_tax_receivable` (1300) / `output_tax_payable` (2200).
- Credit/debit settlement allocation is manual/open only. Explicit `receivable_entry_settlement` and `payable_entry_settlement` actions settle note-created AR/AP entries without creating extra GL.
- New mapping keys (`sales_returns`, `inventory_return_variance`, `inventory_scrap_loss`, `purchase_returns_allowances`, `input_tax_receivable`, `output_tax_payable`) seeded idempotently in `AccountingCoreSeeder`.
- Permissions: `sales.returns`, `sales.credit_notes`, `sales.invoice_revisions`, `purchasing.returns`, `purchasing.adjustment_notes`. Attachment entities registered for all five families. Routes: `sales-returns.*`, `customer-credit-notes.*`, `invoice-revisions.*`, `purchase-returns.*`, `supplier-adjustment-notes.*`, plus `GET /sales/returns/returnable-lines/{invoiceId}`.
- Verification: full suite 407 tests / 404 passed / 3 skipped / 3172 assertions; `Phase4Slice10ReturnsCreditNotesTest` 38 tests / 38 passed / 0 skipped / 230 assertions; Concurrency suite 7 tests / 16 assertions; all accounting stress commands pass at 50 workers, including `accounting:settlement-concurrency-stress --workers=50`; Pint, typecheck, and build pass. `concurrency:stress --workers=100` remains blocked locally by Windows paging-file memory exhaustion; `--workers=10` passes.
