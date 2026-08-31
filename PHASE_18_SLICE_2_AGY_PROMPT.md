# Mini ERP - Phase 18 Slice 2 Agy Prompt

Execute ONLY Phase 18 Slice 2: Controller Clean-Code Boundary Gate.

This is a maintainability and regression-gate slice. Stop after this slice. Do not start Slice 3.

## Non-Negotiable Rules

- No multi-tenant architecture and no company/tenant/security scope changes.
- Do not change business behavior, accounting results, workflow transitions, or permissions.
- Do not perform deployment work.
- Controllers must stay thin and orchestration-only.
- If a controller violation is found, fix the smallest affected controller by moving query/composition/persistence detail into an existing or new service/FormRequest/page-data class.

## Objective

Add an automated clean-code gate proving controller boundaries remain sane after the large migration:

- Controllers should remain under 150 lines unless explicitly justified.
- Controllers should not contain large inline query composition, CSV construction, repeated option-building, or business posting math.
- Controllers should delegate page data to page-data/query services and mutations to application services.
- Form validation should use FormRequests when it becomes non-trivial.

## Required Review

Inspect:

- `laravel/app/Http/Controllers`
- `laravel/app/Application`
- `laravel/tests/Feature/Phase15ProductHardeningTest.php`

Do not invent broad architecture. This slice should add a guard and fix only concrete violations.

## Tests Required

Add to `Phase18ProductAcceptanceTest` or a new focused test:

1. Every controller under `app/Http/Controllers` is <= 150 physical lines, excluding comments is not required.
2. Controllers do not contain forbidden heavy-query fragments except allowlisted lightweight patterns:
   - `DB::table(`
   - long chained query composition directly in controller
   - inline CSV row loops
   - posting math helpers
3. Controllers may call `Inertia::render`, `$request->only`, `$request->validate` only for trivial cases, injected services, FormRequests, and redirect responses.
4. Known service-authorized controllers (`AttachmentController`, `NotificationController`) remain thin and session/entity authorized.
5. No new tenant/company scope terms introduced.

If current code already passes, do not refactor. Add the regression gate and report that no production code changes were required.

## Verification Commands

Run from `laravel/` and report exact results:

```powershell
vendor/bin/pint --test
php artisan test --filter=Phase18ProductAcceptanceTest --compact
php artisan test --filter=Phase15ProductHardeningTest --compact
php artisan security:route-audit --strict
npm run typecheck
```

Run `npm run build` only if frontend files changed.

## Final Report

Create `PHASE_18_SLICE_2_REPORT.md` with:

- controller audit summary
- exact files changed
- violations found/fixed or confirmation that none were found
- tests added/changed
- verification results
- no-scope scan result
- remaining risks

Update:

- `PHASE_18_PRODUCT_ACCEPTANCE_UI_CLEAN_CODE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

Stop after Phase 18 Slice 2.
