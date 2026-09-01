# CONTINUE HERE - Mini ERP Laravel handoff

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.

> **Branch-Capable Product Direction:** Owner direction on 2026-08-24 requires future support for multiple operational branches, branch transfers, and branch-aware workflows. This is not multi-tenancy. See `PRODUCT_EXTENSIBILITY_ROADMAP.md`.


Current date/context: 2026-09-01. Phase 22 (CI Pipeline and Browser Smoke Coverage) is 100% COMPLETE (`PHASE_22_CI_AND_BROWSER_COVERAGE.md`). Phase 21 (Report Server-Side Pagination) is 100% COMPLETE (`PHASE_21_REPORT_SERVER_SIDE_PAGINATION.md`) — no report page ships an unbounded row array. Product development is release-candidate complete through Phase 20 (`PHASE_20_FINAL_VERIFICATION_REPORT.md`). Phase 19 (Accountant Acceptance Execution and Gap Closure) is 100% COMPLETE (`PHASE_19_FINAL_VERIFICATION_REPORT.md`). Phase 18 (Product Acceptance, UI Polish, and Clean Code Gate) is 100% COMPLETE (`PHASE_18_FINAL_VERIFICATION_REPORT.md`). Phase 17 (Security and Access Governance) is 100% COMPLETE (`PHASE_17_FINAL_VERIFICATION_REPORT.md`). Phase 16 (Projects, Cost Centers, and Budgeting) is 100% COMPLETE. Deployment execution remains parked until external staging/production host inputs and owner/operator sign-off are available.

Latest update: 2026-09-01 Phase 22 is COMPLETE. Run browser tests with: `php artisan e2e:prepare-user --password="<local>"`, set `E2E_EMAIL`/`E2E_PASSWORD`, then `npm run e2e`. Note the CI workflow now targets `laravel/`; the old one pointed at the abandoned `app/` tree and tested nothing real. CI is validated locally but has not yet run on GitHub Actions.

Previous update: 2026-09-01 Phase 21 is COMPLETE. Slice 1 (`PHASE_21_SLICE_1_REPORT.md`) wired the previously orphaned `VatRegisterDataTableService` into `/reports/vat-register/data` and added cross-cutting DataTable endpoint guards to `ReportInputHardeningTest`. Slice 2 (`PHASE_21_SLICE_2_REPORT.md`) added `RentalOperationsDataTableService` and `/reports/rentals/data`, translating 35 PHP-computed fields into SQL with parity asserted across 26 row fields plus the summary/readiness payload. Both pages keep complete CSV exports. Note: `agy` could not run these slices as a sub-agent — headless mode auto-denies its tool permissions; add an allow-rule under `permissions.allow` in its settings.json to enable that.

Previous update: 2026-08-30 Go-live gate preparation is COMPLETE locally. Added `ops:go-live-readiness` non-secret readiness command, `GoLiveReadinessCommandTest` (2 tests / 23 assertions), and `spec/GO_LIVE_EXECUTION_LOG.md`. Verified local readiness, scheduler token GC, queue failed-job inspection, health route, no direct private storage route, Phase 8 readiness checks, staging/production fail-closed behavior from local, and expanded batched regression after aligning legacy Phase 5/6 feature tests with the current sensitive-action confirmation contract. Phase 20 remains 100% COMPLETE.

Next task: execute staging deployment dry-run and owner/accountant hands-on sign-off once target host, domain/HTTPS, production environment values, backup/restore access, queue/scheduler process manager, and mail/storage provider inputs are available. Deployment remains parked until those external gates pass.

The old Next.js app under `app/` remains historical reference only. Do not restore old tenant/company-scope behavior from it.

## Source Of Truth

Use the current Laravel code and these documents first:

- `README.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PRODUCT_ACCEPTANCE_DEFECT_LOG.md`
- `PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md`
- `OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md`
- `spec/GO_LIVE_EXECUTION_LOG.md`
- `spec/GO_LIVE_ACCEPTANCE.md`
- `spec/DEPLOYMENT.md`
- `PHASE_20_FINAL_VERIFICATION_REPORT.md`
- `PHASE_20_HANDS_ON_ACCEPTANCE_DEFECT_CLOSURE.md`
- `PHASE_20_SLICE_3_REPORT.md`
- `PHASE_20_SLICE_3_AGY_PROMPT.md`
- `PHASE_20_SLICE_2_REPORT.md`
- `PHASE_20_SLICE_2_AGY_PROMPT.md`
- `PHASE_20_SLICE_1_REPORT.md`
- `PHASE_20_SLICE_1_AGY_PROMPT.md`
- `PHASE_19_FINAL_VERIFICATION_REPORT.md`
- `PHASE_19_ACCOUNTANT_ACCEPTANCE_EXECUTION.md`
- `PHASE_19_SLICE_1_REPORT.md`
- `PHASE_19_SLICE_1_AGY_PROMPT.md`
- `PHASE_19_SLICE_2_REPORT.md`
- `PHASE_19_SLICE_2_AGY_PROMPT.md`
- `PHASE_19_SLICE_3_REPORT.md`
- `PHASE_19_SLICE_3_AGY_PROMPT.md`
- `PHASE_19_SLICE_4_AGY_PROMPT.md`
- `PHASE_18_FINAL_VERIFICATION_REPORT.md`
- `PHASE_18_PRODUCT_ACCEPTANCE_UI_CLEAN_CODE.md`
- `PHASE_18_SLICE_3_REPORT.md`
- `PHASE_18_SLICE_2_REPORT.md`
- `PHASE_18_SLICE_1_REPORT.md`
- `PHASE_18_SLICE_1_AGY_PROMPT.md`
- `PHASE_18_SLICE_2_AGY_PROMPT.md`
- `PHASE_18_SLICE_3_AGY_PROMPT.md`
- `PHASE_18_SLICE_4_AGY_PROMPT.md`
- `PHASE_17_FINAL_VERIFICATION_REPORT.md`
- `PHASE_17_SECURITY_ACCESS_GOVERNANCE.md`
- `PHASE_17_SLICE_1_REPORT.md`
- `PHASE_17_SLICE_2_REPORT.md`
- `PHASE_17_SLICE_3_REPORT.md`
- `PHASE_17_SLICE_4_REPORT.md`
- `PHASE_17_SLICE_5_REPORT.md`
- `PHASE_17_FINAL_VERIFICATION_REPORT.md`
- `PHASE_17_SLICE_6_AGY_PROMPT.md`


- `PHASE_16_FINAL_VERIFICATION_REPORT.md`
- `PHASE_16_SLICE_2_REPORT.md`
- `PHASE_16_SLICE_3_REPORT.md`
- `PHASE_16_SLICE_4_REPORT.md`
- `PHASE_16_SLICE_5_REPORT.md`
- `PHASE_16_FINAL_VERIFICATION_REPORT.md`
- `PHASE_16_SLICE_2_AGY_PROMPT.md`
- `PHASE_15_FINAL_VERIFICATION_REPORT.md`
- `PHASE_15_PRODUCT_HARDENING_REPORT.md`
- `PHASE_14_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_14_FINAL_VERIFICATION_REPORT.md`
- `PHASE_14_RENTAL_BILLING_REPORT.md`
- `PHASE_14_RENTAL_FULFILLMENT_REPORT.md`
- `PHASE_14_RENTAL_CONTRACTS_REPORT.md`
- `PHASE_14_RENTALS_FOUNDATION_REPORT.md`
- `PHASE_13_PAYROLL_FOUNDATION_REPORT.md`
- `PHASE_12_PREPAID_ACCRUED_EXPENSES_REPORT.md`
- `PHASE_11_EXPENSE_MANAGEMENT_REPORT.md`
- `PHASE_10_BRANCH_WAREHOUSE_OPERATIONS.md`
- `PHASE_10_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_10_FINAL_REPORT.md`
- `PHASE_10_OPERATIONAL_COMPLETION_REPORT.md`
- `PHASE_10_TREASURY_TRANSFER_REPORT.md`
- `PHASE_10_FIXED_ASSET_MOVEMENT_REPORT.md`
- `PHASE_10_BRANCH_OPERATIONAL_REPORTS_REPORT.md`
- `PHASE_10_GL_BRANCH_PROFITABILITY_REPORT.md`
- `PHASE_10_BRANCH_GL_MAPPING_REPORT.md`
- `PHASE_10_BRANCH_APPROVAL_RULES_REPORT.md`
- `PHASE_10_LANDED_COST_ALLOCATION_REPORT.md`
- `SECURITY_HARDENING_REPORT.md`
- `STABILIZATION_HARDENING_REPORT.md`
- `PHASE_8_FINAL_OPERATIONAL_READINESS_REPORT.md`
- `PHASE_7_FINAL_VERIFICATION_REPORT.md`
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
- `PHASE_5_YEAR_END_CLOSE_DECISION.md`
- `PHASE_5_SLICE_6_GEMINI_PROMPT.md`
- `PHASE_5_FINAL_VERIFICATION_REPORT.md`
- `PHASE_6_FIXED_ASSETS.md`
- `PHASE_6_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_6_FIXED_ASSETS_POLICY_DECISION.md`
- `PHASE_6_SLICE_2_GEMINI_PROMPT.md` (Phase 6 Slice 2 Fixed Asset Register Foundation 100% COMPLETE & VERIFIED 2026-08-23)
- `PHASE_6_SLICE_3_GEMINI_PROMPT.md` (Phase 6 Slice 3 Capitalization and Opening Asset Posting 100% COMPLETE & VERIFIED 2026-08-23)
- `PHASE_6_SLICE_4_GEMINI_PROMPT.md` (Phase 6 Slice 4 Depreciation Schedule Engine 100% COMPLETE & VERIFIED 2026-08-23)
- `PHASE_6_SLICE_5_GEMINI_PROMPT.md` (Phase 6 Slice 5 Depreciation Run Posting 100% COMPLETE & VERIFIED 2026-08-23)
- `PHASE_6_SLICE_6_GEMINI_PROMPT.md` (Phase 6 Slice 6 Fixed Asset Disposal 100% COMPLETE & VERIFIED 2026-08-23)
- `PHASE_6_SLICE_7_GEMINI_PROMPT.md` (Phase 6 Slice 7 Reports, UX, Export/Print & Close-Out 100% COMPLETE & VERIFIED 2026-08-23)
- `PHASE_6_FINAL_VERIFICATION_REPORT.md` (Phase 6 Final Verification Report 100% COMPLETE & VERIFIED 2026-08-23)
- `PHASE_7_TAX_VAT.md` (Phase 7 Tax / VAT planning contract)
- `PHASE_7_SLICE_1_GEMINI_PROMPT.md` (Phase 7 Slice 1 Tax/VAT Policy Decision Pack 100% COMPLETE 2026-08-23)
- `PHASE_7_TAX_VAT_POLICY_DECISION.md` (Phase 7 Tax/VAT Policy Decision Pack 100% COMPLETE 2026-08-23)
- `PHASE_7_SLICE_2_GEMINI_PROMPT.md` (Phase 7 Slice 2 Tax Code and Tax Rate Foundation 100% COMPLETE & VERIFIED 2026-08-23)
- `PHASE_7_SLICE_3_GEMINI_PROMPT.md` (Sales Output VAT Integration 100% COMPLETE & VERIFIED 2026-08-23)
- `PHASE_7_SLICE_4_GEMINI_PROMPT.md` (Purchasing Input VAT Integration 100% COMPLETE & VERIFIED 2026-08-23)
- `PHASE_7_SLICE_5_GEMINI_PROMPT.md` (VAT Register, Reports, and GL Reconciliation 100% COMPLETE & VERIFIED 2026-08-23)
- `PHASE_7_SLICE_6_GEMINI_PROMPT.md` (Tax Period Filing and Locking Controls 100% COMPLETE & VERIFIED 2026-08-23)
- `PHASE_7_SLICE_7_GEMINI_PROMPT.md` (UX, Export/Print, Source Scans, and Close-Out 100% COMPLETE & VERIFIED 2026-08-23)
- `PHASE_7_FINAL_VERIFICATION_REPORT.md` (Phase 7 Final Verification Report 100% COMPLETE & VERIFIED 2026-08-23)
- `PHASE_8_OPERATIONAL_READINESS.md` (Phase 8 operational readiness master contract; prepared 2026-08-24)
- `PHASE_8_SLICE_1_GEMINI_PROMPT.md` (Operational readiness decision pack 100% COMPLETE 2026-08-24)
- `PHASE_8_OPERATIONAL_READINESS_DECISION.md` (Owner-facing operational readiness decision pack 100% COMPLETE 2026-08-24)
- `PHASE_8_SLICE_2_GEMINI_PROMPT.md` (Laravel deployment documentation refresh; prepared 2026-08-24)
- `PHASE_8_SLICE_3_GEMINI_PROMPT.md` (Scheduler, queue, and health readiness; prepared 2026-08-24)
- `PHASE_8_SLICE_4_GEMINI_PROMPT.md` (Browser smoke / E2E foundation; prepared 2026-08-24)
- `PHASE_8_SLICE_5_GEMINI_PROMPT.md` (Final operational close-out; prepared 2026-08-24)
- `PHASE_8_FINAL_OPERATIONAL_READINESS_REPORT.md` (Phase 8 Operational Readiness final report 100% COMPLETE & VERIFIED 2026-08-24)
- `PHASE_9_STAGING_PRODUCTION_CUTOVER.md` (Phase 9 staging/production cutover master contract; prepared 2026-08-24)
- `PHASE_9_SLICE_1_GEMINI_PROMPT.md` (Cutover decision pack 100% COMPLETE 2026-08-24)
- `PHASE_9_CUTOVER_DECISION_PACK.md` (Cutover decision pack 100% COMPLETE 2026-08-24)
- `PHASE_9_SLICE_2_GEMINI_PROMPT.md` (Environment and secrets checklist 100% COMPLETE 2026-08-24)
- `spec/ENVIRONMENT_CHECKLIST.md` (Deployment environment checklist 100% COMPLETE 2026-08-24)
- `PHASE_9_SLICE_3_GEMINI_PROMPT.md` (Deployment and rollback runbooks 100% COMPLETE 2026-08-24)
- `spec/DEPLOYMENT_RUNBOOK.md` & `spec/ROLLBACK_RUNBOOK.md` (Deployment and rollback runbooks 100% COMPLETE 2026-08-24)
- `PHASE_9_SLICE_4_GEMINI_PROMPT.md` (Backup and restore drill pack 100% COMPLETE 2026-08-24)
- `spec/BuCKUP_RESTORE_DRILL.md` (PostgreSQL backup and restore drill pack 100% COMPLETE 2026-08-24)
- `PHASE_9_SLICE_5_GEMINI_PROMPT.md` (Runtime processes, storage, mail, and logs 100% COMPLETE 2026-08-24)
- `spec/RUNTIME_PROCESSES.md` (Runtime processes operations guide 100% COMPLETE 2026-08-24)
- `PHASE_9_SLICE_6_GEMINI_PROMPT.md` (Go-live smoke, security checklist, and acceptance gate 100% COMPLETE 2026-08-24)
- `spec/GO_LIVE_ACCEPTANCE.md` (Go-live smoke, security checklist, and acceptance gate 100% COMPLETE 2026-08-24)
- `PHASE_9_SLICE_7_GEMINI_PROMPT.md` (Final cutover close-out 100% COMPLETE & VERIFIED 2026-08-24)
- `PHASE_9_FINAL_CUTOVER_REPORT.md` (Phase 9 final cutover report 100% COMPLETE & VERIFIED 2026-08-24)
- `docs/CONCURRENCY_AUDIT.md`

Historical audit/spec files can still be useful for ERP scope and no-multi-tenant evidence, but they are not current implementation-status documents. Owner corrections, current Laravel code, `IMPLEMENTATION_STATUS.md`, `NEXT_TASKS.md`, and the latest phase reports override old generated architecture and stale "not implemented" notes.

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

`UNDEFINED - DO NOT uSSUME`

Confirmed later owner decision:

- FiscalYear is `SINGLE-ERP CONTEXT`.
- Fiscal years are global to this installation/business profile.
- `fiscal_year.year` is globally unique.
- FinancialPeriod belongs to FiscalYear.

Confirmed owner product direction on 2026-08-24:

- Future product work must account for multiple operational branches.
- Future product work must account for branch transfers and branch-aware sales, purchasing, inventory, returns, cash/bank, fixed asset, reporting, and approval scenarios.
- This does not make Branch a tenant, Company child, login context, or security boundary.
- Branch references may be added only by explicit bounded slices with migrations, services, UI, permissions, tests, audit, and source-scan classification.

## Review Gate For Generated Work

Before accepting any Gemini/uI implementation report:

- Source scans are clean only when they print zero matches. If a scan prints matches, require a classification table and fixes for every unacceptable match.
- Verification commands count as passed only after the local command exits successfully. Do not accept background timers, "will notify later", or future-tense pass claims.
- Tests must use actual local schema fields. Do not invent fields such as `financial_period.name`, `period_number`, tenant/company/branch IDs, or other natural-sounding ERP columns unless the slice explicitly adds them with migrations and tests.
- Backend warnings, blockers, statuses, and validation messages that appear in the UI must be localization-ready (`code` plus params, or existing multilingual payloads), not raw English prose.
- Frontend TSX must not contain new hardcoded visible labels, statuses, empty states, table headers, button text, or warnings. Use EN/AR dictionaries or backend multilingual master data.
- User-facing backend routes/actions must have matching permission-aware UI controls unless intentionally internal-only and documented.
- Financial reporting and period guards must use accounting/document dates and `financial_period_id`, not row timestamps such as `created_at` or `updated_at`.

## Current Verified Status

Phase 8 Operational Readiness & E2E Smoke is FULLY COMPLETE after local review and verification.

Phase 9 Staging / Production Cutover Pack is FULLY COMPLETE after local review and verification.

Phase 10 Branch/Warehouse/Stock Transfer Foundation is FULLY COMPLETE after local review and verification.

Phase 10 Stock Count/Adjustment/Warehouse Selector Completion is COMPLETE after targeted local verification.

Phase 10 landed cost/freight allocation is COMPLETE after targeted local verification.

Phase 11 Expense Management is COMPLETE after full local verification.

Phase 12 Prepaid & Accrued Expenses is COMPLETE after full local verification.

Phase 13 Payroll Foundation is COMPLETE after full local verification.

Phase 14 Rentals is COMPLETE after full local verification: rentable item register, contracts, handovers, returns, inspections, billing/deposits/charges, VAT, AR/GL posting, rental operations report, export/print UX, and close-out. Deployment process is parked until the owner/operator is ready for staging/production decisions.

Latest Phase 9 completion notes:

- Created cutover decision pack, environment checklist, deployment runbook, rollback runbook, backup/restore drill, runtime processes guide, go-live acceptance checklist, and final cutover report.
- Updated `README.md`, `IMPLEMENTATION_STATUS.md`, `NEXT_TASKS.md`, `CONTINUE_HERE.md`, `CHuNGELOG.md`, and `spec/DEPLOYMENT.md`.
- Verification passed: full PHPUnit suite (554 tests, 551 passed, 3 skipped, 4,068 assertions), Phase 8 suite (6/6), Concurrency suite, required stress commands, token GC, Pint, TypeScript typecheck, and Vite build.

Latest Phase 8 completion notes:

- Created policy-safe Phase 8 prompt files that avoid private environment values, provider account setup, GitHub uctions requirements, tenant/company/branch scope, and new ERP business modules.
- Refreshed `spec/DEPLOYMENT.md` for Laravel + Inertia + PostgreSQL.
- Added operational readiness tests for `/health`, scheduler `tokens:gc --batch=100`, and queue baseline tables.
- Added Inertia route smoke tests for login, dashboard, reports hub, tax codes, VAT register, and permission denial.
- Fixed VAT-to-GL reconciliation same-day date filtering and raw aggregate compatibility.
- Verification passed: full PHPUnit suite (554 tests, 551 passed, 3 skipped, 4,068 assertions), Phase 8 suite (6/6), Concurrency suite, required stress commands, token GC, Pint, TypeScript typecheck, and Vite build.

Previous Phase 7 detail:

Phase 7 Slice 3 (Sales Output VAT Integration) is FULLY COMPLETE after local review and verification.

Phase 7 Slice 2 (Tax Code & Tax Rate Foundation) is FULLY COMPLETE after local review.

Latest Phase 7 Slice 3 local completion notes:

- Added database migration `2026_08_23_090000_create_phase7_slice3_sales_tax_columns.php` with sales tax columns (`tax_amount_minor` on `customer_invoice`, `customer_credit_note`, `sales_return`; `tax_code_id`, `tax_rate_bps`, `tax_amount_minor`, `gross_amount_minor` on lines).
- Updated Eloquent models (`CustomerInvoice`, `CustomerInvoiceLine`, `CustomerCreditNoteLine`, `SalesReturnLine`) with integer tax casts, fillables, and `taxCode` BelongsTo relations.
- Enhanced `CustomerInvoiceService`: Injected `TaxCalculationService`, computed line tax amounts as of `invoice_date`, updated draft totals (`subtotal_minor`, `tax_amount_minor`, `total_minor`), and posted balanced JVs (Dr `ar_control` for gross total, Cr `sales_revenue` for net subtotal, Cr `output_tax_payable` for output VAT).
- Enhanced `CustomerCreditNoteService`: Preserved original invoice line tax snapshots (`tax_code_id`, `tax_rate_bps`) for linked credit note lines, calculated tax for standalone lines using `TaxCalculationService`, and posted output VAT reversal (Dr `sales_returns` for net, Dr `output_tax_payable` for tax, Cr `ar_control` for gross).
- Enhanced `SalesReturnService`: Preserved original customer invoice line tax fields onto `sales_return_line`.
- Updated Controllers (`CustomerInvoiceController`, `CustomerCreditNoteController`, `SalesReturnController`) to pass active `taxCodes` to Inertia views.
- Updated TSX views (`CustomerInvoices.tsx`, `CustomerCreditNotes.tsx`, `SalesReturns.tsx`) to render tax code selection, tax breakdown, and gross amounts.
- Built `SalesTaxPostingStressCommand.php` (`php artisan accounting:sales-tax-stress`) verifying concurrent sales tax posting and credit note reversal idempotency.
- Built feature test suite `Phase7Slice3SalesOutputVatTest.php` (5/5 tests passed / 23 assertions).
- Verification: Pint passed, `php artisan test --filter=Phase7Slice3` passed (5/5 tests), `php artisan test --filter=Phase7` passed (12/12 tests), `php artisan accounting:sales-tax-stress` passed cleanly, `npm run typecheck` passed (0 errors), `npm run build` built cleanly.

Phase 6 Slice 3 (Capitalization and Opening Asset Posting) is FULLY COMPLETE after local review.

Phase 6 Slice 4 (Depreciation Schedule Engine) is FULLY COMPLETE after local review.

Phase 6 Slice 5 (Depreciation Run Posting) is FULLY COMPLETE after local review.

Phase 6 Slice 6 (Fixed Asset Disposal, Sale, Scrap, and Reversal Workflow) is FULLY COMPLETE after local review.

Phase 6 Slice 7 (Fixed Asset Reports, UX, Export/Print, and Close-Out) is FULLY COMPLETE after local correction.

Latest Phase 6 Slice 7 local correction notes:

- Added `FixedAssetReportService` so register, net book value, depreciation schedule, depreciation run history, and disposal history reports read service-calculated values.
- Corrected `FixedAssetReportController` to expose five report pages and five CSV exports; exports preserve integer minor-unit values and require `reports.view`, `view_financials`, and either `reports.export` or `fixedAssets.export`.
- Added missing standalone Net Book Value and Depreciation Run History report pages.
- Replaced new fixed-asset report visible TSX text with EN/AR dictionary keys and removed `locale ===` bilingual literals.
- Removed `/100` formatting from Slice 7 report pages/exports; frontend money display formats integer minor units as strings.
- Added `Phase6Slice7FixedAssetReportsTest.php` (6/6 tests / 153 assertions) covering page authorization, Inertia props, CSV export permissions, minor-unit CSV output, source scans, and no unsupported scope columns.
- Full verification gate executed cleanly: Pint passed, full PHPUnit suite 514 tests / 511 passed / 3 skipped / 3855 assertions, Phase 6 suite 64/64 tests / 456 assertions, Concurrency test suite 7/7 passed, PostgreSQL stress commands passed cleanly (core accounting plus 50-worker fixed asset depreciation and disposal stress), `npm run typecheck` passed (0 errors), `npm run build` completed cleanly, migrations were up to date, and `tokens:gc --batch=100` completed.

Latest Phase 6 Slice 6 local correction notes:

- Added forward hardening migration `2026_08_23_071000_enforce_fixed_asset_disposal_integrity.php` enforcing one posted disposal per asset and database immutability for posted disposal financial fields.
- Corrected disposal idempotency so duplicate post requests replay safely while a corrected new disposal can be posted after reversal.
- Locked fixed asset depreciation schedules during disposal, blocked disposal before already posted depreciation periods, skipped planned schedules at/after disposal date, and restored those skipped schedules on reversal.
- Removed hardcoded visible text from the new disposal pages/modal and corrected the active sidebar key to `fixed-assets-disposals.index`.
- Expanded `Phase6Slice6FixedAssetDisposalTest.php` to 15/15 tests / 60 assertions, including DB immutability, delete blocking, no unsupported scope columns, backdated disposal blocking, and repost after reversal.
- Full verification gate executed cleanly after Slice 6 review: Pint passed, full PHPUnit suite 508 tests / 505 passed / 3 skipped / 3702 assertions, Phase 6 suite 58/58 tests / 303 assertions, Concurrency test suite 7/7 passed, PostgreSQL stress commands passed cleanly (including 50-worker fixed asset disposal stress), `npm run typecheck` passed (0 errors), `npm run build` completed cleanly, and `tokens:gc --batch=100` completed.

Latest Phase 6 Slice 5 local correction notes:

- Built `fixed_asset_depreciation_run` database migration with PostgreSQL check constraints `chk_fadr_status` and `chk_fadr_amounts`, plus added `depreciation_run_id` foreign key on `fixed_asset_depreciation_schedule`.
- Added `2026_08_23_061000_harden_fixed_asset_depreciation_schedule_run_link_immutability.php` so posted schedule rows cannot have their `depreciation_run_id` changed after posting.
- Built `FixedAssetDepreciationPostingService` with `PeriodGuard::assertPeriodOpenForPostingWithLock` enforcement, row locks, idempotency claim handling, balanced journal posting (Dr `depreciation_expense` / Cr `accumulated_depreciation`), and reversal via `ReversalService` marking schedule rows `reversed` while preserving original run/journal links.
- Built `FixedAssetDepreciationRunController` and Inertia React views (`DepreciationRuns/Index.tsx`, `DepreciationRuns/Show.tsx`, `DepreciationRuns/Preview.tsx`) with dictionary translations, preview workflow, and permissions mapping.
- Created concurrency stress command `accounting:fixed-asset-depreciation-stress --workers=50`.
- Built `Phase6Slice5DepreciationRunTest.php` feature suite (10/10 tests passed / 44 assertions).
- Full verification gate executed cleanly: Pint passed, full PHPUnit suite 493 tests / 490 passed / 3 skipped / 3637 assertions, Concurrency test suite 7/7 passed, PostgreSQL stress commands passed cleanly (including 50-worker depreciation run concurrency test), `npm run typecheck` passed (0 errors), `npm run build` completed cleanly.

Latest Phase 6 Slice 2 local correction notes:

- Fixed Asset create/edit/category forms now submit nested multilingual `name.en` / `name.ar` payloads instead of local-only `name_en` / `name_ar` fields.
- New Fixed Asset TSX pages use dictionary-backed labels, buttons, statuses, headings, confirmation prompts, and field labels.
- Manual register activation is blocked; fixed assets remain `draft` until workflow-owned capitalization changes them to `active`. Future workflow statuses `fully_depreciated` and `disposed` remain reserved for later depreciation/disposal services.
- Fixed asset creation validates `currency` with `size:3` and `exists:currency,code`.
- Added regression coverage for invalid currency rejection, blocked manual active-status requests, and blocked future-status updates.
- Verification: migrations up to date, `vendor/bin/pint --test`, Slice 2 suite 9/9 / 71 assertions.

Latest Phase 6 Slice 3 local correction notes:

- `FixedAssetCapitalizationService` now uses fixed asset row locking plus capitalization state for retry-safe idempotency, avoiding stale failed idempotency keys after a closed-period attempt.
- Manual capitalization still posts through `PostingEngine` and uses `fixed_asset_cost` / `fixed_asset_clearing`, but generated journal descriptions and line memos are machine-readable keys instead of raw English prose.
- Non-draft uncapitalized assets cannot be capitalized, active assets cannot be edited or updated through generic register routes, and an already-capitalized asset cannot be recapitalized with a different mode.
- Fixed Asset detail UI text is dictionary-backed, including confirmation prompts, headings, status labels, and attachment byte suffixes.
- Verification: migrations up to date, `vendor/bin/pint --test`, Slice 3 suite 11/11 / 64 assertions, full PHPUnit suite 470 tests / 467 passed / 3 skipped / 3519 assertions, Concurrency testsuite 7/7, `accounting:concurrency-stress --workers=50`, `npm run typecheck`, and `npm run build`.

Latest Phase 6 Slice 4 local correction notes:

- `FixedAssetDepreciationEngineService` starts depreciation in the month after `in_service_date` according to the owner-approved policy, with SQLite/PostgreSQL-safe `Y-m-d` financial period comparisons.
- Schedule generation is restricted to active fixed assets; reads remain side-effect free and do not generate schedules.
- Added database-level immutability trigger migration for posted depreciation schedule financial fields while preserving future reversal readiness.
- Fixed Asset detail depreciation schedule UI uses dictionary-backed statuses, date separators, table labels, buttons, and empty states.
- Verification: migrations up to date through `2026_08_23_051000_enforce_fixed_asset_depreciation_schedule_immutability`, `vendor/bin/pint --test`, Slice 4 suite 13/13 / 64 assertions, full PHPUnit suite 483 tests / 480 passed / 3 skipped / 3588 assertions, Concurrency testsuite 7/7, PostgreSQL stress commands, `npm run typecheck`, and `npm run build`.

Historical next-step note resolved: Phase 7 is closed out, Phase 8 is complete, Phase 9 is complete, Phase 10 warehouse/stock-transfer foundation is complete, Phase 10 stock count/adjustment/warehouse selector completion is complete, Phase 10 branch cash/bank treasury transfer is complete, Phase 10 fixed asset movement history is complete, Phase 10 branch operational reports are complete, branch profitability is complete, branch-specific GL mapping overrides are complete, branch-aware approval rules are complete, landed cost/freight allocation is complete, Phase 11 Expense Management is complete, Phase 12 Prepaid & Accrued Expenses is complete, Phase 13 Payroll Foundation is complete, Phase 14 Rentals are complete and verified, and Phase 15 Product Hardening is closed with Slices 1-190 complete. Current continuation should open a new bounded phase/slice for the next product capability or UX initiative, without reopening deployment until owner/operator cutover decisions are ready.

Latest Phase 6 Slice 1 notes:

- Created `PHASE_6_FIXED_ASSETS_POLICY_DECISION.md` containing Arabic executive summary, technical overview, 12 policy dimensions, GL mapping strategy, RBAC permission specifications, owner decision checklist, recommended defaults, and future slice roadmap.
- Recommended straight-line depreciation, useful life in months, optional salvage value defaulting to 0, depreciation starting in month after in-service date, opening asset registration without GL entries, and acquisition via Fixed Asset Clearing account (`fixed_asset_clearing`).
- Preserved docs-only execution: 0 migrations, 0 models, 0 services, 0 routes, 0 UI components, 0 seeders, 0 commands, and 0 tests added. Status marked as `OWNER DECISION REQUIRED`.
- Slices 2-7 have implemented the register foundation, capitalization/opening posting, depreciation schedules, depreciation run posting, disposal workflows, and reports/export/print close-out.

Latest Phase 5 Slice 6 close-out notes:

- Added permission-aware Print action buttons (`reports.print` + `view_financials`) to `BalanceSheet.tsx`, `IncomeStatement.tsx`, and `CashFlow.tsx`.
- Created `Phase5Slice6FinalCloseOutTest.php` verifying CSV export streaming, service total matching, authorization enforcement, route access contracts, and schema-field fidelity (4 passing tests / 30 assertions after local review).
- Removed duplicate `"app.accounting"` key from `en.json` and `ar.json`.
- Executed verification after local review: migrations up to date, full PHPUnit test suite (450 tests / 447 passed / 3 skipped / 3374 assertions), Concurrency testsuite (7/7), all accounting/Phase 3 stress and integrity commands, token GC, Pint lint check, TypeScript typecheck, and Vite build.
- Created `PHASE_5_FINAL_VERIFICATION_REPORT.md` close-out artifact.

Latest Phase 5 Slice 4 local correction notes:

- Period close metadata exists on `financial_period`: `closed_by`, `closed_at`, `reopened_by`, `reopened_at`, `close_note`.
- `PeriodGuard` locks target FinancialPeriod rows before posting side effects; PostingEngine remains the final safety net.
- Delivery Note and Goods Receipt stock posting now resolve and lock the date-covered FinancialPeriod before inventory movement posting.
- Close-readiness includes approved-but-unposted invoices, bills, sales returns, customer credit notes, purchase returns, and supplier adjustment notes.
- Periods UI no longer has visible English TSX fallbacks for close/reopen controls; blocker entity/status labels are dictionary-backed.
- Cheque period validation treats `reopened` as postable.
- Phase 4 Slice 10 settlement tests are pinned to their document date to avoid machine-date flakiness around `settled_at`.
- Local verification after correction: full suite 446 tests / 443 passed / 3 skipped / 3344 assertions; `Phase5Slice4PeriodCloseTest.php` 13/13 passing tests, 37 assertions; TypeScript typecheck, Vite build, and stress commands passed.

Previous Phase 5 Slice 3 local correction notes:

- Cash Flow uses posted `ledger_entry.entry_date` for report ranges, not `created_at` / `updated_at`.
- Cash-flow classifications are explicit: account override first, then `financial_statement_line.cash_flow_activity`, then unclassified.
- Active cash/bank GL accounts cannot be assigned a direct cash-flow activity override; cash movement is classified from non-cash counterparties.
- Mixed-activity cash journals are routed to unclassified warnings.
- Warning payloads use codes/parameters so `CashFlow.tsx` localizes visible text through EN/AR dictionaries.
- Phase 5 report money formatting is string-based from integer minor units and avoids JS floating-point division.
- Local targeted verification: `Phase5Slice3CashFlowStatementTest.php` 9/9 passing tests, 46 assertions.

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
  - Manual tax stored in integer basis points with exact manual amount override; modes `none`/`manual_rate`/`manual_amount` computed as `intdiv(($baseMinor * $rateBps) + 5000, 10000)`.
  - Credit/debit settlement is manual/open only; explicit settlement/reversal actions create no extra GL and use dedicated `receivable_entry_settlement` / `payable_entry_settlement` rows.
  - Numbering keys/prefixes `SR-`, `CN-`, `PRT-`, `SuN-`; permissions `sales.returns`, `sales.credit_notes`, `sales.invoice_revisions`, `purchasing.returns`, `purchasing.adjustment_notes`; attachment registry entries for all five entities.
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
  - global `REC-YYYY-XXXXX` and `PuY-YYYY-XXXXX` numbering.
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
  - `reports.view` permission, Reports Hub, customer/supplier statements, AR/AP aging, Cash Book, Bank Book, Cheque Register, bank reconciliation status/detail, AR/AP to GL reconciliation, CSV exports, and read-only report services under `upp\Application\Reports`.
- Phase 3 Slice 9 PostgreSQL stress and integrity hardening:
  - `accounting:phase3-integrity-check` audit command.
  - `accounting:phase3-stress` orchestrator command.
  - PostgreSQL concurrency stress coverage across all Phase 3 workflows.
  - `Phase3Slice9StressIntegrityTest` 6/6 tests, 262 assertions.
  - read-only report integrity verified.
- Phase 3 Slice 10 Close-Out & Final Verification Gate:
  - Repository documentation audit, status synchronization, final verification gate execution, and `PHASE_3_FINAL_VERIFICATION_REPORT.md`.

Latest verified commands:

Phase 15 Product Hardening Slice 55:

```powershell
cd laravel
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure
php artisan test --filter=AccountingCoreTest --stop-on-failure
php artisan test --stop-on-failure
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:allocation-concurrency-stress --workers=50
php artisan accounting:settlement-concurrency-stress --workers=50
php artisan accounting:cheque-concurrency-stress --workers=50
php artisan accounting:bank-reconciliation-concurrency-stress --workers=50
php artisan accounting:stock-transfer-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan accounting:fixed-asset-depreciation-stress --workers=50
php artisan accounting:fixed-asset-disposal-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan accounting:phase3-stress --workers=20
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Latest Phase 15 results:

- Last full Laravel suite pass remains Slice 57: 720 tests, 717 passed, 3 skipped, 17923 assertions.
- Phase 15 product hardening test after Slice 190: 192 tests, 25644 assertions.
- Slice 190 global Inertia page control/navigation/type guard with native date-input coverage: 1 test, 1 assertion.
- Slice 188 global Inertia page button accessible-action guard: 1 test, 1 assertion.
- Full Pages button accessibility scanner after Slice 188: `TOTAL_FILES=0`, `TOTAL_MISSING=0`.
- Slice 187 report/tax-code/stock-balance accessible-action guard: 1 test, 258 assertions.
- Slice 186 core accounting workflow accessible-action guard: 1 test, 136 assertions.
- Slice 185 delivery/goods-receipt/purchase-return accessible-action guard: 1 test, 255 assertions.
- Slice 184 notification/tax-rate accessible-action guard: 1 test, 165 assertions.
- Slice 183 fixed-asset master-data accessible-action guard: 1 test, 154 assertions.
- Slice 182 credit/adjustment note modal accessible-action guard: 1 test, 248 assertions.
- Slice 181 sales/purchase order modal accessible-action guard: 1 test, 248 assertions.
- Slice 180 catalog master-data accessible-action guard: 1 test, 391 assertions.
- Slice 179 login accessible-action/dev-credentials guard: 1 test, 103 assertions.
- Slice 178 sales-return modal accessible-action guard: 1 test, 111 assertions.
- Slice 177 supplier-bill modal accessible-action guard: 1 test, 37 assertions.
- Slice 176 audit-log accessible-action guard: 1 test, 20 assertions.
- Slice 175 journal-detail financial-action guard: 1 test, 16 assertions.
- Slice 174 customer-invoice modal accessible-action guard: 1 test, 38 assertions.
- Slice 173 landed-cost lifecycle accessible-action guard: 1 test, 22 assertions.
- Slice 172 fixed-asset detail financial-action guard: 1 test, 31 assertions.
- Slice 171 financial mapping/fiscal period accessible-action guard: 1 test, 40 assertions.
- Slice 170 user/role security accessible-action guard: 1 test, 40 assertions.
- Slice 169 accounting setup accessible-action guard: 1 test, 41 assertions.
- Slice 168 accounting taxonomy accessible-action guard: 1 test, 38 assertions.
- Slice 167 foundation settings accessible-action guard: 1 test, 37 assertions.
- Slice 166 master-data action accessible-name guard: 1 test, 64 assertions.
- Slice 165 AR/AP settlement accessible-name guard: 1 test, 22 assertions.
- Slice 164 AR/AP allocation accessibility/restricted/scroll guard: 1 test, 26 assertions.
- Slice 163 Treasury Transfer action accessibility/scroll guard: 1 test, 29 assertions.
- Slice 162 AR/AP receipt/payment/opening-balance accessible-name guard: 1 test, 48 assertions.
- Slice 161 cheque/bank-reconciliation modal accessible-name guard: 1 test, 38 assertions.
- Slice 160 cheque/bank-reconciliation action-cell guard: 1 test, 89 assertions.
- Slice 159 expense schedule/depreciation-run action-cell guard: 1 test, 84 assertions.
- Slice 158 inventory/rental/payroll/expense action-cell guard: 1 test, 154 assertions.
- Slice 157 invoice/return action-cell guard: 1 test, 128 assertions.
- Slice 157 financial post-action permission guard: 1 test, 36 assertions.
- Slice 156 sales/purchasing document action-cell guard: 1 test, 90 assertions.
- Slice 155 AR/AP restricted-action guard: 1 test, 40 assertions.
- Slice 154 JournalDetail and OpeningBalances posting-readiness guards: 2 tests, 899 assertions.
- Slice 153 pagination-link typing guard: 1 test, 504 assertions.
- Slice 152 returns/adjustments/landed-cost select-control guard: 1 test, 89 assertions.
- Slice 149 Sales/Purchase Order select-control guard: 1 test, 26 assertions.
- Slice 148 Settings attachment entity select-control guard: 1 test, 20 assertions.
- Slice 147 Rental handover/return line select-control guard: 1 test, 20 assertions.
- Slice 146 Fixed Asset workflow select-control guard: 1 test, 22 assertions.
- Slice 145 Product Catalog select-control guard: 1 test, 38 assertions.
- Slice 144 accounting mapping select-control guard: 1 test, 19 assertions.
- Slice 143 tax master select-control guard: 1 test, 15 assertions.
- Slice 142 fixed-asset report filter-control guard: 1 test, 33 assertions.
- Slice 141 operational report filter-control guard: 1 test, 84 assertions.
- Slice 139 financial statement export-link guard: 1 test, 21 assertions.
- Slice 140 AR/AP receipt/payment cash-bank type-control guard: 1 test, 12 assertions.
- Phase 5 financial statement suites passed after Slice 139: Balance Sheet/Income Statement 8 tests, 54 assertions; Cash Flow 9 tests, 46 assertions.
- Phase 3 receipt/payment suite passed after Slice 140: 14 tests, 71 assertions, 2 skipped.
- Bank/cheque localization guard after Slice 84: 1 test, 358 assertions.
- Phase 3 cheque and bank reconciliation feature suites after Slice 84: 19 tests, 97 assertions.
- Concurrency suite after Slice 84: 7 tests, 16 assertions.
- PostgreSQL cheque and bank-reconciliation stress commands passed after Slice 84.
- State-changing route security guard: 1 test, 550 assertions; all writable routes are auth-gated and permission-protected or deliberately allowlisted. AR/AP posting confirmation guard: 1 test, 80 assertions. Invoice source-document typing guard: 1 test, 15 assertions. Pagination-link typing guard: 1 test, 26 assertions. Sales/purchasing flash localization guard: 1 test, 121 assertions. Controller success-flash localization guard: 1 test, 425 assertions. Backend guard/error localization guard: 1 test, 17 assertions. Financial service, AR/AP cash-bank, and treasury-transfer validation localization guard: 1 test, 433 assertions. Branch approval rule localization guard: 1 test, 22 assertions. Tax service localization guard: 1 test, 132 assertions. Expense service localization guard: 1 test, 301 assertions. Payroll service localization guard: 1 test, 253 assertions. Inventory workflow localization guard: 1 test, 297 assertions. Inventory costing localization guard: 1 test, 68 assertions. Rental item/contract localization guard: 1 test, 171 assertions. Rental fulfillment localization guard: 1 test, 90 assertions. Rental invoice localization guard: 1 test, 120 assertions. Fixed-asset service localization guard: 1 test, 413 assertions. Sales/purchasing invoice localization guard: 1 test, 389 assertions. Returns/adjustment localization guard: 1 test, 487 assertions. Order/fulfillment localization guard: 1 test, 297 assertions. Catalog/customer/supplier localization guard: 1 test, 120 assertions. Phase 3 master-data suite: 14 tests, 58 assertions. Phase 4 catalog suite: 12 tests, 66 assertions. Phase 4 sales order suite: 15 tests, 72 assertions. Phase 4 purchase order suite: 16 tests, 74 assertions. Phase 4 fulfillment suite: 19 tests, 140 assertions. Phase 4 customer invoice suite: 19 tests, 84 assertions. Phase 4 supplier bill suite: 19 tests, 98 assertions. Phase 4 returns/credit notes suite: 40 tests, 237 assertions. Phase 7 sales/purchasing VAT suites: 9 tests, 48 assertions. Phase 6 fixed-asset suites passed after Slice 78. Phase 10 fixed-asset movement suite: 5 tests, 50 assertions. Phase 4 inventory costing suite: 14 tests, 99 assertions, 1 skipped. Phase 10 landed-cost allocation suite: 5 tests, 40 assertions. Phase 14 rental foundation suite: 16 tests, 159 assertions. Phase 14 rental billing suite: 8 tests, 56 assertions. Payroll feature suite: 6 tests, 90 assertions. Phase 10 stock workflow tests: 10 tests, 144 assertions.
- Concurrency suite: 7 tests, 16 assertions.
- `concurrency:stress --workers=100`, core accounting, allocation, settlement, cheque, bank reconciliation, stock transfer, inventory, fixed asset depreciation, fixed asset disposal, Phase 3 stress/integrity, and `tokens:gc --batch=100` passed.
- Console command and seeder fixed-currency scan passed with zero hardcoded `EGP`/`USD` matches under `app/Console/Commands` and `database/seeders`.
- Visible financial post actions now match backend `view_financials` authorization requirements, and return/credit-note/adjustment pages use route-specific permissions.
- Inventory stock count/adjustment dense tables now use canonical missing-warehouse labels, and stock adjustment value deltas are formatted with the adjustment currency.
- Pint, TypeScript typecheck, and Vite production build passed after Slice 190.
- Monolithic full Laravel suite was attempted after Slice 58 but exceeded the local timeout budget during the broader run; `Phase4Slice10ReturnsCreditNotesTest.php` passed standalone with a larger timeout, and the suite runtime needs separate tuning.

Phase 13 Payroll Foundation:

```powershell
cd laravel
php artisan migrate --force
php artisan migrate:status
php artisan db:seed --class=AccountingCoreSeeder
php artisan db:seed --class=PayrollComponentSeeder
vendor/bin/pint --test
php artisan test --filter=Phase13PayrollFoundationTest --stop-on-failure
php artisan test --filter=SecurityHardeningTest --stop-on-failure
php artisan test --filter=Phase3Slice9StressIntegrityTest --stop-on-failure
php artisan test --testsuite=Concurrency --stop-on-failure
php artisan test --stop-on-failure
php artisan concurrency:stress --workers=100
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:stock-transfer-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan accounting:phase3-integrity-check
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Latest Phase 14 foundation, contract lifecycle, and fulfillment results:

- Full Laravel suite: 643 tests, 640 passed, 3 skipped, 5516 assertions.
- Phase 14 feature tests: 16/16 tests, 159 assertions.
- Phase3Slice9StressIntegrityTest: 6/6 tests, 652 assertions.
- Rental routes: 20 routes for items, contracts, handovers, and returns.
- Concurrency suite: 7/7 tests, 16 assertions.
- Required stress commands, phase3 integrity check, token GC, Pint, TypeScript typecheck, locale JSON parse, and Vite production build passed.

Phase 5 Slice 3 correction pass:

```powershell
cd laravel
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=Phase5Slice1FinancialStatementMappingTest
php artisan test --filter=Phase5Slice2FinancialStatementsTest
php artisan test --filter=Phase5Slice3CashFlowStatementTest
php artisan test --testsuite=Concurrency
php artisan test
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Latest Phase 5 Slice 3 correction results:

- `php artisan migrate --force`: applied `2026_08_23_011000_harden_phase5_slice3_cash_flow_activity_constraints`.
- `php artisan migrate:status`: all migrations Ran through `2026_08_23_011000_harden_phase5_slice3_cash_flow_activity_constraints`.
- `vendor/bin/pint --test`: passed.
- `php artisan test --filter=Phase5Slice1FinancialStatementMappingTest`: 9 tests / 30 assertions passed.
- `php artisan test --filter=Phase5Slice2FinancialStatementsTest`: 8 tests / 8 passed / 0 skipped / 54 assertions.
- `php artisan test --filter=Phase5Slice3CashFlowStatementTest`: 9 tests / 9 passed / 0 skipped / 46 assertions.
- `php artisan test --testsuite=Concurrency`: 7 tests / 16 assertions passed.
- `php artisan test`: 433 tests / 430 passed / 3 skipped / 3307 assertions.
- `php artisan tokens:gc --batch=100`: deleted sessions=0 password_reset_tokens=0 idempotency_keys=0.
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

Previous full-track baseline results:

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
activity_log: 3135
users: 2
jobs: 0
failed_jobs: 0
rentable_item: 0
rentable_item_status_event: 0
employee: 0
payroll_component: 5
payroll_run: 0
payroll_run_line: 0
journal_entry: 507
ledger_entry: 1014
```

`activity_log`, `journal_entry`, and `ledger_entry` can vary because local seeders and stress commands create real records outside PHPUnit transactions. Payroll and rental operational records are normally zero after the transactional test suite unless demo/operational data is created manually.

## Phase 3 Completion & Phase 4 Start

**Phase 3 Slices 1–10 are 100% complete.** Phase 3 AR/AP + Cash/Bank/Cheques track is fully closed out for the agreed scope. See `PHASE_3_FINAL_VERIFICATION_REPORT.md`.

ull 10 bounded prompt files (`PHASE_3_SLICE_1_GEMINI_PROMPT.md` through `PHASE_3_SLICE_10_GEMINI_PROMPT.md`) have been executed and remain as historical traceability reference.

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

Phase 6 planning is prepared:

- `PHASE_6_FIXED_ASSETS.md`
- `PHASE_6_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_6_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_6_SLICE_3_GEMINI_PROMPT.md`
- `PHASE_6_SLICE_4_GEMINI_PROMPT.md`
- `PHASE_6_SLICE_5_GEMINI_PROMPT.md`
- `PHASE_6_SLICE_6_GEMINI_PROMPT.md`
- `PHASE_6_SLICE_7_GEMINI_PROMPT.md`

Phase 6 Slices 1-7 are complete. No Phase 6 implementation slice remains.

Phase 7 planning is prepared:

- `PHASE_7_TAX_VAT.md`
- `PHASE_7_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_7_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_7_SLICE_3_GEMINI_PROMPT.md`
- `PHASE_7_SLICE_4_GEMINI_PROMPT.md`
- `PHASE_7_SLICE_5_GEMINI_PROMPT.md`
- `PHASE_7_SLICE_6_GEMINI_PROMPT.md`
- `PHASE_7_SLICE_7_GEMINI_PROMPT.md`

Phase 7 is 100% complete and verified. Do not restart Phase 7.

Other possible owner choices:

- **Optional: E2E Browser Testing** (Playwright / Dusk smoke testing).
- **Optional: Production Deployment Readiness** (Nginx, Supervisor, Redis, Backup strategies).

Not started / blocked; each requires a bounded owner prompt or decision before implementation:

- Rental billing, deposits, charge posting, revenue/VAT/AR integration, and rental reports are not implemented yet and must be delivered as bounded Phase 14 slices.
- Payroll future extensions such as payslips, salary payment execution, payroll reports, loans/advances, attendance inputs, and statutory payroll tax/social insurance rules.
- Physical year-end closing journal / Retained Earnings GL posting remains blocked until explicit owner approval.

Going forward, keep these invariants:

- no tenant/company scope
- no branch tenancy, branch security boundary, currentBranch context, or blanket branch scope
- explicit branch operational references are allowed only through bounded owner-approved slices
- no float money math
- posted journal and ledger data immutable
- corrections by reversal
- numbering atomic
- posting idempotent
- Phase 3 audit uses the owner-approved Spatie Activitylog decision through the existing `AuditLogger` API
- attachment authorization through entity registry
- notifications targeted to users


