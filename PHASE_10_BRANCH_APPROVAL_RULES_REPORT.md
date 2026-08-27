# PHASE 10 - BRANCH-AWARE APPROVAL RULES REPORT

**Status:** COMPLETE & TARGET-VERIFIED  
**Date:** 2026-08-25  
**Scope:** Optional branch-aware approval rules for selected inventory approval workflows.

## What Changed

- Added `branch_approval_rule` table via `2026_08_25_050000_create_branch_approval_rules.php`.
- Added `App\Models\BranchApprovalRule`.
- Added `App\Application\Approvals\BranchApprovalRuleService` for rule CRUD, validation, audit logging, and approval enforcement.
- Added `Settings\BranchApprovalRuleController` and `/settings/branch-approval-rules` CRUD routes.
- Added `Settings/BranchApprovalRules.tsx` with dictionary-backed EN/AR UI text.
- Added Settings navigation entry and settings hub card.
- Added RBAC permissions: `approvals.view`, `approvals.configure`, and `approvals.override`.
- Ran `php artisan db:seed --class=RbacSeeder` locally so the new permissions exist in the active database.
- Updated inventory approval services:
  - `stock_transfer` approval can require an extra permission based on source, destination, either, or global branch rule.
  - `stock_count` approval can require an extra permission based on the document warehouse branch.
  - `stock_adjustment` approval can require an extra permission based on the document warehouse branch.
- Updated the Phase 3 integrity guard and its regression test so `branch_approval_rule.branch_id` is classified as an owner-approved operational reference, not a tenant/security scope.

## Guardrails Preserved

- No multi-tenant architecture was added.
- No `company_id`, `tenant_id`, `currentCompany`, `currentBranch`, or Spatie Teams scope was added.
- Branch rules do not create branch data-access ownership.
- Branch rules do not make Branch a tenant or a login context.
- No branch-scoped numbering was added.
- Rule enforcement is default-off: if no active rule matches, existing approval behavior remains unchanged.
- Approval rules add an extra permission gate only for matching documents and branches.
- All mutation paths use service-layer logic and Spatie Activitylog through the existing audit adapter.

## Covered Workflows

| Workflow | Branch Context Used | Result |
|---|---|---|
| Stock Transfer | source branch, destination branch, either branch, or global rule | matching rules can require `approvals.override` or another configured permission |
| Stock Count | warehouse branch | matching rules can require an extra permission before approval |
| Stock Adjustment | warehouse branch | matching rules can require an extra permission before approval |

## Intentionally Not Implemented

- Branch access matrices for general page/data visibility.
- Branch-owned users, roles, or permissions.
- Branch-specific sales/purchasing invoice approval rules; those need a separate owner-approved line-level policy because documents can involve multiple warehouse/branch lines.
- Branch-scoped document numbering.
- Landed cost and freight allocation.

## Verification Results

```powershell
php artisan migrate:status
php artisan db:seed --class=RbacSeeder
vendor/bin/pint --test
php artisan qa:verify-local --timeout=300
php artisan test tests/Feature/Phase10BranchApprovalRulesTest.php --stop-on-failure
php artisan qa:verify-local --only-feature-files --filter=Phase10 --stop-on-failure --timeout=300
php artisan test --filter=Phase3Slice9StressIntegrityTest --stop-on-failure
php artisan test --filter=SecurityHardeningTest --stop-on-failure
php artisan accounting:phase3-integrity-check
php artisan concurrency:stress --workers=100
php artisan accounting:stock-transfer-stress --workers=50
php artisan accounting:concurrency-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Result summary:

- `migrate:status`: `2026_08_25_050000_create_branch_approval_rules` is Ran.
- `RbacSeeder`: completed, registering the new `approvals.*` permissions.
- `qa:verify-local --timeout=300`: Unit 5/15, Integration 8/70, Invariants 15/522, and Concurrency 7/16 passed.
- `Phase10BranchApprovalRulesTest`: 5 tests / 30 assertions passed.
- Phase 10 feature gate: 8 feature files passed, 37 tests / 385 assertions.
- `Phase3Slice9StressIntegrityTest`: 6 tests / 527 assertions passed.
- `SecurityHardeningTest`: 6 tests / 365 assertions passed.
- `accounting:phase3-integrity-check`: passed.
- `concurrency:stress --workers=100`: passed.
- `accounting:stock-transfer-stress --workers=50`: passed.
- `accounting:concurrency-stress --workers=50`: passed.
- `tokens:gc --batch=100`: passed.
- Pint passed.
- TypeScript typecheck passed.
- Vite build passed, with only the existing chunk-size warning.

## Next Product Work

The remaining Phase 10 product extension is landed cost and freight allocation. Deployment remains parked until the owner/operator is ready for staging and production cutover.
