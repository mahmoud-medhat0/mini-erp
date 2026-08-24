# NO MULTI-TENANT POLICY

This repository's active Laravel ERP target is a single-installation ERP context.

The system is not a multi-tenant SaaS unless a later explicit owner decision changes that scope and defines the exact business model.

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
- default `company_id`, `branch_id`, or `tenant_id` on business tables
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

`branch` is a standalone business/reporting reference unless later explicitly redefined by the owner.

It is not:

- a tenant
- a security boundary
- owned by Company
- a required document-numbering dimension

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
