# IMPLEMENTATION STATUS

- **Current phase:** Phase 1 — Laravel migration foundation (in progress)
- **Latest verified:** 2026-08-21 (local Laravel + PostgreSQL)
- **Tests passing:** Laravel PHPUnit 29/29, including migrated page coverage and Concurrency 7/7. PostgreSQL `concurrency:stress --workers=100` remains the required manual stress check.
- **Latest verified code commit:** local `develop` worktree; see `git log` after commit.
- **Remote/CI:** No GitHub Actions pipeline is connected for this Laravel migration track.
- **Verification:** `php artisan migrate --force` clean · `php artisan test` clean · `vendor\bin\pint --test` clean · `npm run typecheck` clean · `npm run build` clean · `composer validate --strict` clean · `php artisan concurrency:stress --workers=100` clean.
- **Handoff:** see `DOMAIN_MODEL_REVIEW.md` first for the Laravel architecture correction, then `CONTINUE_HERE.md` and `NEXT_TASKS.md` as historical Next.js reference material.

## Legend
`COMPLETE` fully implemented + tested · `PARTIAL` partially implemented · `SCAFFOLD ONLY` structure without logic.

## Laravel migration track
| Item | Status | Notes |
|---|---|---|
| M2 Inertia foundation | COMPLETE | Laravel app boots with Inertia/Vite and health route |
| Domain model review | COMPLETE | `DOMAIN_MODEL_REVIEW.md` classifies Company/Branch as business scopes, not SaaS tenants |
| M3 database foundation | PARTIAL | Native `users` plus company/branch business tables and non-team Spatie RBAC seeders; domain relationships beyond the spec review must not be assumed |
| M5 session auth backend | COMPLETE | Login/logout, Argon2id, throttling, active users, bootstrap admin, protected foundation route |
| M6 migrated Inertia pages | COMPLETE | Dashboard, settings hub, companies, branches, numbering, users/roles, notifications, app shell, and notification read action backed by real Laravel data |
| Removed tenant assumptions | COMPLETE | Laravel tenant context/middleware/onboarding and Spatie `company_id` teams removed |
| Concurrency hardening | COMPLETE | `idempotency_keys`, optimistic locks, PostgreSQL number allocation, bounded auth token GC, notification dedupe, audit doc, and stress/test coverage |

## Core kernel
| Item | Status | Notes |
|---|---|---|
| Money (exact minor units, allocation) | COMPLETE | 9 tests incl. 500-case property |
| Accounting kernel (Σdr=Σcr guard) | COMPLETE | 5 tests |
| Numbering (format + atomic allocate) | COMPLETE | 4 tests incl. 1000-parallel uniqueness |
| RBAC (server-side permission + scope checks) | COMPLETE | 5 tests |
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
| Session + route guard | COMPLETE | `requireSession`/`authorize`; no tenant/current-company is inferred from auth |
| Auth.js provider wiring | COMPLETE | NextAuth v5 credentials config + login screen + `requireAuth`; DB-backed E2E verified |
| Prisma repositories | COMPLETE (Phase-1 scope) | user/audit/numbering/settings/branch/company/user-admin/attachments/notifications real repos; DB paths verified with Postgres |
| DB integration tests | COMPLETE | numbering and foundation DB paths run against PostgreSQL |
| CI Postgres + integration | COMPLETE | root workflow provisions Postgres, pushes schema, seeds, runs invariants + integration + build + E2E |
| RBAC permission catalog | COMPLETE | 24 modules × actions + sensitive caps |
| RBAC role templates (9) | COMPLETE | deny-by-default; SUPER_ADMIN…VIEWER; 7 tests |
| RBAC seed plan | COMPLETE | pure planner tested; `prisma/seed.ts` verified with DB |
| Tenant context + isolation | REMOVED | Incorrect SaaS assumption; use explicit business authorization scopes only |
| Append-only audit service | COMPLETE | redaction + field diff + requestId; append-only by construction; tested |
| Numbering config + allocation service | COMPLETE | validate/persist/preview/allocate; 1000-parallel uniqueness; per-company business uniqueness |
| Attachment storage abstraction | COMPLETE | interface + validation + company scope; local adapter written; tested |
| Attachment metadata + routes | PARTIAL | Prisma metadata repo + upload/download route handlers added; route-level mocked auth/storage test passes; DB-backed route test pending |
| Notification service | COMPLETE | create/list/read + company scope; channel interface; tested |
| Notifications persistence + UI | PARTIAL | Prisma repo + header link + `/notifications` center added; full DB/E2E verification runs in CI |
| Job runner (idempotency + backoff) | COMPLETE | once-only, retry-on-throw, capped backoff; tested |
| pg-boss adapter + worker entrypoint | PARTIAL | real code (publish/work/graceful shutdown); runs at full install + DB |
| Company/Branch settings | PARTIAL | company/branch business screens exist in the Next reference; SaaS-style first-run onboarding is not a Laravel target unless revalidated by the spec |
| Users & Roles settings | PARTIAL | `/settings/users` lists users/roles and assigns/revokes roles with server-side RBAC; DB/E2E path gated |
| Playwright smoke E2E | COMPLETE | 5/5 against real Postgres: locale direction, redirect, invalid login, admin dashboard/settings, viewer permission denied |

## Modules (Phases 2–10)
All 24 domain modules are `SCAFFOLD ONLY` (directory + boundary files); implemented in their roadmap phases. **No module is marked COMPLETE.**

## Known issues
- Next.js build has non-blocking warnings: root detection (`turbopack.root`) and deprecated `middleware` convention.
- DB-backed integration/E2E requires `DATABASE_URL`; without it those suites intentionally skip or cannot exercise credential paths.
- No GitHub Actions workflow is connected for the Laravel migration track; verification is currently local.

## Remote (GitHub)
- **Status:** Not used for the current Laravel migration verification.
- **CI:** Not connected.

## Next milestone
Continue the Laravel migration with the next explicitly requested backend slice; accounting posting must use the concurrency primitives documented in `docs/CONCURRENCY_AUDIT.md`.
