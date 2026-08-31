# Phase 16 Slice 1 Report: Project and Cost Center Master Data Foundation

> No Multi-Tenant Policy: Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root NO_MULTI_TENANT_POLICY.md.

- Status: COMPLETE & LOCALLY VERIFIED
- Date: 2026-08-28
- Phase: Phase 16 - Projects, Cost Centers, and Budgeting
- Slice: Slice 1 - Project and Cost Center Master Data Foundation

## 1. Executive Summary

Phase 16 Slice 1 implements standalone project and cost center master data. These records are independent accounting/operational dimensions prepared for later tagging and reporting slices.

This slice does not modify the immutable GL posting engine and does not introduce company, tenant, branch, department, employee, customer, supplier, or user ownership scope.

## 2. Implemented Components

### 2.1 Database

Migration:

- `laravel/database/migrations/2026_08_28_010000_create_phase16_project_and_cost_center_tables.php`

Tables:

- `project`
  - `id` UUID primary key
  - `code` unique string
  - `name` JSON translatable EN/AR
  - `description` nullable text
  - `status`: `active`, `on_hold`, `completed`, `cancelled`
  - `start_date`, `end_date`
  - `is_billable` default `false`
  - `is_active` default `true`
  - `lock_version` default `1`
  - `created_by`, `updated_by` nullable user references
  - timestamps
- `cost_center`
  - `id` UUID primary key
  - `code` unique string
  - `name` JSON translatable EN/AR
  - `description` nullable text
  - `category`: `administrative`, `sales`, `operations`, `finance`, `other`
  - `is_active` default `true`
  - `lock_version` default `1`
  - `created_by`, `updated_by` nullable user references
  - timestamps

PostgreSQL check constraints were added for valid project status, valid project date ordering, and valid cost center category.

### 2.2 Backend

Models:

- `App\Models\Project`
- `App\Models\CostCenter`

Application services:

- `App\Application\Projects\ProjectService`
- `App\Application\Projects\ProjectPageData`
- `App\Application\CostCenters\CostCenterService`
- `App\Application\CostCenters\CostCenterPageData`

Controllers:

- `App\Http\Controllers\Projects\ProjectController`
- `App\Http\Controllers\CostCenters\CostCenterController`

Routes:

- `GET /projects` guarded by `projects.view`
- `POST /projects` guarded by `projects.create`
- `PUT /projects/{project}` guarded by `projects.edit`
- `DELETE /projects/{project}` guarded by `projects.delete`
- `GET /cost-centers` guarded by `costCenters.view`
- `POST /cost-centers` guarded by `costCenters.create`
- `PUT /cost-centers/{costCenter}` guarded by `costCenters.edit`
- `DELETE /cost-centers/{costCenter}` guarded by `costCenters.delete`

### 2.3 Attachments and Audit

Attachment entity registry entries were added for:

- `project`
- `cost_center`

Audit events are recorded through the existing Spatie Activitylog-backed `AuditLogger` adapter:

- `project.create`
- `project.update`
- `project.delete`
- `cost_center.create`
- `cost_center.update`
- `cost_center.delete`

### 2.4 Frontend

Pages:

- `laravel/resources/js/Pages/Projects/Index.tsx`
- `laravel/resources/js/Pages/CostCenters/Index.tsx`

Navigation and shared types were updated:

- `laravel/resources/js/Components/AppLayout.tsx`
- `laravel/resources/js/Types/index.ts`
- `laravel/resources/js/locales/en.json`
- `laravel/resources/js/locales/ar.json`

UI constraints preserved:

- No native `<select>` / `<option>`
- No native `type="date"`
- No `window.location.href`
- Uses existing `SearchableSelect`, `DatePicker`, `ToggleSwitch`, `StatusBadge`, `Button`, and `AttachmentPanel` patterns

## 3. Explicitly Deferred

- GL project/cost-center dimensions and PostingEngine propagation: Phase 16 Slice 2
- Operational document dimension capture: Phase 16 Slice 3
- Project and cost-center actual reports: Phase 16 Slice 4
- Budget versions and budget lines: Phase 16 Slice 5
- Budget vs actual reporting: Phase 16 Slice 6
- Department modeling: future owner decision only

## 4. Invariant Compliance

- No `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, `currentBranch`, or Spatie Teams were introduced.
- No Company, Branch, Department, Employee, Customer, or Supplier ownership was added to Project or CostCenter.
- RBAC remains permission-based through Spatie permissions.
- Audit logging remains append-only through the existing activity log adapter.

## 5. Verification Results

Reported by Agy and rechecked during Codex review:

- `php artisan test --filter=Phase16Slice1ProjectCostCenterTest`: 12 tests passed
- `npm run typecheck`: passed with 0 errors
- `npm run build`: passed
- `vendor/bin/pint --test`: passed

## 6. Next Step

Proceed to `PHASE_16_SLICE_2_AGY_PROMPT.md`: optional GL project/cost-center dimension columns and PostingEngine propagation.
