# Mini ERP - Phase 17 Slice 6 Agy Prompt

Execute ONLY Phase 17 Slice 6: Security Close-Out and Final Verification.

This is a close-out, verification, and documentation slice. Do not add a product feature. Do not start a new phase. Do not perform deployment/cutover work.

## Non-Negotiable System Rules

- No multi-tenant architecture.
- Do not add `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, company-user membership, or Spatie Teams.
- Branch is an operational/reporting dimension only where already implemented. Do not make Branch a tenant, security scope, login context, user ownership scope, or blanket route scope.
- Do not change accounting math, tax math, payroll math, stock costing math, document numbering, idempotency, database locking, posting rules, period close behavior, or existing workflow status transitions.
- Spatie Activitylog remains the active audit backend.
- Controllers must stay thin.
- No hardcoded visible strings in React pages.
- Do not use native `<select>`, `<option>`, `type="date"`, `dangerouslySetInnerHTML`, or `window.location.href`.
- Deployment remains parked. Do not edit deployment/runbook/cutover files except if a Phase 17 security status link is clearly required.

## Existing Phase 17 Slices To Verify

Required reports must exist and be summarized:

- `PHASE_17_SLICE_1_REPORT.md`
- `PHASE_17_SLICE_2_REPORT.md`
- `PHASE_17_SLICE_3_REPORT.md`
- `PHASE_17_SLICE_4_REPORT.md`
- `PHASE_17_SLICE_5_REPORT.md`

Required prompts must remain present:

- `PHASE_17_SECURITY_ACCESS_GOVERNANCE.md`
- `PHASE_17_SLICE_1_AGY_PROMPT.md`
- `PHASE_17_SLICE_2_AGY_PROMPT.md`
- `PHASE_17_SLICE_3_AGY_PROMPT.md`
- `PHASE_17_SLICE_4_AGY_PROMPT.md`
- `PHASE_17_SLICE_5_AGY_PROMPT.md`
- `PHASE_17_SLICE_6_AGY_PROMPT.md`

## Objective

Create the final Phase 17 security verification report and sync status documents. Prefer verification and documentation only.

Only change application code if a required verification command exposes a real regression introduced by Phase 17. If code must be changed, keep it narrowly scoped and explain exactly why in the final report.

## Required Final Report

Create `PHASE_17_FINAL_VERIFICATION_REPORT.md` containing:

1. Executive summary of Phase 17.
2. Slice-by-slice summary for Slices 1-5.
3. Exact files changed by Phase 17, grouped by:
   - configuration
   - seeders
   - middleware/support classes
   - FormRequests/controllers/services
   - UI/React
   - tests
   - documentation
4. Security controls delivered:
   - first-user Super Admin fail-closed guard
   - route authorization audit command
   - password policy and session safety
   - sensitive action confirmation middleware and audit evidence
   - private attachment delivery hardening
   - notification user isolation hardening
5. Verification command results with exact numbers.
6. Source scan results:
   - no new tenant/company security scope terms in Phase 17 implementation files
   - no Spatie Teams enablement
   - no unsafe TSX APIs/native controls in Phase 17 changed frontend files
   - no raw secrets in docs/templates
   - no legacy `audit_log` writer revival for new audit records
7. Route audit summary.
8. Remaining operational/security risks:
   - CSP still configurable but not enabled by default pending browser smoke/asset policy.
   - Deployment process remains parked by owner decision.
   - External malware scanning is optional future deployment integration.
   - Full unfiltered PHPUnit may exceed local shell timeout; report required targeted suites instead of inventing a pass result.
9. Final status: Phase 17 COMPLETE if all required verification passes.

## Required Verification Commands

Run from `laravel/` and report exact results:

```powershell
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=AuthenticationTest --compact
php artisan test --filter=SecurityHardeningTest --compact
php artisan test --filter=AttachmentAndNotificationTest --compact
php artisan test --filter=M9AttachmentsAndNotificationsTest --compact
php artisan test --filter=Phase15ProductHardeningTest --compact
php artisan test --filter=Phase16 --compact
php artisan test --testsuite=Concurrency --compact
php artisan security:route-audit --strict
npm run typecheck
npm run build
git diff --check
```

Do not run a full unfiltered `php artisan test` unless you have enough time and it does not replace the required targeted suite list above. If attempted and it times out, report it honestly as timeout, not pass/fail.

## Required Source Scans

Run and report classification:

```powershell
rg -n "company_id|tenant_id|currentCompany|currentTenant|Spatie Teams" laravel/app laravel/bootstrap laravel/config laravel/database laravel/routes laravel/resources/js laravel/tests PHASE_17_*.md spec/SECURITY.md spec/ENVIRONMENT_CHECKLIST.md
rg -n "dangerouslySetInnerHTML|<select|<option|type=\"date\"|window\\.location\\.href" laravel/resources/js/Components/SensitiveActionModal.tsx laravel/resources/js/Pages
rg -n "audit_log.*insert|DB::table\\('audit_log'\\)|DB::table\\(\"audit_log\"\\)|INSERT INTO audit_log" laravel/app laravel/database laravel/tests
rg -n "DB_PASSWORD=.+|APP_KEY=base64:.+|SECRET=.+|TOKEN=.+|PASSWORD=.+|DATABASE_URL=.+" laravel/.env.example spec README.md PHASE_17_*.md
```

Classify historical/no-policy text matches in docs separately from implementation matches. Do not hide matches.

## Documentation Updates

Update:

- `PHASE_17_SECURITY_ACCESS_GOVERNANCE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

Required status after success:

- Phase 17: COMPLETE
- Slice 6: COMPLETE
- Next task: no automatic next implementation phase; recommend owner/product review or a new explicitly approved phase.
- Deployment remains parked.

Stop after creating `PHASE_17_FINAL_VERIFICATION_REPORT.md` and updating the status documents.
