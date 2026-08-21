# Concurrency Audit - Laravel Migration

Date: 2026-08-21

## Scope

This audit covers the current Laravel + Inertia migration state and uses the existing Next.js implementation only as a reference for engines that have not been ported yet. The Laravel target is not a multi-tenant SaaS; company and branch are ERP business scopes only where the specification explicitly requires them.

## Current Laravel State

- Authentication uses Laravel session authentication, database sessions by default, CSRF, login throttling, session regeneration on login, and session invalidation on logout.
- No Sanctum, Passport, OAuth, refresh-token, or personal-access-token storage exists.
- PostgreSQL remains the production authority. PHPUnit defaults to SQLite in-memory for fast feature/integration tests.
- Accounting posting, inventory posting, payment allocation, attachment routes, notification routes, and queued business jobs are not yet implemented in Laravel.
- Current mutating Laravel endpoints are login, logout, and locale preference update.
- Current mutating non-request code is database migration/seeding.

## Operation Matrix

| Operation | Current race-condition risk | Transaction boundary | Locking strategy | Unique/check constraints | Idempotency strategy | Retry strategy | Expected concurrent behavior | Test coverage |
|---|---|---|---|---|---|---|---|---|
| Login | Low. Duplicate login attempts can create/rotate sessions independently. Rate limiting must be atomic. | Laravel guard/session internals. | Laravel session write lock/handler and cache-backed rate limiter. | `users.email` unique; active user filter. | Not required; login has no irreversible ERP side effect. | Laravel request retry only; no custom retry. | Multiple valid attempts authenticate; failed attempts are throttled. | `AuthenticationTest`; audit notes rate limiter relies on Laravel atomic cache store. |
| Logout | Low. Duplicate logout should be harmless. | Request-local session invalidation. | Session handler. | `sessions.id` primary key. | Naturally idempotent. | None. | Repeated logout leaves user unauthenticated. | `AuthenticationTest`. |
| Locale update | Medium lost-update risk for concurrent preference edits. | Single request update; no explicit transaction. | None yet. | `users.locale` check constraint on PostgreSQL. | Not required; latest preference wins today. | None. | Last committed locale wins. Optimistic locking should be used once the profile/settings screen is ported. | Existing route coverage indirect only. |
| Currency/RBAC seeders | Medium if two operators seed at the same time; current `updateOrCreate` is not fully atomic. | Per ORM statement; no broad transaction. | Database unique constraints backstop duplicates. | `currency.code`, `permissions.name+guard`, `roles.name+guard`, `permissions.module+action`. | Seeder reruns are intended to be idempotent by key. | Manual rerun. | Unique constraints prevent duplicate catalog rows; one concurrent seeder may receive an integrity error. | `FoundationSeederTest`. |
| Company create via tests/seeds | Low in current runtime because no Laravel endpoint exists. | Caller-defined. | None. | `company.id` primary key. | Not applicable yet. | None. | Duplicate UUID is rejected by DB. | `FoundationSchemaTest`. |
| Branch create | Medium if concurrent creates use the same company/code. | Caller-defined. | None. | `branch(company_id, code)` unique. | Not implemented as endpoint yet. | None. | Exactly one branch with the same code can exist per company. | `FoundationSchemaTest`. |
| Company membership insert | Medium until domain access model is specified. | Caller-defined. | None. | `company_user(company_id, user_id)` primary key. | Not implemented as endpoint yet. | None. | Duplicate membership is rejected by DB. | `FoundationSchemaTest`. |
| Number sequence allocation | High if implemented with read-max/write-next. | New Laravel allocator uses one DB statement for PostgreSQL. | PostgreSQL `INSERT ... ON CONFLICT ... DO UPDATE RETURNING` serializes the conflicting row. | `number_sequence(company_id, key)` unique. | Not required for bare allocation; financial document creation must combine allocation with operation-level idempotency. | Safe bounded retry can be wrapped around callers for deadlock/serialization failures. | Concurrent allocators receive unique increasing values. | New concurrency tests and `concurrency:stress`. |
| Idempotent side-effect operation | High for future posting/payment/inventory/jobs. | New `IdempotencyStore` creates/checks key in short transactions. | Unique `(operation, key_hash, key_scope)`. | `idempotency_keys` unique + status check. | New `IdempotencyStore::run` allows one owner; duplicates return completed response or deterministic in-progress conflict. | Caller may retry failed transient DB errors only with the same idempotency key. | Same operation/key cannot execute the callback twice concurrently. | New concurrency tests and `concurrency:stress`. |
| Optimistic update | Medium for editable business records once settings screens are ported. | New helper performs compare-and-swap update. | `WHERE id = ? AND lock_version = ?`. | `lock_version` columns on editable foundation tables. | Not idempotency; prevents lost updates. | User reload required on conflict. | One update succeeds, stale update receives `ConcurrencyConflict`. | New concurrency tests. |
| Session/password-reset/idempotency cleanup | Medium database growth and stale auth records. | New GC deletes in bounded batches. | Delete predicates repeat the expiry condition; no long table locks. | `sessions.last_activity`, `password_reset_tokens.created_at`, `idempotency_keys.expires_at/status` indexes. | GC is idempotent. | Safe to rerun. | Concurrent GC workers may overlap but cannot delete refreshed active rows because delete predicates re-check expiry. | New concurrency tests; `tokens:gc`. |
| Queue jobs | High for at-least-once delivery once business jobs exist. | Laravel DB queue table exists; no business jobs yet. | Laravel queue reservation fields. | `failed_jobs.uuid` unique. | Future side-effect jobs must use `idempotency_keys` or unique jobs. | Bounded retry only for idempotent jobs. | Duplicate delivery must be a no-op or deterministic conflict. | Documented; no Laravel business job exists yet. |
| Notifications | Medium duplicate logical notifications once event hooks exist. | Not implemented as Laravel service yet. | Future insert should use deterministic `dedupe_key`. | New nullable `notification.dedupe_key` plus unique partial index where supported. | Dedupe key per logical event. | Safe retry by same dedupe key. | Same logical notification can be inserted once when dedupe key is supplied. | Schema-level audit; service tests pending when notification service is ported. |
| Attachments | High for upload/delete once routes are ported. | Not implemented in Laravel yet. | Future DB-first metadata + storage consistency strategy required. | Existing `attachment.id` primary key and lookup indexes. | Future uploads that create irreversible storage objects should use `idempotency_keys`. | Retry only after metadata/storage reconciliation rules are implemented. | Browser file IDs must be authorized server-side. | Documented; no Laravel attachment route exists yet. |
| Accounting posting | Critical, not yet implemented in Laravel. | Must be one short transaction. | Lock period/document rows in deterministic order. | Future journal/source unique constraints and balanced-entry checks. | Mandatory idempotency by logical source/action. | Retry only for safe transient DB failures with same key. | Same logical event posts once; period close/post race cannot produce invalid state. | Pending Phase 2. |

## Explicit Lock Ordering

When the posting engine is ported, locks must be acquired in this order:

1. Company/business context row if the operation explicitly needs one.
2. Fiscal period.
3. Source document.
4. Number sequence row.
5. Journal/subledger/ledger rows.
6. Notification/audit side effects.

No operation should acquire these in the reverse order.

## Database Constraints Reviewed

- `users.email` unique.
- `password_reset_tokens.email` primary key.
- `sessions.id` primary key and `sessions.last_activity` index.
- `cache.key` and `cache_locks.key` primary keys.
- `jobs.queue` index; `failed_jobs.uuid` unique.
- `currency.code` primary key.
- `branch(company_id, code)` unique.
- `company_user(company_id, user_id)` primary key.
- `exchange_rate(currency, date)` unique.
- `fiscal_year(company_id, year)` unique.
- `financial_period(fiscal_year_id, month)` unique.
- `number_sequence(company_id, key)` unique.
- `audit_log(company_id, entity_type, entity_id)` and `audit_log(company_id, at)` indexes.
- `attachment(company_id, entity_type, entity_id)` index.
- `notification(company_id, user_id, read)` index.
- Spatie permission names and role names are unique by guard; Spatie teams are disabled.

## New Hardening Added By This Increment

- `idempotency_keys` table and unique operation/key/scope constraint.
- `lock_version` columns for optimistic concurrency on `company` and `branch`.
- Password reset token `created_at` index for deterministic cleanup.
- Notification dedupe key and unique partial index where the database supports it.
- `IdempotencyStore` for one-owner side-effect execution.
- `NumberSequenceAllocator` with PostgreSQL atomic `INSERT ... ON CONFLICT ... DO UPDATE RETURNING`.
- `OptimisticLock` helper and localized conflict messages.
- `AuthGarbageCollector` and `tokens:gc` command for bounded session/password-reset/idempotency cleanup.
- `concurrency:stress` command for repeatable PostgreSQL stress checks.

## Known Gaps

- Laravel accounting posting is not implemented yet; idempotency primitives are ready but posting is not complete.
- Laravel queue business jobs are not implemented yet; all future side-effect jobs must use idempotency and at-least-once-safe handlers.
- Laravel attachment and notification application services are not yet ported; schema and audit guidance are prepared.
- CI is not configured in this local migration track. Run `php artisan test --testsuite=Concurrency` and `php artisan concurrency:stress` manually against PostgreSQL until CI is introduced.

## Verification

- `php artisan migrate --force` applied `2026_08_21_060000_add_concurrency_hardening_foundation`.
- `php artisan test --testsuite=Concurrency` passed 7 tests / 16 assertions.
- `php artisan test` passed 26 tests / 131 assertions.
- `php artisan concurrency:stress --workers=100` passed on PostgreSQL; sequence values were unique and contiguous, and the idempotency callback executed exactly once.
- `php artisan tokens:gc --batch=100` completed successfully with no expired rows to delete in the local database.
