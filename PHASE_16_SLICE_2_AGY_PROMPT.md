# MINI ERP - PHASE 16 SLICE 2 - GL PROJECT AND COST CENTER DIMENSIONS

You are continuing the active Laravel 13 + Inertia + React Mini ERP repository.

Implement this slice only. Do not start Slice 3, budgets, forecasting, department modeling, project security, or deployment.

## Goal

Add optional Project and Cost Center dimensions to manual journal lines and immutable posted ledger entries.

This slice gives accounting users the ability to tag each manual journal line with:

- `project_id`
- `cost_center_id`

The dimensions must be preserved when the journal is posted and mirrored when the posted journal is reversed.

## Required Source Reading

Before editing, inspect these files:

- `NO_MULTI_TENANT_POLICY.md`
- `PHASE_16_PROJECTS_COST_CENTERS_BUDGETING.md`
- `PHASE_16_SLICE_1_REPORT.md`
- `laravel/database/migrations/2026_08_21_100000_create_phase2_accounting_core_tables.php`
- `laravel/database/migrations/2026_08_28_010000_create_phase16_project_and_cost_center_tables.php`
- `laravel/app/Domain/Accounting/DraftLine.php`
- `laravel/app/Application/Accounting/JournalDraftService.php`
- `laravel/app/Application/Accounting/PostingEngine.php`
- `laravel/app/Application/Accounting/ReversalService.php`
- `laravel/app/Http/Controllers/AccountingController.php`
- `laravel/resources/js/Pages/Accounting/JournalForm.tsx`
- `laravel/resources/js/Pages/Accounting/JournalDetail.tsx`
- `laravel/resources/js/locales/en.json`
- `laravel/resources/js/locales/ar.json`
- `laravel/lang/ar.json`

## Non-Negotiable Rules

- No multi-tenant architecture.
- No `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, `currentBranch`, or Spatie Teams.
- Do not add Company, Branch, Department, Employee, Customer, Supplier, or User ownership to Project or CostCenter.
- Do not add `project_id` or `cost_center_id` to `journal_entry` header.
- Do not add project/cost-center columns to operational documents in this slice.
- Do not make dimensions mandatory.
- Do not alter existing money math, tax math, branch operational dimension behavior, stock costing behavior, fixed asset posting, payroll posting, rental posting, or subledger behavior.
- Posted `ledger_entry` rows remain append-only and immutable.
- Use existing Spatie Activitylog-backed audit adapter only where audit is already appropriate.
- Controllers must stay focused; push logic into services/page-data helpers.
- Visible TSX text must be dictionary-backed through EN/AR locale files.
- No native `<select>` / `<option>`, no native `type="date"`, no `window.location.href`.
- Do not introduce fake demo data in this slice.

## Schema Requirements

Create one forward migration:

- `laravel/database/migrations/2026_08_28_020000_add_phase16_gl_dimensions_to_journal_and_ledger.php`

Add nullable UUID columns:

- `journal_line.project_id`
- `journal_line.cost_center_id`
- `ledger_entry.project_id`
- `ledger_entry.cost_center_id`

Constraints:

- FK `journal_line.project_id -> project.id` with `restrictOnDelete`
- FK `journal_line.cost_center_id -> cost_center.id` with `restrictOnDelete`
- FK `ledger_entry.project_id -> project.id` with `restrictOnDelete`
- FK `ledger_entry.cost_center_id -> cost_center.id` with `restrictOnDelete`

Indexes:

- `journal_line(project_id)`
- `journal_line(cost_center_id)`
- `ledger_entry(project_id, financial_period_id, entry_date)`
- `ledger_entry(cost_center_id, financial_period_id, entry_date)`

Do not use cascade delete. A project/cost center referenced by accounting records must not be deletable through DB cascade.

Down migration must drop FKs/indexes/columns safely.

## Model Requirements

Update only relevant models:

- `JournalLine`
- `LedgerEntry`
- `Project`
- `CostCenter`

Add fillable/casts where appropriate and relationships:

- `JournalLine::project()`
- `JournalLine::costCenter()`
- `LedgerEntry::project()`
- `LedgerEntry::costCenter()`
- `Project::journalLines()`
- `Project::ledgerEntries()`
- `CostCenter::journalLines()`
- `CostCenter::ledgerEntries()`

Do not add any company/branch/tenant relationships.

## Domain / Service Requirements

Update `DraftLine` to include nullable `projectId` and `costCenterId`.

Update `JournalDraftService`:

- Accept nullable `project_id` and `cost_center_id` in line data.
- Validate referenced Project exists and `is_active = true`.
- Validate referenced CostCenter exists and `is_active = true`.
- Store dimensions on `journal_line`.
- Preserve existing branch behavior exactly as-is.
- Preserve balancing, period, currency, transaction-currency, and stale-lock behavior.

Update `PostingEngine`:

- Load line project/cost center relationships or validate them inside the posting transaction.
- Reject posting if a referenced project/cost center is missing or inactive at posting time.
- Copy `journal_line.project_id` and `journal_line.cost_center_id` into `ledger_entry`.
- Keep idempotency and one durable post behavior unchanged.
- Keep control account protection unchanged.

Update `ReversalService`:

- When creating reversal journal lines, copy `project_id` and `cost_center_id` from the original lines.
- Posting the reversal must create reversal ledger rows with the same dimensions as the reversal lines.

## Controller / Request Requirements

Update the manual journal controller flow only.

- Validate `lines.*.project_id` as nullable UUID existing in `project,id`.
- Validate `lines.*.cost_center_id` as nullable UUID existing in `cost_center,id`.
- Pass active project/cost-center selector options to journal create/edit pages.
- Do not expose project/cost-center selectors for operational documents in this slice.

## Frontend Requirements

Update only the manual journal pages needed for this slice:

- `JournalForm.tsx`
- `JournalDetail.tsx` if it displays line dimensions
- shared type definitions if needed
- EN/AR dictionaries

Behavior:

- Each journal line can optionally select a Project and Cost Center.
- Selectors must use `SearchableSelect`.
- Show only active Project and active CostCenter options.
- Labels must include code + localized name.
- Empty value must be allowed.
- Existing two-line default journal form behavior must remain.
- Keep responsive accounting-friendly table layout; avoid card-inside-card patterns.

## Validation Messages

Add Arabic translations in `laravel/lang/ar.json` for any new backend messages.

Suggested messages:

- `Selected project is inactive or missing.`
- `Selected cost center is inactive or missing.`
- `Cannot post journal line with inactive project [:code].`
- `Cannot post journal line with inactive cost center [:code].`

## Tests Required

Add `laravel/tests/Feature/Phase16Slice2GlDimensionTest.php`.

Minimum tests:

1. Migration/schema test confirms columns, foreign keys behavior, nullable defaults, indexes where introspectable, and no tenant/company columns.
2. Manual journal creation can store project and cost center on individual journal lines.
3. Manual journal posting copies dimensions from `journal_line` to immutable `ledger_entry`.
4. Reversal copies dimensions to reversal `journal_line` and reversal `ledger_entry`.
5. Inactive project cannot be used on draft creation/update or posting.
6. Inactive cost center cannot be used on draft creation/update or posting.
7. Deleting a project or cost center referenced by journal/ledger records is blocked by restrict behavior or guarded service behavior.
8. Journal form Inertia props include active project/cost-center options and exclude inactive records.
9. UI source scan test confirms no native `<select>`, `<option>`, `type="date"`, `window.location.href`, or hardcoded visible project/cost-center option labels.
10. Scope scan test confirms this slice introduced no `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, `currentBranch`, or Spatie Teams references outside existing historical/policy guard files.

Also run existing focused regression tests:

- `php artisan test --filter=Phase16Slice1ProjectCostCenterTest --compact`
- `php artisan test --filter=Phase15ProductHardeningTest --compact`
- `php artisan test --testsuite=Concurrency --compact`

## Verification Commands

Run and report exact results:

```bash
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=Phase16Slice2GlDimensionTest --compact
php artisan test --filter=Phase16Slice1ProjectCostCenterTest --compact
php artisan test --filter=Phase15ProductHardeningTest --compact
php artisan test --testsuite=Concurrency --compact
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

If full `php artisan test --compact` is too slow, do not claim it passed. Report timeout or skipped status honestly.

## Documentation / Report Requirements

Create `PHASE_16_SLICE_2_REPORT.md` with:

- files changed
- migration applied
- schema diff
- service changes
- UI changes
- tests added
- verification results
- remaining risks
- confirmation that operational documents were not modified
- confirmation that no tenant/company/current-context assumptions were introduced

Update:

- `PHASE_16_PROJECTS_COST_CENTERS_BUDGETING.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

Mark Slice 2 as complete only if all required targeted checks pass.
