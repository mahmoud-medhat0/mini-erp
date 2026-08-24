# Changelog

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


All notable changes. Format: Keep a Changelog; SemVer per phase.

### Added - Phase 9 Final Cutover Close-Out (2026-08-24)
- Created `PHASE_9_FINAL_CUTOVER_REPORT.md` documenting all completed Phase 9 slices, files created/updated, owner/operator decisions still pending, runbook status, verification command results, source-scan classifications, and no-new-business-module confirmation.
- Created and linked `spec/GO_LIVE_ACCEPTANCE.md` covering pre-go-live approvals, non-secret environment sanity checks, login/dashboard/report/tax/attachment/notification smoke checks, permission/security checks, scheduler/queue readiness, backup/restore evidence, and formal go/no-go sign-off.
- Updated `README.md`, `IMPLEMENTATION_STATUS.md`, `NEXT_TASKS.md`, and `CONTINUE_HERE.md` to mark Phase 9 complete and verified.
- Final verification passed: full PHPUnit suite (554 tests, 551 passed, 3 skipped, 4,068 assertions), Phase 8 suite, Concurrency suite, required stress commands, token GC, Pint, TypeScript typecheck, and Vite production build.

### Added — Phase 9 Slice 5 Runtime Processes, Storage, Mail, and Logs (2026-08-24)
- Created `spec/RUNTIME_PROCESSES.md` detailing provider-neutral runtime operations documentation for Artisan Scheduler external cron trigger requirement, queue worker supervision (Supervisor and systemd templates) & `queue:restart` policy, failed job inspection/retry workflow (`failed_jobs`), `tokens:gc --batch=100` hourly garbage collection schedule, attachment storage configuration (`storage/app/private` vs private S3 bucket), mail delivery modes (`log`, `smtp`, `ses`), logging retention/rotation expectations (`LOG_CHANNEL=daily`), HTTP health endpoint (`/health`), and operator 5-point post-restart verification checklist.
- Refreshed `spec/DEPLOYMENT.md` referencing `RUNTIME_PROCESSES.md`.
- Operations documentation slice: zero server configuration performed, zero provider account setup.
- Executed verification scans (`git diff --stat`, sensitive-value `rg` scan, scope assumption `rg` scan).

### Added — Phase 9 Slice 4 Backup and Restore Drill Pack (2026-08-24)
- Created `spec/BACKUP_RESTORE_DRILL.md` detailing PostgreSQL backup objectives (RPO/RTO), backup frequency options (daily dumps, continuous WAL archiving PITR, hybrid cloud snapshots), retention options (30-day, 90-day, 7-year), restore test frequency options, staging restore drill workflow with generic placeholder `pg_dump`/`pg_restore`/`psql` examples, required post-restore verification suite (`migrate:status`, `Phase8` tests, `accounting:phase3-integrity-check`, `tokens:gc --batch=100`, `/health`), production restore approval protocol, and restore drill log template.
- Refreshed `spec/DEPLOYMENT.md` referencing `BACKUP_RESTORE_DRILL.md`.
- Operations documentation slice: zero production command execution, zero real credentials or database connection strings.
- Executed verification scans (`git diff --stat`, sensitive-value `rg` scan, destructive text classification scan).

### Added — Phase 9 Slice 3 Deployment and Rollback Runbooks (2026-08-24)
- Created `spec/DEPLOYMENT_RUNBOOK.md` detailing a 12-step provider-neutral deployment workflow (pre-release checks, maintenance window approval, source/artifact preparation, dependency installation, asset build, environment validation, database migration, cache optimization, scheduler/queue worker restart, health check, smoke verification, post-release monitoring).
- Created `spec/ROLLBACK_RUNBOOK.md` detailing rollback trigger scenarios, approval authority, code/asset rollback steps, migration rollback policies with production safety rules against unqualified destructive commands, database backup restore escalation path (`pg_restore` / `psql`), queue/scheduler restart notes, and incident post-mortem requirements.
- Refreshed `spec/DEPLOYMENT.md` referencing runbooks.
- Runbook slice: zero application code, database migrations, controllers, services, routes, models, React views, tests, or configuration behavior were modified. Zero production execution performed.
- Executed verification scans (`git diff --stat`, sensitive-value `rg` scan, destructive command text classification scan).

### Added — Phase 9 Slice 2 Environment & Secrets Checklist (2026-08-24)
- Created `spec/ENVIRONMENT_CHECKLIST.md` documenting environment variable rules, purpose, required environments, value category, owner/operator notes, and validation methods for app identity, debug mode, app key, locale, PostgreSQL connection, Argon2id hashing, bootstrap seeding, session/cache/queue drivers, storage disks, mail transport, and logging channels.
- Audited `laravel/.env.example` template and added missing comments for `DB_SSLMODE` and `MAIL_ENCRYPTION`.
- Refreshed `spec/DEPLOYMENT.md` referencing `ENVIRONMENT_CHECKLIST.md`.
- Documentation & template-audit slice: zero real passwords, secret keys, tokens, or credentials added to codebase or documentation.
- Executed verification scans (`git diff --stat`, sensitive-value `rg` scan, historical Next.js/Prisma `rg` scan).

### Added — Phase 9 Slice 1 Cutover Decision Pack (2026-08-24)
- Created `PHASE_9_CUTOVER_DECISION_PACK.md` containing owner/operator decision pack for staging and production cutover in English and Arabic.
- Documented staging vs production cutover definitions, technology stack, deployment responsibilities by role, 12-item pending owner/operator decision matrix (hosting, DB owner, domain/SSL, scheduler, queue manager, storage, mail, log review, restore frequency, staging availability, cutover window, rollback approver), go/no-go criteria, rollback approval process, and minimum smoke acceptance criteria.
- Docs-only slice: zero application code, database migrations, controllers, services, routes, models, React views, tests, or configuration behavior were modified.
- Ran sensitive-value and scope assumption scans (`rg` checks confirmed clean).

### Added - Phase 9 Staging / Production Cutover Prompt Set (2026-08-24)
- Created `PHASE_9_STAGING_PRODUCTION_CUTOVER.md` as the master contract for staging/production cutover.
- Created strict prompt files `PHASE_9_SLICE_1_GEMINI_PROMPT.md` through `PHASE_9_SLICE_7_GEMINI_PROMPT.md`.
- Covered cutover decisions, environment/secrets checklist, deployment and rollback runbooks, backup/restore drill, runtime processes, go-live smoke/security acceptance, and final close-out.
- Prompt wording intentionally avoids private environment values, provider account setup, GitHub Actions requirements, production command execution, new ERP business modules, and tenant/company/branch assumptions.
- Updated `NEXT_TASKS.md`, `IMPLEMENTATION_STATUS.md`, and `CONTINUE_HERE.md` so the next prepared track starts at `PHASE_9_SLICE_1_GEMINI_PROMPT.md`.

### Added — Phase 8 Slice 1 Operational Readiness Decision Pack (2026-08-24)
- Created `PHASE_8_OPERATIONAL_READINESS_DECISION.md` containing owner-facing operational readiness decision pack in English and Arabic.
- Documented current Laravel 13 + Inertia + PostgreSQL stack, required runtime services (Web Server, Asset Delivery, PostgreSQL, Scheduler Daemon, Queue Worker Daemon, File Storage, Mail Gateway), environment variable names guide, staging vs production checklist, and 9 pending owner operational decisions.
- Docs-only slice: zero application code, database migrations, controllers, services, routes, models, React views, or unit/feature tests were modified.
- Ran docs-safe verification suite (`git diff --stat` and `rg` source scans).

### Added — Phase 8 Operational Readiness & E2E Smoke Close-Out (2026-08-24)
- Created `PHASE_8_OPERATIONAL_READINESS.md` as the master contract for deployment documentation, scheduler/queue readiness, health checks, and browser smoke coverage.
- Created safe prompt files `PHASE_8_SLICE_1_GEMINI_PROMPT.md` through `PHASE_8_SLICE_5_GEMINI_PROMPT.md`.
- Created `PHASE_8_FINAL_OPERATIONAL_READINESS_REPORT.md`.
- Refreshed `spec/DEPLOYMENT.md` for the active Laravel + Inertia + PostgreSQL target and removed obsolete Next.js/Prisma deployment guidance.
- Refreshed `README.md` current status, remaining future scope, verification snapshot, and documentation entry points.
- Added `Phase8Slice3OperationalReadinessTest.php` covering `/health`, scheduler token GC registration, and queue baseline tables.
- Added `Phase8Slice4RouteSmokeTest.php` covering public login, authenticated operational routes, and report permission denial.
- Fixed VAT-to-GL reconciliation same-day ledger date filtering and aggregate compatibility in `VatToGlReconciliationService.php`.
- Updated `NEXT_TASKS.md`, `IMPLEMENTATION_STATUS.md`, and `CONTINUE_HERE.md` so Phase 8 starts with operational readiness instead of re-running Phase 7.
- Prompt wording intentionally avoids private environment values, provider account setup, new ERP business modules, and tenant/company/branch assumptions.
- Verified with full PHPUnit suite (554 tests, 551 passed, 3 skipped, 4,068 assertions), Phase 8 suite, Concurrency suite, required stress commands, token GC, Pint, TypeScript typecheck, and Vite build.

### Added — Phase 7 Slice 7 UX, Export/Print, E2E Smoke, Source Scans, and Close-Out (2026-08-23)
- Created `PHASE_7_FINAL_VERIFICATION_REPORT.md` documenting completion of all 7 slices, migration status, routes, models, permissions, GL mappings, tax posting examples, reconciliation formulas, scan classifications, test results, and stress results.
- Translated Tax & VAT Reports section in `Reports/Index.tsx` to use translation dictionary keys (`dict.app.taxes`).
- Audited all 7 required source scans (`rg` checks) confirming zero multi-tenant columns, zero out-of-scope features, clean integer money arithmetic, exact document date filtering, and zero leftover debug logs.
- Executed full 28-command verification suite sequentially: Pint passed, full 253-test PHPUnit suite passed (253/253 tests / 1,462 assertions), all 13 concurrency stress test commands passed cleanly with 100% data integrity, `npm run typecheck` passed (0 errors), `npm run build` produced clean Vite production build.
- Updated `IMPLEMENTATION_STATUS.md`, `NEXT_TASKS.md`, `CONTINUE_HERE.md`, and `CHANGELOG.md`.

### Added — Phase 7 Slice 6 Tax Period Filing and Locking Controls (2026-08-23)
- Created migration `2026_08_23_110000_create_phase7_slice6_tax_period_tables.php` defining `tax_periods` and `tax_returns` with PostgreSQL check constraints `chk_tp_status` and `chk_tr_status`.
- Created Eloquent models `TaxPeriod` and `TaxReturn` with `HasUuids` trait and relations.
- Created `TaxPeriodGuard` preventing tax-affecting document postings (`CustomerInvoiceService`, `CustomerCreditNoteService`, `SalesReturnService`, `SupplierBillService`, `SupplierAdjustmentNoteService`, `PurchaseReturnService`) for document dates falling within filed tax periods.
- Created `TaxPeriodService` managing non-overlapping monthly/quarterly tax period definitions and status transitions (`open`, `filed`).
- Created `TaxReturnService` performing draft tax return generation from VAT summary reports, transactional/row-locked return filing, and audit logging.
- Created `TaxPeriodController` and Inertia React pages `Taxes/Periods/Index.tsx` and `Show.tsx` with modal filing interface.
- Created `TaxFilingStressCommand.php` (`php artisan accounting:tax-filing-stress`) testing 50 concurrent filing workers on PostgreSQL.
- Created feature test suite `Phase7Slice6TaxFilingTest.php` (9/9 passing tests / 53 assertions).

### Added — Phase 7 Slice 5 VAT Register, Reports, and GL Reconciliation (2026-08-23)
- Created `VatRegisterReportService.php` reading posted source document tax snapshots (`customer_invoice`, `customer_credit_note`, `sales_return`, `supplier_bill`, `supplier_adjustment_note`, `purchase_return`) applying explicit sign rules and integer minor-unit arithmetic.
- Created `VatSummaryReportService.php` summarizing output and input VAT grouped by tax code with 100% register mathematical equality.
- Created `VatToGlReconciliationService.php` comparing register totals against GL ledger movement for `output_tax_payable` and `input_tax_receivable` accounts, computing signed differences, and reporting localized warning codes (`ERR_OUTPUT_TAX_ACCOUNT_NOT_MAPPED`, `ERR_INPUT_TAX_ACCOUNT_NOT_MAPPED`, `WARN_VAT_GL_MISMATCH`).
- Created `VatReportController.php` with web routes (`/reports/vat-register`, `/reports/vat-summary`, `/reports/vat-gl-reconciliation`) and streamed CSV export endpoints.
- Built Inertia React pages `VatRegister.tsx`, `VatSummary.tsx`, `VatGlReconciliation.tsx` and updated Reports Hub (`Index.tsx`).
- Updated EN/AR translation dictionaries in `en.json` and `ar.json` with tax report headers, summary labels, and localized warning codes.
- Added nav key entries `'reports.vat-register'`, `'reports.vat-summary'`, `'reports.vat-gl-reconciliation'` to `AppLayout.tsx`.
- Built feature test suite `Phase7Slice5VatReportsTest.php` (9/9 passing tests / 40 assertions, 25/25 total Phase 7 tests passing).
- Executed full verification gate cleanly: Pint passed, `php artisan test --filter=Phase7Slice5` passed (9/9 tests), `php artisan test --filter=Phase7` passed (25/25 tests), `php artisan accounting:sales-tax-stress` passed, `php artisan accounting:purchasing-tax-stress` passed, `npm run typecheck` passed (0 errors), `npm run build` built cleanly.

### Added — Phase 7 Slice 4 Purchasing Input VAT Integration (2026-08-23)
- Created database migration `2026_08_23_100000_create_phase7_slice4_purchasing_tax_columns.php` adding purchasing tax columns (`tax_amount_minor` on `supplier_bill`, `supplier_adjustment_note`, `purchase_return`; `tax_code_id`, `tax_rate_bps`, `tax_amount_minor`, `gross_amount_minor` on lines).
- Updated Eloquent models (`SupplierBill`, `SupplierBillLine`, `SupplierAdjustmentNote`, `SupplierAdjustmentNoteLine`, `PurchaseReturn`, `PurchaseReturnLine`) with integer tax casts, fillables, and `taxCode` BelongsTo relations.
- Enhanced `SupplierBillService`: Injected `TaxCalculationService`, computed line input tax amounts as of `bill_date`, updated draft totals (`subtotal_minor`, `tax_amount_minor`, `total_minor`), and posted balanced JVs (Dr `purchase_expense`/`grni_clearing` for net, Dr `input_tax_receivable` for tax, Cr `ap_control` for gross).
- Enhanced `SupplierAdjustmentNoteService`: Injected `TaxCalculationService`, preserved linked supplier bill line tax snapshots (`tax_code_id`, `tax_rate_bps`), computed line tax amounts, and posted input VAT reversal (Dr `ap_control` for gross total, Cr `purchase_returns_allowances` for net, Cr `input_tax_receivable` for tax).
- Enhanced `PurchaseReturnService`: Preserved original supplier bill line tax fields onto `purchase_return_line`.
- Updated Controllers (`SupplierBillController`, `SupplierAdjustmentNoteController`, `PurchaseReturnController`) to pass active `taxCodes` to Inertia views and validate line tax code IDs.
- Updated TSX views (`SupplierBills.tsx`, `SupplierAdjustmentNotes.tsx`, `PurchaseReturns.tsx`) to accept `taxCodes` props.
- Built `PurchasingTaxPostingStressCommand.php` (`php artisan accounting:purchasing-tax-stress`) verifying concurrent purchasing tax posting and adjustment note reversal idempotency.
- Built feature test suite `Phase7Slice4PurchasingInputVatTest.php` (4/4 passing tests / 25 assertions, 16/16 total Phase 7 tests passing).
- Executed full verification gate cleanly: Pint passed, `php artisan test --filter=Phase7Slice4` passed (4/4 tests), `php artisan test --filter=Phase7` passed (16/16 tests), `php artisan accounting:purchasing-tax-stress` passed cleanly, `npm run typecheck` passed (0 errors), `npm run build` built cleanly.

### Added — Phase 7 Slice 3 Sales Output VAT Integration (2026-08-23)
- Created database migration `2026_08_23_090000_create_phase7_slice3_sales_tax_columns.php` adding sales tax columns (`tax_amount_minor` on `customer_invoice`, `customer_credit_note`, `sales_return`; `tax_code_id`, `tax_rate_bps`, `tax_amount_minor`, `gross_amount_minor` on lines).
- Updated Eloquent models (`CustomerInvoice`, `CustomerInvoiceLine`, `CustomerCreditNoteLine`, `SalesReturnLine`) with integer tax casts, fillables, and `taxCode` BelongsTo relations.
- Enhanced `CustomerInvoiceService`: Injected `TaxCalculationService`, computed line tax amounts as of `invoice_date`, updated draft totals (`subtotal_minor`, `tax_amount_minor`, `total_minor`), and posted balanced JVs (Dr `ar_control` for gross total, Cr `sales_revenue` for net subtotal, Cr `output_tax_payable` for output VAT).
- Enhanced `CustomerCreditNoteService`: Preserved original invoice line tax snapshots (`tax_code_id`, `tax_rate_bps`) for linked credit note lines, calculated tax for standalone lines using `TaxCalculationService`, and posted output VAT reversal (Dr `sales_returns` for net, Dr `output_tax_payable` for tax, Cr `ar_control` for gross).
- Enhanced `SalesReturnService`: Preserved original customer invoice line tax fields onto `sales_return_line`.
- Updated Controllers (`CustomerInvoiceController`, `CustomerCreditNoteController`, `SalesReturnController`) to pass active `taxCodes` to Inertia views.
- Updated TSX views (`CustomerInvoices.tsx`, `CustomerCreditNotes.tsx`, `SalesReturns.tsx`) to render tax code selection, tax breakdown, and gross amounts.
- Built `SalesTaxPostingStressCommand.php` (`php artisan accounting:sales-tax-stress`) verifying concurrent sales tax posting and credit note reversal idempotency.
- Built feature test suite `Phase7Slice3SalesOutputVatTest.php` (5/5 passing tests / 23 assertions).
- Executed full 14-command verification gate cleanly: Pint passed, `php artisan test --filter=Phase7Slice3` passed (5/5 tests), `php artisan test --filter=Phase7` passed (12/12 tests), `php artisan accounting:sales-tax-stress` passed cleanly, `npm run typecheck` passed (0 errors), `npm run build` built cleanly.

### Added — Phase 7 Slice 2 Tax Code and Tax Rate Foundation (2026-08-23)
- Created database migration `2026_08_23_080000_create_phase7_slice2_tax_tables.php` creating `tax_codes` and `tax_rates` tables with PostgreSQL check constraints `chk_tc_tax_type`, `chk_tc_calc_mode`, `chk_tc_rec_mode`, and `chk_tr_rate_bps`.
- Created Eloquent models `TaxCode` and `TaxRate` with JSON name casting, UUIDs, and relations.
- Built `TaxMasterDataService` supporting tax code and tax rate CRUD, system tax code deletion protection (`is_system = true`), in-use protection, and Spatie Activitylog audit logging.
- Built `TaxCalculationService` supporting effective tax rate lookup by document date (`resolveEffectiveRate`) and integer minor-unit tax calculation (`calculateTax`) for `exclusive`, `inclusive`, and `exempt` calculation modes using deterministic half-up integer division math.
- Built `TaxCodeController` and `TaxRateController` guarded by permissions `taxes.view` and `taxes.edit`.
- Registered web routes under `/taxes/codes` and `/taxes/rates`.
- Created Inertia React pages `Taxes/Codes/Index.tsx`, `Taxes/Codes/Create.tsx`, `Taxes/Codes/Edit.tsx`, and `Taxes/Rates/Index.tsx`.
- Updated EN/AR translation dictionaries in `en.json` and `ar.json` under `taxes`.
- Updated navigation key union, permission mapping, and sidebar menu in `AppLayout.tsx`.
- Created `TaxCodeSeeder` with default `VAT_STD_14` (14.00%), `VAT_ZERO` (0.00%), and `EXEMPT` (Exempt) master records.
- Built feature test suite `Phase7Slice2TaxFoundationTest.php` (7/7 passing tests / 38 assertions).
- Executed full verification gate pass cleanly: Pint passed, `npm run typecheck` passed (0 errors), `npm run build` completed cleanly.

### Added — Phase 7 Slice 1 Tax/VAT Policy Decision Pack (2026-08-23)
- Created `PHASE_7_TAX_VAT_POLICY_DECISION.md` containing Arabic & English executive summaries, plain-language explanation of VAT concepts, comparison table for tax scope options, integer basis-points rate scale specification (`rate_bps`), tax calculation/rounding policy, sales output VAT and purchasing input VAT posting workflows, monthly tax period filing controls, 15 owner decision checklist items, and recommended implementation path.
- Docs-only slice: zero implementation code added. Verified via `git diff --stat` and source scans.

### Added — Phase 7 Tax / VAT Planning Prompts (2026-08-23)
- Added `PHASE_7_TAX_VAT.md` as the bounded master planning contract for Tax / VAT.
- Added seven strict Gemini execution prompts:
  - `PHASE_7_SLICE_1_GEMINI_PROMPT.md` Tax/VAT Policy Decision Pack.
  - `PHASE_7_SLICE_2_GEMINI_PROMPT.md` Tax Code and Tax Rate Foundation.
  - `PHASE_7_SLICE_3_GEMINI_PROMPT.md` Sales Output VAT Integration.
  - `PHASE_7_SLICE_4_GEMINI_PROMPT.md` Purchasing Input VAT Integration.
  - `PHASE_7_SLICE_5_GEMINI_PROMPT.md` VAT Register, VAT Reports, and GL Reconciliation.
  - `PHASE_7_SLICE_6_GEMINI_PROMPT.md` Tax Period Filing and Locking Controls.
  - `PHASE_7_SLICE_7_GEMINI_PROMPT.md` UX, Export/Print, Source Scans, and Close-Out.
- Updated `CONTINUE_HERE.md`, `NEXT_TASKS.md`, and `IMPLEMENTATION_STATUS.md` so the next bounded track starts with the docs-only Tax/VAT policy decision pack.
- Reconfirmed Phase 7 must preserve no tenant/company/branch scope, integer-only tax/money math, dictionary-backed UI text, detailed current permissions, Spatie Activitylog audit, PeriodGuard posting protections, and synchronous local verification before completion claims.
- No Phase 7 Laravel implementation code, migrations, routes, controllers, services, UI pages, seeders, commands, or tests were added in this planning pass.

### Added — Phase 6 Slice 7 Reports, UX, Export/Print, E2E Smoke & Close-Out (2026-08-23)
- Built `FixedAssetReportService` so fixed asset register, net book value, depreciation schedule, depreciation run history, and disposal history reports read one service-calculated source of truth.
- Rebuilt `FixedAssetReportController` with strict `reports.view` + `view_financials` report access and CSV export guarded by (`reports.export` OR `fixedAssets.export`) plus `reports.view` and `view_financials`.
- Registered report routes in `routes/web.php` for five report pages and five CSV export endpoints under `/reports/fixed-asset-*`.
- Created Inertia React report pages `Reports/FixedAssetRegisterReport.tsx`, `Reports/FixedAssetNetBookValueReport.tsx`, `Reports/FixedAssetDepreciationReport.tsx`, `Reports/FixedAssetDepreciationRunReport.tsx`, and `Reports/FixedAssetDisposalReport.tsx`.
- Integrated a permission-aware Fixed Asset Reports section into the Reports Hub (`Reports/Index.tsx`).
- Corrected CSV exports and frontend money display to preserve integer minor units without `/100`, float casts, or rounding.
- Added EN/AR dictionary keys for all new report UI labels/statuses/actions and removed new hardcoded visible TSX text.
- Added feature test suite `Phase6Slice7FixedAssetReportsTest.php` (6/6 passing tests / 153 assertions).
- Executed local verification cleanly on PostgreSQL: full suite 514 tests / 511 passed / 3 skipped / 3855 assertions, Phase 6 suite 64/64 tests / 456 assertions, Concurrency suite 7/7, core and fixed-asset PostgreSQL stress commands, Pint, typecheck, build, migrations, source scans, and token GC passed.
- Updated `PHASE_6_FINAL_VERIFICATION_REPORT.md` marking Phase 6 (Fixed Assets & Depreciation Engine) 100% COMPLETE & VERIFIED after local correction.

### Added — Phase 6 Slice 6 Fixed Asset Disposal (2026-08-23)
- Created database migration `2026_08_23_070000_create_phase6_slice6_fixed_asset_disposal_tables.php` for `fixed_asset_disposal` table with PostgreSQL check constraints `chk_fad_status` (`posted`, `reversed`), `chk_fad_type` (`sale`, `scrap`, `retirement`), and `chk_fad_amounts` (`proceeds_minor >= 0`, `net_book_value_minor >= 0`, `gain_minor >= 0`, `loss_minor >= 0`).
- Created Eloquent model `FixedAssetDisposal` with casts & relations, and updated `FixedAsset` model with `disposals` relation.
- Built `FixedAssetDisposalPostingService` domain application service supporting:
  - `previewDisposal`: real-time calculation of Net Book Value ($\text{Cost} - \text{Accum Dep}$), proceeds, gain, and loss in integer minor units.
  - `postDisposal`: locked open period guard, row locks, idempotency claim handling, asset status transition (`active` -> `disposed`), GL journal posting via `PostingEngine` (**Credit** `fixed_asset_cost`, **Debit** `accumulated_depreciation`, **Debit** `fixed_asset_clearing` for proceeds, **Debit** `fixed_asset_disposal_loss` for loss, **Credit** `fixed_asset_disposal_gain` for gain), and automatic skipping of remaining unposted depreciation schedules.
  - `reverseDisposal`: reversal via `ReversalService`, restoring asset status back to `active` and schedule statuses back to `planned`.
- Built `FixedAssetDisposalController` with actions `index`, `show`, `preview`, `store`, `reverse` guarded by Spatie RBAC permissions (`fixedAssets.view`, `fixedAssets.post`, `fixedAssets.reverse`, `view_financials`).
- Registered web routes in `routes/web.php` (`/fixed-assets-disposals`, `/fixed-assets-disposals/{id}`, `/fixed-assets/{assetId}/disposals/preview`, `/fixed-assets/{assetId}/disposals`, `/fixed-assets-disposals/{id}/reverse`).
- Built Inertia React pages `Disposals/Index.tsx` and `Disposals/Show.tsx`, and added Dispose Asset Modal in `FixedAssets/Show.tsx`.
- Added dictionary translation keys in `en.json` and `ar.json` under `fixedAssetsDisposals`.
- Updated navigation key `fixed-assets-disposals.index` and permission mapping in `AppLayout.tsx`.
- Created console command `FixedAssetDisposalStressCommand.php` (`php artisan accounting:fixed-asset-disposal-stress --workers=50`). Verified that 50 concurrent workers created exactly 1 durable disposal record on PostgreSQL.
- Added forward hardening migration `2026_08_23_071000_enforce_fixed_asset_disposal_integrity.php` enforcing one posted disposal per asset and database immutability for posted disposal financial fields.
- Built feature test suite `Phase6Slice6FixedAssetDisposalTest.php` (15/15 passing tests / 60 assertions after local review).
- Executed full verification gate pass cleanly: Pint passed, full PHPUnit suite 508 tests / 505 passed / 3 skipped / 3702 assertions, Phase 6 test suites passed (58/58 tests / 303 assertions), PostgreSQL stress commands passed cleanly (including 50-worker disposal stress), `npm run typecheck` passed (0 errors), `npm run build` completed cleanly.

### Corrected — Phase 6 Slice 6 Local Review (2026-08-23)
- Hardened `fixed_asset_disposal` at database level with a partial unique index for one active posted disposal per asset plus PostgreSQL/SQLite triggers blocking posted financial-field mutation and deletion.
- Corrected disposal idempotency so repeated duplicate requests replay safely while corrected reposting after reversal is not trapped by stale completed keys.
- Locked depreciation schedule rows during disposal posting, blocked backdated disposal before posted depreciation periods, skipped only schedules at/after the disposal date, and restored those skipped schedules on reversal.
- Removed hardcoded visible disposal text from the new React pages/modal and corrected disposal pages to use `fixed-assets-disposals.index` as the active navigation key.
- Added regression coverage for DB immutability, delete blocking, no unsupported company/branch/tenant/custodian/location scope columns, repost after reversal, and backdated disposal rejection.

### Added — Phase 6 Slice 5 Depreciation Run Posting (2026-08-23)
- Created database migration `2026_08_23_060000_create_phase6_slice5_depreciation_run_tables.php` for `fixed_asset_depreciation_run` table (with check constraints `chk_fadr_status` and `chk_fadr_amounts`) and added `depreciation_run_id` FK on `fixed_asset_depreciation_schedule`.
- Created Eloquent model `FixedAssetDepreciationRun` with casts & relations, and updated `FixedAssetDepreciationSchedule` with `depreciationRun` relation.
- Built `FixedAssetDepreciationPostingService` application service featuring:
  - Strict period guard `PeriodGuard::assertPeriodOpenForPostingWithLock` enforcing open period status with row lock.
  - Idempotency claim handling via `DatabaseIdempotencyStore`.
  - Balanced journal voucher posting via `PostingEngine`: **Dr** `depreciation_expense` / **Cr** `accumulated_depreciation`.
  - Reversal engine via `reverseDepreciationRun`, reversing JV via `ReversalService` and marking schedule statuses `reversed` while preserving original run/journal links.
- Built `FixedAssetDepreciationRunController` with actions `index`, `store`, `show`, `preview`, `reverse` guarded by permissions (`fixedAssets.view`, `fixedAssets.post`, `fixedAssets.reverse`, `view_financials`).
- Registered web routes in `routes/web.php` (`/fixed-assets-depreciation-runs`, `/fixed-assets-depreciation-runs/preview`, `/fixed-assets-depreciation-runs/{id}/reverse`).
- Added dictionary translation keys in `en.json` and `ar.json`.
- Built Inertia React pages `DepreciationRuns/Index.tsx`, `DepreciationRuns/Show.tsx`, and `DepreciationRuns/Preview.tsx`, and updated navigation in `AppLayout.tsx`.
- Created console command `FixedAssetDepreciationStressCommand.php` (`php artisan accounting:fixed-asset-depreciation-stress --workers=50`).
- Added forward hardening migration `2026_08_23_061000_harden_fixed_asset_depreciation_schedule_run_link_immutability.php` so posted schedule rows cannot have their `depreciation_run_id` changed after posting.
- Built feature test suite `Phase6Slice5DepreciationRunTest.php` (10/10 passing tests / 44 assertions).
- Executed full verification gate cleanly: Pint passed, full PHPUnit suite 493 tests / 490 passed / 3 skipped / 3637 assertions, Concurrency test suite 7/7 passed, PostgreSQL stress commands passed cleanly (including 50-worker depreciation run concurrency test), `npm run typecheck` passed (0 errors), `npm run build` completed cleanly.

### Corrected — Phase 6 Slice 5 Local Review (2026-08-23)
- Preserved posted depreciation schedule auditability during reversal by marking schedules `reversed` instead of resetting them to `planned` or clearing run/journal links.
- Removed unused JV sequence allocation from depreciation run posting; `PostingEngine` remains the sole allocator for JV numbers.
- Added missing GL mapping regression coverage and DB immutability coverage for posted schedule `depreciation_run_id`.
- Added the missing depreciation run preview page and removed hardcoded visible period/status text from the depreciation run UI.
- Updated the fixed asset depreciation stress command output to report unique durable runs instead of implying every worker posted a separate run.

### Added — Phase 6 Slice 4 Depreciation Schedule Engine (2026-08-23)
- Created database migration `2026_08_23_050000_create_phase6_slice4_fixed_asset_depreciation_schedule_table.php` for `fixed_asset_depreciation_schedule` table with PostgreSQL check constraints `chk_fads_status` (`planned`, `posted`, `reversed`, `skipped`) and `chk_fads_amounts` (`depreciation_minor >= 0`, `accumulated_depreciation_minor >= 0`, `net_book_value_minor >= 0`).
- Created database migration `2026_08_23_051000_enforce_fixed_asset_depreciation_schedule_immutability.php` enforcing database-level protection for posted depreciation schedule financial fields and posted-row deletion.
- Created `FixedAssetDepreciationSchedule` Eloquent model with UUID trait and casts, and added `depreciationSchedules` HasMany relation to `FixedAsset`.
- Built `FixedAssetDepreciationEngineService` application service featuring:
  - Straight-Line integer minor-unit math (`intdiv` and `%` modulo).
  - Deterministic remainder allocation: 1 minor unit per month distributed to the first remainder months so total scheduled depreciation across all periods **exactly** equals the depreciable base ($\text{Cost} - \text{Salvage} - \text{Opening Accum}$).
  - Automatic fiscal year extension: uses `PeriodService` to automatically generate missing future fiscal years up to useful life duration.
  - Idempotent schedule (re)generation: uses `updateOrCreate` and protects existing `posted` schedule lines from mutation or deletion.
  - Zero GL posting in this slice.
- Added controller action `generateSchedule` in `FixedAssetController` guarded by permissions `fixedAssets.edit` and `view_financials`.
- Registered web route `POST /fixed-assets/{id}/generate-schedule` in `routes/web.php`.
- Updated Inertia React view `Show.tsx` with Depreciation Schedule table preview (showing period #, dates, depreciation, accumulated depreciation, net book value, and status) and Generate/Regenerate Schedule action button.
- Added dictionary translation keys in `en.json` and `ar.json`.
- Built feature test suite `Phase6Slice4DepreciationScheduleTest.php` (13/13 passing tests / 64 assertions after local review).
- Executed full verification pass cleanly (483 PHPUnit tests, 480 passed, 3 skipped / 3588 assertions; Concurrency testsuite 7/7; PostgreSQL stress commands passed cleanly including Phase 3 stress; `npm run typecheck`; `npm run build`).

### Corrected — Phase 6 Slice 4 Local Review (2026-08-23)
- Enforced the owner-approved depreciation start policy: schedules start in the month after `in_service_date`.
- Fixed SQLite test parity by converting the starting financial-period comparison to explicit `Y-m-d` strings instead of comparing date strings to Carbon datetime bindings.
- Restricted schedule generation to active assets and kept schedule reads side-effect free.
- Localized depreciation schedule statuses, date separator text, controls, and empty-state text in the Fixed Asset detail UI.
- Added database immutability regression tests for posted schedule row financial-field updates and deletion.

### Added — Phase 6 Slice 3 Capitalization and Opening Asset Posting (2026-08-23)
- Created database migration `2026_08_23_040000_create_phase6_slice3_capitalization_columns.php` adding capitalization metadata columns (`capitalization_mode`, `capitalization_date`, `journal_entry_id`, `capitalized_at`, `capitalized_by`) and PostgreSQL check constraint `chk_fixed_asset_capitalization_mode` to `fixed_asset`.
- Updated `FixedAsset` Eloquent model with capitalization fillable fields, date/timestamp casts, and Eloquent relationships `journalEntry` and `capitalizer`.
- Built `FixedAssetCapitalizationService` supporting both owner-approved capitalization modes:
  - `opening_already_capitalized`: Marks asset `active` without GL posting (0 journal/ledger entries created) for opening balance assets already represented in existing ledger.
  - `manual_capitalization`: Posts a balanced journal entry (Dr Fixed Asset Cost / Cr Fixed Asset Clearing) via `PostingEngine`, validates open period via `PeriodGuard`, allocates JV number, and creates ledger entries.
  - `reverseCapitalization`: Reverses capitalization journal entry via `ReversalService` and resets asset status back to `draft`.
- Added controller endpoints `capitalize` and `reverseCapitalization` in `FixedAssetController` guarded by permissions `fixedAssets.post`, `fixedAssets.reverse`, and `view_financials`, with exception handling mapping `PeriodClosedException` to validation errors.
- Registered web routes `POST /fixed-assets/{id}/capitalize` and `POST /fixed-assets/{id}/reverse-capitalization` in `routes/web.php`.
- Updated Inertia React view `Show.tsx` with Capitalize Asset modal (mode selector & date picker), capitalization status badges, clickable linked journal voucher link, and Reverse Capitalization action button.
- Added EN/AR translation dictionary keys in `en.json` and `ar.json`.
- Built feature test suite `Phase6Slice3CapitalizationTest.php` (11/11 passing tests / 64 assertions after local review).
- Executed local verification after review: migrations up to date, `vendor/bin/pint --test`, Slice 2 suite 9/9, Slice 3 suite 11/11, full PHPUnit suite 470 tests / 467 passed / 3 skipped / 3519 assertions, Concurrency testsuite 7/7, `accounting:concurrency-stress --workers=50`, `npm run typecheck`, and `npm run build`.
- Created database migration `2026_08_23_030000_create_phase6_slice2_fixed_asset_tables.php` for `fixed_asset_category` and `fixed_asset` tables with PostgreSQL check constraints enforcing positive costs, non-negative salvage/opening accumulated values, valid depreciation methods (`straight_line`), and valid statuses (`draft`, `active`, `fully_depreciated`, `disposed`).
- Created Eloquent models `FixedAssetCategory` and `FixedAsset` with Spatie `HasTranslations` (`name`) and UUID traits.
- Added 6 fixed asset GL mapping keys (`fixed_asset_cost`, `accumulated_depreciation`, `depreciation_expense`, `fixed_asset_disposal_gain`, `fixed_asset_disposal_loss`, `fixed_asset_clearing`) to `AccountingAccountMappingService` with type/nature validation rules, and seeded standard COA accounts (1600, 1690, 1699, 4910, 5250, 5910) and default mappings in `AccountingCoreSeeder`.
- Registered `fixed_asset` entity in `config/erp_attachments.php` for permission-gated attachment authorization (`fixedAssets.view`, `fixedAssets.edit`, `fixedAssets.create`, `fixedAssets.delete`).
- Built application services `FixedAssetCategoryService` and `FixedAssetRegisterService` using `NumberSequenceAllocator::nextValue('fixed_asset')` for `FA-YYYY-00001` global asset code allocation and Spatie Activitylog audit logging.
- Built controllers `FixedAssetCategoryController` and `FixedAssetController` with Inertia React pages (`Categories.tsx`, `Index.tsx`, `Create.tsx`, `Show.tsx`, `Edit.tsx`).
- Added web routes in `routes/web.php` guarded by RBAC permissions (`fixedAssets.view`, `fixedAssets.create`, `fixedAssets.edit`, `fixedAssets.delete`, `view_financials`), added EN/AR translations in `en.json` & `ar.json`, and added navigation items in `AppLayout.tsx`.
- Created feature test suite `Phase6Slice2FixedAssetRegisterTest.php` (9/9 passing tests / 71 assertions after latest local review).
- Executed local verification: migrations up to date, `vendor/bin/pint --test`, Slice 2 suite 9/9, Concurrency testsuite 7/7, full PHPUnit suite 470 tests / 467 passed / 3 skipped / 3519 assertions, `npm run typecheck`, and `npm run build`.

### Corrected — Phase 6 Slice 3 Local Review (2026-08-23)
- Removed stale outer capitalization idempotency key usage and made fixed asset capitalization retry-safe through row locking plus stored capitalization state; closed-period failures can be retried after reopening the period.
- Blocked manual register activation: fixed assets are created/edited as `draft`, and `active` is now owned by capitalization workflows.
- Blocked generic edit/update routes for active fixed assets and blocked capitalization of non-draft uncapitalized records.
- Blocked recapitalization with a different capitalization mode while preserving replay behavior for the same completed mode.
- Replaced fixed asset capitalization journal descriptions and line memos with localization-ready machine keys.
- Removed hardcoded visible English text from `FixedAssets/Show.tsx` and added the missing EN/AR dictionary keys.
- Added regression coverage for retry after closed-period failure, non-draft capitalization rejection, active asset edit/update rejection, manual active-status request rejection, and journal/memo key generation.

### Corrected — Phase 6 Slice 2 Local Review (2026-08-23)
- Corrected Fixed Asset React forms so category and asset create/edit pages submit nested multilingual `name.en` / `name.ar` payloads instead of local-only `name_en` / `name_ar` fields.
- Removed hardcoded visible English text from new Fixed Asset TSX pages and added the missing EN/AR dictionary keys for filters, buttons, statuses, section headings, confirmation prompts, and field labels.
- Blocked manual register activation; fixed assets remain `draft` until capitalization owns the transition to `active`. Future statuses `fully_depreciated` and `disposed` remain display/filter values only until depreciation/disposal workflows own those transitions.
- Hardened fixed asset creation validation so `currency` must be exactly 3 characters and exist in `currency.code`.
- Added regression tests covering unsupported future status updates and invalid currency rejection.

### Added — Phase 6 Fixed Assets Planning (2026-08-23)
- Added `PHASE_6_FIXED_ASSETS.md` as the master planning contract for Fixed Assets.
- Added seven bounded Gemini execution prompts:
  - `PHASE_6_SLICE_1_GEMINI_PROMPT.md` Fixed Asset Policy Decision Pack.
  - `PHASE_6_SLICE_2_GEMINI_PROMPT.md` Fixed Asset Register Foundation.
  - `PHASE_6_SLICE_3_GEMINI_PROMPT.md` Capitalization and Opening Asset Posting.
  - `PHASE_6_SLICE_4_GEMINI_PROMPT.md` Depreciation Schedule Engine.
  - `PHASE_6_SLICE_5_GEMINI_PROMPT.md` Depreciation Run Posting.
  - `PHASE_6_SLICE_6_GEMINI_PROMPT.md` Disposal, Sale, Scrap, and Reversal Workflow.
  - `PHASE_6_SLICE_7_GEMINI_PROMPT.md` Reports, UX, Export/Print, Smoke, and Close-Out.
- Updated `CONTINUE_HERE.md`, `NEXT_TASKS.md`, and `IMPLEMENTATION_STATUS.md` so Phase 6 starts from Slice 1 and remains docs-only until fixed-asset owner decisions are recorded.
- Reconfirmed the future Fixed Assets implementation must preserve exact permissions, dictionary-backed visible UI text, no tenant/company/branch/custodian assumptions, integer-only money math, PostingEngine integration, PeriodGuard checks, Spatie Activitylog audit, and full verification evidence.

### Added — Phase 5 Slice 6 UX, Export/Print, E2E Smoke & Close-Out (2026-08-23)
- Closed out Phase 5 with permission-aware Print actions (`reports.print` + `view_financials`) across Balance Sheet, Income Statement, and Cash Flow Statement pages (`BalanceSheet.tsx`, `IncomeStatement.tsx`, `CashFlow.tsx`).
- Created `Phase5Slice6FinalCloseOutTest.php` verifying CSV export streaming, service total matching, authorization enforcement, route access contracts, and actual schema-field usage (4 passing tests / 30 assertions after local review).
- Removed duplicate `"app.accounting"` key from `en.json` and `ar.json` and added `"printReport"` translations.
- Corrected the Slice 6 test fixture to use actual schema fields (`financial_period.month`, `journal_entry.number`, `account.is_active`) instead of silently ignored natural-language fields.
- Executed verification after local review: migrations up to date, full PHPUnit test suite (450 tests / 447 passed / 3 skipped / 3374 assertions), Concurrency testsuite (7/7), all accounting/Phase 3 stress and integrity commands, token GC, Pint lint check, TypeScript typecheck, and Vite build.
- Created `PHASE_5_FINAL_VERIFICATION_REPORT.md` close-out artifact.

### Added — Phase 5 Slice 5 Year-End Close Decision Pack (2026-08-23)
- Added `PHASE_5_YEAR_END_CLOSE_DECISION.md` as the docs-only decision pack for Year-End Close and Retained Earnings handling.
- Documented Soft Close, Physical Closing Journal, and Hybrid options in owner-facing Arabic and technical English.
- Recommended Hybrid: continue with soft/date-based reporting now, and add a physical Retained Earnings closing journal only after explicit owner approval.
- Explicitly confirmed zero Slice 5 implementation additions: no migrations, models, services, routes, UI components, seeders, commands, jobs, or closing journal engine.
- Updated handoff/status documents to record the docs-only owner decision before Phase 5 close-out.

### Added — Phase 5 Slice 4 Period Close Controls & Hardening (2026-08-23)
- Added migration `2026_08_23_020000_create_phase5_slice4_period_close_columns.php` with close/reopen metadata on `financial_period` and a PostgreSQL status constraint for `open`, `closed`, and `reopened`.
- Added `PeriodGuard` and `PeriodClosedException`, and integrated period-open/date-bound checks into PostingEngine and financial-impact posting services.
- Added period close-readiness endpoint and close/reopen actions guarded by exact `close_period` and `reopen_period` permissions; `settings.configure` is not a close/reopen bypass.
- Updated the Accounting Periods Inertia page with permission-aware close/reopen controls and localized blocker display.
- Added `Phase5Slice4PeriodCloseTest.php`, now covering 13 tests / 37 assertions.

### Corrected — Phase 5 Slice 4 Local Review (2026-08-23)
- Removed visible English fallback strings from `Periods.tsx` and localized blocker status labels instead of rendering raw backend status codes.
- Corrected close-readiness to include approved but unposted invoices, bills, sales returns, customer credit notes, purchase returns, and supplier adjustment notes.
- Corrected Delivery Note and Goods Receipt stock posting to resolve and lock the date-covered FinancialPeriod before inventory movement side effects.
- Corrected cheque posting validation so `reopened` periods are treated as postable, matching the global FinancialPeriod rule.
- Fixed a time-dependent Phase 4 Slice 10 settlement test by pinning the test clock to the document date, preventing `settled_at` from drifting beyond the report as-of date.
- Removed stale `financial_period.name` / `period_number` fixture assumptions from older tests and the bank-reconciliation stress command so source scans no longer imply non-existent period fields.
- Verified locally with full PHPUnit suite 446 tests / 443 passed / 3 skipped / 3344 assertions, Concurrency suite 7/7, all Phase 3/accounting stress commands, `npm run typecheck`, and `npm run build`.

### Added — Phase 5 Slice 3 Cash Flow Statement Foundation (2026-08-23)
- Created migration `2026_08_23_010000_create_phase5_slice3_cash_flow_activity_columns.php` adding nullable `cash_flow_activity` columns to `financial_statement_line` and `account`.
- Created forward hardening migration `2026_08_23_011000_harden_phase5_slice3_cash_flow_activity_constraints.php` adding PostgreSQL check constraints for allowed stored values (`operating`, `investing`, `financing`).
- Updated `FinancialStatementLineSeeder` with system default activities and kept unclassified as a derived/null state rather than a stored tenant/company scope.
- Created `CashFlowReportService` deriving active cash-equivalent GL accounts from `CashAccount` and `BankAccount`, using `ledger_entry.entry_date` for date filtering, classifying non-cash counterparties with precedence `account.cash_flow_activity` > `financial_statement_line.cash_flow_activity` > unclassified, excluding internal cash transfers, and routing mixed/unclassified journals to localized warning codes.
- Created `CashFlowReportController` with report and CSV export routes protected by `reports.view` + `view_financials` for viewing and `reports.export` + `view_financials` for export.
- Added `CashFlow.tsx`, Reports Hub card, and AppLayout navigation entry with EN/AR dictionary-backed visible text and string-based integer minor-unit money formatting.
- Extended `FinancialStatementMappings.tsx` with cash-flow activity controls for statement lines and account-level non-cash overrides; backend rejects direct activity assignment to active cash/bank GL accounts.
- Verified with clean Pint, `Phase5Slice1FinancialStatementMappingTest` 9/9, `Phase5Slice2FinancialStatementsTest` 8/8, `Phase5Slice3CashFlowStatementTest` 9/9 (46 assertions), Concurrency suite 7/7, full suite 433 tests / 430 passed / 3 skipped / 3307 assertions, clean TypeScript typecheck, and Vite build.

### Corrected — Phase 5 Remaining Prompt Hardening (2026-08-23)
- Tightened `PHASE_5_FINANCIAL_STATEMENTS_PERIOD_CLOSE.md` and remaining Slice 3-6 prompts with stricter acceptance rules for accounting date fields, integer money formatting, exact permissions, no hardcoded visible TSX text, no tenant/company/branch assumptions, source scans, and final reporting evidence.
- Made Slice 3 Cash Flow rules explicit: cash-equivalent derivation from CashAccount/BankAccount GL links, `ledger_entry.entry_date` filtering, explicit cash-flow classifications only, internal cash transfer handling, mixed classification warnings, and exact reconciliation formula.
- Made Slice 4 Period Close rules explicit: service-level closed-period guards, PostingEngine final safety net, blocker inspection by actual schema columns, close/post race protection, and no `settings.configure` bypass.
- Made Slice 5 docs-only by default and marked year-end close/retained earnings as `OWNER DECISION REQUIRED` with no migrations/models/services/routes/pages allowed.
- Made Slice 6 close-out stricter for UI/export/print consistency, E2E smoke evidence, source scan match classification, localization-ready backend warning payloads, route/UI parity, and synchronous verification-only pass claims.
- Added generated-work review gates to `CONTINUE_HERE.md` and `NEXT_TASKS.md` so remaining Phase 5 execution cannot treat non-empty scans as clean or report background commands as passed.

### Added — Phase 5 Slice 2 Balance Sheet & Income Statement Core Generation (2026-08-22)
- Implemented `BalanceSheetReportService` generating read-only Balance Sheet financial position as of a specified date from immutable posted `ledger_entry` records and statement line taxonomy mappings; compares Total Assets to Liabilities + Equity, calculates `is_balanced` status and imbalance amount, and handles contra-asset/contra-liability display signs.
- Implemented `IncomeStatementReportService` generating read-only Income Statement profit and loss over a date range or fiscal period; calculates Net Revenue (Gross Revenue less Sales Returns & Allowances), Gross Profit, Operating Income, and Net Income / (Loss).
- Implemented unmapped accounts visibility and warning banners (`has_unmapped_warning`) on both reports to ensure active accounts with movements are never hidden.
- Created `BalanceSheetReportController` and `IncomeStatementReportController` with CSV export streaming (`exportCsv`). Registered routes under `/reports/balance-sheet` and `/reports/income-statement` protected by server-side gates enforcing `reports.view` AND `view_financials` for report viewing, and `reports.export` AND `view_financials` for CSV exports.
- Created Inertia React reporting pages `BalanceSheet.tsx` and `IncomeStatement.tsx` with date filter controls, fiscal period selector, imbalance & unmapped warning banners, no emojis, full EN/AR dictionary translations, Reports Hub integration, and sidebar navigation links.
- Created comprehensive feature test suite `Phase5Slice2FinancialStatementsTest.php` covering Balance Sheet equation verification, Income Statement Net Income calculation, contra revenue & contra asset display signs, unmapped accounts visibility, permission enforcement (`view_financials`, `reports.view`, `reports.export`), Inertia page props, and read-only ledger query immutability.

### Corrected — Phase 5 Slice 2 Local Review (2026-08-23)
- Corrected Balance Sheet and Income Statement report filtering to use accounting `ledger_entry.entry_date` instead of database row `created_at`, so backdated/postdated accounting activity reports in the correct financial period.
- Corrected unmapped account warnings so accounts with no movement do not create noisy warning rows; active unmapped accounts with non-zero report movement remain visible.
- Corrected Income Statement period selector data to use actual `financial_period` columns (`fiscal_year_id`, `month`, `start_date`, `end_date`, `status`) instead of a non-existent `name` column.
- Hardened report-page authorization in the UI so export controls require both `reports.export` and `view_financials`.
- Removed new hardcoded visible TSX fallback strings from the Slice 2 report pages and navigation entries; text is now dictionary-backed for EN/AR.
- Replaced frontend minor-unit display formatting with integer-safe formatting instead of floating-point division.
- Verified local correction with clean Pint, `Phase5Slice2FinancialStatementsTest.php` 8/8 tests and 54 assertions, clean TypeScript typecheck, and successful Vite build.

### Added — Phase 5 Slice 1 Financial Statement Mapping Foundation (2026-08-23)
- Created database migration for financial statement lines taxonomy: `financial_statement_line` table and nullable `financial_statement_line_id` foreign key on `account` (`2026_08_23_000000_create_phase5_slice1_financial_statement_line_tables.php`).
- Created `FinancialStatementLine` model with `HasTranslations` (`name`), `HasUuids`, and `accounts` relationship. Updated `Account` model with `financialStatementLine` relationship.
- Implemented `FinancialStatementLineSeeder` seeding 11 default system statement lines (`ASSET_CURRENT`, `ASSET_NON_CURRENT`, `LIABILITY_CURRENT`, `LIABILITY_NON_CURRENT`, `EQUITY`, `REVENUE`, `CONTRA_REVENUE`, `COGS`, `EXPENSE_OPERATING`, `INCOME_OTHER`, `EXPENSE_OTHER`) idempotently. Auto-assigned obvious chart of accounts to default lines.
- Implemented `FinancialStatementMappingService` providing statement line CRUD, system line deletion protection (`is_system = true`), in-use deletion protection (`accounts()->count() > 0`), statement_type compatibility validation, bulk account assignment, and `AuditLogger` integration.
- Created `FinancialStatementMappingController` and routes under `/accounting/statement-mappings` protected by `accounting.mappings` permission.
- Created Inertia React page `FinancialStatementMappings.tsx` featuring tab filters, mapped/unmapped account views, quick assignment widget, system badges, no emojis per UI rules, and full EN/AR translation dictionary support.
- Hardened the Slice 1 page after review so visible TSX text uses dictionary keys only, statement/section/balance option labels are translated client-side, and controller option payloads no longer carry English-only labels.
- Created comprehensive feature test suite `Phase5Slice1FinancialStatementMappingTest.php` (9/9 passing tests, 30 assertions) covering schema integrity, seeder idempotency, relationships, validations, system line delete protection, account assignment matching, RBAC authorization, and audit logging.

### Added — Phase 5 Financial Statements & Period Close Planning (2026-08-23)
- Added `PHASE_5_FINANCIAL_STATEMENTS_PERIOD_CLOSE.md` as the master planning contract for Financial Statements and Period Close.
- Added six bounded Gemini execution prompts:
  - `PHASE_5_SLICE_1_GEMINI_PROMPT.md` Financial Statement Mapping Foundation.
  - `PHASE_5_SLICE_2_GEMINI_PROMPT.md` Balance Sheet and Income Statement.
  - `PHASE_5_SLICE_3_GEMINI_PROMPT.md` Cash Flow Statement Foundation.
  - `PHASE_5_SLICE_4_GEMINI_PROMPT.md` Period Close Controls and Posting Guards.
  - `PHASE_5_SLICE_5_GEMINI_PROMPT.md` Year-End Close and Retained Earnings Decision Pack.
  - `PHASE_5_SLICE_6_GEMINI_PROMPT.md` UX, Export/Print, E2E Smoke, and Close-Out.
- Updated `CONTINUE_HERE.md`, `NEXT_TASKS.md`, and `IMPLEMENTATION_STATUS.md` so Phase 5 starts from Slice 1 with exact permission checks and no hardcoded visible UI text/team/tenant assumptions.

### Added — Phase 4 Slice 10 Manual AR/AP Settlement Pass (2026-08-22)
- Implemented manual settlement schema and models for note-created AR/AP entries: `receivable_entry_settlement` and `payable_entry_settlement` (`2026_08_22_200000_create_phase4_slice10_settlement_tables.php`).
- Implemented domain services: `ReceivableEntrySettlementService` (credit note settlement against invoice debits) and `PayableEntrySettlementService` (supplier adjustment note settlement against bill credits) with deterministic ID row-locking (`orderBy('id', 'asc')->lockForUpdate()`), capacity & match validation, `AuditLogger` integration, and idempotency store protection.
- Created controllers & routes: `ReceivableEntrySettlementController` (`sales.receivable_settlements.*`) and `PayableEntrySettlementController` (`purchasing.payable_settlements.*`).
- Added Inertia React settlement pages: `ReceivableSettlements.tsx` and `PayableSettlements.tsx` with settlement entry forms and reversal modals (no emojis per style rule). Added quick "Settle" action links on posted customer credit notes and supplier adjustment notes.
- Updated subledger reporting services: `ArAgingReportService`, `ArToGlReconciliationReportService`, `ApAgingReportService`, and `ApToGlReconciliationReportService` to incorporate active note settlements into remaining open balances.
- Added concurrency stress command: `SettlementConcurrencyStressCommand` (`accounting:settlement-concurrency-stress {--workers=50}`).
- Updated test suite: `Phase4Slice10ReturnsCreditNotesTest.php` (38/38 passing tests, 0 skipped, 230 assertions). Removed skipped test and added full test coverage for AR/AP settlement, over-settlement rejection, customer/supplier/currency mismatch rejection, idempotency, reversal, reporting reconciliation, and architecture compliance.

### Added — Phase 4 Slice 10 Sales Returns, Credit Notes & Operations Close-Out (2026-08-22)
- Implemented six migrations: `sales_return`/`sales_return_line`, `customer_credit_note`/`customer_credit_note_line`, `customer_invoice_revision`, `purchase_return`/`purchase_return_line`, `supplier_adjustment_note`/`supplier_adjustment_note_line` tables, and the `2026_08_22_100050_update_accounting_mapping_for_slice10` mapping update.
- Added services `SalesReturnService`, `CustomerCreditNoteService`, `CustomerInvoiceRevisionService`, `PurchaseReturnService`, and `SupplierAdjustmentNoteService`; extended the Moving Weighted Average inventory service with `recordReturn`/`recordScrap`/`calculateIssueCostForReturn` so returns post as reversal stock movements and scrap disposition does not increase saleable stock.
- Added routes `sales-returns.*`, `customer-credit-notes.*`, `invoice-revisions.*` under `/sales/invoice-revisions`, `purchase-returns.*`, `supplier-adjustment-notes.*`, plus `GET /sales/returns/returnable-lines/{invoiceId}`.
- Added permissions `sales.returns`, `sales.credit_notes`, `sales.invoice_revisions`, `purchasing.returns`, `purchasing.adjustment_notes` in `config/erp_rbac.php`, and registered attachment entities `sales_return`, `customer_credit_note`, `customer_invoice_revision`, `purchase_return`, `supplier_adjustment_note` in `config/erp_attachments.php`.
- Added numbering keys/prefixes `sales.return` (`SR-`), `customer.credit_note` (`CN-`), `purchase.return` (`PRT-`), and `supplier.adjustment_note` (`SAN-`) with idempotent number allocation.
- Seeded new accounting mapping keys idempotently in `AccountingCoreSeeder`: `sales_returns` (4200), `inventory_return_variance` (5200), `inventory_scrap_loss` (5300), `purchase_returns_allowances` (5400), `input_tax_receivable` (1300), and `output_tax_payable` (2200).
- Implemented immutable cumulative customer invoice revisions (`R01`/`R02`) showing original, returned, and net quantities with no GL effects.
- Implemented manual tax percentage stored in integer basis points with exact manual amount override; modes `none`/`manual_rate`/`manual_amount` computed as `intdiv(($baseMinor * $rateBps) + 5000, 10000)`.
- Kept credit/debit note settlement manual/open only with explicit settlement actions that do not create extra GL; purchase return GRNI vs post-bill AP impact is carried through a separate `supplier_adjustment_note`.
- Final verification after the Manual AR/AP Settlement Pass: full PHPUnit suite 407 tests / 404 passed / 3 skipped / 3172 assertions, `Phase4Slice10ReturnsCreditNotesTest` 38 tests / 38 passed / 0 skipped / 230 assertions, Concurrency suite 7 tests / 16 assertions, all accounting concurrency stress commands passing at 50 workers including `accounting:settlement-concurrency-stress --workers=50`, clean Pint, `npm run typecheck`, and `npm run build`. `concurrency:stress --workers=100` remains blocked locally by Windows paging-file memory exhaustion; `--workers=10` passes.

### Added — Phase 4 Slice 10 Selected Returns/Credit/Supplier Adjustment Prompt
- Recorded the selected safe operating model in `PHASE_4_RETURNS_CREDIT_DEBIT_DECISION.md`: separate physical returns (`sales_return`, `purchase_return`) from financial adjustments (`customer_credit_note`, normalized `supplier_adjustment_note`).
- Added `PHASE_4_SLICE_10_GEMINI_PROMPT.md` as the bounded execution contract for Sales Returns, Customer Credit Notes, Purchase Returns, Supplier Adjustment Notes, manual tax basis points, manual/open allocation, stock valuation rules, and operational close-out hardening.
- Added the owner-requested posted-invoice return workflow: select returned invoice lines/quantities, post Sales Return + Customer Credit Note, then generate an immutable corrected customer invoice revision showing original, returned, and net quantities.
- Updated status/handoff documentation so Phase 4 Slice 10 is ready for execution and no longer blocked on owner decision.

### Added — Phase 4 Slice 9 Read-only Operational Reports & Returns Decision Pack
- Implemented 7 read-only operational query services (`SalesOrderReportService`, `PurchaseOrderReportService`, `DeliveryNoteReportService`, `GoodsReceiptReportService`, `CustomerInvoiceReportService`, `SupplierBillReportService`, `StockMovementReportService`).
- Created 7 HTTP Controllers under `App\Http\Controllers\Reports` with `Gate::authorize('reports.view')` access control.
- Implemented 7 Inertia UI Pages (`SalesOrdersReport.tsx`, `PurchaseOrdersReport.tsx`, `DeliveryNotesReport.tsx`, `GoodsReceiptsReport.tsx`, `CustomerInvoicesReport.tsx`, `SupplierBillsReport.tsx`, `StockMovementsReport.tsx`).
- Updated Reports Hub (`Reports/Index.tsx`) to link all 7 new operational reports under a dedicated "Sales, Purchasing & Inventory Reports" group.
- Drafted owner-facing decision pack `PHASE_4_RETURNS_CREDIT_DEBIT_DECISION.md` covering Sales Returns, Customer Credit Notes, Purchase Returns, Supplier Credit/Debit Notes, Tax/VAT status, and recommended Next Slice execution plan.
- Implemented feature test suite `Phase4Slice9OperationalReportsTest.php` (7/7 passing tests, 85 assertions) verifying RBAC authorization, query filters, integer minor unit / e6 quantity formatting, linked accounting IDs, and zero-mutation database safety.
- Hardened report query services locally after review to use the current schema fields (`number` and `journal_entry.number`) instead of stale generated aliases such as `order_number`, `invoice_number`, or `entry_number`.

### Added — Phase 4 Slice 8 Moving Weighted Average Inventory Costing & Posting
- Implemented `stock_balance` and `stock_movement_ledger` migrations and Eloquent domain models.
- Added database immutability triggers for `stock_movement_ledger` on PostgreSQL and SQLite.
- Extended `AccountingAccountMappingService` with `inventory_asset`, `grni_clearing`, and `cogs` mapped account keys.
- Implemented `MovingWeightedAverageInventoryService` supporting exact integer valuation math (`quantity_e6`), residual clearance on final issue, pessimistic balance locking (`lockForUpdate`), GL journal posting, and audit logging.
- Integrated Goods Receipt confirmation to post stock receipt (Dr `inventory_asset` / Cr `grni_clearing`).
- Integrated Delivery Note confirmation to post stock issue (Dr `cogs` / Cr `inventory_asset`) and enforce non-negative stock.
- Integrated Supplier Bill posting to clear `grni_clearing` for stock lines sourced from Goods Receipts.
- Integrated Customer Invoice posting for stock lines sourced from Delivery Notes.
- Implemented read-only Inertia stock balances page `resources/js/Pages/Inventory/StockBalances.tsx`.
- Implemented `Phase4Slice8InventoryCostingTest` feature test suite (14 tests, 13 passed, 1 PostgreSQL-only check skipped under the current test driver, 100 assertions).
- Implemented `accounting:inventory-concurrency-stress --workers=50` command passing 100% cleanly.
- Hardened Slice 8 locally after review: added PostgreSQL stock integrity constraints, corrected inventory-generated journal line metadata to use `memo`, added overflow guards for integer valuation math, and fixed the inventory stress command so it does not delete append-only stock movement records.

### Added — Phase 4 Slice 7 Inventory Costing Decision Pack
- Created `PHASE_4_INVENTORY_COSTING_DECISION.md` as the owner-facing decision document for stock costing.
- Compared Moving Weighted Average, FIFO layers, Standard Costing, and Non-Valued / Manual Stock Tracking.
- Documented current stock-product boundaries, required future GL mappings, operational consequences, concurrency/integrity requirements, and the blocked Phase 4 Slice 8 contract.
- Confirmed this was documentation-only: no migrations, no PHP/TS implementation changes, no database mutation, and no tenant/company/branch scope introduced.

### Added — Phase 4 Slice 7 Inventory Costing Decision Prompt
- Added `PHASE_4_SLICE_7_GEMINI_PROMPT.md` as a bounded decision-pack contract before any inventory valuation implementation.
- Scope is limited to reviewing current stock-product boundaries, comparing weighted average, FIFO, standard cost, and non-valued/manual stock tracking, and producing owner-ready consequences, recommended path, and future implementation plan.
- Explicitly excludes stock ledger migrations, warehouse/location semantics, COGS posting, stock-product invoice/bill posting, landed cost, tax, returns, credit notes, debit notes, and tenant/company/branch scope.

### Added — Phase 4 Slice 6 Supplier Bill Posting
- Implemented `supplier_bill` and `supplier_bill_line` tables, models, service, controller, routes, attachment registry, navigation, and `SupplierBills.tsx` Inertia page.
- Added `purchase_expense` accounting mapping support and Supplier Bill posting through the existing PostingEngine: Dr Purchase Expense / Cr AP Control, plus AP `payable_entry` credit creation.
- Added exact integer bill total calculation, `BILL-YYYY-XXXXX` numbering, lifecycle transitions (`draft` -> `submitted` -> `approved` -> `posted` / `cancelled`), idempotent post replay, and Spatie Activitylog audit via `AuditLogger`.
- Hardened Supplier Bill source rules locally after Gemini output: source lines require matching source headers, Purchase Order/Goods Receipt sources cannot be mixed, product/UOM/unit cost must match the source line, duplicate source-line quantities inside one bill are counted cumulatively, source lines are locked deterministically, and JournalLine uses `memo` instead of a non-existent description field.
- Fixed `SupplierBillController` product filtering to use `is_purchase_enabled` and seeded default `purchase_expense` mapping to account `5100` through `AccountingCoreSeeder`.
- Verified `Phase4Slice6SupplierBillTest` 19/19 passing tests (100 assertions), full PHPUnit suite 342 tests / 340 passed / 2 skipped / 2675 assertions, clean Pint, clean Supplier Bill backend float/rounding source scan, `npm run typecheck`, and `npm run build`.

### Added — Phase 4 Slice 6 Supplier Bill Posting Prompt
- Added `PHASE_4_SLICE_6_GEMINI_PROMPT.md` as the bounded execution contract for Supplier Bill lifecycle and AP/GL posting through the existing `PostingEngine`.
- Scope is limited to `supplier_bill` / `supplier_bill_line`, `purchase_expense` accounting mapping, AP `payable_entry` credit creation, idempotent `BILL-YYYY-XXXXX` posting, attachment registry, RBAC, audit, and Inertia UX.
- Explicitly excludes stock-product billing, inventory valuation, stock movement, COGS, landed cost, VAT/tax, discounts, returns, credit notes, debit notes, reports, and tenant/company/branch scope.

### Added — Phase 4 Slice 5 Customer Invoice Posting
- Implemented `customer_invoice` and `customer_invoice_line` tables, models, service, controller, routes, attachment registry, navigation, and `CustomerInvoices.tsx` Inertia page.
- Added `sales_revenue` accounting mapping support and Customer Invoice posting through the existing PostingEngine: Dr AR Control / Cr Sales Revenue, plus AR `receivable_entry` debit creation.
- Added exact integer invoice total calculation, `INV-YYYY-XXXXX` numbering, lifecycle transitions (`draft` -> `submitted` -> `approved` -> `posted` / `cancelled`), idempotent post replay, and Spatie Activitylog audit via `AuditLogger`.
- Hardened Customer Invoice source rules locally after Gemini output: source lines require matching source headers, Sales Order/Delivery Note sources cannot be mixed, product/UOM/unit price must match the source line, source lines are locked deterministically, and JournalLine uses `memo` instead of a non-existent description field.
- Verified `Phase4Slice5CustomerInvoiceTest` 19/19 passing tests (86 assertions), full PHPUnit suite 323 tests / 321 passed / 2 skipped / 2565 assertions, clean Pint, clean Customer Invoice backend float/rounding source scan, `npm run typecheck`, and `npm run build`.

### Added — Phase 4 Slice 5 Customer Invoice Posting Prompt
- Added `PHASE_4_SLICE_5_GEMINI_PROMPT.md` as the bounded execution contract for Customer Invoice lifecycle and AR/GL posting through the existing `PostingEngine`.
- Scope is limited to `customer_invoice` / `customer_invoice_line`, `sales_revenue` accounting mapping, AR `receivable_entry` debit creation, idempotent `INV-YYYY-XXXXX` posting, attachment registry, RBAC, audit, and Inertia UX.
- Explicitly excludes Supplier Bills, AP posting, stock-product invoicing, inventory valuation, stock movement, COGS, VAT/tax, discounts, returns, credit notes, debit notes, reports, and tenant/company/branch scope.

### Fixed — Phase 4 Slice 4 Source-Scan False Positive
- Renamed the Slice 4 backend scan test and split the literal `(float)` string construction in `Phase4Slice4FulfillmentTest.php` so repository-level forbidden-pattern scans no longer report false positives from the test source itself.
- Re-ran `php artisan test --filter=Phase4Slice4FulfillmentTest` (17 tests / 138 assertions), the Delivery/Goods Receipt forbidden-pattern source scan (no results), and `vendor/bin/pint --test` (passed).

### Added — Phase 4 Slice 4 Delivery Notes & Goods Receipts Operational Foundation
- Created migration `2026_08_22_050000_create_phase4_slice4_fulfillment_tables.php` defining `delivery_note`, `delivery_note_line`, `goods_receipt`, and `goods_receipt_line` tables with UUID primary keys, optimistic locking (`lock_version`), integer quantity scaling (`quantity_e6`), foreign keys, and zero prohibited tenancy/company/accounting columns.
- Created Eloquent models `DeliveryNote`, `DeliveryNoteLine`, `GoodsReceipt`, and `GoodsReceiptLine` with proper relationships to SalesOrder, PurchaseOrder, Product, UnitOfMeasure, and User.
- Implemented `DeliveryNoteService` and `GoodsReceiptService` domain services supporting full document lifecycle (`draft` -> `confirmed` / `cancelled`), integer quantity validation (`quantity_e6`), cumulative over-fulfillment prevention with deterministic transaction locks (`lockForUpdate`), global number sequence allocation (`DN-YYYY-XXXXX` & `GRN-YYYY-XXXXX`) via `NumberSequenceAllocator`, idempotent confirmation replay, and Spatie Activitylog auditing via `AuditLogger`.
- Registered `delivery_note` and `goods_receipt` entity definitions in `config/erp_attachments.php` mapping permissions `sales.view`, `sales.create`, `sales.edit`, `sales.delete` and `purchasing.view`, `purchasing.create`, `purchasing.edit`, `purchasing.delete`.
- Created `DeliveryNoteController` and `GoodsReceiptController` and web routes under `/sales/delivery-notes/*` and `/purchasing/goods-receipts/*`.
- Created Inertia React pages `DeliveryNotes.tsx` and `GoodsReceipts.tsx` with confirmed order selectors, dynamic line items, quantity inputs, status badges, and action controls. Added Delivery Notes and Goods Receipts links to `AppLayout.tsx` navigation.
- Created `Phase4Slice4FulfillmentTest.php` feature test suite (17/17 passing, 138 assertions). Verified full suite (302 passing tests, 0 TS errors, clean Pint formatting, successful Vite build).

### Added — Phase 4 Slice 3 Purchase Order Backend & UX
- Created migration `2026_08_22_040000_create_phase4_slice3_purchase_order_tables.php` defining `purchase_order` and `purchase_order_line` tables with optimistic locking (`lock_version`), integer currency columns, `quantity_e6` scaling, foreign keys, and zero prohibited tenancy/company columns.
- Created Eloquent models `PurchaseOrder` and `PurchaseOrderLine` with relationships to Supplier, Currency, Product, UnitOfMeasure, and User.
- Implemented `PurchaseOrderService` domain service supporting full document lifecycle (`draft` -> `submitted` -> `confirmed` / `cancelled`), exact integer math calculation helper (`calculateLineTotalMinor` using `intdiv` and `% 1000000`), server-side line & header total recomputations, global number sequence allocation (`PO-YYYY-XXXXX`) via `NumberSequenceAllocator`, idempotent confirmation replay, and Spatie Activitylog auditing via `AuditLogger`.
- Registered `purchase_order` entity definition in `config/erp_attachments.php` mapping permissions `purchasing.view`, `purchasing.create`, `purchasing.edit`, `purchasing.delete`.
- Created `PurchaseOrderController` and web routes under `/purchasing/orders/*`.
- Created Inertia React page `PurchaseOrders.tsx` with supplier selector, product/UOM selector, dynamic line items, real-time total preview, status badges, and action controls. Added Purchase Orders link to `AppLayout.tsx` navigation.
- Created `Phase4Slice3PurchaseOrderTest.php` feature test suite (16/16 passing, 74 assertions). Verified full suite (285 passing tests, 0 TS errors, clean Pint formatting, successful Vite build).

### Fixed — Phase 4 Slice 2 Sales Order Integer Math Correction
- Refactored `SalesOrderService.php` to calculate line totals using exact integer math helper `calculateLineTotalMinor` (`intdiv` and `% 1000000`), completely eliminating `round()`, `(float)`, and floating division `/ 1000000`.
- Added strict overflow prevention (`intdiv(PHP_INT_MAX, $unitPriceMinor)`) and fractional minor unit validation rejection (`$product % 1000000 !== 0`).
- Expanded `Phase4Slice2SalesOrderTest.php` to 15/15 passing tests, adding explicit test cases for integer math calculation, fractional minor unit rejection, integer overflow rejection, and source-code scan verifying zero forbidden binary/rounding patterns in authoritative Sales Order backend code.

### Added — Phase 4 Slice 2 Sales Order Backend & UX
- Created migration `2026_08_22_030000_create_phase4_slice2_sales_order_tables.php` defining `sales_order` and `sales_order_line` tables with optimistic locking (`lock_version`), integer currency columns, `quantity_e6` scaling, foreign keys, and zero prohibited tenancy/company columns.
- Created Eloquent models `SalesOrder` and `SalesOrderLine` with relationships to Customer, Currency, Product, UnitOfMeasure, and User.
- Implemented `SalesOrderService` domain service supporting full document lifecycle (`draft` -> `submitted` -> `confirmed` / `cancelled`), server-side line & header total recomputations, global number sequence allocation (`SO-YYYY-XXXXX`) via `NumberSequenceAllocator`, idempotent confirmation replay, and Spatie Activitylog auditing via `AuditLogger`.
- Registered `sales_order` entity definition in `config/erp_attachments.php` mapping permissions `sales.view`, `sales.create`, `sales.edit`, `sales.delete`.
- Created `SalesOrderController` and web routes under `/sales/orders/*`.
- Created Inertia React page `SalesOrders.tsx` with customer selector, product/UOM selector, dynamic line items, real-time total preview, status badges, and action controls. Added Sales Orders link to `AppLayout.tsx` navigation.
- Created `Phase4Slice2SalesOrderTest.php` feature test suite. Verified full suite after correction (269 passing tests, 0 TS errors, clean Pint formatting, successful Vite build); local targeted recheck after source-scan cleanup passed 15 tests / 72 assertions.

### Added — Phase 4 Slice 1 Product/Service Catalog Foundation
- Created migration `2026_08_22_020000_create_phase4_slice1_catalog_tables.php` defining `unit_of_measure`, `product_category`, and `product` tables with optimistic locking, Spatie Translatable JSON columns, foreign keys, and zero prohibited tenancy/company columns.
- Created Eloquent models `UnitOfMeasure`, `ProductCategory`, `Product` with `HasTranslations`, `HasUuids`, and relationship definitions.
- Implemented domain services `UnitOfMeasureService`, `ProductCategoryService`, and `ProductService` with code normalization/uniqueness checks, optimistic locking, in-use delete prevention, and Spatie Activitylog auditing via `AuditLogger`.
- Registered `products` (`view`, `create`, `edit`, `delete`, `export`) and `uom` (`view`, `create`, `edit`, `delete`) in `config/erp_rbac.php` and `PermissionSeeder`.
- Registered `product` entity definition in `config/erp_attachments.php` mapping permissions `products.view`, `products.create`, `products.edit`, `products.delete`.
- Created catalog seeders `UnitOfMeasureSeeder` and `ProductCategorySeeder` and registered them in `DatabaseSeeder.php`.
- Created Inertia controllers `UnitOfMeasureController`, `ProductCategoryController`, and `ProductController`, web routes under `/catalog/*`, and Inertia React pages (`UnitsOfMeasure.tsx`, `ProductCategories.tsx`, `Products.tsx`).
- Updated `AppLayout.tsx` sidebar navigation with expandable "Catalog" dropdown group (no emojis, clean SVG icons).
- Created `Phase4Slice1CatalogTest.php` feature test suite (12/12 passing, 66 assertions). Verified full suite (254 passing tests, 0 TS errors, clean Pint formatting, successful Vite build).

### Added — Phase 3 Slice 10 close-out & final verification gate
- Performed repository-wide documentation audit and status synchronization across all Markdown files (`README.md`, `CONTINUE_HERE.md`, `IMPLEMENTATION_STATUS.md`, `NEXT_TASKS.md`, `MD_DOCUMENTATION_AUDIT.md`, `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`).
- Executed 100% passing final verification gate: `php artisan migrate:status` (33/33 ran), `vendor/bin/pint --test`, `php artisan test` (242 passed, 2 skipped, 2064 assertions), `php artisan accounting:phase3-integrity-check`, `php artisan accounting:phase3-stress --workers=50`, `npm run typecheck` (0 TS errors), and `npm run build` (compiled in 998ms).
- Created `PHASE_3_FINAL_VERIFICATION_REPORT.md` close-out document.
- Formally marked **Phase 3 Slices 1–10 100% complete** for the agreed contract scope.

### Added — Phase 3 Slice 9 concurrency stress & integrity tests
- Added non-mutating integrity check command `php artisan accounting:phase3-integrity-check` covering Customer Receipts, Supplier Payments, AR/AP Allocations, Cheque Lifecycles, Bank Reconciliations, and Report read-only invariants.
- Added Phase 3 concurrency stress orchestrator command `php artisan accounting:phase3-stress {--workers=50}` executing PostgreSQL row-locking concurrency scenarios across all Phase 3 workflows.
- Created `Phase3Slice9StressIntegrityTest.php` feature test suite (6/6 passing, 262 assertions) validating receipt/payment posting idempotency, period close posting locks, allocation over-pressure bounds, report read-only behavior, integrity check artisan command, and strict anti-tenancy/company-scoping rules.
- Verified 242 total PHPUnit passing tests, 0 TypeScript errors (`npm run typecheck`), clean Pint formatting (`vendor/bin/pint --test`), and Vite asset compilation (`npm run build`).

### Added — Phase 3 Slice 8 operational/subledger reports
- Added `reports.view` permission, Reports Hub, and protected report endpoints.
- Implemented read-only report services and Inertia pages for Customer Statement, Supplier Statement, AR Aging, AP Aging, Cash Book, Bank Book, Cheque Register, Bank Reconciliation status/detail, AR to GL reconciliation, and AP to GL reconciliation.
- Added streaming CSV exports for report downloads.
- Kept reports derived from existing durable Phase 2/Phase 3 records only: no fake Sales/Purchase invoice aging, no accounting mutation, no bank import, no automatic adjustment posting, and no tenant/company/branch scope.
- Verified `Phase3Slice8ReportsTest.php` 12/12 tests / 180 assertions, `php artisan test` 236 passing tests reported after Slice 8, `vendor/bin/pint --test`, `npm run typecheck`, and `npm run build`.

### Added — Phase 3 Slice 7 Inertia pages & UX actions
- Created 13 Http Controllers (`CustomerController`, `SupplierController`, `CashAccountController`, `BankAccountController`, `CustomerOpeningBalanceController`, `SupplierOpeningBalanceController`, `CustomerReceiptController`, `SupplierPaymentController`, `ReceivableAllocationController`, `PayableAllocationController`, `IncomingChequeController`, `OutgoingChequeController`, `BankReconciliationController`).
- Registered 13 web route endpoints in `routes/web.php` covering index, store, update, post, reverse, lifecycle state transitions, and bank reconciliation line matching/finalization.
- Created 14 Inertia pages under `resources/js/Pages/` with rich aesthetics, zero emojis, full English/Arabic (RTL) support, accessible form modals, and real-time status badges.
- Implemented custom React `DatePicker.tsx` component supporting English and Arabic locales, 3x4 month/decade grid views, preset ranges, min/max bounds, and SVG navigation icons.
- Updated `AppLayout.tsx` sidebar navigation with expandable groups for AR/Customers, AP/Suppliers, and Cash/Bank/Cheques.
- Created `Phase3Slice7UiTest.php` feature test suite with 13/13 passing tests.
- Verified zero TypeScript errors with `npm run typecheck`, 0 fontaine warnings with `npm run build`, and `php artisan test` 226 total / 224 passed / 2 skipped, 1622 assertions.

### Added — Phase 3 Slice 6 bank reconciliation
- Implemented `bank_reconciliation` header and `bank_reconciliation_line` statement matching models and migration (`2026_08_22_000000_create_phase3_slice6_bank_reconciliation_tables.php`).
- Created `CashBookQueryService` and `BankBookQueryService` derived strictly from immutable posted `ledger_entry` rows.
- Implemented `BankReconciliationService` handling draft creation, statement line management, candidate ledger entry lookup, line matching, unmatching, dynamic summary computation, and strict zero-difference finalization checks.
- Added PostgreSQL partial unique index `bank_recon_line_matched_ledger_unique` to prevent duplicate ledger entry matching globally across statement lines.
- Registered RBAC permission `banks.reconcile` and attachment entity `bank_reconciliation`.
- Built `accounting:bank-reconciliation-concurrency-stress --workers=50` command verifying concurrent duplicate-match protection and idempotent finalization.
- Hardened matching date/currency validation, deterministic header-first lock ordering, and DB-level immutability triggers for finalized reconciliation headers/lines.
- Verified with `php artisan test` 213 total / 211 passed / 2 PostgreSQL-specific skipped, 1510 assertions; Phase 3 Slice 6 suite 11/11; Concurrency suite 7/7; PostgreSQL concurrency/accounting/allocation/cheque/bank-reconciliation stress commands; TypeScript typecheck; and Vite build.
### Added — Phase 3 Slice 6 bank reconciliation prompt
- Added `PHASE_3_SLICE_6_GEMINI_PROMPT.md` as the bounded execution contract for ledger-backed bank reconciliation, cash/bank book query foundations, strict reconciliation lifecycle, duplicate-match/finalize concurrency stress, Spatie-backed audit, and attachment/RBAC integration.
- Explicitly kept bank statement import, bank feed/OCR parsing, automatic bank adjustment posting, broad Slice 7 UI, Sales/Purchasing/Inventory, and full financial statements out of Slice 6.

### Added — Phase 3 Slice 5 cheque lifecycle
- Added `incoming_cheque` and `outgoing_cheque` records with pre-clear state machines for incoming receive/deposit/clear/bounce/return and outgoing issue/clear/return/cancel.
- Added configurable `cheques_under_collection` and `cheques_payable` accounting mappings without company, branch, or tenant dimensions.
- Routed cheque accounting effects through the existing PostingEngine and preserved AR/AP subledger effects for received/issued and bounced/returned/cancelled pre-clear cheques.
- Added idempotent cheque transition services, attachment entity registry entries, Spatie Activitylog audit writes through `AuditLogger`, and owner-decision guards for post-clear bounce/return workflows.
- Hardened cheque concurrency with `accounting:cheque-concurrency-stress --workers=50`, covering concurrent clear replay, incoming clear-vs-bounce races, and outgoing duplicate clear prevention.
- Verified with `php artisan test` 202 total / 200 passed / 2 PostgreSQL-specific skipped, 1464 assertions; Phase 3 Slice 5 suite 8/8; Concurrency suite 7/7; PostgreSQL concurrency/accounting/allocation/cheque stress commands; TypeScript typecheck; and Vite build.
- Added `PHASE_3_SLICE_5_GEMINI_PROMPT.md` as the historical bounded execution contract; bank reconciliation, reports, broad cheque register UI, Sales/Purchasing/Inventory, and post-clear cheque bounce/return semantics remain outside Slice 5.

### Added — Phase 3 Slice 4 allocation engine
- Added `receivable_allocation` and `payable_allocation` settlement records with restrict foreign keys and PostgreSQL row checks.
- Added CustomerReceipt-to-ReceivableEntry and SupplierPayment-to-PayableEntry allocation/reversal services without creating GL, journal, ledger, receivable, or payable posting rows.
- Preserved `allocated_minor + unapplied_minor = amount_minor` on receipts/payments while preventing AR/AP over-allocation.
- Hardened allocation concurrency with deterministic parent/target/allocation lock order, active allocation row locking before remaining-balance calculation, and idempotent create/reversal commands.
- Reworked `accounting:allocation-concurrency-stress --workers=50` to use true concurrent workers for AR and AP allocation pressure plus shared idempotency replay checks.
- Verified with `php artisan test` 194 total / 192 passed / 2 PostgreSQL-specific skipped, 1413 assertions; Phase 3 Slice 4 suite 7/7; Concurrency suite 7/7; PostgreSQL concurrency/accounting/allocation stress commands; TypeScript typecheck; and Vite build.

### Added — Phase 3 Slice 3 customer receipts and supplier payments
- Added Customer Receipt and Supplier Payment draft/post services using the existing Accounting PostingEngine only.
- Added global receipt/payment numbering with `REC-YYYY-XXXXX` and `PAY-YYYY-XXXXX`.
- Added AR/AP subledger effects and unapplied balance tracking for posted receipts/payments without implementing allocation behavior yet.
- Hardened receipt/payment integrity with linked GL currency validation, delete restriction for referenced customer/supplier rows, status checks, amount checks, `allocated + unapplied = amount`, and exactly-one CashAccount/BankAccount checks.
- Verified with `php artisan test` 187 total / 185 passed / 2 PostgreSQL-specific skipped, 1377 assertions; Concurrency suite 7/7; Phase 3 Slice 3 suite 14 total / 12 passed / 2 PostgreSQL-specific skipped; PostgreSQL stress commands; TypeScript typecheck; and Vite build.

### Added — Phase 3 Slice 2 AR/AP subledgers and opening balances
- Added Customer and Supplier opening-balance services that post through the existing Accounting PostingEngine and create durable `receivable_entry` / `payable_entry` subledger rows.
- Added global accounting account mappings for `ar_control`, `ap_control`, and `opening_balance_offset`, with account classification, active-account, and currency validation.
- Added PostgreSQL integrity hardening for active opening-balance uniqueness, source uniqueness, statuses, and positive/non-negative accounting amounts.
- Hardened Slice 2 validation so financial periods must belong to the selected fiscal year, duplicate active opening balances are rejected, non-unit FX is blocked until exact FX posting exists, and mapped account currencies must match the opening balance currency.
- Verified with `php artisan test` 173 tests / 1304 assertions, Phase 3 Slice 2 suite 14/14, Concurrency suite 7/7, PostgreSQL stress commands, TypeScript typecheck, and Vite build.
- Added `PHASE_3_SLICE_3_GEMINI_PROMPT.md` for the next bounded implementation slice: receipt/payment posting without allocations.

### Added — Phase 3 Slice 1 master data foundation
- Added Customer and Supplier master-data tables, models, and application services with globally unique codes, multilingual names, statuses, provenance fields, optimistic locking, and Spatie Activitylog audit writes through `AuditLogger`.
- Added CashAccount and BankAccount tables, models, and services linked to active GL accounts and system currencies, with optimistic locking and attachment entity registry entries.
- Hardened Slice 1 updates so nullable contact/bank fields can be cleared intentionally and `is_active=false` updates are preserved.
- Verified no `company_id`, `branch_id`, `tenant_id`, current-company/current-branch context, or Spatie Teams behavior was introduced.
- Verified with `php artisan test` 159 tests / 1243 assertions, Phase 3 Slice 1 suite 14/14, Concurrency suite 7/7, PostgreSQL stress commands, TypeScript typecheck, and Vite build.

### Corrected — Phase 3 planning contract
- Added `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md` as the corrected Phase 3 scope/contract proposal.
- Added Bank Reconciliation to Phase 3 scope.
- Removed the unsupported generic manual AR/AP adjustment assumption; generic manual receivable/payable adjustment remains `UNDEFINED - DO NOT ASSUME`.
- Defined cheque accounting lifecycle requirements, cheque state-machine constraints, allocation concurrency/idempotency requirements, receipt/payment reversal owner-decision gates, and Phase 3 PostgreSQL stress-test expectations.
- Confirmed Phase 3 must not introduce company, branch, or tenant scope and must not start Sales, Purchasing, or Inventory.
- Clarified that Phase 3 audit uses the owner-approved Spatie Activitylog backend through the existing `AuditLogger`; legacy `audit_log` remains archive only.
- Added `PHASE_3_SLICE_1_GEMINI_PROMPT.md` for the first bounded implementation slice: Customer/Supplier and Cash/Bank master-data foundation only.
- Added `PHASE_3_SLICE_2_GEMINI_PROMPT.md` for the next bounded implementation slice: AR/AP subledger and customer/supplier opening balances only.

### Added — Laravel M10 Spatie Activitylog, audit viewer, scheduler, and jobs baseline
- Installed `spatie/laravel-activitylog` 4.12.3 and made Spatie `activity_log` the active audit backend.
- Kept `App\Domain\Audit\AuditLogger::record(...)` as the central application adapter while routing new writes through Spatie Activitylog.
- Preserved legacy `audit_log` as a read-only archive; no new application writes should target it.
- Added backward-compatible audit query aliases so the audit UI still receives `actor_id`, `actor_name`, `action`, `entity_type`, `entity_id`, `before_json`, `after_json`, `reason`, `request_id`, `ip`, `device`, and `at`.
- Added append-only DB triggers for both `activity_log` and legacy `audit_log` on PostgreSQL and SQLite.
- Added the read-only `/audit-log` Inertia page protected by `audit.view` or `settings.configure`.
- Registered `tokens:gc --batch=100` hourly with `withoutOverlapping()` and verified jobs/failed_jobs/job_batches baseline behavior.
- Verified with `php artisan test` 145 tests / 1185 assertions, Concurrency suite 7/7, PostgreSQL stress commands, TypeScript typecheck, and Vite build.

### Added — Laravel Phase 2 Accounting Core
- Implemented the Laravel accounting ledger spine: account categories, account types, account groups/accounts, FX rates, fiscal periods, manual journals, posting engine, immutable ledger entries, reversal workflow, opening balances, General Journal, General Ledger, and Trial Balance.
- Added database foreign keys for currency relationships across accounting tables.
- Added account type/category CRUD pages and control-account behavior.
- Added demo accounting seeder and polished empty states for accounting reports.
- Added PostgreSQL accounting stress verification through `php artisan accounting:concurrency-stress --workers=50`.
- Preserved the single-ERP context: no company/branch/tenant dimensions were introduced into accounting tables.

### Added — Laravel M9 attachments and notifications services
- Added attachment upload/list/download/delete service behavior with extension, MIME, and size validation.
- Added explicit allowlisted entity authorization for attachment entities and storage cleanup compensation on failure.
- Added reusable `AttachmentPanel` integration for supported entities.
- Added notification service creation/list/unread/mark-read/mark-all-read behavior with per-user dedupe.
- Triggered user notifications for role assign/revoke actions.

### Added — Laravel M8 settings/user actions
- Added real create/update actions for company profile, standalone branch references, numbering sequences, and role assignment/revocation.
- Hardened permissions so empty RBAC assignments do not grant management mutations.
- Preserved no-tenant/no-current-company behavior across settings actions.

### Corrected — Laravel fiscal-year single-ERP context
- Removed unsupported `fiscal_year.company_id`; fiscal years are now global to this ERP installation/business profile with global `year` uniqueness.
- Preserved `financial_period.fiscal_year_id` so financial periods belong to fiscal years without Company/Tenant semantics.
- Added a migration guard that blocks the correction if existing data contains duplicate fiscal years for the same global year.

### Corrected — Laravel bootstrap admin seeding
- Made local bootstrap admin role assignment explicit and config-controlled: `DatabaseSeeder` seeds RBAC before the bootstrap user, then assigns the configured global `SUPER_ADMIN` role without company, branch, tenant, or current-company scope.
- Added `FirstUserSuperAdminSeeder` so the first user in the installation receives `SUPER_ADMIN` explicitly.
- Added coverage for the default bootstrap admin permission path, disabling bootstrap role assignment, and first-user super-admin assignment.

### Corrected — Laravel post-audit security and documentation pass
- Removed the implicit settings/user-management authorization fallback: empty RBAC assignments now deny management mutations instead of granting bootstrap privileges.
- Added explicit allowlisted attachment entity authorization; unknown entity types and missing/unauthorized entities deny by default.
- Added attachment storage failure compensation so a metadata/audit persistence failure deletes the newly stored file.
- Renamed the misleading global `COMPANY_ADMIN` role template to `ERP_ADMIN` and added a migration path for existing development data.
- Reclassified `fiscal_year.company_id` as OWNER DECISION REQUIRED; later resolved by removing the column for single-ERP fiscal years.
- Corrected current documentation to prevent reintroducing Company/Branch tenancy, company-scoped RBAC, or company/branch numbering dimensions.

### Corrected — Laravel Company/Branch/User relationship assumptions
- Removed unsupported Company/User membership (`company_user`) from the Laravel target.
- Removed unsupported `branch.company_id`, Company-to-Branch Eloquent relationships, and per-company branch-code uniqueness.
- Removed Company and Branch dimensions from document numbering; numbering remains atomic and unique by sequence key.
- Removed unsupported `company_id`/`branch_id` scope columns from audit logs while preserving actor, entity, action, before/after, redaction, and append-only behavior.
- Removed unsupported `company_id` scope columns from attachments and notifications; attachments remain entity-linked and notifications remain user-targeted with per-user dedupe.
- Updated Laravel tests and documentation so future work treats undefined relationships as `UNDEFINED - DO NOT ASSUME`.

### Added — Laravel migration M7-M10 backend parity
- Ported Laravel core-kernel primitives for exact integer-minor-unit Money, currency exponents, double-entry accounting invariants, typed domain errors, and document number formatting/config.
- Added Laravel `tests/Invariants` coverage for money exactness/allocation, accounting balance/well-formed lines, and deterministic numbering.
- Added working settings actions for company create/update, branch create/update, numbering create/update, and role assign/revoke with explicit IDs and no current-company or tenant session.
- Added notification and attachment application services, attachment upload/download routes, notification dedupe/list/mark-read behavior, and service/feature tests.
- Added append-only audit logging with sensitive-field redaction and wired audit records to company/branch/numbering/attachment mutations without inventing organizational scope. This is now backed by Spatie Activitylog for new writes.
- Added an idempotent job runner/backoff primitive and scheduled `tokens:gc --batch=100` hourly with overlap protection.

### Added — Laravel migration M6 app pages
- Migrated the authenticated Laravel Inertia app shell and pages for dashboard, settings hub, companies, branches, numbering, users/roles, and notifications.
- Changed post-login flow to land on `/dashboard`; kept `/foundation` as the migration diagnostic page.
- Wired page props to real Laravel/PostgreSQL data only: company/branch records, number sequences, native users, Spatie roles/permissions, and user notifications.
- Added notification mark-read handling scoped to the signed-in user and shared unread notification counts.
- Added feature coverage for every migrated page and notification mark-read behavior.

### Added — Laravel concurrency hardening
- Added a Laravel concurrency audit at `docs/CONCURRENCY_AUDIT.md` covering current mutation surfaces, lock ordering, idempotency, retries, token cleanup, and future posting/job risks without reintroducing SaaS tenant assumptions.
- Added an `idempotency_keys` table, operation/key/scope uniqueness, status checks on PostgreSQL, and a database-backed idempotency store that never logs raw keys.
- Added optimistic locking primitives with `lock_version` columns on `company` and `branch`, localized conflict messages in EN/AR, and exception rendering for JSON/Inertia requests.
- Added PostgreSQL-safe number sequence allocation using `INSERT ... ON CONFLICT ... DO UPDATE RETURNING`.
- Added bounded authentication garbage collection for expired database sessions, password reset tokens, and idempotency keys via `php artisan tokens:gc`.
- Added notification dedupe-key schema protection and `php artisan concurrency:stress` for PostgreSQL stress verification.
- Added a dedicated Laravel `Concurrency` PHPUnit suite covering sequence allocation, idempotency replay/conflict behavior, stale optimistic updates, token GC, notification dedupe, and localization.

### Corrected — Laravel architecture review
- Added `DOMAIN_MODEL_REVIEW.md` to classify confirmed ERP relationships versus old multi-tenant implementation artifacts.
- Removed the Laravel tenant context, tenant middleware, first-run onboarding assumption, Inertia `tenant` shared prop, and Spatie Permission company/team scope.
- Corrected Laravel RBAC so role templates are global and authorization scope remains explicit `scope_json`, not company-owned Spatie roles.
- Historical Next.js entries below may mention tenant wording because they describe the existing reference app, not the corrected Laravel target.

### Added — Laravel migration M5 authentication schema
- Extended Laravel's native `users` table with locale, theme, active-account, and MFA status fields while preserving the existing session and password-reset tables.
- Added PostgreSQL constraints for the supported locales/themes and an index for active-user filtering.
- Made Argon2id the Laravel password-hashing default using the same memory/time/parallelism parameters as the verified Next.js reference.
- Added integration coverage for auth columns, defaults, casts, mass assignment, and Argon2id password hashing; applied the migration successfully to local PostgreSQL.
- Added Laravel session login/logout with CSRF, active-account checks, login throttling, session regeneration, logout invalidation, a protected Inertia foundation route, and a local bootstrap admin seeder.

### Added — Laravel migration M3 database foundation
- Added Laravel migrations for the ERP foundation tables around the native `users` table: company, branch, currency, exchange rates, fiscal years/periods, number sequences, audit log, attachments, and notifications.
- Added Spatie Translatable-backed Company, Branch, and Currency models with JSON multilingual `name` columns.
- Added permission module/action metadata, assignment scope JSON, and seeded the module/action catalog plus 9 global role templates without Spatie teams.
- Added Laravel integration tests for schema constraints, currency seeding, and RBAC template seeding; verified migrations/seeds against a temporary PostgreSQL database.

### Added
- Project scaffold: Next.js (App Router) + TypeScript + Prisma + Zod + Tailwind, modular-monolith structure (24 modules + core kernel).
- Core kernel (tested, legacy Next.js snapshot): exact **Money** value object (BigInt minor units, exact allocation), **accounting-kernel** (`assertBalanced` Σdr=Σcr), concurrency-safe **numbering**, legacy RBAC experiment, typed **errors**, **audit** types, **currency** registry (EGP seed, multi-currency). Tenant-isolation wording from this historical snapshot is superseded by `NO_MULTI_TENANT_POLICY.md`.
- Prisma kernel schema (company, branch, user, role, permission, currency, exchange rate, fiscal year/period, number sequence, audit log, attachment, notification).
- i18n (EN/AR) + RTL/LTR + design tokens/theming wired into the App Router.
- CI workflow with a **blocking accounting-invariant job**.
- Documentation set: ARCHITECTURE, SECURITY, TESTING_STRATEGY, DEPLOYMENT, DISASTER_RECOVERY, PHASE1_STATUS, plus README/ROADMAP/IMPLEMENTATION_STATUS.
- Design system built in Figma ("Mini ERP — Design System & UI") + live style-guide.html.

### Added — Phase 1 application layer (real + unit-tested)
- **Auth:** credentials authentication service (anti-enumeration, generic errors, no hash leakage), Argon2id hasher adapter, fixed-window rate limiter, session + route guards.
- **RBAC:** full permission catalog (24 modules × actions + sensitive capabilities), 9 deny-by-default role templates (SUPER_ADMIN…VIEWER), pure seed plan + Prisma seed.
- **Legacy tenant experiment:** superseded by the Laravel no-multi-tenant policy.
- **Audit:** append-only audit service with field diff, sensitive-field redaction, requestId.
- **Numbering:** configuration + allocation application service over the concurrency-safe engine.
- **Attachments:** storage abstraction + validation + local-disk adapter in the legacy reference app; company-scope wording is superseded in Laravel.
- **Notifications:** in-app notification service (create/list/read, channel interface) in the legacy reference app; company-scope wording is superseded in Laravel.
- **Jobs:** queue-agnostic job runner (idempotency + exponential backoff) + pg-boss adapter + worker entrypoint.
- **Company:** company/branch onboarding + settings service (validated; owner admin role seeded).

### Added — Phase 1 integration layer
- **DB:** Prisma client singleton + repositories (user, audit append-only, numbering with atomic `INSERT … ON CONFLICT DO UPDATE RETURNING`). Repositories are the only DB-touching layer.
- **Auth.js:** NextAuth v5 credentials config wired to the tested auth service + Argon2 + Prisma user repo; JWT session carries server-derived companyId + RBAC grants; login screen (EN/AR, tokens, light/dark); `requireAuth` route guard.
- **CI:** now provisions a Postgres service, runs `prisma db push`, and executes the DB-gated numbering-concurrency integration test alongside the blocking invariant suite. Working directory set to `app/`; triggers on main + develop.

### Added / Fixed — toolchain hardening (verified via real install)
- Generated **package-lock.json** (CI `npm ci` now works).
- **TS-aware ESLint** (typescript-eslint) — `npm run lint` passes clean at `--max-warnings=0`.
- Fixed **pg-boss v10** adapter (batch `Job[]` work handler, `pollingIntervalSeconds`, `includeMetadata`).
- Fixed login **server-action signature** (+ generic error display via `?error=1`).
- Lint/type nits: `const` in money.allocate, unused imports, test cast, tailwind token typing.

### Verification (this increment, real tooling)
- `npm install` (319 pkgs) ✓ · `eslint --max-warnings=0` ✓ · `vitest` 57 passed / 1 skipped ✓.
- `tsc --noEmit`: only 5 errors remain, all from the **ungenerated Prisma client** (blocked binaries.prisma.sh in the sandbox); CI's `prisma generate` resolves them.

### Added — reusable UI + app shell (locally typechecked + linted)
- UI primitives: **Button** (primary/secondary/ghost/danger + loading/disabled), **Input** (label/error/hint), **StatusBadge** (colour + dot + label, never colour-alone), **Card / PageHeader / EmptyState / PermissionDenied**. Token-styled, RTL-safe via logical CSS properties, light/dark via variables.
- **AppShell** (sidebar + topbar, localized nav, active state) and a **protected route group** (`(app)/layout` enforces `requireAuth`) with a **dashboard shell** that shows an EmptyState — no mock KPIs.
- Verified: `eslint --max-warnings=0` clean; `vitest` 57 passed; `tsc` adds zero new errors.

### Added — auth route + Settings (locally verified)
- **NextAuth route handler** (`/api/auth/[...nextauth]`, Node runtime) — credentials flow is now end-to-end.
- **Company settings**: `SettingsService` (validated) + `PrismaSettingsRepository` (JSON column `settingsJson`), a **Settings hub** and a **Company settings screen** (currency/locale/timezone/fiscal-start) built from the UI components, EN/AR, server-action persistence in the legacy reference app; tenant-context wording is superseded in Laravel.
- Verified: `eslint --max-warnings=0` clean; `vitest` **60 passed**; `tsc` clean except Prisma-client generation (CI).

### Added — Branches + Numbering settings (locally verified)
- **BranchService** (unique code per company, validation) + `PrismaBranchRepository` + tests.
- **Branches settings screen** (list + add) and **Numbering settings screen** (list configs + add/update sequence with reset policy + next-number preview), wired to the tested services, EN/AR in the legacy reference app; tenant-context wording is superseded in Laravel.
- Verified: lint clean; `tsc` clean except Prisma-client generation (CI); `vitest` **62 passed / 1 skipped**.

### Added — onboarding, users, attachments, notifications, and E2E smoke
- **First-run onboarding**: `/[locale]/onboarding` plus `PrismaCompanyRepository` that atomically creates company + first branch + global permissions + 9 company role templates + owner membership + `COMPANY_ADMIN`.
- **Users & Roles settings**: `PrismaUserAdminRepository`, `UserAdminService`, and `/settings/users` for listing users/roles and assigning/revoking roles with server-side RBAC permission-denied state.
- **Attachments end-to-end foundation**: attachment schema now stores `mime` + `size`; added Prisma metadata repository and scoped upload/download route handlers backed by the local storage adapter.
- **Notifications UI**: `PrismaNotificationRepository`, header notifications link/count, `/notifications` center, and mark-read action.
- **Playwright smoke E2E**: config + smoke suite for locale direction, unauthenticated redirect, DB-backed login, dashboard/settings navigation, and permission-denied path; CI job provisions Postgres and installs Chromium.

### Fixed — runtime/build blockers
- Converted next-intl locale messages from flat dotted keys to nested objects, fixing `INVALID_KEY` / `MISSING_MESSAGE` runtime errors.
- Added PostCSS config for Tailwind directives and converted `design/tailwind.tokens.js` to ESM, fixing Next/Turbopack build failures.
- Fixed Prisma JSON typing in settings persistence.

### Verification — 2026-08-21
- Local PostgreSQL verification: `prisma generate` ✓ · `prisma db push` ✓ · `prisma seed` ✓ · `npm run ci` ✓ · `next build` ✓ · `playwright` smoke **5 passed / 0 skipped** ✓.
- Vitest: **17 files / 66 tests passed** with DB-backed integration enabled. Invariants: **4 files / 23 tests passed**.
- Onboarding transaction verified: company + branch + 9 roles + 458 permission links + owner membership + `COMPANY_ADMIN`; cross-company role leakage = 0.
- GitHub Actions CI run `32440676342` completed `success` for `develop`.

### Tests
- 66 Vitest tests pass with PostgreSQL. 5 Playwright smoke tests pass with PostgreSQL-backed auth/RBAC. Invariant suite remains blocking.
