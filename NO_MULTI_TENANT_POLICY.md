# NO MULTI-TENANT POLICY

This repository's active Laravel ERP target is a single-installation ERP context.

The system is not a multi-tenant SaaS unless a later explicit owner decision changes that scope and defines the exact business model.

## Owner Direction: Branch-Capable Operations

On 2026-08-24, the owner explicitly required the product to remain capable of multiple operational branches, branch transfers, and branch-aware business workflows.

This is an ERP operations requirement. It does not redefine Branch as a tenant, Company child, login context, or security boundary.

Future slices may add branch references to specific operational records only when the slice proves the need and includes migrations, service rules, UI, permissions, tests, audit logging, and source-scan classification.

## Current Rule

Do not add, infer, or restore:

- tenant context or tenant middleware
- `company_user`
- `users.company_id`
- `branch.company_id`
- `fiscal_year.company_id`
- `currentCompany`
- `currentBranch`
- company-owned users, branches, roles, or permissions
- Spatie Teams / company-scoped RBAC
- company, branch, or tenant dimensions in document numbering
- default `company_id`, `branch_id`, or `tenant_id` on business tables without an explicit module-level owner decision
- authorization scopes based on invented company or branch ownership

## Company

`company` is a business configuration/profile record only.

It does not prove:

- multiple companies
- Company as tenant
- Company owning users
- Company owning branches
- Company owning roles or permissions

## Branch

`branch` is a standalone business/reporting/operational reference. It may be used by future branch-capable operational workflows when explicitly approved and implemented by a bounded slice.

It is not:

- a tenant
- a security boundary
- owned by Company
- a required document-numbering dimension

Branch-capable workflows must follow `PRODUCT_EXTENSIBILITY_ROADMAP.md`.

Current approved branch reference:

- `warehouse.branch_id` is allowed as an optional operational/reporting reference. It is not tenancy, ownership, login context, or authorization scope.
- `cash_account.branch_id` and `bank_account.branch_id` are allowed as optional operational/reporting references for branch cash/bank operations.
- `fixed_asset.branch_id` and fixed asset movement branch snapshots are allowed as optional operational location/history references.
- `journal_entry.branch_id`, `journal_line.branch_id`, and `ledger_entry.branch_id` are allowed only as optional operational reporting dimensions created by approved branch-aware accounting slices.
- `accounting_account_mapping.branch_id` is allowed only as an optional branch-specific override with global fallback; it is not a tenant or authorization scope.
- `employee.branch_id`, `payroll_run.branch_id`, and `payroll_run_line.branch_id` are allowed only as optional payroll operational/reporting references.

Current approved warehouse references:

- `warehouse_id` on stock balances, stock movements, stock transfers, stock counts, stock adjustments, Delivery Notes, Goods Receipts, Sales Returns, and Purchase Returns is an operational inventory movement dimension. It is not tenancy, Company ownership, login context, or authorization scope.

## Users And RBAC

Users, roles, and permissions are confirmed.

RBAC remains global through Spatie Permission with Teams disabled.

User-to-Company, User-to-Branch, and User-to-Employee relationships are undefined until explicitly approved.

## Fiscal Years

FiscalYear is `SINGLE-ERP CONTEXT`.

Fiscal years are global to this ERP installation/business profile, and `fiscal_year.year` is globally unique.

FinancialPeriod belongs to FiscalYear.

## Historical Documents

Some older documents describe the superseded Next.js/Prisma reference app or pre-correction generated architecture.

Any historical text that mentions tenant isolation, company scope, branch scope, company-owned roles, or company/branch ownership must be read as legacy context only.

The active Laravel implementation and all future prompts must follow this policy.
