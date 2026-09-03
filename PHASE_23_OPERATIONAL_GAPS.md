# Phase 23 - Operational Gap Closure

> Performance and operational hardening. No ERP business behavior changed. Deployment execution remains parked until the owner explicitly resumes cutover work.

## Status

COMPLETE - 2026-09-03.

## Purpose

A gap sweep of the working tree, run by measuring and executing rather than reading.
Three real defects surfaced. All three are fixed and pinned with tests.

## Defects Found And Fixed

### 1. Trial Balance issued 294 queries per page load

`GeneralLedgerService::getTrialBalance()` looped over every active account and ran two
aggregate queries inside the loop — one for debits, one for credits.

Measured on the development database: **294 queries, 240 ms, 146 accounts.**

That cost grows linearly with the chart of accounts. A 500-account chart would issue
roughly 1,000 queries to render one of the most frequently opened accounting screens.

**Fix:** one grouped aggregate over `ledger_entry`, keyed by account, with the same four
optional filters (period, branch, start date, end date) applied once to the grouped query
instead of per account.

**After: 3 queries, 110 ms.**

Output equivalence was verified by capturing the result before and after the change via
`git stash`. Both produce 95 rows, `total_debit = 7,300,000`, `total_credit = 4,800,000`,
and the same `is_balanced` value. (`is_balanced` is `false` on this database because it
holds leftover stress-test data — a pre-existing condition, unrelated to this change.)

### 2. No automated database backup

`spec/BACKUP_RESTORE_DRILL.md` specified `pg_dump` under every candidate retention option,
but nothing executed it. The runbook described a manual procedure, and the scheduler had
exactly one entry: `tokens:gc`.

A production install would have run with **no backups at all**.

**Fix:** added `php artisan db:backup`:

- Compressed custom-format dump (`pg_dump --format=custom`), restorable selectively with `pg_restore`.
- Password passed via `PGPASSWORD` in the child process environment, never on the command line where `ps` would expose it.
- Refuses to run against a non-PostgreSQL connection.
- Deletes any partial file on failure — a truncated dump that looks restorable is worse than no dump.
- Rejects a zero-byte result even when `pg_dump` reports success.
- `--retention-days` prunes old dumps; pruning is skipped entirely when the dump fails, so a bad run can never delete good backups.
- Exits non-zero on failure, verified directly (`REAL_EXIT=1`), so the scheduler and monitoring can detect it.

Scheduled daily at 02:30 with `withoutOverlapping()` and `onOneServer()`.
`storage/app/backups` was already gitignored.

`ops:go-live-readiness` now fails if `db:backup` is missing from the scheduler, so a
production cutover cannot silently proceed without backups.

**Retention defaults to 14 days.** Adjust once the owner selects an option in
`spec/BACKUP_RESTORE_DRILL.md` — that decision is still open.

### 3. Debug-level logging would reach production

`.env.example` shipped `LOG_LEVEL=debug`. At debug level Laravel records request context
that can include customer records and financial payloads. Correct locally, a data-retention
problem on a live install.

**Fix:** `ops:go-live-readiness` now fails for staging and production targets when the log
level is `debug` or `info`. Verified: `--target=production` reports
`FAIL | log.level | LOG_LEVEL is [debug]`. The local target is unaffected. The template
carries a comment explaining the constraint.

## Checked And Found Sound

Several suspicions did not survive verification. Recording them so they are not re-audited:

| Concern | Finding |
|---|---|
| N+1 queries across report services | Only `getTrialBalance` had the pattern. Other report services aggregate correctly. |
| Missing indexes on hot tables | `ledger_entry` 9, `journal_entry` 7, `receivable_entry` 5, `payable_entry` 5. Adequate. |
| Login brute-force protection | Present in `LoginRequest` via `RateLimiter`, capped at 5 attempts. An initial search looked in the routes file and missed it. |
| 40 pages receive paginated data but render no controls | **False alarm.** `AppLayout` mounts `UniversalPagination`, which detects any paginator in the page props. Verified in-browser: customers 3 links, products 3, journal 13 across 1,189 entries. |

## Verification

| Command | Result |
|---|---|
| Trial Balance query count, before/after | 294 → **3**; identical output |
| `php artisan test --filter=DatabaseBackupCommandTest` | 4 passed / 12 assertions |
| `php artisan test --filter='(AccountingCore\|AccountCategory\|AccountType\|Phase2\|Phase3\|Phase5)'` | 205 passed, 2 skipped / 2,067 assertions |
| `php artisan test --filter='(Phase15\|Phase17\|Phase18\|Phase19\|Phase20\|Security\|GoLive\|HealthCheck\|DatabaseBackup)'` | 290 passed / 29,706 assertions |
| `php artisan test --filter='(AccountingCore\|TrialBalance\|Phase5Slice2\|Phase15)'` | 222 passed / 26,827 assertions |
| `npx playwright test` | 22 passed |
| `php artisan ops:go-live-readiness` | 0 blocking failures |
| `php artisan ops:go-live-readiness --target=production` | correctly flags `log.level` |
| `php artisan schedule:list` | `tokens:gc` hourly, `db:backup` daily 02:30 |
| `vendor/bin/pint --test` | passed |
| `npx tsc --noEmit` | 0 errors |

## Invariants Preserved

- No business logic, GL posting, or financial calculation changed.
- Trial Balance output is byte-identical to the previous implementation.
- No tenant/company scope; no float money math.
- No credential committed; backup passwords stay in the process environment.

## Owner Decision: CI Removed (2026-09-03)

The GitHub Actions workflow was removed at the owner's request. `.github/` no longer exists.

This also removes the dead `app/`-targeted workflow that predated Phase 22, so the project
no longer carries a pipeline that reports green checks for code nobody runs.

Consequences to be aware of:

- The backup **success** path is no longer exercised anywhere automatically. Only the failure
  path was verifiable locally (`pg_dump` is not installed on the development machine). Run
  `php artisan db:backup` by hand on a host that has the PostgreSQL client before trusting it.
- Nothing runs the test suite or the source scans on push. Verification is on demand.

Every tool the CI job called still works standalone and is unchanged:

```powershell
cd laravel
php artisan qa:verify-local
php artisan security:route-audit --strict
php artisan ops:go-live-readiness
node scripts/check-locale-parity.mjs
vendor/bin/pint --test
npm run typecheck
npm run e2e
```

## Still Open

**The backup success path is unverified** — the PostgreSQL client is not installed on this
machine, so only the failure path could be checked here (it fails cleanly and writes no
partial file). The CI round-trip that would have covered the success path was removed with
the pipeline, so run `php artisan db:backup` manually on a host with `pg_dump` and confirm a
non-empty `.dump` file appears before relying on it.

**Backup retention and destination remain owner decisions** (`spec/BACKUP_RESTORE_DRILL.md`).
The command writes locally; shipping dumps to offsite or encrypted storage needs the target
and credentials you choose.
