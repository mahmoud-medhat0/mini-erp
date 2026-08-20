# Mini ERP — Application

Production-grade modular-monolith ERP. **Next.js (App Router) · TypeScript · PostgreSQL · Prisma · Zod · Tailwind.** Node.js runtime only (never Edge for accounting/DB).

## Architecture (enforced)
`UI → Application Services → Domain Engines → Repositories → PostgreSQL`. The UI never touches Prisma or contains accounting logic. Ledger/stock rows are written **only** by domain engines inside a DB transaction. Money = **BigInt minor units** (no floats). Posted financial data is **immutable** (correct via reversal). See `../spec/FINAL_ARCHITECTURE_REVIEW.md`.

## Layout
```
src/
  app/[locale]/           # Next.js App Router (EN/AR, RTL/LTR)
  core/                   # kernel: money, currency, numbering, rbac, audit,
                          # accounting-kernel, errors, auth, jobs, workflow, …
  modules/<domain>/       # application/ domain/ db/ ui/ + permissions/events/validators
  i18n/  locales/         # next-intl, en + ar
  design/                 # design tokens (mirror of Figma + tokens.css)
prisma/schema.prisma      # Phase-1 kernel schema
tests/invariants/         # BLOCKING accounting-invariant suite
```

## Scripts
`dev` · `build` · `typecheck` · `lint` · `test` · `test:invariants` · `e2e` · `prisma:generate` · `prisma:migrate` · `ci`.

## Phase 1 status (this deliverable)
**Implemented & unit-tested (23 passing):** money value object (exact minor-unit math, exact allocation), accounting-kernel (`assertBalanced` Σdr=Σcr), concurrency-safe numbering (format + atomic allocate), RBAC (server-side, scope + tenant isolation), audit types/diff, currency registry, typed domain errors. Prisma kernel schema (company/branch/user/role/permission/currency/fx/fiscal period/number-sequence/audit/attachment/notification). i18n (EN/AR) + RTL + tokens/theming wired. CI with a **blocking invariant job**.

**Next in Phase 1:** Auth.js wiring + password hashing, RBAC seed (role templates → permission catalog), company/branch onboarding + settings UI, numbering config UI, attachments storage adapter, notifications foundation, pg-boss job runner bootstrap, Playwright smoke E2E. Then Phase 2 (Accounting core).

## Setup
```
cp .env.example .env      # set DATABASE_URL etc.
npm install
npm run prisma:generate && npm run prisma:migrate
npm run test              # invariants + unit
npm run dev
```
