# Mini ERP - Phase 20 Slice 1 Agy Prompt

Execute ONLY Phase 20 Slice 1: Hands-On Acceptance Defect Register and Walkthrough Baseline.

Stop after this slice. Do not start Slice 2.

## Scope

Create a practical defect register and automated baseline proving that the owner/accountant walkthrough can be executed against the current local product.

This is not deployment work and not a new ERP module.

## Non-Negotiable Rules

- No multi-tenant architecture and no company/tenant/security scope changes.
- Do not add `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, company-user membership, tenant context, branch security context, or Spatie Teams.
- Branch may be referenced only as an existing operational/reporting dimension.
- Do not change accounting math, posting behavior, stock costing, tax, payroll, period close, numbering, idempotency, or locks.
- Do not store Telegram credentials, chat IDs, API keys, passwords, or production secrets in files.
- No hardcoded visible strings in React pages.
- Keep controllers thin.
- If no bug is found, do not invent a code fix.

## Required Review Before Editing

Inspect:

- `PHASE_20_HANDS_ON_ACCEPTANCE_DEFECT_CLOSURE.md`
- `OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md`
- `PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md`
- `PHASE_19_FINAL_VERIFICATION_REPORT.md`
- `laravel/database/seeders/AccountantAcceptanceSeeder.php`
- `laravel/tests/Feature/Phase19AccountantAcceptanceTest.php`
- `laravel/routes/web.php`
- `laravel/config/erp_rbac.php`

## Required Implementation

Create a reusable acceptance defect register:

- `PRODUCT_ACCEPTANCE_DEFECT_LOG.md`

The document must include:

1. Arabic and English purpose.
2. Severity definitions: Blocker, High, Medium, Low.
3. Status definitions: New, Confirmed, Fixed, Retest Passed, Deferred, Rejected.
4. A table template with columns:
   - ID
   - Date
   - Reporter
   - Persona/Role
   - Module/Page
   - Route
   - Severity
   - Status
   - Steps to Reproduce
   - Expected Result
   - Actual Result
   - Evidence
   - Fix Summary
   - Retest Result
   - Owner Sign-Off
5. Initial rows for "No open defects recorded yet" without claiming owner sign-off.
6. Clear policy that deployment remains parked until owner/operator approval.

Add or extend a focused feature test, preferably:

- `laravel/tests/Feature/Phase20HandsOnAcceptanceTest.php`

The tests must verify:

1. `PRODUCT_ACCEPTANCE_DEFECT_LOG.md` exists and contains the required columns/statuses/severities.
2. `OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md` still contains the 15-step walkthrough headings/flow.
3. `AccountantAcceptanceSeeder` can run and provides the minimum data needed for the walkthrough.
4. Representative walkthrough routes from the script load for an authorized `SUPER_ADMIN` user.
5. Representative accountant/report routes load for an authorized `ACCOUNTANT` or `AUDITOR` user where the existing RBAC supports that role.
6. Guest users are redirected from protected walkthrough routes.
7. No forbidden scope assumptions were introduced by this slice.
8. No raw secrets were written into Phase 20 docs/tests/seeders.

Use existing factories, seeders, roles, and route names. Do not hardcode production credentials.

## Documentation

Create `PHASE_20_SLICE_1_REPORT.md` with:

- exact files changed
- acceptance defect register summary
- walkthrough baseline test coverage
- no-scope scan classification
- secret scan classification
- verification command results
- remaining risks or deferred items for Slice 2

Update:

- `PHASE_20_HANDS_ON_ACCEPTANCE_DEFECT_CLOSURE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

## Verification Commands

Run from `laravel/` and report exact results:

```powershell
vendor/bin/pint --test
php artisan test --filter=Phase20HandsOnAcceptanceTest --compact
php artisan test --filter=Phase19AccountantAcceptanceTest --compact
php artisan security:route-audit --strict
npm run typecheck
```

Run source scans and classify matches without storing secret values:

```powershell
rg -n "dangerouslySetInnerHTML|<select|<option|type=\"date\"|window\\.location\\.href" laravel/resources/js/Pages laravel/resources/js/Components
rg -n "company_id|tenant_id|currentCompany|currentTenant|Spatie Teams" PHASE_20*.md PRODUCT_ACCEPTANCE_DEFECT_LOG.md laravel/tests/Feature/Phase20HandsOnAcceptanceTest.php
```

Also run a secret scan for actual credentials/tokens across Phase 20 files, the defect log, tests, and seeders. Report whether any real secret values were found.

## Final Rule

Stop after Phase 20 Slice 1. Do not start Slice 2.
