# Domain Relationship Audit

Date: 2026-08-21

Evidence rule: if a relationship is not explicitly supported by original owner requirements or latest owner correction, classify it as `UNDEFINED - DO NOT ASSUME`. Do not infer ownership from ERP convention, generated docs, Prisma schema, Laravel migrations, or tests.

Post-audit correction note: the misleading global role template `COMPANY_ADMIN` was renamed to `ERP_ADMIN`. It remains a global role template and does not imply Company ownership. FiscalYear ownership/context is now resolved as `SINGLE-ERP CONTEXT`: global fiscal years, no Company/Tenant semantics.

## Relationship Classification Matrix

| Relationship / Concept | Classification | Current Laravel evidence | Decision |
| --- | --- | --- | --- |
| Company exists as configurable business profile | DERIVED | `company` table and settings page exist. | Keep as configuration entity, not tenant proof. |
| Company = Tenant | REMOVE | No tenant middleware/context. Latest owner correction rejects it. | Do not reintroduce. |
| Multi-company architecture | UNDEFINED - DO NOT ASSUME | No source-of-truth requirement confirms it. | Needs owner requirement before design. |
| Company -> User | UNDEFINED - DO NOT ASSUME | No `company_user`, no `users.company_id`. | Do not add. |
| User -> Company | UNDEFINED - DO NOT ASSUME | No membership/current company context. | Do not add. |
| Company -> Role | LEGACY/AI ASSUMPTION | Roles are global; Spatie teams disabled. | Removed/avoid. |
| Company -> Permission | LEGACY/AI ASSUMPTION | Permissions are global module/action records. | Removed/avoid. |
| Company -> Branch | UNDEFINED - DO NOT ASSUME | `branch` has no `company_id`; no Eloquent relationship confirmed. | Do not add. |
| Branch concept as reporting dimension | DERIVED | Owner correction says branch is referenced as possible project/department/branch dimension. | Keep concept cautious. |
| Exact Branch model | UNDEFINED - DO NOT ASSUME | Standalone `branch` table exists, but no original requirement proves ownership/security semantics. | Needs owner decision. |
| Branch.company_id | REMOVE | Current schema has no column. | Do not reintroduce. |
| Branch as tenant/security boundary | REMOVE | No confirmed requirement. | Do not use for authorization scope. |
| Branch code unique per company | REMOVE | Current schema has no company dimension. | Do not add. |
| Branch code globally unique | UNDEFINED - DO NOT ASSUME | Current schema has no unique `code`. | Needs owner decision if required. |
| User exists | CONFIRMED | `users` table, auth, tests. | Keep. |
| User -> Role | CONFIRMED | Spatie `model_has_roles`. | Keep. |
| User -> Permission | CONFIRMED | Spatie `model_has_permissions`. | Keep. |
| Role -> Permission | CONFIRMED | Spatie `role_has_permissions`. | Keep. |
| User -> Branch | UNDEFINED - DO NOT ASSUME | No schema/model relationship. | Do not add. |
| User = Employee | UNDEFINED - DO NOT ASSUME | No employee table/relationship in Laravel. | Do not infer. |
| User -> Notification | CONFIRMED | `notification.user_id` FK cascade delete. | Keep as target-user relationship. |
| User -> AuditLog as actor | CONFIRMED | `audit_log.actor_id` FK set null on delete. | Keep; consider soft deletes for audit fidelity later. |
| User -> Attachment uploaded_by | DERIVED | `attachment.uploaded_by` FK set null on delete. | Keep for provenance; not authorization. |
| Role templates | DERIVED | `RbacSeeder` seeds global templates. | Keep as global templates. |
| `ERP_ADMIN` role name | CONFIRMED GLOBAL RBAC TEMPLATE | Replaces legacy `COMPANY_ADMIN`; no company ownership implied. | Keep as global template. |
| Spatie Teams | REMOVE | `permission.teams` is false. | Keep disabled. |
| Permission `scope_json` | DERIVED | Nullable column exists on permission pivots. | Do not assign semantics until requirements define scope. |
| Company -> FiscalYear | REMOVE | `fiscal_year.company_id` removed. | Do not reintroduce. |
| FiscalYear global year identity | CONFIRMED BY OWNER DECISION | `fiscal_year.year` is globally unique. | Keep single-ERP context. |
| FiscalYear -> FinancialPeriod | CONFIRMED BY OWNER DECISION | `financial_period.fiscal_year_id` FK exists. | Keep. |
| Account -> FiscalYear/Company | NOT IMPLEMENTED / UNDEFINED | No account table in Laravel target. | Do not design yet. |
| NumberSequence -> Company | REMOVE | No `number_sequence.company_id`. | Keep removed. |
| NumberSequence -> Branch | REMOVE | No `include_branch` or branch FK. | Keep removed. |
| NumberSequence identity | NEEDS_OWNER_DECISION | Current unique identity is global `key`. | Confirm document type/year/reset dimensions. |
| AuditLog -> Company/Branch | REMOVE | No company/branch columns. | Keep removed. |
| AuditLog -> Actor and audited entity/event | CONFIRMED | Actor FK plus `entity_type`, `entity_id`, `action`. | Keep. |
| Attachment -> Company | REMOVE | No company column. | Keep removed. |
| Attachment -> Business Entity | DERIVED | Uses `entity_type` and `entity_id`. | Keep, but add entity authorization before production use. |
| Notification -> Company | REMOVE | No company column. | Keep removed. |
| Notification -> User | CONFIRMED | `user_id` FK and query scope. | Keep. |
| Notification -> Business Event/Entity | DERIVED | `target_ref` and `dedupe_key` exist. | Keep generic until modules define entities. |
| Customer -> Company/Branch/User | NOT IMPLEMENTED / UNDEFINED | No current Laravel customer table. | Do not infer. |
| Supplier -> Company/Branch/User | NOT IMPLEMENTED / UNDEFINED | No current Laravel supplier table. | Do not infer. |
| Warehouse -> Branch | NOT IMPLEMENTED / UNDEFINED | No current Laravel warehouse table. | Do not infer. |
| Project -> Branch/Department/CostCenter | NOT IMPLEMENTED / UNDEFINED | No current Laravel tables. | Treat as analytic dimensions only when explicitly defined. |
| CostCenter -> Company/Branch | NOT IMPLEMENTED / UNDEFINED | No current Laravel table. | Do not infer. |
| Department -> Company/Branch | NOT IMPLEMENTED / UNDEFINED | No current Laravel table. | Do not infer. |
| Employee -> User | NOT IMPLEMENTED / UNDEFINED | No current Laravel employee table. | Do not infer. |
| Payroll -> Employee/User/Company | NOT IMPLEMENTED / UNDEFINED | No current Laravel payroll tables. | Do not infer. |

## Confirmed Relationships

These are safe to build on now:

- Authenticated users exist.
- Users can have roles and direct permissions.
- Roles can have permissions.
- Notifications target users.
- Audit records link to an actor when available and to an audited entity/event reference.
- Attachments record uploader provenance and generic entity reference.
- Fiscal years are global to the ERP installation/business profile.
- Financial periods belong to fiscal years.

## Removed Or Unsupported Assumptions

These must not be used:

- `company_user`
- `users.company_id`
- `Company::users()`
- `User::companies()`
- `currentCompany`
- `currentBranch`
- `branch.company_id`
- `Company::branches()`
- `Branch::company()`
- company-scoped roles
- company-scoped permissions
- Spatie teams
- `number_sequence.company_id`
- `number_sequence.include_branch`
- `fiscal_year.company_id`
- `audit_log.company_id`
- `audit_log.branch_id`
- `attachment.company_id`
- `notification.company_id`

## Needs Owner Decision

1. Does the ERP support exactly one company profile, or multiple company profiles without tenant semantics, or something else?
2. Is Branch a master-data table, free-form reporting dimension, physical location, or future module concept?
3. Should branch codes be unique globally, non-unique labels, or not stored as master data?
4. What are the exact document-numbering dimensions: document type, year, fiscal year, legal sequence, country, business unit, or other?
5. Should additional global role-template names be adjusted for clarity as more modules move to Laravel?

## Implementation Guardrail

Future code should require one of these before adding a relationship:

- Direct owner requirement.
- Explicit later owner correction.
- Existing implemented business workflow that requires the relationship and does not contradict owner corrections.

Generated docs, natural ERP convention, old Next.js code, Prisma schema, and current Laravel leftovers are not enough.
