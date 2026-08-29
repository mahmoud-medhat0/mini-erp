# Phase 17 - Security and Access Governance

> Defensive security hardening only. Deployment execution remains parked until the owner explicitly resumes cutover work.

## Status

COMPLETE - 2026-08-29. Slices 1-6 are 100% complete and verified. See `PHASE_17_FINAL_VERIFICATION_REPORT.md`.

## Purpose

Phase 17 hardens authentication, privilege assignment, authorization coverage, sensitive action controls, private data delivery, and audit evidence across the existing Laravel ERP.

This phase must not add a new ERP business module. It must preserve all accounting, inventory, payroll, tax, rental, branch-operational, project, cost-center, and budgeting behavior already implemented.

## Non-Negotiable Rules

- No multi-tenant architecture.
- No Company-as-tenant semantics.
- No `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, or Spatie Teams.
- Branch remains an operational/reporting dimension only where explicitly implemented. Do not make Branch a security boundary, login context, user scope, or blanket route scope.
- No deployment/server cutover work in Phase 17.
- No hardcoded visible text in React pages. Use `resources/js/locales/en.json` and `resources/js/locales/ar.json`.
- Controllers must stay thin. Put business/security logic in request classes, services, middleware, policies, or support classes.
- Spatie Activitylog remains the active audit backend.
- Permission checks must be server-side authoritative; frontend hiding is only presentation.

## Slice Plan

| Slice | File | Status | Goal |
|---|---|---|---|
| 1 | `PHASE_17_SLICE_1_AGY_PROMPT.md` | COMPLETE | Controlled bootstrap admin and first-user privilege seeding guard (`PHASE_17_SLICE_1_REPORT.md`). |
| 2 | `PHASE_17_SLICE_2_AGY_PROMPT.md` | COMPLETE | Route authorization audit command/report and regression tests (`PHASE_17_SLICE_2_REPORT.md`). |
| 3 | `PHASE_17_SLICE_3_AGY_PROMPT.md` | COMPLETE | Password policy and session safety configuration hardening (`PHASE_17_SLICE_3_REPORT.md`). |
| 4 | `PHASE_17_SLICE_4_AGY_PROMPT.md` | COMPLETE | Sensitive financial action confirmation and audit evidence hardening (`PHASE_17_SLICE_4_REPORT.md`). |
| 5 | `PHASE_17_SLICE_5_AGY_PROMPT.md` | COMPLETE | Attachment, notification, and private-delivery safety hardening (`PHASE_17_SLICE_5_REPORT.md`). |
| 6 | `PHASE_17_SLICE_6_AGY_PROMPT.md` | COMPLETE | Final security close-out, source scans, and documentation sync (`PHASE_17_FINAL_VERIFICATION_REPORT.md`). |

## Required Close-Out Evidence

Each implemented slice must provide:

- exact files changed
- migrations added, if any
- permissions added/changed, if any
- no-scope scan result
- UI hardcoded text/native-control scan result where TSX changed
- targeted PHPUnit result
- relevant regression result
- Pint result
- TypeScript result when frontend code changed
- Vite build result when frontend code changed

## Next Action

Phase 17 is complete. No automatic next implementation phase. Recommend owner/product review or an explicitly approved phase. Deployment remains parked.
