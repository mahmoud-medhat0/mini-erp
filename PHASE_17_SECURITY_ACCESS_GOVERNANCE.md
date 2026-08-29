# Phase 17 - Security and Access Governance

> Defensive security hardening only. Deployment execution remains parked until the owner explicitly resumes cutover work.

## Status

PLANNED - 2026-08-28

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
| 1 | `PHASE_17_SLICE_1_AGY_PROMPT.md` | READY | Controlled bootstrap admin and first-user privilege seeding guard. |
| 2 | `PHASE_17_SLICE_2_AGY_PROMPT.md` | READY | Route authorization audit command/report and regression tests. |
| 3 | `PHASE_17_SLICE_3_AGY_PROMPT.md` | READY | Password policy and session safety configuration hardening. |
| 4 | `PHASE_17_SLICE_4_AGY_PROMPT.md` | READY | Sensitive financial action confirmation and audit evidence hardening. |
| 5 | `PHASE_17_SLICE_5_AGY_PROMPT.md` | READY | Attachment, notification, and private-delivery safety hardening. |
| 6 | `PHASE_17_SLICE_6_AGY_PROMPT.md` | READY | Final security close-out, source scans, and documentation sync. |

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

## Current First Slice

Start with `PHASE_17_SLICE_1_AGY_PROMPT.md`.
