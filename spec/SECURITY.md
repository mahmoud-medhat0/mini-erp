# SECURITY

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


Current status: Laravel migration security baseline plus 2026-08-24 hardening pass. Older Next.js/Auth.js and tenant-scoping notes are historical only.

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

## Authentication & Password Policy

- Laravel session authentication.
- CSRF protection on web mutations.
- Passwords hashed through Laravel hashing configuration (`argon2id` / `bcrypt`), never stored in plaintext.
- Password policy centralized in `config/security.php` (`password_policy`) and enforced via `PasswordPolicyRules`:
  - `min_length`: default 12 (`ERP_PASSWORD_MIN_LENGTH`)
  - `max_length`: default 128 (`ERP_PASSWORD_MAX_LENGTH`)
  - `mixed_case`: default true (`ERP_PASSWORD_REQUIRE_MIXED_CASE`)
  - `letters`: default true (`ERP_PASSWORD_REQUIRE_LETTERS`)
  - `numbers`: default true (`ERP_PASSWORD_REQUIRE_NUMBERS`)
  - `symbols`: default true (`ERP_PASSWORD_REQUIRE_SYMBOLS`)
  - Network-isolated: zero external/reputation uncompromised checks (`Password::uncompromised()` banned).
- FormRequest enforcement:
  - `StoreUserRequest`: required password matching full security policy.
  - `UpdateUserRequest`: optional/nullable password; when provided, must satisfy full policy; when omitted/empty, preserves existing hash.
- Login throttling by email/IP.
- Session regeneration on login (`$request->session()->regenerate()`).
- Session invalidation on logout (`Auth::logout()`, `$request->session()->invalidate()`, `$request->session()->regenerateToken()`).
- Active-account check on login.
- Active authenticated users are rechecked on protected web requests; inactive accounts are logged out before protected page access.
- `BootstrapUserSeeder` default credentials (`Password123!`) comply with default password policy.


## Authorization

- Server-side authorization is authoritative.
- Frontend hiding is cosmetic only.
- Use Spatie Permission plus Laravel Policies/Gates/domain authorization rules where appropriate.
- Missing permissions default to deny.
- Protected application routes must have explicit route-level authorization middleware (`can`, `permission.any`, or `permission.all`) unless the route is deliberately user/entity scoped in a service-level authorizer.
- The `security:route-audit` Artisan command (`php artisan security:route-audit [--strict] [--json]`) automatically inspects all registered routes and enforces defensive route authorization categorization:
  - `public`: no `auth` middleware and explicitly listed in `RouteAuthorizationAuditor::PUBLIC_ALLOWLIST` (`/up`, `/health`, `/locale`, and local Inertia development tooling routes).
  - `guest`: guest auth routes such as login render/submit (`/login`).
  - `explicitly_authorized`: auth route with middleware starting with `can:`, `permission.any:`, or `permission.all:`.
  - `service_authorized_allowlist`: auth route intentionally allowed because service/controller authorizes by entity/user internally (`foundation`, `logout`, `notifications`, `notifications.read_all`, `notifications.read`, `attachments.index`, `attachments.store`, `attachments.show`, `attachments.destroy`).
  - `failing`: auth route without explicit authorization middleware and not in the service-authorized allowlist, or unauthenticated route not in the public allowlist.
- `--strict` mode returns non-zero exit code (1) if any failing route is detected.
- Dashboard access requires `dashboard.view`.
- Foundation diagnostics require `settings.configure` or `audit.view`.
- Report exports require export permissions; financial exports also require `view_financials` where applicable.
- Tax return filing requires the sensitive `taxes.file` capability and is not granted through the general accountant tax module grant.
- Payroll access requires the sensitive `view_payroll` capability alongside module-specific payroll permissions; payroll posting also requires `view_financials`.
- Fixed-asset financial operations require both the fixed-asset action and `view_financials`.
- Empty RBAC assignments do not grant management access.
- Settings and user-management mutations require explicit permissions such as `settings.configure` or `users.configure`.
- Attachment access must be authorized through an explicit allowlisted entity registry and the referenced entity's server-side authorization rule.
- Audit-log viewing requires `audit.view` or an explicitly allowed administrative permission.

## RBAC & Privilege Seeding

- Spatie Permission is used with teams disabled.
- Roles and permissions are global, not company-scoped.
- `scope_json` on permission pivots is reserved/undefined and must not be interpreted as Company, Branch, or tenant scope until an owner decision defines it.
- First-user Super Admin elevation (`FirstUserSuperAdminSeeder`) is fail-closed and disabled by default (`ERP_ASSIGN_FIRST_USER_SUPER_ADMIN=false`).
- In `production`, first-user Super Admin assignment requires explicit enablement (`ERP_ASSIGN_FIRST_USER_SUPER_ADMIN=true`) plus the exact confirmation phrase (`ERP_FIRST_USER_SUPER_ADMIN_PRODUCTION_CONFIRM=CONFIRM_ASSIGN_FIRST_USER_SUPER_ADMIN`); execution throws a `RuntimeException` fail-closed if unconfirmed.
- Every privilege assignment is audited via Spatie Activitylog (`first_user_super_admin.seed`).

## Attachments

- `entity_type` values must come from an explicit allowlist.
- Browser-provided `entity_type` must never dynamically resolve arbitrary PHP classes.
- Upload and download must authorize against the stored/referenced entity.
- Unknown entity types and missing entities are rejected.
- Authentication alone is not authorization.
- The local private disk must not be directly served by framework `/storage/*` routes by default. Keep `FILESYSTEM_LOCAL_SERVE=false`; attachment delivery must go through authenticated application routes.

## HTTP Security Headers

- Baseline web security headers are applied by `AddSecurityHeaders`.
- CSP support exists in `config/security.php` but remains disabled by default until the final production asset policy is approved.
- Production operators may enable CSP through environment configuration after browser smoke testing.

## Data And Integrity

- Money and accounting writes must remain transactional.
- Posted financial history, audit records, numbering history, and journal data must not be garbage-collected merely because of age.
- Spatie Activitylog is the active audit backend. Audit records link actor/causer, action/event, entity type/id, before/after payload, and timestamp without invented Company/Branch scope.
- Legacy `audit_log` is retained as archive; both `activity_log` and `audit_log` are append-only at the database level.
- Ledger entries are immutable; corrections must use reversal workflows.
- FiscalYear is global to the single ERP context and must not be used as a Company/Tenant boundary.

## Current Gaps

- Branch is an approved operational/reporting dimension for bounded workflows only; it is still not a tenant, Company child, login context, blanket authorization scope, or document-numbering scope.
- Production scheduler execution still needs deployment wiring outside the codebase.
- CSP is configurable but not enabled by default pending production browser smoke and asset policy approval.
