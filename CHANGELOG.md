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

### Added — reusable UI + app shell (locally typechecked + linted)
- UI primitives: **Button** (primary/secondary/ghost/danger + loading/disabled), **Input** (label/error/hint), **StatusBadge** (colour + dot + label, never colour-alone), **Card / PageHeader / EmptyState / PermissionDenied**. Token-styled, RTL-safe via logical CSS properties, light/dark via variables.
- **AppShell** (sidebar + topbar, localized nav, active state) and a **protected route group** (`(app)/layout` enforces `requireAuth`) with a **dashboard shell** that shows an EmptyState — no mock KPIs.
- Verified: `eslint --max-warnings=0` clean; `vitest` 57 passed; `tsc` adds zero new errors.

### Added — auth route + Settings (locally verified)
- **NextAuth route handler** (`/api/auth/[...nextauth]`, Node runtime) — credentials flow is now end-to-end.
- **Company settings**: `SettingsService` (validated) + `PrismaSettingsRepository` (JSON column `settingsJson`), a **Settings hub** and a **Company settings screen** (currency/locale/timezone/fiscal-start) built from the UI components, EN/AR, server-action persistence with server-derived tenant context.
- Verified: `eslint --max-warnings=0` clean; `vitest` **60 passed**; `tsc` clean except Prisma-client generation (CI).

### Added — Branches + Numbering settings (locally verified)
- **BranchService** (unique code per company, validation) + `PrismaBranchRepository` + tests.
- **Branches settings screen** (list + add) and **Numbering settings screen** (list configs + add/update sequence with reset policy + next-number preview), wired to the tested services, EN/AR, server-derived tenant context.
- Verified: lint clean; `tsc` clean except Prisma-client generation (CI); `vitest` **62 passed / 1 skipped**.

### Added — onboarding, users, attachments, notifications, and E2E smoke
- **First-run onboarding**: `/[locale]/onboarding` plus `PrismaCompanyRepository` that atomically creates company + first branch + global permissions + 9 company role templates + owner membership + `COMPANY_ADMIN`.
- **Users & Roles settings**: `PrismaUserAdminRepository`, `UserAdminService`, and `/settings/users` for listing users/roles and assigning/revoking roles with server-side RBAC permission-denied state.
- **Attachments end-to-end foundation**: attachment schema now stores `mime` + `size`; added Prisma metadata repository and scoped upload/download route handlers backed by the local storage adapter.
- **Notifications UI**: `PrismaNotificationRepository`, header notifications link/count, `/notifications` center, and mark-read action.
- **Playwright smoke E2E**: config + smoke suite for locale direction, unauthenticated redirect, DB-backed login, dashboard/settings navigation, and permission-denied path; CI job provisions Postgres and installs Chromium.

### Fixed — runtime/build blockers
- Converted next-intl locale messages from flat dotted keys to nested objects, fixing `INVALID_KEY` / `MISSING_MESSAGE` runtime errors.
- Added PostCSS config for Tailwind directives and converted `design/tailwind.tokens.js` to ESM, fixing Next/Turbopack build failures.
- Fixed Prisma JSON typing in settings persistence.

### Verification — 2026-08-21
- `prisma generate` ✓ · `eslint --max-warnings=0` ✓ · `tsc --noEmit` ✓ · `vitest` **64 passed / 2 skipped** ✓ · `next build` ✓ · `playwright` smoke **2 passed / 3 DB-gated skipped** locally.

### Tests
- 64 passing + 2 DB-gated integrations (skip without DATABASE_URL, run in CI). Invariant suite intact. Playwright smoke is configured; credential/permission tests run with Postgres.

### Notes
- GitHub remote not yet connected — session token is repo-bound and no repo is enabled for this session (see IMPLEMENTATION_STATUS → Remote).
