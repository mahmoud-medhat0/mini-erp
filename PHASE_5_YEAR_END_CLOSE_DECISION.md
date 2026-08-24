# MINI ERP - PHASE 5 SLICE 5 YEAR-END CLOSE & RETAINED EARNINGS DECISION PACK

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


**Status**: OWNER DECISION REQUIRED  
**Date**: 2026-08-23  
**Track**: Laravel + Inertia + PostgreSQL Mini ERP  

---

## 1. Owner-Facing Executive Summary / ملخص تنفيذي لمالك المشروع

### Arabic Executive Summary / ملخص تنفيذي باللغة العربية

> [!IMPORTANT]
> **حالة إقفال نهاية السنة وحساب الأرباح المبقاة (المحتجزة):**  
> لم يتم تطبيق قيود إقفال حسابات الإيرادات والمصروفات آلياً في قاعدة البيانات في هذه المرحلة، وهذا إجراء مدروس وآمن لحماية دقة البيانات المالية.  
> 
> **الوضع الحالي للنظام:**  
> 1. يتم إقفال الفترات المالية الشهرية (12 شهراً) بدقة وأمان تام عبر `PeriodGuard` و `PeriodService`.  
> 2. تُحسب القوائم المالية من قيود اليومية المرحّلة حسب تاريخ القيد المحاسبي. الميزانية العمومية الحالية تضيف صافي حركة حسابات قائمة الدخل ضمن إجمالي حقوق الملكية للعرض والتوازن، لكن لم يتم اعتماد أو إنشاء حساب دفتر أستاذ رسمي للأرباح المبقاة ولم يتم إنشاء قيد إقفال نهاية سنة.
> 3. لا يوجد أي تعديل أو حظر على السجلات التاريخية للدفاتر المرحّلة.  
> 
> **التوصية الفنية (الخيار الهجين - Hybrid):**  
> نوصي بالاستمرار في الإقفال "المرن/الناعم" (Soft Close) لجميع فترات السنة المالية حالياً، مع الاحتفاظ بحساب الأرباح المبقاة محسوباً ديناميكياً في التقارير. وعند موافقة مالك المشروع الصريحة وتحديد الحساب المحاسبي الخاص بالأرباح المبقاة في دليل الحسابات (Chart of Accounts)، يمكن إضافة ميزة إنشاء "قيد إقفال نهاية السنة" (Closing Journal Entry) في مرحلة مستقبلية بشكل مستمر وآمن.

---

### English Technical Overview

This decision pack presents a bounded architectural comparison and recommended path for **Year-End Close** and **Retained Earnings** handling in Mini ERP.

Currently, Mini ERP operates with **pessimistic, period-guarded period closing** (`PeriodGuard` & `PeriodService` implemented in Phase 5 Slice 4). Financial statements—including the Balance Sheet, Income Statement, and Cash Flow Statement—calculate balances and net income from posted `ledger_entry` records filtered by accounting date (`entry_date`). The current Balance Sheet includes income-statement net movement in the equity total for reporting balance, but no physical Retained Earnings GL account or year-end closing journal engine has been approved or implemented.

This document explains three operational approaches for year-end closing, recommends **Option 3 (Hybrid Approach)**, provides an owner decision framework, and establishes the blueprint for future implementation upon explicit owner approval.

---

## 2. Plain-Language Explanation of Year-End Close & Retained Earnings

In double-entry accounting and ERP systems:

1. **Temporary Accounts (Income Statement Accounts)**:
   - Revenue and Expense accounts measure financial performance over a specific period (e.g., a fiscal year).
   - At the beginning of a new fiscal year, managers expect Income Statement reports for the new year to start from zero.

2. **Permanent Accounts (Balance Sheet Accounts)**:
   - Assets, Liabilities, and Equity accounts carry their cumulative balances forward from period to period and year to year indefinitely.

3. **Net Income & Retained Earnings**:
   - **Net Income** = Total Revenues minus Total Expenses during a fiscal year.
   - **Retained Earnings** = The cumulative accumulated net income (or loss) of all prior fiscal years that has been retained in the business (not distributed as dividends/drawings).
   - In Equity: $\text{Total Equity} = \text{Contributed Capital} + \text{Prior Retained Earnings} + \text{Current Net Income}$.

4. **Two Technical Ways to Handle Year-End Close**:
   - **Dynamic Calculation (Soft Close)**: The ERP leaves revenue and expense accounts intact in the database ledger. When generating reports for a new fiscal year, the reporting engine filters by date (`entry_date >= new_year_start`) so new year revenues/expenses start at zero, while Balance Sheet dynamically calculates Retained Earnings as the sum of all prior-year net income.
   - **Physical Closing Journal (Hard Close)**: At fiscal year-end, the ERP generates a physical multi-line journal entry that debits all revenue accounts and credits all expense accounts to zero them out, placing the net difference into the General Ledger `Retained Earnings` account.

---

## 3. Comparison of Year-End Close Options

| Feature / Dimension | Option 1: Soft Year Close Only | Option 2: Physical Closing Journal Entry | Option 3: Hybrid Approach (RECOMMENDED) |
|---|---|---|---|
| **Mechanism** | Lock all 12 monthly periods. Dynamic report calculation. | Lock 12 periods + post a physical zeroing journal to Retained Earnings at year end. | Lock 12 periods now with dynamic reporting. Add physical closing entry engine later upon approval. |
| **Physical Ledger Changes** | Zero new journal entries posted. | 1 multi-line closing journal entry posted to GL at fiscal year end. | Zero new journal entries now. Optional physical closing entries later. |
| **Retained Earnings GL Account Mapping** | Not required. Calculated dynamically in reports. | Mandatory explicit GL account mapping (`account_id`). | Not required now. Required prior to future physical posting pass. |
| **Income Statement Next-Year Zeroing** | Achieved via report date filters (`entry_date >= year_start`). | Achieved by zero GL account opening balance via physical entry. | Achieved via report date filters now; physical zeroing available later. |
| **Reopen & Audit Complexity** | Extremely low. Reopening a period simply removes posting block. | High. Reopening a year requires reversing or voiding the closing journal entry. | Low now. Clear policy defined before physical closing engine is built. |
| **Risk of Double Counting** | Zero. | Medium (if reporting engine sums ledger entries without excluding closing entries). | Zero now. Pre-engineered reporting isolation if closing entries added later. |
| **Implementation Scope** | Available now for period locking and date-based reporting; formal Retained Earnings posting is not implemented. | Requires Migration, Model, Service, GL Mapping, Reversal Policy, UI, & Tests. | Complete as a decision path for Phase 5. Future slice dedicated to physical close engine only when approved. |

---

### Detailed Analysis of Options

#### Option 1: Soft Year Close Only
- **How it works**:
  - All 12 monthly `financial_period` records in the fiscal year are updated to `status = 'closed'` using `PeriodService::closePeriod`.
  - The `PeriodGuard` prevents any new transaction postings into any date falling within the closed fiscal year.
  - The Balance Sheet includes income-statement net movement in the equity total for reporting balance. A separately named Retained Earnings presentation line can be derived from historical revenue/expense movement, but this slice does not implement a physical Retained Earnings GL posting.
  - The Income Statement filters strictly by the selected fiscal year date range (`entry_date BETWEEN start_date AND end_date`), so income statement accounts naturally display only transactions within that period.
- **Pros**:
  - 100% audit transparent; zero synthetic entries inserted into `journal_entry` or `ledger_entry`.
  - Simple reopening: Reopening a period or year requires only updating `financial_period.status` back to `open` or `reopened`.
  - No risk of double-counting net income on financial reports.
- **Cons**:
  - General Ledger queries without date parameters would show cumulative multi-year totals for revenue/expense accounts.
- **Impact on Financial Statements**:
  - **Balance Sheet**: Can present accumulated profit/loss dynamically from prior periods while leaving the immutable ledger untouched.
  - **Income Statement**: Displays exact performance for any chosen date range.
  - **Cash Flow Statement**: Unaffected, as cash movements are categorized from operational `ledger_entry` records regardless of closing entries.

---

#### Option 2: Physical Closing Journal to Retained Earnings
- **How it works**:
  - At the end of Fiscal Year $Y$, an automated or manual action generates a system journal voucher on `fiscal_year.end_date`:
    - **Debit**: All Revenue accounts with credit balances (reducing balance to 0).
    - **Credit**: All Expense accounts with debit balances (reducing balance to 0).
    - **Credit / Debit (Offset)**: Retained Earnings Equity GL Account (`account_id`).
  - All 12 monthly periods + Fiscal Year are marked `closed`.
- **Pros**:
  - Clean account balances in absolute GL queries starting from zero in Fiscal Year $Y+1$.
  - Traditional accounting presentation matching legacy desktop ERP systems.
- **Cons**:
  - High risk of double-counting if financial reports (Balance Sheet / Income Statement) sum GL entries without filtering out transaction type `year_end_close`.
  - Reopening a period in a closed year requires an automated reversal mechanism for the closing journal entry.
  - Requires mandatory setup of a designated Retained Earnings account in Chart of Accounts (`account.is_retained_earnings` or `financial_statement_line` mapping).
- **Impact on Financial Statements**:
  - **Balance Sheet**: Retained Earnings account holds explicit posted credit/debit balance.
  - **Income Statement**: Must filter out `entry_type = 'year_end_close'` to avoid showing zeroed revenue/expense balances on year-end financial reports.
  - **Cash Flow Statement**: Must ignore `year_end_close` entries as they involve non-cash equity transfers.

---

#### Option 3: Hybrid Approach (RECOMMENDED)
- **How it works**:
  1. **Phase 5 Current State**: Utilize **Soft Year Close**. All monthly periods are closed via `PeriodGuard`. Balance Sheet, Income Statement, and Cash Flow Statement calculate report totals from historical accounting entries (`entry_date`). Balance Sheet currently includes income-statement net movement in equity totals; it does not post or require a physical Retained Earnings GL account.
  2. **Future State (Upon Owner Approval)**: If the owner explicitly requests physical year-end closing entries, add a dedicated Year-End Close service (`YearEndCloseService`) that posts a `year_end_close` journal entry to Retained Earnings while marking `journal_entry.source_type = 'year_end_close'`.
- **Why Option 3 is Recommended**:
  - **Zero Breaking Changes**: Preserves the current verified reporting, posting, and period-close behavior.
  - **Maximum Flexibility**: Business operations can execute period closes immediately without blocking reporting or audit processes.
  - **Safe Transition**: Gives the business owner full authority to approve the Retained Earnings GL mapping and reversal rules before any physical ledger entries are created.

---

## 4. Architectural Impacts & Specifications

### Accounting Impact
- Temporary accounts (Revenue, Expense, COGS, Sales Returns, Discounts) retain their immutable ledger history.
- Dynamic statement calculation can present accumulated profit/loss across fiscal years without mutating ledger history. Current implementation includes net income in equity totals; a formal Retained Earnings posting/account remains an owner decision.
- If physical closing entries are added in a future slice, a dedicated `source_type = 'year_end_close'` identifier will isolate closing entries from operational postings.

### Database Impact
- **Current Database Schema**:
  - `financial_period`: Stores period bounds (`start_date`, `end_date`), `status` (`open`, `closed`, `reopened`), and close audit metadata (`closed_by`, `closed_at`, `reopened_by`, `reopened_at`, `close_note`).
  - `ledger_entry`: Immutable posted debit/credit entries linked to `account_id` and `journal_entry_id`.
- **Future Schema Addition (If Option 2/3 Physical Close is Approved)**:
  - Choose one explicit owner-approved Retained Earnings mapping strategy, such as an accounting mapping key, a financial-statement mapping line, or an account-level flag. Do not add one by assumption.
  - Add any closing-entry metadata only if the selected implementation requires it, for example a fiscal-year reference on a dedicated year-end close record or journal source metadata.

### Audit Impact
- All close and reopen actions are logged to Spatie Activitylog via `AuditLogger`.
- Tracked attributes include: `period_id`, `fiscal_year_id`, `closed_by`, `closed_at`, `reopened_by`, `reopened_at`, and `close_note`.
- Historical `ledger_entry` records remain strictly **immutable** (no updates or deletes permitted).

### Reopen Policy & Impact
- If a period or fiscal year is reopened:
  - **Under Soft Close (Current)**: `financial_period.status` transitions from `closed` to `reopened`. PeriodGuard immediately permits authorized postings.
  - **Under Physical Closing Journal (Future)**: Reopening requires the system to automatically post a reversing journal entry (`source_type = 'year_end_close_reversal'`) or void the closing entry prior to reopening.

### Permissions Required
- **Period Close**: `close_period` (strictly enforced in `PeriodService` & `AccountingController`).
- **Period Reopen**: `reopen_period` (strictly enforced in `PeriodService` & `AccountingController`).
- **Financial Statement Viewing**: `reports.view` AND `view_financials`.
- **GL & Statement Mapping**: `accounting.mappings`.
- **Future Year-End Close Action**: `close_period` AND `view_financials` (or dedicated `year_end_close` permission).

---

## 5. Formal Owner Decision Statement & Checklist

### Exact Owner Decision Question / بيان قرار المالك

> **To the Project Owner / Client:**  
> Please select one of the following three options for Year-End Closing and Retained Earnings management in Mini ERP:
>
> 1. **Option 3 (Hybrid - RECOMMENDED)**: Execute Soft Period Close now for all 12 monthly periods (dynamic Retained Earnings calculation on reports). Keep physical Year-End Closing entries as a future optional module subject to explicit approval.
> 2. **Option 1 (Soft Close Only)**: Operate permanently with Soft Period Close and dynamic financial statement calculations. Do not create physical closing journals.
> 3. **Option 2 (Physical Closing Journal)**: Approve immediate implementation of an automated Year-End Closing Journal engine that posts zeroing entries to Retained Earnings at fiscal year end.

---

### Owner Approval Checklist (Required if choosing Option 2 or Option 3 Physical Close)

If physical closing journal entries are to be implemented in a future slice, the owner must review and approve the following 5 parameters:

- [ ] **1. Retained Earnings GL Account Selection**:  
  Specify the exact GL Account ID / Code from the Chart of Accounts to receive the year-end net income transfer (e.g., Account `3100 - Retained Earnings`).
- [ ] **2. Closing Entry Date Rule**:  
  Confirm that closing journals will be dated on the exact final date of the fiscal year (`fiscal_year.end_date`, e.g., `2026-12-31`).
- [ ] **3. Account Zeroing Policy**:  
  Confirm whether revenue and expense accounts should be zeroed physically via GL posting entries or presented as zeroed via report presentation filters.
- [ ] **4. Reopen & Reversal Policy**:  
  Approve the policy that reopening a closed fiscal year automatically generates an reversing journal entry for the year-end close voucher.
- [ ] **5. Role & User Authorization**:  
  Specify which user roles (e.g., `Finance Manager`, `Super Admin`) are authorized to execute Year-End Close.

---

## 6. Future Implementation Plan (If Physical Close is Approved)

If the owner approves physical Year-End Closing journal entries in a future slice, the execution will follow this structured sequence:

1. **Migration & Mapping**:
   - Implement the owner-approved Retained Earnings mapping strategy.
   - Seed or designate the default Retained Earnings account only after explicit owner approval.
2. **Domain Service (`YearEndCloseService`)**:
   - Create `YearEndCloseService::closeFiscalYear(string $fiscalYearId, string $userId, ?string $note)`.
   - Calculate total debits/credits for all revenue and expense accounts for the fiscal year.
   - Construct a balanced multi-line `JournalEntry` (`source_type = 'year_end_close'`).
   - Post through `PostingEngine` to `ledger_entry`.
   - Lock all 12 `financial_period` records in the fiscal year.
3. **Reversal Handler (`YearEndCloseService::reopenFiscalYear`)**:
   - Create reversing `JournalEntry` (`source_type = 'year_end_close_reversal'`).
   - Unlock fiscal periods.
4. **Reporting Engine Isolation**:
   - Update `IncomeStatementReportService` to exclude `source_type = 'year_end_close'` entries so net income reporting remains accurate.
5. **UI & Controller**:
   - Add Year-End Close modal to `Periods.tsx` or dedicated Year-End Close view under `/accounting/year-end-close`.
   - Require `close_period` and `view_financials` permissions.

---

## 7. Verification & Testing Plan for Future Implementation

When physical Year-End Closing is built in a future slice, the following automated tests must be created and verified:

1. **Closing Entry Balance Verification**:
   - Test that the generated closing journal entry is strictly balanced ($\text{Debits} = \text{Credits}$).
2. **Revenue & Expense Zeroing Test**:
   - Test that query of revenue and expense ledger entries *inclusive of closing entry* totals exactly zero at fiscal year end.
3. **Retained Earnings Credit/Debit Test**:
   - Test that net profit correctly credits Retained Earnings, and net loss correctly debits Retained Earnings.
4. **Period Guard Enforcement**:
   - Test that attempting to post any transaction into a closed fiscal year throws `PeriodClosedException`.
5. **Reopen & Reversal Integrity Test**:
   - Test that reopening a closed fiscal year posts a valid reversing journal entry and restores open status cleanly.
6. **Financial Statement Consistency Test**:
   - Test that Balance Sheet, Income Statement, and Cash Flow Statement yield identical totals before and after running Year-End Close.

---

## 8. "Not Implemented Yet" Declaration

To ensure complete clarity for project maintainers, auditors, and AI subagents:

- **Migrations added in Slice 5**: `0`
- **Models added in Slice 5**: `0`
- **Services added in Slice 5**: `0`
- **Controllers / Routes added in Slice 5**: `0`
- **UI Components added in Slice 5**: `0`
- **Seeders / Commands / Jobs added in Slice 5**: `0`

**Current Status**: **OWNER DECISION REQUIRED** (Docs-Only Slice Complete).
