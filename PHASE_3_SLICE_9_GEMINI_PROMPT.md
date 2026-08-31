# MINI ERP - PHASE 3 SLICE 9 GEMINI PROMPT

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


You are continuing the existing Mini ERP Laravel + Inertia + React migration.

Implement **Phase 3 Slice 9 only**:

```text
PostgreSQL Stress / Integrity Tests for Phase 3 Workflows and Reports
```

This is a hardening and verification slice.

Do **not** build new business modules. Do **not** redesign architecture. Do **not** add UI pages unless a tiny internal diagnostics view already exists and the project pattern requires it. Prefer tests, command-line stress tools, integrity checks, and documentation.

## Source Of Truth

Before changing code, read:

- `README.md`
- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`
- `PHASE_3_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_3_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_4_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_5_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_6_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_7_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_8_GEMINI_PROMPT.md`

Then inspect current implementation under:

- `laravel/app/Application`
- `laravel/app/Console/Commands`
- `laravel/app/Models`
- `laravel/database/migrations`
- `laravel/routes`
- `laravel/tests`

Do not rely on old Next.js docs or historical tenant/company scope documents.

## Current Baseline

The following are already implemented:

- Phase 2 accounting core:
  - immutable posted journal/ledger records
  - PostingEngine
  - reversal workflow
  - period close support
  - atomic number allocation
  - accounting stress command
- Phase 3 Slice 1:
  - Customer, Supplier, CashAccount, BankAccount
- Phase 3 Slice 2:
  - customer/supplier opening balances
  - receivable/payable subledgers
  - AR/AP control mapping
- Phase 3 Slice 3:
  - customer receipts
  - supplier payments
  - unapplied balances
- Phase 3 Slice 4:
  - receivable/payable allocations
  - allocation reversal
  - allocation concurrency stress command
- Phase 3 Slice 5:
  - incoming/outgoing cheques
  - cheque lifecycle stress command
- Phase 3 Slice 6:
  - bank reconciliation
  - bank reconciliation concurrency stress command
- Phase 3 Slice 7:
  - Inertia pages/actions for Phase 3 workflows
- Phase 3 Slice 8:
  - operational/subledger report services and pages
  - customer/supplier statements
  - AR/AP aging
  - Cash Book / Bank Book
  - Cheque Register
  - Bank Reconciliation status/detail
  - AR/AP to GL reconciliation

Existing stress commands include:

- `accounting:concurrency-stress`
- `accounting:allocation-concurrency-stress`
- `accounting:cheque-concurrency-stress`
- `accounting:bank-reconciliation-concurrency-stress`
- generic `concurrency:stress`

Slice 9 must audit these commands/tests first, then add missing hardening only where coverage is absent or weak.

## Owner Decisions That Must Remain True

- The ERP is **not** multi-tenant.
- Do not add `company_id`, `branch_id`, `tenant_id`, `currentCompany`, `currentBranch`, tenant middleware, or Spatie Teams.
- Company/Branch/User ownership relationships remain `UNDEFINED - DO NOT ASSUME`.
- Branch is not a tenant/security boundary.
- Money remains integer minor units; no float math.
- Report code remains read-only.
- Posted journals and ledger entries remain immutable.
- Finalized reconciliations remain immutable.
- Audit/activity records remain append-only.
- Corrections must use existing reversal behavior only.
- Do not add GitHub Actions. The owner has no GitHub Actions connected.

## Slice 9 Objective

Create a clear Phase 3 integrity and PostgreSQL concurrency hardening layer.

Required outcomes:

1. Audit existing stress/integrity coverage against the Phase 3 concurrency matrix.
2. Add missing PostgreSQL concurrency tests or stress command scenarios.
3. Add non-mutating integrity check command(s) for durable Phase 3 invariants.
4. Add report consistency checks for Slice 8 reports.
5. Preserve all existing behavior and no-tenant assumptions.
6. Update docs/status after verification.

## Pre-Implementation Audit

Before editing behavior, produce a coverage map in code comments, docs, or test names showing each matrix row and whether it is:

- already covered
- newly covered in Slice 9
- not implementable because the underlying business workflow is intentionally absent

Do not duplicate existing tests just to increase count. If coverage already exists, reference it and only add gaps.

## Required Stress / Integrity Coverage

### Receipt Posting Race

Verify that the same Customer Receipt cannot post twice under concurrent requests.

Expected invariant:

- exactly one durable posting effect
- one final posted receipt state
- one receipt document number if numbering is allocated at post time
- one journal/source linkage if applicable
- AR/unapplied balances remain correct

Use true PostgreSQL concurrency where possible.

### Payment Posting Race

Verify that the same Supplier Payment cannot post twice under concurrent requests.

Expected invariant mirrors receipt posting:

- exactly one durable posting effect
- one final posted payment state
- one payment document number if numbering is allocated at post time
- one journal/source linkage if applicable
- AP/unapplied balances remain correct

### Duplicate Idempotency Delivery

Verify same idempotency key replay for:

- customer receipt post
- supplier payment post
- receivable allocation
- payable allocation
- incoming cheque transition where idempotency exists
- outgoing cheque transition where idempotency exists
- bank reconciliation match/finalize where idempotency exists

Expected invariant:

- replay returns same result or deterministic conflict
- no duplicate journals
- no duplicate ledger entries
- no duplicate allocation rows
- no duplicate state transition effects

### AR Allocation Race

Verify allocation pressure where multiple workers attempt to allocate more than the remaining receivable balance.

Expected invariant:

- committed allocations never exceed target receivable remaining amount
- receipt `allocated_minor + unapplied_minor = amount_minor`
- target receivable remaining never goes negative
- no deadlocks

If already covered by `accounting:allocation-concurrency-stress`, add focused assertions or documentation only for uncovered cases.

### AP Allocation Race

Mirror AR allocation race for supplier payments and payable entries.

Expected invariant:

- committed allocations never exceed target payable remaining amount
- payment `allocated_minor + unapplied_minor = amount_minor`
- target payable remaining never goes negative
- no deadlocks

### Multi-Target Allocation Race

Verify deterministic locking with overlapping multi-target allocations.

Expected invariant:

- no deadlock
- no over-allocation
- lock ordering is documented
- partial success/failure is deterministic and explainable

If services only support one target per call, document that and test the closest supported overlapping scenario.

### Allocation vs Receipt Reversal

Verify the current owner-approved behavior for allocation colliding with receipt reversal.

If receipt reversal with allocations is implemented:

- one transaction wins
- no impossible balance persists
- receipt, allocations, receivable entries, and unapplied balances remain consistent

If receipt reversal with allocations is intentionally blocked or not implemented:

- add a test proving the blocked behavior is deterministic
- document it as owner-decision boundary, not a missing race fix

### Allocation vs Payment Reversal

Mirror allocation vs receipt reversal for supplier payments.

Apply the same rule:

- test the implemented workflow if it exists
- otherwise test/document deterministic blocked behavior

### Cheque Clear vs Clear

Verify the same cheque clears exactly once under concurrent workers.

Expected invariant:

- one valid state transition
- one accounting effect
- one journal/source linkage where applicable
- idempotency replay remains stable

If already covered by `accounting:cheque-concurrency-stress`, add only missing assertions or document coverage.

### Cheque Clear vs Bounce / Return / Cancel

Verify incompatible cheque transitions cannot both commit.

Expected invariant:

- clear and bounce cannot both persist
- clear and return/cancel cannot both persist if such state combination is invalid
- resulting subledger/GL effects match the final state

Respect the existing owner decision: do not invent post-clear bounce/return semantics.

### Bank Reconciliation Duplicate Matching

Verify a ledger entry cannot be matched into incompatible active/final reconciliations.

Expected invariant:

- duplicate matching fails cleanly
- unique/partial indexes and service locks agree
- no orphaned statement-line match remains

If already covered by `accounting:bank-reconciliation-concurrency-stress`, add only missing edge coverage.

### Reconciliation Finalization Race

Verify concurrent finalization is idempotent/safe.

Expected invariant:

- one final reconciled state
- finalized records are immutable
- final summary values remain stable

### Period Close vs Phase 3 Posting

Verify period close cannot race with Phase 3 posting into the same period.

At minimum cover:

- period close vs customer receipt posting
- period close vs supplier payment posting
- period close vs incoming cheque clear/bounce where posting happens
- period close vs outgoing cheque clear/return/cancel where posting happens

Expected invariant:

- either the posting commits before close and remains valid
- or close wins and posting fails cleanly
- no posted Phase 3 accounting effect appears in a closed period after the close commits

Use existing `PeriodService::closePeriod` and existing posting services. Do not invent a new period close mechanism.

### Subledger To GL Reconciliation Consistency

Verify AR/AP subledger balances agree with configured control GL accounts after:

- opening balances
- receipt/payment posting
- allocations
- cheque lifecycle effects that affect AR/AP
- reversals where implemented

Expected invariant:

- AR report/service reconciliation difference is zero in balanced fixture
- AP report/service reconciliation difference is zero in balanced fixture
- missing mapping produces deterministic warning/result, not silent success

Use the Slice 8 AR/AP to GL report services where appropriate.

### Report Read Consistency

Verify report services remain read-only and stable under realistic concurrent writes.

Required checks:

- report calls do not mutate Phase 3 tables
- report services do not create journals, ledger entries, allocations, or audit writes for ordinary viewing
- reports can be called while concurrent allocation/posting stress runs without throwing inconsistent-query errors
- after concurrent workers settle, report totals match durable subledger/ledger invariants

Do not add cache tables or materialized summaries unless a clear existing project pattern already supports that.

## Integrity Check Command

Add a non-mutating command if one does not already exist:

```powershell
php artisan accounting:phase3-integrity-check
```

The command should check existing database data and return non-zero on broken invariants.

Expected checks:

- receipt/payment `allocated_minor + unapplied_minor = amount_minor`
- no negative unapplied balances
- allocation totals do not exceed receipt/payment/source balances
- allocation totals do not exceed receivable/payable target balances
- posted receipt/payment/cheque/reconciliation source references are not orphaned
- posted journals referenced by Phase 3 documents exist
- posted journals referenced by Phase 3 documents have ledger entries
- immutable/finalized state expectations are not violated
- bank reconciliation matched ledger entries are not duplicated incompatibly
- AR to GL reconciliation has zero difference where mappings and data support it
- AP to GL reconciliation has zero difference where mappings and data support it
- reports remain read-only

The command must:

- not delete immutable records
- not modify accounting balances
- not assume tenant/company/branch scope
- print concise pass/fail details

## Stress Command Strategy

Prefer extending existing commands instead of creating many overlapping commands.

Allowed approaches:

- extend existing specialized stress commands with missing scenarios
- add one orchestrator command such as:

```powershell
php artisan accounting:phase3-stress --workers=50
```

The orchestrator may call or share code with existing commands, but it must report each scenario independently.

If adding an orchestrator, include:

- receipt post race
- payment post race
- allocation race
- cheque transition race
- bank reconciliation race
- period close race
- report read consistency check
- final integrity check

Keep stress fixtures isolated with random suffixes and future fiscal years. If immutable posted data cannot be deleted safely, use unique fixture prefixes and leave data in a consistent state.

## Database / Migration Rules

Do not add new business tables.

Forward migrations are allowed only if you discover a real missing database constraint/index required to preserve an already-confirmed invariant.

If adding a migration:

- explain the exact invariant it enforces
- make it PostgreSQL-safe and SQLite-test-safe where applicable
- do not rewrite historical migrations
- do not add company/branch/tenant scope
- do not weaken existing immutability triggers

## Testing Requirements

Add focused tests under the existing test structure.

Expected test file:

- `tests/Feature/Phase3Slice9StressIntegrityTest.php`

Use PostgreSQL-specific skips for tests that require real row locks or Laravel concurrency workers:

```php
if (DB::connection()->getDriverName() !== 'pgsql') {
    $this->markTestSkipped('PostgreSQL row-locking stress test.');
}
```

Minimum coverage:

- receipt posting race
- payment posting race
- duplicate idempotency replay for representative Phase 3 commands
- allocation over-pressure invariants
- allocation vs reversal current behavior
- cheque incompatible transition race current behavior
- bank reconciliation duplicate matching/finalization current behavior
- period close vs Phase 3 posting
- AR/AP to GL consistency after Phase 3 effects
- report read-only behavior
- `accounting:phase3-integrity-check` returns success on clean data
- no `company_id`, `branch_id`, `tenant_id`, `currentCompany`, or `currentBranch` introduced

If a scenario is already fully covered by an existing command/test, add an assertion that invokes that command or document the coverage in the new test file comments.

## Documentation Updates

After implementation, update:

- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`
- `CHANGELOG.md`
- `MD_DOCUMENTATION_AUDIT.md` if classifications change
- optional `docs/CONCURRENCY_AUDIT.md` if it exists and is current

The docs must say Phase 3 Slice 9 is complete only if the code and verification commands pass.

## Verification Commands

Run from `laravel/` and report exact results:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --filter=Phase3Slice9StressIntegrityTest
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan accounting:phase3-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

If `accounting:phase3-stress` is not added because the existing specialized commands fully cover the matrix, explain that decision and run the specialized commands plus `accounting:phase3-integrity-check`.

If any command cannot run, explain exactly why and what was already verified.

## Required Final Report

Return a concise final report with:

1. Coverage map against the Phase 3 concurrency matrix.
2. Files changed.
3. Commands added/extended.
4. Tests added.
5. Invariants verified.
6. PostgreSQL-only scenarios and skip behavior.
7. Any migrations added and exact invariant they enforce.
8. Confirmation that reports remain read-only.
9. Confirmation that no new business module was started.
10. Confirmation that no company/branch/tenant scope was introduced.
11. Verification command results.
12. Remaining risks, if any.

End with explicit confirmations:

```text
Slice implemented: Phase 3 Slice 9 only.
New business modules implemented: NO.
Full financial statements implemented: NO.
Sales/Purchasing/Inventory implemented: NO.
Bank import/auto adjustment posting implemented: NO.
New tenant/company/branch scope introduced: NO.
Report code mutates accounting data: NO.
Phase 3 integrity check command: PASS.
PostgreSQL stress coverage: PASS or documented bounded gaps.
```
