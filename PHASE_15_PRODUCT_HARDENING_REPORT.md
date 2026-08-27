# Phase 15 Product Hardening - Slices 1-134 Report

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices.

## Status

Phase 15 Product Hardening is in progress.

- Slice 1 is complete as a focused security and UI text hardening pass.
- Slice 2 is complete as a focused controller/service boundary cleanup pass.
- Slice 3 is complete as a focused security-rule centralization pass for user and role administration.
- Slice 4 is complete as a financial posting route authorization hardening pass.
- Slice 5 is complete as a broad controller/service boundary cleanup pass for the largest controllers.
- Slice 6 is complete as an accountant-focused UX consistency pass for General Ledger and VAT report pages.
- Slice 7 is complete as a sensitive-action confirmation and settlement-page dictionary cleanup pass.
- Slice 8 is complete as a dense operational workflow confirmation and Arabic rental-contract localization repair pass.
- Slice 9 is complete as a user/role permission administration dictionary and security-label cleanup pass.
- Slice 10 is complete as a foundation settings dictionary and confirmation cleanup pass.
- Slice 11 is complete as an operational document UX cleanup pass removing silent currency, UOM, and warehouse fallbacks from sales, purchasing, and inventory pages.
- Slice 12 is complete as a payroll, expense, rental, and fixed-asset UX cleanup pass removing silent `EGP` fallbacks and adding neutral dictionary-backed missing-currency labels.
- Slice 13 is complete as a tax master-data UI dictionary cleanup pass for Tax Codes and Tax Rates.
- Slice 14 is complete as a tax-period filing UI dictionary cleanup pass.
- Slice 15 is complete as a master-data delete-confirmation UX pass with entity-specific confirmation copy.
- Slice 16 is complete as an accounting master-data form/detail dictionary cleanup pass.
- Slice 17 is complete as an FX-rate base-currency correctness and dictionary cleanup pass.
- Slice 18 is complete as a currency master-data dictionary cleanup pass.
- Slice 19 is complete as a Chart of Accounts explicit-currency and dictionary cleanup pass.
- Slice 20 is complete as a Trial Balance backend display-currency and dictionary cleanup pass.
- Slice 21 is complete as a General Journal dictionary cleanup pass.
- Slice 22 is complete as a Journal Detail dictionary cleanup pass.
- Slice 23 is complete as a Journal Form explicit-currency and dictionary cleanup pass.
- Slice 24 is complete as an Opening Balances dictionary cleanup pass.
- Slice 25 is complete as a Fiscal Periods permission and empty-state UX cleanup pass.
- Slice 26 is complete as an accounting navigation dictionary cleanup pass.
- Slice 27 is complete as a Financial Statement Mapping delete-confirmation hardening pass.
- Slice 28 is complete as an Account Mapping delete-confirmation hardening pass.
- Slice 29 is complete as an Accounting landing-page dictionary cleanup pass.
- Slice 30 is complete as a Reports Hub tax-report dictionary cleanup pass.
- Slice 31 is complete as a VAT-to-GL Reconciliation dictionary and hidden-currency cleanup pass.
- Slice 32 is complete as an AR/AP Aging and AR/AP GL Reconciliation hidden-currency cleanup pass.
- Slice 33 is complete as an operational report mixed-currency summary hardening pass.
- Slice 34 is complete as a tax period and VAT report typed-dictionary cleanup pass.
- Slice 35 is complete as a tax master-data typed-dictionary and unavailable-label cleanup pass.
- Slice 36 is complete as an Audit Log typed-dictionary and legacy fallback cleanup pass.
- Slice 37 is complete as an AppLayout navigation/header typed-dictionary cleanup pass.
- Slice 38 is complete as an accounting master-data typed-dictionary and select-value cleanup pass.
- Slice 39 is complete as an accounting dense-page typed-dictionary cleanup pass for Chart of Accounts, Fiscal Periods, Opening Balances, and Trial Balance.
- Slice 40 is complete as a journal and ledger dense-page typed-dictionary cleanup pass for General Ledger, General Journal, Journal Form, and Journal Detail.
- Slice 41 is complete as a fixed-asset register, disposal, and depreciation-run typed-dictionary cleanup pass.
- Slice 42 is complete as a cross-module select-value parser and cash/bank operational fallback cleanup pass.
- Slice 43 is complete as a sales/purchasing document line-editor typing cleanup pass.
- Slice 44 is complete as a final visible Pages `as any` cleanup pass for Landed Costs and Customer Invoices.
- Slice 45 is complete as a sales/purchasing/catalog operational missing-label cleanup pass.
- Slice 46 is complete as a sales invoice revision missing-label cleanup pass.
- Slice 47 is complete as an AR/AP cash/bank explicit-currency and missing-label cleanup pass.
- Slice 48 is complete as a treasury, inventory transfer, and settings missing-label cleanup pass.
- Slice 49 is complete as a journal-detail and fixed-asset disposal monetary display cleanup pass.
- Slice 50 is complete as a report-table zero/unavailable marker dictionary cleanup pass.
- Slice 51 is complete as a fixed-asset financial value formatting and restricted-marker cleanup pass.
- Slice 52 is complete as a visible-pages hardcoded EGP/USD currency-literal cleanup pass.
- Slice 53 is complete as a VAT/Tax explicit-currency formatting pass.
- Slice 54 is complete as a backend report currency-default cleanup pass.
- Slice 55 is complete as an operational service explicit-currency validation and FX-rate default cleanup pass.
- Slice 56 is complete as a console command, integrity check, and seeder currency-assumption cleanup pass.
- Slice 57 is complete as a financial posting UI permission-parity cleanup pass.
- Slice 58 is complete as an inventory dense-table monetary display and missing-warehouse cleanup pass.
- Slice 59 is complete as a state-changing route authorization regression pass.
- Slice 60 is complete as an AR/AP posting confirmation clarity pass.
- Slice 61 is complete as a sales/purchasing invoice source-document TypeScript cleanup pass.
- Slice 62 is complete as an AR/AP cash/bank pagination-link TypeScript cleanup pass.
- Slice 63 is complete as a sales/purchasing backend flash-message localization pass.
- Slice 64 is complete as a remaining controller success-flash localization pass.
- Slice 65 is complete as a backend guard/error-message Arabic localization pass.
- Slice 66 is complete as a financial service error-message localization pass.
- Slice 67 is complete as an AR/AP cash-bank validation localization pass.
- Slice 68 is complete as a treasury-transfer validation localization pass.
- Slice 69 is complete as a branch-approval rule validation localization pass.
- Slice 70 is complete as a tax service validation localization pass.
- Slice 71 is complete as an expense/prepaid/accrual service validation localization pass.
- Slice 72 is complete as a payroll service validation localization pass.
- Slice 73 is complete as an inventory workflow service validation localization pass.
- Slice 74 is complete as a moving-weighted-average inventory costing validation localization pass.
- Slice 75 is complete as a rentable item and rental contract validation localization pass.
- Slice 76 is complete as a rental fulfillment validation localization pass.
- Slice 77 is complete as a rental invoice validation localization pass.
- Slice 78 is complete as a fixed-asset application service validation localization pass.
- Slice 79 is complete as a sales/purchasing invoice and bill validation localization pass.
- Slice 80 is complete as a returns, credit-note, purchase-return, and supplier-adjustment validation localization pass.
- Slice 81 is complete as a sales/purchasing order and fulfillment validation localization pass.
- Slice 82 is complete as a catalog and customer/supplier master-data validation localization pass.
- Slice 83 is complete as an accounting mapping and financial statement mapping validation localization pass.
- Slice 84 is complete as a bank reconciliation, cash/bank book query, and cheque validation localization pass.
- Slice 85 is complete as a landed cost allocation validation localization pass.
- Slice 86 is complete as an AR/AP allocation validation localization pass.
- Slice 87 is complete as an AR/AP entry settlement validation localization pass.
- Slice 88 is complete as an AR/AP receipt/payment and opening-balance validation localization pass.
- Slice 89 is complete as an invoice revision and shared currency-input validation localization pass.
- Slice 90 is complete as a report export error-message localization pass.
- Slice 91 is complete as a statement report CSV exporter/controller-boundary cleanup pass.
- Slice 92 is complete as an AR/AP and cheque report CSV exporter/controller-boundary cleanup pass.
- Slice 93 is complete as a financial-statement and branch-profitability CSV exporter/controller-boundary cleanup pass.
- Slice 94 is complete as a centralized report CSV stream lifecycle cleanup pass.
- Slice 95 is complete as a financial statement period-option service extraction pass.
- Slice 96 is complete as a bank reconciliation report missing-reference UI localization pass.
- Slice 97 is complete as a dashboard missing-user fallback localization pass.
- Slice 98 is complete as an app-shell language-switcher dictionary cleanup pass.
- Slice 99 is complete as a bank reconciliation finalization-confirmation dictionary cleanup pass.
- Slice 100 is complete as a catalog product Arabic select-label cleanup pass.
- Slice 101 is complete as a catalog category/UOM form label and placeholder dictionary cleanup pass.
- Slice 102 is complete as a Dashboard controller/page-data boundary cleanup pass.
- Slice 103 is complete as a Customer/Supplier controller page-data boundary cleanup pass.
- Slice 104 is complete as a Cash/Bank Account controller page-data boundary cleanup pass.
- Slice 105 is complete as a Trial Balance financial-period option boundary cleanup pass.
- Slice 106 is complete as an AR/AP Opening Balance controller page-data boundary cleanup pass.
- Slice 107 is complete as an AR/AP Receipt/Payment controller page-data boundary cleanup pass.
- Slice 108 is complete as an Incoming/Outgoing Cheque controller page-data boundary cleanup pass.
- Slice 109 is complete as an AR/AP Allocation controller page-data boundary cleanup pass.
- Slice 110 is complete as an AR/AP Entry Settlement controller page-data boundary cleanup pass.
- Slice 111 is complete as a Sales/Purchase Order controller page-data boundary cleanup pass.
- Slice 112 is complete as a Delivery Note/Goods Receipt controller page-data boundary cleanup pass.
- Slice 113 is complete as a Customer Invoice Revision controller page-data boundary cleanup pass.
- Slice 114 is complete as an Accounting Account Mapping controller page-data boundary cleanup pass.
- Slice 115 is complete as an Accounting Overview controller page-data boundary cleanup pass.
- Slice 116 is complete as an Account Category/Type controller page-data boundary cleanup pass.
- Slice 117 is complete as a Journal/Opening Balance controller page-data boundary cleanup pass.
- Slice 118 is complete as a remaining Accounting master-data controller page-data boundary cleanup pass.
- Slice 119 is complete as a Catalog controller page-data boundary cleanup pass.
- Slice 120 is complete as an expense/prepaid/accrual controller page-data boundary cleanup pass.
- Slice 121 is complete as a fixed-asset location/disposal controller page-data boundary cleanup pass.
- Slice 122 is complete as a payroll controller page-data boundary cleanup pass.
- Slice 123 is complete as a rentals operational controller page-data boundary cleanup pass.
- Slice 124 is complete as an inventory and warehouse controller page-data boundary cleanup pass.
- Slice 125 is complete as a landed-cost and treasury-transfer controller page-data boundary cleanup pass.
- Slice 126 is complete as a tax controller page-data boundary cleanup pass.
- Slice 127 is complete as a report-controller selector option cleanup pass.
- Slice 128 is complete as a remaining settings and audit controller query/persistence cleanup pass.
- Slice 129 is complete as an accountant-facing operational report filter UX pass with visible currency filtering, reset actions, and a shared report filter panel.
- Slice 130 is complete as an inventory report filter UX pass applying the shared filter panel to Delivery Notes, Goods Receipts, and Stock Movements reports, including visible currency filtering for stock value movements.
- Slice 131 is complete as an expense/payroll filter-flow cleanup pass replacing inline clear handlers with named reset actions and disabling reset buttons when no filters are active.
- Slice 132 is complete as a remaining operational clear-filter UX pass disabling no-op reset actions across inventory, expense, and rental workflow pages.
- Slice 133 is complete as a rental filter-control consistency pass replacing native status/type filter selects with shared `SearchableSelect` controls.
- Slice 134 is complete as a fixed-asset filter-control UX pass replacing native filter selects with shared `SearchableSelect` controls and guarded clear actions.

## Scope

This phase does not add a new ERP module, migration, table, or business workflow. It tightens existing access controls, cleans controller boundaries, and improves accountant-facing UI consistency.

## Slice 1 Changes

- Strengthened the `/reports` route group so report access now requires both `reports.view` and `view_financials`.
- Added controller-level `view_financials` authorization to:
  - `SalesOrderReportController`
  - `PurchaseOrderReportController`
  - `CustomerInvoiceReportController`
  - `SupplierBillReportController`
- Removed hardcoded bilingual visible labels from these Inertia pages:
  - `Reports/SalesOrdersReport.tsx`
  - `Reports/PurchaseOrdersReport.tsx`
  - `Reports/CustomerInvoicesReport.tsx`
  - `Reports/SupplierBillsReport.tsx`
- Added EN/AR dictionary keys for report titles, descriptions, filters, statuses, totals, table headers, placeholders, and empty states.
- Added `Phase15ProductHardeningTest` covering:
  - users with `reports.view` alone cannot open sensitive report pages;
  - users with `reports.view` and `view_financials` can open the report hub and selected report pages;
  - cleaned report pages contain no hardcoded Arabic UI text;
  - required report dictionary keys exist in both locales.

## Slice 2 Changes

- Added `App\Application\Reports\CsvReportResponse` as a shared CSV streaming response service.
- Updated `FixedAssetReportController` to delegate simple row-based CSV exports to the shared service.
- Updated `VatReportController` to delegate the VAT register CSV export to the shared service.
- Removed duplicated private `csvResponse` helpers from the touched controllers.
- Added regression coverage proving the shared CSV response streams headers/rows and the old duplicated helpers are absent from the touched controllers.

## Slice 3 Changes

- Added `App\Application\Settings\SuperAdminProtection`.
- Moved last-active-super-admin detection and role weakening checks out of:
  - `Settings\UserSettingsController`
  - `Settings\UserRoleAssignmentController`
- Preserved the existing owner-critical safety behavior:
  - the last active super admin cannot be deactivated;
  - the last active super admin cannot be deleted;
  - the last active super admin cannot have the super role removed or replaced by a non-super role.
- Added regression coverage proving the centralized service blocks those weakening paths and that the touched controllers no longer contain the duplicated raw super-admin query helpers.

## Slice 4 Changes

- Strengthened GL/subledger posting routes so financial posting requires `view_financials` in addition to the module action permission.
- Covered accounting journals, opening balances, treasury transfers, AR/AP opening balances, receipts, payments, inventory posting, sales/purchasing invoices and returns, credit/adjustment notes, expenses, prepaid/accrual posting, payroll, rentals, fixed asset capitalization/depreciation/disposal, and landed cost posting routes.
- Preserved existing route names, controllers, services, and business behavior.
- Added regression coverage that gathers middleware for all financial posting routes and fails if `view_financials` is missing.

## Slice 5 Changes

- Extracted page-data/read-side composition out of the largest controllers into focused application services:
  - `SalesReturnPageData`
  - `CustomerCreditNotePageData`
  - `CustomerInvoicePageData`
  - `SupplierBillPageData`
  - `PurchaseReturnPageData`
  - `SupplierAdjustmentNotePageData`
  - `FinancialStatementMappingPageData`
  - `FixedAssetPageData`
  - `RentalInvoicePageData`
  - `BankReconciliationPageData`
  - `FixedAssetDepreciationRunPageData`
- Extracted report CSV composition out of large report controllers into focused exporters:
  - `FixedAssetCsvReportExporter`
  - `VatCsvReportExporter`
  - `RentalOperationsCsvExporter`
- Extracted settings persistence/listing out of settings controllers:
  - `CompanySettingsService`
  - `NumberingSettingsService`
  - `UserSettingsService`
- Preserved all route names, Inertia component names, validation rules, permissions, lifecycle methods, redirects, and business behavior.
- Reduced every Laravel controller in `app/Http/Controllers` to under 150 lines at the time of verification.
- Added regression coverage preventing direct query/composition bloat from returning to the cleaned controllers.

## Slice 6 Changes

- Improved General Ledger accountant workflow:
  - extracted `GeneralLedgerPageData`;
  - removed read-side option queries from `GeneralLedgerController`;
  - added a clear reset-filter action;
  - passed `displayCurrency` from backend data/company settings/currency registry instead of hardcoding it in React;
  - replaced the hardcoded voucher fallback with a dictionary-backed label.
- Improved VAT Register and VAT Summary UX:
  - removed visible English fallback labels from the TSX pages;
  - added dictionary-backed empty-state descriptions;
  - localized summary labels such as net subtotal, total tax, output VAT, and input VAT.
- Added regression coverage that prevents the targeted accountant pages from reintroducing hardcoded visible fallback labels.
- Fixed the missing `BelongsTo` import on `Company::baseCurrencyRef()`.

## Slice 7 Changes

- Added explicit confirmation before deleting:
  - employee payroll component assignments;
  - reusable payroll components.
- Converted manual AR/AP settlement pages to dictionary-backed operational text:
  - page headings and descriptions;
  - customer/supplier filters;
  - source/target entry labels;
  - settlement amount validation alerts;
  - settlement/reversal buttons;
  - reversal modal title, warning copy, reason label, placeholder, and processing states.
- Localized new settlement and payroll confirmation keys in `en.json` and `ar.json`.
- Added regression coverage preventing the targeted payroll delete actions from bypassing confirm dialogs and preventing the settlement pages from reintroducing key hardcoded operational labels.

## Slice 8 Changes

- Added dictionary-backed confirmation guards for sensitive state changes in dense operational pages:
  - Expenses: submit, approve, post, and cancel.
  - Prepaid schedules: submit, approve, cancel, and recognition posting.
  - Accrual schedules: submit, approve, cancel, and accrual entry posting.
  - Rental contracts: submit, approve, activate, and cancel.
  - Rental handovers: confirm and cancel.
  - Rental returns: submit, complete inspection, and cancel.
- Repaired the corrupted Arabic dictionary block for rental contracts, replacing question-mark mojibake with usable Arabic operational copy.
- Added regression coverage requiring those confirmation hooks and dictionary keys to remain present in both locales.

## Slice 9 Changes

- Cleaned `Settings/Users.tsx` permission administration UI:
  - removed hardcoded EN/AR permission-category label map from the React page;
  - moved permission category names and permission action labels into `en.json` and `ar.json`;
  - replaced hardcoded language labels, form placeholders, revoke-role title, search-results text, and delete/self-delete fallbacks with dictionary-backed strings.
- Added localized permission labels for sensitive security capabilities such as `view_financials`, `view_payroll`, `override_control`, and `taxes.file`.
- Added regression coverage preventing hardcoded Arabic/security labels and English fallback confirmations from returning to the user/role administration page.

## Slice 10 Changes

- Cleaned foundation settings pages:
  - `Settings/Company.tsx`;
  - `Settings/Branches.tsx`;
  - `Settings/Numbering.tsx`.
- Moved company/branch placeholders, branch delete confirmation, numbering placeholders, reset-policy labels, include-year helper copy, padding text, and include-year yes/no labels into `en.json` and `ar.json`.
- Removed hardcoded page-level Arabic examples and English fallback labels from those settings pages.
- Removed the React-side hardcoded `EGP` company base-currency fallback; the page now relies on backend-provided currency options and an empty value when no currency exists.
- Added regression coverage preventing the targeted settings pages from reintroducing hardcoded visible text or generic delete confirmations.

## Slice 11 Changes

- Removed React-side silent operational fallback values from sales, purchasing, and inventory workflow pages:
  - `USD` currency fallbacks;
  - `EGP` valuation/currency fallbacks;
  - `PCS` unit-of-measure fallbacks;
  - `MAIN` warehouse-code fallback.
- Added currency options to stock count and stock adjustment pages from the Laravel currency registry instead of posting hidden `EGP`.
- Replaced hardcoded sales/purchasing reference placeholders with EN/AR dictionary keys.
- Replaced missing UOM/currency/warehouse display fallbacks with dictionary-backed neutral labels.
- Added regression coverage preventing these silent operational defaults from returning.

## Slice 12 Changes

- Removed React-side silent `EGP` currency fallbacks from payroll, expense, rental, and fixed asset pages.
- Updated payroll runs/employees, expenses/prepaids/accruals, rental contracts/items/invoices/returns, fixed asset creation, and fixed asset disposal display to use registry-provided currency values or neutral dictionary-backed missing-currency labels.
- Preserved real backend currency validation and did not introduce guessed operational defaults.
- Added regression coverage preventing silent `EGP` fallbacks from returning to the targeted payroll, expense, rental, and fixed asset pages.

## Slice 13 Changes

- Cleaned Tax Codes and Tax Rates master-data pages:
  - `Taxes/Codes/Index.tsx`;
  - `Taxes/Codes/Create.tsx`;
  - `Taxes/Codes/Edit.tsx`;
  - `Taxes/Rates/Index.tsx`.
- Removed visible English fallback labels and inline Arabic search text from those pages.
- Moved tax search labels, create subtitle, code placeholder, all-tax-code filter label, rate input helper label, and basis-points suffix into `en.json` and `ar.json`.
- Preserved existing tax routes, forms, validation, posting behavior, tax period filing behavior, and accounting logic.
- Added regression coverage preventing hardcoded Tax Codes/Rates fallback text from returning to the targeted tax master pages.

## Slice 14 Changes

- Cleaned Tax Periods and Tax Period filing pages:
  - `Taxes/Periods/Index.tsx`;
  - `Taxes/Periods/Show.tsx`.
- Fixed the Tax Period pages to read translations from `dict.app.taxes.periods` instead of relying on fallback text.
- Moved period creation empty-state copy, modal labels, filing status labels, locking-guard copy, return snapshot headers, VAT breakdown table headers, filing notes, and submit/cancel button states into `en.json` and `ar.json`.
- Preserved tax period creation, draft return generation, filing/locking behavior, permissions, routes, and accounting/tax logic.
- Added regression coverage preventing hardcoded Tax Period filing fallback text from returning.

## Slice 15 Changes

- Replaced generic master-data delete confirmations with entity-specific confirmation text on:
  - `Accounting/AccountCategories.tsx`;
  - `Accounting/AccountTypes.tsx`;
  - `Expenses/Categories.tsx`;
  - `Catalog/ProductCategories.tsx`;
  - `Catalog/Products.tsx`;
  - `Catalog/UnitsOfMeasure.tsx`.
- Injected the selected record name/code into each delete confirmation so accountants can verify the exact entity being deleted.
- Moved the new confirmation text into `en.json` and `ar.json`.
- Preserved existing delete routes, permissions, in-use protections, and server-side validation.
- Added regression coverage preventing the targeted master-data pages from returning to generic delete prompts.

## Slice 16 Changes

- Cleaned accounting master-data form/detail text on:
  - `Accounting/AccountCategories.tsx`;
  - `Accounting/AccountTypes.tsx`.
- Moved remaining direct placeholders, button fallbacks, status badges, modal descriptions, table labels, and account-detail fallback text into `en.json` and `ar.json`.
- Removed inline Arabic/English modal description branches from those pages.
- Preserved account category/type CRUD behavior, relationships, deletion guards, permissions, routes, and validation.
- Added regression coverage preventing those accounting master-data pages from reintroducing visible inline fallback text.

## Slice 17 Changes

- Updated `Accounting\ExchangeRateController` to pass the configured company profile base currency and related currency row to the Inertia page.
- Updated `Accounting/ExchangeRates.tsx` to display the actual configured base currency instead of hardcoded `EGP`.
- Removed the silent `USD` default from the FX-rate form; the page now selects from non-base currencies and warns when no non-base currency exists.
- Moved FX-rate form labels, no-base/no-foreign-currency guidance, conversion-line text, placeholders, and empty-state text into `en.json` and `ar.json`.
- Preserved existing FX-rate routes, validation, scaled integer rate storage, currency relationships, and service behavior.
- Added regression coverage proving `/accounting/fx-rates` receives `baseCurrency` / `baseCurrencyRef` and preventing hardcoded `EGP` / `USD` display fallbacks from returning.

## Slice 18 Changes

- Cleaned `Accounting/Currencies.tsx` currency master-data page visible text.
- Moved currency form placeholders, ISO badge text, linked-account/FX-rate tooltips, delete modal title/body, disabled-delete tooltip, and ledger action title into `en.json` and `ar.json`.
- Removed direct visible fallback labels from the currency create/edit/delete/detail workflow while preserving the existing CRUD behavior, currency relationships, deletion guards, permissions, routes, and validation.
- Fixed the linked-account detail modal to localize account nature labels through the existing helper instead of showing a raw fallback.
- Added regression coverage preventing currency master-data visible fallback text from returning.

## Slice 19 Changes

- Updated `Accounting/ChartOfAccounts.tsx` so new ledger accounts start with no hidden currency selection and must use an explicit currency from the Laravel currency registry.
- Updated `Accounting\ChartOfAccountsController::storeAccount` so account currency is required and no longer falls back to `EGP` when the request omits it.
- Moved Chart of Accounts group/account placeholders, no-currency guidance, select-currency prompt, and missing-currency display label into `en.json` and `ar.json`.
- Removed remaining visible fallback labels from the Chart of Accounts page while preserving account group/account creation behavior, account-type normal-balance defaulting, relationship validation, permissions, routes, and existing database schema.
- Updated legacy account type tests to send explicit test currency where the test objective is account-type behavior rather than currency validation.
- Added regression coverage preventing the Chart of Accounts page/controller from reintroducing silent `EGP` account currency defaults.

## Slice 20 Changes

- Updated `GeneralLedgerService::getTrialBalance()` to include account `currency_code` on each trial-balance row.
- Added backend-provided `displayCurrency` for `/accounting/trial-balance`, derived from trial-balance rows, then the company profile base currency, then registered account currencies/config fallback.
- Removed the React-side `EGP` display fallback from `Accounting/TrialBalance.tsx`.
- Removed remaining visible fallback labels from the Trial Balance page so titles, filters, metric labels, empty state, table headers, and totals use `en.json` / `ar.json`.
- Preserved existing trial-balance filters, ledger-derived totals, balancing invariant, permissions, routes, and report calculation behavior.
- Added regression coverage proving the page receives backend `displayCurrency` and preventing Trial Balance visible/hidden fallback text from returning.

## Slice 21 Changes

- Cleaned `Accounting/GeneralJournal.tsx` so status filters, voucher modal labels, empty state, table headers, draft/manual journal placeholders, and action labels use `en.json` / `ar.json`.
- Removed the inline EN/AR status-label map from the React page and replaced it with dictionary-backed status labels.
- Removed the fallback chain into `dict.app.pages.accountingGeneralJournal`; the page now relies on the canonical accounting dictionary keys.
- Added `manualJournal`, `viewFullVoucher`, and `description` accounting dictionary keys in both locales.
- Preserved existing journal listing, detail-link behavior, modal behavior, filters, routes, permissions, and backend journal data.
- Added regression coverage preventing General Journal hardcoded Arabic text and visible fallback labels from returning.

## Slice 22 Changes

- Cleaned `Accounting/JournalDetail.tsx` so voucher title, action buttons, number modal, reverse modal, detail cards, audit trail, and journal-line table headers use `en.json` / `ar.json`.
- Removed page-specific fallback labels from the voucher detail page, including inline status fallbacks and `dict.app.pages.accountingJournalDetail` fallback usage.
- Added dictionary-backed reverse-entry heading/description and system-actor labels in both locales.
- Preserved existing submit, approve, post, reverse, number-modal, attachment-panel, branch display, and journal-line behavior.
- Added regression coverage preventing Journal Detail hardcoded Arabic text and visible fallback labels from returning.

## Slice 23 Changes

- Cleaned `Accounting/JournalForm.tsx` so the create-voucher page title, back link, period/currency warnings, balance summary, field labels, placeholders, line controls, and save/cancel actions use `en.json` / `ar.json`.
- Removed the hidden React-side `EGP` fallback from journal voucher creation; the form now starts from the currency registry or an empty value.
- Added a dictionary-backed no-currency warning and disabled draft saving when no currency is selected.
- Replaced generated page-dictionary fallbacks with canonical accounting dictionary keys and added journal reference/memo placeholder keys.
- Preserved existing journal draft creation route, open-period default, line entry behavior, branch inheritance, balance validation, and server-side posting flow.
- Added regression coverage preventing Journal Form hardcoded Arabic text, hidden `EGP` currency fallback, and visible fallback labels from returning.

## Slice 24 Changes

- Cleaned `Accounting/OpeningBalances.tsx` so the page title, description, post action, fiscal-year selector, accounting totals, balance status badge, empty state, table headers, and draft-save action use the canonical accounting dictionary.
- Removed legacy fallback usage from `dict.app.pages.accountingOpeningBalances` in the React page.
- Preserved existing opening-balance draft saving, fiscal-year switching, balanced-only posting guard, posted-state lockout, account row editing behavior, and PostingEngine-backed post route.
- Added regression coverage preventing Opening Balances hardcoded Arabic text, legacy page fallback dictionary usage, and visible fallback labels from returning.

## Slice 25 Changes

- Updated `AppLayout.tsx` navigation permission handling to support multiple valid permissions for a route-aligned navigation item.
- Aligned the Fiscal Periods sidebar item with the actual route access model: `accounting.view`, `accounting.periods`, or `settings.configure`.
- Limited the "Create Fiscal Year" action in `Accounting/Periods.tsx` to users with `settings.configure`, matching the backend store route.
- Added a dictionary-backed empty state for the no-fiscal-years condition so period-control users do not land on a blank workspace.
- Preserved fiscal-year creation, close-readiness fetch, period close/reopen modals, blocker display, and existing backend authorization.
- Added regression coverage for route/navigation permission parity, dictionary-backed Fiscal Periods empty-state copy, and no hardcoded Arabic text.

## Slice 26 Changes

- Removed hardcoded fallback labels from the Accounting and Tax navigation items in `AppLayout.tsx`.
- Accounting sidebar labels now rely on canonical `app.accounting` dictionary keys for Chart of Accounts, Account Types, General Journal, General Ledger, Trial Balance, Fiscal Periods, Opening Balances, FX Rates, and Currencies.
- Tax sidebar labels now rely on `app.taxes.title` and `app.taxes.periods.title`.
- Preserved existing navigation routes, grouping, collapsed sidebar behavior, active item highlighting, and permission filtering.
- Expanded regression coverage so accounting/tax navigation fallback labels cannot return.

## Slice 27 Changes

- Replaced the generic delete confirmation in `Accounting/FinancialStatementMappings.tsx` with a statement-line-specific confirmation message.
- The confirmation now includes the target financial statement line code and localized name before deletion.
- Added `confirmDeleteStatementLine` dictionary keys in EN/AR.
- Preserved existing delete blockers for system lines and lines with mapped accounts, as well as existing edit/create/assign/unassign behavior.
- Added regression coverage preventing generic `actionsDict.confirmDelete` usage from returning to the statement mapping delete flow.

## Slice 28 Changes

- Replaced the generic branch override delete confirmation in `Accounting/AccountMappings.tsx` with an account-mapping-specific confirmation message.
- The confirmation now includes the mapping key label, operational branch label, and target GL account label before deletion.
- Updated `accountMappingDeleteConfirm` dictionary copy in EN/AR with `{key}`, `{branch}`, and `{account}` placeholders.
- Preserved global mapping protection: only branch override rows can be deleted from the page.
- Added regression coverage preventing generic account mapping delete confirmation usage from returning.

## Slice 29 Changes

- Removed visible fallback labels from `Accounting/Index.tsx`, the main Accounting landing page.
- The page header, create-voucher action, KPI labels, quick-action cards, recent-journal heading, empty-state copy, draft badge, manual-journal fallback, and detail link now use canonical dictionary keys.
- Added localized status-label mapping for recent journal badges instead of displaying raw database status values.
- Reused existing EN/AR dictionary keys and added no new schema, routes, services, or business behavior.
- Added regression coverage preventing hardcoded Accounting landing-page labels from returning.

## Slice 30 Changes

- Removed tax-report visible fallback labels from `Reports/Index.tsx`.
- The Reports Hub now uses `dict.app.taxes` directly for Tax/VAT group title, VAT Register, VAT Summary, and VAT-to-GL Reconciliation names/descriptions.
- Preserved report route links, permission visibility, and all report services/controllers.
- Added regression coverage preventing hardcoded Tax/VAT report labels and `(dict.app.taxes as any)` fallback usage from returning.

## Slice 31 Changes

- Removed visible fallback labels from `Reports/VatGlReconciliation.tsx`.
- Replaced `getDictionary(locale) as any` / optional tax dictionary reads with canonical `dict.app.taxes.vatGlReconciliation` and `dict.app.taxes.warnings`.
- Removed the hidden React-side `USD` currency fallback; the page now uses request filter, report currency, first provided registry currency, or an empty value.
- Moved VAT-to-GL comparison table headers and category labels into EN/AR dictionary keys.
- Preserved VAT-to-GL report routes, filters, export URL, service output, and reconciliation math.
- Added regression coverage preventing visible tax reconciliation fallbacks and hidden currency defaults from returning.

## Slice 32 Changes

- Removed hidden React-side `EGP` currency fallbacks from AR Aging, AP Aging, AR-to-GL Reconciliation, and AP-to-GL Reconciliation currency selectors.
- Clearing a report currency now preserves an explicit empty selection instead of silently switching the report to `EGP`.
- Preserved backend report currency, export URLs, date/customer/supplier filters, and reconciliation math.
- Added regression coverage preventing hidden `EGP` report-currency defaults from returning to these pages.

## Slice 33 Changes

- Replaced unfiltered operational report summary totals that displayed with a hidden `EGP` fallback in Sales Orders, Purchase Orders, Customer Invoices, Supplier Bills, and Stock Movements reports.
- When no currency filter is selected, those summary amount cards now show a localized `Mixed currencies` label instead of presenting a cross-currency total as EGP.
- Removed the default `EGP` argument from the fixed-asset report minor-unit formatter so callers must provide a currency or receive a neutral amount display.
- Preserved row-level currency display, filters, exports, report services, and all backend query behavior.
- Added regression coverage preventing unfiltered operational report totals from being labeled as EGP.

## Slice 34 Changes

- Removed `getDictionary(locale) as any` from Tax Period index/detail pages and VAT Register/Summary report pages.
- Switched VAT Register and VAT Summary to canonical `dict.app.taxes.*` dictionary paths.
- Replaced raw unavailable filing-reference dash fallback with localized `notAvailable` tax-period dictionary text.
- Preserved tax period filing workflows, VAT report filters/exports, summary math, and route behavior.
- Expanded regression coverage preventing loose dictionary typing, legacy `dict.taxes.*` access, hardcoded app-title suffixes, and raw filing-reference fallbacks from returning.

## Slice 35 Changes

- Removed `(dict.app as any).taxes` loose dictionary access from Tax Codes index/create/edit and Tax Rates index pages.
- Replaced raw `-` / dash unavailable fallbacks in tax code/rate display helpers with localized `taxes.notAvailable` text.
- Replaced `e.target.value as any` select casts in tax code forms with explicit calculation/recoverability union casts.
- Preserved tax code/rate routes, forms, delete confirmations, validation behavior, and tax master-data page structure.
- Expanded regression coverage preventing loose tax dictionary access, raw unavailable fallbacks, and select `as any` casts from returning.

## Slice 36 Changes

- Removed loose `(dict.app as any).audit` and legacy `dict.app.pages.auditLog` fallback chains from the Audit Log Inertia page.
- Switched Audit Log labels, actions, placeholders, payload modal headings, pagination labels, actor fallback, and unavailable markers to canonical `dict.app.audit` and `dict.app.actions` keys.
- Added EN/AR audit dictionary keys for request-id placeholder, system actor label, user fallback prefix, and unavailable display text.
- Preserved `/audit-log` routing, filtering, pagination, read-only payload inspection, and Spatie Activitylog-backed query behavior.
- Added regression coverage preventing Audit Log loose dictionary access, legacy fallback chains, hardcoded request placeholder, hardcoded `User #...` actor labels, and raw dash fallbacks from returning.

## Slice 37 Changes

- Removed loose `(dict.app as any).accounting` and `(dict.app as any).taxes` usage from the central `AppLayout` navigation.
- Replaced visible navigation fallbacks such as `Accounting Core`, `Administration`, and duplicated layout-key fallback chains with canonical typed dictionary keys.
- Replaced header user-menu hardcoded fallback identity text with localized EN/AR `unknownUser` and `unknownEmail` dictionary keys.
- Preserved navigation route structure, permission gating, sidebar collapse behavior, notification dropdown behavior, and all active route keys.
- Added regression coverage preventing loose layout dictionary access, navigation fallback chains, optional tax-period label access, and hardcoded user identity fallbacks from returning.

## Slice 38 Changes

- Removed loose `(dict.app as any)` dictionary access from Currencies, FX Rates, Account Categories, and Account Types pages.
- Replaced Account Category and Account Type select `as any` casts with explicit `toNormalBalance` and `toStatementType` parsing helpers.
- Corrected typed dictionary lookups that were previously hidden behind loose typing, including active/inactive status labels, action header labels, account type group labels, delete-disabled hints, and control-account headings.
- Preserved accounting master-data routes, create/edit/delete behavior, validation payloads, relationship detail modals, and table layout.
- Added regression coverage preventing loose accounting master-data dictionary access and select `as any` casts from returning.

## Slice 39 Changes

- Removed loose `(dict.app as any)` dictionary access from Chart of Accounts, Fiscal Periods, Opening Balances, and Trial Balance pages.
- Replaced Chart of Accounts account-nature select casting with an explicit `toAccountNature` parser.
- Replaced Fiscal Periods loose `Record<string, unknown>` dictionary wrappers with typed accounting/action dictionary helpers plus a safe dictionary fallback for dynamic blocker entity/status keys.
- Preserved fiscal-year creation, period close/reopen readiness checks, opening-balance draft/post behavior, trial-balance filtering, and Chart of Accounts create flows.
- Added regression coverage preventing loose dictionary wrappers and account-nature select casts from returning in the cleaned pages.

## Slice 40 Changes

- Removed loose `(dict.app as any)` dictionary access from General Ledger, General Journal, Journal Form, and Journal Detail.
- Replaced Journal Form line editing `any` payload handling with a typed `JournalLineDraft` structure.
- Replaced raw journal status fallbacks with canonical `accDict.statusUnknown`.
- Added accounting `notAvailable` dictionary keys and used them for empty reference/actor/memo fields instead of silent hardcoded dash fallbacks where semantically appropriate.
- Preserved voucher creation, journal listing/detail actions, posting/reversal actions, ledger filtering, branch display, and existing route behavior.
- Added regression coverage preventing loose journal/ledger dictionary access, raw status fallback, and `value: any` line updates from returning.

## Slice 41 Changes

- Removed loose `(dict.app as any)` dictionary access from Fixed Asset register, create, edit, show, category, location, disposal, and depreciation-run pages.
- Replaced disposal-type dynamic dictionary indexing with explicit typed sale/scrap/retirement label mapping.
- Added fixed-asset disposal `notAvailable` dictionary keys and English accounting `scheduleStatusSkipped`.
- Replaced semantically visible missing-value dashes in fixed-asset category, serial, capitalization date, movement reason/actor, depreciation-period, and disposal metadata fields with dictionary-backed unavailable labels.
- Preserved fixed-asset creation/editing, category/location CRUD, capitalization/reversal, movement recording, disposal posting/reversal, depreciation preview/post/reversal, and financial-visibility gating.
- Added regression coverage preventing loose fixed-asset dictionary access and disposal dynamic dictionary indexing from returning.

## Slice 42 Changes

- Replaced select `as any` casts in Customers, Suppliers, Catalog Products, Customer Receipts, Supplier Payments, and Financial Statement Mapping with explicit value parsers.
- Replaced customer/supplier cash-bank destination selection casts with a typed cash/bank parser.
- Removed hidden `EGP` currency defaults from Customer Receipts and Supplier Payments React form state and cleared currency selection handling.
- Replaced hardcoded Arabic cash/bank destination labels in receipt/payment tables with dictionary-backed labels.
- Replaced missing customer/supplier/product UOM/category display fallbacks with canonical accounting `notAvailable` labels.
- Preserved customer/supplier master-data editing, product CRUD, customer receipt/supplier payment draft/post flows, and financial statement mapping line management.
- Added regression coverage preventing select `as any` casts, hidden receipt/payment `EGP` defaults, and hardcoded Arabic destination labels from returning.

## Slice 43 Changes

- Replaced `value: any` line-item update helpers in Customer Credit Notes, Sales Returns, Supplier Adjustment Notes, Purchase Returns, and Supplier Bills with generic typed update helpers.
- Added a typed sales-return disposition parser so disposition select values cannot pass raw strings into line state.
- Added typed optional manual-tax override fields to customer credit note and supplier adjustment note rows, removing `(note as any)` casts.
- Preserved credit-note, sales-return, supplier-adjustment, purchase-return, and supplier-bill create/edit line behavior.
- Added regression coverage preventing document line editors from returning to `value: any`, note casts, or select `as any` usage.

## Slice 44 Changes

- Removed the remaining visible `as any` casts from Landed Costs form error rendering and Customer Invoices edit-line source references.
- Switched Landed Costs to use typed `errors.goods_receipt_id` and `errors.supplier_id` access.
- Switched Customer Invoices to read `sales_order_line_id` and `delivery_note_line_id` directly from the typed invoice line row.
- Added regression coverage proving the cleaned Landed Costs and Customer Invoices pages do not use loose `as any` or `value: any` patterns.
- Verified the broader `laravel/resources/js/Pages` scan for `(dict.app as any)`, loose action dictionaries, `as Record<string, unknown>`, `value: any`, and `as any` now returns no matches.

## Slice 45 Changes

- Replaced silent dash fallbacks in sales, purchasing, and catalog operational tables/forms with canonical accounting `notAvailable` labels.
- Cleaned Sales Orders, Delivery Notes, Customer Invoices, Customer Credit Notes, Sales Returns, Purchase Orders, Goods Receipts, Supplier Bills, Supplier Adjustment Notes, Purchase Returns, and Product Categories.
- Removed UOM and editable description dash fallback leakage in sales-return and purchase-return line editors.
- Preserved operational CRUD, document lifecycle behavior, posting behavior, and existing routes.
- Added regression coverage preventing the cleaned operational pages from reintroducing silent `'-'` missing-label fallbacks.

## Slice 46 Changes

- Replaced silent dash fallbacks in Sales Invoice Revision list/detail pages with canonical accounting `notAvailable` labels.
- Cleaned missing original-invoice, customer, product, description, UOM, related credit note, and related sales return display values.
- Preserved invoice revision read-only workflow, print layout, links, totals, and snapshot rendering.
- Added regression coverage preventing Sales Invoice Revision pages from reintroducing silent dash missing-label fallbacks.

## Slice 47 Changes

- Removed hidden React-side `EGP` currency defaults from Cash Accounts, Bank Accounts, Incoming Cheques, Outgoing Cheques, Customer Opening Balances, and Supplier Opening Balances.
- Added an explicit registry-backed currency selector to Incoming Cheque creation.
- Replaced silent dash/em dash fallbacks in Customers, Suppliers, Cash Accounts, Bank Accounts, Cheques, AR/AP Opening Balances, AR/AP Allocations, and Bank Reconciliations with canonical accounting `notAvailable` labels.
- Replaced hidden `EGP` formatting in AR/AP allocation history and Bank Reconciliation amounts with actual allocation/account currency or a neutral unavailable label when the currency is genuinely missing.
- Removed hardcoded Arabic remaining-amount text from AR/AP allocation selectors.
- Preserved CRUD, lifecycle, posting, allocation, reversal, and bank reconciliation behavior.
- Added regression coverage preventing hidden `EGP`, silent unavailable fallbacks, and hardcoded Arabic selector text from returning to the cleaned AR/AP cash/bank pages.

## Slice 48 Changes

- Removed the remaining hidden React-side `EGP` default from Treasury Transfers.
- Replaced silent missing-label fallbacks in Treasury Transfers, Stock Transfers, Chart of Accounts account-group display, Company Settings creation date, and Numbering detail prefix display with canonical accounting `notAvailable` labels.
- Preserved treasury transfer posting/cancellation, stock transfer lifecycle, Chart of Accounts display, company attachment workflow, and numbering preview/detail behavior.
- Added regression coverage preventing hidden currency defaults and silent unavailable-label fallbacks from returning to the cleaned treasury, inventory transfer, and settings pages.

## Slice 49 Changes

- Fixed `Accounting/JournalDetail.tsx` so journal line debit/credit amounts and footer totals use `formatMoney(..., journal.currency)` instead of raw minor-unit integers.
- Replaced the remaining fixed-asset disposal journal-preview debit/credit and memo dash fallbacks with canonical unavailable labels.
- Preserved journal workflow actions, attachment behavior, reversal behavior, and fixed-asset disposal accounting behavior.
- Added regression coverage preventing raw minor-unit journal display and silent dash fallbacks from returning to the cleaned journal/detail preview pages.

## Slice 50 Changes

- Added the accounting dictionary key `zeroAmount` for intentional zero-side debit/credit table display.
- Replaced hardcoded report-table dash markers in Bank Book, Cash Book, Customer Statement, and Supplier Statement with `accDict.zeroAmount`.
- Replaced missing-link markers in Bank Reconciliation Detail, Customer Invoices Report, Supplier Bills Report, and Stock Movements Report with canonical `accDict.notAvailable`.
- Preserved report filters, exports, totals, journal links, statement movements, and mixed-currency behavior.
- Added regression coverage proving report tables use dictionary-backed zero/unavailable markers instead of hardcoded dash literals.

## Slice 51 Changes

- Added the accounting dictionary key `restrictedValue` for permission-restricted financial cells.
- Formatted fixed-asset list, detail, disposal, and depreciation-run monetary values instead of rendering raw minor-unit integers.
- Replaced fixed-asset hardcoded financial masks (`***`) with dictionary-backed restricted labels.
- Guarded fixed-asset disposal list money display from falling back to helper-default currency when asset currency is unavailable.
- Preserved fixed-asset register, category maintenance, capitalization, depreciation, disposal, and movement behavior.
- Added regression coverage preventing raw minor display, hardcoded masks, optional-currency helper defaults, and hardcoded zero gain/loss display from returning to fixed-asset pages.

## Slice 52 Changes

- Removed the hardcoded `currency="EGP"` display from Payroll Components.
- Formatted payroll component default amounts without inventing a currency when the component itself does not carry a currency field.
- Added broad regression coverage scanning all visible Inertia Pages for hardcoded `EGP` and `USD` currency literals, including double-quoted JSX props.
- Preserved payroll component CRUD, account mapping selectors, assignment counts, and permission-gated actions.

## Slice 53 Changes

- Added backend-provided VAT/Tax display currency to VAT register, VAT summary, VAT-to-GL reconciliation, and tax period filing page data.
- Replaced VAT/Tax report-page `formatMoney(...)` calls that could rely on helper-default currency with explicit-currency helpers.
- Added canonical unavailable display when VAT/Tax report currency cannot be determined instead of silently falling back to a guessed currency.
- Preserved VAT report filters, tax period filing/lock behavior, register rows, reconciliation totals, and posted tax calculations.
- Added regression coverage preventing VAT/Tax pages from calling `formatMoney` without explicit currency and preventing hardcoded `EGP` fallback reintroduction.

## Slice 54 Changes

- Added `App\Application\Reports\ReportCurrencyResolver` to centralize report display/filter currency resolution.
- Replaced hidden hardcoded report defaults in AR Aging, AP Aging, AR/AP-to-GL reconciliation, cheque register, customer statement, supplier statement, VAT-to-GL reconciliation, bank reconciliation report data, branch operational reports, branch profitability, and rental operations reporting.
- Report currency resolution now uses an explicit request currency when present, then configured company base currency, then registered/default currency fallback through the currency registry.
- Updated older report tests to grant `view_financials` alongside `reports.view`, matching the hardened report-route authorization contract.
- Added regression coverage preventing report controllers/services from reintroducing hidden `EGP`/`USD` currency defaults.

## Slice 55 Changes

- Added `App\Application\Support\CurrencyInput` for explicit operational currency normalization and validation.
- Added `App\Application\Support\BaseCurrencyResolver` as the shared base-currency resolver used by reports and FX-rate logic.
- Removed hidden operational `EGP`/`USD` defaults from financial application services outside reporting.
- User-entered financial operations now require explicit registry-backed currency input; source-driven operations derive currency from their source document/account/asset and fail clearly when it is missing.
- `ExchangeRateService` no longer treats `EGP` as a hardcoded base currency and no longer silently returns `1.000000` for missing foreign exchange rates.
- Opening balance posting and depreciation runs no longer hardcode `EGP`; each posting run must resolve to exactly one currency.
- Strengthened controller validation so key operational currency inputs use `exists:currency,code`.
- Tightened branch financial report routes to expose explicit `can:reports.view` and `can:view_financials` middleware.
- Updated stale accounting, branch warehouse, and report fixtures to grant/pass explicit financial currencies and permissions instead of depending on implicit defaults.
- Updated `accounting:concurrency-stress` to use the configured base currency instead of a hidden fixed-currency fixture.

## Slice 56 Changes

- Added `App\Console\Commands\Concerns\ResolvesStressCurrency` so stress and integrity commands resolve the configured base currency through the application instead of embedding fixed `EGP`/`USD` fixtures.
- Removed hardcoded currency literals from console commands, operational stress tools, integrity checks, and seeders.
- Updated allocation, settlement, cheque, bank reconciliation, stock transfer, inventory costing, fixed asset depreciation, and fixed asset disposal stress commands to use the configured base currency and ensure a matching currency row exists.
- Updated `accounting:phase3-integrity-check` to use `ReportCurrencyResolver` instead of a fixed report currency.
- Updated `AccountingCoreSeeder` to resolve account currency through `BaseCurrencyResolver`.
- Updated `AccountingDemoSeeder` to derive the demo journal currency from the configured demo accounts via `CurrencyInput::related`, and to fail clearly if the cash and sales accounts use different currencies.
- Hardened `accounting:inventory-concurrency-stress` to generate run-specific GL account fixtures so old local stress rows with a different currency cannot contaminate later runs.
- Added regression coverage proving `app/Console/Commands` and `database/seeders` contain no hardcoded `EGP`/`USD` literals and that the cleaned commands/seeders use the shared currency resolvers.

## Slice 57 Changes

- Aligned visible financial posting actions with the same `view_financials` requirement already enforced by backend posting routes.
- Updated customer/supplier opening balances, customer receipts, supplier payments, treasury transfers, stock counts, stock adjustments, customer invoices, supplier bills, expenses, prepaid recognitions, and accrual entries so post actions require `view_financials` in the UI.
- Corrected returns and adjustment-note action gates to use their exact route permissions:
  - sales returns use `sales.returns`;
  - customer credit notes use `sales.credit_notes`;
  - purchase returns use `purchasing.returns`;
  - supplier adjustment notes use `purchasing.adjustment_notes`.
- Replaced hardcoded `Settle` action labels in credit-note and supplier-adjustment pages with dictionary-backed EN/AR labels.
- Added regression coverage proving financial post buttons match backend permission requirements, stale generic return/adjustment permissions do not return, and hardcoded settlement action labels stay out of the visible TSX pages.

## Slice 58 Changes

- Formatted inventory stock adjustment `total_value_delta_minor` with the adjustment currency instead of exposing raw minor-unit integers in the table.
- Replaced empty warehouse-cell fallbacks in stock counts and stock adjustments with the canonical accounting unavailable label.
- Extended Phase 15 regression coverage so stock count/adjustment pages participate in the remaining operational-page explicit-currency and missing-label checks.
- Added a targeted guard proving stock adjustment value deltas stay formatted with `formatMoney(..., adjustment.currency)` and do not regress to raw numeric display.

## Slice 59 Changes

- Added a route-surface security regression test for all state-changing `POST`, `PUT`, `PATCH`, and `DELETE` routes.
- The test requires every state-changing route to be auth-gated.
- The test requires explicit `can:` or `permission.*` authorization middleware unless the route is deliberately allowlisted.
- The allowlist is intentionally small and documented in test code: login, locale switch, logout, user-scoped notifications, service-authorized attachments, and the auth-only foundation redirect.
- Verified the new guard across the full Laravel route collection with 550 route assertions.

## Slice 60 Changes

- Replaced generic AR/AP posting confirmation messages on customer receipts, supplier payments, customer opening balances, and supplier opening balances.
- New confirmation copy names the posting impact before the accountant confirms: AR/AP plus cash/bank ledgers or the general ledger.
- Confirmation copy now states that posted receipts, payments, and opening balances cannot be edited after posting.
- Added EN/AR dictionary keys for the workflow-specific confirmation messages.
- Added regression coverage preventing the touched AR/AP pages from returning to legacy generic posting confirmation keys.

## Slice 61 Changes

- Added a shared `PaginationLink` TypeScript type for typed Inertia pagination metadata.
- Replaced loose `any` source-document arrays in `Sales/CustomerInvoices.tsx` with explicit confirmed sales order and confirmed delivery note shapes.
- Replaced loose `any` source-document arrays in `Purchasing/SupplierBills.tsx` with explicit confirmed purchase order and confirmed goods receipt shapes.
- Added camelCase/snake_case relationship guards for source delivery notes and goods receipts without changing backend payload behavior.
- Added regression coverage preventing customer invoice and supplier bill source-document handling from returning to `any`.

## Slice 62 Changes

- Reused the shared `PaginationLink` type across AR/AP cash/bank operational pages.
- Replaced `links: any[]` in customer/supplier opening balances, customer receipts, supplier payments, cash accounts, bank accounts, incoming cheques, outgoing cheques, receivable allocations, payable allocations, and bank reconciliations.
- Extended regression coverage so these pages must keep typed pagination link metadata.
- Preserved all UI rendering, filters, posting actions, allocation behavior, cheque lifecycle behavior, and bank reconciliation behavior.

## Slice 63 Changes

- Localized backend success flash messages for sales and purchasing operational document controllers.
- Wrapped success messages in Laravel translation calls for sales orders, purchase orders, delivery notes, goods receipts, customer invoices, supplier bills, sales returns, purchase returns, customer credit notes, and supplier adjustment notes.
- Added Arabic backend translations for create, update, submit, confirm, approve, post, cancel, and invoice-revision generated success states.
- Preserved all controller service calls, validation rules, routes, permissions, posting behavior, invoice revision generation, and accounting/subledger effects.
- Added regression coverage preventing raw English `with('success', '...')` flash messages from returning to the touched sales/purchasing controllers and requiring Arabic translations for every touched backend flash string.

## Slice 64 Changes

- Removed the remaining raw backend success flash messages from all Laravel controllers.
- Wrapped success flash messages in Laravel translation calls across bank reconciliation, cash/bank accounts, customers, suppliers, customer/supplier opening balances, receipts/payments, catalog master data, cheques, landed costs, AR/AP allocations and settlements, stock counts, stock adjustments, stock transfers, warehouses, treasury transfers, and tax setup/filing actions.
- Added Arabic backend translations for the newly localized operational success messages, including tax return number placeholders.
- Preserved all controller service calls, validation, routes, RBAC middleware, posting flows, stock/treasury/cheque lifecycle behavior, and tax filing behavior.
- Added regression coverage scanning every controller file and failing if any raw `with('success', '...')` or `with('success', "...")` flash message returns.

## Slice 65 Changes

- Added Arabic backend translations for protective accounting and settings guard errors.
- Covered account group/type mismatch errors, protected system account category/type delete errors, in-use account category/type delete errors, last-active-super-admin role removal blocking, and financial-period close blockers.
- Preserved the existing controller and service behavior; this slice only made the already enforced guard failures understandable in Arabic.
- Added regression coverage requiring Arabic translations for the touched backend guard/error messages.

## Slice 66 Changes

- Localized financial service error messages for period posting guards, AR/AP receipts/payments, customer/supplier opening balances, AR/AP allocations, AR/AP settlements, prepaid schedules, accrual schedules, and payroll period resolution.
- Replaced raw interpolated backend messages with Laravel translation placeholders for IDs, statuses, dates, currencies, fiscal years, customers, suppliers, allocations, and settlements.
- Preserved all posting, reversal, idempotency, locking, validation keys, accounting entries, subledger behavior, and period-close protection.
- Added regression coverage requiring Arabic translations for the touched financial service messages and preventing the old raw status/period error fragments from returning.

## Slice 67 Changes

- Localized AR/AP receipt and payment validation errors around cash/bank selection, linked GL account readiness, account/currency mismatches, required fields, positive integer amounts, and temporary 1:1 FX restrictions.
- Localized customer/supplier opening balance helper validation errors for duplicate active balances, required fields, positive amounts, mapped-account currency mismatches, and temporary 1:1 FX restrictions.
- Localized CashAccount and BankAccount service errors for duplicate codes, missing/inactive linked GL accounts, invalid currencies, and optional operational branch references.
- Preserved cash/bank setup behavior, receipt/payment posting, opening-balance posting, branch-as-operational-reference behavior, optimistic locking, validation keys, and accounting effects.
- Extended financial-service regression coverage to scan the touched services for raw bracketed entity/currency/status errors and require Arabic translations for all newly covered messages.

## Slice 68 Changes

- Localized TreasuryTransferService validation errors for draft-only update/post/cancel guards, required fields, positive transfer amounts, positive FX rates, invalid fiscal periods, closed periods, endpoint type mismatch, missing endpoint accounts, linked GL readiness, duplicate source/destination accounts, and transfer currency mismatches.
- Preserved treasury transfer lifecycle behavior, branch-as-operational-reference behavior, PostingEngine journal creation, linked GL validation, optimistic locking, audit logging, and existing validation keys.
- Extended the financial-service regression guard to include TreasuryTransferService and require Arabic translations for all newly covered transfer validation messages.

## Slice 69 Changes

- Localized BranchApprovalRuleService validation errors for required override permissions, unsupported approval document types, unsupported branch match modes, document-only branch matching, missing permissions, missing branches, and duplicate approval rules.
- Preserved branch approval behavior as an operational branch-control feature only; no tenant/company/current-branch scope was introduced.
- Added regression coverage preventing raw bracketed permission messages and raw branch approval validation messages from returning.

## Slice 70 Changes

- Localized tax period, tax return, tax master-data, tax calculation, and filed-period guard validation errors through Laravel translations.
- Replaced tax-code/date/period interpolation with placeholder-backed messages for safer Arabic/English rendering.
- Preserved tax filing locks, VAT calculation rules, basis-point rate math, period overlap checks, audit logging, and existing validation keys.
- Added regression coverage requiring Arabic translations for the touched tax service messages and preventing raw tax validation fragments from returning.

## Slice 71 Changes

- Localized ExpenseService, ExpenseCategoryService, PrepaidScheduleService, and AccrualScheduleService validation errors through Laravel translations.
- Replaced expense line, currency, date, status, and account-label interpolation with placeholder-backed messages for safer Arabic/English rendering.
- Preserved settlement method validation, attachment-before-posting requirements, period guards, posting behavior, audit logging, exact integer money math, optimistic locking, and operational branch references.
- Added regression coverage requiring Arabic translations for the touched expense/prepaid/accrual service messages and preventing raw validation fragments from returning.

## Slice 72 Changes

- Localized PayrollRunService, PayrollComponentService, EmployeeService, and EmployeePayrollComponentService validation errors through Laravel translations.
- Replaced payroll run, employee, component, deduction, account-label, branch, status, and effective-date interpolation with placeholder-backed messages for safer Arabic/English rendering.
- Preserved payroll lifecycle, period locking, authenticated posting guard, payroll component assignment rules, exact integer payroll math, PostingEngine integration, audit logging, optimistic locking, and operational branch references.
- Added regression coverage requiring Arabic translations for the touched payroll service messages and preventing raw payroll validation fragments from returning.

## Slice 73 Changes

- Localized WarehouseService, WarehouseResolver, StockCountService, StockTransferService, and StockAdjustmentService validation errors through Laravel translations.
- Replaced warehouse/location code, stock count/transfer/adjustment status, product, quantity, UOM, date, branch, and valuation-currency interpolation with placeholder-backed messages for safer Arabic/English rendering.
- Preserved warehouse default protection, stock count approval/posting flow, stock transfer issue/receipt flow, stock adjustment posting flow, branch approval rules, period guards, audit logging, optimistic locking, and operational branch references.
- Deferred MovingWeightedAverageInventoryService localization to a dedicated Slice 74 because it is a larger financial-costing engine with separate posting and valuation invariants.
- Added regression coverage requiring Arabic translations for the touched inventory workflow service messages and preventing raw inventory workflow validation fragments from returning.

## Slice 74 Changes

- Localized MovingWeightedAverageInventoryService validation errors through Laravel translations.
- Replaced receipt, issue, return, scrap, transfer-in/out, stock-adjustment, landed-cost, insufficient-stock, multi-currency valuation, original-movement lookup, and integer-overflow validation messages with placeholder-backed messages.
- Preserved moving weighted average calculations, stock balance row locking, immutable stock movement ledger behavior, GL mapping currency checks, PostingEngine integration, landed-cost capitalization rules, return-cost calculation, and operational warehouse/branch references.
- Added regression coverage requiring Arabic translations for the touched inventory costing service messages and preventing raw inventory costing validation fragments from returning.

## Slice 75 Changes

- Localized RentableItemService and RentalContractService validation errors through Laravel translations.
- Replaced rentable item source/status/condition, code, linked product/fixed-asset, branch/warehouse placement, contract lifecycle, contract line, date, amount, duplicate-item, reservable-status, and total-overflow validation messages with translation-backed copy.
- Preserved rental item status transitions, rental contract submit/approve/activate/cancel lifecycle, item reservation/allocation/rented transitions, audit logging, optimistic locking, numbering, exact integer rental amount math, and operational branch/warehouse placement.
- Added regression coverage requiring Arabic translations for the touched rental item/contract service messages and preventing raw validation fragments from returning.

## Slice 76 Changes

- Localized RentalFulfillmentService validation errors through Laravel translations.
- Replaced handover, return, inspection, cancellation, item-status, contract-line, return-line, duplicate-line, condition, outcome, reference, date, and amount validation messages with translation-backed copy.
- Preserved rental handover confirmation/cancellation, return submission/inspection/cancellation, item status transitions, audit logging, optimistic locking, exact integer amount validation, and operational branch/warehouse semantics.
- Added regression coverage requiring Arabic translations for the touched rental fulfillment service messages and preventing raw validation fragments from returning.

## Slice 77 Changes

- Localized RentalInvoiceService validation errors through Laravel translations.
- Replaced rental invoice update/submit/approve/post/cancel, billable-contract, invoice type, currency, billing period, line validation, duplicate billing, deposit cap, damage charge cap, GL mapping currency, period resolution, identifier, date, and amount validation messages with translation-backed copy.
- Preserved rental invoice billing, output VAT calculation, receivable-entry creation, PostingEngine GL posting, period/tax-period guards, overbilling prevention, audit logging, optimistic locking, and operational branch semantics.
- Added regression coverage requiring Arabic translations for the touched rental invoice service messages and preventing raw validation fragments from returning.

## Slice 78 Changes

- Localized fixed-asset application service validation errors through Laravel translations.
- Covered FixedAssetCategoryService, FixedAssetRegisterService, FixedAssetCapitalizationService, FixedAssetDepreciationEngineService, FixedAssetDepreciationPostingService, FixedAssetDisposalPostingService, FixedAssetLocationService, and FixedAssetMovementService.
- Replaced category, register, capitalization, depreciation schedule/run, disposal, location, movement, currency, date, duplicate-number, lifecycle-status, and period-availability validation messages with placeholder-backed copy.
- Preserved fixed-asset capitalization, opening asset handling, depreciation schedule math, depreciation posting/reversal, disposal gain/loss accounting, location movement history, audit logging, optimistic locking, period guards, and operational branch/location semantics.
- Added regression coverage requiring Arabic translations for the touched fixed-asset service messages and preventing raw validation fragments from returning.

## Slice 79 Changes

- Localized `CustomerInvoiceService` and `SupplierBillService` validation errors through Laravel translations.
- Replaced customer/supplier activity checks, invoice/bill lifecycle guards, source-document matching, period/date checks, GL/tax mapping currency validation, stock-source requirements, line validation, over-invoicing/over-billing caps, and exact integer amount guards with placeholder-backed copy.
- Preserved sales invoice posting, supplier bill posting, VAT snapshots, AR/AP subledger entries, PostingEngine journals, source-document quantity locks, optimistic locking, period/tax-period guards, and operational branch/warehouse semantics.
- Added regression coverage requiring Arabic translations for the touched sales/purchasing invoice service messages and preventing raw validation fragments from returning.

## Slice 80 Changes

- Localized `SalesReturnService`, `CustomerCreditNoteService`, `PurchaseReturnService`, and `SupplierAdjustmentNoteService` validation errors through Laravel translations.
- Replaced lifecycle guards, source-document matching, customer/supplier consistency checks, period/date checks, VAT/GL mapping currency checks, stock-balance limits, return/credit quantity caps, disposition/direction/tax-mode guards, and exact integer amount guards with placeholder-backed copy.
- Preserved sales returns, customer credit notes, purchase returns, supplier adjustment notes, VAT reversal/snapshot behavior, AR/AP settlement behavior, stock movement/costing behavior, PostingEngine journals, period/tax-period guards, optimistic locking, and operational branch/warehouse semantics.
- Fixed `SupplierAdjustmentNoteService::validateAndCalculateLines()` so tax calculation uses the explicit adjustment date passed by create/update flows instead of relying on an out-of-scope variable.
- Added regression coverage requiring Arabic translations for the touched returns/adjustment service messages and preventing raw validation fragments from returning.

## Slice 81 Changes

- Localized `SalesOrderService`, `PurchaseOrderService`, `DeliveryNoteService`, and `GoodsReceiptService` validation errors through Laravel translations.
- Replaced order lifecycle guards, customer/supplier activity checks, explicit currency/date validation, product/UOM validation, exact integer line-total guards, fulfillment source matching, delivery/receipt quantity caps, and cancellation/confirmation status guards with placeholder-backed copy.
- Preserved sales/purchase order lifecycle behavior, `SO`/`PO`/`DN`/`GRN` sequence allocation, idempotent confirmation behavior, deterministic row locks, stock receipt/issue integration, PostingEngine-adjacent invariants, optimistic locking, and operational warehouse semantics.
- Added regression coverage requiring Arabic translations for the touched order/fulfillment service messages and preventing raw validation fragments from returning.

## Slice 82 Changes

- Localized `ProductService`, `ProductCategoryService`, `UnitOfMeasureService`, `CustomerService`, and `SupplierService` validation errors through Laravel translations.
- Replaced product SKU uniqueness, product type/status validation, UOM/category activity checks, protected product category/UOM deletes, customer/supplier duplicate-code checks, and customer/supplier status validation with placeholder-backed copy.
- Preserved catalog master-data create/update/delete behavior, audit logging, optimistic locking, product category/UOM reference protection, and customer/supplier active/inactive semantics.
- Added regression coverage requiring Arabic translations for the touched catalog/customer/supplier messages and preventing raw validation fragments from returning.

## Slice 83 Changes

- Localized `AccountingAccountMappingService` and `FinancialStatementMappingService` validation errors through Laravel translations.
- Replaced missing/inactive accounting mapping messages, disallowed mapping keys, branch override target validation, mapping account type/nature guards, financial statement line creation/update/delete guards, account assignment checks, and cash-flow classification safety messages with placeholder-backed copy.
- Preserved global mapping protection, branch-specific operational mapping overrides, account type/nature enforcement, statement-line assignment rules, cash/bank cash-flow classification policy, audit logging, and deletion-safety behavior.
- Added regression coverage requiring Arabic translations for the touched mapping-service messages and preventing raw validation fragments from returning.

## Slice 84 Changes

- Localized `BankReconciliationService`, `CashBookQueryService`, `BankBookQueryService`, `IncomingChequeService`, and `OutgoingChequeService` validation errors through Laravel translations.
- Replaced cheque lifecycle status guards, post-clear owner-decision blockers, bank account activity/currency guards, mapped account validation, financial period/date guards, cash/bank book linked-GL checks, bank reconciliation date/range checks, line match/unmatch/delete guards, ledger candidate validation, finalization blockers, and zero-difference requirements with placeholder-backed copy.
- Preserved cheque posting, AR/AP subledger effects, PostingEngine journals, idempotent replay, deterministic row locks, reconciliation matching/finalization behavior, cash/bank book read-model behavior, audit logging, and period guards.
- Added regression coverage requiring Arabic translations for the touched bank/cheque messages and preventing raw validation fragments from returning.

## Slice 85 Changes

- Localized `LandedCostAllocationService` validation errors through Laravel translations.
- Replaced landed cost lifecycle guards, optimistic-lock message, receipt currency check, posting prerequisites, financial period/fiscal-year check, FX-rate guard, confirmed Goods Receipt rules, allocated split validation, mapped GL currency checks, transition guard, supplier activity check, eligible stock-line checks, manual allocation sum/negative checks, allocation-weight checks, and receipt-line purchase-cost exactness guards with translation-backed copy.
- Preserved landed cost allocation math, receipt-value and quantity allocation behavior, manual split behavior, stock capitalization/COGS split behavior, input VAT/AP/GL posting, PostingEngine usage, PeriodGuard/TaxPeriodGuard behavior, audit logging, and operational branch mapping lookup.
- Added regression coverage requiring Arabic translations for the touched landed-cost messages and preventing raw validation fragments from returning.

## Slice 86 Changes

- Localized `ReceivableAllocationService` and `PayableAllocationService` validation errors through Laravel translations.
- Replaced allocation line presence checks, target-entry references, duplicate target guards, posted receipt/payment status checks, positive integer amount guards, unapplied balance caps, missing target entry checks, customer/supplier mismatch guards, currency mismatch guards, positive AR/AP item guards, and remaining allocatable amount caps with placeholder-backed copy.
- Preserved receipt/payment allocation math, unapplied/allocated balance updates, deterministic row-lock ordering, idempotency-key replay behavior, allocation reversal guards, Spatie Activitylog audit calls, and the no-GL-entry allocation invariant.
- Added regression coverage requiring Arabic translations for the touched AR/AP allocation messages and preventing raw validation fragments from returning.

## Slice 87 Changes

- Localized `ReceivableEntrySettlementService` and `PayableEntrySettlementService` validation errors through Laravel translations.
- Replaced settlement line presence checks, target-entry reference checks, self-settlement guards, duplicate target guards, missing source/target entry checks, source credit/debit eligibility checks, positive settlement amount checks, source remaining-balance caps, customer/supplier mismatch guards, currency mismatch guards, target debit/credit eligibility checks, target remaining-balance caps, and reversal reason validation with placeholder-backed copy.
- Preserved AR/AP credit/debit settlement math, deterministic row-lock ordering, allocation-aware remaining balance checks, idempotency replay, settlement reversal guards, audit calls, and no new GL-posting behavior inside settlement.
- Added regression coverage requiring Arabic translations for the touched AR/AP settlement messages and preventing raw validation fragments from returning.

## Slice 88 Changes

- Localized touched validation errors in `CustomerReceiptService`, `SupplierPaymentService`, `CustomerOpeningBalanceService`, and `SupplierOpeningBalanceService`.
- Replaced draft-only cancellation guards, selected fiscal-year/period mismatch checks, and closed-period creation checks with translation-backed copy.
- Preserved receipt/payment and opening-balance creation, cancellation, posting, PostingEngine integration, AR/AP subledger creation, cash/bank GL resolution, idempotent posting, PeriodGuard checks, and audit logging.
- Added regression coverage requiring Arabic translations for the touched receipt/payment/opening-balance messages and preventing raw validation fragments from returning.

## Slice 89 Changes

- Localized `CustomerInvoiceRevisionService` validation errors and shared `CurrencyInput` validation errors through Laravel translations.
- Replaced invoice-revision source existence checks, posted-invoice requirement, missing invoice-line guard, required currency validation, ISO currency-code validation, and source-document currency validation with translation-backed copy.
- Preserved invoice revision snapshot generation, returned/credited/net quantity and amount calculations, audit logging, and shared explicit-currency input behavior.
- Added regression coverage requiring Arabic translations for the touched invoice-revision/currency-input messages and preventing raw validation fragments from returning.
- Verified the broad `app/Application` raw validation-message scan for the targeted `=> ['English...']` pattern now returns no matches.

## Slice 90 Changes

- Localized report export abort messages in `BankBookController`, `CashBookController`, `CustomerStatementController`, and `SupplierStatementController`.
- Localized `CsvReportResponse` output-stream failure handling through Laravel translations.
- Preserved report export behavior, report query services, CSV row generation, financial visibility route protection, and centralized `CsvReportResponse` streaming.
- Added regression coverage requiring Arabic translations for the touched report-export messages and preventing raw abort/runtime fragments from returning.
- Verified the remaining raw report-export error scan returns no matches for the targeted controller/application/support patterns.

## Slice 91 Changes

- Added `CashBankBookCsvExporter` for Cash Book and Bank Book CSV composition.
- Added `PartnerStatementCsvExporter` for Customer Statement and Supplier Statement CSV composition.
- Expanded `CsvReportResponse` with a shared `stream()` method so CSV response headers, stream opening, stream failure localization, and handle cleanup are centralized.
- Removed `fputcsv`, `response()->stream`, and direct decimal formatting from `CashBookController`, `BankBookController`, `CustomerStatementController`, and `SupplierStatementController`.
- Preserved statement report routes, filenames, filter behavior, query-service usage, CSV headings, report rows, and financial visibility/export permissions.
- Added regression coverage proving the touched controllers delegate CSV composition and the exporters produce the expected CSV output.

## Slice 92 Changes

- Added `ArApCsvReportExporter` for AR Aging, AP Aging, AR-to-GL Reconciliation, and AP-to-GL Reconciliation CSV composition.
- Added `ChequeRegisterCsvExporter` for Cheque Register CSV composition.
- Removed `fputcsv`, `response()->stream`, and direct decimal formatting from `ArAgingController`, `ApAgingController`, `ArToGlReconciliationController`, `ApToGlReconciliationController`, and `ChequeRegisterReportController`.
- Preserved report routes, filenames, filter behavior, query services, CSV headings, detail rows, totals, reconciliation status output, and financial visibility/export permissions.
- Added regression coverage proving the touched AR/AP and cheque report controllers delegate CSV composition and the exporters produce expected CSV output.

## Slice 93 Changes

- Added `FinancialStatementCsvExporter` for Balance Sheet, Income Statement, and Cash Flow CSV composition.
- Added `BranchProfitabilityCsvExporter` for Branch Profitability CSV composition.
- Removed `fputcsv`, `response()->stream`, and local export-name helper logic from `BalanceSheetReportController`, `IncomeStatementReportController`, `CashFlowReportController`, and `BranchProfitabilityReportController`.
- Preserved report routes, filenames, filters, report-service usage, CSV headings, section/detail rows, summary rows, cash-flow reconciliation output, branch readiness output, and report/export permissions.
- Added regression coverage proving the touched financial-statement and branch report controllers delegate CSV composition and the exporters produce expected CSV output.

## Slice 94 Changes

- Centralized the remaining `Application/Reports` CSV stream lifecycle in `CsvReportResponse::stream()`.
- Updated `VatCsvReportExporter` summary and reconciliation exports to use the shared stream boundary instead of local `fopen('php://output')` / `fclose()` handling.
- Updated `RentalOperationsCsvExporter` to use `CsvReportResponse::stream()` instead of direct `response()->stream()` and manual output-handle lifecycle.
- Preserved VAT register, VAT summary, VAT-to-GL reconciliation, rental operations report behavior, filenames, rows, summary/readiness output, and existing report permissions.
- Added regression coverage requiring every `Application/Reports` file except `CsvReportResponse` to avoid direct `response()->stream`, `fopen('php://output')`, `fclose($handle)`, and private stream helpers.

## Slice 95 Changes

- Added `FinancialPeriodReportOptions` to centralize financial-period selector composition for financial statement report pages.
- Updated `IncomeStatementReportController` and `CashFlowReportController` to inject the shared period-options service instead of duplicating `FinancialPeriod` eager-load/map logic.
- Preserved Inertia prop shape, period sort order, fiscal-year context, filters, report generation behavior, CSV exports, route names, and financial visibility/export permissions.
- Added regression coverage proving the shared service returns the expected period option shape and that the touched report controllers no longer contain direct `FinancialPeriod::query()` composition.

## Slice 96 Changes

- Replaced the hardcoded `GL-Entry` fallback in `Reports/BankReconciliationDetail.tsx` with a dictionary-backed accountant label for matched ledger entries that do not have a journal number.
- Added EN/AR dictionary keys under `reportsBankReconciliationDetail.missingGlJournalReference`.
- Preserved matched-entry rendering, reconciliation line amounts, unmatched-line behavior, and bank reconciliation report route/data contracts.
- Added regression coverage preventing the raw `GL-Entry` fallback from returning.

## Slice 97 Changes

- Replaced the hardcoded dashboard missing-user fallback `User` with the existing dictionary-backed `dict.app.header.unknownUser` label.
- Preserved the dashboard welcome banner, user-name interpolation, dashboard metrics, shortcut filtering, and app shell behavior.
- Added regression coverage preventing `Dashboard.tsx` from reintroducing the raw `|| 'User'` fallback and verifying EN/AR `unknownUser` dictionary coverage.

## Slice 98 Changes

- Replaced the hardcoded `EN` / `ع` language-switcher text in `AppLayout.tsx` with `dict.common.language.en` and `dict.common.language.ar`.
- Preserved locale switching behavior, header layout, theme toggle behavior, and existing common language dictionary entries.
- Expanded the existing AppLayout regression guard to prevent the hardcoded language-switcher fallback from returning.

## Slice 99 Changes

- Replaced the generated duplicate finalize-confirmation key usage in `BankReconciliations/Show.tsx` with the explicit `bankReconciliationsShow.confirmFinalizeReconciliation` dictionary key.
- Added clear EN/AR finalization confirmation copy for bank reconciliation closing.
- Preserved bank reconciliation matching, unmatching, line deletion, and finalize route behavior.
- Added regression coverage preventing the page from reusing the generated `areYouSureYouWantTo_2` key for finalization.

## Slice 100 Changes

- Cleaned Arabic catalog product modal select labels for product type and status by removing English parenthetical copy from the visible Arabic locale values.
- Preserved English locale labels, product type/status values, form submission payloads, and catalog service behavior.
- Added regression coverage verifying the used Arabic product modal labels remain non-empty and do not include English parenthetical copy.

## Slice 101 Changes

- Moved catalog product-category and unit-of-measure code/symbol placeholders out of JSX and into EN/AR dictionaries.
- Cleaned Arabic catalog category/UOM form labels by removing English parenthetical label copy for ordinary labels.
- Preserved code examples as dictionary-backed placeholders because product/category/UOM codes remain operational identifiers.
- Added regression coverage preventing the hardcoded catalog placeholder strings and English parenthetical labels from returning.

## Slice 102 Changes

- Added `App\Application\Dashboard\DashboardPageData` to own dashboard count and recent-notification page-data composition.
- Refactored `DashboardController` to delegate dashboard props to the application service instead of importing models and querying `notification` directly.
- Preserved dashboard Inertia props, unread notification count, recent notification shape, and existing migrated dashboard behavior.
- Added regression coverage preventing raw dashboard model imports and `DB::table` notification queries from returning to the controller.

## Slice 103 Changes

- Added `CustomerPageData` and `SupplierPageData` under `App\Application\MasterData` to own customer/supplier index search, status filtering, pagination, currency options, and filter echoing.
- Refactored `CustomerController` and `SupplierController` to delegate index page-data composition while keeping create/update behavior in the existing master-data services.
- Preserved `/customers` and `/suppliers` Inertia prop shapes, pagination behavior, search/status filters, and currency selector data.
- Added regression coverage preventing customer/supplier query composition and `Currency::query` from returning to those controllers.

## Slice 104 Changes

- Added `CashAccountPageData` and `BankAccountPageData` under `App\Application\MasterData` to own cash/bank account index search, status/branch filtering, pagination, GL account options, currency options, branch options, and filter echoing.
- Refactored `CashAccountController` and `BankAccountController` to delegate index page-data composition while keeping create/update behavior in the existing master-data services.
- Preserved `/cash-accounts` and `/bank-accounts` Inertia prop shapes, operational branch filtering, GL account selector data, currency selector data, and pagination behavior.
- Added regression coverage preventing cash/bank account query composition and option-list queries from returning to those controllers.

## Slice 105 Changes

- Refactored `Accounting\TrialBalanceController` to use the shared `FinancialPeriodReportOptions` service for financial-period selector data.
- Removed direct `FinancialPeriod::query()->with('fiscalYear')` option composition from the Trial Balance controller.
- Preserved the `/accounting/trial-balance` Inertia prop shape, report filters, totals, rows, display currency, and existing `GeneralLedgerService` trial-balance calculations.
- Added regression coverage preventing direct financial-period option queries from returning to `TrialBalanceController`.

## Slice 106 Changes

- Added `CustomerOpeningBalancePageData` and `SupplierOpeningBalancePageData` under `App\Application\Accounting` to own AR/AP opening-balance index data.
- Refactored `CustomerOpeningBalanceController` and `SupplierOpeningBalanceController` to delegate balance listing, active partner options, open fiscal-year options, open financial-period options, and currency options.
- Preserved `/customer-opening-balances` and `/supplier-opening-balances` Inertia prop shapes, posting actions, service-backed create/post behavior, and existing AR/AP opening-balance accounting invariants.
- Added regression coverage preventing direct partner, opening-balance, fiscal-year, financial-period, and currency option queries from returning to those controllers.

## Slice 107 Changes

- Added `CustomerReceiptPageData` and `SupplierPaymentPageData` under `App\Application\Accounting` to own AR/AP receipt/payment index data.
- Refactored `CustomerReceiptController` and `SupplierPaymentController` to delegate receipt/payment listing, active partner options, active cash/bank account options, open fiscal-year options, open financial-period options, and currency options.
- Preserved `/customer-receipts` and `/supplier-payments` Inertia prop shapes, posting actions, service-backed create/post behavior, and existing AR/AP cash/bank accounting invariants.
- Added regression coverage preventing direct partner, receipt/payment, cash/bank account, fiscal-year, financial-period, and currency option queries from returning to those controllers.

## Slice 108 Changes

- Added `IncomingChequePageData` and `OutgoingChequePageData` under `App\Application\Accounting` to own cheque index data and filter echoing.
- Refactored `IncomingChequeController` and `OutgoingChequeController` to delegate cheque listing, active partner options, active bank account options, open fiscal-year options, open financial-period options, and currency options.
- Preserved `/incoming-cheques` and `/outgoing-cheques` Inertia prop shapes, status/partner filters, lifecycle actions, service-backed posting behavior, and existing cheque accounting invariants.
- Added regression coverage preventing direct cheque, partner, bank-account, fiscal-year, financial-period, and currency option queries from returning to those controllers.

## Slice 109 Changes

- Added `ReceivableAllocationPageData` and `PayableAllocationPageData` under `App\Application\Accounting` to own AR/AP allocation index data.
- Refactored `ReceivableAllocationController` and `PayableAllocationController` to delegate posted unapplied source receipts/payments, selected source lookup, open target receivable/payable entries, existing allocations, active partner options, and filter echoing.
- Preserved `/receivable-allocations` and `/payable-allocations` Inertia prop shapes, allocation/reversal actions, service-backed settlement behavior, and existing AR/AP allocation invariants.
- Added regression coverage preventing direct allocation, receipt/payment, entry, and partner query composition from returning to those controllers.

## Slice 110 Changes

- Added `ReceivableEntrySettlementPageData` and `PayableEntrySettlementPageData` under `App\Application\Accounting` to own manual settlement index data and remaining-balance calculations.
- Refactored `ReceivableEntrySettlementController` and `PayableEntrySettlementController` to delegate open source entries, selected source remaining amount, eligible target entries, existing settlements, partner options, and filter echoing.
- Preserved `/receivable-settlements` and `/payable-settlements` Inertia prop shapes, settlement/reversal actions, service-backed settlement behavior, and existing no-extra-GL settlement invariants.
- Added regression coverage preventing direct settlement, allocation, entry, partner, `whereRaw`, and array-composition logic from returning to those controllers.

## Slice 111 Changes

- Added `SalesOrderPageData` under `App\Application\Sales` and `PurchaseOrderPageData` under `App\Application\Purchasing` to own order-list filtering, pagination, active partner options, currency options, and eligible product options.
- Refactored `SalesOrderController` and `PurchaseOrderController` to delegate index page-data composition while preserving create/update/submit/confirm/cancel action behavior.
- Preserved `/sales-orders` and `/purchase-orders` Inertia prop shapes, search/status/partner filters, line eager-loading, eligible product selectors, and existing sales/purchasing order lifecycle invariants.
- Added regression coverage preventing direct order, partner, currency, product, and `whereHas` query composition from returning to those controllers.

## Slice 112 Changes

- Added `DeliveryNotePageData` under `App\Application\Sales` and `GoodsReceiptPageData` under `App\Application\Purchasing` to own fulfillment document listing, warehouse filters, confirmed source document selectors, and active warehouse options.
- Refactored `DeliveryNoteController` and `GoodsReceiptController` to delegate index page-data composition while preserving store/update/confirm/cancel action behavior and active warehouse validation.
- Preserved `/delivery-notes` and `/goods-receipts` Inertia prop shapes, search/status/warehouse filters, source order eager-loading, warehouse selectors, and existing fulfillment lifecycle/inventory invariants.
- Added regression coverage preventing direct fulfillment, source order, warehouse, and `whereHas` query composition from returning to those controllers.

## Slice 113 Changes

- Added `CustomerInvoiceRevisionPageData` under `App\Application\Sales` to own invoice revision listing, detail loading, relation eager-loading, search filtering, pagination, and snapshot decoding.
- Refactored `CustomerInvoiceRevisionController` to delegate both index and show page-data composition while preserving the existing `Sales/InvoiceRevisions` and `Sales/InvoiceRevisionShow` Inertia prop shapes.
- Preserved customer invoice revision history behavior, generated snapshot display, linked credit-note/return context, and existing immutable original-invoice revision invariants.
- Added regression coverage preventing direct revision queries, `whereHas`, `snapshot_json`, and JSON decoding from returning to the controller.

## Slice 114 Changes

- Added `AccountingAccountMappingPageData` under `App\Application\Accounting` to own mapping key props, mapping rows, active account options, and operational branch options.
- Refactored `AccountingAccountMappingController` to delegate index page-data composition while preserving `accounting.mappings` authorization and `AccountingAccountMappingService` set/delete action behavior.
- Preserved `/accounting/account-mappings` Inertia prop shapes, branch-specific override display, global mapping update workflow, delete restrictions, and existing branch-first/global-fallback mapping invariants.
- Added regression coverage preventing direct account, mapping, branch, ordering, and collection shaping logic from returning to the controller.

## Slice 115 Changes

- Added `AccountingOverviewPageData` under `App\Application\Accounting` to own active fiscal year lookup, recent journal selection, and overview counts.
- Refactored `AccountingOverviewController` to keep only `accounting.view` authorization and Inertia rendering.
- Preserved `Accounting/Index` Inertia prop shapes, recent journal relations, posted/draft/account counts, and landing-page UI behavior.
- Added regression coverage preventing account, fiscal-year, journal, count, and recent-journal query logic from returning to the controller.

## Slice 116 Changes

- Added `AccountCategoryPageData` and `AccountTypePageData` under `App\Application\Accounting`.
- Refactored `AccountCategoryController` and `AccountTypeController` so index actions delegate listing, eager-loading, counts, active category selectors, and ordering to page-data services.
- Preserved account category/type CRUD validation, protected system deletes, in-use delete guards, legacy category sync, and existing `Accounting/AccountCategories` / `Accounting/AccountTypes` Inertia prop shapes.
- Added regression coverage preventing account master-data read-side query composition from returning to these controllers.

## Slice 117 Changes

- Added `JournalPageData` and `OpeningBalancePageData` under `App\Application\Accounting`.
- Refactored `JournalController` so General Journal listing filters, period/branch selectors, Journal Form options, journal detail eager-loading, and reversal-period options are composed outside the controller.
- Refactored `OpeningBalanceController` so fiscal-year selection, account options, and existing balance lookup are composed outside the controller.
- Preserved journal create/submit/approve/post/reverse actions, opening-balance save/post actions, route names, validation, permissions, branch operational references, and Inertia prop shapes.
- Added regression coverage preventing journal/opening-balance page-data query composition from returning to these controllers.

## Slice 118 Changes

- Added `CurrencyPageData`, `ExchangeRatePageData`, `ChartOfAccountsPageData`, and `FinancialPeriodPageData` under `App\Application\Accounting`.
- Refactored Currency, Exchange Rate, Chart of Accounts, and Financial Period controllers so index page-data composition is delegated out of controllers.
- Preserved currency CRUD and linked-record delete guards, FX-rate validation/service persistence, chart account/group creation rules, fiscal-year creation/close/reopen workflows, permissions, and Inertia prop shapes.
- Reused the existing `BaseCurrencyResolver` for FX-rate page base currency data instead of keeping base-currency lookup logic in the controller.
- Added regression coverage preventing remaining accounting master-data query composition from returning to these controllers.

## Slice 119 Changes

- Added `ProductCategoryPageData`, `ProductPageData`, and `UnitOfMeasurePageData` under `App\Application\Catalog`.
- Refactored Product Category, Product, and Unit of Measure controllers so index filters, listing queries, pagination, active UOM options, and active category options are composed outside controllers.
- Preserved catalog create/update/delete services, validation, optimistic-lock payloads, product type/status filtering behavior, route names, and Inertia prop shapes.
- Added regression coverage preventing catalog index query/filter/pagination composition from returning to these controllers.

## Slice 120 Changes

- Added `ExpenseCategoryPageData`, `ExpensePageData`, `PrepaidSchedulePageData`, and `AccrualSchedulePageData` under `App\Application\Expenses`.
- Refactored Expense Category, Expense, Prepaid Schedule, and Accrual Schedule controllers so index filters, listing queries, pagination, active account/category/tax/currency/cash/bank/supplier/branch selectors, statuses, and settlement-method options are composed outside controllers.
- Preserved expense/prepaid/accrual create/update/submit/approve/post/cancel services, validation, PostingEngine integration, tax-period guards, period-close blockers, operational branch references, permissions, route names, and Inertia prop shapes.
- Added regression coverage preventing expense/prepaid/accrual index query/filter/pagination and selector composition from returning to these controllers.

## Slice 121 Changes

- Added `FixedAssetLocationPageData` and `FixedAssetDisposalPageData`, and extended `FixedAssetPageData` with `assetForEditing`.
- Refactored Fixed Asset, Fixed Asset Location, and Fixed Asset Disposal controllers so edit lookup, location index data, disposal listing filters, disposal pagination, and disposal detail eager-loading are composed outside controllers.
- Preserved fixed asset register/create/update/delete behavior, location create/update/delete behavior, disposal preview/post/reverse behavior, `view_financials` gates, operational branch/location references, route names, and Inertia prop shapes.
- Added regression coverage preventing fixed-asset page-data query/filter/pagination composition from returning to these controllers.

## Slice 122 Changes

- Added `PayrollEmployeePageData`, `PayrollComponentPageData`, and `PayrollRunPageData` under `App\Application\Payroll`.
- Refactored Payroll Employee, Payroll Component, and Payroll Run controllers so index filters, listing queries, pagination, active branch/currency/component/account/period selectors, statuses, run types, and payment-method options are composed outside controllers.
- Preserved employee create/update, component create/update/delete, employee component assignment create/delete, payroll run create/regenerate/submit/approve/post/cancel behavior, `view_payroll`/`view_financials` route gates, operational branch references, route names, and Inertia prop shapes.
- Added regression coverage preventing payroll index query/filter/pagination and selector composition from returning to these controllers.

## Slice 123 Changes

- Added `RentableItemPageData`, `RentalContractPageData`, `RentalHandoverPageData`, and `RentalReturnPageData` under `App\Application\Rentals`.
- Refactored Rentable Item, Rental Contract, Rental Handover, and Rental Return controllers so index filters, listing queries, pagination, customer/branch/warehouse/product/fixed-asset/currency/contract selectors, statuses, billing cycles, rate types, conditions, and return outcomes are composed outside controllers.
- Preserved rentable item create/update/delete, rental contract create/update/submit/approve/activate/cancel, rental handover create/confirm/cancel, and rental return create/submit/complete/cancel behavior, route middleware, operational branch/warehouse references, route names, and Inertia prop shapes.
- Added regression coverage preventing rental operational page-data query/filter/pagination and selector composition from returning to these controllers.

## Slice 124 Changes

- Added `WarehousePageData`, `StockBalancePageData`, `StockTransferPageData`, `StockCountPageData`, and `StockAdjustmentPageData` under `App\Application\Inventory`.
- Refactored Warehouse, Stock Balance, Stock Transfer, Stock Count, and Stock Adjustment controllers so index filters, listing queries, pagination, active warehouse/product/currency selectors, warehouse/location types, and allowed statuses are composed outside controllers.
- Preserved warehouse create/update/delete, stock transfer create/update/submit/approve/issue/receive/cancel, stock count create/update/submit/approve/post/cancel, stock adjustment create/update/submit/approve/post/cancel behavior, route middleware, operational branch/warehouse references, route names, and Inertia prop shapes.
- Added regression coverage preventing inventory and warehouse index query/filter/pagination and selector composition from returning to these controllers.

## Slice 125 Changes

- Added `LandedCostAllocationPageData` under `App\Application\Purchasing` and `TreasuryTransferPageData` under `App\Application\Accounting`.
- Refactored Landed Cost Allocation and Treasury Transfer controllers so index filters, listing queries, pagination, confirmed goods-receipt selectors, active supplier/cash/bank account selectors, fiscal year/financial period selectors, statuses, and allocation methods are composed outside controllers.
- Preserved landed-cost create/update/submit/approve/post/cancel and treasury-transfer create/update/post/cancel behavior, financial posting guards, operational branch references, route middleware, route names, and Inertia prop shapes.
- Added regression coverage preventing landed-cost and treasury-transfer query/filter/pagination and selector composition from returning to controllers.

## Slice 126 Changes

- Added `TaxCodePageData`, `TaxRatePageData`, and `TaxPeriodPageData` under `App\Application\Taxes`.
- Refactored Tax Code, Tax Rate, and Tax Period controllers so tax-code listing/edit lookup, tax-rate listing/selectors, tax-period listing/detail composition, latest/filed return props, and display currency resolution are composed outside controllers.
- Reused `BaseCurrencyResolver` for tax-period display currency and removed direct Company/Currency lookup from `TaxPeriodController`.
- Preserved tax-code create/update/delete, tax-rate create/update/delete, tax-period create/generate-draft/file-return behavior, permissions, route names, and Inertia prop shapes.
- Added regression coverage preventing tax page-data queries and base-currency lookup from returning to controllers.

## Slice 127 Changes

- Added `ReportPageOptions` under `App\Application\Reports` as the shared selector service for report pages.
- Refactored report controllers so customer, supplier, product, currency, cash account, bank account, warehouse, and operational branch selector queries are no longer built inline inside controllers.
- Cleaned selector imports from AR/AP aging, AR/AP-to-GL reconciliation, cash/bank book, customer/supplier statement, cheque register, bank reconciliation, branch operational/profitability, sales/purchase document, delivery/goods receipt, customer invoice, supplier bill, and stock movement report controllers.
- Preserved report generation services, CSV exporters, route names, permissions, default filter behavior, Inertia prop names, and branch-as-operational-dimension behavior without adding tenant/company scope.
- Added regression coverage preventing report selector query composition from returning to report controllers.

## Slice 128 Changes

- Added `BranchSettingsService`, `RoleSettingsService`, and `UserRoleAssignmentService` under `App\Application\Settings`.
- Moved branch listing/localized presentation, branch create/update/delete persistence, role create/update/delete persistence, and user role assign/revoke side effects out of controllers.
- Extended `BranchApprovalRuleService` with `indexData()` so the branch approval rule page delegates listing and selector composition.
- Extended `AuditLogQueryService` with `pageData()` and `usersList()` so `AuditLogController` no longer composes read-side selector queries.
- Preserved settings permissions, branch optimistic locking, last-active-super-admin protection, Spatie Activitylog audit records, role assignment/revocation notifications, route names, and Inertia prop shapes.
- Added regression coverage proving all controllers under `app/Http/Controllers` no longer contain direct `DB::table(` or `::query(` usage.

## Slice 129 Changes

- Added shared `ReportFilterPanel` under `resources/js/Components`.
- Added visible currency selectors to the Sales Orders, Purchase Orders, Customer Invoices, and Supplier Bills report pages so mixed-currency summaries are not hidden behind an invisible query parameter.
- Added active-filter counts and reset actions to the same report pages, keeping all visible labels dictionary-backed through `en.json` and `ar.json`.
- Updated the four report controllers to pass currency selector options from `ReportPageOptions` while preserving report services, filters, permissions, and Inertia prop shapes.
- Added regression coverage proving the pages use the shared filter panel, expose currency filtering, retain reset actions, receive `currencies`, and keep report dictionary keys in both locales.

## Slice 130 Changes

- Applied `ReportFilterPanel` to Delivery Notes, Goods Receipts, and Stock Movements reports.
- Converted delivery/goods-receipt customer, supplier, product, warehouse, and status filters to searchable selectors for faster operational review.
- Added active-filter counts and reset actions to the three inventory-related report pages.
- Added visible currency filtering to Stock Movements and passed currency selector options from `StockMovementReportController`.
- Added Stock Movement dictionary keys for currency, all-currency placeholder, clear-filters action, and active-filter label.
- Added regression coverage proving inventory-related report pages use the shared filter panel, visible reset controls, and the Stock Movements visible currency selector.

## Slice 131 Changes

- Cleaned filter reset flow in `Expenses/Prepaids.tsx`, `Expenses/Accruals.tsx`, `Payroll/Components.tsx`, `Payroll/Employees.tsx`, and `Payroll/Runs.tsx`.
- Replaced long inline clear-filter click handlers with named `clearFilters()` functions for easier review and lower UI-maintenance risk.
- Added `activeFilterCount` to each page and disabled the clear-filter action when no filters are active, reducing accidental no-op clicks in daily accounting workflows.
- Preserved existing route names, query parameter names, permissions, backend page-data services, dictionaries, and operational branch references.
- Added regression coverage proving the touched expense/payroll pages keep named reset handlers, preserve Inertia state/scroll behavior, and do not reintroduce inline clear-filter handlers.

## Slice 132 Changes

- Extended no-op clear-filter protection to remaining inventory, expense, and rental operational pages with existing named reset actions.
- Added `activeFilterCount` and disabled clear-filter buttons in Stock Adjustments, Stock Counts, Warehouses, Stock Transfers, Stock Balances, Expense Entries, Expense Categories, Rental Handovers, Rental Returns, Rentable Items, Rental Contracts, and Rental Invoices.
- Preserved every existing filter query parameter, route, permission, dictionary label, and operational branch/warehouse reference.
- Added regression coverage proving remaining operational clear-filter controls are disabled when no filters are active and still use named reset handlers.

## Slice 133 Changes

- Replaced native status filter selects in Rental Handovers and Rental Returns with shared `SearchableSelect` controls.
- Replaced native status and invoice-type filter selects in Rental Invoices with shared `SearchableSelect` controls.
- Preserved all filter query keys, reset behavior, permissions, existing dictionary labels, and rental workflow actions.
- Added regression coverage proving the touched rental filter bars use shared searchable controls and do not reintroduce the native filter selects.

## Slice 134 Changes

- Replaced native filter selects in Fixed Asset Register, Fixed Asset Locations, and Fixed Asset Disposals with shared `SearchableSelect` controls.
- Added active-filter counts and guarded clear-filter actions in fixed asset register, locations, and disposals so reset buttons are disabled when no filter is active.
- Added EN/AR dictionary keys for fixed-asset clear-filter labels without adding visible hardcoded fallback text.
- Preserved existing fixed asset route names, query parameter names, permissions, backend page-data services, operational branch/location references, and disposal workflows.
- Added regression coverage proving fixed asset filter bars use shared searchable controls, no native filter selects remain in the touched pages, locale JSON remains valid, and reset actions stay guarded.

## Verification

- `node` JSON parse for `lang/ar.json`, `resources/js/locales/en.json`, and `resources/js/locales/ar.json`: passed.
- Arabic hardcoded text scan for the four cleaned pages: passed with 0 matches.
- Targeted settings hardcoded-text scan: passed with 0 matches.
- Silent operational fallback scan for Sales/Purchasing/Inventory pages: passed with 0 matches.
- Silent `EGP` fallback scan for Payroll/Expenses/Rentals/FixedAssets pages: passed with 0 matches.
- Tax Codes/Rates visible fallback scan: passed with 0 matches.
- Tax Periods visible fallback scan: passed with 0 matches.
- Master-data generic delete confirmation scan: passed with 0 matches.
- Accounting master-data form/detail fallback scan: passed with 0 matches.
- FX-rate hardcoded base/default currency scan: passed with 0 matches.
- Currency master-data visible fallback scan: passed with 0 matches.
- Chart of Accounts hidden currency fallback scan: passed with 0 matches.
- Trial Balance visible/hidden fallback scan: passed with 0 matches.
- General Journal visible fallback and hardcoded Arabic scan: passed with 0 matches.
- Journal Detail visible fallback and hardcoded Arabic scan: passed with 0 matches.
- Journal Form hidden-currency and visible fallback scan: passed with 0 matches.
- Opening Balances legacy fallback and hardcoded Arabic scan: passed with 0 matches.
- Fiscal Periods permission/empty-state and hardcoded Arabic scan: passed with 0 matches.
- Accounting/Tax navigation fallback scan: passed with 0 matches.
- Financial Statement Mapping generic delete confirmation scan: passed with 0 matches.
- Account Mapping generic delete confirmation and hardcoded Arabic scan: passed with 0 matches.
- Accounting landing-page visible fallback and hardcoded Arabic scan: passed with 0 matches.
- Reports Hub tax-report visible fallback and hardcoded Arabic scan: passed with 0 matches.
- VAT-to-GL visible fallback, hardcoded Arabic, and hidden USD fallback scan: passed with 0 matches.
- AR/AP Aging and AR/AP GL Reconciliation hidden EGP fallback scan: passed with 0 matches.
- Operational report mixed-currency summary EGP fallback scan: passed with 0 matches.
- Tax Period and VAT Register/Summary typed dictionary scan: passed with 0 matches.
- Tax Codes/Rates typed dictionary and unavailable fallback scan: passed with 0 matches.
- Audit Log typed dictionary and legacy fallback scan: passed with 0 matches.
- AppLayout navigation/header typed dictionary scan: passed with 0 matches.
- Accounting master-data typed dictionary and select-cast scan: passed with 0 matches.
- Accounting dense-page typed dictionary scan: passed with 0 matches.
- Journal/ledger dense-page typed dictionary scan: passed with 0 matches.
- Fixed-asset typed dictionary and canonical missing-label scan: passed with 0 matches.
- Cross-module select-value parser and receipt/payment fallback scan: passed with 0 matches.
- Sales/purchasing line-editor typed update scan: passed with 0 matches.
- Visible Pages loose-any scan: passed with 0 matches.
- Sales/purchasing/catalog canonical missing-label scan: passed with 0 matches.
- Sales invoice revision canonical missing-label scan: passed with 0 matches.
- AR/AP cash/bank explicit-currency and missing-label scan: passed with 0 matches.
- Treasury/inventory/settings explicit-currency and missing-label scan: passed with 0 matches.
- Inventory stock count/adjustment missing-label and value-delta formatting scan: passed with canonical unavailable labels and formatted currency display.
- State-changing route authorization scan: passed; all writable routes are auth-gated and either permission-protected or explicitly allowlisted.
- Journal Detail and Fixed Asset Disposal monetary display scan: passed with formatted money and canonical unavailable labels.
- Report table zero/unavailable marker scan: passed with dictionary-backed markers and 0 hardcoded dash fallback matches.
- Fixed Asset financial value display scan: passed with formatted values, restricted dictionary labels, and no raw minor/mask regressions.
- Visible Pages hardcoded EGP/USD currency literal scan: passed with 0 matches.
- VAT/Tax explicit-currency formatting scan: passed with no single-argument `formatMoney` calls in VAT/Tax pages.
- Backend report currency-default scan: passed with 0 hardcoded `EGP`/`USD` default matches in `Application/Reports` and `Http/Controllers/Reports`.
- Operational application/controller hidden currency default scan: passed with 0 hardcoded `EGP`/`USD` default matches in `Application` and `Http/Controllers`.
- Console command and seeder fixed-currency scan: passed with 0 hardcoded `EGP`/`USD` matches in `app/Console/Commands` and `database/seeders`.
- Broad visible-page money-format scan: passed with only two expected explicit-currency calls using `data.currency`.
- `php artisan migrate --force`: passed, nothing to migrate.
- `php artisan migrate:status`: passed, all migrations are Ran through Phase 14 Rentals.
- Raw controller success-flash scan: passed with 0 matches.
- `php artisan test --filter=test_financial_service_error_messages_use_translation_placeholders --stop-on-failure`: passed after Slice 68, 1 test / 433 assertions.
- `php artisan test --filter=test_branch_approval_rule_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 22 assertions.
- `php artisan test --filter=test_tax_service_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 132 assertions.
- `php artisan test --filter=test_expense_service_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 301 assertions.
- `php artisan test --filter=test_payroll_service_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 253 assertions.
- `php artisan test --filter=Phase13PayrollFoundationTest --stop-on-failure`: passed after Slice 72, 6 tests / 90 assertions.
- `php artisan test --filter=test_inventory_workflow_service_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 297 assertions.
- `php artisan test --filter=Phase10BranchWarehouseOperationsTest --stop-on-failure`: passed after Slice 73, 5 tests / 87 assertions.
- `php artisan test --filter=Phase10StockCountAdjustmentTest --stop-on-failure`: passed after Slice 73, 5 tests / 57 assertions.
- `php artisan test --filter=test_inventory_costing_service_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 68 assertions.
- `php artisan test --filter=Phase4Slice8InventoryCostingTest --stop-on-failure`: passed after Slice 74, 14 tests / 99 assertions / 1 skipped.
- `php artisan test --filter=Phase10LandedCostAllocationTest --stop-on-failure`: passed after Slice 74, 5 tests / 40 assertions.
- `php artisan test --filter=test_rental_item_and_contract_service_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 171 assertions.
- `php artisan test --filter=Phase14RentalsFoundationTest --stop-on-failure`: passed after Slice 75, 16 tests / 159 assertions.
- `php artisan test --filter=Phase14RentalBillingTest --stop-on-failure`: passed after Slice 75, 8 tests / 56 assertions.
- `php artisan test --filter=test_rental_fulfillment_service_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 90 assertions.
- `php artisan test --filter=Phase14RentalsFoundationTest --stop-on-failure`: passed after Slice 76, 16 tests / 159 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 76, 82 tests / 15306 assertions.
- `php artisan test --filter=test_rental_invoice_service_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 120 assertions.
- `php artisan test --filter=Phase14RentalBillingTest --stop-on-failure`: passed after Slice 77, 8 tests / 56 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 77, 83 tests / 15426 assertions.
- `php artisan test --filter=test_fixed_asset_service_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 413 assertions.
- `php artisan test --filter=Phase6Slice2FixedAssetRegisterTest --stop-on-failure`: passed after Slice 78, 9 tests / 71 assertions.
- `php artisan test --filter=Phase6Slice3CapitalizationTest --stop-on-failure`: passed after Slice 78, 11 tests / 65 assertions.
- `php artisan test --filter=Phase6Slice4DepreciationScheduleTest --stop-on-failure`: passed after Slice 78, 13 tests / 64 assertions.
- `php artisan test --filter=Phase6Slice5DepreciationRunTest --stop-on-failure`: passed after Slice 78, 10 tests / 44 assertions.
- `php artisan test --filter=Phase6Slice6FixedAssetDisposalTest --stop-on-failure`: passed after Slice 78, 15 tests / 60 assertions.
- `php artisan test --filter=Phase10FixedAssetMovementTest --stop-on-failure`: passed after Slice 78, 5 tests / 50 assertions.
- `php artisan test --filter=Phase6Slice7FixedAssetReportsTest --stop-on-failure`: passed after Slice 78, 6 tests / 151 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 78, 84 tests / 15839 assertions.
- `php artisan test --filter=test_sales_invoice_and_supplier_bill_service_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 389 assertions.
- `php artisan test --filter=Phase4Slice5CustomerInvoiceTest --stop-on-failure`: passed after Slice 79, 19 tests / 84 assertions.
- `php artisan test --filter=Phase4Slice6SupplierBillTest --stop-on-failure`: passed after Slice 79, 19 tests / 98 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 79, 85 tests / 16228 assertions.
- `php artisan test --filter=test_returns_and_adjustment_service_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 487 assertions.
- `php artisan test --filter=Phase4Slice10ReturnsCreditNotesTest --stop-on-failure`: passed after Slice 80, 40 tests / 237 assertions.
- `php artisan test --filter=Phase7Slice3SalesOutputVatTest --stop-on-failure`: passed after Slice 80, 5 tests / 23 assertions.
- `php artisan test --filter=Phase7Slice4PurchasingInputVatTest --stop-on-failure`: passed after Slice 80, 4 tests / 25 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 80, 86 tests / 16715 assertions.
- `php artisan test --filter=test_order_and_fulfillment_service_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 297 assertions.
- `php artisan test --filter=Phase4Slice2SalesOrderTest --stop-on-failure`: passed after Slice 81, 15 tests / 72 assertions.
- `php artisan test --filter=Phase4Slice3PurchaseOrderTest --stop-on-failure`: passed after Slice 81, 16 tests / 74 assertions.
- `php artisan test --filter=Phase4Slice4FulfillmentTest --stop-on-failure`: passed after Slice 81, 19 tests / 140 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 81, 87 tests / 17012 assertions.
- `php artisan test --filter=test_catalog_customer_supplier_service_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 120 assertions.
- `php artisan test --filter=Phase3Slice1MasterDataTest --stop-on-failure`: passed after Slice 82, 14 tests / 58 assertions.
- `php artisan test --filter=Phase4Slice1CatalogTest --stop-on-failure`: passed after Slice 82, 12 tests / 66 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 82, 88 tests / 17132 assertions.
- `php artisan test --filter=test_accounting_mapping_service_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 93 assertions.
- `php artisan test --filter=Phase5Slice1FinancialStatementMappingTest --stop-on-failure`: passed after Slice 83, 9 tests / 30 assertions.
- `php artisan test --filter=Phase5Slice3CashFlowStatementTest --stop-on-failure`: passed after Slice 83, 9 tests / 46 assertions.
- `php artisan test --filter=Phase10BranchSpecificGlMappingTest --stop-on-failure`: passed after Slice 83, 4 tests / 27 assertions.
- `php artisan test --filter=AccountTypeAndControlAccountTest --stop-on-failure`: passed after Slice 83, 13 tests / 46 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 83, 89 tests / 17225 assertions.
- `php artisan test --filter=test_bank_reconciliation_and_cheque_service_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 358 assertions.
- `php artisan test --filter=Phase3Slice5ChequeTest --stop-on-failure`: passed after Slice 84, 8 tests / 51 assertions.
- `php artisan test --filter=Phase3Slice6BankReconciliationTest --stop-on-failure`: passed after Slice 84, 11 tests / 46 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 84, 90 tests / 17583 assertions.
- `php artisan test --testsuite=Concurrency --stop-on-failure`: passed after Slice 84, 7 tests / 16 assertions.
- `php artisan accounting:cheque-concurrency-stress --workers=50`: passed after Slice 84.
- `php artisan accounting:bank-reconciliation-concurrency-stress --workers=50`: passed after Slice 84.
- `php artisan test --filter=test_landed_cost_allocation_service_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 82 assertions.
- `php artisan test --filter=Phase10LandedCostAllocationTest --stop-on-failure`: passed after Slice 85, 5 tests / 40 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 85, 91 tests / 17665 assertions.
- `php artisan test --filter=test_ar_ap_allocation_service_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 89 assertions.
- `php artisan test --filter=Phase3Slice4AllocationTest --stop-on-failure`: passed after Slice 86, 7 tests / 38 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 86, 92 tests / 17754 assertions.
- `php artisan accounting:allocation-concurrency-stress --workers=50`: passed after Slice 86.
- `php artisan test --filter=test_ar_ap_settlement_service_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 101 assertions.
- `php artisan test --filter=Phase4Slice10ReturnsCreditNotesTest --stop-on-failure`: passed after Slice 87, 40 tests / 237 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 87, 93 tests / 17855 assertions.
- `php artisan accounting:settlement-concurrency-stress --workers=50`: passed after Slice 87.
- `php artisan test --filter=test_ar_ap_receipt_payment_opening_balance_service_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 31 assertions.
- `php artisan test --filter=Phase3Slice2ArApOpeningBalanceTest --stop-on-failure`: passed after Slice 88, 14 tests / 61 assertions.
- `php artisan test --filter=Phase3Slice3ReceiptPaymentTest --stop-on-failure`: passed after Slice 88, 14 tests / 71 assertions, 2 skipped.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 88, 94 tests / 17886 assertions.
- `php artisan test --filter=test_invoice_revision_and_currency_input_error_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 29 assertions.
- `php artisan test --filter=Phase4Slice10ReturnsCreditNotesTest --stop-on-failure`: passed after Slice 89, 40 tests / 237 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 89, 95 tests / 17915 assertions.
- `rg -n "=> \\['[A-Z]" laravel\\app\\Application`: passed after Slice 89, 0 matches.
- `php artisan test --filter=test_report_export_error_messages_are_translation_backed --stop-on-failure`: passed after Slice 90, 1 test / 36 assertions.
- `php artisan test --filter=Phase3Slice8ReportsTest --stop-on-failure`: passed after Slice 90, 12 tests / 180 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 90, 96 tests / 17951 assertions.
- `rg -n "abort\\(400, '[A-Z]|RuntimeException\\('[A-Z]|=> \\['[A-Z]" laravel\\app\\Http laravel\\app\\Application laravel\\app\\Support`: passed after Slice 90, 0 matches.
- `php artisan test --filter=test_statement_report_controllers_delegate_csv_composition --stop-on-failure`: passed after Slice 91, 1 test / 22 assertions.
- `php artisan test --filter=Phase3Slice8ReportsTest --stop-on-failure`: passed after Slice 91, 12 tests / 180 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 91, 97 tests / 18025 assertions.
- `rg -n "fputcsv\\(|response\\(\\)->stream\\(|number_format\\("` across the four touched statement report controllers: passed after Slice 91, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 91.
- `npm run typecheck`: passed after Slice 91 with 0 TypeScript errors.
- `npm run build`: passed after Slice 91 with the existing Vite chunk-size warning only.
- `php artisan test --filter=test_ar_ap_and_cheque_report_controllers_delegate_csv_composition --stop-on-failure`: passed after Slice 92, 1 test / 28 assertions.
- `php artisan test --filter=Phase3Slice8ReportsTest --stop-on-failure`: passed after Slice 92, 12 tests / 180 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 92, 98 tests / 18105 assertions.
- `rg -n "fputcsv\\(|response\\(\\)->stream\\(|number_format\\("` across the five touched AR/AP and cheque report controllers: passed after Slice 92, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 92.
- `npm run typecheck`: passed after Slice 92 with 0 TypeScript errors.
- `npm run build`: passed after Slice 92 with the existing Vite chunk-size warning only.
- `php artisan test --filter=test_financial_statement_and_branch_report_controllers_delegate_csv_composition --stop-on-failure`: passed after Slice 93, 1 test / 23 assertions.
- `php artisan test --filter=Phase5Slice2FinancialStatementsTest --stop-on-failure`: passed after Slice 93, 8 tests / 54 assertions.
- `php artisan test --filter=Phase5Slice3CashFlowStatementTest --stop-on-failure`: passed after Slice 93, 9 tests / 46 assertions.
- `php artisan test --filter=Phase10GlBranchProfitabilityTest --stop-on-failure`: passed after Slice 93, 6 tests / 51 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 93, 99 tests / 18180 assertions.
- `rg -n "fputcsv\\(|response\\(\\)->stream\\(|localizedExportName\\("` across the four touched financial-statement/branch report controllers: passed after Slice 93, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 93.
- `npm run typecheck`: passed after Slice 93 with 0 TypeScript errors.
- `npm run build`: passed after Slice 93 with the existing Vite chunk-size warning only.
- `php artisan test --filter=test_report_exporters_use_the_shared_csv_stream_boundary --stop-on-failure`: passed after Slice 94, 1 test / 148 assertions.
- `php artisan test --filter=Phase7Slice5VatReportsTest --stop-on-failure`: passed after Slice 94, 9 tests / 44 assertions.
- `php artisan test --filter=Phase14RentalReportsCloseOutTest --stop-on-failure`: passed after Slice 94, 3 tests / 41 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 94, 100 tests / 18328 assertions.
- `rg -n "response\\(\\)->stream\\(" app/Application/Reports`: passed after Slice 94 with only `CsvReportResponse.php`.
- `rg -n "fopen" app/Application/Reports`: passed after Slice 94 with only `CsvReportResponse.php`.
- `rg -n "fclose" app/Application/Reports`: passed after Slice 94 with only `CsvReportResponse.php`.
- `rg -n "private function stream" app/Application/Reports`: passed after Slice 94, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 94.
- `npm run typecheck`: passed after Slice 94 with 0 TypeScript errors.
- `npm run build`: passed after Slice 94 with the existing Vite chunk-size warning only.
- `php artisan test --filter=test_financial_statement_report_period_options_are_centralized --stop-on-failure`: passed after Slice 95, 1 test / 16 assertions.
- `php artisan test --filter=Phase5Slice2FinancialStatementsTest --stop-on-failure`: passed after Slice 95, 8 tests / 54 assertions.
- `php artisan test --filter=Phase5Slice3CashFlowStatementTest --stop-on-failure`: passed after Slice 95, 9 tests / 46 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 95, 101 tests / 18374 assertions.
- `rg -n "FinancialPeriod::query" app/Http/Controllers/Reports/IncomeStatementReportController.php app/Http/Controllers/Reports/CashFlowReportController.php`: passed after Slice 95, 0 matches.
- `rg -n "with\\('fiscalYear'\\)" app/Http/Controllers/Reports/IncomeStatementReportController.php app/Http/Controllers/Reports/CashFlowReportController.php`: passed after Slice 95, 0 matches.
- `rg -n "map\\(fn \\(FinancialPeriod" app/Http/Controllers/Reports/IncomeStatementReportController.php app/Http/Controllers/Reports/CashFlowReportController.php`: passed after Slice 95, 0 matches.
- Controller length scan: passed after Slice 95; all controllers remain under 150 lines.
- `vendor/bin/pint --test`: passed after Slice 95.
- `npm run typecheck`: passed after Slice 95 with 0 TypeScript errors.
- `npm run build`: passed after Slice 95 with the existing Vite chunk-size warning only.
- JSON parse for `resources/js/locales/en.json` and `resources/js/locales/ar.json`: passed after Slice 96.
- `php artisan test --filter=test_bank_reconciliation_detail_uses_dictionary_for_missing_gl_journal_reference --stop-on-failure`: passed after Slice 96, 1 test / 20 assertions.
- `php artisan test --filter=Phase3Slice8ReportsTest --stop-on-failure`: passed after Slice 96, 12 tests / 180 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 96, 102 tests / 18394 assertions.
- `rg -n "GL-Entry" resources/js/Pages/Reports/BankReconciliationDetail.tsx resources/js/locales/en.json resources/js/locales/ar.json`: passed after Slice 96, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 96.
- `npm run typecheck`: passed after Slice 96 with 0 TypeScript errors.
- `npm run build`: passed after Slice 96 with the existing Vite chunk-size warning only.
- `php artisan test --filter=test_dashboard_uses_dictionary_for_missing_user_name --stop-on-failure`: passed after Slice 97, 1 test / 16 assertions.
- `php artisan test --filter=MigratedPagesTest --stop-on-failure`: passed after Slice 97, 2 tests / 83 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 97, 103 tests / 18410 assertions.
- `rg -n "\\|\\| 'User'" resources/js/Pages/Dashboard.tsx`: passed after Slice 97, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 97.
- `npm run typecheck`: passed after Slice 97 with 0 TypeScript errors.
- `npm run build`: passed after Slice 97 with the existing Vite chunk-size warning only.
- `php artisan test --filter=test_app_layout_navigation_uses_typed_dictionaries_without_visible_fallbacks --stop-on-failure`: passed after Slice 98, 1 test / 99 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 98, 103 tests / 18413 assertions.
- `rg -n "locale === 'ar' \\? 'EN' : 'ع'" resources/js/Components/AppLayout.tsx`: passed after Slice 98, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 98.
- `npm run typecheck`: passed after Slice 98 with 0 TypeScript errors.
- `npm run build`: passed after Slice 98 with the existing Vite chunk-size warning only.
- `php -r` JSON parse for `resources/js/locales/en.json` and `resources/js/locales/ar.json`: passed after Slice 99.
- `php artisan test --filter=test_bank_reconciliation_finalize_confirmation_uses_canonical_dictionary_key --stop-on-failure`: passed after Slice 99, 1 test / 4 assertions.
- `php artisan test --filter=Phase3Slice6BankReconciliationTest --stop-on-failure`: passed after Slice 99, 11 tests / 46 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 99, 104 tests / 18417 assertions.
- `rg -n "bankReconciliationsShow\\.areYouSureYouWantTo_2|confirmFinalizeReconciliation" resources/js/Pages/BankReconciliations/Show.tsx resources/js/locales tests/Feature/Phase15ProductHardeningTest.php`: passed after Slice 99 with page usage on the canonical key and no page usage of the generated duplicate key.
- `vendor/bin/pint --test`: passed after Slice 99.
- `npm run typecheck`: passed after Slice 99 with 0 TypeScript errors.
- `npm run build`: passed after Slice 99 with the existing Vite chunk-size warning only.
- `php -r` JSON parse for `resources/js/locales/ar.json`: passed after Slice 100.
- `php artisan test --filter=test_catalog_product_modal_select_labels_are_cleanly_localized_in_arabic --stop-on-failure`: passed after Slice 100, 1 test / 15 assertions.
- `php artisan test --filter=Phase4Slice1CatalogTest --stop-on-failure`: passed after Slice 100, 12 tests / 66 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 100, 105 tests / 18432 assertions.
- `rg -n "مخزني \\(Stock\\)|خدمة \\(Service\\)|غير مخزني \\(Non-Stock\\)|نشط \\(Active\\)|غير نشط \\(Inactive\\)" resources/js/locales/ar.json`: passed after Slice 100, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 100.
- `npm run typecheck`: passed after Slice 100 with 0 TypeScript errors.
- `npm run build`: passed after Slice 100 with the existing Vite chunk-size warning only.
- `php -r` JSON parse for `resources/js/locales/en.json` and `resources/js/locales/ar.json`: passed after Slice 101.
- `php artisan test --filter=test_catalog_category_and_uom_form_labels_and_placeholders_are_dictionary_backed --stop-on-failure`: passed after Slice 101, 1 test / 15 assertions.
- `php artisan test --filter=Phase4Slice1CatalogTest --stop-on-failure`: passed after Slice 101, 12 tests / 66 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 101, 106 tests / 18447 assertions.
- `rg -n "e.g. RAW|e.g. PCS|e.g. pc|الرمز \\(CODE\\)|الرمز المختصر \\(Symbol\\)" resources/js/Pages/Catalog resources/js/locales/ar.json`: passed after Slice 101, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 101.
- `npm run typecheck`: passed after Slice 101 with 0 TypeScript errors.
- `npm run build`: passed after Slice 101 with the existing Vite chunk-size warning only.
- `php -l app/Application/Dashboard/DashboardPageData.php` and `php -l app/Http/Controllers/DashboardController.php`: passed after Slice 102.
- `php artisan test --filter=test_dashboard_controller_delegates_page_data_to_application_service --stop-on-failure`: passed after Slice 102, 1 test / 11 assertions.
- `php artisan test --filter=MigratedPagesTest --stop-on-failure`: passed after Slice 102, 2 tests / 83 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 102, 107 tests / 18472 assertions.
- `rg -n "DB::table|use App\\\\Models\\\\Account|use App\\\\Models\\\\Currency|use App\\\\Models\\\\Customer|use App\\\\Models\\\\JournalEntry|use App\\\\Models\\\\LedgerEntry|use App\\\\Models\\\\Supplier" app/Http/Controllers/DashboardController.php`: passed after Slice 102, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 102 after formatting the new page-data service.
- `npm run typecheck`: passed after Slice 102 with 0 TypeScript errors.
- `npm run build`: passed after Slice 102 with the existing Vite chunk-size warning only.
- `php -l app/Application/MasterData/CustomerPageData.php`, `php -l app/Application/MasterData/SupplierPageData.php`, `php -l app/Http/Controllers/CustomerController.php`, and `php -l app/Http/Controllers/SupplierController.php`: passed after Slice 103.
- `php artisan test --filter=test_customer_and_supplier_controllers_delegate_index_page_data_to_services --stop-on-failure`: passed after Slice 103, 1 test / 14 assertions.
- `php artisan test --filter=Phase3Slice7UiTest --stop-on-failure`: passed after Slice 103, 13 tests / 112 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 103, 108 tests / 18514 assertions.
- `rg -n "Customer::query|Supplier::query|Currency::query|use App\\\\Models\\\\Currency|use App\\\\Models\\\\Customer|use App\\\\Models\\\\Supplier" app/Http/Controllers/CustomerController.php app/Http/Controllers/SupplierController.php`: passed after Slice 103, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 103.
- `npm run typecheck`: passed after Slice 103 with 0 TypeScript errors.
- `npm run build`: passed after Slice 103 with the existing Vite chunk-size warning only.
- `php -l app/Application/MasterData/CashAccountPageData.php`, `php -l app/Application/MasterData/BankAccountPageData.php`, `php -l app/Http/Controllers/CashAccountController.php`, and `php -l app/Http/Controllers/BankAccountController.php`: passed after Slice 104.
- `php artisan test --filter=test_cash_and_bank_account_controllers_delegate_index_page_data_to_services --stop-on-failure`: passed after Slice 104, 1 test / 22 assertions.
- `php artisan test --filter=Phase3Slice7UiTest --stop-on-failure`: passed after Slice 104, 13 tests / 112 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 104, 109 tests / 18564 assertions.
- `rg -n "CashAccount::query|BankAccount::query|Account::query|Branch::query|Currency::query|use App\\\\Models\\\\Account|use App\\\\Models\\\\Branch|use App\\\\Models\\\\Currency|use App\\\\Models\\\\CashAccount|use App\\\\Models\\\\BankAccount" app/Http/Controllers/CashAccountController.php app/Http/Controllers/BankAccountController.php`: passed after Slice 104, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 104.
- `npm run typecheck`: passed after Slice 104 with 0 TypeScript errors.
- `npm run build`: passed after Slice 104 with the existing Vite chunk-size warning only.
- `php -l app/Http/Controllers/Accounting/TrialBalanceController.php`: passed after Slice 105.
- `php artisan test --filter=test_trial_balance_controller_uses_shared_financial_period_report_options --stop-on-failure`: passed after Slice 105, 1 test / 6 assertions.
- `php artisan test --filter=AccountingCoreTest --stop-on-failure`: passed after Slice 105, 19 tests / 79 assertions.
- `php artisan test --filter=Phase5Slice2FinancialStatementsTest --stop-on-failure`: passed after Slice 105, 8 tests / 54 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 105, 110 tests / 18570 assertions.
- `rg -n "FinancialPeriod::query\(|with\('fiscalYear'\)|use App\\\\Models\\\\FinancialPeriod" app/Http/Controllers/Accounting/TrialBalanceController.php`: passed after Slice 105, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 105.
- `npm run typecheck`: passed after Slice 105 with 0 TypeScript errors.
- `npm run build`: passed after Slice 105 with the existing Vite chunk-size warning only.
- `php -l app/Application/Accounting/CustomerOpeningBalancePageData.php`, `php -l app/Application/Accounting/SupplierOpeningBalancePageData.php`, `php -l app/Http/Controllers/CustomerOpeningBalanceController.php`, and `php -l app/Http/Controllers/SupplierOpeningBalanceController.php`: passed after Slice 106.
- `php artisan test --filter=test_ar_ap_opening_balance_controllers_delegate_index_page_data_to_services --stop-on-failure`: passed after Slice 106, 1 test / 26 assertions.
- `php artisan test --filter=Phase3Slice2ArApOpeningBalanceTest --stop-on-failure`: passed after Slice 106, 14 tests / 61 assertions.
- `php artisan test --filter=Phase3Slice7UiTest --stop-on-failure`: passed after Slice 106, 13 tests / 112 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 106, 111 tests / 18624 assertions.
- `rg -n "CustomerOpeningBalance::query|SupplierOpeningBalance::query|Customer::query|Supplier::query|Currency::query|FinancialPeriod::query|FiscalYear::query|use App\\\\Models\\\\CustomerOpeningBalance|use App\\\\Models\\\\SupplierOpeningBalance|use App\\\\Models\\\\Currency|use App\\\\Models\\\\FinancialPeriod|use App\\\\Models\\\\FiscalYear" app/Http/Controllers/CustomerOpeningBalanceController.php app/Http/Controllers/SupplierOpeningBalanceController.php`: passed after Slice 106, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 106.
- `npm run typecheck`: passed after Slice 106 with 0 TypeScript errors.
- `npm run build`: passed after Slice 106 with the existing Vite chunk-size warning only.
- `php -l app/Application/Accounting/CustomerReceiptPageData.php`, `php -l app/Application/Accounting/SupplierPaymentPageData.php`, `php -l app/Http/Controllers/CustomerReceiptController.php`, and `php -l app/Http/Controllers/SupplierPaymentController.php`: passed after Slice 107.
- `php artisan test --filter=test_ar_ap_receipt_payment_controllers_delegate_index_page_data_to_services --stop-on-failure`: passed after Slice 107, 1 test / 34 assertions.
- `php artisan test --filter=Phase3Slice3ReceiptPaymentTest --stop-on-failure`: passed after Slice 107, 12 passed / 2 skipped / 71 assertions.
- `php artisan test --filter=Phase3Slice7UiTest --stop-on-failure`: passed after Slice 107, 13 tests / 112 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 107, 112 tests / 18686 assertions.
- `rg -n "CustomerReceipt::query|SupplierPayment::query|Customer::query|Supplier::query|CashAccount::query|BankAccount::query|Currency::query|FinancialPeriod::query|FiscalYear::query|use App\\\\Models\\\\CustomerReceipt|use App\\\\Models\\\\SupplierPayment|use App\\\\Models\\\\CashAccount|use App\\\\Models\\\\BankAccount|use App\\\\Models\\\\Currency|use App\\\\Models\\\\FinancialPeriod|use App\\\\Models\\\\FiscalYear" app/Http/Controllers/CustomerReceiptController.php app/Http/Controllers/SupplierPaymentController.php`: passed after Slice 107, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 107.
- `npm run typecheck`: passed after Slice 107 with 0 TypeScript errors.
- `npm run build`: passed after Slice 107 with the existing Vite chunk-size warning only.
- `php -l app/Application/Accounting/IncomingChequePageData.php`, `php -l app/Application/Accounting/OutgoingChequePageData.php`, `php -l app/Http/Controllers/IncomingChequeController.php`, and `php -l app/Http/Controllers/OutgoingChequeController.php`: passed after Slice 108.
- `php artisan test --filter=test_cheque_controllers_delegate_index_page_data_to_services --stop-on-failure`: passed after Slice 108, 1 test / 30 assertions.
- `php artisan test --filter=Phase3Slice5ChequeTest --stop-on-failure`: passed after Slice 108, 8 tests / 51 assertions.
- `php artisan test --filter=Phase3Slice7UiTest --stop-on-failure`: passed after Slice 108, 13 tests / 112 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 108, 113 tests / 18744 assertions.
- `rg -n "IncomingCheque::query|OutgoingCheque::query|Customer::query|Supplier::query|BankAccount::query|Currency::query|FinancialPeriod::query|FiscalYear::query|use App\\\\Models\\\\IncomingCheque|use App\\\\Models\\\\OutgoingCheque|use App\\\\Models\\\\BankAccount|use App\\\\Models\\\\Currency|use App\\\\Models\\\\FinancialPeriod|use App\\\\Models\\\\FiscalYear" app/Http/Controllers/IncomingChequeController.php app/Http/Controllers/OutgoingChequeController.php`: passed after Slice 108, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 108.
- `npm run typecheck`: passed after Slice 108 with 0 TypeScript errors.
- `npm run build`: passed after Slice 108 with the existing Vite chunk-size warning only.
- `php -l app/Application/Accounting/ReceivableAllocationPageData.php`, `php -l app/Application/Accounting/PayableAllocationPageData.php`, `php -l app/Http/Controllers/ReceivableAllocationController.php`, and `php -l app/Http/Controllers/PayableAllocationController.php`: passed after Slice 109.
- `php artisan test --filter=test_allocation_controllers_delegate_index_page_data_to_services --stop-on-failure`: passed after Slice 109, 1 test / 22 assertions.
- `php artisan test --filter=Phase3Slice4AllocationTest --stop-on-failure`: passed after Slice 109, 7 tests / 38 assertions.
- `php artisan test --filter=Phase3Slice7UiTest --stop-on-failure`: passed after Slice 109, 13 tests / 112 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 109, 114 tests / 18794 assertions.
- `rg -n "CustomerReceipt::query|ReceivableAllocation::query|ReceivableEntry::query|Customer::query|SupplierPayment::query|PayableAllocation::query|PayableEntry::query|Supplier::query|use App\\\\Models\\\\CustomerReceipt|use App\\\\Models\\\\ReceivableAllocation|use App\\\\Models\\\\ReceivableEntry|use App\\\\Models\\\\SupplierPayment|use App\\\\Models\\\\PayableAllocation|use App\\\\Models\\\\PayableEntry" app/Http/Controllers/ReceivableAllocationController.php app/Http/Controllers/PayableAllocationController.php`: passed after Slice 109, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 109.
- `npm run typecheck`: passed after Slice 109 with 0 TypeScript errors.
- `npm run build`: passed after Slice 109 with the existing Vite chunk-size warning only.
- `php -l app/Application/Accounting/ReceivableEntrySettlementPageData.php`, `php -l app/Application/Accounting/PayableEntrySettlementPageData.php`, `php -l app/Http/Controllers/ReceivableEntrySettlementController.php`, and `php -l app/Http/Controllers/PayableEntrySettlementController.php`: passed after Slice 110.
- `php artisan test --filter=test_entry_settlement_controllers_delegate_index_page_data_to_services --stop-on-failure`: passed after Slice 110, 1 test / 26 assertions.
- Targeted `Phase4Slice10ReturnsCreditNotesTest` settlement methods: passed after Slice 110, 5 tests / 23 assertions.
- `php artisan test --filter=Phase3Slice7UiTest --stop-on-failure`: passed after Slice 110, 13 tests / 112 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 110, 115 tests / 18848 assertions.
- `rg -n "ReceivableAllocation::query|ReceivableEntry::query|ReceivableEntrySettlement::query|Customer::query|PayableAllocation::query|PayableEntry::query|PayableEntrySettlement::query|Supplier::query|whereRaw|array_merge|use App\\\\Models\\\\ReceivableAllocation|use App\\\\Models\\\\ReceivableEntry|use App\\\\Models\\\\ReceivableEntrySettlement|use App\\\\Models\\\\PayableAllocation|use App\\\\Models\\\\PayableEntry|use App\\\\Models\\\\PayableEntrySettlement" app/Http/Controllers/ReceivableEntrySettlementController.php app/Http/Controllers/PayableEntrySettlementController.php`: passed after Slice 110, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 110.
- `npm run typecheck`: passed after Slice 110 with 0 TypeScript errors.
- `npm run build`: passed after Slice 110 with the existing Vite chunk-size warning only.
- `php -l app/Application/Sales/SalesOrderPageData.php`, `php -l app/Application/Purchasing/PurchaseOrderPageData.php`, `php -l app/Http/Controllers/SalesOrderController.php`, and `php -l app/Http/Controllers/PurchaseOrderController.php`: passed after Slice 111.
- `php artisan test --filter=test_sales_and_purchase_order_controllers_delegate_index_page_data_to_services --stop-on-failure`: passed after Slice 111, 1 test / 24 assertions.
- `php artisan test --filter=Phase4Slice2SalesOrderTest --stop-on-failure`: passed after Slice 111, 15 tests / 72 assertions.
- `php artisan test --filter=Phase4Slice3PurchaseOrderTest --stop-on-failure`: passed after Slice 111, 16 tests / 74 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 111, 116 tests / 18900 assertions.
- `rg -n "SalesOrder::query|PurchaseOrder::query|Customer::query|Supplier::query|Currency::query|Product::query|whereHas|use App\\\\Models\\\\SalesOrder|use App\\\\Models\\\\PurchaseOrder|use App\\\\Models\\\\Customer|use App\\\\Models\\\\Supplier|use App\\\\Models\\\\Currency|use App\\\\Models\\\\Product" app/Http/Controllers/SalesOrderController.php app/Http/Controllers/PurchaseOrderController.php`: passed after Slice 111, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 111.
- `npm run typecheck`: passed after Slice 111 with 0 TypeScript errors.
- `npm run build`: passed after Slice 111 with the existing Vite chunk-size warning only.
- `php -l app/Application/Sales/DeliveryNotePageData.php`, `php -l app/Application/Purchasing/GoodsReceiptPageData.php`, `php -l app/Http/Controllers/DeliveryNoteController.php`, and `php -l app/Http/Controllers/GoodsReceiptController.php`: passed after Slice 112.
- `php artisan test --filter=test_delivery_note_and_goods_receipt_controllers_delegate_index_page_data_to_services --stop-on-failure`: passed after Slice 112, 1 test / 20 assertions.
- `php artisan test --filter=Phase4Slice4FulfillmentTest --stop-on-failure`: passed after Slice 112, 19 tests / 140 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 112, 117 tests / 18948 assertions.
- `rg -n "DeliveryNote::query|GoodsReceipt::query|SalesOrder::query|PurchaseOrder::query|Warehouse::query|whereHas|use App\\\\Models\\\\DeliveryNote|use App\\\\Models\\\\GoodsReceipt|use App\\\\Models\\\\SalesOrder|use App\\\\Models\\\\PurchaseOrder|use App\\\\Models\\\\Warehouse" app/Http/Controllers/DeliveryNoteController.php app/Http/Controllers/GoodsReceiptController.php`: passed after Slice 112, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 112.
- `npm run typecheck`: passed after Slice 112 with 0 TypeScript errors.
- `npm run build`: passed after Slice 112 with the existing Vite chunk-size warning only.
- `php -l app/Application/Sales/CustomerInvoiceRevisionPageData.php` and `php -l app/Http/Controllers/CustomerInvoiceRevisionController.php`: passed after Slice 113.
- `php artisan test --filter=test_customer_invoice_revision_controller_delegates_page_data_to_service --stop-on-failure`: passed after Slice 113, 1 test / 9 assertions.
- `php artisan test --filter=Phase4Slice10ReturnsCreditNotesTest --stop-on-failure`: passed after Slice 113, 40 tests / 237 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 113, 118 tests / 18971 assertions.
- `rg -n "CustomerInvoiceRevision::query|whereHas|json_decode|snapshot_json|use App\\\\Models\\\\CustomerInvoiceRevision" app/Http/Controllers/CustomerInvoiceRevisionController.php`: passed after Slice 113, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 113.
- `npm run typecheck`: passed after Slice 113 with 0 TypeScript errors.
- `npm run build`: passed after Slice 113 with the existing Vite chunk-size warning only.
- `php -l app/Application/Accounting/AccountingAccountMappingPageData.php` and `php -l app/Http/Controllers/AccountingAccountMappingController.php`: passed after Slice 114.
- `php artisan test --filter=test_accounting_account_mapping_controller_delegates_page_data_to_service --stop-on-failure`: passed after Slice 114, 1 test / 11 assertions.
- `php artisan test --filter=Phase10BranchSpecificGlMappingTest --stop-on-failure`: passed after Slice 114, 4 tests / 27 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 114, 119 tests / 18996 assertions.
- `rg -n "Account::query|AccountingAccountMapping::query|Branch::query|orderBy|values\\(|use App\\\\Models\\\\Account|use App\\\\Models\\\\AccountingAccountMapping|use App\\\\Models\\\\Branch" app/Http/Controllers/AccountingAccountMappingController.php`: passed after Slice 114, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 114.
- `npm run typecheck`: passed after Slice 114 with 0 TypeScript errors.
- `npm run build`: passed after Slice 114 with the existing Vite chunk-size warning only.
- `php -l app/Application/Accounting/AccountingOverviewPageData.php` and `php -l app/Http/Controllers/Accounting/AccountingOverviewController.php`: passed after Slice 115.
- `php artisan test --filter=test_accounting_overview_controller_delegates_page_data_to_service --stop-on-failure`: passed after Slice 115, 1 test / 11 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 115, 120 tests / 19021 assertions.
- `rg -n "Account::query|FiscalYear::query|JournalEntry::query|take\\(5\\)|'counts' =>|use App\\\\Models\\\\Account|use App\\\\Models\\\\FiscalYear|use App\\\\Models\\\\JournalEntry" app/Http/Controllers/Accounting/AccountingOverviewController.php`: passed after Slice 115, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 115.
- `npm run typecheck`: passed after Slice 115 with 0 TypeScript errors.
- `npm run build`: passed after Slice 115 with the existing Vite chunk-size warning only.
- `php -l app/Application/Accounting/AccountCategoryPageData.php`, `php -l app/Application/Accounting/AccountTypePageData.php`, `php -l app/Http/Controllers/Accounting/AccountCategoryController.php`, and `php -l app/Http/Controllers/Accounting/AccountTypeController.php`: passed after Slice 116.
- `php artisan test --filter=test_account_category_and_type_controllers_delegate_page_data_to_services --stop-on-failure`: passed after Slice 116, 1 test / 18 assertions.
- `php artisan test --filter=AccountCategoryTest --stop-on-failure`: passed after Slice 116, 11 tests / 53 assertions.
- `php artisan test --filter=AccountTypeAndControlAccountTest --stop-on-failure`: passed after Slice 116, 13 tests / 46 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 116, 121 tests / 19067 assertions.
- `rg -n "AccountCategory::query|AccountType::query|withCount|orderBy\\('sort_order'\\)|orderBy\\('code'\\)|where\\('is_active', true\\)" app/Http/Controllers/Accounting/AccountCategoryController.php app/Http/Controllers/Accounting/AccountTypeController.php`: passed after Slice 116, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 116.
- `npm run typecheck`: passed after Slice 116 with 0 TypeScript errors.
- `npm run build`: passed after Slice 116 with the existing Vite chunk-size warning only.
- `php -l app/Application/Accounting/JournalPageData.php`, `php -l app/Application/Accounting/OpeningBalancePageData.php`, `php -l app/Http/Controllers/Accounting/JournalController.php`, and `php -l app/Http/Controllers/Accounting/OpeningBalanceController.php`: passed after Slice 117.
- `php artisan test --filter=test_journal_and_opening_balance_controllers_delegate_page_data_to_services --stop-on-failure`: passed after Slice 117, 1 test / 25 assertions.
- `php artisan test --filter=AccountingCoreTest --stop-on-failure`: passed after Slice 117, 19 tests / 79 assertions.
- `php artisan test --filter=Phase3Slice7UiTest --stop-on-failure`: passed after Slice 117, 13 tests / 112 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 117, 122 tests / 19120 assertions.
- `rg -n 'Account::query|Branch::query|Currency::query|FinancialPeriod::query|FiscalYear::query|OpeningBalance::query|GeneralLedgerService|keyBy\\(''account_id''\\)|\\$journalEntry->load' app/Http/Controllers/Accounting/JournalController.php app/Http/Controllers/Accounting/OpeningBalanceController.php`: passed after Slice 117, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 117.
- `npm run typecheck`: passed after Slice 117 with 0 TypeScript errors.
- `npm run build`: passed after Slice 117 with the existing Vite chunk-size warning only.
- `php -l app/Application/Accounting/ChartOfAccountsPageData.php`, `php -l app/Application/Accounting/CurrencyPageData.php`, `php -l app/Application/Accounting/ExchangeRatePageData.php`, `php -l app/Application/Accounting/FinancialPeriodPageData.php`, and the four refactored Accounting controllers: passed after Slice 118.
- `php artisan test --filter=test_remaining_accounting_master_data_controllers_delegate_page_data_to_services --stop-on-failure`: passed after Slice 118, 1 test / 34 assertions.
- `php artisan test --filter=AccountingCoreTest --stop-on-failure`: passed after Slice 118, 19 tests / 79 assertions.
- `php artisan test --filter=MigratedPagesTest --stop-on-failure`: passed after Slice 118, 2 tests / 83 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 118, 123 tests / 19210 assertions.
- `rg -n "Currency::query|ExchangeRate::query|Company::query|Account::query|AccountGroup::query|AccountType::query|FiscalYear::query|withCount|whereNull\\('parent_id'\\)|with\\('periods'\\)" app/Http/Controllers/Accounting/CurrencyController.php app/Http/Controllers/Accounting/ExchangeRateController.php app/Http/Controllers/Accounting/ChartOfAccountsController.php app/Http/Controllers/Accounting/FinancialPeriodController.php`: passed after Slice 118, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 118.
- `npm run typecheck`: passed after Slice 118 with 0 TypeScript errors.
- `npm run build`: passed after Slice 118 with the existing Vite chunk-size warning only.
- `php -l app/Application/Catalog/ProductCategoryPageData.php`, `php -l app/Application/Catalog/ProductPageData.php`, `php -l app/Application/Catalog/UnitOfMeasurePageData.php`, and the three refactored Catalog controllers: passed after Slice 119.
- `php artisan test --filter=test_catalog_controllers_delegate_index_page_data_to_services --stop-on-failure`: passed after Slice 119, 1 test / 31 assertions.
- `php artisan test --filter=Phase4Slice1CatalogTest --stop-on-failure`: passed after Slice 119, 12 tests / 66 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 119, 124 tests / 19283 assertions.
- `rg -n "Product::query|ProductCategory::query|UnitOfMeasure::query|where\\(function|paginate\\(15\\)|withQueryString|ALLOWED_TYPES|ALLOWED_STATUSES" app/Http/Controllers/Catalog/ProductController.php app/Http/Controllers/Catalog/ProductCategoryController.php app/Http/Controllers/Catalog/UnitOfMeasureController.php`: passed after Slice 119, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 119.
- `npm run typecheck`: passed after Slice 119 with 0 TypeScript errors.
- `npm run build`: passed after Slice 119 with the existing Vite chunk-size warning only.
- `php -l app/Application/Expenses/ExpenseCategoryPageData.php`, `php -l app/Application/Expenses/ExpensePageData.php`, `php -l app/Application/Expenses/PrepaidSchedulePageData.php`, `php -l app/Application/Expenses/AccrualSchedulePageData.php`, and the four refactored Expense controllers: passed after Slice 120.
- `php artisan test --filter=test_expense_prepaid_and_accrual_controllers_delegate_index_page_data_to_services --stop-on-failure`: passed after Slice 120, 1 test / 66 assertions.
- `php artisan test --filter=Phase11ExpenseManagementTest --stop-on-failure`: passed after Slice 120, 8 tests / 60 assertions.
- `php artisan test --filter=Phase12PrepaidAccruedExpenseTest --stop-on-failure`: passed after Slice 120, 6 tests / 74 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 120, 125 tests / 19405 assertions.
- `rg -n "Expense::query|ExpenseCategory::query|PrepaidSchedule::query|AccrualSchedule::query|Account::query|TaxCode::query|Branch::query|Currency::query|CashAccount::query|BankAccount::query|Supplier::query|withQueryString|paginate\\(15\\)|expenseAccountOptions|prepaidAssetAccounts|liabilityAccounts" app/Http/Controllers/ExpenseController.php app/Http/Controllers/ExpenseCategoryController.php app/Http/Controllers/PrepaidScheduleController.php app/Http/Controllers/AccrualScheduleController.php`: passed after Slice 120, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 120.
- `npm run typecheck`: passed after Slice 120 with 0 TypeScript errors.
- `npm run build`: passed after Slice 120 with the existing Vite chunk-size warning only.
- `php -l app/Application/FixedAssets/FixedAssetLocationPageData.php`, `php -l app/Application/FixedAssets/FixedAssetDisposalPageData.php`, `php -l app/Application/FixedAssets/FixedAssetPageData.php`, and the three refactored Fixed Assets controllers: passed after Slice 121.
- `php artisan test --filter=test_fixed_asset_location_and_disposal_controllers_delegate_page_data_to_services --stop-on-failure`: passed after Slice 121, 1 test / 25 assertions.
- `php artisan test --filter=Phase10FixedAssetMovementTest --stop-on-failure`: passed after Slice 121, 5 tests / 50 assertions.
- `php artisan test --filter=Phase6Slice6FixedAssetDisposalTest --stop-on-failure`: passed after Slice 121, 15 tests / 60 assertions.
- `php artisan test --filter=Phase6Slice2FixedAssetRegisterTest --stop-on-failure`: passed after Slice 121, 9 tests / 71 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 121, 126 tests / 19458 assertions.
- `rg -n 'FixedAsset::query|FixedAssetDisposal::query|Branch::query|orWhereHas|latest\\(''created_at''\\)|paginate\\(15\\)|listLocations\\(\\$filters\\)|use App\\\\Models\\\\FixedAsset|use App\\\\Models\\\\FixedAssetDisposal|use App\\\\Models\\\\Branch' app/Http/Controllers/FixedAssets/FixedAssetController.php app/Http/Controllers/FixedAssets/FixedAssetLocationController.php app/Http/Controllers/FixedAssets/FixedAssetDisposalController.php`: passed after Slice 121, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 121.
- `npm run typecheck`: passed after Slice 121 with 0 TypeScript errors.
- `npm run build`: passed after Slice 121 with the existing Vite chunk-size warning only.
- `php -l app/Application/Payroll/PayrollEmployeePageData.php`, `php -l app/Application/Payroll/PayrollComponentPageData.php`, `php -l app/Application/Payroll/PayrollRunPageData.php`, and the three refactored Payroll controllers: passed after Slice 122.
- `php artisan test --filter=test_payroll_controllers_delegate_index_page_data_to_services --stop-on-failure`: passed after Slice 122, 1 test / 36 assertions.
- `php artisan test --filter=Phase13PayrollFoundationTest --stop-on-failure`: passed after Slice 122, 6 tests / 90 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 122, 127 tests / 19536 assertions.
- `rg -n "Employee::query|PayrollComponent::query|PayrollRun::query|PayrollPeriod::query|Account::query|Branch::query|Currency::query|withQueryString|paginate\\(15\\)|paginate\\(20\\)|paginate\\(10\\)|use App\\\\Models\\\\Employee|use App\\\\Models\\\\PayrollComponent|use App\\\\Models\\\\PayrollRun|use App\\\\Models\\\\PayrollPeriod|use App\\\\Models\\\\Account|use App\\\\Models\\\\Branch|use App\\\\Models\\\\Currency" app/Http/Controllers/PayrollEmployeeController.php app/Http/Controllers/PayrollComponentController.php app/Http/Controllers/PayrollRunController.php`: passed after Slice 122, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 122.
- `npm run typecheck`: passed after Slice 122 with 0 TypeScript errors.
- `npm run build`: passed after Slice 122 with the existing Vite chunk-size warning only.
- `php -l app/Application/Rentals/RentableItemPageData.php`, `php -l app/Application/Rentals/RentalContractPageData.php`, `php -l app/Application/Rentals/RentalHandoverPageData.php`, `php -l app/Application/Rentals/RentalReturnPageData.php`, and the four refactored Rental controllers: passed after Slice 123.
- `php artisan test --filter=test_rental_operational_controllers_delegate_index_page_data_to_services --stop-on-failure`: passed after Slice 123, 1 test / 53 assertions.
- `php artisan test --filter=Phase14RentalsFoundationTest --stop-on-failure`: passed after Slice 123, 16 tests / 159 assertions.
- `php artisan test --filter=Phase14RentalBillingTest --stop-on-failure`: passed after Slice 123, 8 tests / 56 assertions.
- `php artisan test --filter=Phase14RentalReportsCloseOutTest --stop-on-failure`: passed after Slice 123, 3 tests / 41 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 123, 128 tests / 19645 assertions.
- `rg -n "RentableItem::query|RentalContract::query|RentalHandover::query|RentalReturn::query|Branch::query|Warehouse::query|Product::query|FixedAsset::query|Currency::query|Customer::query|orWhereHas|withQueryString|paginate\\(15\\)|use App\\\\Models\\\\RentableItem|use App\\\\Models\\\\RentalContract|use App\\\\Models\\\\RentalHandover|use App\\\\Models\\\\RentalReturn|use App\\\\Models\\\\Branch|use App\\\\Models\\\\Warehouse|use App\\\\Models\\\\Product|use App\\\\Models\\\\FixedAsset|use App\\\\Models\\\\Currency|use App\\\\Models\\\\Customer" app/Http/Controllers/RentableItemController.php app/Http/Controllers/RentalContractController.php app/Http/Controllers/RentalHandoverController.php app/Http/Controllers/RentalReturnController.php`: passed after Slice 123, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 123.
- `npm run typecheck`: passed after Slice 123 with 0 TypeScript errors.
- `npm run build`: passed after Slice 123 with the existing Vite chunk-size warning only.
- `php -l app/Application/Inventory/WarehousePageData.php`, `php -l app/Application/Inventory/StockBalancePageData.php`, `php -l app/Application/Inventory/StockTransferPageData.php`, `php -l app/Application/Inventory/StockCountPageData.php`, `php -l app/Application/Inventory/StockAdjustmentPageData.php`, and the five refactored Inventory/Warehouse controllers: passed after Slice 124.
- `php artisan test --filter=test_inventory_and_warehouse_controllers_delegate_index_page_data_to_services --stop-on-failure`: passed after Slice 124, 1 test / 44 assertions.
- `php artisan test --filter=Phase10BranchWarehouseOperationsTest --stop-on-failure`: passed after Slice 124, 5 tests / 87 assertions.
- `php artisan test --filter=Phase10StockCountAdjustmentTest --stop-on-failure`: passed after Slice 124, 5 tests / 57 assertions.
- `php artisan test --filter=Phase4Slice8InventoryCostingTest --stop-on-failure`: passed after Slice 124, 14 tests / 99 assertions / 1 skipped.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 124, 129 tests / 19759 assertions.
- `rg -n "::query\\(|DB::table\\(|paginate\\(|withQueryString|InventoryPageOptions" app/Http/Controllers/WarehouseController.php app/Http/Controllers/StockBalanceController.php app/Http/Controllers/StockTransferController.php app/Http/Controllers/StockCountController.php app/Http/Controllers/StockAdjustmentController.php`: passed after Slice 124, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 124.
- `npm run typecheck`: passed after Slice 124 with 0 TypeScript errors.
- `npm run build`: passed after Slice 124 with the existing Vite chunk-size warning only.
- `php -l app/Application/Purchasing/LandedCostAllocationPageData.php`, `php -l app/Application/Accounting/TreasuryTransferPageData.php`, and the two refactored controllers: passed after Slice 125.
- `php artisan test --filter=test_landed_cost_and_treasury_transfer_controllers_delegate_index_page_data_to_services --stop-on-failure`: passed after Slice 125, 1 test / 33 assertions.
- `php artisan test --filter=Phase10LandedCostAllocationTest --stop-on-failure`: passed after Slice 125, 5 tests / 40 assertions.
- `php artisan test --filter=Phase10TreasuryTransferTest --stop-on-failure`: passed after Slice 125, 4 tests / 34 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 125, 130 tests / 19820 assertions.
- `rg -n "::query\\(|DB::table\\(|paginate\\(|withQueryString|orWhereHas|GoodsReceipt::query|LandedCostAllocation::query|Supplier::query|TreasuryTransfer::query|CashAccount::query|BankAccount::query|FiscalYear::query|FinancialPeriod::query" app/Http/Controllers/LandedCostAllocationController.php app/Http/Controllers/TreasuryTransferController.php`: passed after Slice 125, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 125.
- `npm run typecheck`: passed after Slice 125 with 0 TypeScript errors.
- `npm run build`: passed after Slice 125 with the existing Vite chunk-size warning only.
- `php -l app/Application/Taxes/TaxCodePageData.php`, `php -l app/Application/Taxes/TaxRatePageData.php`, `php -l app/Application/Taxes/TaxPeriodPageData.php`, and the three refactored tax controllers: passed after Slice 126.
- `php artisan test --filter=test_tax_controllers_delegate_page_data_to_services --stop-on-failure`: passed after Slice 126, 1 test / 29 assertions.
- `php artisan test --filter=test_vat_and_tax_pages_use_explicit_currency_instead_of_format_money_defaults --stop-on-failure`: passed after Slice 126, 1 test / 47 assertions.
- `php artisan test --filter=Phase7Slice2TaxFoundationTest --stop-on-failure`: passed after Slice 126, 7 tests / 38 assertions.
- `php artisan test --filter=Phase7Slice5VatReportsTest --stop-on-failure`: passed after Slice 126, 9 tests / 44 assertions.
- `php artisan test --filter=Phase7Slice6TaxFilingTest --stop-on-failure`: passed after Slice 126, 9 tests / 18 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 126, 131 tests / 19894 assertions.
- `rg -n "::query\\(|DB::table\\(|paginate\\(|withQueryString|withCount|Company::query|Currency::query|TaxCode::query|TaxRate::query|baseCurrency" app/Http/Controllers/Taxes/TaxCodeController.php app/Http/Controllers/Taxes/TaxRateController.php app/Http/Controllers/Taxes/TaxPeriodController.php`: passed after Slice 126, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 126.
- `npm run typecheck`: passed after Slice 126 with 0 TypeScript errors.
- `npm run build`: passed after Slice 126 with the existing Vite chunk-size warning only.
- `php -l app/Application/Reports/ReportPageOptions.php` and representative refactored report controllers: passed after Slice 127.
- `php artisan test --filter=test_report_controllers_delegate_selector_options_to_page_options_service --stop-on-failure`: passed after Slice 127, 1 test / 191 assertions.
- `php artisan test --filter=Phase3Slice8ReportsTest --stop-on-failure`: passed after Slice 127, 12 tests / 180 assertions.
- `php artisan test --filter=Phase4Slice9OperationalReportsTest --stop-on-failure`: passed after Slice 127, 7 tests / 85 assertions.
- `php artisan test --filter=Phase10BranchOperationalReportsTest --stop-on-failure`: passed after Slice 127, 3 tests / 49 assertions.
- `php artisan test --filter=Phase10GlBranchProfitabilityTest --stop-on-failure`: passed after Slice 127, 6 tests / 51 assertions.
- `php artisan test --filter=Phase10TreasuryTransferTest --stop-on-failure`: passed after Slice 127, 4 tests / 34 assertions.
- `php artisan test --filter=Phase7Slice5VatReportsTest --stop-on-failure`: passed after Slice 127, 9 tests / 44 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 127, 132 tests / 20115 assertions.
- `rg -n "::query\\(|DB::table\\(" app/Http/Controllers/Reports -g "*.php"`: passed after Slice 127, 0 matches.
- `rg -n "use App\\\\Models\\\\(Customer|Supplier|Product|Currency|BankAccount|CashAccount|Warehouse|Branch);" app/Http/Controllers/Reports -g "*.php"`: passed after Slice 127, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 127.
- `npm run typecheck`: passed after Slice 127 with 0 TypeScript errors.
- `npm run build`: passed after Slice 127 with the existing Vite chunk-size warning only.
- `php -l app/Application/Settings/BranchSettingsService.php`, `php -l app/Application/Settings/RoleSettingsService.php`, `php -l app/Application/Settings/UserRoleAssignmentService.php`, and `php -l app/Application/Audit/AuditLogQueryService.php`: passed after Slice 128.
- `php artisan test --filter=test_settings_and_audit_controllers_delegate_query_and_persistence_work --stop-on-failure`: passed after Slice 128, 1 test / 277 assertions.
- `php artisan test --filter=SettingsActionsTest --stop-on-failure`: passed after Slice 128, 3 tests / 19 assertions.
- `php artisan test --filter=M8ActionsTest --stop-on-failure`: passed after Slice 128, 16 tests / 59 assertions.
- `php artisan test --filter=Phase10BranchApprovalRulesTest --stop-on-failure`: passed after Slice 128, 5 tests / 30 assertions.
- `php artisan test --filter=M10AuditAndSchedulerTest --stop-on-failure`: passed after Slice 128, 7 tests / 44 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 128, 133 tests / 20434 assertions.
- `rg -n "::query\\(|DB::table\\(" app/Http/Controllers -g "*.php"`: passed after Slice 128, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 128.
- `npm run typecheck`: passed after Slice 128 with 0 TypeScript errors.
- `npm run build`: passed after Slice 128 with the existing Vite chunk-size warning only.
- `php artisan test --filter=test_operational_report_pages_use_shared_filter_panel_with_visible_currency_filter --stop-on-failure`: passed after Slice 129, 1 test / 68 assertions.
- `php artisan test --filter=Phase4Slice9OperationalReportsTest --stop-on-failure`: passed after Slice 129, 7 tests / 85 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 129, 134 tests / 20508 assertions.
- `rg -n "::query\\(|DB::table\\(" app/Http/Controllers -g "*.php"`: passed after Slice 129, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 129.
- `npm run typecheck`: passed after Slice 129 with 0 TypeScript errors.
- `npm run build`: passed after Slice 129 with the existing Vite chunk-size warning only.
- `php artisan test --filter=test_inventory_report_pages_use_shared_filter_panel_and_visible_reset_controls --stop-on-failure`: passed after Slice 130, 1 test / 121 assertions.
- `php artisan test --filter=Phase4Slice9OperationalReportsTest --stop-on-failure`: passed after Slice 130, 7 tests / 85 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 130, 135 tests / 20629 assertions.
- `php -l app/Http/Controllers/Reports/StockMovementReportController.php`: passed after Slice 130.
- `rg -n "::query\\(|DB::table\\(" app/Http/Controllers -g "*.php"`: passed after Slice 130, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 130.
- `npm run typecheck`: passed after Slice 130 with 0 TypeScript errors.
- `npm run build`: passed after Slice 130 with the existing Vite chunk-size warning only.
- `php artisan test --filter=test_payroll_and_expense_filter_clear_actions_are_named_and_guarded --stop-on-failure`: passed after Slice 131, 1 test / 35 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 131, 136 tests / 20664 assertions.
- `php artisan test --filter=Phase12PrepaidAccruedExpenseTest --stop-on-failure`: passed after Slice 131, 6 tests / 74 assertions.
- `php artisan test --filter=Phase13PayrollFoundationTest --stop-on-failure`: passed after Slice 131, 6 tests / 90 assertions.
- `rg -n "::query\\(|DB::table\\(" app/Http/Controllers -g "*.php"`: passed after Slice 131, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 131.
- `npm run typecheck`: passed after Slice 131 with 0 TypeScript errors.
- `npm run build`: passed after Slice 131 with the existing Vite chunk-size warning only.
- `php artisan test --filter=test_remaining_operational_clear_filter_buttons_are_disabled_when_no_filters_are_active --stop-on-failure`: passed after Slice 132, 1 test / 59 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 132, 137 tests / 20723 assertions.
- `php artisan test --filter=Phase10StockCountAdjustmentTest --stop-on-failure`: passed after Slice 132, 5 tests / 57 assertions.
- `php artisan test --filter=Phase10BranchWarehouseOperationsTest --stop-on-failure`: passed after Slice 132, 5 tests / 87 assertions.
- `php artisan test --filter=Phase11ExpenseManagementTest --stop-on-failure`: passed after Slice 132, 8 tests / 60 assertions.
- `php artisan test --filter=Phase14RentalsFoundationTest --stop-on-failure`: passed after Slice 132, 16 tests / 159 assertions.
- `php artisan test --filter=Phase14RentalBillingTest --stop-on-failure`: passed after Slice 132, 8 tests / 56 assertions.
- `php artisan test --filter=Phase14RentalReportsCloseOutTest --stop-on-failure`: passed after Slice 132, 3 tests / 41 assertions.
- `rg -n "::query\\(|DB::table\\(" app/Http/Controllers -g "*.php"`: passed after Slice 132, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 132.
- `npm run typecheck`: passed after Slice 132 with 0 TypeScript errors.
- `npm run build`: passed after Slice 132 with the existing Vite chunk-size warning only.
- `php artisan test --filter=test_rental_filter_bars_use_searchable_select_controls --stop-on-failure`: passed after Slice 133, 1 test / 11 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 133, 138 tests / 20734 assertions.
- `php artisan test --filter=Phase14RentalsFoundationTest --stop-on-failure`: passed after Slice 133, 16 tests / 159 assertions.
- `php artisan test --filter=Phase14RentalBillingTest --stop-on-failure`: passed after Slice 133, 8 tests / 56 assertions.
- `php artisan test --filter=Phase14RentalReportsCloseOutTest --stop-on-failure`: passed after Slice 133, 3 tests / 41 assertions.
- `rg -n "::query\\(|DB::table\\(" app/Http/Controllers -g "*.php"`: passed after Slice 133, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 133.
- `npm run typecheck`: passed after Slice 133 with 0 TypeScript errors.
- `npm run build`: passed after Slice 133 with the existing Vite chunk-size warning only.
- `php artisan test --filter=test_fixed_asset_filter_bars_use_searchable_select_and_clear_actions --stop-on-failure`: passed after Slice 134, 1 test / 60 assertions.
- `php artisan test --filter=Phase15ProductHardeningTest --stop-on-failure`: passed after Slice 134, 139 tests / 20794 assertions.
- `php artisan test --filter=Phase6Slice2FixedAssetRegisterTest --stop-on-failure`: passed after Slice 134, 9 tests / 71 assertions.
- `php artisan test --filter=Phase6Slice6FixedAssetDisposalTest --stop-on-failure`: passed after Slice 134, 15 tests / 60 assertions.
- `php artisan test --filter=Phase10FixedAssetMovementTest --stop-on-failure`: passed after Slice 134, 5 tests / 50 assertions.
- `node` JSON parse for `resources/js/locales/en.json` and `resources/js/locales/ar.json`: passed after Slice 134.
- `rg -n "::query\\(|DB::table\\(" app/Http/Controllers -g "*.php"`: passed after Slice 134, 0 matches.
- `vendor/bin/pint --test`: passed after Slice 134.
- `npm run typecheck`: passed after Slice 134 with 0 TypeScript errors.
- `npm run build`: passed after Slice 134 with the existing Vite chunk-size warning only.
- `php artisan test --filter=test_state_changing_routes_are_auth_gated_and_authorized_or_explicitly_allowlisted --stop-on-failure`: passed, 1 test / 550 assertions.
- `php artisan test --filter=test_ar_ap_cash_posting_confirmations_name_the_ledger_impact --stop-on-failure`: passed, 1 test / 80 assertions.
- `php artisan test --filter=test_invoice_source_document_shapes_do_not_use_loose_any --stop-on-failure`: passed, 1 test / 15 assertions.
- `php artisan test --filter=test_ar_ap_cash_bank_pagination_links_are_typed --stop-on-failure`: passed, 1 test / 26 assertions.
- `php artisan test --filter=test_sales_and_purchasing_flash_messages_are_localized --stop-on-failure`: passed, 1 test / 121 assertions.
- `php artisan test --filter=test_controller_success_flash_messages_are_translation_backed --stop-on-failure`: passed, 1 test / 425 assertions.
- `php artisan test --filter=test_backend_guard_error_messages_have_arabic_translations --stop-on-failure`: passed, 1 test / 17 assertions.
- `php artisan test --filter=Phase3Slice8ReportsTest --stop-on-failure`: passed, 12 tests / 180 assertions.
- `php artisan test --filter=Phase4Slice9OperationalReportsTest --stop-on-failure`: passed, 7 tests / 85 assertions.
- `php artisan test --filter=AccountTypeAndControlAccountTest --stop-on-failure`: passed, 13 tests / 46 assertions.
- `php artisan test --filter=AccountingCoreTest --stop-on-failure`: passed, 19 tests / 79 assertions.
- `php artisan test --filter=M8ActionsTest --stop-on-failure`: passed, 16 tests / 59 assertions.
- `php artisan test --filter=SettingsActionsTest --stop-on-failure`: passed, 3 tests / 19 assertions.
- `php artisan test --filter=Phase6Slice7FixedAssetReportsTest --stop-on-failure`: passed, 6 tests / 151 assertions.
- `php artisan test --filter=Phase7Slice5VatReportsTest --stop-on-failure`: passed, 9 tests / 44 assertions.
- `php artisan test --filter=MigratedPagesTest --stop-on-failure`: passed, 2 tests / 83 assertions.
- `php artisan test --filter=Phase4Slice5CustomerInvoiceTest --stop-on-failure`: passed, 19 tests / 84 assertions.
- `php artisan test --filter=Phase4Slice6SupplierBillTest --stop-on-failure`: passed, 19 tests / 98 assertions.
- `php artisan test --filter=Phase4Slice10ReturnsCreditNotesTest --stop-on-failure`: passed, 40 tests / 237 assertions.
- `php artisan test --filter=Phase6Slice2FixedAssetRegisterTest --stop-on-failure`: passed, 9 tests / 71 assertions.
- `php artisan test --filter=Phase6Slice5DepreciationRunTest --stop-on-failure`: passed, 10 tests / 44 assertions.
- `php artisan test --filter=Phase10FixedAssetMovementTest --stop-on-failure`: passed, 5 tests / 50 assertions.
- `php artisan test --filter=Phase14RentalBillingTest --stop-on-failure`: passed, 8 tests / 56 assertions.
- `php artisan test --filter=Phase14RentalReportsCloseOutTest --stop-on-failure`: passed, 3 tests / 41 assertions.
- `php artisan test --filter=Phase3Slice6BankReconciliationTest --stop-on-failure`: passed, 11 tests / 46 assertions.
- `php artisan test --filter=Phase10BranchOperationalReportsTest --stop-on-failure`: passed, 3 tests / 49 assertions.
- `php artisan test --filter=Phase10BranchWarehouseOperationsTest --stop-on-failure`: passed, 5 tests / 87 assertions.
- `php artisan test --filter=Phase10GlBranchProfitabilityTest --stop-on-failure`: passed, 6 tests / 51 assertions.
- `php artisan test --filter=AccountingDemoSeederTest --stop-on-failure`: passed, 4 tests / 53 assertions.
- `php artisan test --testsuite=Concurrency`: passed, 7 tests / 16 assertions.
- `php artisan test --compact --stop-on-failure`: passed after Slice 57, 720 tests / 17923 assertions / 3 skipped.
- `php artisan qa:verify-local --only-feature-files --stop-on-failure --timeout=60`: identified `Phase4Slice10ReturnsCreditNotesTest.php` as exceeding the 60s per-file budget; the same file passed standalone with a larger timeout.
- `php artisan test --compact --stop-on-failure`: attempted after Slice 58 and exceeded local timeout budgets at 120s, 180s, 300s, and 600s. Treat this as a local suite-runtime timeout requiring separate QA-runtime tuning, not as a Slice 58 assertion failure.
- `php artisan concurrency:stress --workers=100`: passed.
- `php artisan accounting:concurrency-stress --workers=50`: passed.
- `php artisan accounting:allocation-concurrency-stress --workers=50`: passed.
- `php artisan accounting:settlement-concurrency-stress --workers=50`: passed.
- `php artisan accounting:cheque-concurrency-stress --workers=50`: passed.
- `php artisan accounting:bank-reconciliation-concurrency-stress --workers=50`: passed.
- `php artisan accounting:stock-transfer-stress --workers=50`: passed.
- `php artisan accounting:inventory-concurrency-stress --workers=50`: passed.
- `php artisan accounting:fixed-asset-depreciation-stress --workers=50`: passed.
- `php artisan accounting:fixed-asset-disposal-stress --workers=50`: passed.
- `php artisan accounting:phase3-stress --workers=20`: passed.
- `php artisan accounting:phase3-integrity-check`: passed.
- `php artisan tokens:gc --batch=100`: passed.
- Controller length scan: all controllers under `laravel/app/Http/Controllers` are currently under 150 lines.
- `vendor/bin/pint --test`: passed after formatting.
- `npm run typecheck`: passed with 0 errors.
- `npm run build`: passed with the existing Vite chunk-size warning only.
- `php artisan route:list --path=reports`: report routes registered under the strengthened reports group.

## No-Scope Confirmation

- Migrations added: 0.
- Tables added: 0.
- Multi-tenant/company scope introduced: 0.
- `company_id`, `tenant_id`, `currentCompany`, `currentBranch`, and Spatie Teams introduced: 0.

## Next Hardening Slice

- Continue Phase 15 with accountant-focused UX and consistency hardening:

- continue Slice 135 with accountant-facing UI flow simplification and remaining clean-code scans;
- continue scanning sales, purchasing, inventory, fixed asset, and payroll pages for remaining workflow friction;
- prioritize permission-aware disabled states, remaining visible fallback labels in dense operational pages, table scanning, and pre-posting guidance for the next UX hardening pass;
- keep visible labels dictionary-backed through `en.json` and `ar.json`;
- add focused regression tests for any UX/data-shape changes.
