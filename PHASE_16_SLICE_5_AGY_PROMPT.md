# MINI ERP - PHASE 16 SLICE 5 - BUDGET VERSION AND MONTHLY BUDGET LINE FOUNDATION

You are continuing the active Laravel 13 + Inertia + React Mini ERP repository.

This is a bounded budget master-data and workflow slice. Keep it small, strict, and reviewable.

## Objective

Implement annual budget versions and monthly budget lines under:

- `/budgeting/budgets`

This slice creates budget setup and lifecycle only.

Do not build budget-vs-actual reports in this slice.

Do not post anything to GL in this slice.

## Must Read First

- `PHASE_16_PROJECTS_COST_CENTERS_BUDGETING.md`
- `PHASE_16_SLICE_1_REPORT.md`
- `PHASE_16_SLICE_2_REPORT.md`
- `PHASE_16_SLICE_3_REPORT.md`
- `PHASE_16_SLICE_4_REPORT.md`
- `NO_MULTI_TENANT_POLICY.md`
- `spec/MASTER_ERP_SPEC.md` section C20
- `spec/PERMISSION_MATRIX.md`
- `laravel/config/erp_rbac.php`
- `laravel/app/Models/Project.php`
- `laravel/app/Models/CostCenter.php`
- `laravel/app/Application/Projects/ProjectService.php`
- `laravel/resources/js/Pages/Projects/Index.tsx`
- `laravel/app/Application/Accounting/AccountingAccountMappingService.php`
- `laravel/app/Application/Reports/ReportPageOptions.php`
- `laravel/routes/web.php`

## Non-Negotiable Scope

Do not touch:

- Sales
- Purchasing
- Inventory
- Fixed Assets
- Payroll
- Rentals
- Treasury
- Tax
- Expenses
- Period Close behavior
- PostingEngine behavior
- Ledger immutability

Do not create:

- budget-vs-actual report
- variance report
- forecast tables
- recurring budgets
- department model
- project manager/user assignment
- branch budget scope
- cash-flow forecast
- financial-statement budget toggle

Do not introduce:

- `company_id`
- `tenant_id`
- `currentCompany`
- `currentTenant`
- Spatie Teams
- company-owned budgets
- branch-owned budgets
- user-owned budgets

Branch remains an approved operational dimension in earlier bounded workflows, but it is not part of this budget schema unless a later owner decision explicitly adds branch budgeting.

## Schema

Create one forward migration:

- `2026_08_28_040000_create_phase16_budget_tables.php`

Create table `budget`:

- `id` UUID primary key
- `fiscal_year_id` UUID foreign key to `fiscal_year.id`, restrict on delete
- `code` string, unique globally
- `version_code` string
- `name` JSON translatable
- `description` nullable text
- `status` string
- `default_currency` string size 3 foreign key to `currency.code`, restrict on delete, cascade on update
- `submitted_by` nullable foreign key to `users.id`, null on delete
- `submitted_at` nullable timestamp
- `approved_by` nullable foreign key to `users.id`, null on delete
- `approved_at` nullable timestamp
- `activated_by` nullable foreign key to `users.id`, null on delete
- `activated_at` nullable timestamp
- `archived_by` nullable foreign key to `users.id`, null on delete
- `archived_at` nullable timestamp
- `cancelled_by` nullable foreign key to `users.id`, null on delete
- `cancelled_at` nullable timestamp
- `created_by` nullable foreign key to `users.id`, null on delete
- `updated_by` nullable foreign key to `users.id`, null on delete
- `lock_version` unsigned integer default 1
- timestamps

Add unique index:

- fiscal year plus version code must be unique

Add indexes:

- fiscal_year_id plus status
- status
- default_currency

Create table `budget_line`:

- `id` UUID primary key
- `budget_id` UUID foreign key to `budget.id`, cascade on delete
- `financial_period_id` UUID foreign key to `financial_period.id`, restrict on delete
- `account_id` UUID foreign key to `account.id`, restrict on delete
- `project_id` nullable UUID foreign key to `project.id`, restrict on delete
- `cost_center_id` nullable UUID foreign key to `cost_center.id`, restrict on delete
- `currency` string size 3 foreign key to `currency.code`, restrict on delete, cascade on update
- `amount_minor` signed bigint default 0
- `notes` nullable text
- `created_by` nullable foreign key to `users.id`, null on delete
- `updated_by` nullable foreign key to `users.id`, null on delete
- timestamps

Add indexes:

- budget_id plus financial_period_id
- budget_id plus account_id
- budget_id plus project_id
- budget_id plus cost_center_id
- budget_id plus currency
- financial_period_id plus account_id plus currency
- project_id plus cost_center_id plus currency

Do not add nullable unique constraints that behave differently across PostgreSQL and SQLite. Enforce duplicate line identity in the service while the budget row is locked.

## Status Workflow

Allowed statuses:

- draft
- submitted
- approved
- active
- archived
- cancelled

Allowed transitions:

- draft to submitted
- draft to cancelled
- submitted to approved
- submitted to cancelled
- approved to active
- approved to archived
- active to archived

Rules:

- New budget starts as draft.
- Draft budgets are editable.
- Submitted budgets are not editable except cancel.
- Approved budgets are immutable except activate or archive.
- Active budgets are immutable except archive.
- Archived and cancelled budgets are immutable terminal states.
- Only one active budget is allowed per fiscal year. Activating a budget must archive any other active budget in the same fiscal year inside the same transaction.
- Budget deletion is allowed only for draft budgets.
- Submission requires at least one line and total amount across all lines greater than zero.
- Approval requires submitted status.
- Activation requires approved status.
- Cancellation stores actor and timestamp.
- Archive stores actor and timestamp.
- Every status transition must write Spatie Activitylog through existing `AuditLogger`.

## Budget Line Rules

Budget lines are monthly planning rows, not accounting entries.

Rules:

- `financial_period_id` must belong to the selected budget fiscal year.
- `account_id` must exist and be active.
- `project_id` may be null; when provided, project must exist and be active.
- `cost_center_id` may be null; when provided, cost center must exist and be active.
- `currency` must exist in `currency.code`.
- `amount_minor` must be integer and greater than or equal to zero.
- Two lines in the same draft budget cannot share the same tuple:
  - financial_period_id
  - account_id
  - project_id or null
  - cost_center_id or null
  - currency
- Header `default_currency` is only the default for new line entry. It does not force all lines to share one currency.
- No floating point math.

## Models

Create:

- `App\Models\Budget`
- `App\Models\BudgetLine`

Relationships:

Budget:

- fiscalYear
- lines
- submitter
- approver
- activator
- archiver
- canceller
- creator
- updater

BudgetLine:

- budget
- financialPeriod
- account
- project
- costCenter
- currencyRef
- creator
- updater

Extend reverse relationships only where useful:

- FiscalYear budgets
- FinancialPeriod budgetLines
- Account budgetLines
- Project budgetLines
- CostCenter budgetLines

Do not add Company or Branch relationships.

## Services And Page Data

Create:

- `App\Application\Budgeting\BudgetService`
- `App\Application\Budgeting\BudgetPageData`

BudgetService must own all write behavior:

- create
- update
- replace draft lines
- delete draft budget
- submit
- approve
- activate
- archive
- cancel

Use transactions and `lockForUpdate` for update and status transitions.

Use `lock_version` optimistic concurrency on header edits and line replacement.

Keep duplicate-line detection in the service.

Do not put business workflow logic in controllers.

BudgetPageData must build list/detail option data for Inertia:

- budgets paginated
- filters
- fiscal years
- financial periods grouped or listed with fiscal year context
- accounts
- projects
- cost centers
- currencies
- permission booleans if existing page patterns use them

Use active accounts/projects/cost centers for creating new lines. Inactive historical dimensions may be shown when already attached to existing lines.

## Controllers And Routes

Create:

- `App\Http\Controllers\Budgeting\BudgetController`

Routes:

- `GET /budgeting/budgets`
- `POST /budgeting/budgets`
- `PATCH /budgeting/budgets/{budget}`
- `DELETE /budgeting/budgets/{budget}`
- `POST /budgeting/budgets/{budget}/submit`
- `POST /budgeting/budgets/{budget}/approve`
- `POST /budgeting/budgets/{budget}/activate`
- `POST /budgeting/budgets/{budget}/archive`
- `POST /budgeting/budgets/{budget}/cancel`

Permissions:

- list/view requires `budgeting.view` and `view_financials`
- create requires `budgeting.create` and `view_financials`
- update requires `budgeting.edit` and `view_financials`
- delete requires `budgeting.delete` and `view_financials`
- submit requires `budgeting.edit` and `view_financials`
- approve requires `budgeting.approve` and `view_financials`
- activate requires `budgeting.approve` and `view_financials`
- archive requires `budgeting.approve` and `view_financials`
- cancel requires `budgeting.edit` and `view_financials`
- export is not required in Slice 5

Controllers must stay thin:

- validate
- authorize through middleware or Gate
- call service
- redirect with translated Laravel flash messages

## Frontend

Create:

- `resources/js/Pages/Budgeting/Budgets.tsx`

Update:

- `resources/js/Components/AppLayout.tsx`
- `resources/js/Types/index.ts`
- `resources/js/locales/en.json`
- `resources/js/locales/ar.json`
- `laravel/lang/ar.json` if new Laravel validation or flash messages need Arabic

UX requirements:

- Accountant-friendly list with filters for fiscal year, status, and search.
- Create/edit modal or drawer using existing primitives.
- Budget header section and monthly line editor.
- Lines can select period, account, project, cost center, currency, and amount.
- Use exact minor-unit amount inputs consistent with existing money entry patterns.
- Show total by currency.
- Show status badges and timestamps.
- Show action buttons only when status and permissions allow them.
- Confirm irreversible or lifecycle-changing actions.
- Use `SearchableSelect`, `DatePicker` only if date entry is needed, `Button`, `Modal`, `Card`, `StatusBadge`, `EmptyState`, `tableClasses`, `ToggleSwitch` only as appropriate.
- Do not use native HTML select.
- Do not use native date input.
- Do not use `window.location.href`.
- No hardcoded visible TSX labels. All visible text must come from dictionaries.
- Do not use loose pagination link types. Use existing `PaginationLink`.

## Reports Hub

Do not add Budget vs Actual report cards in this slice.

If adding a navigation item, it must point only to `/budgeting/budgets` and require budget permissions.

## Tests Required

Create:

- `laravel/tests/Feature/Phase16Slice5BudgetFoundationTest.php`

Minimum tests:

1. Schema exists with `budget` and `budget_line`, expected columns, foreign keys by behavior, and no company or tenant columns.
2. Budget model relationships load fiscal year, lines, account, project, cost center, period, currency, and actor users.
3. Budget creation stores a draft header and monthly lines with integer minor amounts.
4. Duplicate budget version code in the same fiscal year is rejected.
5. Duplicate line tuple inside one budget is rejected.
6. Line financial period must belong to the budget fiscal year.
7. Inactive account, project, or cost center cannot be used for new draft lines.
8. Draft budget update replaces lines, increments lock_version, and rejects stale lock_version.
9. Submit requires at least one line and positive total.
10. Workflow transitions enforce draft to submitted to approved to active.
11. Activating a budget archives any other active budget for the same fiscal year.
12. Approved and active budgets reject header and line edits.
13. Archived and cancelled budgets are terminal and immutable.
14. Delete is allowed only for draft budgets.
15. No journal_entry or ledger_entry rows are created by any budget action.
16. Routes enforce `budgeting.*` plus `view_financials`.
17. Inertia page receives paginated budgets, periods, accounts, projects, cost centers, currencies, and filters.
18. UI source scan proves no native select, native date input, unsafe redirect, loose pagination link type, or hardcoded visible report text in the new page.
19. Source scan proves no tenant, company, current context, branch budget scope, or Spatie Teams terms were introduced in Slice 5 implementation files.

Keep tests deterministic.

## Verification Commands

Run and report exact results:

- `php artisan migrate --force`
- `php artisan migrate:status`
- `php artisan test --filter=Phase16Slice5BudgetFoundationTest --compact`
- `php artisan test --filter=Phase16Slice4ProjectCostCenterReportsTest --compact`
- `php artisan test --filter=Phase16Slice3ExpenseDimensionTest --compact`
- `php artisan test --testsuite=Concurrency --compact`
- `vendor/bin/pint --test`
- `npm.cmd run typecheck`
- `npm.cmd run build`
- `php artisan concurrency:stress --workers=100`
- `php artisan accounting:concurrency-stress --workers=50`
- `php artisan tokens:gc --batch=100`

Do not claim the full test suite passed unless you actually run the full suite.

## Documentation Updates

Update:

- `PHASE_16_PROJECTS_COST_CENTERS_BUDGETING.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

Create:

- `PHASE_16_SLICE_5_REPORT.md`

The report must include:

- files changed
- migrations added
- schema summary
- workflow summary
- permissions summary
- exact no-GL-posting confirmation
- exact no multi-tenant confirmation
- UI hardcoded text and native control scan confirmation
- verification command results
- remaining risks

