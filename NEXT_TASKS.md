# NEXT TASKS — actionable checklist

Ordered. Each task: what to build · key files · acceptance. Follow the verification gate (lint → typecheck → tests → invariants → secret scan) and commit in small conventional commits. Keep `IMPLEMENTATION_STATUS.md` honest.

## Phase 1 — remaining (finish before tagging v0.1.0)

### T1. Company/branch onboarding (first-run)
- **Build:** full `PrismaCompanyRepository` implementing `CompanyRepository` (`modules/company/application/companyService.ts`): `createCompany` must, in a transaction, create the company, seed the **9 role templates for that company** with their permission links (from `core/rbac/seed.ts` + `roles.ts` + `catalog.ts`), add the owner membership, and assign `COMPANY_ADMIN`. Onboarding screen at `app/[locale]/onboarding` (create company + first branch) shown when the user has no company.
- **Files:** `core/db/repositories/companyRepo.ts`, `app/[locale]/onboarding/*`, wire into `(app)/layout` (redirect to onboarding if no active company).
- **Accept:** creating a company seeds roles+permissions rows; owner can sign in and reach the shell; company isolation holds. Add an integration test (DB-gated) that creates a company and asserts 9 roles + permission links exist.

### T2. Users & Roles settings screen
- **Build:** list users in company, list roles + their permissions (read from RBAC), assign/revoke roles to a user (server action → `UserRole`).
- **Files:** `app/[locale]/(app)/settings/users/*`, a `PrismaUserAdminRepository`.
- **Accept:** assigning a role changes the user's effective grants (loaded in `auth.ts` jwt callback via `loadGrants`); permission-denied state shows server-side.

### T3. Attachments end-to-end
- **Build:** `PrismaAttachmentRepository` (metadata) + an upload route handler that validates (mime/size/name), stores via `AttachmentStorage` (local adapter in dev), and a download route enforcing company scope.
- **Accept:** upload persists metadata + blob; cross-company download rejected; unit test for the service already exists — add a route test.

### T4. Notifications foundation UI
- **Build:** `PrismaNotificationRepository` + header bell + `/notifications` center (list, mark read), scoped per user/company.
- **Accept:** notifications persist and list per user; mark-read works; cross-company mark rejected (service test exists).

### T5. Playwright smoke E2E
- **Build:** `playwright.config.ts` + `tests/e2e/*`. Scenarios: unauthenticated → redirect to `/login`; invalid login → error; valid login → dashboard; navigate to a settings screen; permission-denied path. Run each in **EN and AR**, assert `dir=rtl` for AR, and toggle `data-theme` light/dark. Add a CI job that builds the app, runs migrations + seed against Postgres, seeds a test user (argon2 hash), starts the server, runs Playwright.
- **Accept:** E2E green in CI; assertions verify real rendered/redirect state (no fake success).

### T6. `next build` in CI + DoD flip
- **Build:** add a `build` step to CI (after `prisma generate`). Only after a confirmed green Actions run.
- **Accept:** CI green end-to-end (npm ci → prisma generate → typecheck → lint → invariants → unit+integration → e2e → build). Flip the Phase-1 DoD checklist in `IMPLEMENTATION_STATUS.md`; tag `v0.1.0-phase1-foundation` and push the tag.

## Phase 2 — Accounting core (start only after Phase 1 DoD)
Build on `core/accounting-kernel`.
- CoA (`account`, groups, types, hierarchy), JournalEntry/Line, LedgerEntry, FiscalYear/Period, opening balances, exchange rates.
- **Posting engine** (`core/accounting/posting`): resolve accounts from configurable mapping → build balanced lines (base+txn currency) → assert period open + Σdr=Σcr → write JE+lines+ledger+subledger atomically, idempotent (idempotency key from `postingIdempotencyKey`), reversible. UI **Accounting tab** to inspect the generated entry.
- Reports: General Journal, General Ledger, Trial Balance, then Financial Statements.
- **New blocking invariant tests:** subledger=GL control, posted immutable, closed-period rejects posting, balance sheet balances. Reference: `spec/ACCOUNTING_EVENT_MAP.md`, `spec/BUSINESS_RULES.md`, `spec/WORKFLOW_CATALOG.md`, `spec/DATABASE_DESIGN.md`.

## Definition of Done reminder (per feature)
DB schema + migration + domain + application service + validation + permissions + workflow + accounting/subledger integration + audit + numbering + jobs (if any) + UI (list/create/edit/detail + loading/empty/error/permission states) + reports + export/print (where applicable) + EN/AR + RTL/LTR + light/dark + responsive + unit/integration/e2e tests + invariant tests + docs + traceability update. Only then mark COMPLETE.
