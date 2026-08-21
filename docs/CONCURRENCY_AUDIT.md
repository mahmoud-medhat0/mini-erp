# Concurrency Audit - Laravel Migration

Date: 2026-08-21

## Scope

Current Laravel + Inertia + React migration state after M10 and Phase 3 Slices 1-5. The Laravel target is not a multi-tenant SaaS and does not infer Company/Branch/User ownership without explicit owner decisions.

## Current State

- Authentication uses Laravel sessions, CSRF, login throttling, session regeneration, and logout invalidation.
- Spatie Permission is enabled with teams disabled.
- Spatie Activitylog is the active audit backend.
- Settings mutations exist for company profile, standalone branch references, document numbering, and role assign/revoke.
- Phase 2 accounting posting, ledger, reversal, opening balances, and reports exist.
- Phase 3 master data, AR/AP subledgers, receipt/payment posting, allocation settlement, and cheque lifecycle foundations exist.
- Attachments and notifications have Laravel service layers.
- PostgreSQL is the production authority. PHPUnit defaults to SQLite in-memory for fast tests, with PostgreSQL stress commands for concurrency paths.

## Operation Matrix

| Operation | Current race-condition risk | Transaction/locking strategy | Constraints / idempotency | Expected behavior | Coverage |
|---|---|---|---|---|---|
| Login/logout | Low | Laravel guard/session internals | `users.email` unique; rate limiter | Duplicate logout harmless; failed login throttled | `AuthenticationTest` |
| Locale update | Medium lost-update risk | Single user update | `users.locale` PostgreSQL check | Last committed preference wins until profile locking exists | Feature coverage indirect |
| Seeders | Medium if concurrent | Unique constraints backstop `updateOrCreate` | currency, roles, permissions unique keys | Rerun is safe | `FoundationSeederTest` |
| Company settings update | Medium | Optimistic compare-and-swap on `lock_version` | `company.id` primary key | One stale update is rejected | `SettingsActionsTest`, `ConcurrencyFoundationTest` |
| Branch reference update | Medium | Optimistic compare-and-swap on `lock_version` | `branch.id` primary key; no company ownership | Branch updates do not assume Company ownership | `SettingsActionsTest`, `FoundationSchemaTest` |
| Number sequence allocation | High if read-max/write-next | PostgreSQL `INSERT ... ON CONFLICT (key) DO UPDATE RETURNING` | `number_sequence.key` unique | Concurrent allocators receive unique increasing values | `ConcurrencyFoundationTest`, `concurrency:stress` |
| Numbering settings update | Medium | DB transaction and row lock on update | immutable `key`, unique `key` | No duplicate/renamed sequence keys | `M8ActionsTest` |
| Idempotent side-effect operation | High | `DatabaseIdempotencyStore` creates/checks key in short transactions | unique `(operation, key_hash, key_scope)` | Same operation/key cannot execute callback twice | `ConcurrencyFoundationTest`, `AuditAndJobsTest`, `concurrency:stress` |
| Token/session cleanup | Medium database growth | Bounded deletes with expiry predicates | sessions/reset/idempotency expiry indexes | Safe to rerun; predicates protect active rows | `ConcurrencyFoundationTest`, `tokens:gc` |
| Notifications | Medium duplicate logical notifications | `insertOrIgnore` by dedupe key | unique per `(user_id, dedupe_key)` | Same user receives one logical notification per dedupe key | `M9AttachmentsAndNotificationsTest`, `ConcurrencyFoundationTest` |
| Attachments | Medium storage/metadata consistency | Local storage write with failure compensation around DB/audit persistence | entity lookup index; file path sanitization | Metadata remains linked to `entity_type/entity_id`; unknown entities deny | `M9AttachmentsAndNotificationsTest` |
| Audit logging | Low append race; high integrity importance | Spatie Activitylog append via `AuditLogger`; DB triggers block update/delete | `activity_log.id`; legacy `audit_log.id` | Audit keeps actor/entity/action/before-after/redaction without org scope | `M10AuditAndSchedulerTest`, `AuditAndJobsTest` |
| Ledger posting | Critical | Short DB transaction with period/journal locks and idempotency envelope | journal/ledger constraints; immutable ledger trigger | Same journal posts once; ledger derived from posted journal lines only | `AccountingCoreTest`, `accounting:concurrency-stress` |
| Reversal | Critical | Locks original journal and target period; idempotent reversal key | reversal links and duplicate guards | One reversal entry per original journal | `AccountingCoreTest`, `accounting:concurrency-stress` |
| Period close/reopen | High against posting | Period row locking | status checks and audit | Closed periods reject posting; race serializes on period row | `AccountingCoreTest`, accounting stress |
| Receipt/payment posting | Critical | Locks source receipt/payment and period; uses PostingEngine and IdempotencyStore | posted source links and status checks | Same receipt/payment posts once and preserves `allocated + unapplied = amount` | `Phase3Slice3ReceiptPaymentTest`, accounting stress |
| AR/AP allocation | Critical | Locks parent receipt/payment, target subledger rows, and active allocation rows in deterministic order | allocation amount checks and idempotency keys | Concurrent allocations cannot over-allocate AR/AP or make unapplied negative | `Phase3Slice4AllocationTest`, `accounting:allocation-concurrency-stress` |
| Cheque transitions | Critical | Locks cheque row, period, mapped accounts, and bank GL rows in service-defined order | idempotent transition keys and source journal links | Clear replay does not duplicate posting; clear vs bounce/return produces one terminal accounting effect | `Phase3Slice5ChequeTest`, `accounting:cheque-concurrency-stress` |

## Lock Ordering

Accounting posting/reversal should keep this order:

1. Financial period row.
2. Source journal/source document row.
3. Number sequence row if allocating a number.
4. Journal lines/accounts required for validation.
5. Insert-only ledger/activity rows.
6. Post-commit side effects.

Do not lock Company or Branch rows for accounting posting.

## Key Constraints Reviewed

- `users.email` unique.
- `currency.code` primary key.
- `branch.id` primary key; no `branch.company_id`.
- `fiscal_year.year` globally unique.
- `financial_period(fiscal_year_id, month)` unique.
- `number_sequence.key` unique.
- `activity_log` has no company/branch/tenant columns.
- legacy `audit_log` has no company/branch columns.
- `attachment(entity_type, entity_id)` index.
- `notification(user_id, dedupe_key)` unique when dedupe key is not null.
- `ledger_entry.journal_line_id` unique and immutable.
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
- Any `activity_log.company_id`, `activity_log.branch_id`, or `activity_log.tenant_id`.

## Hardening In Place

- `idempotency_keys` table and unique operation/key/scope constraint.
- Optimistic locks on editable foundation records.
- PostgreSQL atomic number allocation.
- Auth token garbage collection through `tokens:gc`.
- Notification dedupe key.
- Attachment storage cleanup compensation.
- Ledger-entry immutability trigger.
- Spatie Activitylog and legacy audit immutability triggers.
- `concurrency:stress` command.
- `accounting:concurrency-stress` command.
- `accounting:allocation-concurrency-stress` command.
- `accounting:cheque-concurrency-stress` command.

## Known Gaps

- Laravel queue business jobs beyond baseline are not implemented yet; all future jobs must be idempotent and at-least-once safe.
- Future business modules must register attachment authorization before exposing attachments for new records.
- Bank Reconciliation still needs PostgreSQL duplicate-match/finalize stress coverage when Slice 6 is implemented.
- CI is not configured in this local Laravel migration track. Run the verification commands manually until CI is introduced.
