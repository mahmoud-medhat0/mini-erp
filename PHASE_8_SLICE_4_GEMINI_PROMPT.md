# MINI ERP - PHASE 8 SLICE 4 BROWSER SMOKE / E2E FOUNDATION

Execute only after Slice 3 is complete.

This slice creates a small browser smoke foundation for critical ERP pages.

## Objective

Add minimal browser smoke coverage for high-value flows without changing ERP business logic.

## Read First

- `PHASE_8_OPERATIONAL_READINESS.md`
- `PHASE_8_OPERATIONAL_READINESS_DECISION.md`
- `PHASE_7_FINAL_VERIFICATION_REPORT.md`
- `laravel/package.json`
- `laravel/routes/web.php`
- `laravel/database/seeders`
- current authentication tests and bootstrap admin seeding

## Smoke Coverage

Cover only basic page reachability and permission behavior:

- login page loads
- bootstrap admin can sign in in local/test mode
- dashboard loads
- reports hub loads
- tax code page loads
- VAT register page loads
- unauthorized or low-permission user cannot access a financial report page

Keep smoke tests stable and small.

## Allowed

- add Playwright only if the project does not already have browser smoke tooling and dependency installation is acceptable
- add npm scripts for smoke tests
- add a small seed/setup helper for test mode if needed
- add documentation for running smoke tests locally

## Prohibited

- no new business workflows
- no destructive data cleanup outside the test database
- no real environment values
- no external services
- no screenshots committed unless explicitly required
- no hardcoded visible UI text assertions where route/status assertions are sufficient

## Verification

Run sequentially:

```powershell
php artisan migrate --force
vendor/bin/pint --test
php artisan test
npm run typecheck
npm run build
npm run e2e:smoke
```

If browser tooling cannot run locally, document the blocker and add route-level feature coverage instead.

## Final Report

Report:

1. browser tooling added/reused
2. smoke flows covered
3. setup requirements
4. command results
5. fallback coverage if browser tooling was unavailable

