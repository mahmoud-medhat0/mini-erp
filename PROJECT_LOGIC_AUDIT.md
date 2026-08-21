# Project Logic Audit

Date: 2026-08-21

Scope: inspection-only review of the current Mini ERP repository, with emphasis on the Laravel target. No feature work, refactor, schema change, or existing documentation rewrite was performed in this pass.

Post-audit correction note: after this audit, focused correction passes removed the implicit settings authorization fallback, added allowlisted attachment entity authorization, added attachment storage failure compensation, renamed the misleading `COMPANY_ADMIN` role template to `ERP_ADMIN`, corrected stale tenant/company-scope documentation, and removed `fiscal_year.company_id` for the single-ERP FiscalYear context.

Reviewed evidence:

- Original owner corrections in the current conversation, especially the Company / Branch / User correction.
- Current Laravel implementation under `laravel/app`, `laravel/bootstrap`, `laravel/config`, `laravel/database`, `laravel/routes`, `laravel/resources/js`, `laravel/tests`, `laravel/composer.json`, and `laravel/package.json`.
- Markdown inventory from `rg --files -g "*.md"`.
- Non-destructive Laravel checks: `php artisan migrate:status`, `php artisan route:list --except-vendor`, and targeted `php artisan db:table ...`.

## Executive Summary

The Laravel application is currently a foundation migration target, not a complete ERP. It has a working Laravel/Inertia shell, auth, global RBAC catalog, translatable foundation records, Money and Accounting invariant helpers, concurrency-safe numbering allocation, idempotency storage, token garbage collection, audit append service, notification targeting/dedupe, and attachment storage. It does not yet contain implemented Sales, Purchasing, Inventory, GL posting, Payroll, or most operational business modules.

The latest Company / Branch / User correction is reflected in the schema for the main unsupported tenant assumptions:

- No `company_user` table.
- No `branch.company_id`.
- No `number_sequence.company_id`.
- No `number_sequence.include_branch`.
- No `audit_log.company_id` or `audit_log.branch_id`.
- No `attachment.company_id`.
- No `notification.company_id`.
- No `fiscal_year.company_id`; fiscal years are global by `year`.
- Spatie teams are disabled.
- No `currentCompany` or `currentBranch` context was found in Laravel code.

The previous business-model concern, `fiscal_year.company_id`, has been resolved by owner decision as `SINGLE-ERP CONTEXT`: FiscalYear is global to this ERP installation/business profile and not Company/Tenant owned.

## Severity Summary

### Critical

No active critical runtime defect was confirmed in the implemented foundation during this review. The largest risks are high severity because they can lead to wrong architecture or overbroad access if left in place.

### High

1. POST-AUDIT CORRECTED for current docs: `README.md`, `spec/DATABASE_DESIGN.md`, `spec/SECURITY.md`, and `spec/MASTER_ERP_SPEC.md` now carry the no-tenant/no-assumed-company-scope rule. Historical files can still mention old behavior when labeled as legacy.
2. POST-AUDIT CORRECTED: attachment routes now use an allowlisted entity authorization boundary and deny unknown/missing/unauthorized entities by default.
3. POST-AUDIT CORRECTED: the implicit settings bootstrap fallback was removed; empty RBAC assignments no longer grant management access.
4. POST-AUDIT CORRECTED: `fiscal_year.company_id` was removed; no multi-company fiscal calendars are inferred.

### Medium

1. POST-AUDIT CORRECTED: `COMPANY_ADMIN` was renamed to `ERP_ADMIN` as a global role template.
2. `number_sequence` is concurrency-safe by global `key`, but the business identity and reset dimensions are not fully defined. The allocator increments `key`; yearly reset/output formatting are not a complete document-numbering engine yet.
3. Company and Branch settings pages are implemented as standalone configuration screens, but Branch exact business semantics remain undefined by requirements.
4. Audit is append-only by service convention. There is no database-level rule preventing direct updates/deletes of `audit_log`.
5. Attachment upload writes the file before inserting the DB row. A DB failure after storage write can leave an orphan file.
6. Most RBAC enforcement is route/action-level and permission-string based. Full module policies do not exist yet because the modules are not implemented.
7. UI contains some hardcoded English/security-marketing strings and dev credential quick-fill; this should be gated or removed for production.

### Low

1. Historical Next.js docs and module README files remain useful as archive/context but are stale for Laravel truth.
2. Some generated/spec documents describe future ERP behavior that is not implemented.
3. The dashboard text can overstate accounting completeness unless it is kept clearly as foundation/invariant status.

## Current Laravel Architecture

The target is Laravel 13.26.1 with Inertia React 3, Vite, Tailwind 4, PostgreSQL, `spatie/laravel-permission`, and `spatie/laravel-translatable`.

Current route surface from `php artisan route:list --except-vendor`:

- Auth: `GET login`, `POST login`, `POST logout`.
- Locale: `POST locale`.
- Health/foundation: `/`, `health`, `foundation`.
- Read pages: `dashboard`, `settings`, `settings/company`, `settings/branches`, `settings/numbering`, `settings/users`, `notifications`.
- Mutations: company create/update, branch create/update, numbering create/update, assign/revoke roles, mark notification read, upload/download attachments.

No Laravel route was found for Sales, Purchasing, Inventory, Accounting posting, Payroll, Reports, or other full ERP module transactions.

## Domain Logic Review

### Money

Status: IMPLEMENTED FOUNDATION.

`App\Domain\Money\Money` stores minor units, parses major strings using a currency registry, rejects excess decimal precision, supports same-currency arithmetic, formatting, and allocation. Tests exist under `tests/Invariants/MoneyInvariantTest.php`.

Limit: this is a value-object kernel, not a full financial document or ledger implementation.

### Currency Registry

Status: IMPLEMENTED FOUNDATION.

`CurrencyRegistry` is backed by `config/erp_currencies.php`. Currency names use translatable foundation models. Exchange-rate storage exists in schema, but no complete exchange-rate business workflow was confirmed.

### Accounting Invariant Kernel

Status: IMPLEMENTED FOUNDATION.

`AccountingKernel` validates draft entry lines:

- No negative debit/credit amounts.
- A line cannot have both debit and credit.
- A line cannot have neither debit nor credit.
- Sum of debit minor units must equal sum of credit minor units.
- Posting idempotency key helper exists.

Limit: there are no journal entry tables, posting services, reversal workflow, subledger posting, period close enforcement, or GL reports implemented in Laravel yet.

### Number Formatting / Config

Status: PARTIALLY_IMPLEMENTED.

Money formatting exists. Locale dictionaries exist. Full ERP-level numeric, quantity, tax, and document-number display configuration is not complete.

### Domain Errors

Status: PARTIALLY_IMPLEMENTED.

Domain error classes exist for accounting and money invariants. There is not yet a broad module-level error taxonomy for ERP workflows.

## Relationship Review

The current implementation now mostly honors the latest correction:

- Company is not treated as tenant.
- Users do not belong to companies.
- Branches do not belong to companies.
- Roles and permissions are not company-scoped.
- Notifications target users, not companies.
- Attachments link to entity references, not companies.
- Audit records actor and entity/event, not company.

Unresolved:

- FiscalYear is global to the single ERP context; `fiscal_year.company_id` is removed.
- Branch exact model remains undefined. Current standalone Branch screen and table should not be treated as a security boundary.
- Role template name `COMPANY_ADMIN` was renamed to `ERP_ADMIN` after this audit.

See `DOMAIN_RELATIONSHIP_AUDIT.md` for the relationship matrix.

## Schema Review

Verified with `php artisan db:table`:

- `branch`: `id`, `code`, `name`, `is_active`, `lock_version`; primary key only. No `company_id`.
- `number_sequence`: `id`, `key`, `doc_type`, `prefix`, `include_year`, `padding`, `reset_policy`, `next_value`; unique `key`. No `company_id` or `include_branch`.
- `audit_log`: actor/entity/event payload fields; indexes on `(entity_type, entity_id)` and `(actor_id, at)`. No company/branch fields.
- `attachment`: entity reference, file metadata, `uploaded_by`; index on `(entity_type, entity_id)`. No company field.
- `notification`: `user_id`, type, target ref, read flag, dedupe key; indexes on `(user_id, read)` and unique `(user_id, dedupe_key)`. No company field.
- `fiscal_year`: `id`, `year`, `start_date`, `end_date`, `status`; unique `year`; no `company_id`.

See `SCHEMA_ASSUMPTION_AUDIT.md` for the full schema assumption review.

## Concurrency Review

| Operation | Current protection | Status | Notes |
| --- | --- | --- | --- |
| Number allocation | PostgreSQL `INSERT ... ON CONFLICT (key) DO UPDATE ... RETURNING`; portable transaction fallback | IMPLEMENTED | Stress command exists and previous run passed. |
| Idempotent operations | Unique `(operation, key_hash, key_scope)` with `insertOrIgnore` claim and replay | IMPLEMENTED | Good foundation; not yet wired into every mutation. |
| Company update | `lock_version` optimistic concurrency | IMPLEMENTED | Audit is recorded. |
| Branch update | `lock_version` optimistic concurrency | IMPLEMENTED | Branch model semantics remain undefined. |
| Numbering settings update | Unique `key` and duplicate check | PARTIAL | Race can fall to DB unique error instead of polished validation. No `lock_version`. |
| Role assign/revoke | Spatie pivot behavior | PARTIAL | No explicit idempotency/audit on role changes was confirmed. |
| Attachment upload | None beyond request validation | PARTIAL | File write before DB insert can orphan stored file if DB insert fails. |
| Notification create | Per-user dedupe unique index | IMPLEMENTED FOUNDATION | Mark-read is scoped by current user. |
| Token cleanup | Bounded batch deletes and scheduler `withoutOverlapping()` | IMPLEMENTED FOUNDATION | Command is `tokens:gc --batch=500`, scheduled hourly. |

## Token And Garbage Collection Review

`AuthGarbageCollector` deletes expired sessions, password reset tokens, and idempotency keys in bounded batches. `routes/console.php` schedules `tokens:gc --batch=500` hourly with `withoutOverlapping()`.

Status: IMPLEMENTED FOUNDATION.

Residual risk: production scheduler/queue runtime still needs deployment verification outside code review.

## RBAC Review

Status: IMPLEMENTED FOUNDATION.

Confirmed:

- Spatie teams disabled in `config/permission.php`.
- `roles` has global unique `(name, guard_name)`.
- `permissions` has unique `(name, guard_name)` and `(module, action)`.
- `model_has_roles`, `model_has_permissions`, and `role_has_permissions` include nullable `scope_json`, but no company/team foreign key.
- `RbacSeeder` builds a module/action permission catalog and global role templates.

Risks:

- `ERP_ADMIN` is now the neutral global admin template name; old `COMPANY_ADMIN` references are historical or migration-only.
- `scope_json` has no confirmed business semantics yet.
- Settings actions now deny when the user lacks the required permission; the empty-RBAC bypass was removed after this audit.
- Full entity policies are not present for future modules.

## Auth And Security Review

Implemented:

- Session guard.
- Login rate limiting by email/IP.
- Password hashing through Laravel hashed cast.
- `is_active` login block.
- Session regeneration on login and invalidation on logout.
- Shared Inertia auth payload limits user data to id/name/email/locale/theme and permission strings.

Risks:

- Dev quick-fill credentials appear in the login UI.
- Some hardcoded strings describe security capabilities; production copy should avoid overstating security posture.
- No MFA workflow was confirmed despite `mfa_enabled` field.
- Attachment authorization is incomplete.
- Bootstrap authorization fallback must be controlled before production.

## Inertia / React Review

Status: IMPLEMENTED FOUNDATION.

Pages exist for login, dashboard, foundation, notifications, and settings pages. Locale dictionaries exist for English and Arabic, and RTL support exists through shared locale/direction props.

Risks:

- Some strings remain hardcoded outside dictionaries.
- Some UI claims like "Balanced Kernel" should stay scoped to invariant checks, not full accounting implementation.
- Migrated pages are read/action foundation pages; they are not full ERP modules.

## Test Audit

Current Laravel tests found:

- `tests/Invariants/MoneyInvariantTest.php`
- `tests/Invariants/AccountingKernelInvariantTest.php`
- `tests/Invariants/NumberingInvariantTest.php`
- `tests/Concurrency/ConcurrencyFoundationTest.php`
- `tests/Feature/AuthenticationTest.php`
- `tests/Feature/AttachmentAndNotificationTest.php`
- `tests/Feature/HealthCheckTest.php`
- `tests/Feature/InertiaFoundationTest.php`
- `tests/Feature/MigratedPagesTest.php`
- `tests/Feature/SettingsActionsTest.php`
- `tests/Integration/AuthSchemaTest.php`
- `tests/Integration/FoundationSchemaTest.php`
- `tests/Integration/FoundationSeederTest.php`
- `tests/Unit/AuditAndJobsTest.php`
- `tests/Unit/DirectoryStructureTest.php`
- `tests/Unit/SpatiePermissionFoundationTest.php`

Latest known result from the immediately preceding correction pass: `php artisan test` passed with 50 tests and 771 assertions. This review pass did not rerun the full suite after creating the audit reports; it ran non-destructive inspection commands only.

Coverage gaps:

- No end-to-end browser tests for Laravel UI were confirmed.
- No production policy tests for attachments by entity type.
- No tests for fiscal-year/company relationship validity against owner requirements.
- No full accounting posting tests because posting is not implemented.
- No tests for production removal/gating of bootstrap settings fallback.

## Documentation Contradiction Summary

The largest contradiction is docs that still assert:

- tenant isolation,
- every query scoped by `company_id`,
- company/branch security boundaries,
- company-owned roles/permissions,
- branch belongs to company,
- document numbering unique per company.

These are not allowed under the latest owner correction unless original requirements explicitly prove them. Treat affected docs as stale or legacy references. See `MD_DOCUMENTATION_AUDIT.md`.

## Recommended Correction Order

1. Mark stale/contradictory docs so they cannot be used as source of truth.
2. POST-AUDIT CORRECTED: resolve `fiscal_year.company_id` by removing it for single-ERP fiscal years.
3. POST-AUDIT CORRECTED: rename `COMPANY_ADMIN` global role template to `ERP_ADMIN`.
4. POST-AUDIT CORRECTED: remove the bootstrap settings fallback.
5. POST-AUDIT CORRECTED: add attachment entity authorization before exposing real business attachments.
6. Define document-number sequence dimensions and reset behavior from requirements.
7. Add audit DB hardening if append-only must be enforced below the service layer.
8. Only after those corrections, continue module migrations.
