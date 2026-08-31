# Phase 16 - Projects, Cost Centers, and Budgeting

Status: COMPLETE (All Slices 1 to 6 COMPLETE)

## Purpose

Phase 16 opens the next bounded product track after Phase 15 Product Hardening closed.

The goal is to add project and cost-center dimensional accounting, then budgeting and budget-vs-actual reporting, without changing the existing ledger spine or introducing tenant/company assumptions.

## Source Of Truth

Use these first:

- `NO_MULTI_TENANT_POLICY.md`
- `PRODUCT_EXTENSIBILITY_ROADMAP.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `PHASE_15_FINAL_VERIFICATION_REPORT.md`
- `PHASE_16_SLICE_1_REPORT.md`
- `PHASE_16_SLICE_2_REPORT.md`
- `PHASE_16_SLICE_3_REPORT.md`
- `PHASE_16_SLICE_4_REPORT.md`
- `PHASE_16_SLICE_5_REPORT.md`
- `PHASE_16_FINAL_VERIFICATION_REPORT.md`
- `spec/MASTER_ERP_SPEC.md` sections C19 and C20
- `spec/INTEGRATION_MAP.md`
- `spec/PERMISSION_MATRIX.md`
- current Laravel code under `laravel/`

Older Next.js/Prisma references are historical only.

## Non-Negotiable Rules

- No multi-tenant architecture.
- No Company as tenant.
- No `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, `currentBranch`, or Spatie Teams.
- Do not add Company -> Project, Branch -> Project, Company -> CostCenter, Department -> CostCenter, User -> Project, or User -> CostCenter ownership unless a later explicit owner decision approves that exact relationship.
- Branch is already an approved operational/reporting dimension in bounded implemented workflows, but it remains not a tenant/security boundary.
- Project and Cost Center are standalone operational/reporting dimensions unless a later slice explicitly adds more relationships.
- Department remains a possible reporting concept; do not implement Department or Department relationships in Phase 16 until a dedicated owner decision slice approves them.
- All GL posting must continue through the existing PostingEngine.
- Posted journal, ledger, subledger, tax, stock, fixed-asset, payroll, rental, and audit records remain immutable.
- Use exact integer money math and existing quantity precision rules.
- Use Spatie Activitylog via the existing audit adapter.
- Use existing Spatie Permission style; no teams.
- Visible TSX text must be dictionary-backed through EN/AR locale files.
- No hardcoded visible UI labels in React pages.
- Use existing shared UI controls such as `SearchableSelect`, `DatePicker`, shared pagination links, permission-aware actions, and empty states.
- Keep controllers small and focused; extract services/page-data classes where needed.
- Deployment remains parked.

## Planned Slices

| Slice | File | Status | Scope |
|---|---|---|---|
| 1 | `PHASE_16_SLICE_1_AGY_PROMPT.md` | COMPLETE | Project and Cost Center master-data foundation (`project` & `cost_center` tables, models, services, controllers, routes, Inertia pages, Spatie Activitylog audit, attachment registry, 12 feature tests). |
| 2 | `PHASE_16_SLICE_2_AGY_PROMPT.md` | COMPLETE | Optional GL project/cost-center dimension columns and PostingEngine propagation (`journal_line` & `ledger_entry` dimensions, PostingEngine, ReversalService, JournalForm, JournalDetail, 13 feature tests). |
| 3 | `PHASE_16_SLICE_3_AGY_PROMPT.md` | COMPLETE (Expenses) | Dimension capture on direct Expenses lines and debit GL/ledger propagation (`expense_line` dimensions, validation, grouped posting, UI dropdowns, 11 feature tests). |
| 4 | `PHASE_16_SLICE_4_AGY_PROMPT.md` | COMPLETE | Project profitability and cost-center actual reports from posted ledger entries only, with per-currency summaries, unassigned review rows, CSV exports, reports hub cards, and 14 feature tests. |
| 5 | `PHASE_16_SLICE_5_AGY_PROMPT.md` | COMPLETE | Budget/version master data and monthly budget lines (`budget` and `budget_line` tables, models, `BudgetService`, `BudgetController`, `Budgets.tsx`, DB-enforced single-active budget per fiscal year, workflow lifecycle, optimistic locking, Spatie Activitylog audit, and 22 feature tests). |
| 6 | `PHASE_16_SLICE_6_AGY_PROMPT.md` | COMPLETE | Budget-vs-actual reports (`BudgetVarianceReportService`, `BudgetVarianceCsvExporter`, `BudgetVariancePageData`, `BudgetVarianceController`, `Variance.tsx`, exact integer basis point variance math, multi-currency isolation, warning codes, and 23 feature tests). |

Recurring templates, forecasting, department modeling, project manager/user restrictions, and branch-specific project security are intentionally deferred to later bounded phases.

## Acceptance Gate

Phase 16 is not closed until:

- projects and cost centers can be maintained through permissioned EN/AR Inertia pages;
- posted ledger rows can safely carry optional project and cost-center dimensions;
- approved source documents can pass project/cost-center dimensions into their PostingEngine-generated entries;
- project/cost-center reports reconcile to posted GL without double-counting;
- budgets can be versioned, approved, and compared against posted actuals;
- all new behavior has feature tests and focused regression scans;
- Pint, targeted PHPUnit, Concurrency suite where affected, TypeScript typecheck, and Vite build pass;
- no tenant/company/current-context assumptions are introduced.
