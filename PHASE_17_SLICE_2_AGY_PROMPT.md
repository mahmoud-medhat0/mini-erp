# Mini ERP - Phase 17 Slice 2 Agy Prompt

You are working in the existing Mini ERP Laravel repository.

Implement ONLY Phase 17 Slice 2: Route Authorization Audit Command and Regression Guard.

## Objective

Create a read-only defensive route audit command that verifies protected Laravel web routes have explicit server-side authorization middleware, or are intentionally allowed because authorization is handled by a service-level entity authorizer.

This slice must make route authorization drift visible to developers and operators. It must not change business workflows unless a missing guard is proven by the command and fixed with tests.

## Non-Negotiable Rules

- Expected migrations: 0.
- Do not add `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, Spatie Teams, company-user membership, or tenant scope.
- Do not treat Branch as tenant, login context, user scope, or blanket security boundary.
- Do not modify PostingEngine, tax posting, inventory costing, payroll posting, or any business math.
- Do not add UI pages.
- Do not add deployment/server automation.
- Controllers must stay clean; route-audit logic belongs in a console command/support service.
- Do not print real secrets, `.env` contents, connection strings, or passwords.

## Required Implementation

1. Create a read-only Artisan command:
   - command signature: `security:route-audit`
   - options:
     - `--strict`: return non-zero exit code if any route is classified as failing
     - `--json`: emit machine-readable JSON summary
   - normal table/text mode must be human-readable.
2. The command must inspect `Route::getRoutes()`.
3. Classify each route into exactly one category:
   - `public`: no `auth` middleware and explicitly public/guest-safe route
   - `guest`: guest auth routes such as login render/submit
   - `explicitly_authorized`: auth route with middleware starting with `can:`, `permission.any:`, or `permission.all:`
   - `service_authorized_allowlist`: auth route intentionally allowed because service/controller authorizes by entity/user internally
   - `failing`: auth route without explicit authorization middleware and not in the service-authorized allowlist
4. The service-authorized allowlist must be centralized and documented in code, not scattered across tests:
   - `foundation`
   - `logout`
   - `notifications`
   - `notifications.read_all`
   - `notifications.read`
   - `attachments.index`
   - `attachments.store`
   - `attachments.show`
   - `attachments.destroy`
   - locale-setting route if it exists and is auth-only by design
5. The command output must include:
   - total route count scanned
   - count per category
   - failing route names and URIs
   - allowlisted auth-only route names and reason string
6. `--json` output must be valid JSON and include:
   - `total`
   - `counts`
   - `failures`
   - `allowlisted`
7. `--strict` must return:
   - exit code `0` when no failures exist
   - exit code `1` when at least one failing route exists
8. Reuse the same central audit service/list from both command and tests if practical.

## Required Tests

Add/extend tests, preferably in `tests/Feature/SecurityHardeningTest.php` unless a new focused file is cleaner.

Minimum coverage:

- `php artisan security:route-audit` succeeds on current route table.
- `php artisan security:route-audit --strict` succeeds on current route table.
- `php artisan security:route-audit --json` returns valid JSON with expected keys.
- A dynamically registered auth-only route with no authorization middleware is classified as `failing`.
- Strict mode returns exit code `1` when the dynamic failing route exists.
- All service-authorized allowlist route names are documented with non-empty reason strings.
- Existing route-level regression test continues to pass.
- No tenant/company/branch security scope is introduced.

## Documentation Updates

Update:

- `spec/SECURITY.md`
- `PHASE_17_SECURITY_ACCESS_GOVERNANCE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

Create:

- `PHASE_17_SLICE_2_REPORT.md`

## Required Verification Commands

Run from `laravel/` and report exact results:

```powershell
php artisan security:route-audit
php artisan security:route-audit --strict
php artisan security:route-audit --json
vendor/bin/pint --test
php artisan test --filter=SecurityHardeningTest --compact
php artisan test --filter=AuthenticationTest --compact
npm run typecheck
```

If frontend files are not changed, do not run `npm run build` unless needed. If frontend files are changed, `npm run build` is mandatory.

## Required Source Scans

Run and classify:

```powershell
rg -n "company_id|tenant_id|currentCompany|currentTenant|Spatie Teams" laravel/app laravel/routes laravel/tests spec/SECURITY.md PHASE_17_SLICE_2_REPORT.md
rg -n "Route::.*middleware\\('auth'\\)|->middleware\\('auth'\\)" laravel/routes/web.php
rg -n "security:route-audit|service_authorized_allowlist|explicitly_authorized|failing" laravel/app laravel/tests PHASE_17_SLICE_2_REPORT.md
```

## Final Report

`PHASE_17_SLICE_2_REPORT.md` must include:

- files changed
- command behavior and options
- classification model
- allowlist and reason strings
- exact test results
- no-scope scan classification
- whether any route guard was changed
- remaining risks

Stop after this slice. Do not start Slice 3.
