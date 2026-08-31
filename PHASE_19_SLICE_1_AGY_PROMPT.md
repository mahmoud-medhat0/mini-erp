# Mini ERP - Phase 19 Slice 1 Agy Prompt

Execute ONLY Phase 19 Slice 1: Accountant Acceptance Data Pack and Idempotent Seeder.

Stop after this slice. Do not start Slice 2.

## Scope

Create a realistic local/testing accountant acceptance fixture that can be used by later Phase 19 tests and by a human accountant reviewing `PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md`.

This is not deployment work and not a new ERP module.

## Non-Negotiable Rules

- No multi-tenant architecture and no company/tenant/security scope changes.
- Do not add `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, company-user membership, tenant context, or Spatie Teams.
- Branch may be used only as an operational/reporting dimension if existing branch/warehouse workflows already support it.
- Do not alter core math or posting rules.
- Do not write Telegram credentials, chat IDs, API keys, passwords, or production secrets to files.
- Use existing services and seeders wherever possible. Do not duplicate business posting logic in the seeder.
- Acceptance data must be idempotent.

## Required Review Before Editing

Inspect the current implementation before writing code:

- `PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md`
- `laravel/database/seeders/AccountingDemoSeeder.php`
- `laravel/database/seeders/*Seeder.php`
- `laravel/app/Application/**/**/*Service.php`
- feature tests for Phase 3, Phase 4, Phase 7, Phase 10, Phase 11, Phase 12, Phase 13, Phase 14, and Phase 16.

## Required Implementation

Create a new explicit seeder, preferably:

- `laravel/database/seeders/AccountantAcceptanceSeeder.php`

The seeder must be manually runnable:

```powershell
php artisan db:seed --class=AccountantAcceptanceSeeder
```

It must seed or ensure the minimum data needed for a full acceptance review:

1. One active acceptance user or use first existing user safely without changing passwords.
2. Required currencies, account categories, account types, chart of accounts, and GL mappings.
3. One open fiscal year and at least one open financial period.
4. At least two operational branches if the existing branch model supports this, explicitly as operational/reporting records, not tenants.
5. At least two warehouses/locations if the existing inventory model supports this.
6. One customer and one supplier.
7. One stock product and one service/non-stock product where supported.
8. Active VAT/tax code and rate where current tax services support it.
9. Cash account and bank account linked to valid GL accounts.
10. Any required project/cost-center/budget fixture only if current services support it without forcing a new relationship.

The seeder must create stable codes prefixed with `ACC-` or `ACCEPT-` so it can find/update its own data safely.

## Idempotency Requirements

Running the seeder twice must leave stable counts for its own acceptance documents/master data. Do not duplicate:

- users
- branches
- warehouses
- customers/suppliers
- products
- cash/bank accounts
- chart accounts or mappings
- fiscal periods

If the seeder creates posted business documents in this slice, those documents must also be idempotent. It is acceptable for Slice 1 to prepare master data only and leave transactional posting to Slice 2.

## Tests Required

Add or extend a focused test, preferably `Phase19AccountantAcceptanceTest`, proving:

1. `AccountantAcceptanceSeeder` exists and runs successfully.
2. Running it twice is idempotent for acceptance-prefixed records.
3. Required fixture records exist and are active.
4. The fixture contains branch-capable operational data where implemented, without tenant/company scope.
5. No acceptance fixture stores secrets.
6. No forbidden scope columns/terms were introduced by this slice.

## Documentation

Create `PHASE_19_SLICE_1_REPORT.md` with:

- exact files changed
- fixture data summary
- idempotency proof
- no-scope scan result
- secret scan result
- test results
- remaining risks or deferred items for Slice 2

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
php artisan db:seed --class=AccountantAcceptanceSeeder
php artisan db:seed --class=AccountantAcceptanceSeeder
php artisan security:route-audit --strict
npm run typecheck
```

Run `npm run build` only if frontend files changed.

## Final Rule

Stop after Phase 19 Slice 1 and create the report. Do not start Slice 2.
