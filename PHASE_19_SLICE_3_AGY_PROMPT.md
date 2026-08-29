# Mini ERP - Phase 19 Slice 3 Agy Prompt

Execute ONLY Phase 19 Slice 3: Persona, RBAC, and Owner Execution Script.

Stop after this slice. Do not start Slice 4.

## Scope

Prove that the product can be tested by realistic roles/personas, and create a compact execution script that a business owner or accountant can follow during hands-on acceptance.

This slice is primarily tests and documentation. UI changes are allowed only if a small dictionary-backed usability issue blocks acceptance.

## Non-Negotiable Rules

- No multi-tenant architecture and no company/tenant/security scope changes.
- Branch is operational/reporting only, not a tenant or login scope.
- Do not change route permissions unless a failing test proves an actual mismatch with existing RBAC policy.
- Do not write Telegram credentials, chat IDs, API keys, passwords, or production secrets to files.
- No hardcoded visible strings in React pages.
- Do not add native `<select>`, `<option>`, `type="date"`, `dangerouslySetInnerHTML`, or `window.location.href`.
- Keep controllers thin.

## Required Review Before Editing

Inspect:

- `config/erp_rbac.php`
- `laravel/app/Console/Commands/Security/RouteAuthorizationAuditCommand.php`
- `laravel/tests/Feature/SecurityHardeningTest.php`
- `laravel/tests/Feature/Phase18ProductAcceptanceTest.php`
- `PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md`
- routes under `laravel/routes/web.php`

## Required Implementation

Create `OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md` in the repository root.

It must be concise and usable by a non-technical owner/accountant:

- who logs in
- which demo data/seeder to run before the session
- the 10-15 most important walkthrough steps
- what evidence to capture for sign-off
- what is considered a blocking issue
- what is not a blocking issue
- what must not be tested in production without operator approval

Add a role/persona acceptance test section to `Phase19AccountantAcceptanceTest` or a new focused test:

1. Super admin can access all representative acceptance routes.
2. Accountant/finance persona can access accounting, reports, AR/AP, tax, fixed assets, expenses, and budget views needed for acceptance.
3. Sales persona can access sales/customer workflows but not payroll/settings/global accounting posting screens unless explicitly allowed by existing RBAC.
4. Purchasing persona can access purchase/supplier workflows but not payroll/settings/global accounting posting screens unless explicitly allowed by existing RBAC.
5. Inventory persona can access stock/warehouse workflows but not payroll/settings/global accounting posting screens unless explicitly allowed by existing RBAC.
6. Auditor/read-only persona can access reports/audit/log evidence where existing RBAC supports it and cannot perform mutating financial actions.
7. Guest users are redirected/blocked from all acceptance routes.
8. Strict route audit remains green.

Use existing roles/permissions from `config/erp_rbac.php` and seeders. Do not invent broad new roles unless existing RBAC lacks any way to express a necessary persona; if a new role template is added, document it and seed it idempotently.

## Documentation

Create `PHASE_19_SLICE_3_REPORT.md` with:

- exact files changed
- persona matrix summary
- access-control evidence
- execution script summary
- no-scope scan result
- UI unsafe-control scan result if frontend changed
- route audit result
- remaining risks

Update:

- `PHASE_19_ACCOUNTANT_ACCEPTANCE_EXECUTION.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

## Verification Commands

Run from `laravel/` and report exact results:

```powershell
vendor/bin/pint --test
php artisan test --filter=Phase19AccountantAcceptanceTest --compact
php artisan test --filter=SecurityHardeningTest --compact
php artisan security:route-audit --strict
npm run typecheck
```

Run `npm run build` only if frontend files changed.

## Final Rule

Stop after Phase 19 Slice 3 and create the report. Do not start Slice 4.
