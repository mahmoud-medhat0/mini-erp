# PHASE 16 SLICE 2 REPORT - GL PROJECT AND COST CENTER DIMENSIONS

**Date:** 2026-08-28  
**Phase:** Phase 16 - Projects, Cost Centers, and Budgeting  
**Slice:** Slice 2 - GL Project and Cost Center Dimensions  
**Status:** COMPLETE & CODEX-REVIEWED

---

## 1. Executive Summary

Phase 16 Slice 2 adds optional `project_id` and `cost_center_id` dimensions to manual general journal lines (`journal_line`) and immutable posted general ledger entries (`ledger_entry`). 

This slice establishes end-to-end GL dimension propagation:
- Tagging manual journal draft lines with optional active project and cost center dimensions.
- Enforcing active dimension validation at both draft creation/update and ledger posting time.
- Copying dimensions from journal lines into immutable posted `ledger_entry` records in `PostingEngine`.
- Preserving and mirroring dimensions on reversal journal lines and reversal ledger entries in `ReversalService`.
- Blocking deletion of projects or cost centers referenced by journal lines or ledger entries via `restrictOnDelete` foreign keys and application service guards.
- Exposing active project and cost center selectors in the journal voucher form (`JournalForm.tsx`) and detail view (`JournalDetail.tsx`) using accessible shared primitives and dictionary-backed localization.
- Preserving strict single-installation architecture with zero tenant/company assumptions and leaving operational documents unmodified for Slice 3.

---

## 2. Source Reading & Policy Compliance

All implementation strictly conforms to:
- `NO_MULTI_TENANT_POLICY.md` (No `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, `currentBranch`, or Spatie Teams).
- `PHASE_16_PROJECTS_COST_CENTERS_BUDGETING.md`.
- `PHASE_16_SLICE_1_REPORT.md`.
- Master ERP specs.

No company, branch, department, employee, or user ownership was added to `project` or `cost_center`. No project/cost-center dimensions were added to the `journal_entry` header. Operational documents (Sales, Purchasing, Inventory, Fixed Assets, Payroll, Expenses, Rentals) were not modified in this slice (deferred to Slice 3).

---

## 3. Files Created and Modified

### Database Migrations
- `laravel/database/migrations/2026_08_28_020000_add_phase16_gl_dimensions_to_journal_and_ledger.php`:
  - Added nullable UUID columns: `journal_line.project_id`, `journal_line.cost_center_id`, `ledger_entry.project_id`, `ledger_entry.cost_center_id`.
  - Added foreign key constraints with `restrictOnDelete` targeting `project(id)` and `cost_center(id)`.
  - Added individual indexes on `journal_line(project_id)` and `journal_line(cost_center_id)`.
  - Added composite indexes on `ledger_entry(project_id, financial_period_id, entry_date)` and `ledger_entry(cost_center_id, financial_period_id, entry_date)`.
  - Implemented trigger reapplication logic for PostgreSQL and SQLite immutability rules.

### Backend Domain & Application Services
- `laravel/app/Domain/Accounting/DraftLine.php`:
  - Added nullable `public ?string $projectId = null` and `public ?string $costCenterId = null` properties to the `DraftLine` constructor.
- `laravel/app/Models/JournalLine.php`:
  - Added `project_id` and `cost_center_id` to `$fillable`.
  - Added `project(): BelongsTo` and `costCenter(): BelongsTo` relationships.
- `laravel/app/Models/LedgerEntry.php`:
  - Added `project_id` and `cost_center_id` to `$fillable`.
  - Added `project(): BelongsTo` and `costCenter(): BelongsTo` relationships.
- `laravel/app/Models/Project.php`:
  - Added `journalLines(): HasMany` and `ledgerEntries(): HasMany` relationships.
- `laravel/app/Models/CostCenter.php`:
  - Added `journalLines(): HasMany` and `ledgerEntries(): HasMany` relationships.
- `laravel/app/Application/Projects/ProjectService.php`:
  - Added deletion guard blocking deletion if referenced by `journalLines` or `ledgerEntries`.
- `laravel/app/Application/CostCenters/CostCenterService.php`:
  - Added deletion guard blocking deletion if referenced by `journalLines` or `ledgerEntries`.
- `laravel/app/Application/Accounting/JournalDraftService.php`:
  - In `createDraft()` and `updateDraft()`: validated that provided `project_id` and `cost_center_id` exist and are active; mapped fields to `JournalLine` and `DraftLine`; fresh loaded `lines.project` and `lines.costCenter`.
- `laravel/app/Application/Accounting/PostingEngine.php`:
  - Eager loaded `lines.project` and `lines.costCenter`.
  - Enforced active status validation for tagged projects and cost centers before ledger generation.
  - Copied `project_id` and `cost_center_id` into created `LedgerEntry` records.
  - Fresh loaded `lines.project` and `lines.costCenter` on return.
- `laravel/app/Application/Accounting/ReversalService.php`:
  - Copied `project_id` and `cost_center_id` onto reversal `JournalLine` records.
- `laravel/app/Application/Accounting/JournalPageData.php`:
  - Provided active `projects` and `costCenters` in `createData()`.
  - Eager loaded `lines.project` and `lines.costCenter` in `showData()`.
- `laravel/app/Http/Controllers/Accounting/JournalController.php`:
  - Added validation rules for `lines.*.project_id` and `lines.*.cost_center_id`.

### Localization
- `laravel/lang/ar.json`:
  - Added Arabic translations for project/cost-center inactive errors and deletion blocker messages.
- `laravel/resources/js/locales/en.json` & `laravel/resources/js/locales/ar.json`:
  - Added `project`, `costCenter`, `selectProject`, `selectCostCenter` translation keys under `app.accounting`.

### Frontend & Types
- `laravel/resources/js/Types/index.ts`:
  - Updated `JournalLineRow` and `LedgerRow` with `project_id`, `cost_center_id`, `project?: ProjectRow | null`, and `costCenter?: CostCenterRow | null`.
- `laravel/resources/js/Pages/Accounting/JournalForm.tsx`:
  - Integrated `SearchableSelect` dropdowns for Project and Cost Center per journal line with clearable options, no native `<select>`, and full EN/AR dictionary localization.
- `laravel/resources/js/Pages/Accounting/JournalDetail.tsx`:
  - Added Project and Cost Center columns to the journal lines table and adjusted footer column span.

### Tests
- `laravel/tests/Feature/Phase16Slice2GlDimensionTest.php`:
  - 13 comprehensive feature tests covering schema assertions, line dimension storage, PostingEngine ledger entry propagation, reversal mirroring, inactive dimension blockers during create/update/post, delete prevention, Inertia props filtering, UI source scans, and scope compliance.

### Codex Post-Agy Review Correction

- Added a missing draft update regression test to prove inactive projects and inactive cost centers are rejected during `JournalDraftService::updateDraft()`, not only during draft creation or posting.
- Re-ran the affected test suite and verification checks after the correction.

---

## 4. Verification Results

| Check / Command | Result | Details |
|---|---|---|
| `php artisan migrate --force` | PASSED | Nothing to migrate after Agy application |
| `php artisan migrate:status` | PASSED | All migrations in batch status [Ran] |
| `vendor/bin/pint --test` | PASSED | Code style clean, 0 violations |
| `php artisan test --filter=Phase16Slice2GlDimensionTest --compact` | PASSED | **13 passed** (152 assertions, 13.24s) |
| `php artisan test --filter=Phase16Slice1ProjectCostCenterTest --compact` | PASSED | **12 passed** (146 assertions, 11.27s) |
| `php artisan test --filter=Phase15ProductHardeningTest --compact` | PASSED | **192 passed** (25,764 assertions, 18.29s) |
| `php artisan test --testsuite=Concurrency --compact` | PASSED | **7 passed** (16 assertions, 2.26s) |
| `php artisan concurrency:stress --workers=100` | PASSED | 100 workers, unique contiguous sequences |
| `php artisan accounting:concurrency-stress --workers=50` | PASSED | 50 JV allocations unique, durable posting & single reversal |
| `php artisan tokens:gc --batch=100` | PASSED | Deleted expired tokens and keys |
| `npm run typecheck` | PASSED | 0 TypeScript errors |
| `npm run build` | PASSED | Vite built cleanly; only standard large chunk warning |

---

## 5. Scope & Boundary Invariants Checklist

- [x] Single-installation architecture strictly maintained.
- [x] No `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, `currentBranch`, or Spatie Teams.
- [x] No Company, Branch, Department, Employee, Customer, Supplier, or User ownership added to Project or CostCenter.
- [x] No `project_id` or `cost_center_id` added to `journal_entry` header (line-level dimensions only).
- [x] Dimensions are fully optional (nullable with no mandatory enforcement).
- [x] Operational documents (Sales, Purchasing, Inventory, Fixed Assets, Payroll, Expenses, Rentals) left unmodified for Slice 3.
- [x] Posted `ledger_entry` records remain append-only and immutable.
- [x] All visible TSX text is backed by EN/AR locale dictionary keys.
- [x] No native `<select>`, `<option>`, `type="date"`, or unsafe `window.location.href`.

---

## 6. Next Step

Proceed to **Phase 16 Slice 3: Dimension Capture on Approved Operational Documents** (`PHASE_16_SLICE_3_AGY_PROMPT.md`), which will introduce optional project and cost-center dimensions to operational documents (sales orders, customer invoices, purchase orders, supplier bills, expenses, payroll runs, rental contracts) and propagate them through PostingEngine into the GL.
