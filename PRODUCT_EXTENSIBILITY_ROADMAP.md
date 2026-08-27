# PRODUCT EXTENSIBILITY ROADMAP

> **Owner Direction Recorded 2026-08-24:** The product must support multiple operational branches, branch transfers, and flexible future operational workflows. This is an ERP operations requirement, not a multi-tenant SaaS requirement.

## Purpose

This document is the product extensibility source of truth for future phases after Phase 9.

It exists to prevent two mistakes:

- freezing the Mini ERP into a single-branch-only product
- reintroducing unsupported tenant/company ownership assumptions while adding branch capability

The active application remains Laravel + Inertia + React + PostgreSQL in a single ERP installation context.

## Non-Tenant Branch Capability Rule

Branch support is approved as an operational and reporting capability.

Branch support does not mean:

- Branch is a tenant
- Branch is a security boundary by default
- Branch owns users, roles, permissions, fiscal years, currencies, number sequences, audit records, attachments, or notifications
- login establishes `currentBranch`
- every business table automatically receives `branch_id`
- document numbering becomes branch-scoped by default

Branch references are allowed only when a bounded future slice proves the module-specific operational need, adds schema intentionally, updates services/controllers/UI, and verifies tests.

## Product Must Remain Capable Of

The ERP must be designed so these scenarios can be implemented without redesigning the accounting core:

- multiple branches in one ERP installation
- one branch with one or more warehouses
- one warehouse serving one or more branches, if the owner later chooses that model
- branch-to-branch stock transfer
- warehouse-to-warehouse stock transfer
- transfer in transit, partial receipt, cancellation, reversal, and discrepancy handling
- sales from a selected branch and fulfillment warehouse
- customer returns into the original branch/warehouse or a different return branch/warehouse
- purchase receipt into a selected branch/warehouse
- purchase returns out of a selected branch/warehouse
- branch cash desk and bank account assignment
- cash/bank transfer between branches or accounts
- fixed asset location, branch assignment, custody movement, and transfer history
- branch-level operational reports
- branch-level ledger-backed profitability reports using explicit GL branch dimensions
- optional branch filters across sales, purchasing, inventory, cash/bank, fixed assets, and reports
- optional branch-aware approval rules for workflows that explicitly define branch context

## Operational Contexts To Support Later

| Context | Purpose | Current Status | Future Rule |
|---|---|---|---|
| Branch | business unit, showroom, office, or operating point | standalone reference exists | may be linked to documents and balances by explicit slice |
| Warehouse | physical stock holding area | implemented 2026-08-24 | may optionally reference a branch for operations/reporting |
| Stock Location | bin/shelf/zone inside warehouse | implemented 2026-08-24 | optional operational subdivision of a warehouse |
| Cash Desk | operational cash holding point | cash account exists | may optionally link to branch |
| Bank Account | bank/cashbook reporting | bank account exists | may optionally link to branch |
| Fixed Asset Location | asset physical place | implemented 2026-08-25 | optional operational branch/location movement reference |
| Custodian | employee/person responsible for asset | not implemented | optional; do not infer User = Employee |
| Project | reporting dimension | not implemented | optional future operational dimension |
| Department | reporting dimension | not implemented | optional future operational dimension |
| Cost Center | reporting dimension | not implemented | optional future operational dimension |

## Branch Transfer Accounting Principles

The ERP is single legal/business installation unless later changed.

Therefore branch transfers are not sales by default.

Default safe rules:

- stock transfers move inventory quantity and carrying cost, not revenue
- no output VAT or input VAT is created by an internal branch transfer
- if source and destination use the same inventory GL account, the stock subledger movement may be enough
- if branch-level inventory GL reporting is later required, use explicit inter-branch clearing mappings and balanced PostingEngine journals
- posted stock movements and accounting entries must remain immutable
- corrections must use reversal or adjustment documents, not updates to posted rows

## Customer Return Capability

The owner has approved a flexible return scenario:

- user selects an existing customer invoice
- user selects returned lines and returned quantities
- system creates a new immutable invoice revision or related credit note record
- returned quantities reduce open invoice/business exposure according to the approved settlement workflow
- revenue reversal, VAT reversal, AR settlement, stock return, and cost reversal are all accounted for
- default return cost uses the original outbound cost
- authorized override can allow manually specified return cost or percentage adjustment, with reason, permission, and Spatie Activitylog audit
- returned stock disposition must support saleable, quarantine, repair, scrap, and supplier-return paths

## Future Phase 10 Track

Phase 10 should focus on branch, warehouse, and operational dimension extensibility.

Recommended slices:

| Slice | Name | Purpose |
|---|---|---|
| 1 | Branch and Warehouse Operating Model Decision Pack | choose branch/warehouse relationship, required contexts, and transfer accounting policy |
| 2 | Warehouse and Stock Location Foundation | add warehouses/locations without tenant semantics |
| 3 | Branch-Capable Stock Balance Refactor | extend stock balances and movement ledger with approved operational dimensions |
| 4 | Stock Transfer Workflow | transfer request, issue, in-transit, receipt, cancellation, reversal, discrepancy |
| 5 | Stock Adjustment and Stock Count | controlled adjustments, physical count variance posting, audit |
| 6 | Branch-Aware Sales and Purchasing UX | branch/warehouse selectors, defaults, filters, permissions |
| 7 | Branch Cash/Bank Transfer | optional branch-linked cash desks and bank movements |
| 8 | Fixed Asset Branch/Location Transfer | asset location and custody movement history |
| 9 | Branch Operational Reports | branch stock, cash/bank, fixed asset, movement readiness, and future profitability preparation |
| 10 | Close-Out and Stress Verification | full test, concurrency, UI/UX, dictionary, source-scan, and reporting gate |

## Implemented Phase 10 Foundation

Implemented on 2026-08-24:

- warehouse master data with optional operational branch reference
- stock locations
- warehouse-aware stock balances
- warehouse-aware stock movement ledger entries
- stock transfer workflow with issue, partial receipt, full receipt, and cancellation rules
- transfer cost preservation using moving weighted average carrying cost
- controlled stock count workflow with variance posting
- controlled stock adjustment workflow with inventory gain/loss accounting
- warehouse selectors on Delivery Notes, Goods Receipts, Sales Returns, and Purchase Returns
- stock balances and stock movement reports with warehouse filters
- Delivery Note and Goods Receipt reports with warehouse filters
- warehouse and stock transfer Inertia pages
- stock count and stock adjustment Inertia pages
- inventory transfer/receive permissions
- stock transfer concurrency stress command

Implemented on 2026-08-25:

- optional branch assignment for cash accounts and bank accounts
- internal treasury transfer workflow between cash and bank accounts
- fixed asset operational locations
- current fixed asset branch/location position
- append-only fixed asset movement history
- fixed asset movement route permission `fixedAssets.transfer`
- read-only branch operations report for warehouses, stock, cash/bank, fixed assets, asset movements, and posted treasury transfers
- branch readiness warnings for unassigned operational records and mixed currency scope
- optional GL branch dimension on journal entries, journal lines, and immutable ledger entries
- branch-filtered General Ledger review
- ledger-backed Branch Profitability report with unassigned P&L review rows
- Branch Profitability protected CSV export and permission-aware print/export UI actions
- optional branch-specific GL mapping overrides with global fallback
- `/accounting/account-mappings` management page for global mappings and branch overrides
- optional branch-aware approval rules for stock transfer, stock count, and stock adjustment approvals
- `/settings/branch-approval-rules` management page with extra permission-gate configuration
- landed cost/freight allocation for confirmed Goods Receipts
- landed cost capitalization into remaining warehouse stock and COGS expensing for already-issued stock
- landed cost AP payable, input tax, PostingEngine GL journal, and immutable zero-quantity stock value movement support

## Implementation Guardrails

Every future product slice must preserve:

- no multi-tenant architecture
- no Company as tenant
- no Spatie Teams
- no `currentCompany` or `currentBranch` session context
- exact integer money math and `quantity_e6` quantity math
- PostingEngine-only GL posting
- PeriodGuard and TaxPeriodGuard where applicable
- immutable posted journals, ledgers, subledgers, stock movements, tax returns, and fixed asset events
- idempotency for posting and state transitions
- pessimistic locks for stock balances, sequence allocation, and settlement/concurrency paths
- dictionary-backed EN/AR UI text with no hardcoded visible labels in TSX
- clean resource controllers; avoid mega controllers mixing unrelated workflows
- permission-aware UI controls matching backend authorization
- Spatie Activitylog audit for every business mutation

## Not Approved Yet

These are product capabilities, not implemented facts:

- branch-specific permissions or user access matrices
- branch-scoped document numbering
- automatic branch defaults per user
- employee/custodian ownership
- project/department/cost-center posting dimensions

Do not implement any item above until a bounded slice explicitly approves the schema, service behavior, UI, permissions, tests, and rollback/correction behavior.
