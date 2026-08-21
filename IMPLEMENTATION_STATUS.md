# IMPLEMENTATION STATUS

- **Current phase:** Phase 1 — Foundation (COMPLETE)
- **Latest verified:** 2026-08-21 (local PostgreSQL + GitHub Actions)
- **Tests passing:** Vitest 66/66 with DB-backed integration enabled. Invariants 23/23. Playwright smoke 5/5 with DB-backed credential/RBAC paths.
- **Latest verified code commit:** `1c9737f` (branch `develop`)
- **Remote:** `origin/develop` pushed successfully; CI run `32440676342` completed `success`.
- **Verification:** `npm ci` clean · `prisma generate` clean · `prisma db push` clean · `prisma seed` clean · `npm run ci` clean · `next build` clean · `playwright` smoke 5/5.
- **Handoff:** see `CONTINUE_HERE.md` and `NEXT_TASKS.md` at repo root.

## Legend
`COMPLETE` fully implemented + tested · `PARTIAL` partially implemented · `SCAFFOLD ONLY` structure without logic.

## Core kernel
| Item | Status | Notes |
|---|---|---|
| Money (exact minor units, allocation) | COMPLETE | 9 tests incl. 500-case property |
| Accounting kernel (Σdr=Σcr guard) | COMPLETE | 5 tests |
| Numbering (format + atomic allocate) | COMPLETE | 4 tests incl. 1000-parallel uniqueness |
| RBAC (server-side, scope, tenant isolation) | COMPLETE | 5 tests |
| Errors (typed domain errors) | COMPLETE | used across suite |
| Currency registry | COMPLETE | EGP seed, multi-currency |
| Prisma kernel schema | COMPLETE | schema written (+attachment mime/size); generate + db push verified against PostgreSQL |
| i18n EN/AR + RTL + theming | PARTIAL | nested next-intl messages fixed; Tailwind/PostCSS build verified; full component library pending |
| CI workflow | COMPLETE | blocking invariant job |

## Phase-1 application services (this increment — real + unit-tested)
| Item | Status | Notes |
|---|---|---|
| Credentials auth service | COMPLETE | anti-enumeration timing, generic errors, no hash leakage; 6 tests |
| Argon2id password hasher | PARTIAL | real adapter written (OWASP params); native module runs at full install |
| Auth rate limiter | COMPLETE | fixed-window, injectable clock; tested |
| Session + route guard | COMPLETE | `requireSession`/`authorize`; server-derived tenant |
| Auth.js provider wiring | COMPLETE | NextAuth v5 credentials config + login screen + `requireAuth`; DB-backed E2E verified |
| Prisma repositories | COMPLETE (Phase-1 scope) | user/audit/numbering/settings/branch/company/user-admin/attachments/notifications real repos; DB paths verified with Postgres |
| DB integration tests | COMPLETE | numbering + company onboarding provisioning run against PostgreSQL |
| CI Postgres + integration | COMPLETE | root workflow provisions Postgres, pushes schema, seeds, runs invariants + integration + build + E2E |
| RBAC permission catalog | COMPLETE | 24 modules × actions + sensitive caps |
| RBAC role templates (9) | COMPLETE | deny-by-default; SUPER_ADMIN…VIEWER; 7 tests |
| RBAC seed plan | COMPLETE | pure planner tested; `prisma/seed.ts` and onboarding persistence verified with DB |
| Tenant context + isolation | COMPLETE | server-derived; cross-company rejected; tested |
| Append-only audit service | COMPLETE | redaction + field diff + requestId; append-only by construction; tested |
| Numbering config + allocation service | COMPLETE | validate/persist/preview/allocate; 1000-parallel uniqueness; per-company isolation |
| Attachment storage abstraction | COMPLETE | interface + validation + company scope; local adapter written; tested |
| Attachment metadata + routes | PARTIAL | Prisma metadata repo + upload/download route handlers added; route-level mocked auth/storage test passes; DB-backed route test pending |
| Notification service | COMPLETE | create/list/read + company scope; channel interface; tested |
| Notifications persistence + UI | PARTIAL | Prisma repo + header link + `/notifications` center added; full DB/E2E verification runs in CI |
| Job runner (idempotency + backoff) | COMPLETE | once-only, retry-on-throw, capped backoff; tested |
| pg-boss adapter + worker entrypoint | PARTIAL | real code (publish/work/graceful shutdown); runs at full install + DB |
| Company/Branch onboarding + settings | PARTIAL | atomic Prisma onboarding repo + `/onboarding` UI added; DB-gated provisioning test added |
| Users & Roles settings | PARTIAL | `/settings/users` lists users/roles and assigns/revokes roles with server-side RBAC; DB/E2E path gated |
| Playwright smoke E2E | COMPLETE | 5/5 against real Postgres: locale direction, redirect, invalid login, admin dashboard/settings, viewer permission denied |

## Modules (Phases 2–10)
All 24 domain modules are `SCAFFOLD ONLY` (directory + boundary files); implemented in their roadmap phases. **No module is marked COMPLETE.**

## Known issues
- Next.js build has non-blocking warnings: root detection (`turbopack.root`) and deprecated `middleware` convention.
- DB-backed integration/E2E requires `DATABASE_URL`; without it those suites intentionally skip or cannot exercise credential paths.

## Remote (GitHub)
- **Status:** `origin/develop` push succeeded.
- **CI:** GitHub Actions run `32440676342` completed `success` for commit `1c9737f`.

## Next milestone
Tag `v0.1.0-phase1-foundation` after this documentation update has a green Actions run, then start Phase 2 (Accounting core) only on explicit request.
