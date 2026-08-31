# MINI ERP - PHASE 3 SLICE 7 GEMINI PROMPT

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


You are continuing the existing Mini ERP Laravel + Inertia + React migration.

Implement **Phase 3 Slice 7 only**:

```text
Inertia Pages and UX Actions for the already-built Phase 3 workflows
```

This is a UI/action integration slice. It must make the existing Phase 3 backend workflows usable from the Laravel Inertia app with a polished ERP interface.

Do **not** redesign the architecture. Do **not** start the next module. Do **not** implement reports.

## Source Of Truth

Before changing code, read:

- `README.md`
- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`
- `PHASE_3_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_3_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_4_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_5_GEMINI_PROMPT.md`
- `PHASE_3_SLICE_6_GEMINI_PROMPT.md`

Then inspect the current Laravel implementation under:

- `laravel/app`
- `laravel/config`
- `laravel/database`
- `laravel/routes`
- `laravel/resources/js`
- `laravel/tests`

Use existing services, models, policies, permissions, validation style, Inertia page conventions, and UI components. Do not invent a parallel frontend or a second business layer.

## Current Baseline

The following are already implemented and verified:

- Phase 2 accounting core.
- Spatie Permission RBAC with teams disabled.
- Spatie Activitylog as the active audit backend through `AuditLogger`.
- M9 attachments and notifications foundations.
- Phase 3 Slice 1 Customer, Supplier, CashAccount, and BankAccount foundation.
- Phase 3 Slice 2 AR/AP subledgers and customer/supplier opening balances.
- Phase 3 Slice 3 Customer Receipt and Supplier Payment posting.
- Phase 3 Slice 4 Receivable/Payable allocation engine.
- Phase 3 Slice 5 Incoming/Outgoing cheque lifecycle.
- Phase 3 Slice 6 Bank reconciliation foundation and CashBook/BankBook query services.

Slice 7 must expose these existing capabilities through Inertia pages and server actions.

## Owner Decisions That Must Remain True

- The ERP is **not** multi-tenant.
- Do not add `company_id`, `branch_id`, `tenant_id`, `currentCompany`, `currentBranch`, tenant middleware, or Spatie Teams.
- Company/Branch/User ownership relationships remain `UNDEFINED - DO NOT ASSUME` unless explicitly decided later by the owner.
- Branch is not a tenant/security boundary.
- Phase 3 audit must use the owner-approved Spatie Activitylog path through the existing `AuditLogger` API.
- Attachments must authorize through the registered entity and server-side rules, not through invented company scope.
- Money must remain integer minor units; no float math.
- Posting must continue to use the existing PostingEngine and idempotency services.
- Ledger entries, posted journals, finalized reconciliations, and audit/activity records must remain immutable.

## Slice 7 Objective

Build polished Inertia pages and actions for:

1. Customer master data.
2. Supplier master data.
3. Cash account master data.
4. Bank account master data.
5. Customer opening balances.
6. Supplier opening balances.
7. Customer receipts.
8. Supplier payments.
9. Receivable allocation UX.
10. Payable allocation UX.
11. Incoming cheque register and valid lifecycle actions.
12. Outgoing cheque register and valid lifecycle actions.
13. Bank reconciliation operational page.
14. Navigation entries, empty states, validation feedback, permissions, and bilingual UX.

## Strict Non-Goals

Do not implement:

- Slice 8 reports.
- Customer statements.
- Supplier statements.
- Aging reports.
- Cash Book report page.
- Bank Book report page.
- Cheque register report page.
- Bank reconciliation report/status report page.
- AR/AP to GL reconciliation reports.
- Sales module.
- Purchasing module.
- Inventory module.
- Payroll, rentals, fixed assets, taxes, projects, budgeting, or later ERP modules.
- Bank statement import.
- Bank feed/OCR parsing.
- Automatic bank adjustment posting.
- New accounting posting engines.
- New audit systems.
- New attachment authorization model.
- Post-clear cheque bounce/return semantics beyond the owner-approved boundary.
- Broad dashboard expansion.

Using ledger candidates and summary numbers inside the bank reconciliation working page is allowed. Building standalone report pages is not allowed in this slice.

## UI/UX Direction

Build a practical ERP interface: quiet, dense, readable, and fast for repeated work.

Use the existing frontend foundation:

- `resources/js/Components/AppLayout.tsx`
- `resources/js/Components/Primitives.tsx`
- `resources/js/Components/SearchableSelect.tsx`
- `resources/js/Components/DatePicker.tsx` if present and appropriate
- `resources/js/Components/AttachmentPanel.tsx`
- existing locale files under `resources/js/locales`
- existing accounting helpers under `resources/js/lib`

UI rules:

- No landing pages.
- No hero sections.
- No decorative gradients, orbs, blobs, or marketing layouts.
- No nested cards.
- Use compact tables, filters, summary strips, tabs, side panels, modals, and drawers where appropriate.
- Use existing tokens, colors, spacing, typography, and dark/light theme behavior.
- Keep page sections full-width/unframed where possible; reserve cards for repeated records, dialogs, and genuinely framed tools.
- Use `lucide-react` icons inside buttons where helpful.
- Use icon-only buttons for familiar tools and icon+text buttons for clear business actions.
- Add tooltips or accessible labels for ambiguous icon buttons.
- Use stable dimensions for action buttons, status badges, counters, and table rows so content does not shift.
- Ensure all text fits on desktop and mobile.
- Do not scale font size with viewport width.
- Preserve RTL/LTR behavior for Arabic and English.
- Add all new labels/messages to both English and Arabic locale files.
- Empty states should describe the real operational state, not marketing copy.
- Display server validation errors inline and clearly.
- Display optimistic locking/status conflict errors clearly.
- Disable or hide impossible actions based on row status and permissions, but keep the server as the final authority.

## Expected Page/Action Coverage

### Customer And Supplier Pages

Create or complete Inertia pages for Customer and Supplier management using existing backend services.

Required UX:

- searchable/filterable list
- active/inactive status filters
- create/edit form
- optimistic lock handling via `lock_version` if supported by the service
- status badges
- account/currency references shown with useful labels
- compact detail summary where useful
- attachment panel if the entity is registered for attachments
- permission-aware actions

Do not infer Company, Branch, or Tenant ownership.

### Cash And Bank Account Pages

Create or complete pages for CashAccount and BankAccount management.

Required UX:

- searchable/filterable list
- active/inactive filters
- linked GL account shown clearly
- currency shown through the existing `currency` relation/data
- create/edit form with searchable selects
- status badges
- attachment panel if registered
- permission-aware actions

Do not build Cash Book or Bank Book standalone report pages in this slice.

### Customer Opening Balances

Create or complete a UI for customer opening balances.

Required UX:

- select customer, receivable/control mapping if required by existing service, currency, amount, date/period, reference
- create/post through existing services only
- list existing opening balances
- show linked journal/receivable entry when available
- inline validation for period/date/currency/accounting mapping errors

### Supplier Opening Balances

Create or complete a UI for supplier opening balances.

Required UX mirrors customer opening balances:

- select supplier, payable/control mapping if required by existing service, currency, amount, date/period, reference
- create/post through existing services only
- list existing opening balances
- show linked journal/payable entry when available
- inline validation for period/date/currency/accounting mapping errors

### Customer Receipts

Create or complete Customer Receipt pages/actions.

Required UX:

- draft receipt form
- customer selection
- cash/bank destination selection
- date/period/currency/amount/reference fields
- amount entered as human decimal text but submitted to backend as integer minor units using existing helpers/patterns
- post action with confirmation
- status badges
- linked journal/subledger information
- unapplied/allocated balance display
- attachment panel where supported

### Supplier Payments

Create or complete Supplier Payment pages/actions.

Required UX mirrors customer receipts:

- draft payment form
- supplier selection
- cash/bank source selection
- date/period/currency/amount/reference fields
- integer minor unit submission
- post action with confirmation
- status badges
- linked journal/subledger information
- unapplied/allocated balance display
- attachment panel where supported

### Allocation UX

Create or complete allocation screens for:

- customer receipts -> receivable entries
- supplier payments -> payable entries

Required UX:

- pick a posted receipt/payment with unapplied balance
- list open receivable/payable entries for the same party and currency
- allow entering allocation amounts
- show running total, remaining unapplied amount, and target remaining balance
- prevent obvious over-allocation client-side for UX only
- rely on server validation and row locking for correctness
- show idempotency/duplicate-submit safe feedback
- support reversal only if an existing service/action already supports reversal

Do not create new AR/AP adjustment logic.

### Incoming Cheque Register

Create or complete an Incoming Cheque register and valid actions.

Required UX:

- filters by status, customer, bank/cash account, due date range
- register table with cheque number, customer, amount, currency, due date, current status, linked receipt/journal
- valid actions only for the current status:
  - receive
  - deposit
  - clear
  - bounce
  - return
- confirmations for irreversible/accounting-impacting actions
- clear status/error copy when an action is blocked by the service
- attachment panel if the entity is registered

Respect the owner-approved pre-clear lifecycle boundary. Do not add post-clear bounce/return behavior.

### Outgoing Cheque Register

Create or complete an Outgoing Cheque register and valid actions.

Required UX:

- filters by status, supplier, bank account, due date range
- register table with cheque number, supplier, amount, currency, due date, current status, linked payment/journal
- valid actions only for the current status:
  - issue
  - clear
  - return
  - cancel
- confirmations for irreversible/accounting-impacting actions
- clear status/error copy when an action is blocked by the service
- attachment panel if the entity is registered

Do not generate physical cheque numbers unless the existing service explicitly does so. Physical cheque number should remain user-entered if that is the current model.

### Bank Reconciliation Page

Create or complete a working bank reconciliation UI.

Required UX:

- list existing reconciliation drafts/in-progress/reconciled records
- create draft for a bank account and period/date range using existing service
- detail page/workspace for one reconciliation
- manual statement line entry/edit/delete where allowed by status
- candidate posted ledger entries from existing service/query
- match/unmatch statement line to ledger entry
- summary panel:
  - statement opening
  - statement movement
  - statement closing
  - system movement
  - matched movement
  - difference
  - unmatched counts
- finalize action with confirmation
- finalize button disabled when obvious requirements are unmet, while server remains authoritative
- read-only finalized state
- attachment panel if registered

Do not add bank statement import, OCR, automatic matching heuristics, or automatic adjustment posting.

## Backend Integration Rules

- Prefer existing controllers/actions if present; otherwise add narrowly scoped controllers/actions for these pages.
- Controllers must call existing application services for business behavior.
- Do not duplicate posting, allocation, cheque, or reconciliation logic inside controllers or React.
- Use Laravel validation for every write.
- Use existing policies/permissions/RBAC config.
- Use eager loading and pagination for list pages.
- Shape Inertia props intentionally; do not leak raw model internals when a resource array is clearer.
- Preserve idempotency behavior on write actions where existing services support it.
- Preserve Spatie Activitylog audit through existing application services.
- Preserve attachment authorization through the existing attachment service and entity registry.

## Permissions

Respect the existing permission names and module/action structure in `config/erp_rbac.php`.

If a permission required by an already-implemented backend workflow is missing, add it through the existing RBAC config/seeder pattern only. Do not enable Spatie Teams and do not add company-scoped permissions.

## Testing Requirements

Add focused automated tests for this slice.

Minimum expected coverage:

- Page render tests for each new Inertia page.
- Permission denial tests for protected pages/actions.
- Create/update validation tests for master pages.
- Action tests for receipt/payment posting through the UI route layer.
- Allocation action tests through the UI route layer.
- Cheque action tests through the UI route layer for valid and invalid state transitions.
- Bank reconciliation action tests through the UI route layer for create/match/unmatch/finalize.
- Inertia props contain required option lists and summarized fields.
- RTL/locale keys exist for new UI strings.
- No `company_id`, `branch_id`, `tenant_id`, `currentCompany`, or `currentBranch` is introduced.

If the repo already has browser or screenshot smoke tooling, add a small smoke pass for the most important pages. If it does not, do not introduce a heavy new browser stack just for this slice; rely on Inertia feature tests, `npm run typecheck`, and `npm run build`.

## Verification Commands

Run from `laravel/` and report exact results:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

If any command cannot run, explain exactly why and what was already verified.

## Documentation Updates

After implementation, update:

- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`
- `CHANGELOG.md`
- `MD_DOCUMENTATION_AUDIT.md` if classifications change

The docs must say Phase 3 Slice 7 is complete only if the code and verification commands pass.

## Required Final Report

Return a concise final report with:

1. Files changed.
2. Routes/pages added.
3. Actions implemented.
4. UI/UX improvements made.
5. Permission and validation coverage.
6. Tests added.
7. Confirmation that no company/branch/tenant scope was introduced.
8. Confirmation that Slice 8 reports were not implemented.
9. Verification command results.
10. Remaining risks, if any.

End with explicit confirmations:

```text
Slice implemented: Phase 3 Slice 7 only.
Reports implemented: NO.
New tenant/company/branch scope introduced: NO.
Bank import/auto adjustment posting implemented: NO.
Sales/Purchasing/Inventory implemented: NO.
```
