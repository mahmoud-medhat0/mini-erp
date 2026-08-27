# ARCHITECTURE

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.

This document is the active architecture summary for the Laravel migration target. Older Next.js, Prisma, Edge runtime, or pg-boss architecture notes are historical only.

## Runtime

- Laravel 13.x + PHP 8.3+
- PostgreSQL
- Inertia.js + React + TypeScript + Tailwind
- Laravel session authentication and CSRF
- Laravel scheduler and database queue baseline
- Spatie Permission with teams disabled
- Spatie Activitylog as the active audit backend

## Style

The ERP is a Laravel modular monolith.

Primary boundaries:

- HTTP controllers stay thin and delegate business work to application services.
- Application services own workflow transitions, validation beyond request shape, locking, idempotency, audit calls, and PostingEngine calls.
- Eloquent models represent persistence relationships and should not contain workflow-heavy logic.
- Inertia/React pages are UX surfaces only; they must not compute authoritative balances, accounting totals, posting effects, or permission truth.
- Financial writes happen through explicit services inside database transactions.

## Dependency Direction

The accounting ledger spine is the shared financial kernel. Business modules write financial impact by calling approved services such as `PostingEngine`, account mapping resolution, PeriodGuard, and domain-specific subledger services.

Current implemented module flow:

- Foundation/RBAC/Auth/Settings
- Accounting Core
- AR/AP + Cash/Bank/Cheques
- Sales, Purchasing, Inventory, and Returns
- Financial Statements and Period Close
- Fixed Assets
- Tax/VAT
- Operational Readiness and Cutover Documentation
- Branch-capable operations
- Expenses, Prepaids, Accruals
- Payroll Foundation

Future modules such as Rentals, Projects, Cost Centers, Budgeting, Recurring Workflows, Partners/Equity, and external integrations must be added as bounded slices.

## Accounting Rules

- Money is stored as integer minor units.
- Quantity uses integer micro-units where applicable.
- Tax rates and percentage rates use integer basis points.
- No PHP float arithmetic is allowed in authoritative financial calculations.
- Posted journals and ledger entries are immutable.
- Corrections happen through reversals or explicit adjustment workflows.
- Document numbering uses concurrency-safe global sequence keys unless a later owner decision explicitly changes identity.
- Branch may be used as an operational/reporting dimension only where explicitly implemented; it is not a tenant, login context, or authorization boundary.

## Security

- Server-side authorization is authoritative.
- Spatie Teams remains disabled.
- Sensitive capabilities such as `view_financials`, `view_payroll`, and `taxes.file` must be checked alongside module permissions where relevant.
- Attachments must be served only through authenticated, entity-authorized routes.
- Audit logging uses Spatie Activitylog through the app-level `AuditLogger` adapter.

## Source Of Truth

Use these files for current status and continuation:

- `../IMPLEMENTATION_STATUS.md`
- `../NEXT_TASKS.md`
- `../CONTINUE_HERE.md`
- `../PHASE_13_PAYROLL_FOUNDATION_REPORT.md`
- `../NO_MULTI_TENANT_POLICY.md`
- `../PRODUCT_EXTENSIBILITY_ROADMAP.md`
