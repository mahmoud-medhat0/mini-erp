# MINI ERP - PHASE 8 OPERATIONAL READINESS & E2E SMOKE

Phase 8 starts after Phase 7 Tax / VAT is fully closed out.

This phase does not add a new ERP business module.

Status: COMPLETE & VERIFIED on 2026-08-24. See `PHASE_8_FINAL_OPERATIONAL_READINESS_REPORT.md`.

## Purpose

Move the Laravel target from "locally complete" to "ready for controlled staging/production operation" by preparing:

- Laravel deployment documentation
- environment configuration checklist
- scheduler and queue operation notes
- health/readiness checks
- repeatable local verification commands
- browser smoke coverage for critical ERP flows
- final operational handoff report

## Policy-Safe Prompting Notes

Prompts in this phase are intentionally written with neutral engineering language:

- do not ask an AI model to reveal environment values
- do not paste `.env` contents or private configuration values
- list required environment variable names only, not their values
- do not run destructive commands
- do not add tenant/company/branch ownership assumptions
- do not add external filing, collection integrations, payroll, rentals, projects, or budgeting
- do not introduce GitHub Actions as a requirement because the owner stated no connected actions pipeline is currently used

## Scope

### Included

- Update obsolete deployment docs that still refer to the old Next.js/Prisma track.
- Document Laravel 13 + Inertia + React + PostgreSQL runtime needs.
- Verify scheduler command registration and external cron requirement.
- Verify queue baseline and failed job visibility.
- Prepare local smoke/E2E test structure.
- Create a final Phase 8 verification artifact.

### Excluded

- New accounting behavior.
- New business domains.
- Provider-specific hosting implementation.
- Managed cloud account configuration.
- External filing, collection, e-invoicing, email, or SMS integrations.
- Any request to print private environment values.

## Slice Plan

1. `PHASE_8_SLICE_1_GEMINI_PROMPT.md` - Operational Readiness Decision Pack.
2. `PHASE_8_SLICE_2_GEMINI_PROMPT.md` - Laravel Deployment Documentation Refresh.
3. `PHASE_8_SLICE_3_GEMINI_PROMPT.md` - Scheduler, Queue, and Health Check Readiness.
4. `PHASE_8_SLICE_4_GEMINI_PROMPT.md` - Browser Smoke / E2E Foundation.
5. `PHASE_8_SLICE_5_GEMINI_PROMPT.md` - Final Operational Close-Out.

## Definition Of Done

Phase 8 is complete only when:

- obsolete Next.js deployment references are corrected or clearly marked historical
- required environment names are documented without exposing values
- scheduler and queue operation are documented and locally testable
- health route is verified
- browser smoke coverage exists or a clear owner-approved alternative is documented
- full Laravel tests, concurrency tests, typecheck, build, and relevant stress commands pass
- `PHASE_8_FINAL_OPERATIONAL_READINESS_REPORT.md` is created
