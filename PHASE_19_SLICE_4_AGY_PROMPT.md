# Mini ERP - Phase 19 Slice 4 Agy Prompt

Execute ONLY Phase 19 Slice 4: Final Accountant Acceptance Close-Out.

Stop after this slice. Do not start a new phase.

## Scope

Close Phase 19 with final evidence that accountant acceptance fixtures, end-to-end workflow checks, persona/RBAC checks, documentation, and scans are complete.

Do not deploy. Do not start another product module.

## Non-Negotiable Rules

- No multi-tenant architecture and no company/tenant/security scope changes.
- No deployment/cutover work.
- Do not create new ERP modules.
- Do not alter accounting math, posting behavior, stock costing, tax, payroll, period close, numbering, idempotency, or locks unless a previous Phase 19 test already required a narrow bug fix.
- Do not store Telegram credentials, chat IDs, API keys, passwords, or production secrets in files.

## Required Report

Create `PHASE_19_FINAL_VERIFICATION_REPORT.md` with:

1. Executive summary.
2. Slice-by-slice summary for Slices 1-3.
3. Exact files changed in Phase 19.
4. Acceptance fixture result.
5. End-to-end accountant workflow result.
6. Persona/RBAC result.
7. Verification command results.
8. Source scan classifications.
9. Remaining product gaps, if any.
10. Recommended next owner action.

## Required Verification Commands

Run from `laravel/` and report exact results:

```powershell
php artisan migrate:status
vendor/bin/pint --test
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

## Required Source Scans

Run and classify:

```powershell
rg -n "dangerouslySetInnerHTML|<select|<option|type=\"date\"|window\\.location\\.href" laravel/resources/js/Pages laravel/resources/js/Components
rg -n "company_id|tenant_id|currentCompany|currentTenant|Spatie Teams" --glob "PHASE_19*.md" --glob "OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md" --glob "PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md" --glob "laravel/app/**" --glob "laravel/database/**" --glob "laravel/routes/**" --glob "laravel/resources/js/**" --glob "laravel/tests/**" .
rg -n "DB_PASSWORD=[^[:space:]]+|APP_KEY=base64:[^[:space:]]+|DATABASE_URL=[^[:space:]]+|bot[0-9]{8,}:|[0-9]{8,}:[A-Za-z0-9_-]{20,}" --glob "PHASE_19*.md" --glob "OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md" --glob "PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md" --glob "laravel/database/seeders/**" --glob "laravel/tests/**" .
```

Classify documentation policy mentions, test guard strings, and scan-command text separately from implementation matches. Secret scan must prove no actual Telegram credential/chat ID/API key/production credential was written to files.

## Documentation Updates

Update:

- `PHASE_19_ACCOUNTANT_ACCEPTANCE_EXECUTION.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

Required final status:

- Phase 19: COMPLETE if all tests and reports pass; otherwise PARTIAL with exact gaps.
- Deployment remains parked.
- Next task: owner/accountant hands-on sign-off using `OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md` and `PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md`, or explicitly approved next product phase.

## Final Rule

Stop after Phase 19 Slice 4. Do not start a new phase.
