# Concurrency Audit - Laravel Migration

Date: 2026-08-21

## Scope

This audit covers the current Laravel + Inertia migration state. The Laravel target is not a multi-tenant SaaS and does not infer Company/Branch/User ownership relationships without explicit original requirements.

## Current Laravel State

- Authentication uses Laravel session authentication, database sessions by default, CSRF, login throttling, session regeneration on login, and session invalidation on logout.
- Spatie Permission is enabled with teams disabled.
- Settings mutations exist for company configuration, standalone branch references, document numbering, and role assign/revoke.
- Attachments and notifications have Laravel service layers.
- Audit logging is append-only and linked to actor plus audited entity/event, without an invented company/branch scope.
- PostgreSQL remains the production authority. PHPUnit defaults to SQLite in-memory for fast tests.

## Operation Matrix

| Operation | Current race-condition risk | Transaction/locking strategy | Constraints / idempotency | Expected behavior | Coverage |
|---|---|---|---|---|---|
| Login/logout | Low | Laravel guard/session internals | `users.email` unique; rate limiter | Duplicate logout harmless; failed login throttled | `AuthenticationTest` |
| Locale update | Medium lost-update risk | Single user update | `users.locale` PostgreSQL check | Last committed preference wins until profile locking exists | Feature coverage indirect |
| Seeders | Medium if concurrent | Unique constraints backstop `updateOrCreate` | currency, roles, permissions unique keys | One concurrent seeder may hit integrity error; rerun is safe | `FoundationSeederTest` |
| Company settings update | Medium | Optimistic compare-and-swap on `lock_version` | `company.id` primary key | One stale update is rejected | `SettingsActionsTest`, `ConcurrencyFoundationTest` |
| Branch reference update | Medium | Optimistic compare-and-swap on `lock_version` | `branch.id` primary key; no assumed `company_id` or company-code uniqueness | Branch updates do not assume Company ownership | `SettingsActionsTest`, `FoundationSchemaTest` |
| Number sequence allocation | High if read-max/write-next | PostgreSQL `INSERT ... ON CONFLICT (key) DO UPDATE RETURNING` serializes the sequence row | `number_sequence.key` unique | Concurrent allocators receive unique increasing values | `ConcurrencyFoundationTest`, `concurrency:stress` |
| Idempotent side-effect operation | High for future posting/payment/inventory/jobs | `DatabaseIdempotencyStore` creates/checks key in short transactions | unique `(operation, key_hash, key_scope)` | Same operation/key cannot execute callback twice | `ConcurrencyFoundationTest`, `AuditAndJobsTest`, `concurrency:stress` |
| Optimistic update | Medium | `WHERE id = ? AND lock_version = ?` | `lock_version` columns | One update succeeds; stale update receives conflict | `ConcurrencyFoundationTest` |
| Token/session cleanup | Medium database growth | Bounded deletes with expiry predicates | `sessions.last_activity`, `password_reset_tokens.created_at`, `idempotency_keys.expires_at/status` indexes | Safe to rerun; predicates protect active rows | `ConcurrencyFoundationTest`, `tokens:gc` |
| Notifications | Medium duplicate logical notifications | `insertOrIgnore` when dedupe key is supplied | unique per `(user_id, dedupe_key)` where dedupe key is not null | Same user receives one logical notification per dedupe key | `AttachmentAndNotificationTest`, `ConcurrencyFoundationTest` |
| Attachments | Medium storage/metadata consistency | Local storage write with failure compensation around metadata/audit persistence; explicit allowlisted entity authorization | `attachment.id` primary key, entity lookup index | Attachment metadata remains linked to `entity_type/entity_id`, not company scope; unknown entities deny by default | `AttachmentAndNotificationTest` |
| Audit logging | Low append race; high integrity importance | Append-only insert | `audit_log.id` primary key; entity and actor indexes | Audit keeps actor/entity/action/before-after/redaction without guessed org scope | `AuditAndJobsTest` |
| Fiscal year / financial period setup | Medium close/post race when posting is implemented | Future period-close operations must lock the financial period row | `fiscal_year.year` global unique; `financial_period(fiscal_year_id, month)` unique | Fiscal years are global to the single ERP context; periods belong to fiscal years | `FoundationSchemaTest` |
| Accounting posting | Critical, not yet implemented | Must be one short transaction | Future journal/source unique constraints and balanced-entry checks | Same logical event posts once; period close/post race cannot invalidate data | Pending Phase 2 |

## Explicit Lock Ordering For Future Posting

When the posting engine is ported, locks must be acquired in this order:

1. Explicit business context row only if the original requirement and current operation require one.
2. Fiscal period.
3. Source document.
4. Number sequence row.
5. Journal/subledger/ledger rows.
6. Notification/audit side effects.

No operation should acquire these in reverse order.

## Database Constraints Reviewed

- `users.email` unique.
- `password_reset_tokens.email` primary key.
- `sessions.id` primary key and `sessions.last_activity` index.
- `cache.key` and `cache_locks.key` primary keys.
- `jobs.queue` index; `failed_jobs.uuid` unique.
- `currency.code` primary key.
- `branch.id` primary key; no assumed `branch.company_id`.
- `exchange_rate(currency, date)` unique.
- `fiscal_year.year` unique globally; FiscalYear has no Company/Tenant scope.
- `financial_period(fiscal_year_id, month)` unique.
- `number_sequence.key` unique; no company or branch dimension.
- `audit_log(entity_type, entity_id)` and `audit_log(actor_id, at)` indexes.
- `attachment(entity_type, entity_id)` index.
- `notification(user_id, read)` index.
- `notification(user_id, dedupe_key)` unique when `dedupe_key` is not null.
- Spatie permission names and role names are unique by guard; Spatie teams are disabled.

## Removed Unsupported Assumptions

- `company_user(company_id, user_id)`.
- `branch(company_id, code)` uniqueness and Company/Branch FK.
- `number_sequence(company_id, key)`.
- `number_sequence.include_branch`.
- `fiscal_year.company_id`.
- `audit_log.company_id` and `audit_log.branch_id`.
- `attachment.company_id`.
- `notification.company_id`.

## Hardening In Place

- `idempotency_keys` table and unique operation/key/scope constraint.
- `lock_version` columns for optimistic concurrency on editable foundation records.
- Password reset token `created_at` index for deterministic cleanup.
- Notification dedupe key with per-user unique protection.
- `IdempotencyStore` for one-owner side-effect execution.
- `NumberSequenceAllocator` with PostgreSQL atomic `INSERT ... ON CONFLICT ... DO UPDATE RETURNING`.
- `OptimisticLock` helper and localized conflict messages.
- `AuthGarbageCollector` and `tokens:gc` command for bounded cleanup.
- `concurrency:stress` command for repeatable PostgreSQL stress checks.

## Known Gaps

- Laravel accounting posting is not implemented yet; idempotency primitives are ready but posting is not complete.
- Laravel queue business jobs are not implemented yet; all future side-effect jobs must use idempotency and at-least-once-safe handlers.
- Attachment authorization now uses an explicit allowlisted entity registry for foundation entities and denies unknown entity types by default. Future business modules must register their own entity authorization before attachments are exposed for those records.
- CI is not configured in this local migration track. Run `php artisan test --testsuite=Concurrency` and `php artisan concurrency:stress --workers=100` manually against PostgreSQL until CI is introduced.
