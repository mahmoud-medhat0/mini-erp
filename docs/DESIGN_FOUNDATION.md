# Mini ERP — Design System Foundation

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


The reusable UI foundation. Every screen is built from these primitives; nothing
is designed in isolation. Built **i18n + RTL/LTR + light/dark first**, not retrofitted.

**Delivered files (drop into the Next.js app):**
- `foundation/tokens.css` — semantic + financial design tokens, light & dark.
- `foundation/tailwind.tokens.js` — Tailwind mapping (`bg-surface`, `text-income`, …).
- `style-guide.html` — live preview across EN/AR × light/dark.

---

## 1. Principles
- **Dense but readable.** Accountants scan hundreds of rows; default text 14px, table body 13px, generous vertical rhythm only where it aids scanning.
- **Trust over decoration.** No gradients-as-decoration, minimal motion (120–260ms), restrained radius. It should *feel* financial.
- **Tokens only.** Components read `var(--token)`; no hex in components. Theme = flip `[data-theme]`; direction = flip `dir`.
- **Color is never the only signal.** Financial state = color **+** label **+** icon **+** value.
- **Everything traceable.** Numbers are links; the drill-down chevron is a first-class affordance.

## 2. Color system
Semantic surface hierarchy `background < surface < surface-elevated`, with `-muted/-hover/-active` states. Full status set (success/warning/danger/info) each with a `-subtle` background and legible `on-*` foreground in both themes. Dark theme is **purpose-built** (elevated surfaces lighten; borders stay soft) — not an inversion.

**Financial semantics:** `income, expense, profit, loss, receivable, payable, cash, bank, inventory, tax` — stable meaning across the app. Negative figures use `--loss` **and** a minus/parenthesis and are right/inline-end aligned.

Contrast target: **WCAG AA** (≥4.5:1 body, ≥3:1 large/UI) verified in all four QA combos.

## 3. Typography
- **English UI:** Source Sans 3. **Arabic UI:** Cairo (auto-selected via `:lang(ar)` / `dir=rtl`). Display serif (Playfair) reserved for occasional headings only — never tables/forms.
- **Numbers:** tabular lining figures everywhere financial (`font-variant-numeric: tabular-nums`), so columns align.
- Arabic and English are tuned to feel visually balanced (matched x-height/weight), so Arabic never feels like a translated afterthought.

## 4. RTL / LTR architecture
- **One component set**, direction-agnostic. Use **logical properties**: `margin-inline`, `padding-inline`, `inset-inline`, `text-align: start/end`; Tailwind `ms-/me-/ps-/pe-/start-/end-`. Never hardcode `left/right` where direction matters.
- **Direction isolation** (`unicode-bidi: isolate`) for identifiers — invoice numbers, SKU, barcode, IBAN, email, phone, URLs — so RTL never corrupts them. Mixed content ("فاتورة INV-2026-00001", "عميل ABC Company") renders correctly.
- **Directional icons** (arrows, chevrons, breadcrumb separators, pagination) mirror; **non-directional** icons (search, user, calendar) do **not**.
- Sidebar, drawers, dropdown positioning, breadcrumbs, tables, pagination all adapt from the same code.

## 5. Theming (light / dark / system)
- Three modes; **system** follows OS. Switch is global with **no reload** and **no loss** of page, filters, form data, or report params (theme + locale in a client provider + cookie/localStorage; language persisted per user).
- Charts, badges, tables, reports all theme-aware. **Print/PDF uses a dedicated light print theme**, never the dark UI.

## 6. Numbers, currency, dates
Centralized formatter (never per-component): currency (EGP now, multi-currency ready), decimal precision, thousands separators, negatives, percentages, dates/ranges/times — locale-aware for AR/EN while keeping professional financial formatting.

## 7. Localization architecture
All strings from `locales/{ar,en}/{common,navigation,accounting,sales,purchasing,inventory,rentals,customers,suppliers,reports,validation}`. Consistent keys; no hardcoded UI text. Business data (accounts, products, categories, tax names, statuses) supports **Arabic + English names** where useful (`name_ar` / `name_en`).

## 8. Component inventory
Each component ships with states: **default, hover, focus, active, disabled, loading, error, success, read-only, permission-denied**, plus a defined mobile behavior — and is verified in all four QA combos.

**Foundations:** Button (primary/secondary/ghost/danger), Input, NumberField, CurrencyField, Select/Combobox, DatePicker/DateRange, Search, Checkbox/Radio/Switch, Textarea.
**Selectors:** Customer, Supplier, Product, Warehouse, Account, Tax, Project, CostCenter (typeahead + create-new).
**Structure:** AppShell, Sidebar, TopHeader, PageHeader, Breadcrumbs, Tabs, Stepper, FormSection, Card, Drawer, Modal, ConfirmDialog.
**Data:** DataTable (sticky header + sticky key column, sort, filter, column visibility, pagination, selection, bulk/row actions, saved filters), KpiCard, Chart, Pagination, Tooltip, Dropdown.
**Status & feedback:** StatusBadge (document/payment/approval), Toast, Alert, EmptyState, ErrorState, LoadingSkeleton, PermissionDenied.
**Records:** DocumentHeader, AuditTimeline, ActivityFeed, Attachment, DocumentPreview, ApprovalWorkflow.

Predictable names shared between design and code: `Button, Input, Select, DataTable, Modal, Drawer, KpiCard, StatusBadge, PageHeader, FormSection, DocumentHeader, AuditTimeline`.

## 9. Patterns (composed from primitives)
List page · Detail page (with **Accounting tab** on every financial doc) · Create/Edit form (sections, sticky actions, inline validation, auto-calc, unsaved-changes guard) · Approval page · Report page (company header, filters, totals, print) · Dashboard (clickable KPIs → source) · Master-data · Transaction workflow.

## 10. Accessibility
Keyboard nav in RTL **and** LTR; visible focus in both themes; semantic structure; labelled controls; accessible dialogs/menus/tables; AA contrast; color never the sole status signal.

## 11. Responsive strategy
Desktop-first for accountants; deliberate tablet/mobile. Tables don't just shrink — priority columns, horizontal scroll, expandable rows, mobile cards, bottom sheets. Mobile prioritizes dashboard, approvals, notifications, quick actions, lookups. Mobile RTL is intentionally adapted, not desktop-RTL scaled down.

## 12. Design QA matrix — a screen isn't done until it passes
| Language | Direction | Theme |
|---|---|---|
| English | LTR | Light |
| English | LTR | Dark |
| Arabic | RTL | Light |
| Arabic | RTL | Dark |
Plus: mobile/tablet/desktop · long AR & EN text · mixed AR/EN · large & negative numbers · long names · empty & error states.

## 13. Next steps
1. Scaffold Next.js app; wire `tokens.css` + Tailwind mapping + theme/locale providers (Phase 1).
2. Build the shell (Sidebar, Header, PageHeader) and 6–8 core primitives against this foundation.
3. Optionally mirror this system into **Figma** (variants: Language EN/AR · Direction LTR/RTL · Theme Light/Dark · State …) via the Figma MCP.
