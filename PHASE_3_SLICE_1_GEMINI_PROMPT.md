# MINI ERP - PHASE 3 SLICE 1 GEMINI PROMPT

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


You are continuing the existing Laravel + Inertia + React Mini ERP repository.

Implement **Phase 3 Slice 1 only**.

Do not implement the whole Phase 3.

Read these first:

- `README.md`
- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`
- `DOMAIN_MODEL_REVIEW.md`
- `DOMAIN_RELATIONSHIP_AUDIT.md`
- `SCHEMA_ASSUMPTION_AUDIT.md`
- `docs/CONCURRENCY_AUDIT.md`

Then inspect the actual Laravel code under:

- `laravel/app`
- `laravel/config`
- `laravel/database`
- `laravel/routes`
- `laravel/resources/js`
- `laravel/tests`
- `laravel/composer.json`
- `laravel/package.json`

Do not rely on old Next.js code or generated specs when they conflict with the current Laravel implementation or owner corrections.

## OWNER DECISIONS

The ERP is not a multi-tenant SaaS.

Do not introduce:

- `company_id`
- `branch_id`
- `tenant_id`
- tenant middleware/context
- `currentCompany`
- `currentBranch`
- Company-owned roles/permissions
- Spatie Teams
- Company/Branch scope

If a relationship is not explicitly required, classify it as:

`UNDEFINED - DO NOT ASSUME`

Audit decision:

- Use Spatie Activitylog as the active audit backend.
- Use the existing `App\Domain\Audit\AuditLogger` API.
- Do not write new Phase 3 audit rows directly to legacy `audit_log`.
- Do not create another audit system.
- Do not add Company/Branch/Tenant audit scope.

## SLICE 1 OBJECTIVE

Implement only the Phase 3 master-data foundation:

1. Customer master data.
2. Supplier master data.
3. CashAccount master data.
4. BankAccount master data.
5. GL-account links for CashAccount and BankAccount.
6. Models, relationships, services/actions as needed for safe persistence.
7. RBAC permissions for these master-data actions.
8. Audit logging through `AuditLogger`.
9. Attachment entity registration for Slice 1 entities where appropriate.
10. Tests.
11. Docs/status update.

This slice must not post accounting entries.

## STRICT NON-GOALS

Do not implement:

- receipts
- payments
- allocations
- cheques
- bank reconciliation
- AR/AP opening balances
- AR/AP subledger entries
- Sales Invoice
- Purchase Invoice
- Inventory movement
- VAT workflow
- full financial statements
- dashboard expansion
- new accounting posting engine
- new idempotency system
- new audit system
- frontend pages unless explicitly required by an already existing route pattern for this slice

If tempted to implement any of these, stop.

## EXPECTED TABLES

Use singular table names consistent with the current Laravel schema style:

- `customer`
- `supplier`
- `cash_account`
- `bank_account`

Use UUID primary keys for these business tables.

No table may contain:

- `company_id`
- `branch_id`
- `tenant_id`

## CUSTOMER TABLE

Required minimum fields:

- `id` uuid primary key
- `code` string, globally unique in the current single-ERP context
- `name` JSON/JSONB multilingual field compatible with the project translatable pattern
- `status` string or boolean active flag; choose one consistent with existing patterns
- optional contact fields only if simple and non-invasive, such as email/phone/address/tax_number
- `created_by` nullable FK to users if consistent with current audit/provenance patterns
- `updated_by` nullable FK to users if consistent
- `lock_version` unsigned integer default 0 if using existing optimistic locking pattern
- timestamps

Do not add balance fields as accounting source of truth.

Do not add opening balance fields in Slice 1.

## SUPPLIER TABLE

Mirror the safe Customer structure:

- `id`
- `code`
- `name`
- `status`
- optional contact fields
- provenance fields if used for Customer
- `lock_version` if used for Customer
- timestamps

Do not add balance fields as accounting source of truth.

Do not add opening balance fields in Slice 1.

## CASH_ACCOUNT TABLE

Required minimum fields:

- `id` uuid primary key
- `code` string, globally unique
- `name` JSON/JSONB multilingual field
- `gl_account_id` uuid FK to `account.id`
- `currency` char/string(3), FK to `currency.code`
- `is_active` boolean default true
- provenance fields if consistent
- `lock_version` if using optimistic locking
- timestamps

Rules:

- referenced GL account must exist
- referenced GL account must be active
- referenced currency must exist
- no browser-provided trusted accounting source classification
- do not add a new `manual_posting_allowed` flag
- use existing Accounting Core control-account rules; do not weaken manual journal restrictions

## BANK_ACCOUNT TABLE

Required minimum fields:

- `id` uuid primary key
- `code` string, globally unique
- `name` JSON/JSONB multilingual field
- `bank_name` nullable string or JSON multilingual if useful
- `account_number` nullable string
- `iban` nullable string
- `swift` nullable string
- `gl_account_id` uuid FK to `account.id`
- `currency` char/string(3), FK to `currency.code`
- `is_active` boolean default true
- provenance fields if consistent
- `lock_version` if using optimistic locking
- timestamps

Rules:

- referenced GL account must exist
- referenced GL account must be active
- referenced currency must exist
- do not store secrets
- no Company/Branch scope

## MODELS AND RELATIONSHIPS

Create Eloquent models:

- `App\Models\Customer`
- `App\Models\Supplier`
- `App\Models\CashAccount`
- `App\Models\BankAccount`

Expected relationships:

- CashAccount belongs to Account through `gl_account_id`
- CashAccount belongs to Currency through `currency -> code`
- BankAccount belongs to Account through `gl_account_id`
- BankAccount belongs to Currency through `currency -> code`

Do not add:

- Company relationships
- Branch relationships
- User ownership relationships beyond optional provenance fields
- Customer/Supplier balances as mutable source of truth

## SERVICES / CONTROLLERS / ACTIONS

Implement only what is needed for safe master-data persistence and tests.

If adding controllers/routes:

- use server-side validation
- require explicit permissions
- audit create/update/status changes with `AuditLogger`
- do not build posting workflows

If deferring UI/controllers to later slices, add model/service-level tests and document that UI comes later.

## RBAC

Extend `config/erp_rbac.php` only as needed.

Suggested permissions:

- `customers.view`
- `customers.create`
- `customers.edit`
- `suppliers.view`
- `suppliers.create`
- `suppliers.edit`
- `cash.view`
- `cash.create`
- `cash.edit`
- `banks.view`
- `banks.create`
- `banks.edit`

Do not add branch/company scoped permissions.

Update seeders/tests so permissions are registered.

## ATTACHMENTS

Reuse the existing attachment entity registry.

For Slice 1, register only entities that exist in this slice:

- `customer`
- `supplier`
- `cash_account` if attachments are appropriate
- `bank_account` if attachments are appropriate

Do not add generic dynamic class resolution.

Unknown entity types remain deny-by-default.

## AUDIT

Audit through `AuditLogger`:

- customer create/update/status changes
- supplier create/update/status changes
- cash account create/update/status changes
- bank account create/update/status changes

Tests should assert new audit records are visible in the active Spatie Activitylog-backed audit path used by the current app.

Do not write new Phase 3 audit rows directly to legacy `audit_log`.

## TESTS

Add focused tests for:

- tables exist
- no `company_id`, `branch_id`, `tenant_id`
- Customer code uniqueness
- Supplier code uniqueness
- CashAccount code uniqueness
- BankAccount code uniqueness
- CashAccount requires valid active GL account
- BankAccount requires valid active GL account
- CashAccount requires valid currency
- BankAccount requires valid currency
- models expose GL account and currency relationships
- RBAC permissions exist
- unauthorized users cannot mutate master data if controllers/actions are added
- authorized users can create/update master data if controllers/actions are added
- audit records are written through the current `AuditLogger`
- attachment registry accepts only explicit Slice 1 entity types
- Spatie teams remain disabled

Do not weaken existing tests.

## VERIFICATION

Run and report exact results:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

If UI files are not touched, `npm run typecheck` and `npm run build` should still pass.

## REQUIRED FINAL REPORT

Report:

1. Files changed.
2. Migrations added.
3. Schema summary.
4. Models and relationships.
5. RBAC additions.
6. Attachment registry additions.
7. Audit behavior and confirmation it uses Spatie Activitylog through `AuditLogger`.
8. Confirmation no Company/Branch/Tenant scope was introduced.
9. Tests added.
10. Full verification command results.
11. Remaining deferred Phase 3 slices.

Explicitly confirm:

```text
Slice implemented: Phase 3 Slice 1 only
Receipts implemented: NO
Payments implemented: NO
Allocations implemented: NO
Cheques implemented: NO
Bank reconciliation implemented: NO
Sales/Purchasing/Inventory implemented: NO
company_id introduced: NO
branch_id introduced: NO
tenant_id introduced: NO
Spatie teams enabled: NO
Audit backend changed away from Spatie Activitylog: NO
```
