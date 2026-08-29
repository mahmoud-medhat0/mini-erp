# Mini ERP - Phase 18 Slice 1 Agy Prompt

Execute ONLY Phase 18 Slice 1: Safe Pagination Rendering and UI Safety Cleanup.

This is a narrow UI/security polish slice. Stop after this slice. Do not start Slice 2.

## Non-Negotiable Rules

- No multi-tenant architecture and no company/tenant/security scope changes.
- Do not change business logic, posting math, stock costing, tax, payroll, period close, numbering, idempotency, or workflow transitions.
- No deployment work.
- No hardcoded visible strings in React pages.
- Do not use `dangerouslySetInnerHTML`, native `<select>`, `<option>`, `type="date"`, or `window.location.href`.
- Preserve English/Arabic and RTL behavior.

## Objective

Remove the known unsafe pagination rendering from:

- `laravel/resources/js/Pages/Projects/Index.tsx`
- `laravel/resources/js/Pages/CostCenters/Index.tsx`

Prefer creating or extending a reusable primitive in `laravel/resources/js/Components/Primitives.tsx`, such as `PaginationControls`, that safely renders Laravel pagination labels without `dangerouslySetInnerHTML`.

## Implementation Guidance

- Laravel pagination labels may include HTML entities like `&laquo; Previous` and `Next &raquo;`.
- Render labels safely as text, not HTML.
- Decode only the small known HTML entities needed for pagination labels (`&laquo;`, `&raquo;`, `&amp;`, `&lt;`, `&gt;`, `&#039;`, `&quot;`) without injecting markup.
- Reuse existing `PaginationLink` type if suitable.
- Keep visual styling consistent with the existing compact accounting UI.
- Preserve `preserveScroll` and `preserveState`.
- Keep `totalRecords` label dictionary-backed using existing dictionaries.

## Tests Required

Add a focused `Phase18ProductAcceptanceTest` or extend an existing source-scan test to assert:

1. `Projects/Index.tsx` no longer contains `dangerouslySetInnerHTML`.
2. `CostCenters/Index.tsx` no longer contains `dangerouslySetInnerHTML`.
3. No `dangerouslySetInnerHTML` remains anywhere under `laravel/resources/js/Pages`.
4. New pagination primitive does not use `dangerouslySetInnerHTML`.
5. `Projects/Index.tsx` and `CostCenters/Index.tsx` still use `PaginationLink[]`.
6. No native `<select>`, `<option>`, `type="date"`, or `window.location.href` were introduced.
7. No new multi-tenant/company scope terms were introduced in Slice 1 files.

## Verification Commands

Run from `laravel/` and report exact results:

```powershell
vendor/bin/pint --test
php artisan test --filter=Phase18ProductAcceptanceTest --compact
php artisan test --filter=Phase16Slice1ProjectsCostCentersTest --compact
npm run typecheck
npm run build
```

If `Phase16Slice1ProjectsCostCentersTest` does not exist, find and run the closest existing Phase 16 project/cost-center test and report its exact name.

## Final Report

Create `PHASE_18_SLICE_1_REPORT.md` with:

- exact files changed
- unsafe rendering removed
- pagination rendering behavior
- tests added/changed
- verification results
- no-scope scan result
- UI unsafe-control scan result
- remaining risks

Update:

- `PHASE_18_PRODUCT_ACCEPTANCE_UI_CLEAN_CODE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

Stop after Phase 18 Slice 1.
