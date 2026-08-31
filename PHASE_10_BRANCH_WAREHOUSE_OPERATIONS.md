# PHASE 10 - BRANCH, WAREHOUSE, AND OPERATIONAL DIMENSIONS

> **Implementation update 2026-08-24 / 2026-08-25:** The owner approved branch support as an operational dimension and requested execution. Warehouses, stock locations, warehouse-aware stock balances/movements, stock transfers, stock counts, stock adjustments, warehouse selectors on fulfillment/returns, cash/bank branch assignment, treasury transfers, fixed asset locations/movement history, branch operational reports, optional GL branch dimensions, branch profitability, Branch Profitability export/print, optional branch-specific GL mapping overrides, branch-aware approval rules, landed cost/freight allocation, UI pages, RBAC, report filters, and concurrency stress coverage were implemented. See `PHASE_10_FINAL_REPORT.md`, `PHASE_10_OPERATIONAL_COMPLETION_REPORT.md`, `PHASE_10_TREASURY_TRANSFER_REPORT.md`, `PHASE_10_FIXED_ASSET_MOVEMENT_REPORT.md`, `PHASE_10_BRANCH_OPERATIONAL_REPORTS_REPORT.md`, `PHASE_10_GL_BRANCH_PROFITABILITY_REPORT.md`, `PHASE_10_BRANCH_GL_MAPPING_REPORT.md`, `PHASE_10_BRANCH_APPROVAL_RULES_REPORT.md`, and `PHASE_10_LANDED_COST_ALLOCATION_REPORT.md`.

## Mission

Design and implement the next product track so the ERP can handle multiple operational branches, warehouses, stock transfers, branch-aware sales and purchasing, and operational reports without becoming multi-tenant.

Phase 10 must follow `PRODUCT_EXTENSIBILITY_ROADMAP.md`.

## Non-Negotiable Scope Rule

This phase may add branch/warehouse operational references only where the slice explicitly proves the need.

This phase must not add:

- tenant middleware
- Company as tenant
- Spatie Teams
- `currentCompany`
- `currentBranch`
- branch-owned roles or permissions
- blanket `branch_id` columns on unrelated tables
- branch-scoped number sequences unless the owner explicitly approves that exact behavior

## Target Capabilities

Phase 10 should make the product operationally ready for:

- multiple branches in one ERP installation
- warehouses and optional stock locations
- branch-to-branch and warehouse-to-warehouse stock transfers
- stock in transit
- partial receipts and transfer discrepancies
- controlled stock adjustments and stock counts
- branch-aware sales, delivery, purchase receipt, return, and reporting workflows
- optional branch filters and selectors in accounting-friendly UI
- branch-aware approval rules for inventory approval workflows without enabling branch as a security boundary

## Proposed Slices

| Slice | File | Scope |
|---|---|---|
| 1 | `PHASE_10_SLICE_1_GEMINI_PROMPT.md` | decision pack and schema-impact audit |
| 2 | `PHASE_10_SLICE_2_GEMINI_PROMPT.md` | warehouse and stock location foundation |
| 3 | `PHASE_10_SLICE_3_GEMINI_PROMPT.md` | stock balance and stock movement dimension refactor |
| 4 | `PHASE_10_SLICE_4_GEMINI_PROMPT.md` | stock transfer workflow |
| 5 | `PHASE_10_SLICE_5_GEMINI_PROMPT.md` | stock count and adjustment workflow |
| 6 | `PHASE_10_SLICE_6_GEMINI_PROMPT.md` | branch-aware sales and purchasing UX/actions |
| 7 | `PHASE_10_SLICE_7_GEMINI_PROMPT.md` | cash/bank transfer and optional branch assignment |
| 8 | `PHASE_10_SLICE_8_GEMINI_PROMPT.md` | fixed asset location, branch movement, and custody preparation |
| 9 | `PHASE_10_SLICE_9_GEMINI_PROMPT.md` | branch operational reports |
| 10 | `PHASE_10_SLICE_10_GEMINI_PROMPT.md` | close-out, source scans, stress, UI/UX and clean-code review |

The original slice plan remains useful for future expansion, but the 2026-08-24 accelerated pass implemented the warehouse and stock transfer foundation directly.

## Cross-Cutting Acceptance Rules

Every implementation slice must:

- run PostgreSQL migrations forward only
- preserve existing data or report before a risky change
- keep money and quantity arithmetic integer-only
- preserve stock movement immutability
- preserve accounting ledger immutability
- post through PostingEngine only
- use Spatie Activitylog through `AuditLogger`
- use exact permissions from RBAC config or add new permissions explicitly to `config/erp_rbac.php`
- add matching permission-aware UI controls
- avoid hardcoded visible TSX labels and status text
- avoid controllers mixing unrelated resources
- add feature tests and concurrency/stress tests where stock, posting, or state transitions are affected

## Current Implemented Position

Implemented inventory and operations today:

- Moving Weighted Average costing
- `stock_balance`
- immutable `stock_movement_ledger`
- Goods Receipt inventory receipt posting
- Delivery Note inventory issue posting
- sales/purchase return stock movements
- `warehouse`
- `stock_location`
- optional operational `warehouse.branch_id`
- warehouse-aware stock balances
- warehouse-aware stock movement ledger entries
- warehouse-to-warehouse and branch-to-branch stock transfers through warehouses
- transfer issue and receipt movements
- partial transfer receipt support
- stock count workflow with variance lines
- stock adjustment workflow with approval/posting
- stock count variance posting through generated stock adjustments
- warehouse selectors on Delivery Notes, Goods Receipts, Sales Returns, and Purchase Returns
- warehouse filters in stock balances and stock movement reports
- warehouse filters in Delivery Note and Goods Receipt reports
- Inertia pages for warehouses and stock transfers
- Inertia pages for stock counts and stock adjustments
- `accounting:stock-transfer-stress --workers=50`
- cash/bank optional branch assignment
- treasury transfer workflow between cash/bank accounts
- fixed asset locations
- current fixed asset branch/location position
- append-only fixed asset movement history
- branch operations report under `/reports/branch-operations`
- branch readiness checks for unassigned warehouses, stock balances, cash/bank accounts, and fixed assets
- optional GL branch dimension on `journal_entry`, `journal_line`, and immutable `ledger_entry`
- branch-filtered General Ledger review
- branch profitability report under `/reports/branch-profitability`
- Branch Profitability CSV export and print actions
- optional branch-specific GL mapping overrides with global fallback
- account mapping management page under `/accounting/account-mappings`
- optional branch-aware approval rules for stock transfer, stock count, and stock adjustment approvals
- branch approval rule management page under `/settings/branch-approval-rules`
- landed cost/freight allocation with stock capitalization, COGS split, AP payable, and PostingEngine GL posting

## Verification Gate

Run from `laravel/` for every implementation slice:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=10
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan accounting:stock-transfer-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Additional Phase 10 stress commands must be added by the slices that introduce stock transfers, stock counts, or branch-aware posting.
