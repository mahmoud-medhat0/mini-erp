# Phase 10 Branch-Specific GL Mapping Report

Status: COMPLETE & TARGET-VERIFIED  
Date: 2026-08-25  
Scope: Optional branch-specific accounting account mapping overrides with global fallback.

## What Changed

- Added forward migration `2026_08_25_040000_add_branch_overrides_to_accounting_account_mapping.php`.
- Added nullable operational `branch_id` to `accounting_account_mapping`.
- Replaced the single global `key` uniqueness model with:
  - one global mapping per key when `branch_id IS NULL`
  - one branch override per `(key, branch_id)` when `branch_id IS NOT NULL`
- Updated `AccountingAccountMappingService` so `getMapping()` and `getAccount()` resolve branch override first, then fall back to the global mapping.
- Added branch existence validation and a guarded delete path for branch overrides only; global mappings stay protected.
- Updated `AccountingCoreSeeder` so seed data updates only global mappings and never overwrites branch overrides.
- Updated Moving Weighted Average inventory postings to use branch-specific mappings when branch context is derived from the operational warehouse.
- Added `/accounting/account-mappings` Inertia page for global mappings and branch overrides.
- Added route-level authorization through `accounting.mappings` / `settings.configure`.
- Added EN/AR dictionary-backed UI text with no hardcoded visible labels in the new page.
- Updated Phase 3 integrity allowlists to classify `accounting_account_mapping.branch_id` as an owner-approved Phase 10 operational reference only.
- Hardened `qa:verify-local` so spawned test processes run with explicit testing environment variables.

## Scope Guardrails

- No `company_id` was introduced.
- No `tenant_id` was introduced.
- No `currentCompany` or `currentBranch` context was introduced.
- No Spatie Teams scope was introduced.
- Branch mappings are operational accounting configuration overrides only.
- Branch mappings are not tenant scope, security scope, login context, or document-numbering scope.

## Verification

- `php artisan migrate --force`: passed; migration applied.
- `php artisan migrate:status`: passed; new migration is Ran.
- `vendor/bin/pint --test`: passed.
- `php artisan test tests/Feature/Phase10BranchSpecificGlMappingTest.php --stop-on-failure`: 4 tests / 27 assertions passed.
- `php artisan qa:verify-local --only-feature-files --filter=Phase10 --stop-on-failure --timeout=300`: passed all 7 Phase 10 feature files in 75,738 ms.
- `php artisan route:list --path=accounting/account-mappings -v`: index/store/delete routes are protected by `web`, `auth`, and `permission.any:accounting.mappings,settings.configure`.
- `npm run typecheck`: passed.
- `npm run build`: passed with the existing Vite chunk-size warning only.

## Remaining Future Options

- Optional branch-aware approval rules.
- Landed cost and freight allocation.
- Optional branch-specific sales/purchasing invoice line mapping policy if the owner requires line-level branch overrides beyond warehouse-derived inventory postings.

These remain future product options and must not be implemented as tenant, company, login, or security assumptions.
