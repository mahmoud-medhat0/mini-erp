# SECURITY

Current status: Laravel migration security baseline. Older Next.js/Auth.js and tenant-scoping notes are historical only.

## Non-Negotiable Domain Rule

The Mini ERP is not currently a multi-tenant SaaS. Security must not rely on:

- tenant context
- `company_id` query scope
- `branch_id` query scope
- Company-owned users/roles/permissions
- Branch as a security boundary
- Spatie Teams
- `currentCompany` or `currentBranch`

If a relationship is not explicitly established by owner requirements or later owner decisions, classify it as `UNDEFINED - DO NOT ASSUME`.

## Authentication

- Laravel session authentication.
- CSRF protection on web mutations.
- Passwords hashed through Laravel hashing configuration.
- Login throttling by email/IP.
- Session regeneration after login.
- Session invalidation after logout.
- Active-account check on login.

## Authorization

- Server-side authorization is authoritative.
- Frontend hiding is cosmetic only.
- Use Spatie Permission plus Laravel Policies/Gates/domain authorization rules where appropriate.
- Missing permissions default to deny.
- Empty RBAC assignments do not grant management access.
- Settings and user-management mutations require explicit permissions such as `settings.configure` or `users.configure`.
- Attachment access must be authorized through an explicit allowlisted entity registry and the referenced entity's server-side authorization rule.
- Audit-log viewing requires `audit.view` or an explicitly allowed administrative permission.

## RBAC

- Spatie Permission is used with teams disabled.
- Roles and permissions are global, not company-scoped.
- `scope_json` on permission pivots is reserved/undefined and must not be interpreted as Company, Branch, or tenant scope until an owner decision defines it.

## Attachments

- `entity_type` values must come from an explicit allowlist.
- Browser-provided `entity_type` must never dynamically resolve arbitrary PHP classes.
- Upload and download must authorize against the stored/referenced entity.
- Unknown entity types and missing entities are rejected.
- Authentication alone is not authorization.

## Data And Integrity

- Money and accounting writes must remain transactional.
- Posted financial history, audit records, numbering history, and journal data must not be garbage-collected merely because of age.
- Spatie Activitylog is the active audit backend. Audit records link actor/causer, action/event, entity type/id, before/after payload, and timestamp without invented Company/Branch scope.
- Legacy `audit_log` is retained as archive; both `activity_log` and `audit_log` are append-only at the database level.
- Ledger entries are immutable; corrections must use reversal workflows.
- FiscalYear is global to the single ERP context and must not be used as a Company/Tenant boundary.

## Current Gaps

- Full financial statements and later operational modules are not implemented yet.
- AR/AP, Cash, Bank, Cheques, Sales, Purchasing, Inventory, Payroll, Rentals, Fixed Assets, Projects, and Budgeting need module-specific policies when implemented.
- Branch exact semantics remain owner-decision-required.
- Production admin/bootstrap process needs an explicit controlled mechanism; no implicit "first user" or "empty RBAC" privilege escalation is allowed.
- Production scheduler execution still needs deployment wiring outside the codebase.
