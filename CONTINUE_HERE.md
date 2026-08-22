# CONTINUE HERE - Mini ERP Laravel handoff

Current date/context: 2026-08-23. This is the current handoff for the Laravel + Inertia + React migration track.

The old Next.js app under `app/` remains historical reference only. Do not restore old tenant/company-scope behavior from it.

## Source Of Truth

Use the current Laravel code and these documents first:

- `README.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `DOMAIN_MODEL_REVIEW.md`
- `DOMAIN_RELATIONSHIP_AUDIT.md`
- `SCHEMA_ASSUMPTION_AUDIT.md`
- `PROJECT_LOGIC_AUDIT.md`
- `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`
- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `PHASE_4_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_2_CORRECTION_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_3_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_4_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_5_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_6_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_7_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_8_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_9_GEMINI_PROMPT.md`
- `PHASE_4_RETURNS_CREDIT_DEBIT_DECISION.md`
- `PHASE_4_SLICE_10_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_10_SETTLEMENT_CORRECTION_PROMPT.md`
- `PHASE_5_FINANCIAL_STATEMENTS_PERIOD_CLOSE.md`
- `PHASE_5_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_5_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_5_SLICE_3_GEMINI_PROMPT.md`
- `PHASE_5_SLICE_4_GEMINI_PROMPT.md`
- `PHASE_5_SLICE_5_GEMINI_PROMPT.md`
- `PHASE_5_SLICE_6_GEMINI_PROMPT.md`
- `docs/CONCURRENCY_AUDIT.md`

Historical specs can still be useful for ERP scope, but owner corrections override old generated architecture.

## Current Stack

- Laravel 13.x + PHP 8.3+
- PostgreSQL
- Inertia.js + React + TypeScript + Tailwind
- Laravel session auth and CSRF
- Spatie Permission with teams disabled
- Spatie Translatable for multilingual master data
- Spatie Activitylog as the active audit backend
- Laravel scheduler/queues baseline

## Non-Negotiable Corrections

This ERP is not currently a multi-tenant SaaS.

Do not introduce:

- tenant context or tenant middleware
- `company_user`
- `users.company_id`
- `branch.company_id`
- `fiscal_year.company_id`
- `currentCompany` or `currentBranch`
- company-owned roles/permissions
- Spatie teams
- company/branch dimensions in document numbering
- branch/company security scopes unless explicitly defined later

If a relationship is not explicitly supported by owner requirements or a later owner decision, classify it as:

`UNDEFINED - DO NOT ASSUME`

Confirmed later owner decision:

- FiscalYear is `SINGLE-ERP CONTEXT`.
- Fiscal years are global to this installation/business profile.
- `fiscal_year.year` is globally unique.
- FinancialPeriod belongs to FiscalYear.

## Current Verified Status

The Laravel migration through M10, Phase 3 Slices 1-10, Phase 4 Slices 1-10, Phase 5 Slice 1, and Phase 5 Slice 2 (Balance Sheet & Income Statement Core Generation) is fully complete, locally hardened, and verified on PostgreSQL. Phase 5 Slice 3 is ready for bounded execution.

Latest Phase 5 Slice 2 local correction notes:

- Balance Sheet and Income Statement services must filter posted ledger activity by `ledger_entry.entry_date`, not row `created_at`.
- Unmapped statement warnings must include only active unmapped accounts with non-zero movement in the report range/as-of window.
- New financial statement pages/nav entries must keep visible UI text dictionary-backed; do not add hardcoded visible English strings in TSX pages.
- Viewing requires `reports.view` plus `view_financials`; CSV export requires `reports.export` plus `view_financials`.
- Frontend statement amount formatting must remain integer-safe from minor units, without floating-point division.
- Local targeted verification: `Phase5Slice2FinancialStatementsTest.php` 8/8 passing tests, 54 assertions; Pint, TypeScript typecheck, and Vite build all passed.

Implemented:

- M2 Inertia foundation.
- M3 schema foundation and global RBAC.
- M5 session auth backend.
- M6 migrated React/Inertia pages.
- M7 core accounting kernel parity.
- Phase 2 accounting core.
- M8 page actions for settings/users.
- M9 attachment registry + notification system.
- M10 Spatie Activitylog audit backend, scheduler, and jobs baseline.
- Phase 3 Slices 1-10 Foundation (Master Data, AR/AP Subledgers, Receipts/Payments, Allocation Engine, Cheques, Bank Reconciliation, Inertia Pages/UX, Operational Reports, Concurrency Stress & Integrity, Close-Out Report).
  - Main Reports Hub (`Reports/Index.tsx`) links.
  - Returns / Credit Notes / Debit Notes owner decision pack `PHASE_4_RETURNS_CREDIT_DEBIT_DECISION.md`.
  - Feature test suite `Phase4Slice9OperationalReportsTest.php` (7/7 passing tests, 85 assertions after local schema-alignment correction).
- Phase 4 Slice 10 Sales Returns, Credit Notes, Supplier Adjustments & Operations Close-Out:
  - Five document families: physical `sales_return`, financial `customer_credit_note`, immutable cumulative `customer_invoice_revision` print copies (`R01`/`R02`), physical `purchase_return`, and normalized `supplier_adjustment_note`.
  - Services `SalesReturnService`, `CustomerCreditNoteService`, `CustomerInvoiceRevisionService`, `PurchaseReturnService`, `SupplierAdjustmentNoteService`; inventory service extended with `recordReturn`/`recordScrap`/`calculateIssueCostForReturn` (returns post as reversal stock movements; scrap disposition does not increase saleable stock).
  - Posting through the existing PostingEngine, e.g. Sales Return + Credit Note posts Dr `sales_returns` (4200) / Cr `ar_control`; scrap posts Dr `inventory_scrap_loss` (5300); supplier adjustments post against `purchase_returns_allowances` (5400) with `input_tax_receivable` (1300) / `output_tax_payable` (2200) tax sides; mappings seeded idempotently in `AccountingCoreSeeder`.
  - Invoice revisions are snapshot-based (`R01`/`R02` cumulative: original, returned, net quantities) with no GL effects.
  - Purchase returns resolve GRNI vs AP impact by document path; AP-impacting corrections use a separate `supplier_adjustment_note` instead of mutating posted bills.
  - Manual tax stored in integer basis points with exact manual amount override; modes `none`/`manual_rate`/`manual_amount` computed as `intdiv(($baseMinor * $rateBps) + 5000, 10000)`.
  - Credit/debit settlement is manual/open only; explicit settlement/reversal actions create no extra GL and use dedicated `receivable_entry_settlement` / `payable_entry_settlement` rows.
  - Numbering keys/prefixes `SR-`, `CN-`, `PRT-`, `SAN-`; permissions `sales.returns`, `sales.credit_notes`, `sales.invoice_revisions`, `purchasing.returns`, `purchasing.adjustment_notes`; attachment registry entries for all five entities.
  - Feature test suite `Phase4Slice10ReturnsCreditNotesTest.php` (38 tests / 38 passed / 0 skipped / 230 assertions).

- Phase 4 Slice 4 Delivery Notes & Goods Receipts Operational Foundation:
  - `delivery_note`, `delivery_note_line`, `goods_receipt`, `goods_receipt_line` models/migrations.
  - `DeliveryNoteService` & `GoodsReceiptService` lifecycle (`draft` -> `confirmed` / `cancelled`).
  - Integer quantity validation (`quantity_e6 = 1000000 = 1.000000`).
  - Cumulative over-delivery and over-receipt prevention with deterministic row locks.
  - Number sequence allocation `DN-YYYY-XXXXX` and `GRN-YYYY-XXXXX` with idempotent confirmation replay.
  - Spatie Activitylog audit via `AuditLogger`.
  - Attachment entity registry registration for `delivery_note` and `goods_receipt`.
  - `DeliveryNoteController` and `GoodsReceiptController` endpoints.
  - `DeliveryNotes.tsx` and `GoodsReceipts.tsx` Inertia pages.
  - `Phase4Slice4FulfillmentTest` 17/17 passing tests (138 assertions).
- Phase 4 Slice 5 Customer Invoice Posting to AR/GL:
  - `customer_invoice` and `customer_invoice_line` models/migrations.
  - `CustomerInvoiceService` lifecycle (`draft` -> `submitted` -> `approved` -> `posted` / `cancelled`).
  - manual service/non-stock invoice lines and confirmed Sales Order / Delivery Note source lines.
  - strict source matching for source header, source line, product, UOM, and unit price.
  - cumulative over-invoicing prevention with deterministic source-line locks.
  - `INV-YYYY-XXXXX` global numbering, `sales_revenue` mapping, PostingEngine integration, and AR `receivable_entry` debit.
  - Spatie Activitylog audit via `AuditLogger`, attachment registry registration for `customer_invoice`, controller/routes, and `CustomerInvoices.tsx` Inertia page.
  - `Phase4Slice5CustomerInvoiceTest` 19/19 passing tests (86 assertions) after local hardening.
- Phase 4 Slice 6 Supplier Bill Posting to AP/GL:
  - `supplier_bill` and `supplier_bill_line` models/migrations.
  - `SupplierBillService` lifecycle (`draft` -> `submitted` -> `approved` -> `posted` / `cancelled`).
  - manual service/non-stock bill lines and confirmed Purchase Order / Goods Receipt source lines.
  - strict source matching for source header, source line, product, UOM, and unit cost.
  - cumulative over-billing prevention with deterministic source-line locks, including duplicate source-line protection inside one bill.
  - `BILL-YYYY-XXXXX` global numbering, `purchase_expense` mapping, PostingEngine integration, and AP `payable_entry` credit.
  - Spatie Activitylog audit via `AuditLogger`, attachment registry registration for `supplier_bill`, controller/routes, and `SupplierBills.tsx` Inertia page.
  - `Phase4Slice6SupplierBillTest` 19/19 passing tests (100 assertions) after local hardening.
  - currencies and FX rates
  - fiscal years and periods
  - account categories and account types
  - chart of accounts
  - manual journals
  - posting engine
  - immutable ledger entries
  - reversal workflow
  - opening balances
  - General Journal, General Ledger, Trial Balance
  - demo accounting data seeder and empty states
- M8 page actions:
  - company create/update
  - branch create/update
  - numbering create/update
  - role assign/revoke
- M9 attachments and notifications services.
- M10 Spatie Activitylog migration, audit viewer, scheduler, and jobs baseline.
- Phase 3 Slice 1 master data:
  - Customer and Supplier models/services.
  - CashAccount and BankAccount models/services.
  - GL account and currency relationships.
  - optimistic locking, RBAC permissions, Spatie Activitylog audit, and attachment registry entries.
- Phase 3 Slice 2 AR/AP subledger and opening balances:
  - Customer/Supplier opening balances.
  - `receivable_entry` and `payable_entry` subledgers.
  - global accounting mappings for AR control, AP control, and opening-balance offset accounts.
  - PostingEngine integration, idempotent posting, subledger-to-GL reconciliation, and DB integrity hardening.
- Phase 3 Slice 3 receipt/payment posting:
  - Customer Receipt and Supplier Payment draft/post services.
  - global `REC-YYYY-XXXXX` and `PAY-YYYY-XXXXX` numbering.
  - PostingEngine GL effects for Cash/Bank GL vs AR/AP control.
  - AR/AP subledger effects, unapplied balance tracking, idempotent posting, linked GL currency validation, and DB integrity hardening.
- Phase 3 Slice 4 allocation engine:
  - ReceivableAllocation and PayableAllocation models/services.
  - CustomerReceipt-to-ReceivableEntry and SupplierPayment-to-PayableEntry settlement.
  - allocation reversal without mutating journals/ledgers.
  - deterministic locks, active allocation row locking, idempotency, and true concurrent allocation stress coverage.
- Phase 3 Slice 5 cheque lifecycle:
  - IncomingCheque and OutgoingCheque models/services.
  - incoming receive/deposit/clear/bounce/return and outgoing issue/clear/return/cancel pre-clear workflows.
  - Cheques Under Collection and Cheques Payable mappings.
  - PostingEngine GL effects, AR/AP subledger effects, idempotency, Spatie Activitylog audit, attachment registry entries, and PostgreSQL cheque transition stress coverage.
- Phase 3 Slice 6 bank reconciliation:
  - BankReconciliation and BankReconciliationLine models/services.
  - manual ledger-backed statement matching only; no bank statement import.
  - CashBook and BankBook query services derived from immutable posted ledger entries.
  - draft -> in_progress -> reconciled lifecycle, zero-difference finalization, DB-enforced immutable finalized records, and PostgreSQL reconciliation stress coverage.
- Phase 3 Slice 7 Inertia pages and UX actions:
  - 13 Controllers, 14 Inertia pages, DatePicker with RTL support, sidebar navigation, EN/AR locale support, and 13/13 UI feature tests.
- Phase 3 Slice 8 operational and subledger reports:
  - `reports.view` permission, Reports Hub, customer/supplier statements, AR/AP aging, Cash Book, Bank Book, Cheque Register, bank reconciliation status/detail, AR/AP to GL reconciliation, CSV exports, and read-only report services under `App\Application\Reports`.
- Phase 3 Slice 9 PostgreSQL stress and integrity hardening:
  - `accounting:phase3-integrity-check` audit command.
  - `accounting:phase3-stress` orchestrator command.
  - PostgreSQL concurrency stress coverage across all Phase 3 workflows.
  - `Phase3Slice9StressIntegrityTest` 6/6 tests, 262 assertions.
  - read-only report integrity verified.
- Phase 3 Slice 10 Close-Out & Final Verification Gate:
  - Repository documentation audit, status synchronization, final verification gate execution, and `PHASE_3_FINAL_VERIFICATION_REPORT.md`.

Latest verified commands:

Phase 5 Slice 2 correction pass:

```powershell
cd laravel
vendor/bin/pint --test
php artisan test --filter=Phase5Slice2FinancialStatementsTest
npm run typecheck
npm run build
```

Latest Phase 5 Slice 2 correction results:

- `vendor/bin/pint --test`: passed.
- `php artisan test --filter=Phase5Slice2FinancialStatementsTest`: 8 tests / 8 passed / 0 skipped / 54 assertions.
- `npm run typecheck`: passed.
- `npm run build`: passed (chunk size warning only).

```powershell
cd laravel
composer install
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --filter=Phase4Slice10ReturnsCreditNotesTest
php artisan test --filter=Phase4Slice1CatalogTest
php artisan test --filter=Phase4Slice2SalesOrderTest
php artisan test --filter=Phase3Slice9StressIntegrityTest
php artisan test --filter=Phase3Slice8ReportsTest
php artisan test --filter=Phase3Slice6BankReconciliationTest
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan accounting:phase3-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Latest results:

- `php artisan migrate --force`: Nothing to migrate after Phase 4 Slice 10 implementation.
- `php artisan migrate:status`: all migrations Ran through `2026_08_22_200000_create_phase4_slice10_settlement_tables`.
- `php artisan test`: 407 tests, 404 passed, 3 skipped / 3172 assertions.
- `php artisan test --filter=Phase4Slice10ReturnsCreditNotesTest`: 38 tests / 38 passed / 0 skipped / 230 assertions.
- `php artisan test --testsuite=Concurrency`: 7 tests / 16 assertions passed.
- `php artisan concurrency:stress --workers=10`: passed; `--workers=100` remains blocked locally by Windows paging-file memory exhaustion.
- `php artisan accounting:concurrency-stress --workers=50`: passed.
- `php artisan accounting:allocation-concurrency-stress --workers=50`: passed.
- `php artisan accounting:settlement-concurrency-stress --workers=50`: passed.
- `php artisan accounting:cheque-concurrency-stress --workers=50`: passed.
- `php artisan accounting:bank-reconciliation-concurrency-stress --workers=50`: passed.
- `php artisan accounting:inventory-concurrency-stress --workers=50`: passed.
- `php artisan accounting:phase3-integrity-check`: passed.
- `php artisan accounting:phase3-stress --workers=50`: 50 SUCCESS.
- `php artisan tokens:gc --batch=100`: OK.
- `vendor/bin/pint --test`: passed after Phase 4 Slice 10 implementation.
- `npm run typecheck`: passed.
- `npm run build`: passed (chunk size warning only).
- Supplier Bill backend forbidden float/rounding source scan: no results.

## Audit Status

Spatie Activitylog is now the active audit backend.

- New writes go to `activity_log`.
- Legacy `audit_log` is retained as a read-only archive.
- `AuditLogger::record(...)` keeps the old application API but writes through Spatie Activitylog.
- `AuditLogQueryService` maps Spatie rows back to the old UI aliases:
  - `actor_id`
  - `actor_name`
  - `actor_email`
  - `action`
  - `entity_type`
  - `entity_id`
  - `before_json`
  - `after_json`
  - `reason`
  - `request_id`
  - `ip`
  - `device`
  - `at`
- `activity_log` and legacy `audit_log` are protected by append-only DB triggers.

Spatie Activitylog installed version:

```text
spatie/laravel-activitylog 4.12.3
```

## Local Login

Default development bootstrap user:

```text
Email: admin@mini-erp.local
Password: Password123!
Role: SUPER_ADMIN
```

The bootstrap user is not tied to a company, branch, tenant, or current-company context.

## Run Locally

```powershell
cd laravel
composer install
npm install
php artisan migrate --seed
npm run dev
composer run serve:no-xdebug
```

Open:

```text
http://127.0.0.1:8000
```

On this Windows/WAMP setup, direct `php artisan serve` may exit when Xdebug is enabled. Prefer `composer run serve:no-xdebug`.

## Current DB Counts From Last Verification

```text
audit_log: 17
activity_log: 397
users: 7
jobs: 0
failed_jobs: 0
bank_reconciliation: 10
bank_reconciliation_line: 12
journal_entry: 81
ledger_entry: 156
```

`activity_log` can vary because stress commands create real audit records outside PHPUnit transactions.

## Phase 3 Completion & Phase 4 Start

**Phase 3 Slices 1–10 are 100% complete.** Phase 3 AR/AP + Cash/Bank/Cheques track is fully closed out for the agreed scope. See `PHASE_3_FINAL_VERIFICATION_REPORT.md`.

All 10 bounded prompt files (`PHASE_3_SLICE_1_GEMINI_PROMPT.md` through `PHASE_3_SLICE_10_GEMINI_PROMPT.md`) have been executed and remain as historical traceability reference.

Phase 4 planning is prepared:

- `PHASE_4_SALES_PURCHASING_OPERATIONS.md`
- `PHASE_4_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_2_CORRECTION_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_3_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_4_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_5_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_6_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_7_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_8_GEMINI_PROMPT.md`
- `PHASE_4_SLICE_9_GEMINI_PROMPT.md`
- `PHASE_4_INVENTORY_COSTING_DECISION.md`
- `PHASE_4_RETURNS_CREDIT_DEBIT_DECISION.md`
- `PHASE_4_SLICE_10_GEMINI_PROMPT.md`

Next prepared execution step:

Execute `PHASE_5_SLICE_3_GEMINI_PROMPT.md` for Cash Flow Statement Foundation.

Other possible owner choices:

- **Optional: E2E Browser Testing** (Playwright / Dusk smoke testing).
- **Optional: Production Deployment Readiness** (Nginx, Supervisor, Redis, Backup strategies).

Not started; each requires a bounded owner prompt before any implementation:

- Payroll.
- Rentals.
- Fixed Assets.
- Full tax/VAT filing module beyond Slice 10 manual note tax fields.
- Warehouse/location semantics.
- Landed cost and freight allocation.
- Remaining Phase 5 financial statement work beyond Balance Sheet/Income Statement: Cash Flow, period close controls, year-end close decision pack, and UX/export/print close-out.

Going forward, keep these invariants:

- no tenant/company/branch scope
- no float money math
- posted journal and ledger data immutable
- corrections by reversal
- numbering atomic
- posting idempotent
- Phase 3 audit uses the owner-approved Spatie Activitylog decision through the existing `AuditLogger` API
- attachment authorization through entity registry
- notifications targeted to users
