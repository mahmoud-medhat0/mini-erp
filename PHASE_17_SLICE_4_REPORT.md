# Phase 17 Slice 4 Report - Sensitive Financial Action Confirmation

Status: COMPLETE after local Codex review.
Date: 2026-08-29.

## Scope

Implemented centralized confirmation enforcement for high-impact financial and irreversible actions. This slice is defensive security only; it does not change accounting math, stock costing, tax math, payroll math, period-close rules, document numbering, idempotency, locks, or posting results.

## Files Changed

- `laravel/app/Http/Middleware/RequireSensitiveActionConfirmation.php`
- `laravel/app/Support/Security/SensitiveActionRegistry.php`
- `laravel/bootstrap/app.php`
- `laravel/routes/web.php`
- `laravel/resources/js/Components/SensitiveActionModal.tsx`
- Sensitive action callers in accounting, AR/AP, treasury, bank reconciliation, sales, purchasing, inventory, tax, payroll, rentals, budgeting, and fixed asset pages.
- `laravel/resources/js/locales/en.json`
- `laravel/resources/js/locales/ar.json`
- `laravel/tests/Feature/SecurityHardeningTest.php`
- `laravel/tests/Feature/Phase15ProductHardeningTest.php`
- Phase handoff/status documents.

## Backend Design

- Added `SensitiveActionRegistry` with 38 protected route names.
- Added `RequireSensitiveActionConfirmation` middleware and registered alias `sensitive.confirm`.
- Middleware validates:
  - `confirm_action` is required and must exactly match the configured confirmation code.
  - `reason` is required for reason-required routes, trimmed, minimum 3 characters, maximum 1000 characters.
  - optional reasons are normalized and capped at 1000 characters.
- Middleware stores normalized values on the request:
  - `sensitive_action_code`
  - `sensitive_action_reason`
- Controller/service execution is blocked when confirmation payload is missing or wrong.

## Protected Routes

The registry protects 38 existing sensitive routes:

- Accounting: `accounting.journal.post`, `accounting.journal.reverse`, `accounting.opening_balances.post`, `accounting.periods.close`, `accounting.periods.reopen`
- AR/AP, cash, bank: `customer-opening-balances.post`, `supplier-opening-balances.post`, `customer-receipts.post`, `supplier-payments.post`, `receivable-allocations.reverse`, `payable-allocations.reverse`, `treasury-transfers.post`, `bank-reconciliations.finalize`
- Sales, purchasing, inventory: `landed-costs.post`, `customer-invoices.post`, `supplier-bills.post`, `sales-returns.post`, `customer-credit-notes.post`, `purchase-returns.post`, `supplier-adjustment-notes.post`, `receivable-settlements.reverse`, `payable-settlements.reverse`, `stock-transfers.issue`, `stock-transfers.receive`, `stock-counts.post`, `stock-adjustments.post`
- Tax, payroll, rentals, budgeting: `taxes.returns.file`, `payroll.runs.post`, `rentals.invoices.post`, `budgeting.budgets.activate`, `budgeting.budgets.archive`, `budgeting.budgets.cancel`
- Fixed assets: `fixed-assets.capitalize`, `fixed-assets.reverse_capitalization`, `fixed-assets.depreciation-runs.store`, `fixed-assets.depreciation-runs.reverse`, `fixed-assets.disposals.store`, `fixed-assets-disposals.reverse`

## Reason-Required Actions

21 routes require a reason:

- `accounting.journal.reverse`
- `accounting.periods.close`
- `accounting.periods.reopen`
- `receivable-allocations.reverse`
- `payable-allocations.reverse`
- `bank-reconciliations.finalize`
- `receivable-settlements.reverse`
- `payable-settlements.reverse`
- `stock-counts.post`
- `stock-adjustments.post`
- `taxes.returns.file`
- `payroll.runs.post`
- `budgeting.budgets.activate`
- `budgeting.budgets.archive`
- `budgeting.budgets.cancel`
- `fixed-assets.capitalize`
- `fixed-assets.reverse_capitalization`
- `fixed-assets.depreciation-runs.store`
- `fixed-assets.depreciation-runs.reverse`
- `fixed-assets.disposals.store`
- `fixed-assets-disposals.reverse`

## Audit Evidence

Successful protected actions create a Spatie Activitylog event before the underlying action executes:

- Event: `sensitive_action.confirmed`
- Properties:
  - `sensitive_action_code`
  - `sensitive_action_confirmed`
  - `sensitive_action_reason`
  - `route_name`
  - `actor_id`
  - `request_id`
  - `ip`
  - `device`

Existing business audit events are preserved.

## UI Changes

- Added reusable `SensitiveActionModal`.
- Updated protected route callers to send explicit `confirm_action` payloads.
- Added reason capture where required.
- Preserved dictionary-backed EN/AR visible text.
- Local review fixed stale page/test expectations after Agy timeout, including allocation reversals, settlement reversals, purchase return posting, supplier adjustment posting, stock transfer issue/receive, payroll posting, fixed asset depreciation run posting/reversal, rental invoice posting, and bank reconciliation modal prop consistency.

## Tests Added Or Changed

- `SecurityHardeningTest` now covers registry completeness, middleware attachment, missing/wrong confirmation rejection, required reason validation, optional reason behavior, Spatie Activitylog evidence, no-scope scans, and UI unsafe-control scans.
- `Phase15ProductHardeningTest` source-scan expectations were updated so old empty `{}` payloads are no longer accepted for newly protected sensitive actions.

## Verification Results

Required verification commands from `PHASE_17_SLICE_4_AGY_PROMPT.md`:

- `vendor/bin/pint --test`: PASSED (`{"tool":"pint","result":"passed"}`; existing local Xdebug log warning only).
- `php artisan test --filter=SecurityHardeningTest --compact`: PASSED, 36 tests / 958 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --compact`: PASSED, 192 tests / 26114 assertions.
- `php artisan test --testsuite=Concurrency --compact`: PASSED, 7 tests / 16 assertions.
- `php artisan security:route-audit --strict`: PASSED, 457 routes scanned, 0 failing.
- `npm run typecheck`: PASSED, 0 TypeScript errors.
- `npm run build`: PASSED, 711 modules transformed, existing Vite chunk-size warning only.

Additional note: a full unfiltered `php artisan test --compact` attempt exceeded the local 10-minute command timeout twice. This was a timeout, not a reported PHPUnit failure. Required targeted security/product/concurrency verification passed.

## Scans

- No-scope scan on new Slice 4 security files: CLEAN for `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, and `Spatie Teams`.
- UI unsafe-control scan on changed sensitive-action TSX files: CLEAN for `dangerouslySetInnerHTML`, native `<select>`, `<option>`, `type="date"`, and `window.location.href`.
- Prop mismatch scan: CLEAN for `prompt={` on `SensitiveActionModal` usage.
- Empty confirmation scan: CLEAN for `confirm_action: ''`.

## Remaining Risks

- Full unfiltered PHPUnit runtime is currently longer than the local command timeout. Security Slice 4 required checks are green, but a later final close-out should either run the full suite with a longer external timeout or split it by suite/file group.
- Some lower-risk legacy browser `confirm()` calls remain outside the Slice 4 sensitive route list by design.
