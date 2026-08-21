# Markdown Documentation Audit

Date: 2026-08-21

Scope: recursive markdown review of files returned by `rg --files -g "*.md"` at review time. The new audit files created in this pass are outputs and were not part of the initial inventory.

Post-audit correction note: current docs have since been updated through M10 and Phase 3 Slices 1-6. Phase 2 Accounting Core, Phase 3 master data for Customer/Supplier/CashAccount/BankAccount, Phase 3 AR/AP opening-balance subledgers, Phase 3 customer receipt/supplier payment posting, Phase 3 AR/AP allocation settlement, Phase 3 cheque lifecycle, and Phase 3 bank reconciliation foundation are implemented in Laravel. Spatie Activitylog is the active audit backend, and FiscalYear is `SINGLE-ERP CONTEXT`: global fiscal years, no Company/Tenant scope. Historical files may still quote old Next.js behavior when clearly treated as legacy history.

Classification meanings:

- CURRENT: aligned with the current Laravel implementation and latest owner corrections.
- PARTIALLY_STALE: contains useful material, but some claims are old, aspirational, or not proven by code.
- STALE: mostly outdated for the current Laravel target.
- CONTRADICTORY: conflicts with latest owner corrections or verified Laravel implementation.
- LEGACY_REFERENCE: useful as archive/history only; not source of truth.
- NEEDS_OWNER_DECISION: contains a business-model choice that cannot be accepted without owner confirmation.

## Global Findings

1. Several docs still describe a tenant/company/branch-scoped ERP. This directly contradicts the latest Company / Branch / User correction.
2. Several docs describe the old Next.js implementation or Phase 1 foundation state. They are historical references, not Laravel implementation proof.
3. Generated specification files contain useful module/business ideas but must not be treated as original owner requirements.
4. The current Laravel implementation includes the foundation plus Phase 2 accounting ledger spine. Docs claiming later operational modules or complete ERP behavior remain stale unless explicitly scoped to current Laravel code.

## File Classification

| File | Classification | Notes |
| --- | --- | --- |
| `README.md` | CURRENT | Describes current Laravel migration, Phase 2 accounting, Spatie Activitylog, and no-tenant rule. |
| `CHANGELOG.md` | CURRENT_WITH_HISTORY | Current top entries are aligned; older entries remain historical. |
| `CONTINUE_HERE.md` | CURRENT | Current Laravel handoff; old Next handoff replaced. |
| `DOMAIN_MODEL_REVIEW.md` | CURRENT | Aligned with the latest correction direction; still should be treated as review output, not original requirements. |
| `IMPLEMENTATION_STATUS.md` | CURRENT | Current Laravel status and verification numbers. |
| `MIGRATION_PLAN.md` | CURRENT | Current migration context with Spatie Activitylog and Phase 2 status. |
| `NEXT_TASKS.md` | CURRENT | Current Phase 3 Slice 7 recommendation. |
| `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md` | CURRENT | Corrected Phase 3 planning contract; Slices 1-6 are complete and remaining slices include pages, reports, stress/integrity wrap-up, and docs/final verification. |
| `PHASE_3_SLICE_1_GEMINI_PROMPT.md` | CURRENT_WITH_HISTORY | Bounded execution prompt already used for Phase 3 Slice 1; keep as traceability for what was requested. |
| `PHASE_3_SLICE_2_GEMINI_PROMPT.md` | CURRENT_WITH_HISTORY | Bounded execution prompt already used for Phase 3 Slice 2; keep as traceability for what was requested. |
| `PHASE_3_SLICE_3_GEMINI_PROMPT.md` | CURRENT_WITH_HISTORY | Bounded execution prompt already used for Phase 3 Slice 3: receipt/payment posting without allocations. |
| `PHASE_3_SLICE_4_GEMINI_PROMPT.md` | CURRENT_WITH_HISTORY | Bounded execution prompt already used for Phase 3 Slice 4: allocation engine without cheques/reconciliation/UI expansion. |
| `PHASE_3_SLICE_5_GEMINI_PROMPT.md` | CURRENT_WITH_HISTORY | Bounded execution prompt already used for Phase 3 Slice 5: cheque lifecycle without bank reconciliation/reports/UI expansion. |
| `PHASE_3_SLICE_6_GEMINI_PROMPT.md` | CURRENT_WITH_HISTORY | Bounded execution prompt already used for Phase 3 Slice 6: bank reconciliation and cash/bank statement foundations without import, broad UI, or automatic adjustment posting. |
| `ROADMAP.md` | CURRENT | Current phase statuses; still high-level planning. |
| `docs/CONCURRENCY_AUDIT.md` | CURRENT | Current concurrency/status review aligned with latest correction. |
| `docs/DESIGN_FOUNDATION.md` | PARTIALLY_STALE | Useful UI/design reference; not authoritative for business/domain relationships. |
| `docs/PROJECT_MAP.md` | PARTIALLY_STALE | Useful navigation map, but may include old Next/Laravel transition context. |
| `laravel/README.md` | CURRENT | Laravel setup/readme context. No business relationship proof. |
| `app/AGENTS.md` | LEGACY_REFERENCE | Old Next/app agent guidance. Not Laravel source of truth. |
| `app/CLAUDE.md` | LEGACY_REFERENCE | Old assistant/project guidance. Not Laravel source of truth. |
| `app/README.md` | CONTRADICTORY | Old Next foundation readme with tenant/isolation claims. |
| `app/src/modules/accounting/README.md` | LEGACY_REFERENCE | Old module scaffold. Does not prove Laravel implementation. |
| `app/src/modules/accruals/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/banks/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/budgeting/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/cash/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/cheques/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/cost-centers/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/customers/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/dashboard/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/equipment/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/equity/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/expenses/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/fixed-assets/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/inventory/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/payroll/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/prepaids/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/projects/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/purchasing/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/recurring/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/rentals/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/reports/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/sales/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/suppliers/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `app/src/modules/taxes/README.md` | LEGACY_REFERENCE | Old module scaffold. |
| `spec/ACCOUNTING_EVENT_MAP.md` | PARTIALLY_STALE | Useful accounting design reference. Phase 2 manual posting exists, but later operational posting events are not implemented. |
| `spec/ARCHITECTURE.md` | PARTIALLY_STALE | Architectural ideas must be filtered through latest owner corrections and actual Laravel code. |
| `spec/BUSINESS_RULES.md` | PARTIALLY_STALE | Useful rule catalog, but individual claims need owner/code verification. |
| `spec/DATABASE_DESIGN.md` | PARTIALLY_STALE | Post-audit corrected with legacy/status warnings and no default company/branch scope rule; still a planning reference, not source of truth. |
| `spec/DEPLOYMENT.md` | PARTIALLY_STALE | Deployment reference; verify against Laravel target before use. |
| `spec/DISASTER_RECOVERY.md` | PARTIALLY_STALE | Operational reference, not current implementation proof. |
| `spec/FINAL_ARCHITECTURE_REVIEW.md` | PARTIALLY_STALE | Review artifact. Must not override owner corrections. |
| `spec/INTEGRATION_MAP.md` | PARTIALLY_STALE | Integration planning reference. Most integrations are not implemented. |
| `spec/MASTER_ERP_SPEC.md` | PARTIALLY_STALE | Post-audit corrected with authority limits and no Company/Branch tenancy rule; still a future planning reference. |
| `spec/PERMISSION_MATRIX.md` | NEEDS_OWNER_DECISION | Permission catalog is useful, but any branch/company scope needs explicit owner approval. |
| `spec/PHASE1_STATUS.md` | STALE | Old Phase 1/Next completion snapshot. Not current Laravel status. |
| `spec/REPORT_CATALOG.md` | PARTIALLY_STALE | Report catalog is planning material. Reports are not implemented in Laravel. |
| `spec/REQUIREMENTS_TRACEABILITY.md` | PARTIALLY_STALE | Useful only where it maps to original owner requirements. Generated conclusions need validation. |
| `spec/SCREEN_CATALOG.md` | PARTIALLY_STALE | Screen planning catalog, not proof of Laravel page implementation. |
| `spec/SECURITY.md` | CURRENT | Post-audit corrected to Laravel deny-by-default security without tenant/company scope. |
| `spec/TESTING_STRATEGY.md` | PARTIALLY_STALE | Strategy reference; current Laravel tests cover foundation, not complete ERP. |
| `spec/TRACEABILITY_MATRIX_V2.md` | PARTIALLY_STALE | Traceability reference. Must be checked against owner requirements and current Laravel implementation. |
| `spec/WORKFLOW_CATALOG.md` | PARTIALLY_STALE | Workflow catalog describes intended workflows; most are not implemented in Laravel. |

## Contradiction Matrix

| Claim family | Found in docs | Current status |
| --- | --- | --- |
| Application is multi-tenant / company is tenant | Current priority docs corrected post-audit; old status/history docs may still mention it as legacy | REMOVE. Latest owner correction rejects this assumption. |
| Company owns users | Old task/status docs and generated specs | UNDEFINED - DO NOT ASSUME. No `company_user`, no `user.company_id`. |
| Company owns branches | `spec/DATABASE_DESIGN.md`, generated docs, old tasks | UNDEFINED - DO NOT ASSUME. Current `branch` table has no `company_id`. |
| Roles/permissions are company scoped | Old onboarding/company-admin tasks | REMOVE. Spatie teams are disabled; roles are global templates. |
| Every query scoped by company_id | Corrected in `spec/SECURITY.md`; stale historical/generated references may remain | CONTRADICTORY if treated as current. No current company context exists. |
| Document numbers unique per company/branch | database specs and old tasks | UNDEFINED. Current sequence identity is global `key`; company/branch dimensions removed. |
| Fiscal years owned by company/tenant | old schema/audit notes before owner decision | REMOVE. Current Laravel schema has global `fiscal_year.year` and no `fiscal_year.company_id`. |
| Accounting/posting complete | Status/spec wording in several files | IMPLEMENTED for Phase 2 manual/opening-balance ledger spine only; later operational module posting is not implemented. |
| Old Next.js Phase 1 complete | `app/README.md`, `spec/PHASE1_STATUS.md`, `CONTINUE_HERE.md` | LEGACY_REFERENCE only. Laravel target is current. |

## Documentation Handling Rules

Use these rules before implementing future work:

1. Treat owner corrections as newer and stronger than generated docs.
2. Treat generated specs as planning/reference, not original requirements.
3. Do not add `company_id`, `branch_id`, tenant context, ownership relations, or authorization scopes unless explicitly confirmed.
4. Do not claim a later module is implemented because a README exists under the old Next app.
5. Update stale docs only in a dedicated documentation correction pass.
