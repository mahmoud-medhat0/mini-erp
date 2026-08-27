# MINI ERP - PHASE 10 SLICE 1 - BRANCH AND WAREHOUSE OPERATING MODEL DECISION PACK

You are continuing the existing Laravel Mini ERP.

This is a docs-first decision and audit slice only.

Do not write migrations, models, controllers, services, routes, React pages, seeders, jobs, or tests in this slice unless the owner explicitly asks for implementation after reviewing the decision pack.

## Source Of Truth

Read first:

- `PRODUCT_EXTENSIBILITY_ROADMAP.md`
- `PHASE_10_BRANCH_WAREHOUSE_OPERATIONS.md`
- `NO_MULTI_TENANT_POLICY.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `README.md`
- current Laravel migrations/models/services for `branch`, `stock_balance`, `stock_movement_ledger`, sales, purchasing, cash/bank, fixed assets, attachments, notifications, audit, and RBAC

## Required Context

Owner direction recorded 2026-08-24:

- the product must support multiple operational branches
- the product must support branch transfers and transfer workflows
- the product must remain flexible for all sales, purchasing, inventory, returns, cash/bank, fixed asset, reporting, and approval scenarios

This does not mean multi-tenancy.

Branch is an operational dimension, not a tenant or automatic security boundary.

## Required Output

Create `PHASE_10_BRANCH_WAREHOUSE_DECISION_PACK.md`.

The decision pack must be written for both business owner and developer audiences and must include:

1. Plain-language Arabic owner summary.
2. English technical summary.
3. Current implementation inventory of branch, stock, sales, purchasing, returns, cash/bank, and fixed asset capabilities.
4. Branch operating model options:
   - Option A: Branch only as reporting tag.
   - Option B: Branch has one or more warehouses.
   - Option C: Warehouse can serve multiple branches.
   - Option D: Mixed model with explicit document-level source and destination contexts.
5. Warehouse/location options:
   - no locations
   - warehouse-level only
   - warehouse plus bin/location
   - quarantine/repair/scrap/supplier-return holding locations
6. Stock transfer lifecycle options:
   - immediate transfer
   - issue and receipt with in-transit state
   - partial receipt
   - discrepancy handling
   - cancellation and reversal
7. Accounting options for branch transfer:
   - stock subledger only when same inventory GL account is used
   - balanced GL movement through inter-branch clearing when branch-level inventory GL reporting is enabled
   - never recognize revenue or VAT on internal branch transfer by default
8. Return workflow options:
   - return to original branch/warehouse
   - return to different branch/warehouse
   - original outbound cost
   - authorized manual cost or percentage override with reason and audit
   - saleable, quarantine, repair, scrap, supplier-return disposition
9. Cash/bank branch transfer options:
   - no branch assignment
   - optional branch assignment on cash/bank accounts
   - transfer between cash desks/bank accounts with optional clearing
10. Fixed asset branch/location movement options:
    - location history only
    - branch assignment
    - custodian preparation without assuming User = Employee
11. Permission model:
    - keep current global Spatie RBAC
    - list any new permissions needed by future slices
    - do not enable Spatie Teams
    - do not implement branch access control unless owner approves exact access rules
12. UI/UX standards:
    - accounting-friendly dense pages
    - branch/warehouse filters where useful
    - searchable selectors
    - no hardcoded visible TSX strings
    - clear empty states and validation
13. Clean-code controller/service plan:
    - separate controllers by resource
    - no mega AccountingController expansion
    - services own business transactions
    - controllers validate, authorize, call services, return Inertia/redirects
14. Exact owner decision checklist with yes/no/flexible options.
15. Proposed Phase 10 Slice 2 implementation contract preview.

## Required Audits

Run read-only scans and include classifications in the decision pack:

```powershell
rg -n "branch_id|warehouse|location|stock_balance|stock_movement_ledger|currentBranch|tenant_id|Spatie Teams" laravel/app laravel/database laravel/routes laravel/resources/js laravel/tests
rg -n "class .*Controller|Route::" laravel/app/Http/Controllers laravel/routes/web.php
rg -n "locale ===|[\\p{Arabic}]" laravel/resources/js
```

For each scan:

- zero matches can be reported as clean
- non-zero matches must be classified as acceptable baseline, future gap, or must-fix
- do not call a scan clean if it prints matches

## Hard Rules

- Do not create new schema in this slice.
- Do not add `branch_id` anywhere in code in this slice.
- Do not add warehouse tables in this slice.
- Do not change existing implementation behavior in this slice.
- Do not modify Laravel business code in this slice.
- Do not remove the no-multi-tenant policy.
- Do update documentation status files to record Slice 1 completion and pending owner decisions.

## Final Report Required

Your final report must include:

1. files created and changed
2. confirmation of zero implementation code changes
3. owner decisions still required
4. scan commands and classifications
5. next recommended file
