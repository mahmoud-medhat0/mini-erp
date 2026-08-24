# PHASE 4 SLICE 1 - PRODUCT/SERVICE CATALOG FOUNDATION

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


You are continuing the active Laravel + Inertia Mini ERP migration.

Execute only Phase 4 Slice 1.

Do not start Sales Orders, Purchase Orders, Delivery Notes, Goods Receipts, Customer Invoices, Supplier Bills, Inventory Valuation, COGS, VAT, Returns, Reports, Dashboard expansion, E2E hardening, or deployment work in this pass.

## Read First

Read and follow:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `PHASE_3_FINAL_VERIFICATION_REPORT.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`

Use the current Laravel code as the source of truth.

Do not treat old Next.js docs, generated specs, or historical Phase 3 prompts as proof of unsupported business relationships.

## Objective

Build the Phase 4 foundation for a Product/Service Catalog and Unit of Measure management.

This slice should make the ERP ready to reference products/services from later Sales and Purchasing documents, without creating those documents yet.

## Hard Boundaries

Do not introduce:

- tenant context
- company scope
- branch scope
- `company_id`
- `branch_id`
- `tenant_id`
- Spatie Teams
- `currentCompany`
- `currentBranch`
- warehouse/branch relationship
- inventory valuation
- stock quantity ledger
- COGS posting
- sales order tables
- purchase order tables
- delivery note tables
- goods receipt tables
- customer invoice tables
- supplier bill tables
- tax/VAT tables or fields
- price list engine
- approval engine

If a relationship is not explicitly supported, classify it as:

`UNDEFINED - DO NOT ASSUME`

## Required Slice Scope

Implement a catalog foundation using the existing Laravel patterns.

Expected backend concepts:

- Unit of Measure.
- Product/Service Catalog.
- Optional simple product category/classification if useful and bounded.

Use names that fit the existing codebase. If choosing between `item` and `product`, pick one consistently and document the reason in the implementation report.

Minimum catalog attributes:

- globally unique code/SKU
- multilingual name using the existing Spatie Translatable pattern
- optional multilingual description
- type: stock, service, non_stock
- default unit of measure
- active/inactive status
- sales enabled flag
- purchase enabled flag
- optimistic lock/version field if current master-data services use that pattern

Do not add monetary price fields in this slice unless the existing requirements/code already define the pricing model. A single "default price" can become a bad assumption before price lists, currency, discounts, and tax rules are decided.

Do not add accounting account mappings per product in this slice. Revenue, expense, inventory, COGS, and clearing mappings belong in later posting slices after rules are explicit.

## Relationships Allowed

Allowed only if implemented with real foreign keys and tests:

- Product belongs to UnitOfMeasure.
- UnitOfMeasure has many Products.
- Product belongs to ProductCategory, if ProductCategory is created.
- ProductCategory has many Products, if ProductCategory is created.

Do not create Company, Branch, User, Warehouse, Sales, Purchasing, Inventory, Tax, or Accounting posting relationships in this slice.

## RBAC

Extend the current RBAC config/seeding pattern with bounded permissions such as:

- `products.view`
- `products.create`
- `products.update`
- `products.delete`
- `uom.view`
- `uom.create`
- `uom.update`
- `uom.delete`

Use current naming conventions if the codebase already has a different module/action pattern.

Do not enable Spatie teams.

## Audit

Use the existing `AuditLogger` API only.

Audit create/update/delete or deactivate/reactivate events for:

- units of measure
- product/service catalog records
- optional product categories

Do not write directly to legacy `audit_log`.

Spatie Activitylog is the active backend.

## Attachments

Register attachment support only where it makes sense for the new catalog entity.

If registering attachments for products, use the existing attachment entity authorizer/registry pattern and enforce server-side authorization.

Do not authorize attachments through company/branch scope.

## Notifications

Do not add notification triggers unless there is a concrete user-facing event in this slice that needs notification.

If no notification trigger is added, explicitly report that no notification trigger was needed for Slice 1.

## UI Scope

Prefer backend correctness first.

If the existing project pattern expects a management page for master data in the same slice, add a small, production-quality Inertia page for product catalog and/or UOM management.

If UI is added:

- use the existing AppLayout patterns
- support EN/AR translations
- support RTL
- use existing form/input/table components where available
- use permission-aware action controls
- include empty states
- do not make a landing page

If UI is not added, explicitly report that UI is deferred to a later Phase 4 UX slice.

## Validation And Integrity

Required validations:

- code/SKU required, normalized, and globally unique
- name required in at least the current/default locale
- type must be one of: stock, service, non_stock
- default UOM must exist and be active
- cannot delete a UOM used by products
- cannot delete a category used by products, if categories exist
- cannot delete system/protected seed rows, if system rows exist
- inactive products cannot be selected by future sales/purchasing services

Required schema checks:

- no `company_id`
- no `branch_id`
- no `tenant_id`
- no accidental inventory stock quantity columns
- no accidental sales/purchasing document columns
- no monetary price fields unless explicitly justified by existing code/requirements

## Seeders

Add idempotent seeders for:

- common units of measure, for example each, hour, day, kg, meter, box
- any product categories if created

Seeders must be safe to run repeatedly.

Do not seed fake sales or purchasing documents.

## Tests

Add focused tests proving:

- migrations create the expected tables and foreign keys
- no forbidden company/branch/tenant scope columns exist
- UOM seeders are idempotent
- product/category seeders, if any, are idempotent
- product create/update validation works
- duplicate code/SKU is rejected
- invalid type is rejected
- inactive or missing UOM is rejected
- UOM in use cannot be deleted
- category in use cannot be deleted, if categories exist
- audit entries are written through Spatie Activitylog via `AuditLogger`
- RBAC permissions are registered
- attachment registry works for product if added
- no journal/ledger/receivable/payable entries are created by catalog operations

If UI is added, add feature tests for the Inertia props and basic actions.

## Verification Commands

Run from `laravel/` and report the exact results:

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

If a command is not applicable because no frontend changed, still run `npm run typecheck` and `npm run build` to protect the current app.

## Documentation Updates

After implementation, update:

- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`
- `MD_DOCUMENTATION_AUDIT.md`

Do not mark Phase 4 complete. Mark only Phase 4 Slice 1 complete.

## Final Report Required

Return a concise implementation report with:

1. Files changed.
2. Migrations added.
3. Schema diff.
4. Models and relationships added.
5. RBAC permissions added.
6. Audit/attachment/notification changes.
7. Explicit confirmation that no company/branch/tenant scope was introduced.
8. Confirmation that no Sales/Purchasing/Inventory posting modules were started.
9. Test results.
10. Stress results.
11. Remaining risks or owner decisions needed.

