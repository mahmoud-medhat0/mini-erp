# IMPLEMENTATION STATUS

- **Current phase:** Phase 1 — Foundation (IN PROGRESS)
- **Latest verified:** 2026-08-21
- **Tests passing:** 64 passing + 2 DB-gated integrations (skip locally without `DATABASE_URL`) = 66. Playwright smoke: 2 local pass + 3 DB-gated skipped; CI E2E provisions Postgres.
- **Latest local commit:** `470a4d4` (branch `develop`)
- **Remote:** `origin/develop` last confirmed `ffc6bc4`; local is ahead by ~11 commits (handed off via bundle/zip). Session is READ-ONLY on the remote.
- **Verification (local):** `prisma generate` clean · `eslint --max-warnings=0` clean · `tsc --noEmit` clean · `vitest` 64 pass/2 skipped · `next build` clean · `playwright` smoke 2 pass/3 DB-gated skipped.
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
| Prisma kernel schema | PARTIAL | schema written (+attachment mime/size); generate verified locally; DB push/migrate runs in CI |
| i18n EN/AR + RTL + theming | PARTIAL | nested next-intl messages fixed; Tailwind/PostCSS build verified; full component library pending |
| CI workflow | COMPLETE | blocking invariant job |

## Phase-1 application services (this increment — real + unit-tested)
| Item | Status | Notes |
|---|---|---|
| Credentials auth service | COMPLETE | anti-enumeration timing, generic errors, no hash leakage; 6 tests |
| Argon2id password hasher | PARTIAL | real adapter written (OWASP params); native module runs at full install |
| Auth rate limiter | COMPLETE | fixed-window, injectable clock; tested |
| Session + route guard | COMPLETE | `requireSession`/`authorize`; server-derived tenant |
| Auth.js provider wiring | PARTIAL | NextAuth v5 credentials config (`src/auth.ts`) + login screen (EN/AR) + `requireAuth`; verified at full runtime |
| Prisma repositories | PARTIAL | user/audit/numbering/settings/branch/company/user-admin/attachments/notifications real repos; DB paths run with Postgres |
| DB integration tests | PARTIAL | numbering + company onboarding provisioning; run in CI/Postgres, skip locally without `DATABASE_URL` |
| CI Postgres + integration | COMPLETE | CI provisions Postgres, `prisma db push`, runs invariants + integration; E2E job added |
| RBAC permission catalog | COMPLETE | 24 modules × actions + sensitive caps |
| RBAC role templates (9) | COMPLETE | deny-by-default; SUPER_ADMIN…VIEWER; 7 tests |
| RBAC seed plan | COMPLETE (plan) / PARTIAL (persist) | pure planner tested; `prisma/seed.ts` applies (needs DB) |
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
| Playwright smoke E2E | PARTIAL | config + smoke tests + CI job added; local public/redirect checks pass, credential checks need `DATABASE_URL` |

## Modules (Phases 2–10)
All 24 domain modules are `SCAFFOLD ONLY` (directory + boundary files); implemented in their roadmap phases. **No module is marked COMPLETE.**

## Known issues
- Local DB-backed integration/E2E credential tests require `DATABASE_URL`; they skip locally when no Postgres is configured.
- CI still needs a remote green run before Phase-1 DoD can be flipped or `v0.1.0-phase1-foundation` tagged.
- Next.js build has non-blocking warnings: root detection (`turbopack.root`) and deprecated `middleware` convention.

## Remote (GitHub)
- **Status:** `GITHUB PUSH BLOCKED — remote access not enabled for this session.`
- **Detail:** authenticated as GitHub user `mahmoud-medhat0`, but the session token is repository-bound; creating repos is denied and no repository is enabled for this session (API: "GitHub access to this repository is not enabled for this session. Use add_repo to request access."). The `add_repo` capability is not available as a tool in this session.
- **Local Git:** initialized; branches `main` (stable) + `develop`; Phase-1 work committed locally.
- **To unblock:** enable a repository for this session (via the Claude Code GitHub app / `add_repo`) or provide a repo URL where user `mahmoud-medhat0` has write access; then `git remote add origin …` + push.

## Next milestone
Run DB-backed CI/E2E, then wire `next build` into CI after the first green run and flip Phase-1 DoD. Phase 2 remains blocked until Phase 1 is genuinely complete.
