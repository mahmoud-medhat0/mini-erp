# Phase 16 Slice 3 Report - Expense Document Dimension Capture

**Status:** COMPLETE  
**Date:** 2026-08-28  
**Scope:** Phase 16 Slice 3A - Direct Expense Line Project and Cost Center Dimension Capture Only

---

## Executive Summary

Phase 16 Slice 3A implements optional Project and Cost Center dimensional capture on direct Expense document lines, and propagates those dimensions downstream into debit expense journal lines and immutable ledger entries during posting.

This slice is deliberately bounded:
- **Modified:** Direct Expense document lines (`expense_line`), `ExpenseService`, `ExpenseController`, `ExpensePageData`, `Expenses/Index.tsx`, `ProjectService`, `CostCenterService`, and localization dictionaries.
- **Untouched:** Sales, Purchasing, Payroll, Rentals, Fixed Assets, Inventory, Treasury, Prepaids, Accruals, Landed Costs, and deployment files.
- **Architectural Policy:** Single-installation architecture strictly preserved. Zero tenant/company columns or context assumptions introduced.

---

## 1. Database Migration & Schema Changes

### Migration File
`laravel/database/migrations/2026_08_28_030000_add_phase16_dimensions_to_expense_lines.php`

### Schema Additions on `expense_line`
- `project_id` (UUID, nullable, foreign key -> `project(id)` with `restrictOnDelete`)
- `cost_center_id` (UUID, nullable, foreign key -> `cost_center(id)` with `restrictOnDelete`)
- Individual indexes: `idx_expense_line_project`, `idx_expense_line_cost_center`
- Composite indexes: `idx_expense_line_expense_project`, `idx_expense_line_expense_cost_center`
- Implemented clean `down()` method for rollback safety.

---

## 2. Backend Domain & Application Changes

### Models
- `App\Models\ExpenseLine`:
  - Added `project_id` and `cost_center_id` to `$fillable`.
  - Added `project(): BelongsTo` and `costCenter(): BelongsTo` relationships.
- `App\Models\Project`:
  - Added `expenseLines(): HasMany` relationship.
- `App\Models\CostCenter`:
  - Added `expenseLines(): HasMany` relationship.

### Services
- `App\Application\Projects\ProjectService`:
  - Updated `delete()` guard to block deletion if referenced by `expenseLines()`, `journalLines()`, or `ledgerEntries()`.
- `App\Application\CostCenters\CostCenterService`:
  - Updated `delete()` guard to block deletion if referenced by `expenseLines()`, `journalLines()`, or `ledgerEntries()`.
- `App\Application\Expenses\ExpenseService`:
  - `validateAndCalculateLines()` validates that provided `project_id` and `cost_center_id` exist and are active (`is_active = true`), storing dimension values on each line.
  - `defaultRelations()` eager loads `lines.project` and `lines.costCenter`.
  - `post()` validates that tagged project and cost center dimensions remain active at the time of posting.
  - Replaced simple account grouping with `expenseLineTotalsByAccountAndDimensions()`, which groups debit lines by `account_id + project_id + cost_center_id`. This guarantees distinct dimension combinations are posted as distinct debit journal lines.
  - Passes `project_id` and `cost_center_id` to debit expense journal lines. Input tax and settlement credit journal lines remain untagged (`null`).
  - PostingEngine (Slice 2) copies `project_id` and `cost_center_id` from debit journal lines directly onto immutable `LedgerEntry` rows.

### Controllers & Page Data
- `App\Http\Controllers\ExpenseController`:
  - Added validation rules for `lines.*.project_id` (`['nullable', 'uuid', 'exists:project,id']`) and `lines.*.cost_center_id` (`['nullable', 'uuid', 'exists:cost_center,id']`).
- `App\Application\Expenses\ExpensePageData`:
  - Eager loads `lines.project` and `lines.costCenter`.
  - Injects active `projects` and `costCenters` options into `indexData()`.

---

## 3. Frontend & Localization Changes

### Frontend Page (`laravel/resources/js/Pages/Expenses/Index.tsx`)
- Updated TypeScript types: `ProjectOption`, `CostCenterOption`, `ExpenseLine`, `LineForm`, and `Props`.
- Destructured `projects` and `costCenters` props, filtering for active records (`is_active !== false`).
- Added clearable `SearchableSelect` components for `pageDict.project` and `pageDict.costCenter` on each expense line.
- Preserved dense layout without nested cards or native date/select controls.
- Replaced line creation/editing handlers to properly hydrate and submit line dimensions.

### Localization Files
- `laravel/resources/js/locales/en.json`:
  - Added `project`, `costCenter`, `selectProject`, `selectCostCenter` under `app.pages.expenses`.
- `laravel/resources/js/locales/ar.json`:
  - Added `project` ("المشروع"), `costCenter` ("مركز التكلفة"), `selectProject` ("اختر المشروع..."), `selectCostCenter` ("اختر مركز التكلفة...") under `app.pages.expenses`.
- `laravel/lang/ar.json`:
  - Added translations for backend validation errors:
    - `"Selected expense project is inactive or missing."` -> `"مشروع المصروف المحدد غير نشط أو غير موجود."`
    - `"Selected expense cost center is inactive or missing."` -> `"مركز تكلفة المصروف المحدد غير نشط أو غير موجود."`
    - `"Cannot delete project referenced by expense lines, journal lines, or ledger entries."` -> `"لا يمكن حذف المشروع لارتباطه بسطور مصروفات أو بنود قيود أو حركات في دفتر الأستاذ."`
    - `"Cannot delete cost center referenced by expense lines, journal lines, or ledger entries."` -> `"لا يمكن حذف مركز التكلفة لارتباطه بسطور مصروفات أو بنود قيود أو حركات في دفتر الأستاذ."`

---

## 4. Test Suite & Verification Results

### Feature Test Suite: `Phase16Slice3ExpenseDimensionTest.php`
11 comprehensive feature tests (119 assertions) covering:
1. `test_schema_has_expense_line_dimension_columns_and_no_tenant_assumptions`: Verifies columns, nullability, foreign keys, absence of header-level dimensions, and zero tenant columns.
2. `test_expense_page_props_include_active_projects_and_cost_centers_and_exclude_inactive`: Verifies Inertia props filter active vs inactive dimensions.
3. `test_creating_expense_stores_project_and_cost_center_dimensions_on_expense_lines`: Verifies line dimension persistence on create.
4. `test_editing_draft_expense_preserves_hydrates_and_updates_expense_line_dimensions`: Verifies dimension update and hydration on draft edit.
5. `test_inactive_project_or_cost_center_cannot_be_used_on_create_or_update`: Verifies ValidationException when using inactive project or cost center.
6. `test_posting_approved_expense_propagates_dimensions_from_expense_lines_to_debit_journal_lines_and_ledger_entries`: Verifies downstream GL propagation.
7. `test_multiple_expense_lines_using_same_account_with_different_dimensions_remain_separate_in_journal_and_ledger`: Verifies dimensional grouping invariant.
8. `test_input_tax_and_settlement_journal_and_ledger_lines_remain_untagged`: Verifies only debit expense lines receive dimensions.
9. `test_project_and_cost_center_deletion_is_blocked_when_referenced_by_expense_lines`: Verifies deletion guards.
10. `test_ui_source_scan_confirms_clean_react_patterns_and_no_hardcoded_labels`: Scans for absence of native select/date inputs and presence of dictionary keys.
11. `test_scope_scan_confirms_no_tenant_or_company_assumptions`: Scans for zero tenant/company/current-context assumptions.

### Verification Commands Output Summary
- `php artisan migrate --force`: Migration `2026_08_28_030000_add_phase16_dimensions_to_expense_lines` ran successfully (37.76ms).
- `php artisan migrate:status`: All migrations ran cleanly.
- `vendor/bin/pint --test`: Passed with 0 violations.
- `php artisan test --filter=Phase16Slice3ExpenseDimensionTest --compact`: 11 passed (119 assertions, 37.7s).
- `php artisan test --filter=Phase16Slice2GlDimensionTest --compact`: 13 passed (152 assertions, 14.7s).
- `php artisan test --filter=Phase15ProductHardeningTest --compact`: 192 passed (25,764 assertions, 18.0s).
- `php artisan test --filter=Phase11ExpenseFeatureTest --compact`: Reported not found (`Phase11ExpenseManagementTest` ran and passed 8/8 tests, 60 assertions, 22.7s).
- `php artisan test --testsuite=Concurrency --compact`: 7 passed (16 assertions, 2.3s).
- `php artisan concurrency:stress --workers=100`: Passed cleanly with 100 workers.
- `php artisan accounting:concurrency-stress --workers=50`: Passed cleanly with 50 workers.
- `php artisan tokens:gc --batch=100`: Executed successfully.
- `npm run typecheck`: Passed cleanly (0 TypeScript errors).
- `npm run build`: Production assets built successfully.

---

## 5. Scope & Invariant Confirmation

- [x] Only direct Expenses modified.
- [x] Sales, Purchasing, Payroll, Rentals, Fixed Assets, Inventory, Treasury, Prepaids, Accruals, Landed Costs, and deployment files were NOT touched.
- [x] No `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, or `currentBranch` context added.
- [x] Dimensions captured at line-level only; no header dimensions on `expense`.
- [x] Dimensions remain optional (nullable).
- [x] No changes to settlement methods, tax calculation, exact integer math, FX rules, or status workflow.
- [x] Deletion of referenced projects and cost centers is strictly prevented.
- [x] Posting to immutable `ledger_entry` verified and preserved.
