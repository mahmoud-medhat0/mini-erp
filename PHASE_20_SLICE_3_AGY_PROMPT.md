# Mini ERP - Phase 20 Slice 3 Agy Prompt

Execute ONLY Phase 20 Slice 3: Validation Feedback, Permissions Clarity, and Action Availability.

Stop after this slice. Do not start Slice 4.

## Scope

Tighten the non-happy-path experience for the same workflows used during owner/accountant acceptance. The goal is to make invalid or unauthorized actions clear, localized, and safe.

This is not a security redesign and not a new ERP module.

## Non-Negotiable Rules

- No multi-tenant architecture and no company/tenant/security scope changes.
- Do not add `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, tenant context, branch security context, or Spatie Teams.
- Deployment remains parked.
- Do not change accounting math, tax math, stock costing, posting, numbering, idempotency, period close, workflow state transitions, or immutable ledger behavior except for narrowly proven defects.
- Do not weaken permissions or sensitive-action confirmation requirements.
- Do not store Telegram credentials, chat IDs, API keys, passwords, or production secrets in files.
- No hardcoded visible strings in React pages. Use dictionaries.
- Keep controllers thin. Validation belongs in FormRequests or application services.

## Required Review Before Editing

Inspect:

- `OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md`
- `PRODUCT_ACCEPTANCE_DEFECT_LOG.md`
- `laravel/app/Http/Requests`
- `laravel/app/Http/Controllers`
- `laravel/app/Application`
- `laravel/resources/js/Pages` for acceptance workflow pages
- `laravel/resources/js/i18n/en.json`
- `laravel/resources/js/i18n/ar.json`
- `laravel/config/erp_rbac.php`
- `laravel/tests/Feature/Phase15ProductHardeningTest.php`
- `laravel/tests/Feature/SecurityHardeningTest.php`
- `laravel/tests/Feature/Phase19AccountantAcceptanceTest.php`
- `laravel/tests/Feature/Phase20HandsOnAcceptanceTest.php`

## Required Implementation

Inspect the acceptance workflow routes for:

1. Missing or unclear validation feedback.
2. Missing permission-aware visible actions.
3. Mutating actions that do not provide clear success/error flash messages.
4. Sensitive actions that need confirmation reason but do not display clear localized guidance.
5. Pages that show actions the current user cannot execute when backend permissions would reject them.

Make only narrow fixes. Prefer:

- FormRequest extraction if a controller still contains bulky validation logic.
- Shared helper/service use where existing patterns already exist.
- Dictionary additions for visible UI text.
- Tests that lock the behavior.

Do not broaden role privileges just to make a page pass. If an action is correctly forbidden, the UI should make that clear by hiding or disabling the action according to existing permission props.

## Tests Required

Add or extend `Phase20HandsOnAcceptanceTest` to cover:

1. Invalid create/post/update attempts fail without financial mutation.
2. Unauthorized personas cannot see or execute mutating acceptance actions.
3. Authorized personas can see expected actions.
4. Sensitive actions still require confirmation where applicable.
5. Error/success messaging remains localized and dictionary-backed for touched pages.

## Documentation

Create `PHASE_20_SLICE_3_REPORT.md` with:

- validation/permission issues found
- exact fixes made
- no-op pages and why
- tests added/changed
- route audit result
- no-scope scan result
- UI unsafe-control scan result
- secret scan result
- remaining risks for Slice 4

Update:

- `PHASE_20_HANDS_ON_ACCEPTANCE_DEFECT_CLOSURE.md`
- `PRODUCT_ACCEPTANCE_DEFECT_LOG.md` if a real issue was found/fixed
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

## Verification Commands

Run from `laravel/` and report exact results:

```powershell
vendor/bin/pint --test
php artisan test --filter=Phase20HandsOnAcceptanceTest --compact
php artisan test --filter=SecurityHardeningTest --compact
php artisan test --filter=Phase19AccountantAcceptanceTest --compact
php artisan security:route-audit --strict
npm run typecheck
npm run build
```

Run and classify:

```powershell
rg -n "dangerouslySetInnerHTML|<select|<option|type=\"date\"|window\\.location\\.href" laravel/resources/js/Pages laravel/resources/js/Components
rg -n "company_id|tenant_id|currentCompany|currentTenant|Spatie Teams" PHASE_20*.md PRODUCT_ACCEPTANCE_DEFECT_LOG.md laravel/app laravel/routes laravel/resources/js laravel/tests/Feature/Phase20HandsOnAcceptanceTest.php
```

Also run a secret scan for actual credentials/tokens across touched files. Report whether any real secret values were found.

## Final Rule

Stop after Phase 20 Slice 3. Do not start Slice 4.
