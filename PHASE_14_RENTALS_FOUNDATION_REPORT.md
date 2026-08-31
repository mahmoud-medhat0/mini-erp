# Phase 14 Rentals Foundation Report

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.

Status: COMPLETE & LOCALLY VERIFIED  
Date: 2026-08-25  
Scope: Rentals rentable item register foundation only.

## Implemented

- Added `rentable_item` table with global code, translated name/description, source model, optional product/fixed-asset link, optional operational branch/warehouse placement, status, condition, currency, rates, deposit, replacement value, active flag, optimistic `lock_version`, and actor metadata.
- Added `rentable_item_status_event` table for item status history.
- Added PostgreSQL constraints for source consistency, allowed statuses/conditions, non-negative money fields, and valid event types.
- Added `RentableItem` and `RentableItemStatusEvent` models and relationships.
- Added reciprocal relationships on `Product`, `FixedAsset`, `Warehouse`, and `Branch`.
- Added `RentableItemService` with validation, exact one-source linking rules, operational branch/warehouse consistency checks, optimistic locking, status history, delete protection for active workflow states, and Spatie Activitylog audit calls.
- Added `RentableItemController` and `/rentals/items` CRUD routes.
- Extended `rentals.*` RBAC action list with future workflow actions: `deliver`, `return`, `inspect`, `invoice`, and `configure`.
- Registered `rentable_item` in attachment authorization config without company or tenant scope.
- Added `Rentals/RentableItems.tsx` Inertia page with EN/AR dictionary-backed text, filters, create/edit form, status/condition badges, source selectors, operational branch/warehouse selectors, and integer minor-unit amount handling.
- Added Rentals navigation entry in the application layout.
- Added EN/AR dictionary entries for the Rentals page and navigation.

## Explicitly Not Implemented In This Slice

- Rental contracts.
- Delivery/return documents.
- Rental billing schedules.
- Customer invoices for rentals.
- Deposit posting/settlement.
- Revenue recognition.
- Damage/loss charge posting.
- Rental reports.

These must be implemented in later slices on top of this register.

## Guardrails Preserved

- No `company_id`.
- No `tenant_id`.
- No Spatie Teams.
- No current company/current branch context.
- No Employee-to-User relationship.
- Optional `branch_id` and `warehouse_id` on `rentable_item` are operational placement/reporting fields only.
- Attachment authorization for rentable items comes from `rentals.*` permissions and the referenced entity, not a company/tenant scope.

## Verification

Commands run:

```powershell
php artisan migrate --force
php artisan db:seed --class=RbacSeeder
php artisan test --filter=Phase14RentalsFoundationTest --stop-on-failure
vendor/bin/pint --test
npm run typecheck
npm run build
php artisan route:list --path=rentals
php artisan test --stop-on-failure
php artisan test --testsuite=Concurrency --stop-on-failure
php artisan accounting:phase3-integrity-check
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:stock-transfer-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan tokens:gc --batch=100
```

Results:

- Migration `2026_08_25_100000_create_phase14_rental_item_tables` applied successfully.
- `Phase14RentalsFoundationTest`: 7 tests / 46 assertions passed.
- Full Laravel suite: 634 tests, 631 passed, 3 skipped, 5352 assertions.
- Concurrency suite: 7 tests / 16 assertions passed.
- `accounting:phase3-integrity-check`: passed after classifying `rentable_item.branch_id` as an owner-approved operational reference.
- `concurrency:stress --workers=100`: passed.
- `accounting:concurrency-stress --workers=50`: passed.
- `accounting:stock-transfer-stress --workers=50`: passed.
- `accounting:inventory-concurrency-stress --workers=50`: passed when run standalone. An earlier parallel run alongside stock-transfer stress failed due to shared stress fixture/mapping interference, so these stress commands should be run sequentially.
- `tokens:gc --batch=100`: deleted sessions=0 password_reset_tokens=0 idempotency_keys=0.
- Pint passed.
- TypeScript typecheck passed with 0 errors.
- Vite build passed with the existing chunk-size warning only.
- Rental route list shows 4 routes: index, store, update, destroy.

Current local record counts after verification:

```json
{
  "rentable_item": 0,
  "rentable_item_status_event": 0,
  "activity_log": 3135,
  "users": 2,
  "journal_entry": 507,
  "ledger_entry": 1014,
  "jobs": 0,
  "failed_jobs": 0
}
```

## Follow-Up Status

Phase 14 rental contract lifecycle is now implemented and verified in `PHASE_14_RENTAL_CONTRACTS_REPORT.md`. Rental handover, return, and inspection workflow is also implemented and verified in `PHASE_14_RENTAL_FULFILLMENT_REPORT.md`.

## Next Slice

Phase 14 should continue with rental billing, deposits, charges, and reports:

- refundable deposit handling.
- rental invoice creation from contracts/periods.
- revenue and VAT posting through the existing PostingEngine/tax services.
- damage/loss/late-fee charge documents.
- AR subledger impact and settlement readiness.
- no new scope assumptions.
