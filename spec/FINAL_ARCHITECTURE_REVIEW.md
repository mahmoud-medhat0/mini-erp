# FINAL ARCHITECTURE REVIEW — Pre-Implementation Gate

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


**Purpose:** independent verification, hardening, and consolidation of the ERP architecture before any Phase-1 code. Covers all 26 review points (brief §45), locks the §47 decisions, adds the infrastructure areas the prior spec set did not cover (deployment, DR/backup, observability, performance, security model, testing), and states a clear go/no-go.

**Verdict up front:** the specification baseline is **sound and internally consistent** after the corrections in §26 below. The additions here are **infrastructure & operational** (previously absent) plus a handful of accounting-integrity refinements. **Phase 1 is safe to start** once the two Phase-1-gating decisions (multi-currency base config, approval-flow model) are acknowledged — both are locked below with sensible defaults, so there is **no hard blocker**.

---

## 1. Final architecture
**Modular Monolith** on **Next.js (App Router) + TypeScript**, **PostgreSQL + Prisma**, **Zod**. One deployable, internally partitioned into domain modules with explicit boundaries and a shared kernel. Layering (strict, in-process):

```
UI (React Server/Client Components)         ← no business logic, no direct DB
  → Application Services (per module)        ← use-case orchestration, Zod validation, RBAC checks
    → Domain Services / Engines              ← posting, inventory, numbering, tax, FX, approval
      → Repositories (Prisma)                ← the ONLY layer that touches the DB
        → PostgreSQL                          ← integrity constraints, transactions
Cross-cutting: Auth/RBAC · Audit · Numbering · Notifications · Jobs · i18n · Money/FX
```
**Rule:** UI never calls repositories or the posting engine directly; it calls an Application Service. Ledger/stock rows are written **only** by domain engines inside a DB transaction. This keeps the door open for later extraction of a domain into its own service without rewrites (boundaries are already service-shaped).

## 2. Module boundaries
Each module = its own folder (`/src/modules/<name>`) with `application/`, `domain/`, `db/` (Prisma models colocated for clarity, one schema), `ui/`, `permissions.ts`, `events.ts`. Modules communicate through **Application Service interfaces** and a lightweight **in-process domain-event bus** (typed), never by reaching into another module's repositories. The **Accounting engine** is a shared kernel every financial module depends on; the reverse dependency (accounting → sales) is forbidden (accounting knows only `source_type/source_id`, not sales internals).

## 3. Dependency graph (build & runtime)
```
                     ┌────────────── Platform kernel ──────────────┐
                     │ Auth/RBAC · Audit · Numbering · i18n · Money │
                     │ /FX · Jobs · Notifications · Attachments     │
                     └───────────────────┬──────────────────────────┘
                                         │ (everything depends on kernel)
                         ┌──────────────Accounting Engine (GL)──────────────┐
                         │ CoA · Journal · Ledger · Periods · Statements     │
                         └───┬───────┬───────┬────────┬────────┬────────┬────┘
        depends on GL →  Sales   Purchasing  Cash/Bank/  Expenses  Payroll  Fixed
                          │         │        Cheques      /Prepaid   │      Assets
                          ▼         ▼          │          /Accrual   ▼        │
                       Inventory ◄──┘          ▼             │     Partners   │
                          ▲                  AR / AP ◄───────┴──── /Equity    │
                          │                    ▲                              │
                       Rentals ── Tools/Equipment ─────────────(link)─────────┘
   Cross rollups: Projects/Cost Centers, Budgeting, Recurring, Reports, Dashboard read POSTED data only.
```
**No cycles.** Sales/Purchasing/Rentals depend on Inventory + AR/AP + Accounting; those depend on the kernel. Reports/Dashboard/Budgeting are **read-only consumers** of posted data. This graph drives the phase order (§24) — a phase never needs infrastructure a later phase builds.

## 4. Database / domain boundaries
Single Postgres database, single Prisma schema, **module-prefixed tables**. Hard wall: **operational documents** vs **accounting ledger** (`journal_entry`, `journal_line`, `ledger_entry`) vs **subledgers** (`ar_entry`, `ap_entry`, stock ledger, asset register). Ledger/subledger tables are written only by engines. Money = **BIGINT minor units + currency + fx_rate** (decision locked, §25.11). FKs `RESTRICT` on masters referenced by posted txns; soft-delete only on drafts; posted financial rows immutable (DB trigger + app guard). Full model: `DATABASE_DESIGN.md`.

## 5. Accounting engine design
Real double-entry (`BUSINESS_RULES` BR-A1..A8). Posting service: resolve accounts from configurable mapping → build lines (base + txn currency) → assert `Σdr=Σcr` → assert period open → write JE + lines + ledger + subledger (+ stock) **atomically** → return JE id linked to `source_type/source_id`. Posted = immutable; correction = linked reversing entry. Fiscal years/periods with open/closed/reopened; year-end close rolls P&L → Retained Earnings. Bidirectional drill-down: statement ↔ account ↔ ledger ↔ JE ↔ source document. Detail: `MASTER_ERP_SPEC` B1.

## 6. Accounting event map
`ACCOUNTING_EVENT_MAP.md` — every transaction type (12 groups) with trigger, source, Dr/Cr, tax, inventory, dimensions, currency/FX, subledger effect, reversal. Verified: all balanced; control accounts touched only by their subledger events; each event atomic. **Correction applied:** added GRN-clearing (2-way vs 3-way match) and realized/unrealized FX entries explicitly.

## 7. Inventory engine
Real stock ledger (not a quantity field). Movements with source; valuation by **WAVG default / FIFO supported (per-product, configurable)**; negative stock **blocked by default, permissioned+audited override**; unit conversions; counts/reconciliation; landed cost (qty/value/weight allocation) capitalized to inventory. Historical cost immutability: a costing-method change applies **prospectively only** — past posted layers/averages are never rewritten (BR-I5, new). Detail: B2.

## 8. AR / AP architecture
Subledgers (`ar_entry`/`ap_entry`) reconciling to GL control accounts (BR-S1/S2). Opening balances, invoices, payments, returns, credit/debit notes, advances, allocations, statements, aging (Current/1-30/31-60/61-90/90+), over/under-payment (excess → advance), credit limits (block + override). Control accounts never posted manually.

## 9. Tax architecture
Configurable engine, separate from core accounting: `Tax(kind input/output/withholding, rate, account, effective dates)`; tax periods + return workspace (review before filing). Input/Output/Withholding to distinct accounts; net VAT settlement. **Egyptian rates are seed configuration, verified separately before production — not in code** (BR-T1). Detail: B12.

## 10. Workflow engine
Configurable per document type: flows, conditional steps (amount tiers/branch/project), approve/reject/return, separate approval permissions, full status history. Explicit state machines for all 16 document families in `WORKFLOW_CATALOG.md`. No hardcoded "invoice → accountant."

## 11. Permission architecture
RBAC: Module→Feature→Action (View/Create/Edit/Delete/Submit/Approve/Reject/Post/Cancel/Reverse/Export/Print/Configure) + scopes (company/branch/warehouse/project/cost-center/doc-type) + sensitive flags (view-financials, view-payroll, override-credit-limit, override-negative-stock, close-period). **Server-side enforced** in every Application Service; UI hiding is cosmetic only. Role templates + custom roles. `PERMISSION_MATRIX.md`.

## 12. Audit architecture
`audit_log(entity, id, action, actor, before, after, at, ip/device)` append-only + lifecycle stamps on records. Meaningful field-level diffs; reason captured on reversals/overrides/period-reopen. Financial audit immutable. Every create/modify/approve/post/reverse/cancel/delete recorded.

## 13. Reporting architecture
Report = definition (source query + params + columns + calc) + viewer. Universal params/features incl. drill-down, export (XLSX/CSV/PDF), print (dedicated **light print theme**), AR/EN + RTL/LTR. **All financial reports read posted data only**; Dashboard KPIs reuse the same query definitions (no independent math). `REPORT_CATALOG.md`.

## 14. Background job architecture
Durable queue + scheduler (BullMQ/pg-boss on Postgres, or equivalent). Jobs: recurring docs, depreciation, prepaid/accrual recognition, FX revaluation, aging sweeps, notifications, scheduled reports. **Every job is idempotent** (idempotency key per period/run), **transactional**, **audited**, retried with backoff, and observable (last-run/next-run/failure surfaced in UI). A failed financial job never partially posts (atomic).

## 15. Localization architecture
AR + EN first-class; RTL/LTR via logical CSS + direction isolation for identifiers; light/dark/system with no-reload/no-state-loss switching; per-user persisted language & theme; central money/number/date formatter (multi-currency ready); `name_ar`/`name_en` on business data; strings in `locales/*`. Reports/PDF localized incl. RTL. `DESIGN_FOUNDATION.md`, `tokens.css`.

## 16. Design system architecture
Semantic + financial design tokens (light/dark) in `tokens.css` → Tailwind mapping. Component library with the full state set (default/hover/focus/active/disabled/loading/empty/error/success/read-only/permission-denied) + responsive + RTL. Independent visual identity (trustworthy financial blue-teal; Cairo/Source Sans 3; Playfair for branding only). Financial status never color-alone. Mirrored into **Figma** (this session — see §17).

## 17. Figma integration plan
Design system built in Figma via the Figma MCP, following figma-generate-library phases: **Foundations (variables: Primitives + Color Light/Dark + Spacing + Radius + Type) → Components (Button, Input, StatusBadge, KPI card, …) → representative ERP screen (Dashboard)** with light/dark variable modes and EN/AR specimens. File created this session: `Mini ERP — Design System & UI` (`5sll1NWDIjdSIiUXrQwqyl`). Figma tokens mirror `tokens.css` names so design ↔ code stay in sync (Code Connect later). Screens are assembled from components, not drawn ad-hoc.

## 18. Security model
- **AuthN:** Auth.js sessions; password hashing (argon2/bcrypt); optional MFA for privileged roles.
- **AuthZ:** central policy checked in every Application Service; deny-by-default; scope filters applied to every query.
- **Data:** TLS in transit; encryption at rest (managed Postgres); secrets in a vault/ENV (never in repo); PII/payroll fields access-flagged.
- **Integrity:** all money writes transactional; posted rows immutable; period locks; concurrency-safe numbering.
- **App hardening:** input validation (Zod) on every boundary; CSRF protection on mutations; rate limiting on auth; output encoding; audit of privileged actions; least-privilege DB role for the app.
- **Tenancy:** company/branch scoping enforced server-side on every query.

## 19. Testing strategy
- **Unit:** domain math (money, depreciation, tax, FIFO/WAVG, aging), validators.
- **Integration:** posting → ledger → trial balance; allocation; stock valuation; numbering under concurrency.
- **Accounting invariant tests (must pass in CI, gate merges):** Σdr=Σcr; AR sub=GL; AP sub=GL; inventory valuation=stock ledger; posted immutable; closed-period rejects; unique doc numbers under parallel load; reversal preserves original; balance sheet balances.
- **Permission tests:** each role×action×scope; server-side denial.
- **Workflow tests:** legal/illegal state transitions.
- **E2E (Playwright):** the acceptance scenario in all 4 QA combos (EN/AR × light/dark), desktop+mobile.
- **DB tests:** constraints/triggers (immutability, FK RESTRICT).
Coverage gates on domain/engine code; invariant suite is blocking.

## 20. Deployment architecture
- **Runtime:** containerized Next.js (Node server, not edge — needs Postgres + long transactions) behind a reverse proxy; managed PostgreSQL; a worker process/container for jobs (same image, `WORKER=1`); Redis (or pg-boss) for the queue.
- **Environments:** local → staging → production; migrations via `prisma migrate deploy` gated in CI; seed for CoA templates/roles/numbering.
- **Config:** 12-factor env vars; per-env secrets; base currency & locale seeded, not hardcoded.
- **Release:** CI runs typecheck + invariant/unit/integration tests + lint; blue/green or rolling deploy; migrations run before app switch; feature-flag incomplete modules (they render an explicit "not available" state, never fake data — brief §40).
- **Scaling:** stateless web tier scales horizontally; Postgres vertical + read replicas for reporting later; jobs scale by worker count.

## 21. Backup / recovery strategy
- **Backups:** automated daily full + WAL/PITR (point-in-time recovery) on managed Postgres; retention ≥30 days; periodic restore drills.
- **RPO/RTO targets:** RPO ≤ 15 min (WAL), RTO ≤ 1 h (documented, testable).
- **Financial safety:** because posted data is immutable and append-only, recovery is deterministic; nightly integrity job snapshots subledger↔GL reconciliation so corruption is detectable.
- **Attachments/object storage:** versioned bucket with lifecycle + backup.
- **DR:** infra-as-code so the stack is reproducible; documented runbook.

## 22. Observability / logging strategy
- **Structured logs** (JSON) with request id, user id, company/branch, module, action; **no secrets/PII in logs**.
- **Audit vs logs** kept separate (audit = business record; logs = ops).
- **Metrics:** request latency/error rate, job success/latency, DB pool, posting throughput; **business health metric: subledger↔GL drift = 0** alarmed.
- **Tracing** across UI→service→engine→DB for slow-transaction diagnosis.
- **Error tracking** (Sentry-class) with release tagging.
- **Job dashboards:** last/next run, failures, retries surfaced in-app (Settings→Integrations/Jobs).

## 23. Performance strategy
Server-side pagination/filter/sort on all lists; virtualized tables for large sets; indexed reporting queries + partial indexes on `status='posted'`; materialized `ledger_entry` (and optional materialized views for heavy statements/aging, refreshed by job); caching of read-mostly config (CoA, tax, numbering) with invalidation; avoid N+1 via repository query design; big exports streamed/async. Money math in integer space (fast, exact). Target: list pages < 300 ms P95 at 100k+ rows via pagination.

## 24. 10-phase roadmap (dependency-verified)
Same order as `MASTER_ERP_SPEC` ROADMAP, now checked against the §3 graph — **every phase's dependencies exist in an earlier phase; no forward references.** Each phase carries: Scope · Dependencies · DB · Domain · UI · Reports · Permissions · Tests · Acceptance · Definition-of-Done (brief §41 applicability documented per module). Summary:
1. Foundation (kernel: design system, auth, RBAC, company/branch, settings, numbering, audit, i18n/theme, **job runner skeleton**, **CI + invariant-test harness**).
2. Accounting core (CoA, JE/ledger/trial balance, periods, FX rates, posting engine).
3. Customers/Suppliers/Cash/Banks/Cheques (AR/AP subledgers, reconciliation).
4. Sales & Purchasing (+posting rules, allocation, returns, approvals, credit limits).
5. Inventory (movements, transfers, adjustments, counts, valuation, landed cost).
6. Tools & Equipment + Rentals.
7. Expenses, Fixed Assets, Prepaids, Accruals (+depreciation/recognition jobs).
8. Payroll, Taxes, Partners & Equity.
9. Projects, Cost Centers, Budgeting & Forecasting, Recurring.
10. Reports, Dashboard, Analytics, Audit UX, notifications, print/PDF, hardening.
**Dependency corrections made:** the **job runner** and **CI invariant harness** are pulled **into Phase 1** (previously implicit) because Phases 2/7/9 depend on them; **FX rate tables** land in Phase 2 (not later) because multi-currency is Day-One; **numbering + audit** are Phase-1 kernel because every module needs them.

## 25. Locked decisions (from brief §47 — all confirmed as default; none block Phase 1)
1. Inventory costing: **WAVG default, FIFO supported, configurable per product.** ✔
2. Negative stock: **block by default; permissioned + audited override.** ✔
3. Landed cost: **supported; qty/value/weight allocation; capitalized to inventory.** ✔
4. Multi-currency: **architecture Day-One; base currency configurable (seed EGP).** ✔ *(Phase-1/2 relevant — acknowledged, not blocking.)*
5. Taxes: **configurable engine; Egyptian config separated & verified before production.** ✔
6. Approval: **configurable workflow engine** (fixed sensible defaults ship, fully configurable). ✔ *(Phase-1/4 relevant — acknowledged, not blocking.)*
7. Depreciation: **straight-line + declining balance.** ✔
8. Credit limits/tolerances: **block by default; permissioned override; audited.** ✔
9. Posted accounting: **immutable; reverse instead of edit/delete.** ✔
10. Traceability: **bidirectional source↔accounting↔GL↔reports.** ✔
11. Reconciliation: **AR/AP/Inventory/Assets/Cash/Bank/Tax/Payroll ↔ GL, with integrity job.** ✔
Additional locks made in this review: **Money = BIGINT minor units + currency + fx_rate** (numeric alternative rejected for exactness+performance); **costing-method changes are prospective-only** (BR-I5); **jobs idempotent+atomic+audited**; **queue on pg-boss/BullMQ**; **runtime = Node server (not edge)**.

## 26. Open decisions & corrections log
**Independent verification result — I did NOT trust the prior "0 missing" blindly.** Findings:

**Conflicts found & resolved:**
- *PROJECT_MAP (early) vs MASTER_SPEC:* PROJECT_MAP omitted multi-currency, unit conversions, stock counts, landed cost, budget versions, asset revaluation/maintenance, partner loans, withholding. **Resolved:** MASTER_SPEC supersedes; PROJECT_MAP marked as a superseded sketch.
- *Costing-method change vs posted immutability:* prior specs allowed configurable costing without stating history handling — a real accounting-corruption risk. **Resolved:** BR-I5 prospective-only.
- *GRN vs invoice timing:* event map lacked GRN-clearing. **Resolved:** 2-way/3-way match + clearing account added.
- *FX gain/loss:* mentioned but no posting lines. **Resolved:** realized (settlement) + unrealized (revaluation job) entries specified.

**Genuinely missing (now added by THIS review — were absent from the whole prior set):** deployment architecture, backup/DR (RPO/RTO), observability/logging, performance strategy, consolidated security model, and the formal testing strategy with a blocking accounting-invariant CI suite. These are §18–§23 above.

**Remaining decisions (non-blocking; needed before their phase, not before Phase 1):**
- Egyptian VAT/withholding exact rates & return format → seed config before **Phase 8** (verify against current law).
- Landed-cost default allocation basis (proposed: value) → confirm before **Phase 5**.
- Approval default tiers (amounts) & whether creator may self-approve → confirm before **Phase 4** (defaults ship).
- Depreciation convention (full-month vs pro-rata first period) → confirm before **Phase 7** (default: pro-rata).
- Multi-currency **enablement at launch** vs EGP-only-first UI (architecture is ready either way) → confirm before **Phase 2** (default: EGP-only UI, multi-currency schema live).

**Blocking issues for Phase 1: NONE.** All Phase-1 dependencies (kernel) are self-contained.

---

## Go / No-Go
**GO for Phase 1.** The architecture is consistent, the dependency graph is acyclic and phase-ordered, accounting integrity is specified and testable, and the previously-missing operational concerns are now covered. Proceed to Phase 1 (Foundation) whenever you approve — the only items outstanding are later-phase configuration choices with safe defaults already chosen.
