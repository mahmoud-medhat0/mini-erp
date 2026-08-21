# Schema Assumption Audit

Date: 2026-08-21

Scope: current Laravel schema after M10. Focus: unsupported Company/Branch/User scoping, accounting Phase 2 tables, audit/activity logging, attachments, notifications, numbering, RBAC, and concurrency.

## Verification Snapshot

Latest full verification from `laravel/`:

- `php artisan migrate:status`: 24 migrations Ran.
- `php artisan test`: 145 tests / 1185 assertions passed.
- `php artisan test --testsuite=Concurrency`: 7 tests / 16 assertions passed.
- `php artisan concurrency:stress --workers=100`: passed.
- `php artisan accounting:concurrency-stress --workers=50`: passed.
- `npm run typecheck`: passed.
- `npm run build`: passed.

## Applied Migrations

Current migrations reported as Ran:

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
- `2026_08_21_080000_rename_company_admin_role_template`
- `2026_08_21_090000_remove_fiscal_year_company_scope`
- `2026_08_21_092749_create_activity_log_table`
- `2026_08_21_092750_add_event_column_to_activity_log_table`
- `2026_08_21_092751_add_batch_uuid_column_to_activity_log_table`
- `2026_08_21_100000_create_phase2_accounting_core_tables`
- `2026_08_21_110000_enforce_ledger_entry_immutability`
- `2026_08_21_120000_add_currency_foreign_key_constraints`
- `2026_08_21_130000_add_timestamps_to_exchange_rate_table`
- `2026_08_21_140000_create_account_type_and_add_relations`
- `2026_08_21_150000_create_account_category_table_and_update_account_type`
- `2026_08_21_160000_canonicalize_contra_revenue_account_type`
- `2026_08_21_170000_enforce_audit_log_immutability`
- `2026_08_21_180000_enforce_activity_log_immutability`

## Unsupported Assumptions Removed Or Absent

| Assumption | Current schema status | Classification |
|---|---|---|
| `company_user` pivot | Table absent; tests assert absence. | REMOVED LEGACY/AI ASSUMPTION |
| `users.company_id` | Column absent. | UNDEFINED - DO NOT ASSUME |
| `branch.company_id` | Column absent. | REMOVED LEGACY/AI ASSUMPTION |
| Company -> Branch FK | Absent. | UNDEFINED - DO NOT ASSUME |
| `fiscal_year.company_id` | Column absent; FiscalYear is global single-ERP context. | REMOVED LEGACY/AI ASSUMPTION |
| `number_sequence.company_id` | Column absent. | REMOVED LEGACY/AI ASSUMPTION |
| `number_sequence.branch_id` | Column absent. | REMOVED LEGACY/AI ASSUMPTION |
| `number_sequence.include_branch` | Column absent. | REMOVED LEGACY/AI ASSUMPTION |
| `audit_log.company_id` | Column absent. | REMOVED LEGACY/AI ASSUMPTION |
| `audit_log.branch_id` | Column absent. | REMOVED LEGACY/AI ASSUMPTION |
| `activity_log.company_id` | Column absent. | DO NOT ADD |
| `activity_log.branch_id` | Column absent. | DO NOT ADD |
| `activity_log.tenant_id` | Column absent. | DO NOT ADD |
| `attachment.company_id` | Column absent. | REMOVED LEGACY/AI ASSUMPTION |
| `notification.company_id` | Column absent. | REMOVED LEGACY/AI ASSUMPTION |
| `roles.company_id` | Column absent. | REMOVED LEGACY/AI ASSUMPTION |
| Spatie team foreign key | Not active; `permission.teams` false. | REMOVED |
| `currentCompany` / `currentBranch` context | Not found in Laravel code. | REMOVED / ABSENT |

## Key Tables

### `company`

Company is a business profile/configuration record only. It does not own users, branches, roles, permissions, fiscal years, audit records, attachments, or notifications.

### `branch`

Standalone reference records. No `company_id`, no Company relationship, and no DB-level code uniqueness because Branch exact semantics remain undefined.

### `fiscal_year` / `financial_period`

FinancialPeriod belongs to FiscalYear. FiscalYear is global to this single ERP installation/business profile.

### `number_sequence`

Global sequence by `key`. No company or branch dimension. Atomic allocation uses PostgreSQL `INSERT ... ON CONFLICT ... DO UPDATE RETURNING`.

### `activity_log`

Active audit backend through Spatie Activitylog.

Expected core columns:

- `id`
- `log_name`
- `description`
- `subject_type`
- `subject_id`
- `event`
- `causer_type`
- `causer_id`
- `properties`
- `batch_uuid`
- `created_at`
- `updated_at`

No company/branch/tenant scope. Append-only DB trigger blocks UPDATE/DELETE.

### `audit_log`

Legacy archive retained for old audit rows. No new application writes should target this table. It remains append-only and has no company/branch fields.

### `attachment`

Generic entity-linked metadata. Authorization comes from the allowlisted entity registry and server-side permissions, not company scope.

### `notification`

User-targeted notifications with per-user dedupe. No company scope.

### Accounting Tables

Phase 2 accounting tables are implemented:

- `account_category`
- `account_type`
- `account_group`
- `account`
- `journal_entry`
- `journal_line`
- `ledger_entry`
- `opening_balance`

Schema assumptions:

- Account codes are global in the current single-ERP context.
- AccountCategory -> AccountType is confirmed in current implementation.
- AccountType -> AccountGroup/Account is confirmed in current implementation.
- JournalEntry belongs to FinancialPeriod.
- JournalLine belongs to JournalEntry and Account.
- LedgerEntry is derived from posted JournalLine and is immutable.
- OpeningBalance belongs to FiscalYear and Account.
- No accounting table introduces company/branch/tenant scope.

### RBAC Tables

Spatie Permission tables are global:

- no Spatie teams
- no `roles.company_id`
- no company-owned permissions
- `scope_json` remains an explicit future extension point, not a Company/Branch scope

## Remaining `company_id` / `branch_id` References

Allowed remaining references are non-runtime:

- cleanup migrations that drop old unsupported columns if present
- tests asserting unsupported columns are absent
- documentation describing removed assumptions

These are not active business schema.

## Follow-Ups

1. Decide Branch semantics before adding relationships, uniqueness, or authorization rules.
2. Decide document-number reset policy for future legal/commercial documents.
3. Register attachment authorization for each new Phase 3 entity.
4. Decide whether legacy `audit_log` rows should be imported into `activity_log` later; current behavior preserves it as archive.
5. Add Phase 3 schema without company/branch/tenant assumptions.
