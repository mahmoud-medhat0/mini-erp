# Domain Model Review - Laravel Migration Correction

Date: 2026-08-21

## Purpose

This review corrects the Laravel migration target after the previous prompt incorrectly treated the ERP as a multi-tenant SaaS. The target architecture must port the real ERP domain, not a tenant-per-company abstraction.

## Sources Reviewed

- `spec/MASTER_ERP_SPEC.md`
- `spec/DATABASE_DESIGN.md`
- `spec/BUSINESS_RULES.md`
- `spec/WORKFLOW_CATALOG.md`
- `spec/ACCOUNTING_EVENT_MAP.md`
- `spec/PERMISSION_MATRIX.md`
- `app/prisma/schema.prisma`
- `app/src/core/rbac/*`
- `app/src/core/tenant/context.ts`
- `app/src/modules/company/application/*`

## 1. Core Entities

Confirmed by the specification:

| Entity | Status | Source |
|---|---|---|
| Company | PRESERVE | Business/configuration entity; company header/settings/base currency |
| Branch | PRESERVE | Business organization under company |
| User | PRESERVE | Authentication identity and application actor |
| Role | PRESERVE | RBAC role template/custom role |
| Permission | PRESERVE | Module/feature/action catalog |
| RolePermission | PRESERVE | Role-permission link with optional `scope_json` |
| UserRole | PRESERVE | User-role assignment with optional `scope_json` |
| Employee | PRESERVE | Payroll/equipment business entity, separate from User unless later specified |
| FiscalYear/FinancialPeriod | PRESERVE | Accounting period structure |
| NumberSequence | PRESERVE | Document numbering by business context |
| AuditLog | PRESERVE | Append-only operational history |
| Attachment/Notification | PRESERVE | Cross-cutting ERP features |

## 2. Authentication Identity

`User` answers "who is signed in?" It carries login credentials and preferences such as email, name, locale, theme, active status, and MFA state.

Classification: PRESERVE.

Must not assume:

- User is an Employee.
- User owns a Company.
- User login automatically selects a current tenant.
- User login automatically selects a current company or branch.

## 3. Company Relationship

`Company` is a domain/business entity, not a SaaS tenant.

Explicitly supported:

- Business records carry `company_id` where the spec says they are company-scoped.
- Accounting/reporting/settings may be per company.
- `DATABASE_DESIGN.md` says `company` has branches and users.

Not fully defined:

- Whether user-company assignment is direct FK, pivot, role scope, or another access model.
- Whether first-run onboarding creates a company.
- Whether a signed-in user must always have an active company.
- Whether a company has an "owner" user.

Classification:

- Company as accounting/business scope: PRESERVE.
- Company as tenant boundary: REMOVE.
- Login-time current company requirement: UNDEFINED - DO NOT ASSUME.
- Owner membership during onboarding: UNDEFINED - DO NOT ASSUME.

## 4. Branch Relationship

Explicitly supported:

- `branch.company_id` references `company`.
- Branch codes are unique per company.
- Business records and report filters may use `branch_id`.
- Permission scopes may restrict branch access when such a restriction is assigned.

Classification:

- Company to Branch: PRESERVE.
- Branch as tenant boundary: REMOVE.

## 5. Employee Relationship

Explicitly supported:

- `employee` is used by payroll, equipment custody, loans, advances, expenses, and other business flows.

Not defined:

- User to Employee mapping.
- Employee as authentication identity.

Classification:

- Employee as business entity: PRESERVE.
- User = Employee: UNDEFINED - DO NOT ASSUME.

## 6. User Relationship

Explicitly supported:

- User has roles through `user_role`.
- User can be actor in audit records.
- Notifications target users.

Not defined enough to implement as tenant logic:

- User belongs to one current company.
- User must be redirected to onboarding if no company exists.
- User-company assignment creates authorization automatically.

Classification:

- User to Role: PRESERVE.
- User to Company as login tenant context: REMOVE.
- User-company access semantics: UNDEFINED - DO NOT ASSUME beyond explicit future requirements.
- Laravel Eloquent `User::companies()` / `Company::users()` relationships are not introduced until that access model is specified.

## 7. Role Relationship

Explicitly supported:

- Role links to permissions through `role_permission`.
- Role templates plus custom roles are part of RBAC.
- `scope_json` can restrict role/permission grants by business scope.

Not supported as a default:

- Role belongs to Company.
- Role is a Spatie team role.
- Roles are duplicated per company during onboarding.

Classification:

- Global role templates/custom roles: PRESERVE.
- Company-owned roles by default: REMOVE.

## 8. Permission Relationship

Explicitly supported:

- Permission catalog is module/feature/action.
- Permission checks are server-side.
- Permission-denied UI state is required.

Not supported:

- Permission belongs to Company.
- Permission belongs to Tenant.

Classification:

- Module/action permissions: PRESERVE.
- Tenant-scoped permission tables: REMOVE.

## 9. Authorization Scopes

Explicitly supported scopes:

- Company
- Branch
- Warehouse
- Project
- Cost center
- Document type

Correct interpretation:

Scopes are authorization/business constraints on a permission assignment or operation. They are not a tenant architecture.

Classification:

- `scope_json` on role/user assignments: PRESERVE.
- Generic `TenantScope` abstraction: REMOVE.
- Browser-provided scope decisions: REMOVE.

## 10. Accounting Ownership

Explicitly supported:

- Accounting settings, mappings, periods, sequences, and reports can be per company/branch where specified.
- Journal/accounting lines can carry branch/project/cost center dimensions.
- Posted data is immutable and corrected by reversal.

Classification:

- Company/branch as accounting dimensions: PRESERVE.
- Company/branch as SaaS tenants: REMOVE.

## 11. Organizational Hierarchy

Confirmed:

- Company -> Branch.
- CostCenter can represent department, branch, project, or unit.
- Warehouse/location exists for inventory.
- Project exists for project accounting.
- Employee has department/position fields.

Undefined:

- User -> Employee.
- Department table.
- User -> Branch default.
- User -> Warehouse assignment model.

## 12. Explicitly Supported Relationships

| Relationship | Classification |
|---|---|
| Company -> Branch | PRESERVE |
| Business records -> Company/Branch where specified | PRESERVE |
| User -> Role | PRESERVE |
| Role -> Permission | PRESERVE |
| RolePermission/UserRole -> scope_json | PRESERVE |
| Notification -> User | PRESERVE |
| AuditLog -> Actor/User | PRESERVE |
| Employee -> payroll/equipment/expense flows | PRESERVE |

## 13. Relationships That Must Not Be Assumed

| Assumption | Classification |
|---|---|
| Company = Tenant | REMOVE |
| TenantContext / CurrentTenant | REMOVE |
| Tenant middleware | REMOVE |
| Spatie teams with `company_id` | REMOVE |
| Role belongs to Company by default | REMOVE |
| Permission belongs to Company by default | REMOVE |
| First-run onboarding creates owner membership | REMOVE until explicitly specified |
| Auth session establishes current company | REMOVE |
| Auth session establishes current branch | REMOVE |
| User = Employee | UNDEFINED - DO NOT ASSUME |
| Employee = User | UNDEFINED - DO NOT ASSUME |
| Company owns Users | UNDEFINED - DO NOT ASSUME as an ownership/security model |
| Branch is a tenant boundary | REMOVE |

## Multi-Tenant Assumptions Identified

| Location | Assumption | Action |
|---|---|---|
| `app/prisma/schema.prisma` comment | Queries filter by tenant | Treat as old implementation artifact |
| `app/src/core/tenant/context.ts` | Tenant context derived from session | Do not port to Laravel |
| `app/src/core/errors/index.ts` | `CrossTenantError` | Do not port as tenant error |
| `app/src/core/auth/server.ts` | Missing company redirects to onboarding | Do not port to Laravel |
| `app/src/core/db/repositories/companyRepo.ts` | Company provisioning seeds company roles and owner membership | Do not port until spec explicitly defines onboarding |
| Prior Laravel M5 commit | Tenant middleware and Inertia `tenant` prop | Removed from Laravel |
| Prior Laravel Spatie setup | Teams via `company_id` | Removed from Laravel |

## Correct Laravel Target

- Laravel session authentication remains.
- Spatie Permission remains, without teams and without company-owned role tables.
- `scope_json` remains for explicit business authorization scopes.
- Inertia shared props expose `auth.user`, `auth.permissions`, `locale`, `direction`, `theme`, `notifications`, and `flash`.
- Company and Branch remain ERP business entities.
- Any selected company/branch context must be implemented later as an explicit business workflow, not as tenant context.
