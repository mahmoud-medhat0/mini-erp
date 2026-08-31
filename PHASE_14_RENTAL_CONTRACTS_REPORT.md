# Phase 14 Rental Contracts Report

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.

Status: COMPLETE & LOCALLY VERIFIED  
Date: 2026-08-25  
Scope: Rental contract lifecycle, item reservation/allocation, and UI actions only. No rental billing, deposits posting, revenue recognition, delivery/return, inspection, or GL posting was added in this slice.

## Implemented

- Added `rental_contract`, `rental_contract_line`, and `rental_contract_status_event` tables.
- Added `RentalContract`, `RentalContractLine`, and `RentalContractStatusEvent` models and relationships.
- Added customer and optional operational branch references on rental contracts.
- Added rental contract line references to rentable items.
- Added `RentalContractService` with exact integer totals, duplicate-item protection, item availability checks, date-window validation, optimistic locking, Spatie Activitylog audit, and status events.
- Added contract lifecycle actions:
  - `draft -> submitted`: allocates `RENT-YYYY-XXXXX` and reserves items.
  - `submitted -> approved`: allocates items.
  - `approved -> active`: marks items rented.
  - `draft/submitted/approved -> cancelled`: releases reserved/allocated items.
- Added `RentalContractController` and `/rentals/contracts` routes.
- Added `Rentals/Contracts.tsx` Inertia page with filters, create/edit form, multi-line contract items, lifecycle actions, permission-aware buttons, and EN/AR dictionary-backed visible text.
- Added Rentals navigation entry for contracts.
- Registered `rental_contract` in attachment authorization config.
- Extended Phase 3 integrity allowlist for `rental_contract.branch_id` as an owner-approved operational/reporting reference only.
- Extended Phase 14 feature tests to cover rental contract schema, relations, lifecycle, permissions, and UI props.

## Guardrails Preserved

- No `company_id`.
- No `tenant_id`.
- No Spatie Teams.
- No current company/current branch context.
- No Company-to-User, Branch-to-User, or Employee-to-User assumption.
- `rental_contract.branch_id` is optional operational/reporting context only; it is not tenancy, ownership, login context, or authorization scope.
- No GL posting was added for contracts. Rental billing, deposits, revenue, VAT, damage charges, and settlement remain later bounded slices.
- No float arithmetic for rental amounts.
- Controllers remain thin and delegate business behavior to `RentalContractService`.

## Verification

Commands run:

```powershell
php artisan migrate --force
php artisan migrate:status
node -e "JSON.parse(...en.json); JSON.parse(...ar.json)"
php artisan test --filter=Phase14RentalsFoundationTest --stop-on-failure
php artisan route:list --path=rentals
php artisan accounting:phase3-integrity-check
vendor/bin/pint
vendor/bin/pint --test
npm run typecheck
npm run build
php artisan test --stop-on-failure
php artisan test --testsuite=Concurrency --stop-on-failure
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:stock-transfer-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan tokens:gc --batch=100
```

Results:

- `php artisan migrate --force`: Nothing to migrate after the contract migration was already applied.
- `php artisan migrate:status`: 80 migrations Ran, including:
  - `2026_08_25_100000_create_phase14_rental_item_tables`
  - `2026_08_25_101000_create_phase14_rental_contract_tables`
- Locale JSON parse passed for `en.json` and `ar.json`.
- `Phase14RentalsFoundationTest`: 12 tests / 97 assertions passed.
- Full Laravel suite: 639 tests, 636 passed, 3 skipped, 5425 assertions.
- Concurrency suite: 7 tests / 16 assertions passed.
- `accounting:phase3-integrity-check`: passed.
- `concurrency:stress --workers=100`: passed; number sequence values were unique and contiguous, and idempotency executed once.
- `accounting:concurrency-stress --workers=50`: passed.
- `accounting:stock-transfer-stress --workers=50`: passed.
- `accounting:inventory-concurrency-stress --workers=50`: passed.
- `tokens:gc --batch=100`: deleted sessions=0 password_reset_tokens=0 idempotency_keys=0.
- Pint passed.
- TypeScript typecheck passed with 0 errors.
- Vite build passed with the existing chunk-size warning only.
- `route:list --path=rentals`: 11 rental routes registered for items and contracts.
- Prohibited scope scan over Phase 14 rental code returned zero matches for `company_id`, `tenant_id`, `currentCompany`, `currentBranch`, `Spatie Teams`, and `company_user`.

Current local record counts after verification:

```json
{
  "rentable_item": 0,
  "rentable_item_status_event": 0,
  "rental_contract": 0,
  "rental_contract_line": 0,
  "rental_contract_status_event": 0,
  "activity_log": 3640,
  "journal_entry": 608,
  "ledger_entry": 1216,
  "jobs": 0,
  "failed_jobs": 0
}
```

## Next Slice

Phase 14 rental handover, return, and inspection workflow is now implemented and verified in `PHASE_14_RENTAL_FULFILLMENT_REPORT.md`.

Phase 14 should continue with billing, deposits, charges, and reports:

- refundable deposit handling.
- rental invoice creation from contracts/periods.
- revenue and VAT posting through the existing PostingEngine/tax services.
- damage/loss/late-fee charge documents.
- AR subledger impact and settlement readiness.
- no new scope assumptions.
