# PHASE 7 TAX / VAT POLICY DECISION PACK

**Status**: PROPOSED / OWNER REVIEW REQUIRED  
**Date**: 2026-08-23  
**Track**: Laravel 13.x + Inertia + PostgreSQL Mini ERP  

---

## 1. ملخص للمالك باللغة العربية (Arabic Owner Summary)

> [!IMPORTANT]
> **مطلوب موافقة المالك قبل البدء في التنفيذ البرمجي لـ المرحلة السابعة (ضريبة القيمة المضافة Tax / VAT).**

تهدف هذه الوثيقة إلى تحديد وإقرار السياسات المحاسبية وضوابط ضريبة القيمة المضافة (VAT) الخاصة بنظام Mini ERP قبل إنشاء الجداول وتطبيق محرك الضرائب في كود Laravel.

### المسار الموصى به للمالك:
1. **نطاق الضريبة:** ضريبة القيمة المضافة (VAT) فقط في المرحلة السابعة. عدم إضافة ضريبة الخصم والإضافة (Withholding Tax) أو ضريبة كسب العمل في هذه المرحلة.
2. **تمثيل نسبة الضريبة:** استخدام النقاط الأساسية الصحيحة (Basis Points - `rate_bps`) حيث `1000 = 10%` و `1400 = 14%` بدون استخدام أي أرقام عشرية (Floating-Point) على الإطلاق.
3. **طريقة الحساب:** احتساب الضريبة بشكل غير شامل (Tax Exclusive Default) حيث يُحسب مبلغ الضريبة كنسبة من الصافي، مع دعم الضريبة الشاملة (Tax Inclusive) اختيارياً حسب فئة الضريبة.
4. **سياسة التقريب:** تقريب المبلغ الضريبي لأقرب وحدة صغرى (Minor Unit - القرش/السنتم) بالنصف للأعلى (Half-Up) باستخدام الحسابات الصحيحة (Integer Division) فقط.
5. **الربط بالحسابات المالية (GL Mappings):**
   - **ضريبة المخرجات المستحقة (Sales Output VAT):** حساب `output_tax_payable` (التزام / دائن عند إصدار الفواتير).
   - **ضريبة المدخلات القابلة للاسترداد (Purchasing Input VAT):** حساب `input_tax_receivable` (أصل / مدين عند استلام فواتير الموردين).
6. **فترات تقديم الإقرار الضريبي:** فترات شهرية مغلقة. الفترات الضريبية المقدمة (Filed Tax Periods) تُقفل نهائياً ويُمنع التعديل عليها أو الإدراج فيها مباشرة، ويتم تسوية أي تعديلات لاحقة من خلال مستندات مرتجعات/إشعارات دائنة ومدينة في الفترة المفتوحة التالية.

---

## 2. English Technical Summary

This document presents the accounting design specifications, math standards, posting workflows, and audit rules for integrating Value-Added Tax (VAT) into the Mini ERP system for **Phase 7**.

### Core Technical Directives:
- **Zero Floats Standard:** All tax amounts are calculated and stored as integer minor units (`tax_amount_minor`, `taxable_base_minor`). Tax rates are defined in basis points (`rate_bps`, integer scale $10,000 = 100\%$).
- **Deterministic Half-Up Integer Rounding:** $\text{Tax Minor} = \text{intdiv}((\text{Base Minor} \times \text{Rate BPS}) + 5000, 10000)$.
- **Strict Period & Filing Guard:** Closed financial periods (`PeriodGuard`) and filed tax periods (`TaxPeriodGuard`) strictly block new tax-affecting postings or reversals.
- **Append-Only Immutable Ledgers:** Tax register entries are immutable snapshots created upon document posting. Corrections occur solely via Credit Notes, Debit Notes, or Purchase/Sales Returns.

---

## 3. Plain-Language Explanation of Tax/VAT Principles

### Value-Added Tax (VAT) Concepts:
1. **Output Tax Payable (ضريبة المخرجات):** Tax collected from customers on sales of goods or services. It creates a short-term liability owed to the tax authority (**Credit** `output_tax_payable`).
2. **Input Tax Receivable (ضريبة المدخلات):** Tax paid to suppliers on business purchases and expenses. If 100% recoverable, it creates a asset/receivable claim against the tax authority (**Debit** `input_tax_receivable`).
3. **Net Tax Settlement (صافي التزام/استرداد الضريبة):**
   $$\text{Net Tax Payable/Claim} = \text{Total Output VAT} - \text{Total Recoverable Input VAT}$$

---

## 4. Tax Scope Options Comparison

| Option | Description | Scope in Phase 7 | Recommendation |
|---|---|---|---|
| **Option A (Recommended)** | **VAT Only (Output & Input)** | Standard VAT codes, zero-rated, exempt lines, output tax payable, input tax receivable. | **RECOMMENDED** for Phase 7 initial release. |
| **Option B** | **VAT + Withholding Tax (WHT)** | Includes WHT retention on supplier payments / customer collections. | Requires owner approval & Phase 7 extension. |
| **Option C** | **Jurisdiction Integration (e.g. ETA / ZATCA)** | Live online e-invoicing API integration, XML signing, clearance. | `NOT IMPLEMENTED YET - REQUIRES OWNER APPROVAL` |

---

## 5. Tax Calculation & Rounding Options Comparison

| Dimension | Option A | Option B | Recommendation |
|---|---|---|---|
| **Tax Calculation Base** | **Tax-Exclusive** (Net + Tax = Gross) | **Tax-Inclusive** (Gross includes Tax) | **Tax-Exclusive** as default; Tax-Inclusive as line/code override option. |
| **Tax Rate Unit** | **Basis Points (`rate_bps`)** (1400 = 14.00%) | Decimal percentage (14.00) | **Basis Points (`rate_bps`)** to guarantee zero float math. |
| **Rounding Algorithm** | **Half-Up Integer Math** $\text{intdiv}((B \times R) + 5000, 10000)$ | Floating-point `round(base * rate, 2)` | **Half-Up Integer Math** (Zero floats, zero `round()`). |
| **Line vs Header Rounding** | **Line-Level Summation** (Header tax = sum of line taxes) | Header-level calculation with unallocated line distribution | **Line-Level Summation** for exact line audit trail. |

---

## 6. Accounting Posting Entries

### 6.1 Sales Output Tax Posting Entries

#### Customer Invoice Posting (Sales Output VAT):
$$\begin{array}{lll}
\text{Debit} & \text{Accounts Receivable Control (1200)} & \text{Gross Amount } (\text{Net} + \text{Tax}) \\
\text{Credit} & \text{Sales Revenue Account (4000)} & \text{Net Taxable Base} \\
\text{Credit} & \text{Output Tax Payable (2300)} & \text{Output VAT Amount}
\end{array}$$

#### Sales Return / Customer Credit Note Posting (Output VAT Reversal):
$$\begin{array}{lll}
\text{Debit} & \text{Sales Returns & Allowances (4100)} & \text{Net Taxable Base Credited} \\
\text{Debit} & \text{Output Tax Payable (2300)} & \text{Output VAT Credited} \\
\text{Credit} & \text{Accounts Receivable Control (1200)} & \text{Gross Amount Credited}
\end{array}$$

---

### 6.2 Purchasing Input Tax Posting Entries

#### Supplier Bill Posting (100% Recoverable Purchasing Input VAT):
$$\begin{array}{lll}
\text{Debit} & \text{Purchases Expense / Inventory Clearing (5000/1599)} & \text{Net Taxable Base} \\
\text{Debit} & \text{Input Tax Receivable (1300)} & \text{Input VAT Amount} \\
\text{Credit} & \text{Accounts Payable Control (2100)} & \text{Gross Amount } (\text{Net} + \text{Tax})
\end{array}$$

#### Purchase Return / Supplier Adjustment Note Posting (Input VAT Reversal):
$$\begin{array}{lll}
\text{Debit} & \text{Accounts Payable Control (2100)} & \text{Gross Amount Credited} \\
\text{Credit} & \text{Purchase Returns & Allowances (5100)} & \text{Net Taxable Base Credited} \\
\text{Credit} & \text{Input Tax Receivable (1300)} & \text{Input VAT Credited}
\end{array}$$

---

## 7. Tax Period & Filing Model

1. **Tax Period Model:** Monthly tax periods (`YYYY-MM`), aligned with financial periods.
2. **Filing Status States:** `draft` -> `posted` -> `filed`.
3. **Filing Guard Enforcement:**
   - Once a Tax Period is marked `filed`, no new tax-affecting documents can be posted with a date inside that tax period.
   - Closed tax periods cannot be reopened directly; post-filing adjustments must be recorded in the current open tax period via explicit adjustment/return documents.

---

## 8. GL Account Mapping Strategy

Required GL Account Mapping Keys (managed via `AccountingAccountMappingService`):
1. `output_tax_payable` (Liability / Credit normal balance)
2. `input_tax_receivable` (Asset / Debit normal balance)

---

## 9. RBAC Permission Requirements

Phase 7 uses granular Spatie RBAC permissions:
- Viewing Tax Master Data & Pages: `taxes.view`
- Editing Tax Codes & Rates: `taxes.edit`
- Posting / Filing Tax Returns: `taxes.file` AND `view_financials`
- Exporting Tax Registers / Reports: `taxes.export` / `reports.export` AND `view_financials`

---

## 10. Required Owner Decision Checklist (15 Items)

| # | Owner Decision Item | Proposed Path | Status |
|---|---|---|---|
| 1 | **Tax Scope** | VAT Only (Output & Input) | `PROPOSED - PENDING OWNER APPROVAL` |
| 2 | **Jurisdiction Rules** | Generic VAT Foundation (No hardcoded jurisdiction APIs) | `PROPOSED - PENDING OWNER APPROVAL` |
| 3 | **Tax Rate Format** | Integer Basis Points (`rate_bps`, $10000 = 100\%$) | `PROPOSED - PENDING OWNER APPROVAL` |
| 4 | **Calculation Base** | Tax-Exclusive default with Tax-Inclusive option | `PROPOSED - PENDING OWNER APPROVAL` |
| 5 | **Rounding Policy** | Half-up integer division to nearest minor unit | `PROPOSED - PENDING OWNER APPROVAL` |
| 6 | **Tax Codes Supported** | Standard VAT, Zero-Rated, Exempt / Out-of-Scope | `PROPOSED - PENDING OWNER APPROVAL` |
| 7 | **Tax Code Level** | Line-level tax code with document header default | `PROPOSED - PENDING OWNER APPROVAL` |
| 8 | **Sales Tax Posting** | Credit `output_tax_payable` on Invoice, Debit on Return | `PROPOSED - PENDING OWNER APPROVAL` |
| 9 | **Purchasing Tax Posting**| Debit `input_tax_receivable` on Bill, Credit on Return | `PROPOSED - PENDING OWNER APPROVAL` |
| 10| **VAT Recoverability** | 100% Recoverable Input VAT for Phase 7 | `PROPOSED - PENDING OWNER APPROVAL` |
| 11| **Tax Period Unit** | Monthly tax periods (`YYYY-MM`) | `PROPOSED - PENDING OWNER APPROVAL` |
| 12| **Tax Filing Guard** | Filed periods locked; post-filing corrections via returns | `PROPOSED - PENDING OWNER APPROVAL` |
| 13| **GL Mapping Keys** | `output_tax_payable` and `input_tax_receivable` | `PROPOSED - PENDING OWNER APPROVAL` |
| 14| **Tax Reports** | VAT Register, Tax Summary, CSV Export, Print | `PROPOSED - PENDING OWNER APPROVAL` |
| 15| **RBAC Permissions** | `taxes.view`, `taxes.edit`, `taxes.file`, `taxes.export` | `PROPOSED - PENDING OWNER APPROVAL` |

---

## 11. Declared "Not Implemented Yet" Scope

The following capabilities are **explicitly excluded** from Phase 7 unless requested and approved in writing:
- Withholding tax (WHT) retentions and certificate management.
- E-invoicing authority clearance APIs (ETA / ZATCA / IRS integration).
- Reverse-charge VAT mechanisms for cross-border services.
- Partial input VAT recovery ratio calculations.
- Multi-company / multi-branch tax filing consolidation.
- Payroll, stamp duty, or municipal tax engines.

---

## 12. Future Slice Plan (Phase 7 Roadmap)

- **Slice 1:** Tax Policy Decision Pack (`PHASE_7_TAX_VAT_POLICY_DECISION.md` - Docs-only).
- **Slice 2:** Tax Code & Tax Rate Foundation (Master data, validation, DB tables, RBAC, UI).
- **Slice 3:** Sales Output VAT Integration (Customer Invoice, Credit Note, Sales Return posting).
- **Slice 4:** Purchasing Input VAT Integration (Supplier Bill, Adjustment Note, Purchase Return posting).
- **Slice 5:** VAT Register, VAT Summary Reports, and GL Reconciliation.
- **Slice 6:** Tax Period Filing and Locking Controls.
- **Slice 7:** Phase 7 UX Polish, Export/Print, E2E Smoke, Source Scans, & Close-Out.
