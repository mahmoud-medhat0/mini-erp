# Mini ERP - Phase 17 Slice 1 Agy Prompt

You are working in the existing Mini ERP Laravel repository.

Implement ONLY Phase 17 Slice 1: Controlled Bootstrap Admin and First-User Privilege Seeding Guard.

## Objective

The current repository contains `Database\Seeders\FirstUserSuperAdminSeeder`, which assigns `SUPER_ADMIN` to the first user automatically. This is acceptable only as an explicit local/operator-controlled recovery tool, not as implicit privilege escalation.

Make the behavior fail-closed and explicitly configurable:

- default: disabled
- local/testing/development: can be enabled with an explicit env/config flag
- production: can be enabled only when an additional exact confirmation value is supplied
- every assignment remains audited through the existing `App\Domain\Audit\AuditLogger`

Do not remove `BootstrapUserSeeder`. Do not remove existing roles or permissions.

## Non-Negotiable Rules

- Do not add `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, Spatie Teams, or company/user membership semantics.
- Do not turn Branch into a security boundary, tenant, login context, or blanket authorization scope.
- Do not change business modules.
- Do not add deployment automation.
- Do not weaken the existing `SUPER_ADMIN`, RBAC, audit, auth, or active-user protections.
- Keep controllers out of this unless absolutely necessary.
- Do not print or commit real secrets.

## Required Implementation

1. Update `config/erp_auth.php` with a dedicated first-user admin configuration block:
   - `first_user_super_admin.enabled`
   - `first_user_super_admin.production_confirmation`
   - `first_user_super_admin.required_production_confirmation`
   - optional documented label/reason fields if useful
2. Environment variable names must be documented but must not contain secrets:
   - `ERP_ASSIGN_FIRST_USER_SUPER_ADMIN=false`
   - `ERP_FIRST_USER_SUPER_ADMIN_PRODUCTION_CONFIRM=`
3. Default behavior must be disabled.
4. `FirstUserSuperAdminSeeder` must:
   - no-op when disabled
   - assign only the earliest existing user when enabled and role exists
   - not create users
   - not assign to multiple users
   - audit successful assignment
   - in production, refuse to run unless `production_confirmation` exactly equals `required_production_confirmation`
   - throw a clear `RuntimeException` on unsafe production enablement, rather than silently granting privileges
5. Update `DatabaseSeeder` and `AccountingDemoSeeder` only if needed to preserve safe behavior with the new disabled default.
6. Update docs:
   - `spec/SECURITY.md`
   - `spec/ENVIRONMENT_CHECKLIST.md`
   - `README.md` or `NEXT_TASKS.md` only if they currently imply implicit first-user elevation
   - `IMPLEMENTATION_STATUS.md`, `CONTINUE_HERE.md`, `CHANGELOG.md`
7. Add or update tests:
   - default config does not assign `SUPER_ADMIN`
   - explicitly enabled local/testing config assigns `SUPER_ADMIN` to the first user only
   - no user means no-op
   - missing role means no-op
   - production enabled without confirmation throws and does not assign
   - production enabled with exact confirmation assigns
   - assignment is audited
   - no company/tenant/branch security scope is introduced

## Existing Tests To Inspect And Preserve

- `tests/Feature/AuthenticationTest.php`
- `tests/Feature/SecurityHardeningTest.php`
- `tests/Integration/FoundationSeederTest.php`

Adjust old tests only to reflect the new safer explicit-config behavior.

## Required Verification Commands

Run these from `laravel/` and report exact results:

```powershell
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=AuthenticationTest --compact
php artisan test --filter=SecurityHardeningTest --compact
php artisan test --filter=FoundationSeederTest --compact
php artisan test --filter=Phase16 --compact
npm run typecheck
```

If frontend files are not changed, `npm run build` is optional. If frontend files are changed, it is mandatory.

## Required Source Scans

Run and report:

```powershell
rg -n "company_id|tenant_id|currentCompany|currentTenant|Spatie Teams" laravel/config laravel/database/seeders laravel/tests/Feature/AuthenticationTest.php laravel/tests/Feature/SecurityHardeningTest.php spec/SECURITY.md spec/ENVIRONMENT_CHECKLIST.md
rg -n "ERP_ASSIGN_FIRST_USER_SUPER_ADMIN|ERP_FIRST_USER_SUPER_ADMIN_PRODUCTION_CONFIRM|first_user_super_admin" laravel/config laravel/database/seeders laravel/tests spec README.md NEXT_TASKS.md IMPLEMENTATION_STATUS.md CONTINUE_HERE.md CHANGELOG.md
```

The first scan may match policy text in documentation/tests only. It must not show a new implementation dependency on tenant/company scope.

## Final Report

Create `PHASE_17_SLICE_1_REPORT.md` with:

- files changed
- exact behavior before vs after
- config/env variables
- production confirmation rule
- audit behavior
- test results
- source-scan classification
- remaining risks

Stop after this slice. Do not start Slice 2.
