# Markdown Documentation Audit

Date: 2026-08-21

Scope: recursive markdown review of files returned by `rg --files -g "*.md"` at review time. The new audit files created in this pass are outputs and were not part of the initial inventory.

Post-audit correction note: the highest-risk current docs (`README.md`, `spec/DATABASE_DESIGN.md`, `spec/SECURITY.md`, and `spec/MASTER_ERP_SPEC.md`) were corrected after this audit to prevent restoring Company/Branch tenancy or misleading completion claims. Historical files may still quote old Next.js behavior when clearly treated as legacy history.

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
4. The current Laravel implementation is foundation-only. Docs claiming complete ERP behavior, complete module implementation, or full accounting posting are stale unless explicitly scoped to foundation/invariants.

## File Classification

| File | Classification | Notes |
| --- | --- | --- |
| `README.md` | CURRENT | Post-audit corrected to describe the Laravel foundation and forbid Company/Branch tenancy assumptions. |
| `CHANGELOG.md` | PARTIALLY_STALE | Useful history, but contains old Next.js and tenant/company-scope entries. Keep as history only. |
| `CONTINUE_HERE.md` | LEGACY_REFERENCE | Old handoff/status file with completed Next tasks and unsupported company-scope/onboarding assumptions. |
| `DOMAIN_MODEL_REVIEW.md` | CURRENT | Aligned with the latest correction direction; still should be treated as review output, not original requirements. |
| `IMPLEMENTATION_STATUS.md` | PARTIALLY_STALE | Useful current status plus historical Next/Laravel migration notes. Needs careful reading. |
| `MIGRATION_PLAN.md` | PARTIALLY_STALE | Good migration context, but not proof of business relationships. |
| `NEXT_TASKS.md` | LEGACY_REFERENCE | Historical task list. Contains old company/tenant assumptions and prior completion claims. |
| `ROADMAP.md` | PARTIALLY_STALE | High-level planning reference. Not implementation proof. |
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
| `spec/ACCOUNTING_EVENT_MAP.md` | PARTIALLY_STALE | Useful accounting design reference, but posting is not implemented in Laravel. |
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
| Accounting/posting complete | Status/spec wording in several files | NOT IMPLEMENTED beyond invariant kernel. |
| Old Next.js Phase 1 complete | `app/README.md`, `spec/PHASE1_STATUS.md`, `CONTINUE_HERE.md` | LEGACY_REFERENCE only. Laravel target is current. |

## Documentation Handling Rules

Use these rules before implementing future work:

1. Treat owner corrections as newer and stronger than generated docs.
2. Treat generated specs as planning/reference, not original requirements.
3. Do not add `company_id`, `branch_id`, tenant context, ownership relations, or authorization scopes unless explicitly confirmed.
4. Do not claim a module is implemented because a README exists under the old Next app.
5. Update stale docs only in a dedicated documentation correction pass.
