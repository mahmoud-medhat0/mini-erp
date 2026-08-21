# CONTINUE HERE — Mini ERP handoff

> **Laravel correction:** this handoff describes the existing Next.js reference and contains old tenant wording. For the Laravel migration target, read `DOMAIN_MODEL_REVIEW.md` first: Company/Branch are ERP business scopes, not SaaS tenants, and first-run owner onboarding must not be ported unless the spec explicitly revalidates it.

> **ملخص بالعربي:** ده ملف تسليم لإكمال المشروع بموديل/جلسة تانية. المشروع ERP محاسبي حقيقي (مش mockup). **Phase 1 (Foundation) مكتملة ومتحققة على PostgreSQL وGitHub Actions.** الكود كله Next.js + TypeScript + Prisma + PostgreSQL، مبني كـ Modular Monolith بطبقات صارمة. اقرأ الأقسام تحت: **إيه اللي خلص**، **إزاي تشغّل وتتحقق**، **حالة Git/CI**، و**بداية Phase 2**. مهم: لا تكسر أي invariant محاسبي، ولا تحط بيانات وهمية، وكل مبلغ فلوس = BigInt minor units (مفيش float).

This document is the single entry point for continuing the build. Read it fully before writing code.

---

## 1. What this project is
A production-grade **Mini ERP / Accounting & Business Management System**. Core principle: *enter a transaction once → the system automatically produces every operational, subledger, inventory, accounting, reporting and audit consequence.* Full scope in `spec/MASTER_ERP_SPEC.md` (26 modules). **Do not reduce scope; phases are implementation order only.**

## 2. Tech + architecture (locked)
- **Next.js (App Router) · TypeScript · PostgreSQL · Prisma · Zod · Tailwind · next-intl (EN/AR, RTL/LTR) · Auth.js (NextAuth v5) · Argon2 · pg-boss · Vitest · Playwright.** Node.js runtime only (never Edge for DB/accounting).
- **Layering (strict):** `UI → Application Services → Domain Engines → Repositories → PostgreSQL`. The UI never touches Prisma and never contains authoritative accounting logic. **Repositories are the ONLY layer that accesses the DB.** Ledger/stock/subledger writes happen only in domain engines inside DB transactions.
- **Modular monolith:** `src/modules/<domain>/{application,domain,db,ui}` + `src/core/*` kernel. Accounting is a shared kernel; it must not depend on other modules' internals.
- Deep detail: `spec/FINAL_ARCHITECTURE_REVIEW.md`, `spec/ARCHITECTURE.md`, `spec/DATABASE_DESIGN.md`, `spec/BUSINESS_RULES.md`, `spec/ACCOUNTING_EVENT_MAP.md`.

## 3. Non-negotiable rules (keep every one)
- **Money = `bigint` minor units + currency.** Never IEEE-754 float in any monetary path. Use `src/core/money`.
- **Double-entry:** every posted journal entry must balance (Σ debit = Σ credit) — `src/core/accounting-kernel`.
- **Posted data is immutable;** correct via reversal, never edit/delete.
- **Closed periods reject posting.** Company/branch isolation enforced server-side; `company_id` is never trusted from the browser (`src/core/tenant`).
- **Concurrency-safe numbering** (`src/core/numbering`, atomic `INSERT … ON CONFLICT DO UPDATE RETURNING`).
- **No fake data / no fake CRUD / no hardcoded KPIs.** If something can't run yet, show an EmptyState / "not available yet" and mark it `PARTIAL`/`BLOCKED` in `IMPLEMENTATION_STATUS.md`.
- **DoD:** a module is done only when DB + services + validation + permissions + workflow + accounting/subledger + audit + numbering + jobs + UI (all states) + reports + EN/AR + RTL + light/dark + tests all exist (see `NEXT_TASKS.md` and master §47).

## 4. Repo layout
```
app/
  src/
    app/[locale]/            # routes: login/, (app)/ protected group (layout=requireAuth), dashboard, settings/*
    app/api/auth/[...nextauth]/route.ts
    app/api/attachments/*      # scoped upload/download route handlers
    core/                    # money, currency, numbering, rbac, audit, tenant, auth, jobs, attachments,
                             # notifications, errors, accounting-kernel, db/(prisma + repositories)
    modules/company/application/  # companyService, settingsService, branchService, userAdminService
    ui/                      # Button, Input, StatusBadge, primitives (Card/PageHeader/EmptyState/PermissionDenied), AppShell
    i18n/  locales/{en,ar}/  # next-intl
    worker.ts                # pg-boss worker entrypoint
  prisma/schema.prisma  prisma/seed.ts
  tests/{invariants,unit,integration,e2e}/
  # workflow lives at repo root: .github/workflows/ci.yml
spec/  docs/  foundation/  style-guide.html
CONTINUE_HERE.md  NEXT_TASKS.md  IMPLEMENTATION_STATUS.md  ROADMAP.md  CHANGELOG.md
```

## 5. What is DONE (Phase 1) — verified
**Core kernel (unit-tested):** money (exact minor units + exact allocation), accounting-kernel (`assertBalanced`), numbering (format + atomic allocate), RBAC (catalog + 9 role templates + seed plan + scope/tenant checks), tenant isolation, append-only audit service (+ redaction/diff), currency registry, typed errors, auth (credentials service + Argon2 adapter + rate limiter + session guard), attachments (storage abstraction + local adapter + validation), notifications service, jobs (idempotent runner + backoff + pg-boss adapter + worker).

**Integration + app (built, typechecked/linted locally; DB parts run in CI):** Prisma client singleton + repositories (user, append-only audit, atomic numbering, settings, branch, company onboarding, user admin, attachment metadata, notifications); NextAuth v5 credentials config + route handler; login screen; protected route group (`requireAuth`) that redirects signed-in/no-company users to onboarding; app shell (sidebar+topbar+notification count); reusable UI library; Company/Branches/Numbering/Users **Settings** screens (EN/AR, server-derived tenant, real persistence via services); dashboard shell (EmptyState, no mock KPIs); onboarding, notifications center, attachment upload/download route handlers.

**Tooling/CI:** `package-lock.json`, TS-aware ESLint (clean at `--max-warnings=0`), PostCSS/Tailwind build config, root GitHub Actions CI with Postgres service + `prisma db push` + seed + **blocking accounting-invariant suite** + DB-backed numbering/company-onboarding integration tests + production build + Playwright smoke E2E job. **66 Vitest cases pass with DB.** Playwright smoke: **5/5 pass** with DB.

**Status legend + full table:** `IMPLEMENTATION_STATUS.md` (COMPLETE / PARTIAL / SCAFFOLD ONLY). Phase 1 foundation is complete; domain modules for later phases remain scaffold-only.

## 6. How to run & verify (in a NORMAL environment)
```
cd app
cp .env.example .env         # set DATABASE_URL, AUTH_SECRET
npm ci
npm run prisma:generate
npm run prisma:db-push       # local schema sync for verification
npm run prisma:seed          # currencies + permission catalog
npm run test                 # vitest: invariants + unit (+ integration if DATABASE_URL set)
npm run lint                 # eslint --max-warnings=0  (must be clean)
npm run typecheck            # next typegen + tsc --noEmit
npm run build                # Next production build
npm run e2e                  # Playwright; credential tests need DATABASE_URL
npm run dev                  # app
npm run worker               # pg-boss worker (separate process)
```
**Verification gate before every commit:** format → lint → typecheck → tests → (invariant suite must pass) → secret scan → commit.

## 7. Environmental gotchas (important for the next session)
- `prisma generate` now runs cleanly in this environment. If TypeScript reports missing Prisma fields after schema edits, regenerate the client.
- DB integration tests (`tests/integration/*.pg.test.ts`) **skip** unless `DATABASE_URL` is set; CI provides Postgres so they run there.
- Playwright browser binaries must be installed locally with `npx playwright install chromium`; CI does this automatically. Credential/permission E2E tests need `DATABASE_URL`.
- pg-boss is **v10** (batch `Job[]` work handler). Argon2 is native (built at full install; unit tests use a fake hasher so they don't need it).

## 8. Phase 1 completion
Phase 1 foundation is complete after real PostgreSQL verification and a green GitHub Actions run (`32440676342`) on `develop`.

Verified:
1. `npm run ci`: typegen + typecheck + lint + invariants + full Vitest.
2. Vitest: 17 files, 66 tests passed with DB-backed integration enabled.
3. Invariants: 4 files, 23 tests passed.
4. Playwright: 5/5 passed against Postgres-backed auth/RBAC.
5. Production build: `next build` passed.
6. Onboarding transaction: company + branch + 9 roles + 458 permission links + owner membership + `COMPANY_ADMIN`; cross-company role leakage = 0.

## 9. Phase 2 kickoff (Accounting core) — do NOT start until explicitly requested
Build on `src/core/accounting-kernel`. Deliver: Chart of Accounts, Journal/Lines, Ledger, Trial Balance, Fiscal years/Periods, opening balances, FX rates, and the **posting engine** (atomic: JE + ledger + subledger, idempotent, period-locked, reversible). Rules & events already specified: `spec/ACCOUNTING_EVENT_MAP.md`, `spec/BUSINESS_RULES.md`, `spec/WORKFLOW_CATALOG.md`. Add invariant tests (subledger=GL, immutability, closed-period rejection) to the blocking CI suite.

## 10. Git / remote / CI state
- Branches: `main` (stable), `develop` (integration). Work on `develop` or feature branches; small **conventional commits**; run the gate before each.
- Commit trailer used in this project:
  ```
  Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
  Claude-Session: https://claude.ai/code/session_01ErpWJ1PAKfi3VzsZuQRxD9
  ```
- **Remote:** `github.com/mahmoud-medhat0/mini-erp`. `develop` pushed successfully.
- **CI** (`.github/workflows/ci.yml`): on push to main/develop → `npm ci` → `prisma generate` → typegen/typecheck → lint → DB push + seed → **blocking invariant tests** → unit + DB integration (Postgres service) → `next build` → Playwright smoke E2E (Postgres + Chromium). The accounting-invariant suite must never be made informational.

## 11. Golden rules for whoever continues
Accounting correctness > UI speed. Never mutate posted data. Never compute authoritative balances in the UI. Never bypass the posting engine (Phase 2). Keep company/branch isolation. Keep numbering concurrency-safe. Audit privileged actions. Mark honestly: COMPLETE only when actually done + tested. Keep `IMPLEMENTATION_STATUS.md`, `CHANGELOG.md`, `ROADMAP.md` in sync with reality.
