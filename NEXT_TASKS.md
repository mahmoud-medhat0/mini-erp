# NEXT TASKS - Current Laravel Track

Current status: Phase 4 Slice 10 (Sales Returns, Credit Notes & Manual Note Settlement) is fully implemented and locally verified on 2026-08-22, including the Manual Settlement Pass for note-created AR/AP entries (`PHASE_4_SLICE_10_SETTLEMENT_CORRECTION_PROMPT.md`). Phase 5 Slices 1-3 are complete and locally corrected on 2026-08-23.

Do not use the old Next.js tenant/company-scope checklist as implementation guidance. The ERP is single-installation context unless a later owner decision explicitly defines otherwise.

## Completed

- M2 Laravel/Inertia foundation.
- M3 foundation schema, global RBAC, and no-team Spatie Permission.
- M5 Laravel session auth.
- M6 migrated app shell/pages.
- M7 Laravel core kernel parity.
- Phase 2 accounting core ledger spine.
- M8 page actions for migrated settings/users pages.
- M9 attachments and notifications services.
- M10 Spatie Activitylog audit backend, scheduler, and jobs baseline.
- Phase 3 Slices 1-10 Foundation: Master Data, AR/AP Subledgers, Receipts/Payments, Allocation Engine, Cheques, Bank Reconciliation, Inertia Pages/UX, Operational Reports, Concurrency Stress & Integrity, Close-Out Report.
- Phase 4 Slice 1 Product/Service Catalog Foundation.
- Phase 4 Slice 2 Sales Order Backend & UX.
- Phase 4 Slice 3 Purchase Order Backend & UX.
- Phase 4 Slice 4 Delivery Notes & Goods Receipts.
- Phase 4 Slice 5 Customer Invoice Posting.
- Phase 4 Slice 6 Supplier Bill Posting.
- Phase 4 Slice 7 Inventory Costing Decision Pack (Owner selected Option 1: Moving Weighted Average Costing).
- Phase 4 Slice 8 Moving Weighted Average Inventory Costing & Posting.
- Phase 4 Slice 9 Operational Reports & Returns Decision Pack.
- Phase 4 Slice 10 Sales Returns, Credit Notes & Operations Close-Out (FULLY COMPLETE):
  - Five document families: `sales_return`, `customer_credit_note`, `customer_invoice_revision`, `purchase_return`, `supplier_adjustment_note` across seven migrations (including `receivable_entry_settlement` and `payable_entry_settlement`).
  - Services: `SalesReturnService`, `CustomerCreditNoteService`, `CustomerInvoiceRevisionService`, `PurchaseReturnService`, `SupplierAdjustmentNoteService`, `ReceivableEntrySettlementService`, `PayableEntrySettlementService`.
  - Manual AR credit note settlement against invoice debits & AP adjustment note settlement against bill credits.
  - Concurrency stress command: `accounting:settlement-concurrency-stress`.
  - Feature test suite `Phase4Slice10ReturnsCreditNotesTest.php` (38/38 passing tests, 0 skipped, 230 assertions).
  - GL mapping keys `sales_returns` (4200), `inventory_return_variance` (5200), `inventory_scrap_loss` (5300), `purchase_returns_allowances` (5400), `input_tax_receivable` (1300), `output_tax_payable` (2200) seeded idempotently in `AccountingCoreSeeder` with accounts.
  - Manual tax percentage in integer basis points (`intdiv(($baseMinor * $rateBps) + 5000, 10000)`) with modes `none`/`manual_rate`/`manual_amount`; manual/open credit/debit settlement with explicit settlement/reversal actions and no extra GL.
- Phase 5 Slice 1 Financial Statement Mapping Foundation (FULLY COMPLETE):
  - Database schema: `financial_statement_line` table and `account.financial_statement_line_id` FK (`2026_08_23_000000_create_phase5_slice1_financial_statement_line_tables.php`).
  - Model & Relations: `FinancialStatementLine` model with `HasTranslations` (`name`), `HasUuids`, and `accounts` relationship; `Account` updated with `financialStatementLine` relationship.
  - Default Seeder: `FinancialStatementLineSeeder` seeds 11 default system statement lines (`ASSET_CURRENT`, `ASSET_NON_CURRENT`, `LIABILITY_CURRENT`, `LIABILITY_NON_CURRENT`, `EQUITY`, `REVENUE`, `CONTRA_REVENUE`, `COGS`, `EXPENSE_OPERATING`, `INCOME_OTHER`, `EXPENSE_OTHER`) idempotently.
  - Domain Service: `FinancialStatementMappingService` with line CRUD, account assignment validation (system line protection, line in-use protection, statement_type matching), and `AuditLogger` integration.
  - Controller & Routes: `FinancialStatementMappingController` and routes under `/accounting/statement-mappings` protected by `accounting.mappings` permission.
  - Inertia Page: `FinancialStatementMappings.tsx` with mapped/unmapped account views, tabs, quick assignment widget, system badges, no emojis, full EN/AR dictionary translations, and no hardcoded visible TSX text fallbacks.
  - Feature Suite: `Phase5Slice1FinancialStatementMappingTest.php` (9/9 passing tests, 30 assertions).
- Phase 5 Slice 2 Balance Sheet & Income Statement Core Generation (FULLY COMPLETE):
  - Query Services: `BalanceSheetReportService` and `IncomeStatementReportService` generating structured financial statement lines and account totals from immutable posted `ledger_entry` records.
  - Local correction: report date filtering uses accounting `ledger_entry.entry_date`, never row `created_at`, so backdated/postdated postings appear in the correct reporting period.
  - Subtotals & Accounting Equation: Balance Sheet compares Total Assets against Liabilities + Equity, reports `is_balanced` status and imbalance warning if unmapped or out of balance; Income Statement calculates Net Revenue, Gross Profit, Operating Income, and Net Income / (Loss).
  - Unmapped Accounts Visibility: Both reports display unmapped active accounts with non-zero movement in dedicated sections and set `has_unmapped_warning`; unmapped accounts with no movements are not noisy warnings.
  - Controllers & Export: `BalanceSheetReportController` and `IncomeStatementReportController` with CSV export streaming (`exportCsv`).
  - Authorization: Strict server-side gates enforcing `reports.view` AND `view_financials` for report viewing, and `reports.export` AND `view_financials` for CSV export.
  - Inertia Pages & Navigation: `BalanceSheet.tsx` and `IncomeStatement.tsx` with date filter controls, period dropdowns, imbalance & unmapped warning banners, no emojis, full EN/AR dictionary translations, Reports Hub integration, sidebar navigation, no new hardcoded visible TSX fallbacks, and integer-safe money formatting.
  - Feature Suite: `Phase5Slice2FinancialStatementsTest.php` (8/8 passing tests, 54 assertions).
- Phase 5 Slice 3 Cash Flow Statement Foundation (FULLY COMPLETE):
  - Database schema: Added `cash_flow_activity` column to `financial_statement_line` and `account` (`2026_08_23_010000_create_phase5_slice3_cash_flow_activity_columns.php`) and PostgreSQL check constraints (`2026_08_23_011000_harden_phase5_slice3_cash_flow_activity_constraints.php`).
  - Cash-Equivalent Derivation: Dynamically resolves active GL accounts from linked `CashAccount` and `BankAccount` records and returns structured warning codes for missing/invalid links so UI can localize messages.
  - Query Service: `CashFlowReportService` classifying operating, investing, financing, and unclassified cash movements from posted `ledger_entry.entry_date` records using strict precedence (`account` -> `financial_statement_line` -> `unclassified`).
  - Movement Rules: Opening/closing cash balance calculations, internal cash transfer detection & exclusion from activity totals, mixed-activity journals routed to unclassified warnings, and no `created_at`/`updated_at` financial filtering.
  - Mapping Controls: `FinancialStatementMappings.tsx` now exposes line-level cash-flow activity and account-level non-cash overrides; backend rejects cash-flow activity assignment directly to active cash/bank GL accounts.
  - Controller & Export: `CashFlowReportController` and streamed CSV exports (`exportCsv`).
  - Authorization: Strict server-side gates enforcing `reports.view` AND `view_financials` for report viewing, and `reports.export` AND `view_financials` for CSV export.
  - Inertia Pages & Navigation: `CashFlow.tsx` with date filter controls, period dropdowns, localized warning banners, no emojis, no hardcoded visible TSX fallback text, integer-safe string-based money formatting, full EN/AR dictionary translations, Reports Hub integration, and sidebar navigation.
  - Feature Suite: `Phase5Slice3CashFlowStatementTest.php` (9/9 passing tests, 46 assertions).

## Immediate Next Steps (Phase 5 Slice 4)

- Execute **Phase 5 Slice 4: Period Close Controls & Hardening**:
  - Read `PHASE_5_SLICE_4_GEMINI_PROMPT.md`.
  - Follow the hardened prompt exactly: service-level closed-period guards, PostingEngine final safety net, actual-schema blocker inspection, close/post race coverage, no `settings.configure` bypass, and no timestamp-based accounting filters.

## Next Execution

Continue Phase 5 in bounded order:

1. `PHASE_5_SLICE_4_GEMINI_PROMPT.md` - Period Close Controls and Posting Guards.
2. `PHASE_5_SLICE_5_GEMINI_PROMPT.md` - Year-End Close and Retained Earnings Decision Pack.
3. `PHASE_5_SLICE_6_GEMINI_PROMPT.md` - UX, Export/Print, E2E Smoke, and Close-Out.

Phase 5 must preserve exact permissions, especially `reports.view`, `reports.export`, `reports.print`, `view_financials`, `accounting.mappings`, `close_period`, and `reopen_period`. Frontend pages must not add hardcoded visible text or hardcoded team/tenant/company/branch assumptions.

No required Phase 4 correction remains. Optional Production Deployment Readiness remains separate from Phase 5.

Explicitly NOT STARTED modules requiring bounded owner prompts:

- Payroll.
- Rentals.
- Fixed assets.
- Full tax/VAT filing and reporting module beyond Slice 10 manual note tax fields.
- Warehouse/location semantics.
- Landed cost and freight allocation.

## Owner Decisions Still Needed

Still do not implement these without explicit owner approval:

- full VAT/tax filing/reporting workflow beyond Slice 10 manual note tax fields.
- FIFO, Standard Costing, or Non-Valued alternate inventory costing branches.
- Warehouse/location semantics.
- Warehouse-to-branch relationship.
- Landed cost and freight allocation.
- Post-confirmation sales order cancellation behavior once delivery/invoice exists.
- Post-confirmation purchase order cancellation behavior once goods receipt/bill exists.
- Price lists, discounts, and contract pricing.
- Separate quotation/requisition modules.
- Approval workflow engine beyond bounded status transitions.
- Credit limit blocking.

## Verification Gate

Run from `laravel/` for every Phase 5 implementation slice:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=10
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:settlement-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan accounting:phase3-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Add slice-specific stress tests when a slice introduces concurrency-sensitive transitions or posting.
