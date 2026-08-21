# MINI ERP - PHASE 3 SLICE 10 GEMINI PROMPT

You are continuing the existing Mini ERP Laravel + Inertia + React migration.

Implement **Phase 3 Slice 10 only**:

```text
Docs / Status / Final Verification Gate
```

This is the final Phase 3 close-out slice.

Do **not** build new business functionality. Do **not** add new ERP modules. Do **not** redesign architecture. Do **not** add UI pages. Do **not** add new database business tables.

The goal is to make the repository documentation, status files, verification evidence, and handoff state consistent with the completed Phase 3 implementation.

## Source Of Truth

Before changing files, read:

- `README.md`
- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CHANGELOG.md`
- `MD_DOCUMENTATION_AUDIT.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`
- `PHASE_3_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_3_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_4_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_5_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_6_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_7_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_8_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_9_GEMINI_PROMPT.md`
- `docs/CONCURRENCY_AUDIT.md` if present
- `docs/PROJECT_MAP.md` if present
- `laravel/README.md` if present

Then inspect the current Laravel code only as needed to verify documentation claims:

- `laravel/app`
- `laravel/config`
- `laravel/database`
- `laravel/routes`
- `laravel/resources/js`
- `laravel/tests`

Do not treat old Next.js app docs under `app/` as current source of truth.

## Current Baseline

Phase 3 Slices 1-9 are implemented:

1. Master data: Customer, Supplier, CashAccount, BankAccount.
2. AR/AP subledger and opening balances.
3. Customer receipts and supplier payments.
4. AR/AP allocation engine.
5. Incoming/outgoing cheque lifecycle.
6. Bank reconciliation.
7. Inertia pages and UX actions.
8. Operational/subledger reports.
9. PostgreSQL stress and integrity hardening.

Slice 10 must close Phase 3, not extend it.

## Owner Decisions That Must Remain True

- The ERP is **not** multi-tenant.
- Do not add or restore `company_id`, `branch_id`, `tenant_id`, `currentCompany`, `currentBranch`, tenant middleware, or Spatie Teams.
- Company/Branch/User ownership relationships remain `UNDEFINED - DO NOT ASSUME`.
- Branch is not a tenant/security boundary.
- FiscalYear is global to the installation/business profile.
- Document numbering is global by key unless a later owner decision explicitly changes it.
- Spatie Activitylog is the active audit backend.
- Legacy `audit_log` is archive only.
- Reports are read-only.
- Posted journal and ledger records are immutable.
- Finalized bank reconciliations are immutable.
- Corrections are by existing reversal behavior only.
- No GitHub Actions pipeline is connected for this Laravel migration track.

## Slice 10 Objective

Perform final documentation/status cleanup and verification for Phase 3.

Required outcomes:

1. All current MD files agree that Phase 3 Slices 1-9 are complete.
2. Slice 10 is marked complete only after verification passes.
3. Current handoff docs identify the next step after Phase 3.
4. No stale docs claim that Slice 7, 8, or 9 are merely prompt-ready.
5. Old tenant/company/branch assumptions remain marked legacy/stale where they appear.
6. Final verification command results are recorded.
7. A concise final Phase 3 completion report is added.

## Required Documentation Updates

Update these files if needed:

- `README.md`
- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CHANGELOG.md`
- `MD_DOCUMENTATION_AUDIT.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`
- `docs/CONCURRENCY_AUDIT.md` if present and current
- `docs/PROJECT_MAP.md` if present and current
- `laravel/README.md` if it has stale setup/status information

Also create a final report file:

```text
PHASE_3_FINAL_VERIFICATION_REPORT.md
```

The final report must include:

- scope completed
- files/modules implemented by slice
- verification commands and results
- database/migration status
- stress/integrity results
- remaining explicit non-goals
- remaining risks
- next recommended phase

## Required Status Semantics

After Slice 10 passes, docs must say:

```text
Phase 3 Slices 1-10 complete.
Phase 3 AR/AP + Cash/Bank/Cheques track complete for the agreed scope.
```

The next recommended phase should be one of:

- Phase 4 Sales & Purchasing operations, if owner wants the next product module.
- Optional Laravel browser E2E parity, if owner wants UI smoke testing first.
- Optional production deployment readiness, if owner wants ops hardening first.

Do not automatically start any of these. Only document them as next choices.

## Documentation Consistency Checks

Search all Markdown files for stale current-state claims.

At minimum check for:

- `Slice 7 prompt-ready`
- `Slice 8 prompt-ready`
- `Slice 9 prompt-ready`
- `Prepare a new bounded Slice 10`
- `Phase 3 Slices 1-8`
- `Phase 3 Slices 1-9` after Slice 10 is complete
- `company_id` in a current ownership/scoping claim
- `branch_id` in a current ownership/scoping claim
- `tenant`
- `Spatie Teams`
- `currentCompany`
- `currentBranch`
- `audit_log` as active backend
- `GitHub Actions` as connected CI

Do not erase historical records blindly. If stale claims are in legacy sections, label them as legacy/historical instead of pretending they never existed.

## Code Change Rules

This slice is primarily documentation and verification.

Allowed code changes:

- formatting fixes required by Pint
- tiny test or command output wording fixes if verification reveals a mismatch
- docs-only adjustments

Disallowed code changes:

- new business workflows
- new UI pages
- new migrations unless a verification-breaking schema bug is discovered and must be fixed
- new accounting behavior
- new report behavior
- new tenant/company/branch scope
- new Sales/Purchasing/Inventory/Payroll/Fixed Asset modules
- full financial statements
- bank import
- automatic bank adjustment posting

If verification fails due to real application behavior, report the failure clearly before making any broad fix.

## Final Verification Gate

Run from `laravel/` and record exact results:

```powershell
composer install
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --filter=Phase3Slice9StressIntegrityTest
php artisan test --filter=Phase3Slice8ReportsTest
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

If a command cannot run locally, explain exactly why and keep the Phase 3 completion status pending unless an equivalent verified result already exists and is clearly documented.

## Expected Final Status

If all checks pass, update:

- `IMPLEMENTATION_STATUS.md`: Phase 3 Slices 1-10 complete.
- `NEXT_TASKS.md`: next recommended work is outside Phase 3, with options only.
- `CONTINUE_HERE.md`: final handoff says Phase 3 is complete.
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`: Slice 10 complete and Phase 3 complete for agreed scope.
- `MD_DOCUMENTATION_AUDIT.md`: classify Slice 10 prompt/report as current/current-with-history as appropriate.
- `README.md`: implemented scope includes final Phase 3 verification.
- `CHANGELOG.md`: add Slice 10 completion entry.

## Required Final Report

Return a concise final report with:

1. Documentation files updated.
2. Final verification commands and results.
3. Any code changes made, if any.
4. Final Phase 3 completion status.
5. Remaining explicit non-goals.
6. Remaining risks.
7. Recommended next choices after Phase 3.

End with explicit confirmations:

```text
Slice implemented: Phase 3 Slice 10 only.
Phase 3 agreed scope complete: YES.
New business modules implemented: NO.
Full financial statements implemented: NO.
Sales/Purchasing/Inventory implemented: NO.
Bank import/auto adjustment posting implemented: NO.
New tenant/company/branch scope introduced: NO.
Final verification gate: PASS or documented pending/failure.
```
