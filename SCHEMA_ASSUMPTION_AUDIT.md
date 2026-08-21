# Schema Assumption Audit

Date: 2026-08-21

Scope: current Laravel database schema assumptions, with focus on unsupported Company / Branch / User scoping and related audit, attachment, notification, numbering, RBAC, and concurrency tables.

Post-audit correction note: after this audit, the misleading global `COMPANY_ADMIN` role template was renamed to `ERP_ADMIN`, settings management now denies empty RBAC assignments, and attachment authorization/cleanup were hardened. `fiscal_year.company_id` remains OWNER DECISION REQUIRED.

Verification commands used:

- `php artisan migrate:status`
- `php artisan route:list --except-vendor`
- `php artisan db:table branch`
- `php artisan db:table number_sequence`
- `php artisan db:table audit_log`
- `php artisan db:table attachment`
- `php artisan db:table notification`
- `php artisan db:table fiscal_year`
- `php artisan db:table company`
- `php artisan db:table financial_period`
- `php artisan db:table roles`
- `php artisan db:table permissions`
- `php artisan db:table model_has_roles`
- `php artisan db:table model_has_permissions`
- `php artisan db:table role_has_permissions`
- `php artisan db:table idempotency_keys`

## Applied Migrations

`php artisan migrate:status` reports all current migrations ran:

- `0001_01_01_000000_create_users_table`
- `0001_01_01_000001_create_cache_table`
- `0001_01_01_000002_create_jobs_table`
- `2026_08_21_031000_create_phase1_foundation_tables`
- `2026_08_21_031321_create_permission_tables`
- `2026_08_21_040000_add_authentication_fields_to_users_table`
- `2026_08_21_041000_extend_permission_tables_for_domain_rbac`
- `2026_08_21_050000_remove_spatie_team_scope_from_permission_tables`
- `2026_08_21_060000_add_concurrency_hardening_foundation`
- `2026_08_21_070000_remove_unsupported_company_branch_scope_assumptions`

## Unsupported Assumptions Removed Or Absent

| Assumption | Current schema status | Classification |
| --- | --- | --- |
| `company_user` pivot | Table absent; schema test asserts absence. | REMOVED LEGACY/AI ASSUMPTION |
| `users.company_id` | Column absent. | UNDEFINED - DO NOT ASSUME |
| `branch.company_id` | Column absent. | REMOVED LEGACY/AI ASSUMPTION |
| Company -> Branch FK | Absent. | UNDEFINED - DO NOT ASSUME |
| `number_sequence.company_id` | Column absent. | REMOVED LEGACY/AI ASSUMPTION |
| `number_sequence.include_branch` | Column absent. | REMOVED LEGACY/AI ASSUMPTION |
| `audit_log.company_id` | Column absent. | REMOVED LEGACY/AI ASSUMPTION |
| `audit_log.branch_id` | Column absent. | REMOVED LEGACY/AI ASSUMPTION |
| `attachment.company_id` | Column absent. | REMOVED LEGACY/AI ASSUMPTION |
| `notification.company_id` | Column absent. | REMOVED LEGACY/AI ASSUMPTION |
| `roles.company_id` | Column absent. | REMOVED LEGACY/AI ASSUMPTION |
| Spatie team foreign key | Not active; `permission.teams` false. | REMOVED |
| `currentCompany` / `currentBranch` context | Not found in Laravel code. | REMOVED / ABSENT |

## Remaining `company_id` / `branch_id`

Runtime schema:

| Column | Current justification | Classification |
| --- | --- | --- |
| `fiscal_year.company_id` | Current implementation ties fiscal years to `company`, with unique `(company_id, year)` and FK restrict delete. Original owner correction does not prove Company ownership of fiscal calendars. | NEEDS_OWNER_DECISION |

No runtime `branch_id` column was confirmed in the audited Laravel foundation tables.

Non-runtime references remain in:

- cleanup migrations that drop old unsupported columns if present;
- tests asserting unsupported columns are absent;
- stale docs and historical changelog/status files.

These references are not active business schema.

## Table Review

### `company`

Columns verified:

- `id` uuid primary key
- `name` jsonb
- `base_currency` char(3), default `EGP`
- `settings_json` jsonb nullable
- timestamps
- `lock_version`

Assumption review:

- Safe as configurable company profile.
- Does not prove multi-company tenancy.
- No relationship to users, branches, roles, or permissions should be inferred.

### `branch`

Columns verified:

- `id` uuid primary key
- `code`
- `name` jsonb
- `is_active`
- `lock_version`

Indexes:

- primary key only

Assumption review:

- No `company_id`.
- No company relationship.
- No unique code constraint.
- Exact branch semantics remain undefined.

### `fiscal_year`

Columns verified:

- `id` uuid primary key
- `company_id` uuid
- `year`
- `start_date`
- `end_date`
- `status`

Indexes and FKs:

- unique `(company_id, year)`
- FK `company_id` -> `company.id`, restrict on delete

Assumption review:

- This is the only active schema column still assuming Company owns another business entity.
- It may be valid later, but current evidence is insufficient.
- Do not build period close/posting workflows on this relationship until owner confirms it.

### `financial_period`

Columns verified:

- `id` uuid primary key
- `fiscal_year_id`
- `month`
- `start_date`
- `end_date`
- `status`

Indexes and FKs:

- unique `(fiscal_year_id, month)`
- FK `fiscal_year_id` -> `fiscal_year.id`, cascade on delete

Assumption review:

- Internal fiscal-year relationship is implemented.
- Validity depends on resolving fiscal-year ownership.

### `number_sequence`

Columns verified:

- `id` uuid primary key
- `key`
- `doc_type`
- `prefix`
- `include_year`
- `padding`
- `reset_policy`
- `next_value`

Indexes:

- unique `key`

Assumption review:

- No company dimension.
- No branch dimension.
- Concurrency-safe allocation by global `key` is implemented.
- Business sequence dimensions and reset behavior still need owner confirmation.

### `audit_log`

Columns verified:

- `id` uuid primary key
- `actor_id` nullable
- `action`
- `entity_type`
- `entity_id`
- `before_json` nullable
- `after_json` nullable
- `reason` nullable
- `request_id` nullable
- `ip` nullable
- `device` nullable
- `at`

Indexes and FKs:

- index `(entity_type, entity_id)`
- index `(actor_id, at)`
- FK `actor_id` -> `users.id`, set null on delete

Assumption review:

- No company/branch scope.
- Actor and audited entity/event are preserved.
- Append-only is enforced by service convention, not by DB trigger/rule.

### `attachment`

Columns verified:

- `id` uuid primary key
- `entity_type`
- `entity_id`
- `file_ref`
- `name`
- `mime`
- `size`
- `uploaded_by` nullable
- `at`

Indexes and FKs:

- index `(entity_type, entity_id)`
- FK `uploaded_by` -> `users.id`, set null on delete

Assumption review:

- No company scope.
- Generic entity reference is retained.
- Authorization must come from the referenced entity policy, which is not implemented yet.

### `notification`

Columns verified:

- `id` uuid primary key
- `user_id`
- `type`
- `target_ref`
- `read`
- `at`
- `dedupe_key` nullable

Indexes and FKs:

- index `(user_id, read)`
- unique `(user_id, dedupe_key)`
- FK `user_id` -> `users.id`, cascade on delete

Assumption review:

- No company scope.
- Target user relationship is valid.
- Dedupe is per user, not global/company.

### RBAC Tables

`roles`:

- global unique `(name, guard_name)`
- `is_template`
- no `company_id`

`permissions`:

- unique `(name, guard_name)`
- unique `(module, action)`
- no `company_id`

`model_has_roles`, `model_has_permissions`, `role_has_permissions`:

- standard Spatie pivot structure
- nullable `scope_json`
- no active team/company FK

Assumption review:

- RBAC is confirmed.
- Company-scoped RBAC is removed.
- `scope_json` is a future extension point, not a confirmed company/branch scope.

### `idempotency_keys`

Columns verified:

- `id`
- `operation`
- `key_hash`
- `key_scope`
- nullable `actor_id`
- nullable `request_hash`
- `status`
- response fields
- error field
- timestamps
- `expires_at`

Indexes and FKs:

- unique `(operation, key_hash, key_scope)`
- index `(status, expires_at)`
- index `expires_at`
- FK `actor_id` -> `users.id`, set null on delete

Assumption review:

- Global idempotency scope is explicit through `key_scope`, default `global`.
- No company/branch scope is present.

## Schema Diff From Unsupported Tenant Model

Expected current result of the correction pass:

- Delete/avoid `company_user`.
- Remove `branch.company_id`.
- Remove Company -> Branch relationship.
- Remove `number_sequence.company_id`.
- Remove `number_sequence.include_branch`.
- Preserve atomic numbering by `key`.
- Remove unsupported audit/attachment/notification company scope.
- Preserve audit actor/entity fields.
- Preserve attachment entity reference.
- Preserve notification user targeting and dedupe.
- Keep Spatie teams disabled.

The current audited schema matches those expected results except for the unresolved `fiscal_year.company_id`.

## Risks And Follow-Ups

1. Resolve `fiscal_year.company_id` before implementing posting, closing, reports, or accounting periods.
2. Decide Branch semantics before adding relationships, uniqueness, or authorization rules.
3. Decide document-number sequence identity before legal invoices or accounting documents.
4. Add entity-level authorization for attachments.
5. Consider DB-level audit immutability if append-only integrity must be protected from direct DB updates/deletes.
6. Consider soft-deleting users or actor snapshots if audit actor identity must survive user deletion.
7. Gate or remove settings authorization bootstrap fallback for production.
