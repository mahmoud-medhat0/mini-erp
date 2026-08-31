# Mini ERP Pre-Production QA Audit Report

Date: 2026-08-30  
Environment: Local Laravel application at `http://127.0.0.1:8010`  
Tester mode: Authenticated senior QA audit using the local bootstrap administrator account.

## Remediation Addendum

Status: Fixed in the 2026-08-30 QA remediation pass.

The original findings below are preserved for traceability. The follow-up remediation pass fixed the Phase 3 HTTP 500 failures, normalized stale financial-period usage away from `is_closed`, added explicit cheque `due_date` fields, added locale-prefix redirects for `/en/...` and `/ar/...`, and replaced sensitive financial posting confirmations covered by the audit with the shared `SensitiveActionModal`.

Verification completed after remediation:
- Authenticated live smoke on `http://127.0.0.1:8010`: login succeeded; all previously failing Phase 3 pages returned HTTP 200; `/en/dashboard` and `/ar/dashboard` redirected to `/dashboard` and returned HTTP 200.
- `php artisan test --filter=PreProductionQaRegressionTest`: passed, 3 tests / 18 assertions.
- `php artisan test --filter=Phase3Slice5ChequeTest`: passed, 8 tests / 51 assertions.
- `php artisan test --filter=Phase3Slice6BankReconciliationTest`: passed, 11 tests / 46 assertions.
- `php artisan test --filter=Phase3Slice7UiTest`: passed, 13 tests / 112 assertions.
- `php artisan test --filter=SecurityHardeningTest`: passed, 38 tests / 979 assertions.
- `php artisan security:route-audit --strict --json`: passed with 0 failing routes.
- `npm run typecheck`, `npm run build`, `vendor/bin/pint --test`, concurrency suite, counter stress, accounting stress, and `tokens:gc` passed.

Residual note: The complete `php artisan test` run exceeded the local command timeout twice in this workspace, so the final verification used focused affected suites plus security, build, and stress checks.

## 1. Executive Summary

Overall system quality: The application has a broad and mature Laravel/Inertia foundation. Authentication, security headers, route-level permissions, sensitive-action middleware, accounting invariants, seeded demo accounting data, and most top-level modules are present. The authenticated route crawl found 112 working pages out of 121 static GET pages.

Major risks: The biggest release blocker is that several Phase 3 operational finance pages crash with HTTP 500 after login. These pages belong to AR/AP opening balances, receipts/payments, allocations, cheques, and bank reconciliation. This is not a cosmetic defect; it blocks core finance operations.

Most important findings: The crashes come from code/schema drift: stale relationship names, stale `is_closed` period filters, and cheque page data ordering by a non-existent `due_date` column. These issues should be fixed before any deployment or owner demo.

Overall UX assessment: The ERP visual language is mostly consistent, bilingual support is present, and many pages have functional empty states. However, native browser confirmation dialogs are still used widely for sensitive actions, which weakens the accounting UX and creates an inconsistent confirmation/audit experience.

Overall business-logic assessment: The accounting kernel appears strong, but Phase 3 workflow pages are not aligned with the current data model. Financial-period status handling must be normalized everywhere before the product can be considered operationally reliable.

## 2. Bugs Found

### BUG-001 - Phase 3 finance workflow pages return HTTP 500 after login

Severity: High

Module: AR/AP, Cash/Bank, Cheques, Bank Reconciliation

Page / URL:
- `/customer-opening-balances`
- `/supplier-opening-balances`
- `/customer-receipts`
- `/supplier-payments`
- `/receivable-allocations`
- `/payable-allocations`
- `/incoming-cheques`
- `/outgoing-cheques`
- `/bank-reconciliations`

Description:

Authenticated page crawl found 9 static GET pages returning HTTP 500. These are all operational finance pages from the Phase 3 workflow area.

Steps to Reproduce:
1. Start Laravel locally.
2. Login with the bootstrap administrator credentials.
3. Open any of the URLs listed above.
4. Observe the server error.

Expected Result:

The page should load normally with existing data, form options, and a useful empty state when there are no records.

Actual Result:

Each listed page returns HTTP 500.

Business Impact:

This blocks posting or reviewing customer opening balances, supplier opening balances, customer receipts, supplier payments, cheque lifecycle actions, allocation reversal screens, and bank reconciliations. This is a no-go for production readiness because finance users cannot run daily AR/AP and treasury workflows.

Evidence:

Authenticated crawl result: 121 static pages tested, 9 failures, all with status 500 and error signal.

Log/code evidence:
- `Call to undefined relationship [financialPeriod]` on `CustomerOpeningBalance`, `SupplierOpeningBalance`, `CustomerReceipt`, and `SupplierPayment`.
- `Call to undefined relationship [customerReceipt]` on `ReceivableAllocation`.
- `Call to undefined relationship [supplierPayment]` on `PayableAllocation`.
- `SQLSTATE[42703]: Undefined column: column "is_closed" does not exist`.
- `SQLSTATE[42703]: Undefined column: column "due_date" does not exist`.

Recommended Fix:

Create a focused Phase 3 UI/data-model alignment pass:
- Add missing Eloquent aliases only where they match real columns, or update PageData eager loads to the model's actual relation names such as `period()` and `receipt()`.
- Replace all stale period filters using `is_closed` with the current `status` model, preferably `whereIn('status', ['open', 'reopened'])` or a shared query scope.
- Correct cheque page data to use the actual cheque date fields, or add a real canonical maturity/due-date field through a forward migration if the product requires it.
- Add an authenticated route smoke test covering every GET page so this class of failure is caught automatically.

### BUG-002 - Financial period open/closed logic is inconsistent across modules

Severity: High

Module: Accounting Periods, AR/AP, Bank Reconciliation, Cheques

Page / URL:
- `/customer-opening-balances`
- `/supplier-opening-balances`
- `/customer-receipts`
- `/supplier-payments`
- `/incoming-cheques`
- `/outgoing-cheques`
- `/bank-reconciliations`

Description:

The current `FinancialPeriod` model stores `status` and exposes `isOpen()` for `open` and `reopened` periods. Some Phase 3 page-data/services still query `is_closed`, which does not exist in the schema.

Steps to Reproduce:
1. Login.
2. Open `/bank-reconciliations`.
3. The index tries to load open periods using `is_closed`.
4. The request fails with HTTP 500.

Expected Result:

All modules should use one canonical period state model.

Actual Result:

Some modules use `status`; older Phase 3 code still uses `is_closed`.

Business Impact:

Even after page crashes are patched individually, this logic mismatch can allow the wrong period options to appear, block valid posting, or allow workflows to target a closed period if different modules implement their own rules.

Evidence:

`FinancialPeriod` fillable fields include `status`, and `isOpen()` returns true for `open` and `reopened`. Phase 3 page data still calls `where('is_closed', false)`. `BankReconciliationService` reads `$period->is_closed`.

Recommended Fix:

Add a single shared period availability API, for example `FinancialPeriod::query()->openForPosting()`, and require all posting/page-data services to use it. Replace property checks with `$period->isOpen()` or an equivalent guard service.

### BUG-003 - Cheque pages use an undefined due-date field

Severity: High

Module: Cheques

Page / URL:
- `/incoming-cheques`
- `/outgoing-cheques`

Description:

Cheque page-data and UI expect `due_date`, but the cheque migration/model expose lifecycle dates such as `received_date`, `issued_date`, `deposited_date`, `cleared_date`, `returned_date`, and `bounced_date`. The list queries order by `due_date`, which does not exist.

Steps to Reproduce:
1. Login.
2. Open `/incoming-cheques` or `/outgoing-cheques`.
3. The index query orders by `due_date`.
4. The request fails with HTTP 500.

Expected Result:

Cheque pages should use a canonical maturity date if the business needs cheque due dates, or use the appropriate lifecycle date for each list.

Actual Result:

The page references a missing database column.

Business Impact:

Cheque lifecycle workflows cannot be used. More importantly, reports and aging decisions can become ambiguous if cheque maturity date is not modeled consistently.

Evidence:

`IncomingChequePageData` and `OutgoingChequePageData` order by `due_date`. The Phase 3 cheque migration creates `received_date` for incoming cheques and `issued_date` for outgoing cheques, but no `due_date`.

Recommended Fix:

Confirm the intended product term, then implement one canonical field:
- If post-dated cheques are supported, add `due_date` or `maturity_date` to incoming and outgoing cheque tables with validation.
- If not supported, update pages and reports to use `received_date` / `issued_date` explicitly.

### BUG-004 - Native browser confirmation dialogs remain on many sensitive financial actions

Severity: Medium

Module: Cross-module UX / Sensitive Actions

Page / URL:

Examples include accounting journals/opening balances, treasury transfers, bank reconciliations, inventory transfers/counts/adjustments, sales/purchasing posting pages, budgeting actions, fixed assets, rentals, and settings deletes.

Description:

Many pages still use native `confirm()` / `window.confirm()` for important actions. The backend has a `sensitive.confirm` middleware and a `SensitiveActionRegistry`, but the frontend experience is inconsistent and sometimes cannot collect a reason before calling reason-required actions.

Steps to Reproduce:
1. Open pages with posting, reversal, finalization, delete, archive, or cancel actions.
2. Trigger the action.
3. Observe the native browser confirmation instead of the ERP modal pattern.

Expected Result:

Sensitive financial/destructive actions should use a consistent localized modal with confirmation code, optional/required reason, loading state, error display, and audit-friendly context.

Actual Result:

Multiple pages use native browser dialogs.

Business Impact:

Native dialogs are hard to style, inconsistent between browsers, and do not help users understand financial impact. For reason-required actions, the UI can fail to collect the required justification before submitting.

Evidence:

Static scan found many `confirm()` usages across `resources/js/Pages` and `resources/js/Components`, while `SensitiveActionRegistry` marks several route actions as reason-required, including reversals, period close/reopen, bank reconciliation finalization, stock counts/adjustments, tax filing, payroll posting, fixed asset actions, and budget activation/archive/cancel.

Recommended Fix:

Replace native confirmations for all registered sensitive actions with the shared `SensitiveActionModal`. Keep simple destructive deletes behind a consistent app modal as well for UX consistency.

### BUG-005 - Language-prefixed URLs return 404

Severity: Low

Module: Localization / Routing

Page / URL:
- `/en/dashboard`
- `/ar/dashboard`

Description:

The app supports Arabic/English by session locale switching, but language-prefixed URLs return 404.

Steps to Reproduce:
1. Visit `/en/dashboard`.
2. Visit `/ar/dashboard`.
3. Observe 404 responses.

Expected Result:

Either language-prefixed URLs should redirect to the equivalent session-locale page, or the product should clearly avoid exposing these URLs.

Actual Result:

Both routes return 404.

Business Impact:

Old bookmarks, screenshots, and shared links can break for users who expect locale prefixes from the earlier Next.js route structure.

Evidence:

HTTP header checks returned 404 for both `/en/dashboard` and `/ar/dashboard`.

Recommended Fix:

Add compatibility redirects for `/en/*` and `/ar/*` that set the locale then redirect to the non-prefixed route, or document the route change and avoid generating prefixed links anywhere.

## 3. Business Logic Issues

1. Period status is not centralized.

Current behavior: Some modules use `FinancialPeriod.status` and `isOpen()`, while Phase 3 areas still reference `is_closed`.

Why problematic: Period locking is a core accounting control. Divergent implementations create production risk.

Scenario: A period is closed, but one module still builds its selectable period list using a stale or bypassed condition.

Recommended behavior: One shared period guard/query API must define posting eligibility for all modules.

2. Cheque maturity semantics are undefined in the implementation.

Current behavior: UI/report code references `due_date`, while the schema only has lifecycle event dates.

Why problematic: Cheques commonly need a maturity/due date distinct from receipt/issue date.

Scenario: A customer gives a cheque today dated next month. Without a canonical maturity date, cash forecasting and cheque register filtering are unreliable.

Recommended behavior: Model a canonical cheque maturity date if post-dated cheques are in scope, otherwise remove all due-date language from cheque workflows.

3. AR/AP allocation navigation does not match the model relationships.

Current behavior: Allocation page data expects `customerReceipt` and `supplierPayment`, while models expose `receipt()` and likely `payment()` style relations.

Why problematic: Allocation records may exist and post correctly, but users cannot reliably review or reverse them from the UI.

Scenario: A receipt is allocated incorrectly. The reversal screen fails before the accountant can select and reverse the allocation.

Recommended behavior: Align relation naming in models and page-data, then add tests for viewing, filtering, and reversing allocation records.

4. Sensitive action UX is not fully aligned with audit policy.

Current behavior: Backend confirmation exists, but many pages use browser confirm dialogs.

Why problematic: Audit-friendly workflows should capture intent and reasons consistently.

Scenario: A manager finalizes a bank reconciliation or reopens a period. The UI should force a clear reason and show what is being confirmed.

Recommended behavior: All registered sensitive actions should use the same confirmation modal and reason policy.

## 4. UX/UI Issues

1. High impact: 500 pages instead of graceful operational screens.

Users opening core finance pages see a server failure rather than a usable screen or empty state.

2. High impact: Native confirmation dialogs break the polished ERP experience.

They are not localized/styled consistently and do not show accounting context clearly.

3. Medium impact: Heavy Inertia payloads on configuration pages.

Some working pages return large payloads, for example Chart of Accounts and account configuration pages. This can become slow with real data.

4. Low impact: Locale routing is session-based only.

This is functional, but language-prefixed URLs currently fail.

## 5. Missing Validation

| Field / Operation | Current Validation | Problem | Recommended Validation |
| ----------------- | ------------------ | ------- | ---------------------- |
| Financial period selection | Mixed usage of `status`, `isOpen()`, and stale `is_closed` references | Pages crash and period availability can diverge | Centralize open-period filtering with `status in open/reopened`; enforce through `PeriodGuard` |
| Bank reconciliation period | Service checks `$period->is_closed` | Property does not exist and can bypass intended state model | Use `$period->isOpen()` and reject all non-open states |
| Incoming/outgoing cheque date | UI expects `due_date`, schema has lifecycle dates only | Missing column causes 500 and business meaning is unclear | Add `maturity_date`/`due_date` with validation, or remove due-date references |
| Allocation reverse screens | Page data loads missing relations | Users cannot review/reverse allocations from UI | Align relation names and require reason through `SensitiveActionModal` |
| Sensitive actions requiring reason | Backend requires reason, many frontend actions use `confirm()` | UI may not collect reason or show backend validation clearly | Use shared modal with required reason field for every `reason_required=true` action |
| Locale switching URLs | Session locale works; `/en/*` and `/ar/*` 404 | Shared links/bookmarks can break | Add safe redirects or explicit route handling for locale prefixes |

## 6. Product Improvement Suggestions

Suggestion: Add an authenticated route smoke test.

Current problem: Page-level 500s escaped normal test coverage.

Proposed solution: Build a permanent PHPUnit command/test that logs in as a seeded admin and requests all named GET routes without required parameters, plus representative dynamic routes using seeded records.

Expected benefit: Catches page-data/schema drift immediately.

Priority: High

Suggestion: Standardize all confirmation flows.

Current problem: Native confirm dialogs are scattered across modules.

Proposed solution: Replace all sensitive/destructive confirms with a shared modal that supports reason, bilingual labels, loading, and backend validation errors.

Expected benefit: Better accountant trust, clearer audit trail, fewer accidental postings.

Priority: High

Suggestion: Add payload-size budgets for Inertia pages.

Current problem: Some pages return large serialized props before real production data growth.

Proposed solution: Paginate large selectors, lazy-load search lists, and add a route smoke check that records payload size.

Expected benefit: Faster page loads and fewer browser slowdowns.

Priority: Medium

Suggestion: Add graceful production error pages with request IDs.

Current problem: Server errors are operationally hard to communicate without a request reference.

Proposed solution: Show a clean error screen with request ID and write the same ID to logs/activity.

Expected benefit: Faster support triage.

Priority: Medium

Suggestion: Create one accounting UX pattern library.

Current problem: Some pages use different modal/form/action patterns.

Proposed solution: Define standard page layout, filter bar, amount input, date picker, posting action, reversal action, and empty-state patterns.

Expected benefit: Easier daily use for accountants and simpler future development.

Priority: Medium

## 7. Missing Features

1. Permanent full-app authenticated smoke testing.

This is necessary because the product now has many modules and route-level regressions can happen without failing service tests.

2. Canonical cheque maturity-date support.

The observed UI/reporting intent suggests cheque due dates are needed, but the schema does not currently provide them.

3. Compatibility handling for old language-prefixed links.

The current locale system works by session, but legacy `/en` and `/ar` route expectations are not handled.

4. Unified accountant-facing sensitive action modal across all modules.

The backend registry exists; the frontend adoption is incomplete.

## 8. Final Priority List

### Fix Immediately

- BUG-001: Fix all Phase 3 pages returning HTTP 500.
- BUG-002: Replace stale `is_closed` period logic with canonical `status` / `isOpen()` / `PeriodGuard`.
- BUG-003: Resolve cheque `due_date` schema/UI mismatch.

### Fix Next

- BUG-004: Replace native confirmations with the shared sensitive-action modal.
- Add authenticated route smoke coverage for all major GET pages and representative dynamic pages.
- Add tests for Phase 3 page-data relationship names and period filters.

### Product Improvements

- Add payload-size budgets and lazy-loaded selectors for large Inertia pages.
- Add production-friendly error screens with request IDs.
- Add locale-prefix redirects if old/shared links matter for the rollout.
