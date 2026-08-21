# PHASE 4 - SALES & PURCHASING OPERATIONS

Status: IN PROGRESS - Slice 1 complete, Slice 2 prompt-ready

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
- Returns / credit notes / debit notes after owner decision.
- Operational reports for Sales and Purchasing after durable documents exist.
- Inertia pages/actions after backend behavior is stable.

## Must Not Be Built Yet Without Owner Decision

Do not implement these until explicitly approved:

- VAT/tax workflow.
- Inventory valuation method.
- COGS posting for stock items.
- warehouse-to-branch relationship.
- branch/location authorization.
- multi-warehouse costing assumptions.
- price lists and contract pricing complexity.
- sales quotations if the owner chooses to start directly from sales orders.
- purchase requisitions if the owner chooses to start directly from purchase orders.
- approval workflow engine.
- credit limit blocking rules.
- post-invoice return semantics.
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

Customer Invoice posting must eventually create:

- AR subledger debit.
- GL debit to AR control.
- GL credit to revenue.
- optional discount/return/tax effects only after owner-approved rules.

Supplier Bill posting must eventually create:

- AP subledger credit.
- GL credit to AP control.
- GL debit to expense or inventory/clearing.
- optional tax/landed-cost/inventory valuation effects only after owner-approved rules.

Stock COGS must not be posted until inventory costing method is approved and implemented.

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

Create customer-facing sales order draft lifecycle only.

Status: PROMPT READY

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

Execution file:

- `PHASE_4_SLICE_2_GEMINI_PROMPT.md`

### Slice 3 - Purchase Order Backend

Create supplier-facing purchase order draft lifecycle only.

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

### Slice 4 - Delivery & Goods Receipt Operational Foundation

Create delivery note and goods receipt document lifecycles.

Expected scope:

- delivery note from sales order
- goods receipt from purchase order
- quantity fulfillment tracking
- no COGS
- no inventory valuation
- no invoice auto-generation
- no branch/warehouse assumptions unless approved

### Slice 5 - Customer Invoice Posting

Create customer invoice lifecycle and post approved invoices to AR and GL.

Expected scope:

- customer invoice header/lines
- source from sales order/delivery where available, but also allow manual service invoice if approved by existing UX rules
- global invoice numbering `INV-YYYY-XXXXX`
- PostingEngine integration
- AR subledger integration
- idempotent posting
- reversal/credit-note boundary
- no stock COGS until costing is approved
- no VAT/tax unless approved

### Slice 6 - Supplier Bill Posting

Create supplier bill lifecycle and post approved bills to AP and GL.

Expected scope:

- supplier bill header/lines
- source from purchase order/goods receipt where available, but also allow manual expense bill if approved by existing UX rules
- global purchase numbering, for example `PUR-YYYY-XXXXX` or approved key
- PostingEngine integration
- AP subledger integration
- idempotent posting
- no landed cost/inventory valuation until approved
- no VAT/tax unless approved

### Slice 7 - Inventory Costing Decision Slice

Only after owner approval, implement the chosen inventory valuation approach.

Owner must decide:

- weighted average
- FIFO
- standard cost
- manual/non-valued stock tracking

No default assumption is allowed.

### Slice 8 - Returns, Credit Notes, Debit Notes

Only after owner approval, implement sales returns, purchase returns, credit notes, and debit notes.

This slice must define:

- relation to original invoice/bill
- inventory effect
- AR/AP effect
- revenue/expense reversal effect
- tax effect if tax exists
- posting/reversal invariants

### Slice 9 - Inertia Pages & UX Actions

Build polished Arabic/English Inertia pages for Phase 4 workflows that already have stable backend behavior.

Expected scope:

- product catalog
- UOM/category management
- sales orders
- purchase orders
- delivery/goods receipt
- invoices/bills
- permission-aware actions
- empty states
- validation feedback
- no marketing landing pages

### Slice 10 - Reports, Stress, Docs, Final Verification

Close Phase 4 with operational reports, stress/integrity commands, documentation synchronization, and final verification.

Expected scope:

- sales order report
- purchase order report
- invoice/bill report
- delivery/goods receipt report
- AR/AP reconciliation checks extended to invoices/bills
- PostgreSQL stress tests for sequence allocation, posting, and lifecycle races
- full docs/status close-out

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
