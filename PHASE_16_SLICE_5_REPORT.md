# Phase 16 Slice 5 Report: Budget Version and Monthly Budget Line Foundation

**Date:** 2026-08-28  
**Phase:** 16 (Projects, Cost Centers, and Budgeting)  
**Slice:** 5 (Budget Version and Monthly Budget Line Foundation)  
**Status:** COMPLETE  

---

## 1. Overview & Objectives

Phase 16 Slice 5 establishes the annual budget versioning and monthly budget line foundation under `/budgeting/budgets`.

Key invariants maintained:
1. **Strict No Multi-Tenant Architecture:** Zero tenant/company/branch scoping columns or assumptions. All budget records are global master data.
2. **Zero General Ledger Posting:** No `journal_entry` or `ledger_entry` records are created in this slice. Budget data remains decoupled from accounting postings until read by comparison reports.
3. **Exact Integer Minor Units:** All budget line amounts are integer minor units (`amount_minor >= 0`) with zero float arithmetic.
4. **Strict Status Lifecycle Workflow:** `draft` -> `submitted` -> `approved` -> `active` -> `archived`, plus cancellations (`draft`/`submitted` -> `cancelled`) and `approved` -> `archived`.
5. **Single Active Budget per Fiscal Year:** Activating an approved budget automatically archives any existing active budget for that fiscal year within the same database transaction, with a database-level partial unique index as the final safety net.
6. **Duplicate Line Tuple Detection:** Two lines within the same budget cannot share `(financial_period_id, account_id, project_id|null, cost_center_id|null, currency)`.
7. **Optimistic Concurrency Hardening:** Version tracking (`lock_version`) on budget master records prevents concurrent overwrite collisions.

---

## 2. Database Schema & Models

### Migrations
- `laravel/database/migrations/2026_08_28_040000_create_phase16_budget_tables.php`:
  - `budget` table:
    - UUID primary key (`id`)
    - `fiscal_year_id` (FK to `fiscal_year.id`, restrict on delete)
    - `code` (string, uppercase, unique)
    - `version_code` (string, uppercase, e.g. `V1`, `V2`)
    - `name` (JSON translatable `{"en": "...", "ar": "..."}`)
    - `description` (nullable text)
    - `status` (`draft`, `submitted`, `approved`, `active`, `archived`, `cancelled`)
    - `default_currency` (FK to `currency.code`, restrict on delete)
    - Lifecycle audit stamps: `submitted_by`, `submitted_at`, `approved_by`, `approved_at`, `activated_by`, `activated_at`, `archived_by`, `archived_at`, `cancelled_by`, `cancelled_at`
    - Concurrency `lock_version` (integer default 1)
    - Composite unique index on `['fiscal_year_id', 'version_code']`
    - PostgreSQL check constraint `chk_budget_status`
  - `budget_line` table:
    - UUID primary key (`id`)
    - `budget_id` (FK to `budget.id`, cascade on delete)
    - `financial_period_id` (FK to `financial_period.id`, restrict on delete)
    - `account_id` (FK to `account.id`, restrict on delete)
    - `project_id` (nullable FK to `project.id`, restrict on delete)
    - `cost_center_id` (nullable FK to `cost_center.id`, restrict on delete)
    - `currency` (FK to `currency.code`, restrict on delete)
    - `amount_minor` (big integer >= 0)
    - `notes` (nullable text)
    - Performance indexes on `budget_id`, `financial_period_id`, `account_id`, `project_id`, `cost_center_id`, and composite `[financial_period_id, account_id]`
    - PostgreSQL check constraint `chk_budget_line_amount` (`amount_minor >= 0`)
- `laravel/database/migrations/2026_08_28_041000_enforce_single_active_budget_per_fiscal_year.php`:
  - Adds a PostgreSQL/SQLite partial unique index on `budget(fiscal_year_id)` where `status = 'active'`.
  - Refuses to apply if duplicate active budgets already exist for the same fiscal year.

### Eloquent Models & Relations
- `App\Models\Budget`: HasUuids, HasTranslations, relations to `fiscalYear`, `lines`, `defaultCurrency`, and audit actors (`creator`, `submitter`, `approver`, `activator`, `archiver`, `canceller`).
- `App\Models\BudgetLine`: HasUuids, `amount_minor` integer cast, relations to `budget`, `financialPeriod`, `account`, `project`, `costCenter`, `currencyRef`.
- Extended `FiscalYear`, `FinancialPeriod`, `Account`, `Project`, and `CostCenter` with reverse `HasMany` relations to `budgets` or `budgetLines`.

---

## 3. Application Services & Web Layer

- `App\Application\Budgeting\BudgetService`:
  - Enforces `code` and `(fiscal_year_id, version_code)` uniqueness.
  - Enforces line financial period belongs to the budget's fiscal year.
  - Enforces active status for referenced accounts, projects, and cost centers.
  - Validates duplicate line tuples `(financial_period_id, account_id, project_id, cost_center_id, currency)`.
  - Enforces optimistic concurrency locks (`lock_version`).
  - Rejects changing a budget's fiscal year after creation so existing lines cannot silently drift across fiscal years.
  - Revalidates positive line totals before submit, approve, and activate lifecycle actions.
  - Implements complete lifecycle: `create`, `update`, `replaceLines`, `delete`, `submit`, `approve`, `activate` (auto-archiving active peer budgets), `archive`, and `cancel`.
  - Records Spatie Activitylog audit entries on all mutating actions.
- `App\Application\Budgeting\BudgetPageData`:
  - Structured queries for paginated budgets with filters (`search`, `fiscal_year_id`, `status`), active fiscal years with period lists, active accounts, active projects, active cost centers, and supported currencies.
- `App\Http\Controllers\Budgeting\BudgetController`:
  - Web controller guarding routes with `permission.all:budgeting.*,view_financials`.
  - Actions: `index`, `store`, `update`, `destroy`, `submit`, `approve`, `activate`, `archive`, `cancel`.
- `routes/web.php`:
  - Registered route group `/budgeting/budgets` with permission middleware.

---

## 4. Frontend & User Experience

- `resources/js/Pages/Budgeting/Budgets.tsx`:
  - Full budget list table with fiscal year, version badge, localized status badges, line count, total amount formatted by currency, and permission-gated lifecycle action buttons.
  - Filter bar for search, fiscal year, and status.
  - Create / Edit modal with multi-currency default selection and responsive monthly budget line editor with inline duplicate detection and summary by currency.
  - Detailed view modal showing audit lifecycle timestamps, budget metadata, and complete line breakdown.
  - Standard design primitives used throughout (`PageHeader`, `Card`, `Button`, `Modal`, `StatusBadge`, `SearchableSelect`, `tableClasses`).
  - Full dictionary-backed i18n support in `en.json` and `ar.json` without hardcoded English or Arabic strings.
  - Local review replaced hardcoded placeholders and removed HTML injection from pagination labels.

---

## 5. Verification & Test Suite

All verification commands executed cleanly:

| Check | Command | Result |
|---|---|---|
| Phase 16 Slice 5 Feature Suite | `php artisan test --filter=Phase16Slice5BudgetFoundationTest --compact` | **PASSED** (22 tests / 124 assertions) |
| Phase 16 Slice 4 Reports Suite | `php artisan test --filter=Phase16Slice4ProjectCostCenterReportsTest --compact` | **PASSED** (14 tests / 204 assertions) |
| Phase 16 Slice 3 Expense Suite | `php artisan test --filter=Phase16Slice3ExpenseDimensionTest --compact` | **PASSED** (11 tests / 119 assertions) |
| Concurrency Suite | `php artisan test --testsuite=Concurrency --compact` | **PASSED** (7 tests / 16 assertions) |
| Code Style Standards | `vendor/bin/pint --test` | **PASSED** (0 style issues) |
| TypeScript Types | `npm run typecheck` | **PASSED** (0 errors) |
| Frontend Production Build | `npm run build` | **PASSED** (all assets compiled) |
| Concurrency Stress | `php artisan concurrency:stress --workers=100` | **PASSED** (100 workers unique & contiguous) |
| Accounting Concurrency Stress | `php artisan accounting:concurrency-stress --workers=50` | **PASSED** (50 JV sequential allocations unique) |
| Token / Idempotency GC | `php artisan tokens:gc --batch=100` | **PASSED** (100 idempotency keys collected) |
| Migration Status | `php artisan migrate:status` | **PASSED** (all migrations through `2026_08_28_041000_enforce_single_active_budget_per_fiscal_year` ran) |

---

## 6. Next Steps

Phase 16 Slice 5 is complete. Proceed to **Phase 16 Slice 6**:
- Budget-vs-actual reports and financial-statement comparison hooks (`PHASE_16_SLICE_6_AGY_PROMPT.md`).
- Project and cost center budget variance analysis.
- Phase 16 final close-out verification report.
