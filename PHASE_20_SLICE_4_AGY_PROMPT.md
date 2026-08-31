# Mini ERP - Phase 20 Slice 4 Agy Prompt

Execute ONLY Phase 20 Slice 4: Final Hands-On Acceptance Close-Out.

Stop after this slice. Do not start a new phase.

## Scope

Close Phase 20 with final evidence that the hands-on acceptance defect register, UX friction cleanup, validation/permission clarity, documentation, and scans are complete.

Do not deploy. Do not start another product module.

## Non-Negotiable Rules

- No multi-tenant architecture and no company/tenant/security scope changes.
- No deployment/cutover work.
- Do not create new ERP modules.
- Do not alter accounting math, posting behavior, stock costing, tax, payroll, period close, numbering, idempotency, or locks unless a previous Phase 20 test already required a narrow bug fix.
- Do not store Telegram credentials, chat IDs, API keys, passwords, or production secrets in files.
- Do not include raw secret-matching regular expressions in the final report if they cause self-matches. Summarize secret scan categories instead.

## Required Report

Create `PHASE_20_FINAL_VERIFICATION_REPORT.md` with:

1. Executive summary.
2. Slice-by-slice summary for Slices 1-3.
3. Exact files changed in Phase 20.
4. Acceptance defect register result.
5. UX friction fixes result.
6. Validation/permission clarity result.
7. Verification command results.
8. Source scan classifications.
9. Remaining product gaps, if any.
10. Recommended next owner action.

## Required Verification Commands

Run from `laravel/` and report exact results:

```powershell
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=Phase20HandsOnAcceptanceTest --compact
php artisan test --filter=Phase19AccountantAcceptanceTest --compact
php artisan test --filter=Phase18ProductAcceptanceTest --compact
php artisan test --filter=SecurityHardeningTest --compact
php artisan test --filter=Phase15ProductHardeningTest --compact
php artisan test --testsuite=Concurrency --compact
php artisan security:route-audit --strict
npm run typecheck
npm run build
git diff --check
```

Run and classify:

```powershell
rg -n "dangerouslySetInnerHTML|<select|<option|type=\"date\"|window\\.location\\.href" laravel/resources/js/Pages laravel/resources/js/Components
rg -n "company_id|tenant_id|currentCompany|currentTenant|Spatie Teams" PHASE_20*.md PRODUCT_ACCEPTANCE_DEFECT_LOG.md OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md laravel/app laravel/routes laravel/resources/js laravel/tests/Feature/Phase20HandsOnAcceptanceTest.php
```

Also run a secret scan for actual credentials/tokens across Phase 20 files, the defect log, acceptance docs, Laravel seeders, and Laravel tests. Report whether any real secret values were found. Do not print any actual secret in the report.

## Documentation Updates

Update:

- `PHASE_20_HANDS_ON_ACCEPTANCE_DEFECT_CLOSURE.md`
- `PRODUCT_ACCEPTANCE_DEFECT_LOG.md` if needed
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

Required final status:

- Phase 20: COMPLETE if all tests and reports pass; otherwise PARTIAL with exact gaps.
- Deployment remains parked.
- Next task: owner/accountant hands-on sign-off using `OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md`, `PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md`, and `PRODUCT_ACCEPTANCE_DEFECT_LOG.md`, or explicitly approved next product phase.

## Final Rule

Stop after Phase 20 Slice 4. Do not start a new phase.
