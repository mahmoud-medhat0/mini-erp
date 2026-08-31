# Security Hardening Report - 2026-08-24

Status: COMPLETE & VERIFIED

Scope: Laravel security hardening after Phase 10. No new business module, tenant scope, company scope, or branch security boundary was introduced.

## Changes Implemented

- Added baseline web security headers through `AddSecurityHeaders` middleware:
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: SAMEORIGIN`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()`
  - `X-Permitted-Cross-Domain-Policies: none`
  - `Cross-Origin-Opener-Policy: same-origin`
- Added configurable CSP support through `config/security.php`, disabled by default until production asset policy is finalized.
- Added `EnsureUserIsActive` middleware so inactive authenticated users are logged out before protected page access.
- Added `permission.any` and `permission.all` middleware aliases for clean route-level least-privilege checks.
- Added explicit route-level authorization for dashboard, settings, foundation diagnostics, audit log, accounting, reports, fixed assets, taxes, and export/financial actions.
- Registered `taxes.file` as a sensitive capability so tax filing is not granted by the general accountant tax module grant.
- Disabled framework direct serving of `storage/app/private` by default via `FILESYSTEM_LOCAL_SERVE=false`; attachment access remains through `AttachmentService` and entity authorization.
- Added `SecurityHardeningTest` regression coverage for security headers, inactive-user enforcement, dashboard/audit authorization, sensitive tax filing permission seeding, and authenticated route authorization coverage.

## Explicit Exceptions

The following authenticated routes intentionally remain auth-only because their authorization is user/entity scoped inside the application layer:

- `logout`
- `notifications`
- `notifications.read_all`
- `notifications.read`
- `attachments.index`
- `attachments.store`
- `attachments.show`
- `attachments.destroy`

Attachment routes delegate authorization to `AttachmentService` / `AttachmentEntityAuthorizer` based on the referenced business entity.

## Verification

- `php artisan migrate --force`: passed, nothing to migrate.
- `php artisan migrate:status`: 65 migrations ran.
- `vendor/bin/pint --test`: passed.
- `php artisan test --testsuite=Unit`: passed, 5 tests / 15 assertions.
- `php artisan test --testsuite=Integration`: passed, 8 tests / 70 assertions.
- `php artisan test --testsuite=Invariants`: passed, 15 tests / 522 assertions.
- `php artisan test --testsuite=Concurrency`: passed, 7 tests / 16 assertions.
- Feature tests: every `tests/Feature/*Test.php` file passed in isolated execution.
- `SecurityHardeningTest`: passed, 6 tests / 331 assertions.
- `npm run typecheck`: passed.
- `npm run build`: passed, Vite chunk-size warning only.
- All configured stress and integrity commands passed:
  - `concurrency:stress --workers=100`
  - `accounting:concurrency-stress --workers=50`
  - `accounting:allocation-concurrency-stress --workers=50`
  - `accounting:bank-reconciliation-concurrency-stress --workers=50`
  - `accounting:cheque-concurrency-stress --workers=50`
  - `accounting:settlement-concurrency-stress --workers=50`
  - `accounting:inventory-concurrency-stress --workers=50`
  - `accounting:stock-transfer-stress --workers=50`
  - `accounting:fixed-asset-depreciation-stress --workers=50`
  - `accounting:fixed-asset-disposal-stress --workers=50`
  - `accounting:sales-tax-stress --workers=50`
  - `accounting:purchasing-tax-stress --workers=50`
  - `accounting:tax-filing-stress --workers=50`
  - `accounting:phase3-integrity-check`
  - `tokens:gc --batch=100`

## Notes

The first monolithic `php artisan test` run was interrupted after an abnormal no-output duration. The suite was then verified by running all PHPUnit suites and every Feature test file independently with per-file timeouts; all passed after updating tests to reflect the stricter authorization model.
