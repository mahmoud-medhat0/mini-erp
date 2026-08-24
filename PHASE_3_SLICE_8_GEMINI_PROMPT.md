# MINI ERP - PHASE 3 SLICE 8 GEMINI PROMPT

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


You are continuing the existing Mini ERP Laravel + Inertia + React migration.

Implement **Phase 3 Slice 8 only**:

```text
Phase 3 Operational Reports and Subledger Reports
```

This is a read/reporting slice over the Phase 3 workflows that already exist.

Do **not** redesign architecture. Do **not** start Sales, Purchasing, Inventory, or full financial statements. Do **not** create accounting effects. Do **not** mutate posted data.

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
- `PHASE_3_SLICE_7_GEMINI_PROMPT.md`

Then inspect the current Laravel implementation under:

- `laravel/app`
- `laravel/config`
- `laravel/database`
- `laravel/routes`
- `laravel/resources/js`
- `laravel/tests`

Use current models, services, controllers, permissions, Inertia conventions, UI components, and locale files. Do not invent a parallel reporting framework.

## Current Baseline

The following are already implemented and verified:

- Phase 2 accounting core:
  - immutable posted journals and ledger entries
  - PostingEngine
  - account categories/types
  - chart of accounts
  - fiscal periods
  - General Journal, General Ledger, Trial Balance
- Spatie Permission RBAC with teams disabled.
- Spatie Activitylog through the existing `AuditLogger` API.
- Phase 3 Slice 1 master data:
  - Customer
  - Supplier
  - CashAccount
  - BankAccount
- Phase 3 Slice 2 AR/AP subledgers and opening balances:
  - `receivable_entry`
  - `payable_entry`
  - accounting mappings for AR control, AP control, opening balance offset
- Phase 3 Slice 3 receipts and payments:
  - `customer_receipt`
  - `supplier_payment`
  - unapplied balances
- Phase 3 Slice 4 allocations:
  - `receivable_allocation`
  - `payable_allocation`
- Phase 3 Slice 5 cheque lifecycle:
  - `incoming_cheque`
  - `outgoing_cheque`
- Phase 3 Slice 6 bank reconciliation:
  - `bank_reconciliation`
  - `bank_reconciliation_line`
  - CashBook and BankBook query service foundations
- Phase 3 Slice 7 Inertia pages/actions:
  - operational pages for the Phase 3 workflows above
  - navigation groups
  - English/Arabic translations
  - RTL-aware DatePicker

Slice 8 must add standalone report pages and report query services over these existing durable records.

## Owner Decisions That Must Remain True

- The ERP is **not** multi-tenant.
- Do not add `company_id`, `branch_id`, `tenant_id`, `currentCompany`, `currentBranch`, tenant middleware, or Spatie Teams.
- Company/Branch/User ownership relationships remain `UNDEFINED - DO NOT ASSUME`.
- Branch is not a tenant/security boundary.
- Money remains integer minor units; no float math.
- Reports must not mutate accounting data.
- Posted journal and ledger data remain immutable.
- Finalized bank reconciliations remain immutable.
- Audit/activity tables remain append-only.
- Corrections are by existing reversal behavior only; Slice 8 must not add correction workflows.

## Slice 8 Objective

Build read-only report services, controllers, routes, and Inertia pages for:

1. Customer Statement.
2. Supplier Statement.
3. AR Aging.
4. AP Aging.
5. Cash Book.
6. Bank Book.
7. Cheque Register.
8. Bank Reconciliation Report/Status.
9. AR to GL Reconciliation.
10. AP to GL Reconciliation.

These reports must derive only from Phase 2/Phase 3 tables already implemented.

## Strict Non-Goals

Do not implement:

- Sales invoices.
- Purchase invoices.
- Sales reports.
- Purchase reports.
- Inventory reports.
- VAT reports.
- Payroll reports.
- Full financial statements:
  - Balance Sheet
  - Income Statement
  - Cash Flow Statement
  - Equity Statement
- Bank statement import.
- Bank feed/OCR parsing.
- Automatic bank adjustment posting.
- Automatic reconciliation heuristics.
- New posting engines.
- New AR/AP adjustment workflows.
- Post-clear cheque bounce/return semantics.
- Dashboard expansion beyond small navigation links.
- Any company/branch/tenant scoping.

Do not fabricate invoice aging. Sales/Purchasing invoices do not exist yet, so AR/AP aging must use only actual Phase 3 receivable/payable source entries available in the database.

## Reporting Data Rules

Reports must be deterministic and explainable.

- Use posted/approved durable data only where the report is accounting-facing.
- Never include draft receipts/payments/cheques/reconciliations in accounting balances unless a report explicitly labels them as draft/status information.
- Use integer minor units end-to-end.
- Format money only at the presentation edge.
- Prefer query services/application services over raw controller queries.
- Avoid N+1 queries.
- Use pagination for long tabular reports.
- Use explicit filters and stable ordering.
- Add drill-through links to existing detail/workflow pages where available.
- If a source date/due date field is not present in the current schema, do not invent one silently. Use the actual available date field and label the report basis clearly.
- Do not add speculative schema columns just to make a report look more complete.

## Report Requirements

### Customer Statement

Build a customer statement report over actual AR/subledger activity.

Required filters:

- customer
- date from
- date to
- currency where applicable
- include zero-balance toggle if useful

Required output:

- opening balance before date range
- movement lines inside date range
- debit/credit or increase/decrease columns using clear AR terminology
- running balance
- closing balance
- source document reference and drill-through link where available
- totals summary

Use receivable entries, customer receipts, receivable allocations, and opening balance records that actually exist. Do not fabricate sales invoice rows.

### Supplier Statement

Build a supplier statement report over actual AP/subledger activity.

Required filters mirror Customer Statement:

- supplier
- date from
- date to
- currency where applicable
- include zero-balance toggle if useful

Required output:

- opening balance before date range
- movement lines inside date range
- debit/credit or increase/decrease columns using clear AP terminology
- running balance
- closing balance
- source document reference and drill-through link where available
- totals summary

Use payable entries, supplier payments, payable allocations, and opening balance records that actually exist. Do not fabricate purchase invoice rows.

### AR Aging

Build AR aging from open receivable entries only.

Required filters:

- as-of date
- customer
- currency
- aging basis shown clearly

Required buckets:

- current
- 1-30
- 31-60
- 61-90
- over 90

Required output:

- customer
- document/source reference
- source date or due date if one actually exists
- original amount
- allocated/settled amount
- remaining amount
- bucket totals
- grand totals

If the schema has no due date, age by the available receivable entry date and label it clearly as `entry date basis` in the UI and tests.

### AP Aging

Build AP aging from open payable entries only.

Required filters and buckets mirror AR Aging.

Required output:

- supplier
- document/source reference
- source date or due date if one actually exists
- original amount
- allocated/settled amount
- remaining amount
- bucket totals
- grand totals

If the schema has no due date, age by the available payable entry date and label it clearly as `entry date basis` in the UI and tests.

### Cash Book

Build a standalone Cash Book report page.

Use the existing ledger-backed CashBook query foundation where present.

Required filters:

- cash account
- date from
- date to
- currency if applicable

Required output:

- opening balance
- movement lines from posted ledger entries
- receipts/inflows
- payments/outflows
- running balance
- closing balance
- linked journal/document reference

Draft cash movements must not affect the accounting balance.

### Bank Book

Build a standalone Bank Book report page.

Use the existing ledger-backed BankBook query foundation where present.

Required filters:

- bank account
- date from
- date to
- currency if applicable

Required output:

- opening balance
- movement lines from posted ledger entries
- deposits/inflows
- withdrawals/outflows
- running balance
- closing balance
- linked journal/document reference
- reconciliation/matched status if available from current bank reconciliation data

Draft bank movements must not affect the accounting balance.

### Cheque Register

Build a read-only cheque register report covering incoming and outgoing cheques.

Required filters:

- direction: incoming, outgoing, or all
- status
- customer/supplier where applicable
- bank account where applicable
- issue/receive date range where available
- due date range where available
- currency

Required output:

- direction
- cheque number
- customer/supplier
- bank/cash account
- amount
- currency
- due date
- status
- linked receipt/payment/journal where available
- lifecycle timestamps where available

This is a report. Do not add new cheque lifecycle actions here.

### Bank Reconciliation Report / Status

Build read-only bank reconciliation reporting/status pages.

Required filters:

- bank account
- status
- date range
- finalized/reconciled date range where available

Required output:

- reconciliation list with status, date range, difference, matched/unmatched counts
- detail view for one reconciliation with statement lines and matched ledger entries
- summary snapshot values:
  - statement opening
  - statement movement
  - statement closing
  - system movement
  - matched movement
  - difference
  - unmatched counts
- read-only finalized state

Do not add import, automatic matching, or automatic adjustment posting.

### AR To GL Reconciliation

Build an AR-to-GL reconciliation report.

Required filters:

- as-of date
- currency where applicable

Required output:

- AR subledger balance from open receivable entries
- AR control GL balance from posted ledger entries using the configured AR control account mapping
- difference
- breakdown by currency if needed
- list of customers/items contributing to subledger balance
- clear warning state if the AR control mapping is missing

This report must not post adjustments. It only identifies differences.

### AP To GL Reconciliation

Build an AP-to-GL reconciliation report.

Required filters:

- as-of date
- currency where applicable

Required output:

- AP subledger balance from open payable entries
- AP control GL balance from posted ledger entries using the configured AP control account mapping
- difference
- breakdown by currency if needed
- list of suppliers/items contributing to subledger balance
- clear warning state if the AP control mapping is missing

This report must not post adjustments. It only identifies differences.

## UI/UX Requirements

Build practical, polished ERP reports.

Use existing frontend foundation:

- `resources/js/Components/AppLayout.tsx`
- `resources/js/Components/Primitives.tsx`
- `resources/js/Components/SearchableSelect.tsx`
- `resources/js/Components/DatePicker.tsx`
- existing table/status/empty state patterns
- existing locale files under `resources/js/locales`
- existing accounting helpers under `resources/js/lib`

UI rules:

- No landing pages.
- No hero sections.
- No decorative gradients, orbs, blobs, or marketing layouts.
- No nested cards.
- Use dense report filters, summary strips, sticky table headers where appropriate, and scroll-safe responsive layouts.
- Keep report pages easy to scan for finance users.
- Use bilingual English/Arabic labels.
- Preserve RTL/LTR behavior.
- Use stable column widths for money/status/date columns.
- Use money formatting from existing helpers.
- Use status badges consistently.
- Provide useful empty states for no matching data.
- Show active filters clearly.
- Add reset/apply filter behavior.
- Add drill-through links to source documents, journals, customers, suppliers, cash/bank accounts, and reconciliations where routes exist.
- Do not hide data behind decorative UI.

Optional but useful if simple and dependency-free:

- CSV export for report rows using a server-side streamed response.
- Print-friendly page styling.

Do not add PDF generation packages or heavy charting libraries in this slice.

## Backend Integration Rules

- Add narrowly scoped report query services under the existing application/service conventions.
- Add controllers/routes only for these reports.
- Controllers must validate filters.
- Do not duplicate money math in React.
- Do not perform balance mutations from report code.
- Do not write audit records for ordinary report viewing unless the current app already audits read access for similar sensitive pages.
- Use existing RBAC config/seeder pattern for report permissions if required.
- Do not enable Spatie Teams.
- Do not add company-scoped or branch-scoped permissions.

## Permissions

Inspect `config/erp_rbac.php` and current route middleware.

Use existing permissions where they already fit. If report permissions are missing, add a small reports permission set through the existing RBAC config/seeder style, for example:

- `reports.view`
- or narrower permissions only if the current project pattern clearly expects them

Do not add tenant/company/branch dimensions to permissions.

## Testing Requirements

Add focused tests for Slice 8.

Minimum expected coverage:

- Report pages render with required Inertia props.
- Permission denial tests for every report route group.
- Customer statement opening/movement/closing balance correctness.
- Supplier statement opening/movement/closing balance correctness.
- AR aging bucket correctness using actual receivable entries.
- AP aging bucket correctness using actual payable entries.
- Cash Book uses posted ledger entries only.
- Bank Book uses posted ledger entries only.
- Cheque Register filters incoming/outgoing/status correctly.
- Bank reconciliation status/detail report uses existing reconciliation data and remains read-only.
- AR to GL reconciliation compares subledger balance to configured AR control account ledger balance.
- AP to GL reconciliation compares subledger balance to configured AP control account ledger balance.
- Missing AR/AP control mapping produces a clear non-mutating warning/result.
- No reports fabricate sales or purchase invoice data.
- No `company_id`, `branch_id`, `tenant_id`, `currentCompany`, or `currentBranch` is introduced.
- Locale keys exist for new UI strings.

If CSV export is implemented, add export tests for headers, filters, and integer-money formatting.

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

The docs must say Phase 3 Slice 8 is complete only if the code and verification commands pass.

## Required Final Report

Return a concise final report with:

1. Files changed.
2. Report services/controllers/routes/pages added.
3. Reports implemented.
4. Data sources used for each report.
5. UI/UX improvements made.
6. Permission and validation coverage.
7. Tests added.
8. Confirmation that reports are read-only.
9. Confirmation that no fake Sales/Purchase invoice data was created.
10. Confirmation that no company/branch/tenant scope was introduced.
11. Verification command results.
12. Remaining risks, if any.

End with explicit confirmations:

```text
Slice implemented: Phase 3 Slice 8 only.
Reports implemented: Phase 3 operational/subledger reports only.
Full financial statements implemented: NO.
Sales/Purchasing/Inventory implemented: NO.
Bank import/auto adjustment posting implemented: NO.
New tenant/company/branch scope introduced: NO.
Report code mutates accounting data: NO.
```
