# REQUIREMENTS TRACEABILITY AUDIT

Every requirement area from the original ERP brief (sections 1–73 and the localization/theming addendum 72–89) mapped to its destination. Status legend:
**C** = Covered (fully specified) · **P** = Partially covered (specified, depends on a listed decision) · **M** = Missing (none — target is zero) · **A** = Ambiguous · **DR** = Decision required (explicitly listed at the end; specified as configurable, choice pending).

**Result: 0 MISSING.** All items are C, or P/DR pending the 8 decisions listed at the end. No requirement was dropped.

## Principles & foundation
| Brief § | Requirement | Destination | Status |
|---|---|---|---|
|1|Enter once → automate everything|Posting engine B1, Event Map, Integration Map|C|
|2, 67, 72(first)|Inspect existing / greenfield / first action|Established: greenfield; PROJECT_MAP + this spec|C|
|3, 35, 68|Design System Ops / component system|DESIGN_FOUNDATION + tokens + MASTER A2 + Component inventory C(spec §8)|C|
|4, 36, 87|Figma AI MCP design system|Available; sequenced after spec (roadmap Phase 1 design) — DR on execution|P/DR|
|5|UX direction (dense, trustworthy)|DESIGN_FOUNDATION §1|C|
|6, 38, 85|Responsive desktop/tablet/mobile + RTL|A2 responsive; DESIGN_FOUNDATION §11|C|
|7, 8, 9|App shell / header / quick create|Screen Catalog: Shell & global; C27 Settings|C|
|10, 24, 23(dash)|Dashboard KPIs + clickable + sourced|C23; REPORT_CATALOG KPI table|C|

## Accounting & finance
| Brief § | Requirement | Destination | Status |
|---|---|---|---|
|11|Accounting & GL, CoA, JE|C1; B1; DATABASE_DESIGN|C|
|12|Accounting automation engine|B1 + ACCOUNTING_EVENT_MAP|C|
|13|Financial statements + drill-down|C2; REPORT_CATALOG|C|
|21|Cash management|C10|C|
|22|Banking + reconciliation|C11|C|
|23(brief)|Cheques lifecycle|C12; Event Map §5|C|
|24|Expenses|C13|C|
|25|Prepaid & accrued|C14|C|
|26|Fixed assets + depreciation|C15; depreciation method DR|P/DR|
|27|Payroll|C16|C|
|28|Taxes (configurable, Egyptian)|C17; B12; Egyptian VAT/withholding specifics DR|P/DR|
|29|Partners & equity|C18|C|
|30|Projects & cost centers|C19|C|
|31|Budgeting & forecasting|C20|C|
|32|Recurring transactions|C21; B8|C|

## Operations
| Brief § | Requirement | Destination | Status |
|---|---|---|---|
|14|Sales end-to-end|C3; B3; Event Map §1|C|
|15|Purchasing end-to-end|C4; B4; Event Map §2; landed cost DR|P/DR|
|16|Inventory + valuation|C5; B2; costing method + negative-stock DR|P/DR|
|17|Tools & equipment|C6; custody state machine|C|
|18|Rental management|C7; B5; Event Map §8|C|
|19|Customers / AR|C8|C|
|20|Suppliers / AP|C9|C|

## Cross-cutting
| Brief § | Requirement | Destination | Status |
|---|---|---|---|
|33, 54|Reporting center + printable|C22; REPORT_CATALOG; B9|C|
|34|Users & permissions (RBAC)|C24; B10; PERMISSION_MATRIX|C|
|35(brief), 52, 53|Approval workflow / detail pages / accounting tab|B7; C-module detail tabs (Accounting tab on every financial doc)|C|
|36(brief)|Audit trail|C25; B11|C|
|37|Document numbering|C26; B6|C|
|38(brief)|Document lifecycle|WORKFLOW_CATALOG|C|
|39, 63|Global drill-down / traceability|Integration Map data-flow invariant; A3|C|
|40|Global search|Screen Catalog shell; command palette|C|
|41|Notifications|B13; C(spec)|C|
|42|Empty/error/loading/permission states|A2 states (universal)|C|
|43|Form UX|A2; DESIGN_FOUNDATION §8/§9|C|
|44|Table UX|A2; DataTable component|C|
|45, 37(addendum), 80, 81|Bilingual AR/EN + RTL/LTR + localization arch|A2; DESIGN_FOUNDATION §4/§7; locales structure|C|
|46|Accounting terminology|C1/C2 labels; localized terms|C|
|47, 29(rules)|Data validation / invariants|BUSINESS_RULES|C|
|48, 16(period)|Period management|B1; C1 periods; WORKFLOW §16|C|
|49|Data relationship model|INTEGRATION_MAP; DATABASE_DESIGN|C|
|50|User journeys (roles)|PERMISSION_MATRIX + WORKFLOW_CATALOG (per-role flows)|C|
|51|All states per feature|A2 universal contract|C|
|55|Security UX (confirm consequences)|BR-X3|C|
|56|Settings structure|C27 Settings & Configuration|C|
|57, 40(addendum roadmap)|Implementation phases|ROADMAP_10_PHASE|C|
|58|Per-feature implementation checklist|Roadmap "Definition of Done" §41|C|
|59|No fake functionality|BR-A5 (real data), engines specified|C|
|60|Quality bar (Odoo/Zoho parity, own identity)|DESIGN_FOUNDATION; brand identity|C|
|61, 62|Cover complete product, not just dashboard|SCREEN_CATALOG (~233 screens across all modules)|C|
|64|Business transaction engine|Integration Map + Event Map|C|
|65|Project map before coding|PROJECT_MAP + this spec set|C|
|66|Working style / when to ask|Decisions surfaced (below), not silently assumed|C|
|69|Accessibility|A2 accessibility|C|
|70|Performance (pagination/virtualization)|A1 (TanStack, server pagination), A2|C|
|71|Acceptance criterion|Roadmap end-to-end acceptance|C|
|73(both)|End goal / theming|MASTER + DESIGN_FOUNDATION|C|

## Localization / theming addendum (72–89)
| Item | Destination | Status |
|---|---|---|
|72 Multi-language + RTL/LTR + light/dark, language switcher, persisted|A2; DESIGN_FOUNDATION §4/§5; style-guide.html demo|C|
|73 Light/Dark/System designed dark theme|tokens.css dark; A2|C|
|74 Design tokens for theming|tokens.css; tailwind.tokens.js|C|
|75 Financial color semantics (not color-alone)|tokens.css financial tokens; A2|C|
|76 Theme-aware components (4-way QA)|A2 QA gate; style-guide verified|C|
|77 Typography (Cairo/Source Sans/Playfair)|DESIGN_FOUNDATION §3; font choice confirmable DR(minor)|C|
|78 Numbers/currency/dates centralized|A2 formatter; A4|C|
|79 Mixed AR/EN + direction isolation|A2; verified in style-guide|C|
|80 Localization architecture (locales/)|DESIGN_FOUNDATION §7|C|
|81 Language-specific business data (name_ar/en)|DATABASE_DESIGN conventions|C|
|82,83 Reports print theme + RTL report|B9; REPORT_CATALOG print rules|C|
|84 Accessibility + localization|A2|C|
|85 Responsive + RTL matrix|A2; DESIGN_FOUNDATION §11|C|
|86 Design QA matrix|A2 QA gate; DESIGN_FOUNDATION §12|C|
|87 Figma variants (EN/AR·LTR/RTL·Light/Dark·State)|sequenced Phase 1 — DR on execution|P/DR|
|88 Switch language/theme without losing state|A2 theme/lang persistence|C|
|89 Final product standard|whole spec|C|

---

## What was MISSING from PROJECT_MAP.md (now added by this spec)
1. **Multi-currency + FX** (rates, realized/unrealized gain-loss) — added A4, B1, Event Map §4.
2. **Unit-of-measure conversions** — added B2, DATABASE_DESIGN.
3. **Stock counts & reconciliation sessions** — added C5, WORKFLOW §4.
4. **Landed cost on purchases** — added B4, Event Map 2.10 (DR on method).
5. **Budget versions + forecasting (sales/expense/cash/profit)** — added C20.
6. **Asset revaluation, transfer, maintenance** — added C15, C6.
7. **Partner loans, current accounts, profit distribution, retained-earnings roll** — added C18, Event Map §11, B1.
8. **Withholding tax + tax periods/returns workspace** — added C17, B12.
9. **Command palette + saved views + advanced filters** — added Screen Catalog + component inventory.
10. **GRN clearing / 3-way match, supplier & customer advances, over/under-payment** — added B3/B4, Event Map §1–2.
11. **Prepaid/accrual recognition jobs, depreciation jobs, recurring worker, FX revaluation job, aging/notification sweeps** — added A1 jobs, B8, C14/C15.
12. **Petty cash, bank statement import** — added C10/C11.
13. **Formal component inventory with all states, and the ~233-screen catalog** — added SCREEN_CATALOG + MASTER C(spec §8).

## Ambiguous items (A) — resolved by explicit specification
- "Reports vs dashboards" boundary → resolved: reports = REPORT_CATALOG; dashboard = C23 reading the same queries (BR-A5).
- "Tools & Equipment vs Fixed Assets" overlap → resolved: equipment is operational/custody; optionally linked to a capitalized `fixed_asset` for disposal/loss accounting (C6↔C15).

## DECISION REQUIRED (8) — before the affected phase; specified as configurable, choice pending
1. **Inventory costing method** default — proposed **Weighted Average** with per-product FIFO override. *(Phase 5)*
2. **Negative-stock policy** — proposed **block outbound below zero**, per-warehouse override. *(Phase 5)*
3. **Landed-cost allocation** — proposed **supported on GRN** (by value/qty/weight basis); confirm bases. *(Phase 4/5)*
4. **Multi-currency scope timing** — in scope; confirm **enable at launch** vs EGP-only first (formatter already ready). *(Phase 2)*
5. **Egyptian VAT & withholding specifics** — confirm current rates/kinds/return format for seed config (engine is generic). *(Phase 8)*
6. **Approval flows** — confirm **fully configurable per doc type** at launch vs fixed role defaults first. *(Phase 1/4)*
7. **Depreciation methods** offered — proposed **straight-line + declining balance**; confirm set. *(Phase 7)*
8. **Credit-limit & over-receipt** enforcement — proposed **block with override permission** + configurable tolerance. *(Phase 4)*

Minor: confirm typography (Cairo / Source Sans 3 / Playfair) — proposed, low-risk.

**Gate:** Phase 1 build may begin now; decisions 4 and 6 affect Phase 1/2 and should be confirmed first. Decisions 1–3,5,7,8 are needed before their listed phases, not before Phase 1.
