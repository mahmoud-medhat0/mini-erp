# IMPLEMENTATION STATUS

- **Current phase:** Phase 1 — Laravel migration foundation (Company/Branch/User relationship correction complete; migration continues)
- **Latest verified:** 2026-08-21 (local Laravel + PostgreSQL, through post-audit correction pass)
- **Tests passing:** Laravel PHPUnit 61/61, 795 assertions; Concurrency suite 7/7, 16 assertions. PostgreSQL `concurrency:stress --workers=100` passed.
- **Latest verified code commit:** pending for the current M7-M10 worktree; previous commit `7f1d673` ported Laravel app pages.
- **Remote/CI:** No GitHub Actions pipeline is connected for this Laravel migration track.
- **Verification:** `php artisan migrate --force` clean · `php artisan migrate:status` clean · `php artisan test` clean · `php artisan test --testsuite=Concurrency` clean · `php artisan concurrency:stress --workers=100` clean · `php artisan tokens:gc --batch=100` clean · `vendor\bin\pint --test` clean. TypeScript/build were not rerun in this backend pass.
- **Latest migrated slice:** FiscalYear ownership/context correction: `fiscal_year.company_id` removed, `fiscal_year.year` globally unique, and `financial_period.fiscal_year_id` preserved. Bootstrap admin role seeding and `/settings/users/roles` route ordering also corrected. No company, branch, tenant, or current-company scope was introduced.
- **Handoff:** see `DOMAIN_MODEL_REVIEW.md` first for the Laravel architecture correction, then `CONTINUE_HERE.md` and `NEXT_TASKS.md` as historical Next.js reference material.

## Legend
`COMPLETE` fully implemented + tested · `PARTIAL` partially implemented · `SCAFFOLD ONLY` structure without logic.

Statuses in the Laravel migration table are current. Older Next.js/Prisma status sections are historical reference only and must not be used to restore tenant/company scope or to claim the Laravel ERP modules are complete.

## Laravel migration track
| Item | Status | Notes |
|---|---|---|
| M2 Inertia foundation | COMPLETE | Laravel app boots with Inertia/Vite and health route |
| Domain model review | COMPLETE | `DOMAIN_MODEL_REVIEW.md` now applies the stricter evidence rule: undefined relationships are not assumed |
| M3 database foundation | PARTIAL | Native `users`, company configuration, standalone branch reference records, global fiscal years/periods, and non-team Spatie RBAC seeders; unsupported Company/User, Company/Branch, and Company/FiscalYear relationships removed |
| M5 session auth backend | COMPLETE | Login/logout, Argon2id, throttling, active users, explicit bootstrap admin role assignment, protected foundation route |
| M6 migrated Inertia pages | COMPLETE | Dashboard, settings hub, companies, branches, numbering, users/roles, notifications, app shell, and notification read action backed by real Laravel data |
| M7 Laravel core kernel parity | COMPLETE | Money, currency registry, accounting invariant, domain errors, number formatter/config, and Laravel invariant tests |
| M8 page actions | COMPLETE | Company/branch/numbering actions and role assign/revoke use explicit IDs, validation, permissions, optimistic locks where available, and no tenant/current-company session |
| M9 attachments + notifications | COMPLETE | Attachment upload/download service/routes, explicit allowlisted entity authorization, storage cleanup compensation, and notification service with per-user dedupe/list/mark-read behavior, without invented company scope |
| M10 audit + jobs/scheduler | COMPLETE | Append-only audit logger, idempotent job runner/backoff primitive, and hourly `tokens:gc` schedule |
| Removed relationship assumptions | COMPLETE | `company_user`, `branch.company_id`, Company/Branch Eloquent links, `fiscal_year.company_id`, `number_sequence.company_id`, `number_sequence.include_branch`, and unsupported audit/attachment/notification `company_id` removed |
| Removed tenant assumptions | COMPLETE | Laravel tenant context/middleware/onboarding, currentCompany/currentBranch, and Spatie `company_id` teams removed |
| Concurrency hardening | COMPLETE | `idempotency_keys`, optimistic locks, PostgreSQL number allocation by sequence key, bounded auth token GC, notification dedupe, attachment failure compensation, audit doc, and stress/test coverage |

## Core kernel status

Laravel has the Money, accounting-invariant, numbering, RBAC foundation, error, and currency primitives listed below where they are also referenced in the Laravel migration table. Prisma/Next.js rows are historical reference only.
| Item | Status | Notes |
|---|---|---|
| Money (exact minor units, allocation) | COMPLETE | 9 tests incl. 500-case property |
| Accounting kernel (Σdr=Σcr guard) | COMPLETE | 5 tests |
| Numbering (format + atomic allocate) | COMPLETE | 4 tests incl. 1000-parallel uniqueness |
| RBAC (server-side permission checks) | COMPLETE | global Spatie roles/permissions; `scope_json` reserved/undefined |
| Errors (typed domain errors) | COMPLETE | used across suite |
| Currency registry | COMPLETE | EGP seed, multi-currency |
| Prisma kernel schema | LEGACY_REFERENCE | old Next.js reference; not current Laravel source of truth |
| i18n EN/AR + RTL + theming | PARTIAL | nested next-intl messages fixed; Tailwind/PostCSS build verified; full component library pending |
| CI workflow | LEGACY_REFERENCE | old Next.js workflow; no GitHub Actions pipeline is connected for the Laravel migration track |

## Historical Next.js Phase-1 application services

The section below documents the old Next.js reference/migration history. It is not current Laravel architecture and must not be used to restore tenant/company scope.
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
| Numbering config + allocation service | COMPLETE | validate/persist/preview/allocate; 1000-parallel uniqueness; Laravel target uses sequence key without company/branch dimensions |
| Attachment storage abstraction | COMPLETE | interface + validation + entity metadata; local adapter written; tested |
| Attachment metadata + routes | PARTIAL | Prisma metadata repo + upload/download route handlers added; route-level mocked auth/storage test passes; DB-backed route test pending |
| Notification service | COMPLETE | create/list/read + per-user dedupe; channel interface; tested |
| Notifications persistence + UI | PARTIAL | Prisma repo + header link + `/notifications` center added; full DB/E2E verification runs in CI |
| Job runner (idempotency + backoff) | COMPLETE | once-only, retry-on-throw, capped backoff; tested |
| pg-boss adapter + worker entrypoint | PARTIAL | real code (publish/work/graceful shutdown); runs at full install + DB |
| Company/Branch settings | PARTIAL | company configuration and standalone branch reference screens exist; Company->Branch remains undefined until explicitly specified |
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
