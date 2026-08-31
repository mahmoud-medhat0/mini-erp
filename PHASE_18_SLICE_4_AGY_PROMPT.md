# Mini ERP - Phase 18 Slice 4 Agy Prompt

Execute ONLY Phase 18 Slice 4: Final Product Acceptance, UI Polish, and Clean-Code Close-Out.

This is a close-out and verification slice. Stop after this slice. Do not start a new phase.

## Non-Negotiable Rules

- No multi-tenant architecture and no company/tenant/security scope changes.
- No deployment/cutover work.
- Do not create new ERP modules.
- Do not alter accounting math, posting behavior, stock costing, tax, payroll, period close, numbering, idempotency, or locks.
- Do not store Telegram tokens, chat IDs, API keys, or secrets in files.

## Objective

Close Phase 18 with a final report proving:

- unsafe pagination rendering is removed
- controller clean-code gate exists and passes
- product acceptance matrix exists
- required verification suite passes
- Phase 18 docs/status are synchronized

## Required Report

Create `PHASE_18_FINAL_VERIFICATION_REPORT.md` containing:

1. Executive summary.
2. Slice-by-slice summary for Slices 1-3.
3. Exact files changed in Phase 18.
4. UI safety result.
5. Controller clean-code result.
6. Product acceptance matrix result.
7. Verification command results.
8. Source scan classifications.
9. Remaining risks and recommended next owner decision.

## Required Verification Commands

Run from `laravel/` and report exact results:

```powershell
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=Phase18ProductAcceptanceTest --compact
php artisan test --filter=Phase17 --compact
php artisan test --filter=SecurityHardeningTest --compact
php artisan test --filter=Phase15ProductHardeningTest --compact
php artisan test --testsuite=Concurrency --compact
php artisan security:route-audit --strict
npm run typecheck
npm run build
git diff --check
```

If `php artisan test --filter=Phase17 --compact` matches no tests, report that clearly and rely on the named Phase 17 report/test suites.

## Required Source Scans

Run and classify:

```powershell
rg -n "dangerouslySetInnerHTML|<select|<option|type=\"date\"|window\\.location\\.href" laravel/resources/js/Pages laravel/resources/js/Components
rg -n "company_id|tenant_id|currentCompany|currentTenant|Spatie Teams" --glob "PHASE_18*.md" --glob "PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md" --glob "laravel/app/**" --glob "laravel/routes/**" --glob "laravel/resources/js/**" --glob "laravel/tests/**" .
rg -n "DB_PASSWORD=[^[:space:]]+|APP_KEY=base64:[^[:space:]]+|DATABASE_URL=[^[:space:]]+|bot[0-9]{8,}:|[0-9]{8,}:[A-Za-z0-9_-]{20,}" --glob "PHASE_18*.md" --glob "PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md" .
```

Classify documentation policy mentions, test guard strings, and scan-command text separately from implementation matches. Secret scan must prove no actual Telegram token/chat ID/API key/production credential was written to files; do not add any real secret to the report.

## Documentation Updates

Update:

- `PHASE_18_PRODUCT_ACCEPTANCE_UI_CLEAN_CODE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

Required final status:

- Phase 18: COMPLETE
- Deployment remains parked
- Next task: owner/accountant hands-on acceptance review or explicitly approved next product phase

Stop after Phase 18 Slice 4.
