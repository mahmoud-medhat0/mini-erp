# MINI ERP - PHASE 16 SLICE 1 - PROJECT AND COST CENTER MASTER DATA FOUNDATION

You are continuing the existing Mini ERP Laravel 13 + Inertia + React + PostgreSQL repository.

This is a bounded implementation slice. Implement only Project and Cost Center master-data foundation.

## Required Scope

Implement:

- standalone `project` master data;
- standalone `cost_center` master data;
- Laravel models, migrations, services, controllers, routes, Inertia pages, translations, RBAC integration, attachment registry, audit logging, and tests;
- docs/status updates for Phase 16 Slice 1 only.

Do not implement GL tagging, budget lines, project profitability reports, department modeling, recurring templates, forecasting, or deployment work in this slice.

## Non-Negotiable Rules

- No multi-tenant architecture.
- No `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, `currentBranch`, or Spatie Teams.
- Do not add Company, Branch, Department, User, Employee, Customer, or Supplier ownership to Project or Cost Center.
- Project and Cost Center are standalone dimensions only in this slice.
- Department is not implemented in this slice.
- Branch is not a tenant/security boundary and must not scope project/cost-center permissions.
- Use existing Spatie Permission module/action style:
  - `projects.view`, `projects.create`, `projects.edit`, `projects.delete`, `projects.export`
  - `costCenters.view`, `costCenters.create`, `costCenters.edit`, `costCenters.delete`, `costCenters.export`
- Use Spatie Activitylog through the existing audit adapter/service pattern.
- Register `project` and `cost_center` in the attachment entity registry with server-side authorization.
- Visible TSX text must come from `resources/js/locales/en.json` and `resources/js/locales/ar.json`.
- Do not add native `<select>`, `<option>`, native `type="date"`, unsafe `window.location.href`, or loose pagination link types to Inertia pages.
- Use shared controls and patterns already present after Phase 15.
- Keep controllers under 150 lines. Extract service/page-data classes when needed.
- Do not weaken existing PostingEngine, RBAC, audit, attachment, notification, period, tax, stock, payroll, rental, or branch-operation invariants.

## Suggested Schema

Use singular table naming consistent with the current Laravel schema.

`project`:

- `id` UUID primary key
- `code` string unique
- `name` JSON/JSONB translatable
- `description` text nullable
- `status` string: `active`, `on_hold`, `completed`, `cancelled`
- `start_date` date nullable
- `end_date` date nullable
- `is_billable` boolean default false
- `is_active` boolean default true
- `created_by`, `updated_by` nullable foreign IDs to users if this is consistent with existing master-data patterns
- `lock_version` unsigned integer default 1 if consistent with existing optimistic-lock patterns
- timestamps

`cost_center`:

- `id` UUID primary key
- `code` string unique
- `name` JSON/JSONB translatable
- `description` text nullable
- `category` string nullable, e.g. `administrative`, `sales`, `operations`, `finance`, `other`
- `is_active` boolean default true
- `created_by`, `updated_by` nullable foreign IDs to users if this is consistent with existing master-data patterns
- `lock_version` unsigned integer default 1 if consistent with existing optimistic-lock patterns
- timestamps

Add PostgreSQL/SQLite-compatible validation where this codebase usually adds status/category check constraints. Do not add company, branch, tenant, department, user assignment, or security scope columns.

## Backend Requirements

- Add Eloquent models `Project` and `CostCenter`.
- Use Spatie Translatable for `name`.
- Add application services for create/update/delete/toggle workflows.
- Enforce unique code validation.
- Enforce date rule: project `end_date` cannot be before `start_date`.
- System should prevent deleting records that will be referenced in later slices if references exist; in this slice there are no GL references yet, so use a clean delete/soft-delete policy matching existing master data.
- Record business mutations through the existing audit logging pattern.
- Add attachment entity config entries:
  - `project` requires project permissions.
  - `cost_center` requires cost-center permissions.
- Add routes:
  - `/projects`
  - `/cost-centers`
- Protect every route with authentication and exact permissions.

## Frontend Requirements

- Add Inertia pages:
  - `resources/js/Pages/Projects/Index.tsx`
  - `resources/js/Pages/CostCenters/Index.tsx`
- Add navigation entries using the existing `AppLayout` pattern.
- Use existing shared table/action/empty-state/search/filter patterns.
- Include create/edit/delete flows.
- Include attachment panel only if consistent and lightweight; otherwise ensure registry/backend readiness and leave page integration for a later detail-page slice.
- Use `DatePicker` for project dates.
- Use `SearchableSelect` for status/category controls.
- No hardcoded visible labels; all text must be in EN/AR dictionaries.
- Buttons must have stable `title` or `aria-label`.
- State-changing Inertia submissions should preserve scroll/state where appropriate.

## Tests

Add a focused feature test file, e.g. `tests/Feature/Phase16Slice1ProjectCostCenterTest.php`, covering:

- migrations create `project` and `cost_center`;
- no `company_id`, `tenant_id`, `branch_id`, `currentCompany`, `currentTenant`, or Spatie Teams assumptions;
- permissions are registered by RBAC seeding;
- authorized user can create/update/delete project;
- authorized user can create/update/delete cost center;
- unauthorized user is blocked;
- project date validation rejects `end_date < start_date`;
- models expose translatable names;
- attachment registry includes `project` and `cost_center`;
- Inertia pages render with expected props;
- React pages do not contain native `<select>`, `<option>`, `type="date"`, unsafe `window.location.href`, or loose pagination link types.

## Verification Commands

Run from `laravel/`:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor\bin\pint --test
php -d memory_limit=512M artisan test --filter=Phase16Slice1ProjectCostCenterTest --compact
php -d memory_limit=512M artisan test --filter=Phase15ProductHardeningTest --compact
npm.cmd run typecheck
npm.cmd run build
```

If the local Windows machine hits paging-file/resource errors, retry the failed command once with `php -d memory_limit=512M` or `php -d xdebug.mode=off`. If it still fails due machine resources, report the exact local resource error and do not hide it.

## Documentation Updates

Update only:

- `PHASE_16_PROJECTS_COST_CENTERS_BUDGETING.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`
- create `PHASE_16_SLICE_1_REPORT.md`

The report must include:

1. files changed;
2. migrations added;
3. schema summary;
4. removed/avoided assumptions;
5. remaining `company_id`/`branch_id` occurrences touched, if any, with justification;
6. test results;
7. TypeScript/build results;
8. exact next slice: `PHASE_16_SLICE_2_AGY_PROMPT.md`.
