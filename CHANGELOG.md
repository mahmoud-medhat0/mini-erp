# Changelog

All notable changes. Format: Keep a Changelog; SemVer per phase.

## [Unreleased] — Phase 1: Foundation (in progress)
### Added
- Project scaffold: Next.js (App Router) + TypeScript + Prisma + Zod + Tailwind, modular-monolith structure (24 modules + core kernel).
- Core kernel (tested): exact **Money** value object (BigInt minor units, exact allocation), **accounting-kernel** (`assertBalanced` Σdr=Σcr), concurrency-safe **numbering**, server-side **RBAC** with scope + tenant isolation, typed **errors**, **audit** types, **currency** registry (EGP seed, multi-currency).
- Prisma kernel schema (company, branch, user, role, permission, currency, exchange rate, fiscal year/period, number sequence, audit log, attachment, notification).
- i18n (EN/AR) + RTL/LTR + design tokens/theming wired into the App Router.
- CI workflow with a **blocking accounting-invariant job**.
- Documentation set: ARCHITECTURE, SECURITY, TESTING_STRATEGY, DEPLOYMENT, DISASTER_RECOVERY, PHASE1_STATUS, plus README/ROADMAP/IMPLEMENTATION_STATUS.
- Design system built in Figma ("Mini ERP — Design System & UI") + live style-guide.html.

### Added — Phase 1 application layer (real + unit-tested)
- **Auth:** credentials authentication service (anti-enumeration, generic errors, no hash leakage), Argon2id hasher adapter, fixed-window rate limiter, session + route guards.
- **RBAC:** full permission catalog (24 modules × actions + sensitive capabilities), 9 deny-by-default role templates (SUPER_ADMIN…VIEWER), pure seed plan + Prisma seed.
- **Tenant:** server-derived tenant context + cross-company isolation guards.
- **Audit:** append-only audit service with field diff, sensitive-field redaction, requestId.
- **Numbering:** configuration + allocation application service over the concurrency-safe engine.
- **Attachments:** storage abstraction + validation + company scope + local-disk adapter.
- **Notifications:** in-app notification service (create/list/read, company scope, channel interface).
- **Jobs:** queue-agnostic job runner (idempotency + exponential backoff) + pg-boss adapter + worker entrypoint.
- **Company:** company/branch onboarding + settings service (validated; owner admin role seeded).

### Added — Phase 1 integration layer
- **DB:** Prisma client singleton + repositories (user, audit append-only, numbering with atomic `INSERT … ON CONFLICT DO UPDATE RETURNING`). Repositories are the only DB-touching layer.
- **Auth.js:** NextAuth v5 credentials config wired to the tested auth service + Argon2 + Prisma user repo; JWT session carries server-derived companyId + RBAC grants; login screen (EN/AR, tokens, light/dark); `requireAuth` route guard.
- **CI:** now provisions a Postgres service, runs `prisma db push`, and executes the DB-gated numbering-concurrency integration test alongside the blocking invariant suite. Working directory set to `app/`; triggers on main + develop.

### Added / Fixed — toolchain hardening (verified via real install)
- Generated **package-lock.json** (CI `npm ci` now works).
- **TS-aware ESLint** (typescript-eslint) — `npm run lint` passes clean at `--max-warnings=0`.
- Fixed **pg-boss v10** adapter (batch `Job[]` work handler, `pollingIntervalSeconds`, `includeMetadata`).
- Fixed login **server-action signature** (+ generic error display via `?error=1`).
- Lint/type nits: `const` in money.allocate, unused imports, test cast, tailwind token typing.

### Verification (this increment, real tooling)
- `npm install` (319 pkgs) ✓ · `eslint --max-warnings=0` ✓ · `vitest` 57 passed / 1 skipped ✓.
- `tsc --noEmit`: only 5 errors remain, all from the **ungenerated Prisma client** (blocked binaries.prisma.sh in the sandbox); CI's `prisma generate` resolves them.

### Tests
- 57 passing + 1 DB-gated integration (skips without DATABASE_URL, runs in CI). Invariant suite intact.

### Notes
- GitHub remote not yet connected — session token is repo-bound and no repo is enabled for this session (see IMPLEMENTATION_STATUS → Remote).
