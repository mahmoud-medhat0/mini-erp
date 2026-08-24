# MINI ERP - PHASE 9 STAGING / PRODUCTION CUTOVER PACK

Phase 9 starts after Phase 8 Operational Readiness & E2E Smoke is complete.

This phase does not add a new ERP business module.

## Purpose

Prepare the verified Laravel ERP for controlled staging and production cutover.

Phase 9 converts the operational readiness baseline into deployable handoff artifacts:

- owner/operator cutover decision pack
- final environment checklist using variable names only
- deployment runbook
- rollback runbook
- database backup and restore drill plan
- scheduler and queue process supervision notes
- storage/mail/logging operations checklist
- go-live smoke and acceptance checklist
- final cutover report

## Policy-Safe Prompting Rules

All Phase 9 prompts must use neutral operational language.

Do not:

- ask for or print real `.env` values
- paste private credentials, API keys, passwords, tokens, connection strings, or secrets
- connect provider accounts
- require GitHub Actions or any CI/CD vendor
- assume a hosting provider
- run commands against production
- run destructive commands
- add tenant/company/branch ownership assumptions
- add new ERP business domains
- add visible hardcoded UI text

Use placeholders and variable names only.

## Scope

### Included

- Staging and production decision matrix.
- Laravel deployment release checklist.
- Environment variable checklist.
- Scheduler and queue process operations.
- PostgreSQL backup and restore planning.
- File storage and mail delivery checklist.
- Health/readiness and smoke verification plan.
- Rollback and incident handoff steps.

### Excluded

- Actual deployment to a hosting provider.
- Provider account setup.
- CI/CD implementation.
- External filing, collection, e-invoicing, SMS, or payroll integrations.
- New Laravel business logic.
- Database schema changes unless explicitly justified by a Phase 9 prompt and locally tested.

## Slice Plan

1. `PHASE_9_SLICE_1_GEMINI_PROMPT.md` - Cutover Decision Pack.
2. `PHASE_9_SLICE_2_GEMINI_PROMPT.md` - Environment & Secrets Checklist.
3. `PHASE_9_SLICE_3_GEMINI_PROMPT.md` - Deployment and Rollback Runbooks.
4. `PHASE_9_SLICE_4_GEMINI_PROMPT.md` - Backup and Restore Drill Pack.
5. `PHASE_9_SLICE_5_GEMINI_PROMPT.md` - Runtime Processes, Storage, Mail, and Logs.
6. `PHASE_9_SLICE_6_GEMINI_PROMPT.md` - Go-Live Smoke, Security Checklist, and Acceptance Gate.
7. `PHASE_9_SLICE_7_GEMINI_PROMPT.md` - Final Cutover Close-Out.

## Definition Of Done

Phase 9 is complete only when:

- all cutover docs exist and match the active Laravel target
- environment docs list names only and contain no real values
- scheduler, queue, storage, mail, logs, backup, restore, smoke, and rollback workflows are documented
- source scans classify old Next.js/Prisma references as historical only
- source scans confirm no tenant/company/branch scope was introduced
- verification commands run locally and exit successfully
- `PHASE_9_FINAL_CUTOVER_REPORT.md` is created

