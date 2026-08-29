# IMPLEMENTATION STATUS

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.

> **Branch-Capable Product Direction:** Owner direction on 2026-08-24 requires future support for multiple operational branches, branch transfers, and branch-aware workflows. This is not multi-tenancy. See `PRODUCT_EXTENSIBILITY_ROADMAP.md`.

- **Current phase:** Phase 18 - Product Acceptance, UI Polish, and Clean Code Gate is 100% COMPLETE (`PHASE_18_FINAL_VERIFICATION_REPORT.md`). Phase 17 is 100% COMPLETE (`PHASE_17_FINAL_VERIFICATION_REPORT.md`). Phase 16 is COMPLETE. Deployment remains parked.
- **Latest verified:** 2026-08-29, Phase 18 Slice 4 (Final Product Acceptance, UI Polish, and Clean-Code Close-Out) completed and verified. Created `PHASE_18_FINAL_VERIFICATION_REPORT.md`. All 16 Phase 18 tests passed (1264 assertions), 38 Security Hardening tests passed (969 assertions), 192 Phase 15 product hardening tests passed (26114 assertions), Concurrency suite passed (7 tests / 16 assertions), strict route authorization audit passed (457 routes, 0 failing), Pint passed, TypeScript typecheck passed (0 errors), Vite build passed, and git diff check passed.
- **Tests passing:** Total Phase 18 suite: 16 tests / 1264 assertions passed. Total Phase 16 suite: 95 tests / 944 assertions passed. Phase 15 product hardening suite: 192 tests / 26114 assertions passed. Attachment/Notification suites: 34 tests / 127 assertions passed. Security suite: 38 tests / 969 assertions passed. Concurrency testsuite: 7 tests / 16 assertions passed. Pint passed. TypeScript typecheck passed (0 errors).
- **Stress passing:** `concurrency:stress --workers=100`, `accounting:concurrency-stress --workers=50`, `accounting:allocation-concurrency-stress --workers=50`, `accounting:settlement-concurrency-stress --workers=50`, `accounting:cheque-concurrency-stress --workers=50`, `accounting:bank-reconciliation-concurrency-stress --workers=50`, `accounting:stock-transfer-stress --workers=50`, `accounting:inventory-concurrency-stress --workers=50`, `accounting:fixed-asset-depreciation-stress --workers=50`, `accounting:fixed-asset-disposal-stress --workers=50`, `accounting:phase3-stress --workers=20`, `accounting:phase3-integrity-check`, and `tokens:gc --batch=100`.
- **Frontend verification:** `npm run typecheck` passed (0 errors), `npm run build` passed cleanly with the existing Vite chunk-size warning only.
- **Remote/CI:** No GitHub Actions pipeline is connected for the Laravel migration track.
- **Latest verified code state:** local verification clean on Phase 18 complete (`PHASE_18_FINAL_VERIFICATION_REPORT.md`).
- **Handoff:** start with `CONTINUE_HERE.md`, then `NEXT_TASKS.md`.
- **Phase 18 prompts:** `PHASE_18_PRODUCT_ACCEPTANCE_UI_CLEAN_CODE.md`, Slice 1 COMPLETE (`PHASE_18_SLICE_1_REPORT.md`), Slice 2 COMPLETE (`PHASE_18_SLICE_2_REPORT.md`), Slice 3 COMPLETE (`PHASE_18_SLICE_3_REPORT.md`), Slice 4 COMPLETE (`PHASE_18_FINAL_VERIFICATION_REPORT.md`).
- **Next prepared track:** Owner/accountant hands-on acceptance review (`PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md`) or explicitly approved next product phase. Deployment remains parked.

## Legend

`COMPLETE` fully implemented + tested · `PARTIAL` partially implemented · `PLANNED` prompt/contract prepared but not implemented · `SCAFFOLD ONLY` structure without full business logic · `LEGACY_REFERENCE` old Next.js reference material only.

## Laravel Migration Track

| Item | Status | Notes |
|---|---|---|
| Phase 18 Slice 4 Final Acceptance & Clean-Code Close-Out | COMPLETE | Executed complete verification gate across migrations, Pint, `Phase18ProductAcceptanceTest` (16 tests / 1264 assertions), `SecurityHardeningTest` (38 tests / 969 assertions), `Phase15ProductHardeningTest` (192 tests / 26114 assertions), Concurrency suite, strict route authorization audit (457 routes, 0 failing), TypeScript typecheck (0 errors), Vite production build, git diff check, and source scans (anti-tenancy, unsafe UI controls, secrets). Created `PHASE_18_FINAL_VERIFICATION_REPORT.md` and synced documentation. Phase 18 is 100% COMPLETE. |
| Phase 18 Slice 3 Product Acceptance & Accountant Smoke Matrix | COMPLETE | Created bilingual `PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md` in repo root covering all 20 business, accounting, operational, and security areas with standard sign-off matrices. Added acceptance matrix validation and browserless route smoke tests covering 75+ representative Inertia endpoints across all ERP modules to `Phase18ProductAcceptanceTest` (16 tests / 1264 assertions total). Verified Pint, strict route audit (457 routes), and TypeScript typecheck (0 errors). Created `PHASE_18_SLICE_3_REPORT.md`. |
| Phase 18 Slice 2 Controller Clean-Code Boundary Gate | COMPLETE | Audited all 125 controllers under `app/Http/Controllers`: verified all <= 150 lines (max 110 lines), zero `DB::table(`, zero direct joins/aggregations, zero inline CSV loops, zero posting math helpers, and complete orchestration delegation. Verified service-authorized `AttachmentController` and `NotificationController` remain thin and session/entity authorized. Added 5 boundary gate test methods to `Phase18ProductAcceptanceTest` (13 tests / 245 assertions total). Verified Pint, `Phase15ProductHardeningTest` (192 tests / 26114 assertions), strict route authorization audit (457 routes), and TypeScript typecheck (0 errors). Created `PHASE_18_SLICE_2_REPORT.md`. |
| Phase 18 Slice 1 Safe Pagination & UI Safety | COMPLETE | Added safe HTML entity decoder `decodePaginationLabel` and reusable `PaginationControls` primitive in `Primitives.tsx`. Removed all `dangerouslySetInnerHTML` from `Projects/Index.tsx` and `CostCenters/Index.tsx`. Verified 0 `dangerouslySetInnerHTML` occurrences remain across all pages under `resources/js/Pages`. Added `Phase18ProductAcceptanceTest` (8 tests / 221 assertions), updated `Phase16Slice1ProjectCostCenterTest` (12 tests / 148 assertions), passed Pint, TypeScript typecheck (0 errors), and Vite build. Created `PHASE_18_SLICE_1_REPORT.md`. |
| Phase 17 Slice 6 Security Close-Out & Final Verification | COMPLETE | Executed complete verification gate across migrations, Pint, authentication, security hardening, attachments/notifications, Phase 15 product hardening, Phase 16 suites, Concurrency suite, strict route authorization audit (457 routes, 0 failing), TypeScript typecheck (0 errors), Vite production build, git diff check, and source scans (anti-tenancy, unsafe UI controls, legacy audit writer, raw secrets). Fixed Phase 16 budget test sensitive-action confirmation assertions. Created `PHASE_17_FINAL_VERIFICATION_REPORT.md` and synced documentation. |
| Phase 17 Slice 5 Attachment, Notification & Private Delivery Hardening | COMPLETE | Hardened `AttachmentService` filename/path sanitization (stripping traversal, control characters, null bytes), enforced extension allowlist and `EXTENSION_MIME_MAP` compatibility, preserved private local storage disk with disabled local serving, enforced `validateSafePath`, sanitized download `Content-Disposition` filenames with `nosniff`, wrapped deletion with Spatie Activitylog audit evidence, extracted `ListAttachmentRequest` and `StoreAttachmentRequest` FormRequests, and hardened `NotificationService` with user ID validation, type/targetRef normalization, user-scoped dedupe keys, and strict session-user identifier retrieval in `NotificationController`. Verified via `AttachmentAndNotificationTest` (21 tests / 75 assertions), `M9AttachmentsAndNotificationsTest` (13 tests / 52 assertions), `SecurityHardeningTest` (38 tests / 969 assertions), route audit, Pint, and TypeScript typecheck. |
| Phase 17 Slice 4 Sensitive Financial Action Confirmation | COMPLETE | Added centralized `SensitiveActionRegistry`, `RequireSensitiveActionConfirmation` middleware alias `sensitive.confirm`, and reusable `SensitiveActionModal`; protected 38 high-impact routes with exact `confirm_action` validation, required reason validation on 21 actions, and Spatie Activitylog `sensitive_action.confirmed` evidence. Verified via `SecurityHardeningTest` (36 tests / 958 assertions), `Phase15ProductHardeningTest` (192 tests / 26114 assertions), Concurrency suite, route audit, Pint, TypeScript typecheck, and Vite build. |
| Phase 17 Slice 3 Password Policy & Session Safety | COMPLETE | Centralized configurable password policy in `config/security.php` (`password_policy`), reusable `PasswordPolicyRules` builder with `Password` rules (no external network uncompromised checks), refactored user settings validation to `StoreUserRequest` and `UpdateUserRequest`, verified session regeneration on login and invalidation on logout, preserved existing inactive user state when update payload omits `is_active`, updated `.env.example`, documented in `spec/SECURITY.md` and `spec/ENVIRONMENT_CHECKLIST.md`, verified via `SecurityHardeningTest` (29 tests / 693 assertions) and `AuthenticationTest` (18 tests / 67 assertions). |
| Phase 17 Slice 2 Route Authorization Audit | COMPLETE | Created `security:route-audit` Artisan command (`--strict`, `--json`), `RouteAuthorizationAuditor` service with centralized service and public allowlists, fail-closed handling for unlisted public routes, and expanded `SecurityHardeningTest` (15 tests / 636 assertions). |
| Phase 17 Slice 1 Controlled Admin Privilege Guard | COMPLETE | Hardened `FirstUserSuperAdminSeeder` to fail-closed/disabled default, required exact confirmation phrase in production, audited via Spatie Activitylog. |


| M2 Inertia foundation | COMPLETE | Laravel app boots with Inertia/Vite and health/foundation routes. |
| Domain model correction | COMPLETE | Company/Branch/User ownership is not assumed; undefined relationships remain `UNDEFINED - DO NOT ASSUME`. |
| M3 database foundation | COMPLETE | Native `users`, company profile, standalone branch references, global fiscal years/periods, currency, number sequence, attachments, notifications, legacy audit archive, and non-team Spatie RBAC. |
| M5 session auth backend | COMPLETE | Login/logout, Argon2id, throttling, active users, session regeneration/invalidation, protected routes, bootstrap admin seeding. |
| M6 migrated Inertia pages | COMPLETE | Dashboard, settings hub, company, branches, numbering, users/roles, notifications, app shell, and notification read action backed by real Laravel data. |
| M7 Laravel core kernel parity | COMPLETE | Money, currency registry, accounting invariant, domain errors, number formatter/config, and Laravel invariant tests. |
| Phase 2 accounting core | COMPLETE | Account categories/types, chart of accounts, FX rates, fiscal periods, manual journals, posting engine, immutable ledger, reversal, opening balances, General Journal, General Ledger, Trial Balance, demo seeder, and accounting stress command. |
| M8 page actions | COMPLETE | Company/branch/numbering actions and role assign/revoke use explicit IDs, validation, permissions, optimistic locks where applicable, and no tenant/current-company session. |
| M9 attachments + notifications | COMPLETE | Attachment upload/download/list/delete service/routes, explicit allowlisted entity authorization, MIME/extension/size checks, storage cleanup compensation, UI panels, and user-targeted notification service with dedupe/list/mark-read behavior. |
| M10 audit + jobs/scheduler | COMPLETE | Spatie Activitylog is the active audit backend, legacy `audit_log` is retained as archive, activity/audit tables are append-only, `/audit-log` viewer exists, `tokens:gc --batch=100` is scheduled hourly, and jobs/failed_jobs baseline is verified. |
| Phase 3 Slices 1-10 Foundation | COMPLETE | Master Data, AR/AP Subledgers, Receipts/Payments, Allocation Engine, Cheques, Bank Reconciliation, Inertia Pages/UX, Operational Reports, Concurrency Stress/Integrity, Close-Out Report. |
| Phase 4 Slice 1 Catalog Foundation | COMPLETE | UnitOfMeasure, ProductCategory, Product models/migrations/services/controllers, Spatie Activitylog audit, attachment entity registry for product, Inertia catalog management pages, 12/12 passing feature tests. |
| Phase 4 Slice 2 Sales Order Backend | COMPLETE | `sales_order` and `sales_order_line`, `SalesOrderService` lifecycle, exact integer total calculation with overflow/fractional-minor rejection, `SO-YYYY-XXXXX` idempotent confirmation, Spatie Activitylog audit, `sales_order` attachment registry, Inertia page, and 15/15 passing feature tests. |
| Phase 4 Slice 3 Purchase Order Backend | COMPLETE | `purchase_order` and `purchase_order_line` models/migrations, `PurchaseOrderService` lifecycle (`draft` -> `submitted` -> `confirmed` / `cancelled`), exact integer total calculation (`intdiv` & `% 1000000`), number sequence allocation `PO-YYYY-XXXXX`, idempotent confirmation, Spatie Activitylog audit, attachment entity registry for `purchase_order`, Inertia purchase order management pages (`PurchaseOrders.tsx`), 16/16 passing feature tests. |
| Phase 4 Slice 4 Delivery Notes & Goods Receipts | COMPLETE | `delivery_note`, `delivery_note_line`, `goods_receipt`, `goods_receipt_line` models/migrations, `DeliveryNoteService` & `GoodsReceiptService` lifecycle, integer quantity validation (`quantity_e6`), cumulative over-fulfillment prevention with deterministic transaction locks, `DN-YYYY-XXXXX` and `GRN-YYYY-XXXXX` number allocation, Spatie Activitylog audit, attachment entity registry for `delivery_note` & `goods_receipt`, Inertia management pages (`DeliveryNotes.tsx`, `GoodsReceipts.tsx`), 17/17 passing feature tests. |
| Phase 4 Slice 5 Customer Invoice Posting | COMPLETE | `customer_invoice` and `customer_invoice_line`, lifecycle, strict Sales Order/Delivery Note source matching, exact integer totals, `INV-YYYY-XXXXX`, `sales_revenue` mapping, PostingEngine posting Dr AR / Cr Sales Revenue, `receivable_entry` debit, attachment registry, Inertia page, and 19/19 passing feature tests after local hardening. |
| Phase 4 Slice 6 Supplier Bill Posting | COMPLETE | `supplier_bill` and `supplier_bill_line`, lifecycle, strict Purchase Order/Goods Receipt source matching, exact integer totals, `BILL-YYYY-XXXXX`, `purchase_expense` mapping, PostingEngine posting Dr Purchase Expense / Cr AP Control, `payable_entry` credit, attachment registry, Inertia page, and 16/16 passing feature tests. |
| Phase 4 Slice 7 Inventory Costing Decision | COMPLETE | Created `PHASE_4_INVENTORY_COSTING_DECISION.md` comparing Weighted Average, FIFO, Standard Costing, and Non-Valued Stock Tracking. Owner selected Option 1: Moving Weighted Average Costing. |
| Phase 4 Slice 8 Moving Weighted Average Inventory Costing & Posting | COMPLETE | `stock_balance` and `stock_movement_ledger` tables/models, `MovingWeightedAverageInventoryService`, GL mappings (`inventory_asset`, `grni_clearing`, `cogs`), Goods Receipt stock receipt posting (Dr Inventory Asset / Cr GRNI Clearing), Delivery Note stock issue posting (Dr COGS / Cr Inventory Asset), Supplier Bill stock line GRNI clearing, Customer Invoice stock line DN matching, read-only Inertia stock balances page (`StockBalances.tsx`), PostgreSQL integrity constraints via `2026_08_22_090000_harden_phase4_slice8_inventory_integrity.php`, 14-test Slice 8 feature suite, and 50-iteration inventory integrity stress command. |
| Phase 4 Slice 9 Operational Reports & Returns Decision Pack | COMPLETE | 7 Query Services (`SalesOrderReportService`, `PurchaseOrderReportService`, `DeliveryNoteReportService`, `GoodsReceiptReportService`, `CustomerInvoiceReportService`, `SupplierBillReportService`, `StockMovementReportService`), 7 Controllers, 7 Inertia Pages, Reports Hub links, `PHASE_4_RETURNS_CREDIT_DEBIT_DECISION.md` decision pack, schema-aligned report numbers, and 7/7 passing feature tests (`Phase4Slice9OperationalReportsTest`, 85 assertions). |
| Phase 4 Slice 10 Sales Returns, Credit Notes & Operations Close-Out | COMPLETE | Implemented seven migrations, 7 domain services, manual AR/AP note settlement and reversal (`receivable_entry_settlement`, `payable_entry_settlement`), 38 feature tests passing. |
| Phase 5 Slice 1 Financial Statement Mapping Foundation | COMPLETE | Created `financial_statement_line` table and `account.financial_statement_line_id` FK, `FinancialStatementLine` model, default system statement lines seeder (11 lines), `FinancialStatementMappingService`, `FinancialStatementMappingController`, routes, Inertia React management page (`FinancialStatementMappings.tsx`), EN/AR translations, and 9/9 passing feature tests (`Phase5Slice1FinancialStatementMappingTest`). |
| Phase 5 Slice 2 Balance Sheet & Income Statement Core Generation | COMPLETE | Implemented `BalanceSheetReportService`, `IncomeStatementReportService`, `BalanceSheetReportController`, `IncomeStatementReportController`, CSV exports, routes under `reports.balance_sheet` and `reports.income_statement`, Inertia pages `BalanceSheet.tsx` & `IncomeStatement.tsx`, and Reports Hub cards. Local correction pass enforces report filtering by `ledger_entry.entry_date` instead of row `created_at`, shows unmapped warnings only for accounts with non-zero movement, requires `reports.view` + `view_financials` for viewing and `reports.export` + `view_financials` for CSV export, keeps new pages/nav text dictionary-backed, uses integer-safe frontend money formatting, and verifies 8/8 passing feature tests (`Phase5Slice2FinancialStatementsTest`, 54 assertions). |
| Phase 5 Slice 3 Cash Flow Statement Foundation | COMPLETE | Added `cash_flow_activity` column to `financial_statement_line` and `account`, plus PostgreSQL check constraints via `2026_08_23_011000_harden_phase5_slice3_cash_flow_activity_constraints.php`. `CashFlowReportService` classifies operating/investing/financing/unclassified cash movements from posted `ledger_entry.entry_date` records, excludes internal cash transfers, derives cash-equivalent accounts from `CashAccount` & `BankAccount`, returns structured warning codes for UI localization, and forbids assigning cash-flow activity directly to active cash/bank GL accounts. Added `CashFlowReportController`, `/reports/cash-flow` routes with `reports.view` + `view_financials` and `reports.export` + `view_financials`, `CashFlow.tsx`, mapping-page classification controls, and 9/9 passing feature tests (`Phase5Slice3CashFlowStatementTest`, 46 assertions). |
| Phase 5 Slice 4 Period Close Controls & Hardening | COMPLETE | Added period close metadata and PostgreSQL status constraint, `PeriodGuard`, `PeriodClosedException`, PostingEngine final safety net, close/reopen routes guarded by exact `close_period` / `reopen_period` permissions, close-readiness blockers, and Periods UI close/reopen controls. Local correction pass removed visible TSX English fallbacks, localized blocker statuses, included approved unposted invoices/bills/returns/notes as close blockers, locked date-resolved stock posting periods for Delivery Notes/Goods Receipts, allowed reopened cheque posting periods, and fixed a time-dependent Slice 10 settlement test. |
| Phase 5 Slice 5 Year-End Close Decision Pack | COMPLETE | `PHASE_5_YEAR_END_CLOSE_DECISION.md` is docs-only complete and marked `OWNER DECISION REQUIRED`. No migrations, models, services, routes, UI, seeders, jobs, or Retained Earnings closing journal engine were added. |
| Phase 5 Slice 6 UX, Export/Print & Close-Out | COMPLETE | Added permission-gated Print actions (`reports.print` + `view_financials`), `Phase5Slice6FinalCloseOutTest.php` (4/4 passing tests / 30 assertions after local schema-fixture correction), cleaned dictionary keys in `en.json` & `ar.json`, corrected Bank Reconciliation report journal number display to use `journal_entry.number`, ran full PHPUnit locally after review, and created `PHASE_5_FINAL_VERIFICATION_REPORT.md`. |
| Phase 5 Financial Statements & Period Close | COMPLETE | Slices 1-6 are 100% complete and verified: financial statement mapping, Balance Sheet, Income Statement, Cash Flow, Period Close controls, Year-End Close decision pack, and final UX/Export/Print close-out. |
| Phase 6 Slice 1 Fixed Asset Policy Decision Pack | COMPLETE | `PHASE_6_FIXED_ASSETS_POLICY_DECISION.md` is docs-only complete and marked `OWNER DECISION REQUIRED`. No migrations, models, services, routes, UI, seeders, commands, or tests were added. |
| Phase 6 Slice 2 Fixed Asset Register Foundation | COMPLETE | `fixed_asset_category` and `fixed_asset` tables/models created with PostgreSQL check constraints, 6 fixed asset GL mapping keys registered, attachment entity registered, `FixedAssetCategoryService` & `FixedAssetRegisterService`, controllers, routes, Inertia React pages (`Categories.tsx`, `Index.tsx`, `Create.tsx`, `Show.tsx`, `Edit.tsx`), EN/AR translations, navigation items in `AppLayout.tsx`, local review fixes for multilingual form payloads / hardcoded TSX text / workflow-owned activation / currency validation, and 9/9 passing feature tests (`Phase6Slice2FixedAssetRegisterTest`, 71 assertions). |
| Phase 6 Slice 3 Capitalization and Opening Asset Posting | COMPLETE | Capitalization metadata columns (`capitalization_mode`, `capitalization_date`, `journal_entry_id`, `capitalized_at`, `capitalized_by`) added via migration, `FixedAssetCapitalizationService` supporting `opening_already_capitalized` (0 GL entries) and `manual_capitalization` (PostingEngine Dr Asset Cost / Cr Fixed Asset Clearing), capitalization reversal (`ReversalService`), controller endpoints, web routes, Capitalize Modal in `Show.tsx`, dictionary translations, retry-safe row-lock/state-based capitalization idempotency, blocked non-draft capitalization/edit/update, blocked recapitalization with a different mode, machine-readable journal/memo descriptions, no hardcoded visible fixed-asset detail text, and 11/11 passing feature tests (`Phase6Slice3CapitalizationTest`, 64 assertions). |
| Phase 6 Slice 4 Depreciation Schedule Engine | COMPLETE | `fixed_asset_depreciation_schedule` table/model created with PostgreSQL check constraints plus DB immutability trigger migration `2026_08_23_051000_enforce_fixed_asset_depreciation_schedule_immutability.php`, deterministic straight-line integer minor-unit math, integer remainder allocation, month-after-in-service start policy, automatic fiscal year extension, active-asset-only idempotent schedule (re)generation, side-effect-free schedule reads, protection of posted lines, controller & routes, Inertia React preview table & (re)generate action in `Show.tsx`, dictionary translations, zero GL posting in this slice, and 13/13 passing feature tests (`Phase6Slice4DepreciationScheduleTest`, 64 assertions). |
| Phase 6 Slice 5 Depreciation Run Posting | COMPLETE | `fixed_asset_depreciation_run` table/model created with PostgreSQL check constraints `chk_fadr_status` and `chk_fadr_amounts`, `depreciation_run_id` FK added to schedule table, forward hardening migration `2026_08_23_061000_harden_fixed_asset_depreciation_schedule_run_link_immutability.php`, `FixedAssetDepreciationPostingService` with `PeriodGuard::assertPeriodOpenForPostingWithLock` enforcement, row locks, idempotency claim handling, balanced journal posting (Dr `depreciation_expense` / Cr `accumulated_depreciation`), reversal via `ReversalService` marking posted schedule rows `reversed` while preserving run/journal links, `FixedAssetDepreciationRunController`, web routes, Inertia React pages (`DepreciationRuns/Index.tsx`, `DepreciationRuns/Show.tsx`, `DepreciationRuns/Preview.tsx`), dictionary translations, navigation items, concurrency stress command `accounting:fixed-asset-depreciation-stress --workers=50`, and 10/10 passing feature tests (`Phase6Slice5DepreciationRunTest`, 44 assertions). |
| Phase 6 Slice 6 Fixed Asset Disposal | COMPLETE | `fixed_asset_disposal` table/model created with PostgreSQL check constraints `chk_fad_status`, `chk_fad_type`, `chk_fad_amounts`, `FixedAssetDisposalPostingService` supporting scrap/sale/retirement workflows, exact minor-unit GL posting (Credit asset cost, Debit accum dep, Debit/Credit disposal loss/gain, Debit clearing), disposal reversal via `ReversalService`, DB-level integrity hardening, web/UI workflows, concurrency stress command `accounting:fixed-asset-disposal-stress --workers=50`, and 15/15 passing feature tests (`Phase6Slice6FixedAssetDisposalTest`, 60 assertions). |
| Phase 6 Slice 7 Reports, UX, Export/Print & Close-Out | COMPLETE | Added `FixedAssetReportService`, strict report/export authorization, five report pages (`FixedAssetRegisterReport.tsx`, `FixedAssetNetBookValueReport.tsx`, `FixedAssetDepreciationReport.tsx`, `FixedAssetDepreciationRunReport.tsx`, `FixedAssetDisposalReport.tsx`), CSV exports preserving integer minor units, dictionary-backed EN/AR report UI, Reports Hub integration, source scans, and `Phase6Slice7FixedAssetReportsTest.php` (6/6 tests / 153 assertions). |
| Phase 6 Fixed Assets | COMPLETE | Phase 6 Slices 1 through 7 are 100% complete, fully tested (64/64 Phase 6 tests passed / 456 assertions), and locally verified on PostgreSQL. |
| Phase 7 Slice 1 Tax/VAT Policy Decision Pack | COMPLETE | `PHASE_7_TAX_VAT_POLICY_DECISION.md` created as docs-only policy decision pack detailing tax scope options, basis-points rate scale (`rate_bps`), tax calculation/rounding standards, sales/purchasing GL mapping entries, tax period filing controls, 15 owner decision items, and recommended path. Zero implementation code added. |
| Phase 7 Slice 2 Tax Foundation | COMPLETE | Tax code & tax rate schema/models, `TaxMasterDataService`, `TaxCalculationService`, Spatie Activitylog audit, controllers, Inertia pages, seeder, 7/7 passing tests. |
| Phase 7 Slice 3 Sales Output VAT Integration | COMPLETE | Migration `2026_08_23_090000_create_phase7_slice3_sales_tax_columns.php` added sales tax columns (`tax_amount_minor` on `customer_invoice`, `customer_credit_note`, `sales_return`; `tax_code_id`, `tax_rate_bps`, `tax_amount_minor`, `gross_amount_minor` on lines). Updated Eloquent models (`CustomerInvoice`, `CustomerInvoiceLine`, `CustomerCreditNoteLine`, `SalesReturnLine`) with integer tax casts and relations. `CustomerInvoiceService` calculates line tax amounts, computes exact draft totals, and posts balanced JVs (Dr `ar_control` for gross, Cr `sales_revenue` for net, Cr `output_tax_payable` for tax). `CustomerCreditNoteService` preserves linked invoice line tax snapshots and posts output VAT reversal (Dr `sales_returns` for net, Dr `output_tax_payable` for tax, Cr `ar_control` for gross). Updated `CustomerInvoiceController`, `CustomerCreditNoteController`, and `SalesReturnController` to pass active `taxCodes`. Updated Inertia views (`CustomerInvoices.tsx`, `CustomerCreditNotes.tsx`, `SalesReturns.tsx`). Concurrency stress command `accounting:sales-tax-stress` passed cleanly. Feature test suite `Phase7Slice3SalesOutputVatTest.php` (5/5 passing tests / 23 assertions). |
| Phase 7 Slice 4 Purchasing Input VAT | COMPLETE | Purchasing input tax migration (`2026_08_23_100000_create_phase7_slice4_purchasing_tax_columns.php`), `SupplierBillService` Dr `input_tax_receivable` posting JV, `SupplierAdjustmentNoteService` Cr `input_tax_receivable` reversal JV, `PurchaseReturnService` tax preservation, UI views & controllers, `PurchasingTaxPostingStressCommand`, 4/4 passing tests. |
| Phase 7 Slice 5 VAT Register, Reports & GL Reconciliation | COMPLETE | `VatRegisterReportService`, `VatSummaryReportService`, `VatToGlReconciliationService`, `VatReportController`, Inertia pages (`VatRegister.tsx`, `VatSummary.tsx`, `VatGlReconciliation.tsx`), CSV streaming exports, EN/AR translations with localized warning codes, `Phase7Slice5VatReportsTest.php` (9/9 passing tests / 40 assertions, 25/25 total Phase 7 tests). |
| Phase 7 Slice 6 Tax Period Filing & Locking Controls | COMPLETE | `tax_periods` and `tax_returns` schema/models, `TaxPeriodGuard`, `TaxPeriodService`, `TaxReturnService`, filing UI under `/taxes/periods`, filed-period blocking across all sales/purchasing tax-affecting posting paths, `TaxFilingStressCommand`, and 9/9 passing feature tests. |
| Phase 7 Slice 7 UX, Export/Print, Source Scans & Close-Out | COMPLETE | Created `PHASE_7_FINAL_VERIFICATION_REPORT.md`, completed source scan classifications, route/page/export/permission review, test hygiene corrections, full PHPUnit verification (253/253 tests), all stress commands, Pint, typecheck, and Vite build. |
| Phase 7 Tax / VAT | COMPLETE | Phase 7 Slices 1 through 7 are 100% complete and locally verified. See `PHASE_7_FINAL_VERIFICATION_REPORT.md`. |
| Phase 8 Slice 1 Operational Readiness Decision Pack | COMPLETE | Created `PHASE_8_OPERATIONAL_READINESS_DECISION.md` containing owner-facing operational readiness decision pack in English and Arabic covering current stack, required runtime services, environment variable guide, staging vs production checklist, and 9 pending owner operational decisions. Docs-only slice. |
| Phase 8 Operational Readiness & E2E Smoke | COMPLETE | Safe prompt files prepared, Laravel deployment docs refreshed, scheduler/queue/health readiness tests added, Inertia route smoke foundation added, VAT GL date-filter bug fixed, and `PHASE_8_FINAL_OPERATIONAL_READINESS_REPORT.md` created. No business module or tenant/company/branch scope was introduced. |
| Phase 9 Slice 1 Cutover Decision Pack | COMPLETE | Created `PHASE_9_CUTOVER_DECISION_PACK.md` containing owner/operator decision pack for staging and production cutover in English and Arabic. Documents staging vs production cutover definitions, technology stack, deployment responsibilities by role, 12-item pending owner/operator decision matrix, go/no-go criteria, rollback approval process, and minimum smoke acceptance criteria. Docs-only slice. |
| Phase 9 Slice 2 Environment & Secrets Checklist | COMPLETE | Created `spec/ENVIRONMENT_CHECKLIST.md` documenting environment variable rules, purpose, required environments, value categories, operator notes, and validation methods across 35 configuration variables. Audited `laravel/.env.example` adding `DB_SSLMODE` and `MAIL_ENCRYPTION` template comments. Refreshed `spec/DEPLOYMENT.md`. |
| Phase 9 Slice 3 Deployment & Rollback Runbooks | COMPLETE | Created `spec/DEPLOYMENT_RUNBOOK.md` detailing a 12-step provider-neutral deployment workflow and `spec/ROLLBACK_RUNBOOK.md` detailing rollback conditions, approval authority, code/asset/migration rollback policies, DB restore escalation path, and post-mortem incident audit. Refreshed `spec/DEPLOYMENT.md`. Runbook slice. |
| Phase 9 Slice 4 Backup & Restore Drill Pack | COMPLETE | Created `spec/BACKUP_RESTORE_DRILL.md` detailing PostgreSQL backup objectives, frequency/retention policy options, staging restore drill workflow with placeholder commands, post-restore verification suite (`migrate:status`, `Phase8` tests, `accounting:phase3-integrity-check`, `tokens:gc --batch=100`, `/health`), production restore approval protocol, and restore drill log template. Refreshed `spec/DEPLOYMENT.md`. Operations doc slice. |
| Phase 9 Slice 5 Runtime Processes, Storage, Mail & Logs | COMPLETE | Created `spec/RUNTIME_PROCESSES.md` detailing provider-neutral runtime operations for Artisan Scheduler external cron trigger requirement, queue worker supervision (Supervisor & systemd templates) & restart policy, `failed_jobs` inspection/retry, `tokens:gc --batch=100` schedule, attachment storage configuration, mail transport modes, log rotation/retention, `/health` endpoint, and 5-point post-restart operator checklist. Refreshed `spec/DEPLOYMENT.md`. Operations doc slice. |
| Phase 9 Slice 6 Go-Live Smoke, Security Checklist & Acceptance Gate | COMPLETE | Created `spec/GO_LIVE_ACCEPTANCE.md` covering pre-go-live approvals, non-secret environment sanity checks, login/dashboard/report/tax smoke, attachment/notification checks, permission/security checks, scheduler/queue readiness, backup/restore evidence, and formal go/no-go sign-off. |
| Phase 9 Slice 7 Final Cutover Close-Out | COMPLETE | Created `PHASE_9_FINAL_CUTOVER_REPORT.md`, updated handoff docs, ran the required Laravel verification suite, and classified source-scan matches. |
| Phase 9 Staging / Production Cutover Pack | COMPLETE | Slices 1-7 are complete and verified. Covers cutover decision pack, environment/secrets checklist, deployment and rollback runbooks, backup/restore drill pack, runtime processes, go-live smoke/security acceptance, and final close-out. |
| Product Extensibility Roadmap | COMPLETE | Owner direction recorded for multi-branch operations, branch transfers, warehouse/location readiness, branch-aware sales/purchasing/cash/fixed-asset/reporting workflows, and no multi-tenant semantics. Warehouse/location foundation is implemented. See `PRODUCT_EXTENSIBILITY_ROADMAP.md`. |
| Phase 10 Branch, Warehouse, and Stock Transfer Foundation | COMPLETE | Implemented warehouses, stock locations, warehouse-aware stock balances/movements, stock transfer lifecycle, warehouse UI/report filters, RBAC updates, attachment registry support, PostgreSQL stock transfer stress command, and verification report `PHASE_10_FINAL_REPORT.md`. |
| Phase 10 Stock Count, Adjustment, and Warehouse Document Selectors | COMPLETE | Implemented `stock_count`, `stock_count_line`, `stock_adjustment`, `stock_adjustment_line`, stock count variance posting through generated stock adjustments, inventory adjustment gain/loss mappings, Inertia pages for counts/adjustments, warehouse selectors on Delivery Notes, Goods Receipts, Sales Returns, and Purchase Returns, warehouse filters on Delivery/Goods reports, and verification report `PHASE_10_OPERATIONAL_COMPLETION_REPORT.md`. |
| Security Hardening Pass | COMPLETE | Added baseline security headers, active-user request recheck, route-level authorization coverage, sensitive `taxes.file` capability, private local storage direct-serve lock (`FILESYSTEM_LOCAL_SERVE=false`), and regression test report `SECURITY_HARDENING_REPORT.md`. |
| Stabilization / QA Gate | COMPLETE | Added `qa:verify-local`, corrected Phase 10 PostgreSQL UUID test fixtures, extracted repeated inventory page option queries, removed hardcoded Delivery/Goods report UI text into dictionaries, and created `STABILIZATION_HARDENING_REPORT.md`. |
| Phase 10 Treasury Transfer and Branch Cash/Bank Extension | COMPLETE | Added optional operational `branch_id` references to `cash_account` and `bank_account`, branch-aware cash/bank UI filters/selectors, `treasury_transfer` table/model/service/controller/routes/page, PostingEngine-backed internal transfer posting (Dr destination cash/bank linked GL, Cr source linked GL), and verification report `PHASE_10_TREASURY_TRANSFER_REPORT.md`. |
| Phase 10 Fixed Asset Location and Movement History | COMPLETE | Added `fixed_asset_location`, current `fixed_asset.branch_id` and `fixed_asset.fixed_asset_location_id`, append-only `fixed_asset_movement`, `fixedAssets.transfer` permission, location CRUD page, branch/location filters and movement modal/history on fixed assets, and verification report `PHASE_10_FIXED_ASSET_MOVEMENT_REPORT.md`. |
| Phase 10 Branch Operational Reports | COMPLETE | Added `/reports/branch-operations`, `BranchOperationalReportService`, read-only branch coverage dashboard for warehouses, stock, cash/bank, fixed assets, asset movements, and posted treasury transfers, readiness checks for unassigned operational records, mixed-currency warning, and verification report `PHASE_10_BRANCH_OPERATIONAL_REPORTS_REPORT.md`. |
| Phase 10 GL Branch Dimension and Branch Profitability | COMPLETE | Added optional operational `branch_id` to `journal_entry`, `journal_line`, and immutable `ledger_entry`; updated journal draft/posting/reversal, treasury transfer posting, and inventory-generated journals to preserve branch context; added branch-filtered General Ledger, `/reports/branch-profitability`, protected CSV export, and permission-aware print/export actions; verified via `PHASE_10_GL_BRANCH_PROFITABILITY_REPORT.md`. |
| M8 page actions | COMPLETE | Company/branch/numbering actions and role assign/revoke use explicit IDs, validation, permissions, optimistic locks where applicable, and no tenant/current-company session. |
| M9 attachments + notifications | COMPLETE | Attachment upload/download/list/delete service/routes, explicit allowlisted entity authorization, MIME/extension/size checks, storage cleanup compensation, UI panels, and user-targeted notification service with dedupe/list/mark-read behavior. |
| M10 audit + jobs/scheduler | COMPLETE | Spatie Activitylog is the active audit backend, legacy `audit_log` is retained as archive, activity/audit tables are append-only, `/audit-log` viewer exists, `tokens:gc --batch=100` is scheduled hourly, and jobs/failed_jobs baseline is verified. |
| Phase 3 Slices 1-10 Foundation | COMPLETE | Master Data, AR/AP Subledgers, Receipts/Payments, Allocation Engine, Cheques, Bank Reconciliation, Inertia Pages/UX, Operational Reports, Concurrency Stress/Integrity, Close-Out Report. |
| Phase 4 Slice 1 Catalog Foundation | COMPLETE | UnitOfMeasure, ProductCategory, Product models/migrations/services/controllers, Spatie Activitylog audit, attachment entity registry for product, Inertia catalog management pages, 12/12 passing feature tests. |
| Phase 4 Slice 2 Sales Order Backend | COMPLETE | `sales_order` and `sales_order_line`, `SalesOrderService` lifecycle, exact integer total calculation with overflow/fractional-minor rejection, `SO-YYYY-XXXXX` idempotent confirmation, Spatie Activitylog audit, `sales_order` attachment registry, Inertia page, and 15/15 passing feature tests. |
| Phase 4 Slice 3 Purchase Order Backend | COMPLETE | `purchase_order` and `purchase_order_line` models/migrations, `PurchaseOrderService` lifecycle (`draft` -> `submitted` -> `confirmed` / `cancelled`), exact integer total calculation (`intdiv` & `% 1000000`), number sequence allocation `PO-YYYY-XXXXX`, idempotent confirmation, Spatie Activitylog audit, attachment entity registry for `purchase_order`, Inertia purchase order management pages (`PurchaseOrders.tsx`), 16/16 passing feature tests. |
| Phase 4 Slice 4 Delivery Notes & Goods Receipts | COMPLETE | `delivery_note`, `delivery_note_line`, `goods_receipt`, `goods_receipt_line` models/migrations, `DeliveryNoteService` & `GoodsReceiptService` lifecycle, integer quantity validation (`quantity_e6`), cumulative over-fulfillment prevention with deterministic transaction locks, `DN-YYYY-XXXXX` and `GRN-YYYY-XXXXX` number allocation, Spatie Activitylog audit, attachment entity registry for `delivery_note` & `goods_receipt`, Inertia management pages (`DeliveryNotes.tsx`, `GoodsReceipts.tsx`), 17/17 passing feature tests. |
| Phase 4 Slice 5 Customer Invoice Posting | COMPLETE | `customer_invoice` and `customer_invoice_line`, lifecycle, strict Sales Order/Delivery Note source matching, exact integer totals, `INV-YYYY-XXXXX`, `sales_revenue` mapping, PostingEngine posting Dr AR / Cr Sales Revenue, `receivable_entry` debit, attachment registry, Inertia page, and 19/19 passing feature tests after local hardening. |
| Phase 4 Slice 6 Supplier Bill Posting | COMPLETE | `supplier_bill` and `supplier_bill_line`, lifecycle, strict Purchase Order/Goods Receipt source matching, exact integer totals, `BILL-YYYY-XXXXX`, `purchase_expense` mapping, PostingEngine posting Dr Purchase Expense / Cr AP Control, `payable_entry` credit, attachment registry, Inertia page, and 16/16 passing feature tests. |
| Phase 4 Slice 7 Inventory Costing Decision | COMPLETE | Created `PHASE_4_INVENTORY_COSTING_DECISION.md` comparing Weighted Average, FIFO, Standard Costing, and Non-Valued Stock Tracking. Owner selected Option 1: Moving Weighted Average Costing. |
| Phase 4 Slice 8 Moving Weighted Average Inventory Costing & Posting | COMPLETE | `stock_balance` and `stock_movement_ledger` tables/models, `MovingWeightedAverageInventoryService`, GL mappings (`inventory_asset`, `grni_clearing`, `cogs`), Goods Receipt stock receipt posting (Dr Inventory Asset / Cr GRNI Clearing), Delivery Note stock issue posting (Dr COGS / Cr Inventory Asset), Supplier Bill stock line GRNI clearing, Customer Invoice stock line DN matching, read-only Inertia stock balances page (`StockBalances.tsx`), PostgreSQL integrity constraints via `2026_08_22_090000_harden_phase4_slice8_inventory_integrity.php`, 14-test Slice 8 feature suite, and 50-iteration inventory integrity stress command. |
| Phase 4 Slice 9 Operational Reports & Returns Decision Pack | COMPLETE | 7 Query Services (`SalesOrderReportService`, `PurchaseOrderReportService`, `DeliveryNoteReportService`, `GoodsReceiptReportService`, `CustomerInvoiceReportService`, `SupplierBillReportService`, `StockMovementReportService`), 7 Controllers, 7 Inertia Pages, Reports Hub links, `PHASE_4_RETURNS_CREDIT_DEBIT_DECISION.md` decision pack, schema-aligned report numbers, and 7/7 passing feature tests (`Phase4Slice9OperationalReportsTest`, 85 assertions). |
| Phase 4 Slice 10 Sales Returns, Credit Notes & Operations Close-Out | COMPLETE | Implemented seven migrations, 7 domain services, manual AR/AP note settlement and reversal (`receivable_entry_settlement`, `payable_entry_settlement`), 38 feature tests passing. |
| Phase 5 Slice 1 Financial Statement Mapping Foundation | COMPLETE | Created `financial_statement_line` table and `account.financial_statement_line_id` FK, `FinancialStatementLine` model, default system statement lines seeder (11 lines), `FinancialStatementMappingService`, `FinancialStatementMappingController`, routes, Inertia React management page (`FinancialStatementMappings.tsx`), EN/AR translations, and 9/9 passing feature tests (`Phase5Slice1FinancialStatementMappingTest`). |
| Phase 5 Slice 2 Balance Sheet & Income Statement Core Generation | COMPLETE | Implemented `BalanceSheetReportService`, `IncomeStatementReportService`, `BalanceSheetReportController`, `IncomeStatementReportController`, CSV exports, routes under `reports.balance_sheet` and `reports.income_statement`, Inertia pages `BalanceSheet.tsx` & `IncomeStatement.tsx`, and Reports Hub cards. Local correction pass enforces report filtering by `ledger_entry.entry_date` instead of row `created_at`, shows unmapped warnings only for accounts with non-zero movement, requires `reports.view` + `view_financials` for viewing and `reports.export` + `view_financials` for CSV export, keeps new pages/nav text dictionary-backed, uses integer-safe frontend money formatting, and verifies 8/8 passing feature tests (`Phase5Slice2FinancialStatementsTest`, 54 assertions). |
| Phase 5 Slice 3 Cash Flow Statement Foundation | COMPLETE | Added `cash_flow_activity` column to `financial_statement_line` and `account`, plus PostgreSQL check constraints via `2026_08_23_011000_harden_phase5_slice3_cash_flow_activity_constraints.php`. `CashFlowReportService` classifies operating/investing/financing/unclassified cash movements from posted `ledger_entry.entry_date` records, excludes internal cash transfers, derives cash-equivalent accounts from `CashAccount` & `BankAccount`, returns structured warning codes for UI localization, and forbids assigning cash-flow activity directly to active cash/bank GL accounts. Added `CashFlowReportController`, `/reports/cash-flow` routes with `reports.view` + `view_financials` and `reports.export` + `view_financials`, `CashFlow.tsx`, mapping-page classification controls, and 9/9 passing feature tests (`Phase5Slice3CashFlowStatementTest`, 46 assertions). |
| Phase 5 Slice 4 Period Close Controls & Hardening | COMPLETE | Added period close metadata and PostgreSQL status constraint, `PeriodGuard`, `PeriodClosedException`, PostingEngine final safety net, close/reopen routes guarded by exact `close_period` / `reopen_period` permissions, close-readiness blockers, and Periods UI close/reopen controls. Local correction pass removed visible TSX English fallbacks, localized blocker statuses, included approved unposted invoices/bills/returns/notes as close blockers, locked date-resolved stock posting periods for Delivery Notes/Goods Receipts, allowed reopened cheque posting periods, and fixed a time-dependent Slice 10 settlement test. |
| Phase 5 Slice 5 Year-End Close Decision Pack | COMPLETE | `PHASE_5_YEAR_END_CLOSE_DECISION.md` is docs-only complete and marked `OWNER DECISION REQUIRED`. No migrations, models, services, routes, UI, seeders, jobs, or Retained Earnings closing journal engine were added. |
| Phase 5 Slice 6 UX, Export/Print & Close-Out | COMPLETE | Added permission-gated Print actions (`reports.print` + `view_financials`), `Phase5Slice6FinalCloseOutTest.php` (4/4 passing tests / 30 assertions after local schema-fixture correction), cleaned dictionary keys in `en.json` & `ar.json`, corrected Bank Reconciliation report journal number display to use `journal_entry.number`, ran full PHPUnit locally after review, and created `PHASE_5_FINAL_VERIFICATION_REPORT.md`. |
| Phase 5 Financial Statements & Period Close | COMPLETE | Slices 1-6 are 100% complete and verified: financial statement mapping, Balance Sheet, Income Statement, Cash Flow, Period Close controls, Year-End Close decision pack, and final UX/Export/Print close-out. |
| Phase 6 Slice 1 Fixed Asset Policy Decision Pack | COMPLETE | `PHASE_6_FIXED_ASSETS_POLICY_DECISION.md` is docs-only complete and marked `OWNER DECISION REQUIRED`. No migrations, models, services, routes, UI, seeders, commands, or tests were added. |
| Phase 6 Slice 2 Fixed Asset Register Foundation | COMPLETE | `fixed_asset_category` and `fixed_asset` tables/models created with PostgreSQL check constraints, 6 fixed asset GL mapping keys registered, attachment entity registered, `FixedAssetCategoryService` & `FixedAssetRegisterService`, controllers, routes, Inertia React pages (`Categories.tsx`, `Index.tsx`, `Create.tsx`, `Show.tsx`, `Edit.tsx`), EN/AR translations, navigation items in `AppLayout.tsx`, local review fixes for multilingual form payloads / hardcoded TSX text / workflow-owned activation / currency validation, and 9/9 passing feature tests (`Phase6Slice2FixedAssetRegisterTest`, 71 assertions). |
| Phase 6 Slice 3 Capitalization and Opening Asset Posting | COMPLETE | Capitalization metadata columns (`capitalization_mode`, `capitalization_date`, `journal_entry_id`, `capitalized_at`, `capitalized_by`) added via migration, `FixedAssetCapitalizationService` supporting `opening_already_capitalized` (0 GL entries) and `manual_capitalization` (PostingEngine Dr Asset Cost / Cr Fixed Asset Clearing), capitalization reversal (`ReversalService`), controller endpoints, web routes, Capitalize Modal in `Show.tsx`, dictionary translations, retry-safe row-lock/state-based capitalization idempotency, blocked non-draft capitalization/edit/update, blocked recapitalization with a different mode, machine-readable journal/memo descriptions, no hardcoded visible fixed-asset detail text, and 11/11 passing feature tests (`Phase6Slice3CapitalizationTest`, 64 assertions). |
| Phase 6 Slice 4 Depreciation Schedule Engine | COMPLETE | `fixed_asset_depreciation_schedule` table/model created with PostgreSQL check constraints plus DB immutability trigger migration `2026_08_23_051000_enforce_fixed_asset_depreciation_schedule_immutability.php`, deterministic straight-line integer minor-unit math, integer remainder allocation, month-after-in-service start policy, automatic fiscal year extension, active-asset-only idempotent schedule (re)generation, side-effect-free schedule reads, protection of posted lines, controller & routes, Inertia React preview table & (re)generate action in `Show.tsx`, dictionary translations, zero GL posting in this slice, and 13/13 passing feature tests (`Phase6Slice4DepreciationScheduleTest`, 64 assertions). |
| Phase 6 Slice 5 Depreciation Run Posting | COMPLETE | `fixed_asset_depreciation_run` table/model created with PostgreSQL check constraints `chk_fadr_status` and `chk_fadr_amounts`, `depreciation_run_id` FK added to schedule table, forward hardening migration `2026_08_23_061000_harden_fixed_asset_depreciation_schedule_run_link_immutability.php`, `FixedAssetDepreciationPostingService` with `PeriodGuard::assertPeriodOpenForPostingWithLock` enforcement, row locks, idempotency claim handling, balanced journal posting (Dr `depreciation_expense` / Cr `accumulated_depreciation`), reversal via `ReversalService` marking posted schedule rows `reversed` while preserving run/journal links, `FixedAssetDepreciationRunController`, web routes, Inertia React pages (`DepreciationRuns/Index.tsx`, `DepreciationRuns/Show.tsx`, `DepreciationRuns/Preview.tsx`), dictionary translations, navigation items, concurrency stress command `accounting:fixed-asset-depreciation-stress --workers=50`, and 10/10 passing feature tests (`Phase6Slice5DepreciationRunTest`, 44 assertions). |
| Phase 6 Slice 6 Fixed Asset Disposal | COMPLETE | `fixed_asset_disposal` table/model created with PostgreSQL check constraints `chk_fad_status`, `chk_fad_type`, `chk_fad_amounts`, `FixedAssetDisposalPostingService` supporting scrap/sale/retirement workflows, exact minor-unit GL posting (Credit asset cost, Debit accum dep, Debit/Credit disposal loss/gain, Debit clearing), disposal reversal via `ReversalService`, DB-level integrity hardening, web/UI workflows, concurrency stress command `accounting:fixed-asset-disposal-stress --workers=50`, and 15/15 passing feature tests (`Phase6Slice6FixedAssetDisposalTest`, 60 assertions). |
| Phase 6 Slice 7 Reports, UX, Export/Print & Close-Out | COMPLETE | Added `FixedAssetReportService`, strict report/export authorization, five report pages (`FixedAssetRegisterReport.tsx`, `FixedAssetNetBookValueReport.tsx`, `FixedAssetDepreciationReport.tsx`, `FixedAssetDepreciationRunReport.tsx`, `FixedAssetDisposalReport.tsx`), CSV exports preserving integer minor units, dictionary-backed EN/AR report UI, Reports Hub integration, source scans, and `Phase6Slice7FixedAssetReportsTest.php` (6/6 tests / 153 assertions). |
| Phase 6 Fixed Assets | COMPLETE | Phase 6 Slices 1 through 7 are 100% complete, fully tested (64/64 Phase 6 tests passed / 456 assertions), and locally verified on PostgreSQL. |
| Phase 7 Slice 1 Tax/VAT Policy Decision Pack | COMPLETE | `PHASE_7_TAX_VAT_POLICY_DECISION.md` created as docs-only policy decision pack detailing tax scope options, basis-points rate scale (`rate_bps`), tax calculation/rounding standards, sales/purchasing GL mapping entries, tax period filing controls, 15 owner decision items, and recommended path. Zero implementation code added. |
| Phase 7 Slice 2 Tax Foundation | COMPLETE | Tax code & tax rate schema/models, `TaxMasterDataService`, `TaxCalculationService`, Spatie Activitylog audit, controllers, Inertia pages, seeder, 7/7 passing tests. |
| Phase 7 Slice 3 Sales Output VAT Integration | COMPLETE | Migration `2026_08_23_090000_create_phase7_slice3_sales_tax_columns.php` added sales tax columns (`tax_amount_minor` on `customer_invoice`, `customer_credit_note`, `sales_return`; `tax_code_id`, `tax_rate_bps`, `tax_amount_minor`, `gross_amount_minor` on lines). Updated Eloquent models (`CustomerInvoice`, `CustomerInvoiceLine`, `CustomerCreditNoteLine`, `SalesReturnLine`) with integer tax casts and relations. `CustomerInvoiceService` calculates line tax amounts, computes exact draft totals, and posts balanced JVs (Dr `ar_control` for gross, Cr `sales_revenue` for net, Cr `output_tax_payable` for tax). `CustomerCreditNoteService` preserves linked invoice line tax snapshots and posts output VAT reversal (Dr `sales_returns` for net, Dr `output_tax_payable` for tax, Cr `ar_control` for gross). Updated `CustomerInvoiceController`, `CustomerCreditNoteController`, and `SalesReturnController` to pass active `taxCodes`. Updated Inertia views (`CustomerInvoices.tsx`, `CustomerCreditNotes.tsx`, `SalesReturns.tsx`). Concurrency stress command `accounting:sales-tax-stress` passed cleanly. Feature test suite `Phase7Slice3SalesOutputVatTest.php` (5/5 passing tests / 23 assertions). |
| Phase 7 Slice 4 Purchasing Input VAT | COMPLETE | Purchasing input tax migration (`2026_08_23_100000_create_phase7_slice4_purchasing_tax_columns.php`), `SupplierBillService` Dr `input_tax_receivable` posting JV, `SupplierAdjustmentNoteService` Cr `input_tax_receivable` reversal JV, `PurchaseReturnService` tax preservation, UI views & controllers, `PurchasingTaxPostingStressCommand`, 4/4 passing tests. |
| Phase 7 Slice 5 VAT Register, Reports & GL Reconciliation | COMPLETE | `VatRegisterReportService`, `VatSummaryReportService`, `VatToGlReconciliationService`, `VatReportController`, Inertia pages (`VatRegister.tsx`, `VatSummary.tsx`, `VatGlReconciliation.tsx`), CSV streaming exports, EN/AR translations with localized warning codes, `Phase7Slice5VatReportsTest.php` (9/9 passing tests / 40 assertions, 25/25 total Phase 7 tests). |
| Phase 7 Slice 6 Tax Period Filing & Locking Controls | COMPLETE | `tax_periods` and `tax_returns` schema/models, `TaxPeriodGuard`, `TaxPeriodService`, `TaxReturnService`, filing UI under `/taxes/periods`, filed-period blocking across all sales/purchasing tax-affecting posting paths, `TaxFilingStressCommand`, and 9/9 passing feature tests. |
| Phase 7 Slice 7 UX, Export/Print, Source Scans & Close-Out | COMPLETE | Created `PHASE_7_FINAL_VERIFICATION_REPORT.md`, completed source scan classifications, route/page/export/permission review, test hygiene corrections, full PHPUnit verification (253/253 tests), all stress commands, Pint, typecheck, and Vite build. |
| Phase 7 Tax / VAT | COMPLETE | Phase 7 Slices 1 through 7 are 100% complete and locally verified. See `PHASE_7_FINAL_VERIFICATION_REPORT.md`. |
| Phase 8 Slice 1 Operational Readiness Decision Pack | COMPLETE | Created `PHASE_8_OPERATIONAL_READINESS_DECISION.md` containing owner-facing operational readiness decision pack in English and Arabic covering current stack, required runtime services, environment variable guide, staging vs production checklist, and 9 pending owner operational decisions. Docs-only slice. |
| Phase 8 Operational Readiness & E2E Smoke | COMPLETE | Safe prompt files prepared, Laravel deployment docs refreshed, scheduler/queue/health readiness tests added, Inertia route smoke foundation added, VAT GL date-filter bug fixed, and `PHASE_8_FINAL_OPERATIONAL_READINESS_REPORT.md` created. No business module or tenant/company/branch scope was introduced. |
| Phase 9 Slice 1 Cutover Decision Pack | COMPLETE | Created `PHASE_9_CUTOVER_DECISION_PACK.md` containing owner/operator decision pack for staging and production cutover in English and Arabic. Documents staging vs production cutover definitions, technology stack, deployment responsibilities by role, 12-item pending owner/operator decision matrix, go/no-go criteria, rollback approval process, and minimum smoke acceptance criteria. Docs-only slice. |
| Phase 9 Slice 2 Environment & Secrets Checklist | COMPLETE | Created `spec/ENVIRONMENT_CHECKLIST.md` documenting environment variable rules, purpose, required environments, value categories, operator notes, and validation methods across 35 configuration variables. Audited `laravel/.env.example` adding `DB_SSLMODE` and `MAIL_ENCRYPTION` template comments. Refreshed `spec/DEPLOYMENT.md`. |
| Phase 9 Slice 3 Deployment & Rollback Runbooks | COMPLETE | Created `spec/DEPLOYMENT_RUNBOOK.md` detailing a 12-step provider-neutral deployment workflow and `spec/ROLLBACK_RUNBOOK.md` detailing rollback conditions, approval authority, code/asset/migration rollback policies, DB restore escalation path, and post-mortem incident audit. Refreshed `spec/DEPLOYMENT.md`. Runbook slice. |
| Phase 9 Slice 4 Backup & Restore Drill Pack | COMPLETE | Created `spec/BACKUP_RESTORE_DRILL.md` detailing PostgreSQL backup objectives, frequency/retention policy options, staging restore drill workflow with placeholder commands, post-restore verification suite (`migrate:status`, `Phase8` tests, `accounting:phase3-integrity-check`, `tokens:gc --batch=100`, `/health`), production restore approval protocol, and restore drill log template. Refreshed `spec/DEPLOYMENT.md`. Operations doc slice. |
| Phase 9 Slice 5 Runtime Processes, Storage, Mail & Logs | COMPLETE | Created `spec/RUNTIME_PROCESSES.md` detailing provider-neutral runtime operations for Artisan Scheduler external cron trigger requirement, queue worker supervision (Supervisor & systemd templates) & restart policy, `failed_jobs` inspection/retry, `tokens:gc --batch=100` schedule, attachment storage configuration, mail transport modes, log rotation/retention, `/health` endpoint, and 5-point post-restart operator checklist. Refreshed `spec/DEPLOYMENT.md`. Operations doc slice. |
| Phase 9 Slice 6 Go-Live Smoke, Security Checklist & Acceptance Gate | COMPLETE | Created `spec/GO_LIVE_ACCEPTANCE.md` covering pre-go-live approvals, non-secret environment sanity checks, login/dashboard/report/tax smoke, attachment/notification checks, permission/security checks, scheduler/queue readiness, backup/restore evidence, and formal go/no-go sign-off. |
| Phase 9 Slice 7 Final Cutover Close-Out | COMPLETE | Created `PHASE_9_FINAL_CUTOVER_REPORT.md`, updated handoff docs, ran the required Laravel verification suite, and classified source-scan matches. |
| Phase 9 Staging / Production Cutover Pack | COMPLETE | Slices 1-7 are complete and verified. Covers cutover decision pack, environment/secrets checklist, deployment and rollback runbooks, backup/restore drill pack, runtime processes, go-live smoke/security acceptance, and final close-out. |
| Product Extensibility Roadmap | COMPLETE | Owner direction recorded for multi-branch operations, branch transfers, warehouse/location readiness, branch-aware sales/purchasing/cash/fixed-asset/reporting workflows, and no multi-tenant semantics. Warehouse/location foundation is implemented. See `PRODUCT_EXTENSIBILITY_ROADMAP.md`. |
| Phase 10 Branch, Warehouse, and Stock Transfer Foundation | COMPLETE | Implemented warehouses, stock locations, warehouse-aware stock balances/movements, stock transfer lifecycle, warehouse UI/report filters, RBAC updates, attachment registry support, PostgreSQL stock transfer stress command, and verification report `PHASE_10_FINAL_REPORT.md`. |
| Phase 10 Stock Count, Adjustment, and Warehouse Document Selectors | COMPLETE | Implemented `stock_count`, `stock_count_line`, `stock_adjustment`, `stock_adjustment_line`, stock count variance posting through generated stock adjustments, inventory adjustment gain/loss mappings, Inertia pages for counts/adjustments, warehouse selectors on Delivery Notes, Goods Receipts, Sales Returns, and Purchase Returns, warehouse filters on Delivery/Goods reports, and verification report `PHASE_10_OPERATIONAL_COMPLETION_REPORT.md`. |
| Security Hardening Pass | COMPLETE | Added baseline security headers, active-user request recheck, route-level authorization coverage, sensitive `taxes.file` capability, private local storage direct-serve lock (`FILESYSTEM_LOCAL_SERVE=false`), and regression test report `SECURITY_HARDENING_REPORT.md`. |
| Stabilization / QA Gate | COMPLETE | Added `qa:verify-local`, corrected Phase 10 PostgreSQL UUID test fixtures, extracted repeated inventory page option queries, removed hardcoded Delivery/Goods report UI text into dictionaries, and created `STABILIZATION_HARDENING_REPORT.md`. |
| Phase 10 Treasury Transfer and Branch Cash/Bank Extension | COMPLETE | Added optional operational `branch_id` references to `cash_account` and `bank_account`, branch-aware cash/bank UI filters/selectors, `treasury_transfer` table/model/service/controller/routes/page, PostingEngine-backed internal transfer posting (Dr destination cash/bank linked GL, Cr source linked GL), and verification report `PHASE_10_TREASURY_TRANSFER_REPORT.md`. |
| Phase 10 Fixed Asset Location and Movement History | COMPLETE | Added `fixed_asset_location`, current `fixed_asset.branch_id` and `fixed_asset.fixed_asset_location_id`, append-only `fixed_asset_movement`, `fixedAssets.transfer` permission, location CRUD page, branch/location filters and movement modal/history on fixed assets, and verification report `PHASE_10_FIXED_ASSET_MOVEMENT_REPORT.md`. |
| Phase 10 Branch Operational Reports | COMPLETE | Added `/reports/branch-operations`, `BranchOperationalReportService`, read-only branch coverage dashboard for warehouses, stock, cash/bank, fixed assets, asset movements, and posted treasury transfers, readiness checks for unassigned operational records, mixed-currency warning, and verification report `PHASE_10_BRANCH_OPERATIONAL_REPORTS_REPORT.md`. |
| Phase 10 GL Branch Dimension and Branch Profitability | COMPLETE | Added optional operational `branch_id` to `journal_entry`, `journal_line`, and immutable `ledger_entry`; updated journal draft/posting/reversal, treasury transfer posting, and inventory-generated journals to preserve branch context; added branch-filtered General Ledger, `/reports/branch-profitability`, protected CSV export, and permission-aware print/export actions; verified via `PHASE_10_GL_BRANCH_PROFITABILITY_REPORT.md`. |
| Phase 10 Branch-Specific GL Mapping Overrides | COMPLETE | Added optional operational `branch_id` to `accounting_account_mapping`, partial unique indexes for global vs branch override rows, branch-first/global-fallback mapping resolution, `/accounting/account-mappings` UI/actions, inventory MWA branch mapping integration, QA gate hardening, and verification report `PHASE_10_BRANCH_GL_MAPPING_REPORT.md`. |
| Phase 10 Branch-Aware Approval Rules | COMPLETE | Added optional `branch_approval_rule` configuration, `approvals.*` RBAC permissions, `/settings/branch-approval-rules` CRUD UI/actions, and extra permission gates for stock transfer, stock count, and stock adjustment approvals without tenant/security scope; verified via `PHASE_10_BRANCH_APPROVAL_RULES_REPORT.md`. |
| Phase 10 Landed Cost / Freight Allocation | COMPLETE | Added `landed_cost_allocation` and lines, lifecycle service/controller/routes/page, `purchasing.landed_costs` permission, attachment registry support, period close blocker, WAVG zero-quantity value stock movements, AP payable creation, balanced GL posting, and verification report `PHASE_10_LANDED_COST_ALLOCATION_REPORT.md`. |
| Phase 11 Expense Management | COMPLETE | Added `expense_category`, `expense`, and `expense_line`, category CRUD, expense lifecycle (`draft` -> `submitted` -> `approved` -> `posted` / `cancelled`), payable/cash/bank settlement, PostingEngine journals, input VAT support, required attachment gate, tax-period and period-close blockers, `/expenses` and `/expenses/categories` pages, mixed-currency visible-total protection, and verification report `PHASE_11_EXPENSE_MANAGEMENT_REPORT.md`. |
| Phase 12 Prepaid & Accrued Expenses | COMPLETE | Added `prepaid_schedule`, `prepaid_recognition`, `accrual_schedule`, and `accrual_entry`, schedule lifecycle, exact monthly amount allocation, PostingEngine journals for prepaid recognition and accrual entries, default GL mappings/accounts, period close blockers, `/expenses/prepaids` and `/expenses/accruals`, currency-registry consistency fix, PHPUnit memory-limit test runner hardening, and verification report `PHASE_12_PREPAID_ACCRUED_EXPENSES_REPORT.md`. |
| Phase 13 Payroll Foundation | COMPLETE | Added standalone payroll employees, payroll components, employee recurring component assignments, payroll periods, payroll runs, payroll run lines, PostingEngine-backed payroll journals, default payroll GL mappings/accounts, period close blockers, payroll attachment authorization, `/payroll/employees`, `/payroll/components`, `/payroll/runs`, sensitive `view_payroll` permission enforcement, optional operational payroll branch references, and verification report `PHASE_13_PAYROLL_FOUNDATION_REPORT.md`. |
| Phase 14 Rentals Policy Decision Pack | COMPLETE | Created `PHASE_14_RENTALS.md`, `PHASE_14_SLICE_1_GEMINI_PROMPT.md`, and `PHASE_14_RENTALS_POLICY_DECISION.md`. Owner direction in chat approved continuing with the recommended hybrid standalone register path. |
| Phase 14 Rentals Foundation | COMPLETE | Added `rentable_item` and `rentable_item_status_event`, rentable item service/controller/routes/page, source consistency rules, operational branch/warehouse placement, attachment registry, RBAC action expansion, EN/AR dictionary text, and `PHASE_14_RENTALS_FOUNDATION_REPORT.md`. |
| Phase 14 Rental Contracts | COMPLETE | Added `rental_contract`, `rental_contract_line`, and `rental_contract_status_event`, rental contract service/controller/routes/page, customer linkage, optional operational branch reference, contract lifecycle, item reservation/allocation/rented transitions, attachment registry, EN/AR dictionary text, and `PHASE_14_RENTAL_CONTRACTS_REPORT.md`. No rental GL posting was introduced. |
| Phase 14 Rental Fulfillment | COMPLETE | Added `rental_handover`, `rental_handover_line`, `rental_return`, and `rental_return_line`, `RentalFulfillmentService`, handover/return controllers/routes/pages, handover confirmation, return submission, inspection completion, item outcome transitions, contract completion logic, attachment registry, EN/AR dictionary text, and `PHASE_14_RENTAL_FULFILLMENT_REPORT.md`. No rental GL posting was introduced. |
| Phase 14 Rental Billing | COMPLETE | Added `rental_invoice` and `rental_invoice_line`, `RentalInvoiceService`, `/rentals/invoices`, rent/deposit/damage/late/other charge billing, `RINV-YYYY-XXXXX` numbering, output VAT, AR `receivable_entry`, PostingEngine GL posting, rental accounting mappings, attachment registry, VAT register integration, period close blocker, EN/AR dictionary UI text, and `PHASE_14_RENTAL_BILLING_REPORT.md`. |
| Phase 14 Rental Reports & Close-Out | COMPLETE | Added `RentalOperationsReportService`, `RentalOperationsReportController`, `/reports/rentals`, CSV export, `Reports/RentalOperationsReport.tsx`, Reports Hub/sidebar links, readiness checks for overdue contracts, unbilled lines, open invoices, pending damage, mixed currency, and `PHASE_14_FINAL_VERIFICATION_REPORT.md`. |
| Phase 14 Rentals | COMPLETE FOUNDATION | Phase 14 Slices 1-6 are complete and locally verified: policy, rentable items, contracts, fulfillment, billing/accounting integration, rental operations report, export/print UX, source scans, and final verification. |
| Phase 15 Product Hardening Slices 1-190 | COMPLETE / CLOSED | Slice 190 replaced remaining native `type="date"` page inputs with the shared RTL-aware `DatePicker` and extended the global page control/navigation/type regression guard to prevent native date inputs, native selects/options, unsafe redirects, and loose pagination links; `Phase15ProductHardeningTest` passed with 192 tests / 25644 assertions, controller direct-query scan, no-scope scan, full Pages native-control/pagination scan, Pint, TypeScript typecheck, and Vite build passed. See `PHASE_15_FINAL_VERIFICATION_REPORT.md`. |
| Phase 16 Slice 1 Project and Cost Center Foundation | COMPLETE | Standalone `project` and `cost_center` master data tables, models, services, controllers, Inertia management pages (`Projects/Index.tsx`, `CostCenters/Index.tsx`), Spatie Activitylog audit, attachment registry, EN/AR translations, and 12/12 passing feature tests (`Phase16Slice1ProjectCostCenterTest`). |
| Phase 16 Slice 2 GL Project and Cost Center Dimensions | COMPLETE | Added optional nullable `project_id` and `cost_center_id` to `journal_line` and immutable `ledger_entry` with foreign keys and indexes; updated `DraftLine`, `JournalDraftService`, `PostingEngine`, and `ReversalService` to validate active dimensions and propagate to GL; deletion guards in `ProjectService` and `CostCenterService`; `JournalForm.tsx` and `JournalDetail.tsx` enhanced; 13/13 passing feature tests (`Phase16Slice2GlDimensionTest`). |
| Phase 16 Slice 3 Expense Line Dimension Capture | COMPLETE (Expenses) | Added optional nullable `project_id` and `cost_center_id` to `expense_line` with foreign keys and composite indexes; active dimension validation in `ExpenseService`; grouped debit posting by account and dimensions; line dimension propagation to debit journal lines and ledger entries; deletion prevention in project/cost-center services; updated `Expenses/Index.tsx` with SearchableSelect; 11/11 passing feature tests (`Phase16Slice3ExpenseDimensionTest`). |
| Phase 16 Slice 4 Project and Cost Center Actual Reports | COMPLETE | Added read-only `/reports/project-profitability` and `/reports/cost-center-actuals` pages plus CSV exports, thin report controllers, ledger-only report services, per-currency summaries, unassigned review rows, reports hub cards, EN/AR dictionary text, local review corrections for currency validation and translated selector labels, and 14/14 passing feature tests (`Phase16Slice4ProjectCostCenterReportsTest`, 204 assertions). |
| Phase 16 Slice 5 Budget Version and Line Foundation | COMPLETE | Added `budget` and `budget_line` tables/models, `BudgetService`, `BudgetController`, `Budgets.tsx`, DB-enforced single-active budget per fiscal year, workflow lifecycle (`draft` -> `submitted` -> `approved` -> `active` -> `archived` / `cancelled`), optimistic locking, Spatie Activitylog audit, and 22/22 passing feature tests (`Phase16Slice5BudgetFoundationTest`, 124 assertions). |
| Phase 16 Slice 6 Budget vs Actual Reports & Close-Out | COMPLETE | Added read-only `/budgeting/variance` report and CSV export, `BudgetVarianceReportService`, `BudgetVarianceCsvExporter`, `BudgetVariancePageData`, `BudgetVarianceController`, `Variance.tsx`, exact integer basis point variance math (`intdiv`), multi-currency isolation, warning codes, and 23/23 passing feature tests (`Phase16Slice6BudgetVarianceCloseOutTest`, 199 assertions). |
| Phase 17 Slice 1 Controlled Bootstrap Admin Guard | COMPLETE | Hardened `FirstUserSuperAdminSeeder` to fail-closed and disabled by default (`ERP_ASSIGN_FIRST_USER_SUPER_ADMIN=false`), added production confirmation phrase requirement (`ERP_FIRST_USER_SUPER_ADMIN_PRODUCTION_CONFIRM`), updated `config/erp_auth.php`, documented non-secret env vars in `.env.example` and `spec/ENVIRONMENT_CHECKLIST.md`, updated `spec/SECURITY.md`, and added unit/feature tests in `AuthenticationTest.php` (15/15 passing tests); verified via `PHASE_17_SLICE_1_REPORT.md`. |
| Phase 17 Security and Access Governance | IN PROGRESS | Slice 1 COMPLETE. Slices 2-6 prepared. Deployment remains parked. |
| DB immutability | COMPLETE | PostgreSQL and SQLite triggers block UPDATE/DELETE on `activity_log` and legacy `audit_log`. |

## Verification Snapshot

Latest Phase 14 Rentals final verification:

```powershell
php artisan migrate --force
php artisan migrate:status
node -e "JSON.parse(...en.json); JSON.parse(...ar.json)"
vendor/bin/pint --test
php artisan test --filter=Phase14 --stop-on-failure
php artisan test --filter=Phase14RentalsFoundationTest --stop-on-failure
php artisan test --filter=Phase14RentalBillingTest --stop-on-failure
php artisan test --filter=Phase14RentalReportsCloseOutTest --stop-on-failure
php artisan test --filter=Phase3Slice9StressIntegrityTest --stop-on-failure
php artisan route:list --path=rentals
php artisan route:list --path=reports/rentals
php artisan accounting:phase3-integrity-check
php artisan test --testsuite=Concurrency --stop-on-failure
php artisan test --stop-on-failure
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan accounting:stock-transfer-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Result summary:

- `migrate --force`: Nothing to migrate.
- `migrate:status`: 82 migrations Ran, including all Phase 14 rental item, contract, handover/return, and invoice migrations.
- Locale JSON parse passed for `en.json` and `ar.json`.
- Pint passed.
- `php artisan test --filter=Phase14`: 27 tests / 256 assertions passed.
- `Phase14RentalsFoundationTest`: 16 tests / 159 assertions passed.
- `Phase14RentalBillingTest`: 8 tests / 56 assertions passed.
- `Phase14RentalReportsCloseOutTest`: 3 tests / 41 assertions passed.
- `Phase3Slice9StressIntegrityTest`: 6 tests / 652 assertions passed.
- `route:list --path=rentals`: rental item, contract, invoice, handover, and return routes registered.
- `route:list --path=reports/rentals`: 2 report routes registered.
- Full Laravel suite: 654 tests, 651 passed, 3 skipped / 5,632 assertions passed.
- Concurrency suite: 7 tests / 16 assertions passed.
- `concurrency:stress --workers=100`: passed; sequence values unique and contiguous; idempotency callback executed exactly once.
- `accounting:concurrency-stress --workers=50`: passed.
- `accounting:allocation-concurrency-stress --workers=50`: passed.
- `accounting:cheque-concurrency-stress --workers=50`: passed.
- `accounting:bank-reconciliation-concurrency-stress --workers=50`: passed.
- `accounting:stock-transfer-stress --workers=50`: passed.
- `accounting:inventory-concurrency-stress --workers=50`: passed.
- `accounting:phase3-integrity-check`: passed.
- `tokens:gc --batch=100`: passed.
- TypeScript typecheck passed.
- Vite build passed with the existing chunk-size warning only.

Latest Phase 10 operational inventory completion verification:

```powershell
php artisan migrate:status
vendor/bin/pint --test
php artisan test --testsuite=Unit
php artisan test --testsuite=Integration
php artisan test --testsuite=Invariants
php artisan test --filter=Phase10StockCountAdjustmentTest
php artisan test --filter=Phase10BranchWarehouseOperationsTest
php artisan test --filter=Phase4Slice4FulfillmentTest
php artisan test --filter=Phase4Slice10ReturnsCreditNotesTest
php artisan test --filter=Phase4Slice9OperationalReportsTest
php artisan test --filter=SecurityHardeningTest
php artisan test --filter=Phase8Slice4RouteSmokeTest
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan accounting:stock-transfer-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Result summary:

- all migrations in that earlier snapshot Ran through `2026_08_25_020000_create_phase10_fixed_asset_location_movement_tables`.
- Pint passed.
- Laravel suites passed: Unit, Integration, Invariants, Phase 10 stock count/adjustment, Phase 10 warehouse/transfer, Phase 10 treasury transfer, Phase 10 fixed asset movement, Phase 4 fulfillment, Phase 4 returns, Phase 4 operational reports, Security hardening, Phase 8 route smoke, and Concurrency.
- stock/inventory stress commands passed with 50 workers/iterations; generic concurrency stress passed with 100 workers.
- TypeScript typecheck and Vite build passed; Vite emitted the existing chunk-size warning only.
- one monolithic `php artisan test` run was started and stopped after several silent minutes in the local runner; use the targeted results above plus previous file-by-file verification unless a long unattended full-suite run is specifically needed.

Latest Phase 9 final cutover local verification:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --filter=Phase8
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=10
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan accounting:sales-tax-stress --workers=50
php artisan accounting:purchasing-tax-stress --workers=50
php artisan accounting:tax-filing-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Result summary:

- `php artisan migrate --force`: Nothing to migrate.
- `php artisan migrate:status`: all 64 migrations Ran.
- `vendor/bin/pint --test`: passed.
- `php artisan test`: 554 tests, 551 passed, 3 skipped, 4,068 assertions.
- `php artisan test --filter=Phase8`: 6 tests / 49 assertions passed.
- `php artisan test --testsuite=Concurrency`: 7 tests / 16 assertions passed.
- `php artisan concurrency:stress --workers=10`: passed.
- `php artisan accounting:concurrency-stress --workers=50`: passed.
- `php artisan accounting:phase3-integrity-check`: passed.
- `php artisan accounting:sales-tax-stress --workers=50`: passed.
- `php artisan accounting:purchasing-tax-stress --workers=50`: passed.
- `php artisan accounting:tax-filing-stress --workers=50`: passed.
- `php artisan tokens:gc --batch=100`: deleted sessions=0 password_reset_tokens=0 idempotency_keys=0.
- `npm run typecheck`: passed.
- `npm run build`: passed, 679 modules transformed.

Previous Phase 6 Slice 7 local correction verification:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=Phase6Slice2FixedAssetRegisterTest
php artisan test --filter=Phase6Slice3CapitalizationTest
php artisan test --filter=Phase6Slice4DepreciationScheduleTest
php artisan test --filter=Phase6Slice5DepreciationRunTest
php artisan test --filter=Phase6Slice6FixedAssetDisposalTest
php artisan test --filter=Phase6Slice7FixedAssetReportsTest
php artisan test --filter=Phase6
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:settlement-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan accounting:fixed-asset-depreciation-stress --workers=50
php artisan accounting:fixed-asset-disposal-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Result summary:

- `php artisan migrate --force`: Nothing to migrate.
- `php artisan migrate:status`: all 60 migrations Ran through `2026_08_23_071000_enforce_fixed_asset_disposal_integrity`.
- `vendor/bin/pint --test`: passed.
- `php artisan test --filter=Phase6Slice2FixedAssetRegisterTest`: 9 tests / 71 assertions passed.
- `php artisan test --filter=Phase6Slice3CapitalizationTest`: 11 tests / 64 assertions passed.
- `php artisan test --filter=Phase6Slice4DepreciationScheduleTest`: 13 tests / 64 assertions passed.
- `php artisan test --filter=Phase6Slice5DepreciationRunTest`: 10 tests / 44 assertions passed.
- `php artisan test --filter=Phase6Slice6FixedAssetDisposalTest`: 15 tests / 60 assertions passed.
- `php artisan test --filter=Phase6Slice7FixedAssetReportsTest`: 6 tests / 153 assertions passed.
- `php artisan test --filter=Phase6`: 64 tests / 456 assertions passed.
- `php artisan test`: 514 tests, 511 passed, 3 skipped / 3855 assertions.
- `php artisan test --testsuite=Concurrency`: 7 tests / 16 assertions passed.
- `php artisan concurrency:stress --workers=100`: passed.
- `php artisan accounting:concurrency-stress --workers=50`: passed.
- Fixed asset depreciation and fixed asset disposal stress commands passed with 50 workers each; core accounting stress also passed with 50 workers.
- `php artisan tokens:gc --batch=100`: deleted 0 rows.
- `npm run typecheck`: passed.
- `npm run build`: passed (chunk size warning only).

Latest Phase 5 Slice 6 close-out and local review verification:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=Phase5Slice6FinalCloseOutTest
php artisan test --filter=Phase3Slice8ReportsTest
php artisan test --testsuite=Concurrency
php artisan test
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
rg -n "Balance Sheet|Income Statement|Cash Flow|Close Period|Reopen Period|Print Report|طباعة التقرير" resources/js/Pages resources/js/Components
rg -n -- "->entry_number|'entry_number' =>|'period_number' =>|financial_period\\.name" app/Application/Reports app/Http/Controllers/Reports tests/Feature/Phase5Slice6FinalCloseOutTest.php resources/js/Pages/Reports
```

Result summary:

- `php artisan migrate --force`: Nothing to migrate.
- `php artisan migrate:status`: all 52 migrations Ran.
- `vendor/bin/pint --test`: passed.
- `php artisan test --filter=Phase5Slice6FinalCloseOutTest`: 4 tests / 30 assertions passed.
- `php artisan test --filter=Phase3Slice8ReportsTest`: 12 tests / 180 assertions passed.
- `php artisan test --testsuite=Concurrency`: 7 tests / 16 assertions passed.
- `php artisan test`: 450 tests, 447 passed, 3 skipped / 3374 assertions.
- `php artisan concurrency:stress --workers=10`: passed.
- Accounting stress commands for core posting, allocation, settlement, cheques, bank reconciliation, inventory, and Phase 3 orchestration: passed.
- `php artisan accounting:phase3-integrity-check`: passed.
- `php artisan tokens:gc --batch=100`: deleted 0 rows.
- `npm run typecheck`: passed.
- `npm run build`: passed (chunk size warning only).
- Financial-report visible hardcoded-text scan returned zero matches.
- Schema-fixture scan has no unacceptable matches. The remaining `entry_number` match is an intentionally named Cash Flow warning payload key sourced from `journal_entry.number`.

Previous Phase 5 Slice 4 local correction verification from `laravel/`:

```powershell
vendor/bin/pint --test
php artisan migrate --force
php artisan migrate:status
php artisan test --filter=Phase5Slice1FinancialStatementMappingTest
php artisan test --filter=Phase5Slice2FinancialStatementsTest
php artisan test --filter=Phase5Slice3CashFlowStatementTest
php artisan test --testsuite=Concurrency
php artisan test
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Result summary:

- `vendor/bin/pint --test`: passed.
- `php artisan migrate --force`: applied `2026_08_23_011000_harden_phase5_slice3_cash_flow_activity_constraints`.
- `php artisan migrate:status`: all migrations Ran through `2026_08_23_011000_harden_phase5_slice3_cash_flow_activity_constraints`.
- `php artisan test --filter=Phase5Slice1FinancialStatementMappingTest`: 9 tests / 30 assertions passed.
- `php artisan test --filter=Phase5Slice2FinancialStatementsTest`: 8 tests, 8 passed, 0 skipped / 54 assertions.
- `php artisan test --filter=Phase5Slice3CashFlowStatementTest`: 9 tests, 9 passed, 0 skipped / 46 assertions.
- `php artisan test --testsuite=Concurrency`: 7 tests / 16 assertions passed.
- `php artisan test`: 433 tests, 430 passed, 3 skipped / 3307 assertions.
- `php artisan tokens:gc --batch=100`: deleted 0 rows.
- `npm run typecheck`: passed.
- `npm run build`: passed (chunk size warning only).

Last full verification baseline from `laravel/`:

```powershell
composer install
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --filter=Phase4Slice1CatalogTest
php artisan test --filter=Phase4Slice2SalesOrderTest
php artisan test --filter=Phase4Slice10ReturnsCreditNotesTest
php artisan test --filter=Phase3Slice9StressIntegrityTest
php artisan test --filter=Phase3Slice8ReportsTest
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

Result summary:

- `php artisan migrate --force`: Nothing to migrate after Phase 4 Slice 10 implementation.
- `php artisan migrate:status`: all migrations Ran through `2026_08_22_200000_create_phase4_slice10_settlement_tables`.
- `vendor/bin/pint --test`: passed after Phase 4 Slice 10 implementation.
- `php artisan test`: 407 tests, 404 passed, 3 skipped / 3172 assertions.
- `php artisan test --filter=Phase4Slice10ReturnsCreditNotesTest`: 38 tests / 38 passed / 0 skipped / 230 assertions.
- `php artisan test --testsuite=Concurrency`: 7 tests / 16 assertions passed.
- `php artisan concurrency:stress --workers=10`: passed; `--workers=100` is blocked locally by Windows paging-file memory exhaustion.
- `php artisan accounting:concurrency-stress --workers=50`: passed.
- `php artisan accounting:allocation-concurrency-stress --workers=50`: passed.
- `php artisan accounting:settlement-concurrency-stress --workers=50`: passed.
- `php artisan accounting:cheque-concurrency-stress --workers=50`: passed.
- `php artisan accounting:bank-reconciliation-concurrency-stress --workers=50`: passed.
- `php artisan accounting:inventory-concurrency-stress --workers=50`: passed.
- `php artisan accounting:phase3-integrity-check`: passed.
- `php artisan accounting:phase3-stress --workers=50`: 50 SUCCESS.
- `php artisan tokens:gc --batch=100`: passed.
- `npm run typecheck`: passed.
- `npm run build`: passed (chunk size warning only).
- Phase 4 Slice 10 tax math uses integer basis points only: `intdiv(($baseMinor * $rateBps) + 5000, 10000)`.

## Module Status

| Module / Area | Status | Notes |
|---|---|---|
| Accounting ledger spine | COMPLETE | Phase 2 accounting core is implemented as the current ledger backbone. |
| Settings | COMPLETE | Company profile, standalone branch references, numbering, users/roles. |
| Notifications | COMPLETE FOUNDATION | User-targeted notifications and read actions exist; future modules must add their own event triggers. |
| Attachments | COMPLETE FOUNDATION | Entity registry + service exists; future entities must register authorization rules. |
| Audit | COMPLETE FOUNDATION | Spatie Activitylog active with read-only viewer and append-only enforcement. |
| Sales | COMPLETE | Bounded scope closed: Sales Orders, Delivery Notes, Customer Invoice posting, stock-product invoice reporting, COGS/inventory posting through Delivery Notes, physical Sales Returns, Customer Credit Notes with manual/open settlement, and immutable Customer Invoice Revisions are complete for their bounded scopes. Full VAT/tax filing remains out of scope. |
| Purchasing | COMPLETE | Bounded scope closed: Purchase Orders, Goods Receipts, Supplier Bill AP/GL posting, stock-product bill reporting, GRNI clearing, inventory valuation through Goods Receipts, physical Purchase Returns, and normalized Supplier Adjustment Notes are complete for their bounded scopes. |
| Inventory | COMPLETE FOUNDATION | Moving Weighted Average stock balance, immutable stock movement ledger, warehouse/location operations, stock transfers, stock counts, stock adjustments, warehouse-aware documents, and landed-cost value adjustments are implemented for the current bounded scope. |
| AR/AP + Cash/Bank/Cheques | COMPLETE | Phase 3 Slices 1-10 are complete; Phase 3 AR/AP + Cash/Bank/Cheques track is fully closed out for agreed scope. |
| Fixed Assets | COMPLETE | Phase 6 master contract and Slices 1-7 are complete: policy decision pack, register, capitalization, depreciation schedule, depreciation run posting, disposal, reports/export/print/close-out, and final verification report. |
| Payroll | COMPLETE FOUNDATION | Phase 13 payroll employees, components, recurring assignments, payroll runs, PostingEngine posting, period close blockers, attachments, permissions, and UI pages are complete. Future payslips, salary payment execution, loans/advances, attendance inputs, and statutory payroll rules require bounded slices. |
| Rentals | COMPLETE FOUNDATION | Phase 14 decision pack, rentable item register, rental contract lifecycle, rental handover/return/inspection workflow, billing, deposits, charge posting, revenue/VAT/AR integration, rental operations report, export/print UX, and final verification are complete. Future rental quotations, extensions, deposit refunds, automated recurring billing, and item profitability require bounded slices. |
| Projects, Budgeting | SCAFFOLD ONLY | Not started. |
| Taxes / VAT | COMPLETE | Phase 7 Slices 1-7 master contract and implementation complete: policy decision pack, tax master data foundation, sales tax posting, purchasing tax posting, VAT register/reports/GL reconciliation, tax period filing controls & locks, final verification report. |
| Full financial statements | COMPLETE | Mapping, Balance Sheet, Income Statement, Cash Flow, Period Close controls, Year-End Close decision pack, and print/export UX close-out are complete. Physical retained-earnings closing entries are not approved or implemented. |

## Known Issues / Residual Risks

- No GitHub Actions workflow is connected for the Laravel migration track; verification is currently local.
- Browser E2E coverage for the Laravel UI is not yet equivalent to the old Next.js Playwright smoke suite.
- Branch operational capability is approved. Warehouse/stock-transfer foundation, operational warehouse document selectors, branch cash/bank transfer, fixed asset location movement, branch reporting, branch profitability, branch-specific GL mapping overrides, branch-aware approval rules, and landed cost/freight allocation are implemented. Do not add branch tenancy, ownership, document-numbering scope, or authorization scope by assumption.
- Production scheduler execution still needs deployment wiring, e.g. external cron calling Laravel `schedule:run`.
- Legacy specs and old `app/` docs can mention tenant/company scope; treat them as historical unless they match current owner corrections.

## Next Milestone

Phases 1-9 of the Laravel migration track are 100% complete and verified: Foundation, Accounting Core, AR/AP/Cash/Bank/Cheques, Sales & Purchasing Operations, Financial Statements & Period Close, Fixed Assets, Tax/VAT, Operational Readiness, and Staging/Production Cutover Pack. Phase 10 branch-capable operations and landed cost/freight allocation are complete and verified. Phase 11 Expense Management, Phase 12 Prepaid & Accrued Expenses, Phase 13 Payroll Foundation, and Phase 14 Rentals are complete and verified. Phase 15 Product Hardening is closed with Slices 1-190 complete.

Latest Phase 14 final verification snapshot: Phase 14 feature tests passed 27/27 with 256 assertions, full Laravel suite passed 654 tests / 5,632 assertions with 3 skipped, `php artisan migrate:status` shows 82 migrations Ran, Concurrency suite passed, Phase 3 integrity check passed, stress commands passed sequentially, Pint passed, TypeScript typecheck passed, and Vite build passed with the existing chunk-size warning only.

Latest Phase 15 verification snapshot: `Phase15ProductHardeningTest` passed 192/192 with 25644 assertions after Slice 190. Targeted Slice 190 global Inertia page control/navigation/type guard passed with 1 test / 1 assertion and now blocks native `type="date"` inputs. Slice 188 global Inertia page button guard passed with 1 test / 1 assertion; the standalone Pages button scanner reported `TOTAL_FILES=0` and `TOTAL_MISSING=0`. Locale JSON, UI fallback, explicit currency, authorization, controller-boundary, report CSV delegation, page-data boundary, full Pages native-control/pagination scan, no-scope scan, and controller direct-query scans passed. Pint, TypeScript typecheck, and Vite build passed after Slice 190. Last broad full Laravel suite pass remains Slice 57 because later broad runs exceeded local timeout budgets, while focused suites continue passing.

Next prepared track:

- Phase 15 is closed. Open a new bounded phase/slice for the next product capability or UX initiative. Deployment remains parked until owner/operator cutover decisions are needed.
- All future work must preserve the no-multi-tenant rule, exact permissions, clean controllers, and dictionary-backed visible UI text.


