# Mini ERP - Product Acceptance Defect Register & Walkthrough Log
# سجل عيوب وملاحظات القبول التشغيلي والتحقق العملي (لأصحاب الأعمال والمحاسبين)

**Document Version:** 1.0  
**Phase:** Phase 20 (Hands-On Acceptance and Defect Closure) - Complete  
**Status:** ACTIVE DEFECT REGISTER  
**System Architecture:** Single-Installation Commercial ERP (Strict No Multi-Tenancy Policy)  
**Supported Locales:** Arabic (`ar`) / English (`en`)  
**Deployment Policy:** PARKED (Deployment remains parked until formal owner/operator sign-off)

---

## 1. Purpose & Overview / الهدف ونظرة عامة

### English:
This defect log provides a practical, structured register for logging, classifying, triaging, resolving, and retesting any issues, defects, or usability friction identified during hands-on acceptance testing by the business owner, financial controller, lead accountant, and internal auditor. 

Acceptance testing is conducted against [`OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md`](file:///C:/Users/NEGM/Downloads/erp%20mds/mini-erp/OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md) (the 15-step walkthrough) and [`PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md`](file:///C:/Users/NEGM/Downloads/erp%20mds/mini-erp/PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md) (the 20-module verification matrix). Every reported defect must include reproducible steps, expected versus actual behavior, clear evidence, and strict verification before closure.

### العربية:
يوفر هذا السجل أداة عملية ومنهجية لتوثيق وتصنيف وتتبع وإصلاح وإعادة اختبار أية ملاحظات أو عيوب برمجية أو عقبات استخدام يتم اكتشافها خلال جلسات الاختبار العملي للقبول النهائي من قبل مالك المنشأة، والمدير المالي، ورئيس الحسابات، والمراجع الداخلي.

تتم الاختبارات العملية استناداً إلى [`OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md`](file:///C:/Users/NEGM/Downloads/erp%20mds/mini-erp/OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md) (دليل الخطوات الخمس عشرة) و[`PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md`](file:///C:/Users/NEGM/Downloads/erp%20mds/mini-erp/PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md) (مصفوفة الـ 20 مجالاً). يجب أن يحتوي كل بند يتم تسجيله على خطوات إعادة الإنتاج، والنتيجة المتوقعة مقابل النتيجة الفعلية، والأدلة، ونتائج إعادة الاختبار قبل الإغلاق والاعتماد النهائي.

---

## 2. Severity Classification Guidelines / تصنيف درجة خطورة العيوب

Every defect recorded must be assigned one of the following four severity levels:

| Severity / درجة الخطورة | Criteria & Operational Impact / المعايير والتأثير التشغيلي | Action Policy / سياسة التعامل |
|---|---|---|
| **Blocker** / حرج مانع للاعتماد | - General Ledger Trial Balance or Balance Sheet is out of balance (Debits != Credits, Imbalance != 0).<br>- Unhandled `500 Internal Server Error` on standard operational workflows.<br>- Idempotency failure: re-submitting or re-posting creates duplicate journals or subledger records.<br>- Security privilege escalation or unauthorized write/delete access across roles.<br>- Unauthenticated (guest) user accessing protected financial data or routes.<br>- Inventory costing or tax calculation mathematical error.<br>- Broken period locking allowing backdated posting to closed periods. | Immediate stop-ship. Must be fixed, retested, and passed before any operational sign-off. |
| **High** / عالي | - Core workflow blocked without an obvious operational workaround.<br>- Data validation silently fails or rejects valid standard business data.<br>- Subledger to GL reconciliation discrepancy.<br>- Critical financial report (P&L, Balance Sheet, VAT Register) displays inaccurate grouping or lines. | Must be fixed and retested in the current acceptance cycle. |
| **Medium** / متوسط | - Non-blocking operational friction or confusing validation feedback.<br>- Missing standard filter, reset, or search parameter on an index page.<br>- Minor UI layout inconsistency, misaligned table column, or incomplete empty state.<br>- Missing localization dictionary string where default fallback is clear. | Target for fix in polish slices; can be deferred only with documented owner consent. |
| **Low** / منخفض | - Minor visual polish, spacing, padding, badge color nuance, or icon choice.<br>- Wording suggestion where financial and operational meaning is already unambiguous.<br>- Optional CSV export column ordering suggestion. | Address during general UX polish passes; does not block operational acceptance. |

---

## 3. Defect Status Lifecycle / دورة حياة حالة العيوب

Defects transition through the following standard lifecycle statuses:

- **New / جديد**: The issue has been recorded by the tester/reporter and is awaiting triage and developer confirmation.
- **Confirmed / مؤكد**: The issue has been reproduced and confirmed as a genuine product defect requiring resolution.
- **Fixed / تم الإصلاح**: A narrowly scoped fix has been implemented in code and verified with automated test coverage.
- **Retest Passed / تم اجتياز إعادة الاختبار**: The fix has been re-verified in the local/staging environment against the exact reproduction steps with clean automated tests.
- **Deferred / مؤجل**: The item is confirmed but mutually agreed by the owner to be postponed to a future product phase (non-blocker only).
- **Rejected / مرفوض**: The issue was investigated and determined to be working as designed, an operating misunderstanding, or not reproducible.

---

## 4. Acceptance Defect Register / جدول تسجيل العيوب والملاحظات

| ID | Date | Reporter | Persona/Role | Module/Page | Route | Severity | Status | Steps to Reproduce | Expected Result | Actual Result | Evidence | Fix Summary | Retest Result | Owner Sign-Off |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| `DEF-BASELINE-001` | 2026-08-29 | Automated Acceptance Baseline | `SUPER_ADMIN` / `ACCOUNTANT` | Walkthrough Baseline / All Modules | `/dashboard`, `/accounting/*`, `/purchasing/*`, `/sales/*`, `/reports/*` | Low | Retest Passed | 1. Seed acceptance dataset via `AccountantAcceptanceSeeder`.<br>2. Execute automated 15-step walkthrough baseline and RBAC boundaries.<br>3. Verify double-entry posting, inventory costing, VAT reconciliation, and report balancing. | All 15 walkthrough steps and RBAC role boundaries execute cleanly with 0 failing assertions and 0 imbalance. | All 15 walkthrough steps execute with 0 failures; Trial Balance and Balance Sheet strictly balanced; 0 route authorization gaps. | `Phase20HandsOnAcceptanceTest`, `Phase19AccountantAcceptanceTest` (23 tests passed), `security:route-audit` (0 failing). | Baseline walkthrough automated and verified. No open code defects detected in Slice 1 baseline. | Retest Passed (automated test suite clean). | Pending Live Owner Walkthrough |
| `DEF-UX-001` | 2026-08-29 | Acceptance Inspection | `ACCOUNTANT` | General Journal | `/accounting/journal` | Medium | Retest Passed | 1. Navigate to `/accounting/journal` with >15 vouchers.<br>2. Look for pagination controls below the vouchers table. | Paginator controls are visible below the table to navigate multiple pages of journal vouchers. | Pagination was previously not rendered in the template despite paginated data prop. | `GeneralJournal.tsx`, `Phase20HandsOnAcceptanceTest::test_phase_20_slice_2_general_journal_paginator_contract`. | Added `<PaginationControls links={journals.links} />` beneath the vouchers table. Added operational create action in `EmptyState`. | Retest Passed | Pending Live Owner Walkthrough |
| `DEF-UX-002` | 2026-08-29 | Acceptance Inspection | `ACCOUNTANT` | Chart of Accounts | `/accounting/coa` | Medium | Retest Passed | 1. Open Add Group or Add Account modal on `/accounting/coa`.<br>2. Submit form with invalid/duplicate code or missing required field. | Field-level error messages display directly beneath the invalid input fields. | Field error alerts were only in general error summary or missing per-field binding. | `ChartOfAccounts.tsx`, `Phase20HandsOnAcceptanceTest::test_phase_20_slice_2_accountant_facing_pages_render_cleanly`. | Connected `groupForm.errors` and `accountForm.errors` directly under input fields with localized error text. | Retest Passed | Pending Live Owner Walkthrough |
| `DEF-UX-003` | 2026-08-29 | Acceptance Inspection | `ACCOUNTANT` | Cheque Register | `/reports/cheque-register` | Medium | Retest Passed | 1. Navigate to `/reports/cheque-register`.<br>2. Review filter grid, status labels, and date filtering capability. | Cheque register provides single bank selector, date range pickers, localized status options, and reset button. | Filter grid contained duplicate bank selectors, lacked date range pickers, and had unlocalized hardcoded status options. | `ChequeRegister.tsx`, `Phase20HandsOnAcceptanceTest::test_phase_20_slice_2_accountant_facing_pages_render_cleanly`. | Removed duplicate bank filter, added `dateFrom`/`dateTo` pickers, localized status options/badges with dictionary keys, added Reset button and print/export actions. | Retest Passed | Pending Live Owner Walkthrough |
| `DEF-UX-004` | 2026-08-29 | Acceptance Inspection | `ACCOUNTANT` / `AUDITOR` | Subledgers & Financial Reports | `/accounting/*`, `/reports/*` | Low | Retest Passed | 1. Inspect accountant pages in Arabic (RTL) and English (LTR) modes.<br>2. Check numeric alignment, table headers, reset buttons, and print/export availability. | Tables use logical `text-start` for labels and `text-end` for numeric/currency amounts. Reset and print buttons available where appropriate. | Several report tables had hardcoded `text-left`/`text-right` or lacked reset/print actions. | `TrialBalance.tsx`, `JournalDetail.tsx`, `OpeningBalances.tsx`, `GeneralLedger.tsx`, `ArGlReconciliation.tsx`, `ApGlReconciliation.tsx`, `VatGlReconciliation.tsx`, `VatRegister.tsx`, `VatSummary.tsx`, `CustomerStatement.tsx`, `SupplierStatement.tsx`, `CashBook.tsx`, `BankBook.tsx`, `ArAging.tsx`, `ApAging.tsx`, `BankReconciliation.tsx`, `BankReconciliationDetail.tsx`. | Replaced directional classes with logical `text-start`/`text-end`, added permission-aware Print/Export actions and Filter Reset buttons. | Retest Passed | Pending Live Owner Walkthrough |
| `DEF-UX-005` | 2026-08-29 | Local Review | `ACCOUNTANT` / `AUDITOR` | Financial Report Actions | `/accounting/ledger`, `/accounting/trial-balance`, `/reports/*` | Medium | Retest Passed | 1. Review newly added financial Print/Export UI gates.<br>2. Verify actions are not shown with incomplete financial permissions. | Financial Print/Export actions require complete matching permissions, not partial OR-based checks. | Some new Print controls used `reports.print OR view_financials`, which could show actions too broadly in the UI. | `Phase20HandsOnAcceptanceTest::test_phase_20_slice_2_financial_print_and_export_controls_require_complete_permissions`. | Corrected financial report action gates to require complete permissions and added regression coverage. | Retest Passed | Pending Live Owner Walkthrough |
| `DEF-VAL-001` | 2026-08-29 | Acceptance Inspection | `ACCOUNTANT` | AR/AP Settlements | `/sales/receivable-settlements`, `/purchasing/payable-settlements` | Medium | Retest Passed | 1. Try to settle with zero selected allocation lines.<br>2. Try reversing settlement without clear sensitive-action flow. | User sees inline localized feedback, and reversal requires sensitive confirmation with reason. | Settlement pages used browser alert style feedback and hand-rolled reversal modals. | `ReceivableSettlements.tsx`, `PayableSettlements.tsx`, `SensitiveActionModal`, `Phase20HandsOnAcceptanceTest`. | Replaced alert feedback with inline error state, added submit permission guards, and routed reversal through `SensitiveActionModal`. | Retest Passed | Pending Live Owner Walkthrough |
| `DEF-VAL-002` | 2026-08-29 | Local Review | `ACCOUNTANT` | Financial Statement Mappings | `/accounting/statement-mappings` | Low | Retest Passed | 1. Attempt deleting a system or in-use statement line.<br>2. Observe feedback style. | User sees inline localized feedback without disruptive browser popups. | Page used browser `alert()` for protected delete cases. | `FinancialStatementMappings.tsx`, `Phase20HandsOnAcceptanceTest`. | Replaced browser alerts with inline `pageError` feedback using existing localized messages. | Retest Passed | Pending Live Owner Walkthrough |
| `DEF-PERM-001` | 2026-08-29 | Acceptance Inspection | `ACCOUNTANT` / `AUDITOR` | COA, Account Mappings, Sales Returns | `/accounting/coa`, `/accounting/account-mappings`, `/sales/returns` | Medium | Retest Passed | 1. Review pages with read-only or incomplete permissions.<br>2. Check create/save/delete buttons. | Mutating buttons appear only when the user has the exact required backend permission path. | Some action visibility depended on broad or mismatched permissions. | `ChartOfAccounts.tsx`, `AccountMappings.tsx`, `SalesReturns.tsx`, `Phase20HandsOnAcceptanceTest`. | Added permission-aware UI gates for COA creation, account mapping save/delete, and sales return creation. | Retest Passed | Pending Live Owner Walkthrough |
| `DEF-UX-006` | 2026-08-29 | Local Review | `ACCOUNTANT` / `ADMIN` | AR/AP Allocations & User Administration | `/receivable-allocations`, `/payable-allocations`, `/settings/users` | Medium | Retest Passed | 1. Trigger invalid allocation submission with no positive lines.<br>2. Review the self-delete guard in user administration.<br>3. Run frontend alert scan. | Feedback appears inline in the workspace without blocking browser popups, and frontend source contains no `alert()` calls. | Remaining browser `alert()` calls existed in allocation entry pages and self-delete guard. | Global frontend `alert()` scan, `Phase20HandsOnAcceptanceTest::test_phase_20_slice_2_no_unsafe_ui_controls_in_frontend`. | Replaced allocation alerts with inline `allocationError`, retained permission guards, and replaced self-delete alert with inline status feedback. Added `alert()` to the unsafe frontend guard. | Retest Passed | Pending Live Owner Walkthrough |

---

## 5. Summary of Defect Metrics / ملخص مؤشرات العيوب

| Metric | Blocker | High | Medium | Low | Total |
|---|---|---|---|---|---|
| **New** | 0 | 0 | 0 | 0 | **0** |
| **Confirmed** | 0 | 0 | 0 | 0 | **0** |
| **Fixed** | 0 | 0 | 0 | 0 | **0** |
| **Retest Passed** | 0 | 0 | 7 | 3 | **10** |
| **Deferred** | 0 | 0 | 0 | 0 | **0** |
| **Rejected** | 0 | 0 | 0 | 0 | **0** |
| **Total Recorded** | **0** | **0** | **7** | **3** | **10** |
| **Open Active Defects** | **0** | **0** | **0** | **0** | **0** |

*Note: All 10 recorded usability, validation, and permission-clarity items have been fixed, retested, and passed. No open defects remain. Formal owner sign-off will take place upon live walkthrough completion.*

---

## 6. Deployment Readiness Policy / سياسة الجاهزية للنشر والتشغيل

> [!IMPORTANT]
> **Deployment Remains Parked:**
> In strict compliance with project governance and owner instructions:
> 1. **No Production Deployment without Sign-off:** System deployment to staging or production remains strictly parked until the business owner and financial controller complete the hands-on walkthrough in [`OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md`](file:///C:/Users/NEGM/Downloads/erp%20mds/mini-erp/OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md) and grant formal sign-off in Section 8.
> 2. **Zero Blocker / Zero High Defect Requirement:** No release or cutover may proceed if any `Blocker` or `High` severity defect remains in `New`, `Confirmed`, or unverified status.
> 3. **Single Installation Scope:** All tests, fixes, and acceptance evidence apply solely to the single-installation commercial ERP architecture. No multi-tenant modifications or assumptions are permitted.
