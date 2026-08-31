# Phase 14 Rentals Policy Decision

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.

**Status:** OWNER DECISION REQUIRED  
**Date:** 2026-08-25  
**Execution Mode:** Docs-only. No Laravel implementation has been added in this decision slice.

## Arabic Executive Summary

إدارة التأجير هتأثر على العملاء، المخزون، الأصول الثابتة، الفروع/المخازن، العربون، الضريبة، الإيراد، والحسابات. لذلك لا ينفع نبدأ جداول أو كود قبل تحديد نموذج التشغيل.

الاختيار الآمن المقترح هو:

**سجل معدات تأجير مستقل مع قابلية ربط اختيارية بالمخزون أو الأصل الثابت لاحقا.**

هذا يدي مرونة:

- معدات يتم تأجيرها فقط.
- منتجات مخزنية يمكن تأجيرها.
- أصول ثابتة يمكن تأجيرها.
- تتبع حالة المعدة من متاحة إلى محجوزة إلى مؤجرة إلى مرتجعة/تالفة/صيانة.
- تسجيل عربون كالتزام وليس إيراد.
- إصدار فواتير التأجير والرسوم الإضافية من خلال AR/VAT/GL.
- دعم الفروع والمخازن كتشغيل وتقارير فقط، وليس كـ tenant أو صلاحيات مفترضة.

## 1. Rentable Item Source

### Option A: Standalone Rentable Equipment Register

Create a dedicated rentable item register.

Pros:

- Clean rental availability and condition tracking.
- Does not force every rental item to be a sale product or fixed asset.
- Safer for mixed real-world rental operations.

Cons:

- Needs integration decisions later for inventory/fixed assets when required.

### Option B: Inventory Product / Serialized Stock

Treat rentable items as stock items.

Pros:

- Good for high-volume items already managed in warehouses.
- Can reuse product and stock movement concepts.

Cons:

- Rentals need item-level availability; non-serialized stock is not enough.
- Risky if the business also sells the same items.

### Option C: Fixed Assets As Rentable Items

Treat rentable items as capitalized fixed assets.

Pros:

- Good for expensive equipment owned and depreciated by the business.
- Aligns with asset register and NBV/profitability.

Cons:

- Not suitable for consumable or stock-like rental items.

### Option D: Hybrid Register With Optional Links

Create a rental item register that may optionally reference a product or fixed asset when applicable.

Pros:

- Covers the widest scenarios without forcing a single source.
- Lets implementation start with rental identity and availability, then integrate inventory/fixed assets by explicit rules.
- Best fit for Mini ERP because the owner asked for flexibility.

Cons:

- Needs stricter validation to avoid linking one item inconsistently.

**Recommended:** Option D.

## 2. Availability States

The rental engine should support:

- available
- reserved
- allocated
- delivered / rented
- return pending
- returned
- damaged
- lost
- maintenance
- retired

The system must prevent overlapping active rentals for the same rentable item.

Branch and warehouse references may be used only as operational placement and reporting dimensions.

## 3. Contract Lifecycle

Recommended lifecycle:

- draft
- submitted
- approved
- active
- extended
- return_pending
- returned
- closed
- cancelled

Rules:

- Cancellation before delivery should not create revenue.
- Cancellation after delivery requires reversal/credit or settlement rules.
- Extensions must preserve original contract history.
- Closed contracts must be immutable except through approved adjustment/reversal flows.

## 4. Billing Models

The system should support all common scenarios:

- upfront billing
- periodic monthly billing
- billing on return
- mixed billing with deposit and extra charges

Recommended implementation:

- Store billing model on rental contract.
- Generate rental invoice schedules when needed.
- Post actual invoices through existing customer invoice / AR / VAT / GL services where possible.

## 5. Deposits

Deposits must be treated as liabilities until earned or refunded.

Scenarios:

- full refund
- partial refund
- apply to final invoice
- retain part/all for damage or late fees

Required accounting principle:

- Dr Cash/Bank
- Cr Rental Deposit Liability

Deposit is not rental revenue until applied through an approved charge/invoice flow.

## 6. Charges

Supported charge types should include:

- rental charge
- late fee
- damage fee
- lost/replacement charge
- discount
- manual adjustment

All charge totals must use integer minor units and VAT integration where applicable.

## 7. Accounting Mapping Requirements

Likely mapping keys:

- `rental_revenue`
- `rental_deposit_liability`
- `rental_late_fee_revenue`
- `rental_damage_revenue`
- `rental_loss_recovery`

Depending on item source, later slices may also need:

- inventory asset / COGS mappings
- fixed asset disposal or impairment mappings
- maintenance expense mappings

Do not add mappings until the exact posting workflow is implemented.

## 8. Return And Inspection

Return scenarios:

- full return, no extra charge
- partial return
- late return
- damaged return
- lost item
- replacement fee
- repair/maintenance handoff

Inspection should record:

- returned quantity/items
- condition
- damage notes
- photos/attachments
- charges to create
- final availability state

## 9. Permissions

Recommended permission set:

- `rentals.view`
- `rentals.create`
- `rentals.edit`
- `rentals.submit`
- `rentals.approve`
- `rentals.deliver`
- `rentals.return`
- `rentals.inspect`
- `rentals.invoice`
- `rentals.post`
- `rentals.cancel`
- `rentals.export`
- `rentals.print`
- `rentals.configure`

Financial actions should also require `view_financials`.

Do not add branch-scoped permissions unless a later owner decision explicitly defines branch access rules.

## 10. Reports

Phase 14 should eventually provide:

- Active Rentals
- Rentals Ending Soon
- Overdue Returns
- Rental Revenue
- Rental Profitability
- Rental Deposit Liability Aging
- Damaged/Lost Rental Items

Reports must read posted/source data and must not recompute accounting independently from the ledger.

## 11. Owner Decisions Required

The owner must confirm:

1. Rentable item source: Option A, B, C, or D.
2. Whether serialized item tracking is mandatory from day one.
3. Whether rental items can also be sold.
4. Billing model defaults: upfront, periodic, return-based, or mixed.
5. Whether refundable deposits are required.
6. Which deposit scenarios are allowed: refund, apply to invoice, retain for damage/late fees.
7. Whether late fees are manual only or automatically calculated.
8. Whether damage fees are manual only or rule-based.
9. Whether returns require inspection before contract closure.
10. Whether branch/warehouse placement is required for every rentable item.

## 12. Recommended Path

Use Option D:

- Create a standalone rentable item register.
- Allow optional future links to product/fixed asset by explicit validation.
- Start with availability, contract lifecycle, deposits, charges, and invoice integration.
- Keep branch/warehouse as operational placement only.
- Keep all financial posting through existing PostingEngine, AR, VAT, and customer invoice services.

## 13. Not Implemented Yet

This decision pack did not add:

- migrations
- models
- services
- controllers
- routes
- React pages
- seeders
- jobs
- commands
- tests

Implementation starts only after the owner confirms the decisions above.
