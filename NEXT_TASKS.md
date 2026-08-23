# NEXT TASKS - Current Laravel Track

Current status: Phase 6 (Fixed Assets & Depreciation Engine) is 100% fully implemented, concurrency-hardened, and locally verified on 2026-08-23. All 7 slices of Phase 6 are complete.

Do not use the old Next.js tenant/company-scope checklist as implementation guidance. The ERP is single-installation context unless a later owner decision explicitly defines otherwise.

## Completed

- Phase 6 Slice 7 Reports, UX, Export/Print, E2E Smoke & Close-Out (FULLY COMPLETE):
  - Report Service: `FixedAssetReportService` centralizes register, net book value, depreciation schedule, depreciation run history, and disposal history calculations.
  - Controller & Routes: `FixedAssetReportController` exposes five read-only report pages plus five CSV exports under `/reports/fixed-asset-*`.
  - Inertia React UI: `FixedAssetRegisterReport.tsx`, `FixedAssetNetBookValueReport.tsx`, `FixedAssetDepreciationReport.tsx`, `FixedAssetDepreciationRunReport.tsx`, `FixedAssetDisposalReport.tsx`, and Reports Hub integration in `Reports/Index.tsx`.
  - Export: CSV export endpoints preserve integer minor units and are guarded by (`reports.export` OR `fixedAssets.export`) plus `reports.view` and `view_financials`; `fixedAssets.view` alone is not an export permission.
  - Required Source Scans: 0 prohibited tenant/branch/custodian/location scope matches, 0 `/100` or float money conversion matches, 0 hardcoded Arabic/`locale ===` visible report text matches, and 0 `created_at` report ordering matches in the Slice 7 report service.
  - Feature test suite `Phase6Slice7FixedAssetReportsTest.php` (6/6 passing tests / 153 assertions).
  - Final Verification Gate: full suite 514 tests / 511 passed / 3 skipped / 3855 assertions, Phase 6 suite 64/64 tests / 456 assertions, Concurrency suite 7/7, core and fixed-asset PostgreSQL stress commands, Pint, typecheck, build, migrations, and token GC passed.
  - Verification Artifact: `PHASE_6_FINAL_VERIFICATION_REPORT.md` updated after local correction.

- Phase 6 Slice 6 Fixed Asset Disposal (FULLY COMPLETE):
  - Database schema: `2026_08_23_070000_create_phase6_slice6_fixed_asset_disposal_tables.php` creating `fixed_asset_disposal` table with PostgreSQL check constraints `chk_fad_status`, `chk_fad_type`, and `chk_fad_amounts`.
  - Hardening migration: `2026_08_23_071000_enforce_fixed_asset_disposal_integrity.php` enforces one posted disposal per asset and blocks UPDATE/DELETE mutation of posted disposal financial fields at database level.
  - Eloquent models: `FixedAssetDisposal` created and `FixedAsset` updated with `disposals` relation.
  - Domain service: `FixedAssetDisposalPostingService` supporting `previewDisposal`, `postDisposal` (sale/scrap/retirement), and `reverseDisposal`, with corrected idempotency so repeated post requests replay safely while corrected reposting after reversal is allowed.
  - Accounting Entry Engine: Credit `fixed_asset_cost`, Debit `accumulated_depreciation`, Debit `fixed_asset_clearing` (proceeds), Debit `fixed_asset_disposal_loss` (if loss), Credit `fixed_asset_disposal_gain` (if gain), through `PostingEngine` with open period guard.
  - Depreciation/disposal safeguards: blocks disposal before already posted depreciation periods, locks asset schedules during disposal posting, skips unposted schedules at/after disposal date, and restores those skipped schedules on reversal.
  - Controllers & Routes: `FixedAssetDisposalController` (`index`, `show`, `preview`, `store`, `reverse`) guarded by permissions `fixedAssets.view`, `fixedAssets.post`, `fixedAssets.reverse`, and `view_financials`.
  - Inertia React UI: `Disposals/Index.tsx` and `Disposals/Show.tsx` created, Dispose Asset Modal added in `FixedAssets/Show.tsx`, with visible text dictionary-backed through EN/AR locale files.
  - Navigation: added `fixed-assets-disposals.index` navigation key and permission map in `AppLayout.tsx`.
  - Translations: EN/AR dictionary keys added in `en.json` and `ar.json`.
  - Concurrency stress command: `accounting:fixed-asset-disposal-stress --workers=50` (Passed cleanly on PostgreSQL).
  - Feature test suite `Phase6Slice6FixedAssetDisposalTest.php` (15/15 passing tests / 60 assertions).
  - Full local verification after review: `php artisan test` 508 tests / 505 passed / 3 skipped / 3702 assertions, Phase 6 combined 58/58 tests / 303 assertions, Concurrency suite 7/7, Pint/typecheck/build, token GC, and all relevant PostgreSQL stress commands passed.

- Phase 6 Slice 5 Depreciation Run Posting (FULLY COMPLETE):
  - Database schema: `2026_08_23_060000_create_phase6_slice5_depreciation_run_tables.php` creating `fixed_asset_depreciation_run` table with PostgreSQL check constraints `chk_fadr_status` and `chk_fadr_amounts`, plus added `depreciation_run_id` foreign key on `fixed_asset_depreciation_schedule`.
  - Hardening migration: `2026_08_23_061000_harden_fixed_asset_depreciation_schedule_run_link_immutability.php` protects `depreciation_run_id` on posted schedules.
  - Eloquent models: `FixedAssetDepreciationRun` created and `FixedAssetDepreciationSchedule` updated with `depreciationRun` relation.
  - Domain service: `FixedAssetDepreciationPostingService` with `PeriodGuard::assertPeriodOpenForPostingWithLock` enforcement, row locks, idempotency claim handling, balanced journal voucher posting (Dr `depreciation_expense` / Cr `accumulated_depreciation`), and reversal via `ReversalService` marking schedule rows `reversed` while preserving original run/journal links.
  - Controllers & Routes: `FixedAssetDepreciationRunController` (`index`, `store`, `show`, `preview`, `reverse`) guarded by permissions `fixedAssets.view`, `fixedAssets.post`, `fixedAssets.reverse`, and `view_financials`.
  - Inertia React UI: `DepreciationRuns/Index.tsx`, `DepreciationRuns/Show.tsx`, and `DepreciationRuns/Preview.tsx` updated with dictionary translations, modal run creator, linked JV link, preview workflow, and reverse action.
  - Navigation: added `fixed-assets.depreciation-runs.index` navigation link and permission mapping in `AppLayout.tsx`.
  - Translations: EN/AR dictionary keys added in `en.json` and `ar.json`.
  - Concurrency stress command: `accounting:fixed-asset-depreciation-stress --workers=50`.
  - Feature test suite `Phase6Slice5DepreciationRunTest.php` (10/10 passing tests / 44 assertions).

- Phase 6 Slice 4 Depreciation Schedule Engine (FULLY COMPLETE):
  - Database schema: `2026_08_23_050000_create_phase6_slice4_fixed_asset_depreciation_schedule_table.php` creating `fixed_asset_depreciation_schedule` table with PostgreSQL check constraints `chk_fads_status` and `chk_fads_amounts`.
  - Eloquent models: `FixedAssetDepreciationSchedule` created and `FixedAsset` updated with `depreciationSchedules` HasMany relation.
  - Domain service: `FixedAssetDepreciationEngineService` with deterministic straight-line integer minor-unit math, integer remainder allocation (sum of period amounts exactly equals depreciable base), automatic fiscal year creation/extension via `PeriodService`, and idempotent schedule generation with protection of posted lines.
  - Local review corrections: enforced month-after-in-service start policy across SQLite/PostgreSQL date comparisons, restricted schedule generation to active assets, kept schedule reads side-effect free, added DB-level immutability for posted schedule financial fields, and localized schedule statuses/date separators.
  - Hardening migration: `2026_08_23_051000_enforce_fixed_asset_depreciation_schedule_immutability.php`.
  - Controller & Routes: `FixedAssetController::generateSchedule` guarded by permissions `fixedAssets.edit` and `view_financials`, and web route `POST /fixed-assets/{id}/generate-schedule`.
  - Inertia React UI: `Show.tsx` updated with Depreciation Schedule table preview and Generate/Regenerate Schedule action.
  - Translations: EN/AR dictionary keys added in `en.json` and `ar.json`.
  - Feature test suite `Phase6Slice4DepreciationScheduleTest.php` (13/13 passing tests / 64 assertions).

- Phase 6 Slice 3 Capitalization and Opening Asset Posting (FULLY COMPLETE):
  - Database schema: `2026_08_23_040000_create_phase6_slice3_capitalization_columns.php` adding `capitalization_mode`, `capitalization_date`, `journal_entry_id`, `capitalized_at`, `capitalized_by` to `fixed_asset`.
  - Eloquent model: `FixedAsset` updated with fillable, casts, `journalEntry`, and `capitalizer` relations.
  - Domain service: `FixedAssetCapitalizationService` supporting `opening_already_capitalized` (0 GL entries) and `manual_capitalization` (PostingEngine Dr Asset Cost / Cr Fixed Asset Clearing), capitalization reversal via `ReversalService`, and row-lock/state-based retry-safe idempotency.
  - Controllers & Routes: `FixedAssetController::capitalize` and `reverseCapitalization` guarded by `fixedAssets.post`, `fixedAssets.reverse`, and `view_financials`.
  - Inertia React UI: `Show.tsx` updated with Capitalize modal, status badges, linked journal voucher link, and Reverse Capitalization action.
  - Translations: EN/AR dictionary keys added to `en.json` and `ar.json`.
  - Local review corrections: removed hardcoded visible text from the fixed asset detail page, blocked direct register edit/update after activation, blocked non-draft capitalization, blocked recapitalization with a different mode, removed stale idempotency failure risk after closed-period attempts, and changed generated journal descriptions/memos to localization-ready machine keys.
  - Feature test suite `Phase6Slice3CapitalizationTest.php` (11/11 passing tests / 64 assertions).

- Phase 6 Slice 2 Fixed Asset Register Foundation (FULLY COMPLETE):
  - Database schema: `fixed_asset_category` and `fixed_asset` tables with PostgreSQL check constraints (`2026_08_23_030000_create_phase6_slice2_fixed_asset_tables.php`).
  - Eloquent models: `FixedAssetCategory` and `FixedAsset` with `HasTranslations` and `HasUuids`.
  - 6 fixed asset GL mapping keys registered in `AccountingAccountMappingService` and seeded in `AccountingCoreSeeder`.
  - Attachment entity `fixed_asset` registered in `config/erp_attachments.php`.
  - Domain services: `FixedAssetCategoryService` and `FixedAssetRegisterService` (using `NumberSequenceAllocator::nextValue('fixed_asset')` for `FA-YYYY-00001` asset numbers).
  - Controllers: `FixedAssetCategoryController` and `FixedAssetController`.
  - Routes: `/fixed-asset-categories` and `/fixed-assets` guarded by RBAC permissions (`fixedAssets.view`, `fixedAssets.create`, `fixedAssets.edit`, `fixedAssets.delete`, `view_financials`).
  - Inertia React pages: `Categories.tsx`, `Index.tsx`, `Create.tsx`, `Show.tsx`, `Edit.tsx`.
  - EN/AR translations in `en.json` & `ar.json` and navigation links in `AppLayout.tsx`.
  - Local review corrections: fixed multilingual form payloads, removed hardcoded visible text in the new TSX pages, made activation workflow-owned via capitalization instead of generic register updates, and hardened currency validation with `exists:currency,code`.
  - Feature test suite `Phase6Slice2FixedAssetRegisterTest.php` (9/9 passing tests / 71 assertions after local review).

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
- Phase 5 Slice 4 Period Close Controls & Hardening (FULLY COMPLETE):
  - Migration `2026_08_23_020000_create_phase5_slice4_period_close_columns.php` adds close/reopen metadata and PostgreSQL `financial_period.status` constraint.
  - `PeriodGuard` and `PeriodClosedException` protect PostingEngine, posting services, and date-resolved stock posting periods.
  - Close/reopen routes use exact `close_period` / `reopen_period` permissions; `settings.configure` is not a close/reopen bypass.
  - Close-readiness blocks unposted postable documents, including approved invoices, bills, returns, credit notes, and supplier adjustment notes.
  - Periods UI close/reopen controls are permission-aware, dictionary-backed, and localize blocker entity/status labels without visible TSX English fallbacks.
  - Local correction also fixed the time-dependent Phase 4 Slice 10 settlement test by pinning test time to the document date.
  - Verification: full suite 446 tests / 443 passed / 3 skipped / 3344 assertions; Slice 4 suite 13/13 tests / 37 assertions; typecheck/build/stress commands passed.

- Phase 5 Slice 5 Year-End Close & Retained Earnings Decision Pack (FULLY COMPLETE):
  - Created `PHASE_5_YEAR_END_CLOSE_DECISION.md` containing Arabic executive summary, plain-language business owner explanations, technical comparison of 3 options (Soft close only, Physical closing journal, and Hybrid approach), owner decision prompt and approval checklist, database/audit/reopen/permission specifications, future testing strategy, and explicit "not implemented yet" declaration.
  - Recommended Option 3 (Hybrid: soft close now with current date-based financial reporting, physical closing entry engine later only upon explicit approval).
  - Preserved docs-only execution: 0 migrations, 0 models, 0 services, 0 routes, 0 UI components added. Status marked as `OWNER DECISION REQUIRED`.

- Phase 5 Slice 6 UX, Export/Print, E2E Smoke & Close-Out (FULLY COMPLETE):
  - Added permission-aware Print action buttons (`reports.print` + `view_financials`) to `BalanceSheet.tsx`, `IncomeStatement.tsx`, and `CashFlow.tsx`.
  - Added `Phase5Slice6FinalCloseOutTest.php` verifying CSV export streaming, service total matching, authorization enforcement, route access contracts, and schema-field fidelity (4/4 passing tests, 30 assertions after local review).
  - Cleaned dictionary keys in `en.json` and `ar.json`.
  - Local review corrected the Slice 6 fixture to use actual schema fields: `financial_period.month`, `journal_entry.number`, and `account.is_active`.
  - Executed verification after local review: migrations up to date, full PHPUnit test suite (450 tests / 447 passed / 3 skipped / 3374 assertions), Concurrency testsuite (7/7), all accounting/Phase 3 stress and integrity commands, token GC, Pint lint check, TypeScript typecheck, and Vite build.
  - Created `PHASE_5_FINAL_VERIFICATION_REPORT.md` close-out artifact.

- Phase 6 Slice 1 Fixed Asset Policy Decision Pack (FULLY COMPLETE):
  - Created `PHASE_6_FIXED_ASSETS_POLICY_DECISION.md` containing Arabic executive summary, plain-language business owner explanations, technical comparison of depreciation methods, depreciation start/partial-period rules, acquisition/opening asset options, disposal options, GL mapping requirements, exact owner decision checklist, recommended path, and explicit "not implemented yet" declaration.
  - Recommended straight-line depreciation, useful life in months, optional salvage value defaulting to 0, depreciation starting in month after in-service date, opening asset registration without GL entries, and acquisition via Fixed Asset Clearing account (`fixed_asset_clearing`).
  - Preserved docs-only execution: 0 migrations, 0 models, 0 services, 0 routes, 0 UI components, 0 seeders, 0 commands, and 0 tests added. Status marked as `OWNER DECISION REQUIRED`.

## Roadmap & Next Phase

Phase 5 (Financial Statements & Period Close) is **100% COMPLETE AND VERIFIED**.
Phase 6 Slice 1 (Fixed Asset Policy Decision Pack) is **DOCS-ONLY COMPLETE** (Status: `OWNER DECISION REQUIRED`).
Phase 6 Slices 2-7 (Register, Capitalization, Depreciation Schedule Engine, Depreciation Run Posting, Fixed Asset Disposal, Reports/Export/Print/Close-Out) are **100% COMPLETE AND VERIFIED** after local review.

Phase 6 Prepared execution files:

- `PHASE_6_FIXED_ASSETS.md`
- `PHASE_6_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_6_SLICE_2_GEMINI_PROMPT.md` (Fixed Asset Register Foundation - COMPLETE)
- `PHASE_6_SLICE_3_GEMINI_PROMPT.md` (Capitalization and Opening Asset Posting - COMPLETE)
- `PHASE_6_SLICE_4_GEMINI_PROMPT.md` (Depreciation Schedule Engine - COMPLETE)
- `PHASE_6_SLICE_5_GEMINI_PROMPT.md` (Depreciation Run Posting - COMPLETE)
- `PHASE_6_SLICE_6_GEMINI_PROMPT.md` (Disposal, Sale, Scrap, and Reversal Workflow - COMPLETE)
- `PHASE_6_SLICE_7_GEMINI_PROMPT.md`

Next execution step: no Phase 6 implementation slice remains. Choose the next bounded phase/module with the owner before creating new prompts.

Phase 6 must preserve exact permissions, especially `fixedAssets.view`, `fixedAssets.create`, `fixedAssets.edit`, `fixedAssets.delete`, `fixedAssets.post`, `fixedAssets.reverse`, `fixedAssets.export`, `view_financials`, `reports.view`, `reports.export`, `reports.print`, and `accounting.mappings` where applicable. Frontend pages must not add hardcoded visible text or hardcoded team/tenant/company/branch assumptions.

Review gate for all future implementation slices:

- A scan is clean only when it prints zero matches; non-empty scans require a classification table and fixes for unacceptable matches.
- Verification may only be reported as passed after the command exits successfully. Do not accept background "will notify" or timer-based success claims.
- Backend messages shown in the UI must be dictionary/multilingual-code based, not raw English strings.
- Tests must use actual schema fields. Do not invent columns such as `financial_period.name` or `period_number` unless the slice explicitly adds them with a migration and tests.
- User-facing routes/actions must have matching permission-aware UI controls unless the route is intentionally internal-only and documented.

No required Phase 4 correction remains. Optional Production Deployment Readiness remains separate from Phase 5.

Explicitly NOT STARTED modules requiring bounded owner prompts:

- Payroll.
- Rentals.
- Fixed Assets is complete for Phase 6. Future fixed-asset extensions such as tax depreciation books, maintenance, insurance, barcode, transfers, custody, or warehouse/location semantics require separate owner-approved prompts.
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
- Physical year-end closing journal / Retained Earnings GL posting model. Slice 5 recommends Hybrid, but implementation still requires explicit owner approval of the account mapping, closing date rule, zeroing policy, reopen/reversal policy, and authorized roles.
- Fixed Asset policy decisions from `PHASE_6_SLICE_1_GEMINI_PROMPT.md`: depreciation method, start rule, partial-month convention, salvage/residual value rule, useful-life source, existing/opening asset handling, new asset capitalization policy, GL mappings, disposal policy, reversal policy, asset numbering/identity, and exact fixed-asset permissions.

## Verification Gate

Run from `laravel/` for every future implementation slice:

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
