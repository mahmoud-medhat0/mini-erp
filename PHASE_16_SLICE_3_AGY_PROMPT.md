# MINI ERP - PHASE 16 SLICE 3A - EXPENSE DOCUMENT DIMENSION CAPTURE

You are continuing the active Laravel 13 + Inertia + React Mini ERP repository.

Implement this slice only. This is a deliberately bounded micro-slice to avoid broad cross-module churn.

## Goal

Add optional Project and Cost Center capture to direct Expense lines only, then propagate those dimensions into the debit expense journal lines and posted ledger entries.

This slice must not touch Sales, Purchasing, Payroll, Rentals, Fixed Assets, Inventory, Treasury, Prepaids, Accruals, Landed Costs, or any deployment files.

## Required Source Reading

Before editing, inspect:

- `NO_MULTI_TENANT_POLICY.md`
- `PHASE_16_PROJECTS_COST_CENTERS_BUDGETING.md`
- `PHASE_16_SLICE_1_REPORT.md`
- `PHASE_16_SLICE_2_REPORT.md`
- `laravel/database/migrations/2026_08_25_070000_create_phase11_expense_tables.php`
- `laravel/app/Models/Expense.php`
- `laravel/app/Models/ExpenseLine.php`
- `laravel/app/Models/Project.php`
- `laravel/app/Models/CostCenter.php`
- `laravel/app/Application/Expenses/ExpenseService.php`
- `laravel/app/Application/Expenses/ExpensePageData.php`
- `laravel/app/Http/Controllers/ExpenseController.php`
- `laravel/resources/js/Pages/Expenses/Index.tsx`
- `laravel/resources/js/locales/en.json`
- `laravel/resources/js/locales/ar.json`
- `laravel/lang/ar.json`

## Non-Negotiable Rules

- No multi-tenant architecture.
- No `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, `currentBranch`, or Spatie Teams.
- Do not add Company, Branch, Department, Employee, Customer, Supplier, or User ownership to Project or CostCenter.
- Do not add dimensions to the `expense` header. Dimensions are line-level only.
- Do not make dimensions mandatory.
- Do not change settlement method rules, tax math, exact integer quantity/money math, FX restriction, branch operational behavior, payable entry behavior, attachment requirements, or status workflow.
- Do not add dimensions to `payable_entry`.
- Do not add dimensions to input tax or settlement journal lines by default.
- Only expense-account debit journal lines created from `expense_line` records should inherit `project_id` and `cost_center_id`.
- If multiple expense lines use the same expense account but different dimensions, do not merge them into one journal line. Group by account + project + cost center, or preserve per-line journal lines.
- Posted `ledger_entry` rows remain append-only and immutable.
- Visible TSX text must be dictionary-backed through EN/AR locale files.
- No native `<select>` / `<option>`, no native date input, no `window.location.href`.
- Do not introduce demo data in this slice.

## Schema Requirements

Create one forward migration:

- `laravel/database/migrations/2026_08_28_030000_add_phase16_dimensions_to_expense_lines.php`

Add nullable UUID columns to `expense_line`:

- `project_id`
- `cost_center_id`

Constraints:

- FK `expense_line.project_id -> project.id` with `restrictOnDelete`
- FK `expense_line.cost_center_id -> cost_center.id` with `restrictOnDelete`

Indexes:

- `expense_line(project_id)`
- `expense_line(cost_center_id)`
- `expense_line(expense_id, project_id)`
- `expense_line(expense_id, cost_center_id)`

Down migration must safely drop indexes, foreign keys, and columns.

## Model Requirements

Update:

- `ExpenseLine`
- `Project`
- `CostCenter`

Add:

- `ExpenseLine::project()`
- `ExpenseLine::costCenter()`
- `Project::expenseLines()`
- `CostCenter::expenseLines()`

Update fillable/casts if needed.

## Service Requirements

Update `ExpenseService`:

- Accept nullable `project_id` and `cost_center_id` in each incoming expense line.
- Validate that selected Project exists and `is_active = true`.
- Validate that selected CostCenter exists and `is_active = true`.
- Store dimensions on `expense_line` in create and update flows.
- Preserve dimensions when loading default relations.
- During posting:
  - Debit expense journal lines must receive the dimensions from their source expense lines.
  - If existing code groups expense lines by account, change grouping to account + project + cost center so separate dimension combinations remain separate.
  - Input tax journal line must remain with `project_id = null` and `cost_center_id = null`.
  - Settlement credit journal line must remain with `project_id = null` and `cost_center_id = null`.
  - Posted ledger rows inherit dimensions via existing `PostingEngine`.
- Keep payable entry creation unchanged.

Update `ProjectService` and `CostCenterService` delete guards:

- Prevent deleting a project/cost center referenced by `expense_line` in addition to existing journal/ledger guards.

## Controller Requirements

Update `ExpenseController` request validation:

- `lines.*.project_id`: nullable UUID exists in `project,id`
- `lines.*.cost_center_id`: nullable UUID exists in `cost_center,id`

Do not add new controller bloat. Keep validation only in controller; business validation belongs to service.

## Page Data Requirements

Update `ExpensePageData`:

- Eager load `lines.project` and `lines.costCenter`.
- Provide active project options and active cost center options to `Expenses/Index.tsx`.
- Exclude inactive projects/cost centers from selector props.

## Frontend Requirements

Update `laravel/resources/js/Pages/Expenses/Index.tsx` only where needed:

- Add optional Project and Cost Center selectors per expense line using `SearchableSelect`.
- Preserve existing DatePicker and SearchableSelect usage.
- Preserve existing create/edit behavior and default category/account/tax autofill.
- Existing rows opened for edit must hydrate their line dimensions.
- UI labels/placeholders must use dictionaries under `app.pages.expenses` or existing `app.accounting` keys.
- Avoid nested card patterns; keep current dense accounting layout.

Update shared TS types only if needed.

## Backend Messages

Add Arabic translations in `laravel/lang/ar.json` for any new backend messages, including:

- `Selected expense project is inactive or missing.`
- `Selected expense cost center is inactive or missing.`
- `Cannot delete project referenced by expense lines, journal lines, or ledger entries.`
- `Cannot delete cost center referenced by expense lines, journal lines, or ledger entries.`

## Tests Required

Add `laravel/tests/Feature/Phase16Slice3ExpenseDimensionTest.php`.

Minimum tests:

1. Schema test confirms `expense_line.project_id` and `expense_line.cost_center_id` exist, are nullable, and no tenant/company columns were introduced.
2. Expense page props include active projects/cost centers and exclude inactive records.
3. Creating an expense stores project/cost-center dimensions on expense lines.
4. Editing a draft expense preserves/hydrates and updates expense line dimensions.
5. Inactive project/cost center cannot be used on create or update.
6. Posting an approved expense propagates dimensions from expense lines to debit expense journal lines and posted ledger entries.
7. Multiple expense lines using the same expense account but different project/cost-center dimensions remain separate in journal/ledger output.
8. Input tax and settlement journal/ledger lines remain untagged.
9. Project/cost center deletion is blocked when referenced by expense lines.
10. UI source scan confirms no native `<select>`, `<option>`, native date input, `window.location.href`, and no hardcoded visible Project/Cost Center labels in `Expenses/Index.tsx`.
11. Scope scan confirms no tenant/company/current-context assumptions were introduced.

Also run focused regression tests:

- `php artisan test --filter=Phase16Slice3ExpenseDimensionTest --compact`
- `php artisan test --filter=Phase16Slice2GlDimensionTest --compact`
- `php artisan test --filter=Phase15ProductHardeningTest --compact`
- `php artisan test --filter=Phase11ExpenseFeatureTest --compact` if that test class exists; otherwise report not found.
- `php artisan test --testsuite=Concurrency --compact`

## Verification Commands

Run and report exact results:

```bash
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=Phase16Slice3ExpenseDimensionTest --compact
php artisan test --filter=Phase16Slice2GlDimensionTest --compact
php artisan test --filter=Phase15ProductHardeningTest --compact
php artisan test --filter=Phase11ExpenseFeatureTest --compact
php artisan test --testsuite=Concurrency --compact
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

If a named regression test class does not exist, report it honestly and run the closest expense test discovered by searching for `class .*Expense.*Test` under `laravel/tests`.

If full `php artisan test --compact` is too slow, do not claim it passed.

## Documentation / Report Requirements

Create `PHASE_16_SLICE_3_REPORT.md` with:

- files changed
- migration applied
- schema diff
- service changes
- UI changes
- tests added
- verification results
- confirmation that only direct Expenses were changed
- confirmation that Sales/Purchasing/Payroll/Rentals/Fixed Assets/Inventory/Treasury/Prepaids/Accruals/Landed Costs were not changed
- remaining risks
- confirmation that no tenant/company/current-context assumptions were introduced

Update:

- `PHASE_16_PROJECTS_COST_CENTERS_BUDGETING.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

Mark Slice 3 as complete only for direct Expenses. Do not claim all operational document dimension capture is complete.
