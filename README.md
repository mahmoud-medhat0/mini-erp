# Mini ERP — Accounting & Business Management System

Production-grade, greenfield **Mini ERP**: a connected system where each business transaction is entered **once** and automatically produces every operational, subledger, inventory, accounting, reporting, and audit consequence.

## Overview
One integrated ERP across 26+ modules (Accounting, Sales, Purchasing, Inventory, Rentals, AR/AP, Cash, Banks, Cheques, Expenses, Fixed Assets, Payroll, Taxes, Partners, Projects, Budgeting, Recurring, Reports, Dashboard, RBAC, Audit, Numbering, Settings). Every financial figure is traceable both directions: report ↔ account ↔ ledger ↔ journal ↔ source document.

## Architecture
Modular monolith. `UI → Application Services → Domain Engines → Repositories → PostgreSQL`. The UI never touches Prisma and holds no accounting logic; ledger/stock rows are written only by domain engines inside DB transactions. Accounting is a shared kernel. Full detail in `spec/ARCHITECTURE.md` and `spec/FINAL_ARCHITECTURE_REVIEW.md`.

## Technology stack
Next.js (App Router) · React · TypeScript · PostgreSQL · Prisma · Zod · Tailwind · next-intl (AR/EN, RTL/LTR) · Auth.js · pg-boss (durable jobs) · Vitest · Playwright · ESLint/Prettier. Node.js runtime only (never Edge for accounting/DB).

## Repository layout
```
app/          Next.js application (modular monolith) — see app/README.md
spec/         Authoritative specifications + architecture/security/testing/deploy/DR docs
docs/         PROJECT_MAP, DESIGN_FOUNDATION
foundation/   design tokens (tokens.css, tailwind.tokens.js)
style-guide.html   live design-system preview (EN/AR × light/dark)
ROADMAP.md · CHANGELOG.md · IMPLEMENTATION_STATUS.md
```

## Setup
```
cd app
cp .env.example .env          # set DATABASE_URL, AUTH_SECRET, etc.
npm install
npm run prisma:generate && npm run prisma:migrate
npm run dev
```

## Environment variables
See `app/.env.example` — `DATABASE_URL`, `AUTH_SECRET`, `AUTH_URL`, `BASE_CURRENCY`, `PGBOSS_DATABASE_URL`. **No secrets are committed**; `.env*` is git-ignored.

## Commands
`npm run dev | build | typecheck | lint | test | test:invariants | e2e | prisma:generate | prisma:migrate | ci`.

## Database
PostgreSQL via Prisma. Money is **BigInt minor units** (never float). Posted financial rows are immutable; period locks; concurrency-safe numbering; company/branch isolation.

## Testing
Unit, integration, permission, workflow, E2E — plus a **blocking accounting-invariant suite** (Σdr=Σcr, subledger=GL, immutability, closed-period rejection, unique numbering under concurrency, tenant isolation…). CI fails if any invariant fails. See `spec/TESTING_STRATEGY.md`.

## Project phases
10 dependency-ordered phases (see `ROADMAP.md`). Current: **Phase 1 — Foundation**.

## Current status
See `IMPLEMENTATION_STATUS.md` (kept current each milestone).

## Accounting integrity principles
Double-entry; every posted entry balances; posted data immutable (correct via reversal); closed periods reject posting; subledgers reconcile to GL; every entry references its source document; drill-down both directions.

## Security principles
Server-side authorization (UI hiding is not security); company/branch scope enforced on every query; argon2 password hashing; secrets via env; audit of privileged actions; least-privilege DB. See `spec/SECURITY.md`.
