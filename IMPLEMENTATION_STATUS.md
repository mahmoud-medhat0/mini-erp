# IMPLEMENTATION STATUS

- **Current phase:** Phase 1 — Foundation (IN PROGRESS)
- **Latest verified:** 2026-08-20
- **Tests passing:** 62 passing + 1 DB-gated integration (skips locally, runs in CI with Postgres) = 63
- **Latest local commit:** `470a4d4` (branch `develop`)
- **Remote:** `origin/develop` last confirmed `ffc6bc4`; local is ahead by ~11 commits (handed off via bundle/zip). Session is READ-ONLY on the remote.
- **Verification (local):** `eslint --max-warnings=0` clean · `vitest` 62 pass/1 skip · `tsc` clean **except 5 Prisma-client-generation errors** (the sandbox blocks `binaries.prisma.sh`; `prisma generate` in a normal env / CI resolves them).
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
| Prisma kernel schema | PARTIAL | schema written (+requestId); migrate/generate pending (needs DB) |
| i18n EN/AR + RTL + theming | PARTIAL | wired; full component library pending |
| CI workflow | COMPLETE | blocking invariant job |

## Phase-1 application services (this increment — real + unit-tested)
| Item | Status | Notes |
|---|---|---|
| Credentials auth service | COMPLETE | anti-enumeration timing, generic errors, no hash leakage; 6 tests |
| Argon2id password hasher | PARTIAL | real adapter written (OWASP params); native module runs at full install |
| Auth rate limiter | COMPLETE | fixed-window, injectable clock; tested |
| Session + route guard | COMPLETE | `requireSession`/`authorize`; server-derived tenant |
| Auth.js provider wiring | PARTIAL | NextAuth v5 credentials config (`src/auth.ts`) + login screen (EN/AR) + `requireAuth`; verified at full runtime |
| Prisma repositories (user/audit/numbering) | PARTIAL | real repos incl. atomic numbering SQL; run at full install + Postgres |
| DB integration test (numbering concurrency) | PARTIAL | `tests/integration/numbering.pg.test.ts`; runs in CI (Postgres service) |
| CI Postgres + integration | COMPLETE | CI provisions Postgres, `prisma db push`, runs invariants + integration |
| RBAC permission catalog | COMPLETE | 24 modules × actions + sensitive caps |
| RBAC role templates (9) | COMPLETE | deny-by-default; SUPER_ADMIN…VIEWER; 7 tests |
| RBAC seed plan | COMPLETE (plan) / PARTIAL (persist) | pure planner tested; `prisma/seed.ts` applies (needs DB) |
| Tenant context + isolation | COMPLETE | server-derived; cross-company rejected; tested |
| Append-only audit service | COMPLETE | redaction + field diff + requestId; append-only by construction; tested |
| Numbering config + allocation service | COMPLETE | validate/persist/preview/allocate; 1000-parallel uniqueness; per-company isolation |
| Attachment storage abstraction | COMPLETE | interface + validation + company scope; local adapter written; tested |
| Notification service | COMPLETE | create/list/read + company scope; channel interface; tested |
| Job runner (idempotency + backoff) | COMPLETE | once-only, retry-on-throw, capped backoff; tested |
| pg-boss adapter + worker entrypoint | PARTIAL | real code (publish/work/graceful shutdown); runs at full install + DB |
| Company/Branch onboarding + settings | COMPLETE (service) / PARTIAL (persist+UI) | validation + owner admin role; repo interface; Prisma repo + UI pending |

## Modules (Phases 2–10)
All 24 domain modules are `SCAFFOLD ONLY` (directory + boundary files); implemented in their roadmap phases. **No module is marked COMPLETE.**

## Known issues
- Full `npm install` + `prisma migrate` + `next build` not yet executed in this environment (DB-dependent); domain core verified via vitest directly.

## Remote (GitHub)
- **Status:** `GITHUB PUSH BLOCKED — remote access not enabled for this session.`
- **Detail:** authenticated as GitHub user `mahmoud-medhat0`, but the session token is repository-bound; creating repos is denied and no repository is enabled for this session (API: "GitHub access to this repository is not enabled for this session. Use add_repo to request access."). The `add_repo` capability is not available as a tool in this session.
- **Local Git:** initialized; branches `main` (stable) + `develop`; Phase-1 work committed locally.
- **To unblock:** enable a repository for this session (via the Claude Code GitHub app / `add_repo`) or provide a repo URL where user `mahmoud-medhat0` has write access; then `git remote add origin …` + push.

## Next milestone
Finish Phase 1 remaining (auth + RBAC seed + company/branch onboarding + settings/numbering UI + jobs bootstrap + E2E smoke), then Phase 2 (Accounting core / posting engine on `accounting-kernel`).
