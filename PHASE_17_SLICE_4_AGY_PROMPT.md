# Mini ERP - Phase 17 Slice 4 Agy Prompt

Execute ONLY Phase 17 Slice 4: Sensitive Financial Action Confirmation and Audit Evidence Hardening.

You are operating in an existing Laravel 13 + Inertia + React Mini ERP. This is a defensive security pass only. Do not start or redesign any business module.

## Non-Negotiable System Rules

- No multi-tenant architecture.
- Do not add `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, company-user membership, or Spatie Teams.
- Branch is an operational/reporting dimension only where already implemented; do not make Branch a security scope or login context.
- Do not change posting math, stock costing math, tax math, payroll math, period-close rules, document numbering, idempotency, locking, or accounting results.
- Controllers must stay thin. Put reusable validation/security behavior in middleware, FormRequests, services, or support classes.
- Spatie Activitylog remains the active audit backend. Do not revive the legacy `audit_log` writer for new records.
- UI changes must use dictionary-backed EN/AR visible text only. No new hardcoded visible strings in `.tsx`.
- Do not use native `<select>`, `<option>`, `type="date"`, `dangerouslySetInnerHTML`, or unsafe `window.location.href`.
- Prefer an existing `Modal`, `Button`, `SearchableSelect`, `DatePicker`, and existing design primitives.

## Objective

High-impact irreversible or financially sensitive actions must require explicit backend validation of an intentional confirmation payload, and must preserve audit evidence of the confirmation and reason where appropriate.

This is not just a frontend confirm dialog. Direct POST/DELETE/PATCH calls to protected sensitive routes must fail when the required confirmation payload is absent or wrong.

## Required Backend Design

Implement a centralized sensitive action confirmation layer.

Recommended shape:

- `App\Support\Security\SensitiveActionRegistry`
  - maps protected route names to:
    - `confirmation_code`
    - `reason_required` boolean
    - human-readable internal description for docs/tests, not UI text
- `App\Http\Middleware\RequireSensitiveActionConfirmation`
  - registered as middleware alias, e.g. `sensitive.confirm`
  - resolves the current route name and looks it up in the registry
  - validates request payload:
    - `confirm_action`: required string and must exactly equal the route's configured `confirmation_code`
    - `reason`: required when `reason_required=true`; otherwise nullable
    - `reason`: string, trimmed, min 3 when required, max 1000
  - on failure:
    - returns validation errors for web/Inertia requests
    - never executes the underlying controller/service
  - on success:
    - stores safe normalized values on request attributes:
      - `sensitive_action_code`
      - `sensitive_action_reason`

You may choose equivalent names, but the behavior must remain centralized and testable.

## Required Sensitive Route Coverage

Apply confirmation middleware to these existing route names only if the route exists in `routes/web.php`:

### Accounting

- `accounting.journal.post`
- `accounting.journal.reverse` (reason required)
- `accounting.opening_balances.post`
- `accounting.periods.close` (reason required)
- `accounting.periods.reopen` (reason required)

### Phase 3 AR/AP, Cash, Bank

- `customer-opening-balances.post`
- `supplier-opening-balances.post`
- `customer-receipts.post`
- `supplier-payments.post`
- `receivable-allocations.reverse` (reason required)
- `payable-allocations.reverse` (reason required)
- `treasury-transfers.post`
- `bank-reconciliations.finalize` (reason required)

### Phase 4 Sales, Purchasing, Inventory

- `landed-costs.post`
- `customer-invoices.post`
- `supplier-bills.post`
- `sales-returns.post`
- `customer-credit-notes.post`
- `purchase-returns.post`
- `supplier-adjustment-notes.post`
- `receivable-settlements.reverse` (reason required)
- `payable-settlements.reverse` (reason required)
- `stock-transfers.issue`
- `stock-transfers.receive`
- `stock-counts.post` (reason required)
- `stock-adjustments.post` (reason required)

### Phase 5/7/13/14/16

- `taxes.returns.file` (reason required)
- `payroll.runs.post` (reason required)
- `rentals.invoices.post`
- `budgeting.budgets.activate` (reason required)
- `budgeting.budgets.archive` (reason required)
- `budgeting.budgets.cancel` (reason required)

### Phase 6 Fixed Assets

- `fixed-assets.capitalize` (reason required)
- `fixed-assets.reverse_capitalization` (reason required)
- `fixed-assets.depreciation-runs.store` (reason required)
- `fixed-assets.depreciation-runs.reverse` (reason required)
- `fixed-assets.disposals.store` (reason required)
- `fixed-assets-disposals.reverse` (reason required)

Do not apply this slice to ordinary create/edit/read routes. Do not invent new permissions.

## Audit Evidence Requirement

Every protected action above must leave Spatie Activitylog evidence containing, either in that action's existing audit event or a separate dedicated event:

- `sensitive_action_code`
- `sensitive_action_confirmed` = true
- `sensitive_action_reason` when supplied/required
- `route_name`
- actor/causer via current authenticated user

Do not remove existing audit data. Preserve current `before`/`after` payloads, entity IDs, request IDs, IP/device behavior, idempotency markers, and existing event names where already present.

If adding a separate audit event is simpler and safer, use one consistent event name such as `sensitive_action.confirmed` immediately before the protected controller action executes. It must not replace the business audit event.

## UI Requirement

Update only the TSX pages/components that call protected routes.

Acceptable implementation:

- Create one reusable confirmation modal component, e.g. `SensitiveActionModal`.
- It accepts:
  - action label/title/message from dictionaries
  - confirmation code
  - reason-required flag
  - onConfirm callback returning payload `{ confirm_action, reason }`
- Replace newly affected high-impact `window.confirm` / `confirm()` usages where practical with this modal.
- Existing lower-risk delete/create confirmations outside this slice may remain unchanged.
- Do not hardcode visible confirmation strings in TSX. Add EN/AR keys.
- Keep accounting UX direct: clear action, reason input when required, disabled submit while processing, explicit cancel.

If fully updating every existing page would be too large, prioritize pages for the route list above and create tests/scans proving no protected sensitive route is called with `{}` payload after this slice.

## Tests Required

Add/extend `SecurityHardeningTest` or a focused feature test.

Required assertions:

1. Each listed existing sensitive route has the confirmation middleware.
2. Missing `confirm_action` blocks at least:
   - journal post
   - period close
   - customer invoice post
   - supplier bill post
   - payroll run post
   - tax return file
   - stock adjustment post
   - budget activate
3. Wrong `confirm_action` blocks execution.
4. Required `reason` blocks execution when missing/blank/too short.
5. Correct confirmation allows the action to reach the controller/service for at least one safe fixture path.
6. Spatie Activitylog receives `sensitive_action.confirmed` or equivalent properties for a successful protected action.
7. Source scan test confirms no new `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, or Spatie Teams references in new Slice 4 files.
8. If TSX changed, source scan test confirms:
   - no hardcoded visible sensitive action strings in changed TSX
   - no `dangerouslySetInnerHTML`
   - no native `<select>` / `<option>` / `type="date"`
   - no protected sensitive route call using empty `{}` payload

## Verification Commands

Run from `laravel/` and report exact results:

```powershell
vendor/bin/pint --test
php artisan test --filter=SecurityHardeningTest --compact
php artisan test --filter=Phase15ProductHardeningTest --compact
php artisan test --testsuite=Concurrency --compact
php artisan security:route-audit --strict
npm run typecheck
npm run build
```

If no frontend files changed, explicitly say `npm run build` was still run because this slice intentionally touches cross-cutting UX or to preserve phase verification consistency.

## Final Report

Create `PHASE_17_SLICE_4_REPORT.md` with:

- exact files changed
- route names protected
- confirmation codes configured
- reason-required list
- audit properties captured
- tests added/changed
- verification results
- no-scope scan result
- UI hardcoded/native-control scan result if TSX changed
- remaining risks

Update:

- `PHASE_17_SECURITY_ACCESS_GOVERNANCE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

Stop after Phase 17 Slice 4. Do not start Slice 5.
