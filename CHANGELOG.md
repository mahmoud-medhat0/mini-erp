# Changelog

All notable changes. Format: Keep a Changelog; SemVer per phase.

## [Unreleased] — Phase 1: Foundation (complete)
### Added - Phase 4 Slice 2 Sales Order prompt
- Added `PHASE_4_SLICE_2_GEMINI_PROMPT.md` as the bounded execution contract for Sales Order Backend & UX.
- Updated handoff/status documentation so the next prepared execution step is Phase 4 Slice 2, while keeping Sales Orders, invoices, delivery, inventory, AR/GL posting, COGS, VAT, and company/branch/tenant scope out of the current implementation.

### Added — Phase 4 Slice 1 Product/Service Catalog Foundation
- Created migration `2026_08_22_020000_create_phase4_slice1_catalog_tables.php` defining `unit_of_measure`, `product_category`, and `product` tables with optimistic locking, Spatie Translatable JSON columns, foreign keys, and zero prohibited tenancy/company columns.
- Created Eloquent models `UnitOfMeasure`, `ProductCategory`, `Product` with `HasTranslations`, `HasUuids`, and relationship definitions.
- Implemented domain services `UnitOfMeasureService`, `ProductCategoryService`, and `ProductService` with code normalization/uniqueness checks, optimistic locking, in-use delete prevention, and Spatie Activitylog auditing via `AuditLogger`.
- Registered `products` (`view`, `create`, `edit`, `delete`, `export`) and `uom` (`view`, `create`, `edit`, `delete`) in `config/erp_rbac.php` and `PermissionSeeder`.
- Registered `product` entity definition in `config/erp_attachments.php` mapping permissions `products.view`, `products.create`, `products.edit`, `products.delete`.
- Created catalog seeders `UnitOfMeasureSeeder` and `ProductCategorySeeder` and registered them in `DatabaseSeeder.php`.
- Created Inertia controllers `UnitOfMeasureController`, `ProductCategoryController`, and `ProductController`, web routes under `/catalog/*`, and Inertia React pages (`UnitsOfMeasure.tsx`, `ProductCategories.tsx`, `Products.tsx`).
- Updated `AppLayout.tsx` sidebar navigation with expandable "Catalog" dropdown group (no emojis, clean SVG icons).
- Created `Phase4Slice1CatalogTest.php` feature test suite (12/12 passing, 66 assertions). Verified full suite (254 passing tests, 0 TS errors, clean Pint formatting, successful Vite build).

### Added — Phase 3 Slice 10 close-out & final verification gate
- Performed repository-wide documentation audit and status synchronization across all Markdown files (`README.md`, `CONTINUE_HERE.md`, `IMPLEMENTATION_STATUS.md`, `NEXT_TASKS.md`, `MD_DOCUMENTATION_AUDIT.md`, `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md`).
- Executed 100% passing final verification gate: `php artisan migrate:status` (33/33 ran), `vendor/bin/pint --test`, `php artisan test` (242 passed, 2 skipped, 2064 assertions), `php artisan accounting:phase3-integrity-check`, `php artisan accounting:phase3-stress --workers=50`, `npm run typecheck` (0 TS errors), and `npm run build` (compiled in 998ms).
- Created `PHASE_3_FINAL_VERIFICATION_REPORT.md` close-out document.
- Formally marked **Phase 3 Slices 1–10 100% complete** for the agreed contract scope.

### Added — Phase 3 Slice 9 concurrency stress & integrity tests
- Added non-mutating integrity check command `php artisan accounting:phase3-integrity-check` covering Customer Receipts, Supplier Payments, AR/AP Allocations, Cheque Lifecycles, Bank Reconciliations, and Report read-only invariants.
- Added Phase 3 concurrency stress orchestrator command `php artisan accounting:phase3-stress {--workers=50}` executing PostgreSQL row-locking concurrency scenarios across all Phase 3 workflows.
- Created `Phase3Slice9StressIntegrityTest.php` feature test suite (6/6 passing, 262 assertions) validating receipt/payment posting idempotency, period close posting locks, allocation over-pressure bounds, report read-only behavior, integrity check artisan command, and strict anti-tenancy/company-scoping rules.
- Verified 242 total PHPUnit passing tests, 0 TypeScript errors (`npm run typecheck`), clean Pint formatting (`vendor/bin/pint --test`), and Vite asset compilation (`npm run build`).

### Added — Phase 3 Slice 8 operational/subledger reports
- Added `reports.view` permission, Reports Hub, and protected report endpoints.
- Implemented read-only report services and Inertia pages for Customer Statement, Supplier Statement, AR Aging, AP Aging, Cash Book, Bank Book, Cheque Register, Bank Reconciliation status/detail, AR to GL reconciliation, and AP to GL reconciliation.
- Added streaming CSV exports for report downloads.
- Kept reports derived from existing durable Phase 2/Phase 3 records only: no fake Sales/Purchase invoice aging, no accounting mutation, no bank import, no automatic adjustment posting, and no tenant/company/branch scope.
- Verified `Phase3Slice8ReportsTest.php` 12/12 tests / 180 assertions, `php artisan test` 236 passing tests reported after Slice 8, `vendor/bin/pint --test`, `npm run typecheck`, and `npm run build`.

### Added — Phase 3 Slice 7 Inertia pages & UX actions
- Created 13 Http Controllers (`CustomerController`, `SupplierController`, `CashAccountController`, `BankAccountController`, `CustomerOpeningBalanceController`, `SupplierOpeningBalanceController`, `CustomerReceiptController`, `SupplierPaymentController`, `ReceivableAllocationController`, `PayableAllocationController`, `IncomingChequeController`, `OutgoingChequeController`, `BankReconciliationController`).
- Registered 13 web route endpoints in `routes/web.php` covering index, store, update, post, reverse, lifecycle state transitions, and bank reconciliation line matching/finalization.
- Created 14 Inertia pages under `resources/js/Pages/` with rich aesthetics, zero emojis, full English/Arabic (RTL) support, accessible form modals, and real-time status badges.
- Implemented custom React `DatePicker.tsx` component supporting English and Arabic locales, 3x4 month/decade grid views, preset ranges, min/max bounds, and SVG navigation icons.
- Updated `AppLayout.tsx` sidebar navigation with expandable groups for AR/Customers, AP/Suppliers, and Cash/Bank/Cheques.
- Created `Phase3Slice7UiTest.php` feature test suite with 13/13 passing tests.
- Verified zero TypeScript errors with `npm run typecheck`, 0 fontaine warnings with `npm run build`, and `php artisan test` 226 total / 224 passed / 2 skipped, 1622 assertions.

### Added — Phase 3 Slice 6 bank reconciliation
- Implemented `bank_reconciliation` header and `bank_reconciliation_line` statement matching models and migration (`2026_08_22_000000_create_phase3_slice6_bank_reconciliation_tables.php`).
- Created `CashBookQueryService` and `BankBookQueryService` derived strictly from immutable posted `ledger_entry` rows.
- Implemented `BankReconciliationService` handling draft creation, statement line management, candidate ledger entry lookup, line matching, unmatching, dynamic summary computation, and strict zero-difference finalization checks.
- Added PostgreSQL partial unique index `bank_recon_line_matched_ledger_unique` to prevent duplicate ledger entry matching globally across statement lines.
- Registered RBAC permission `banks.reconcile` and attachment entity `bank_reconciliation`.
- Built `accounting:bank-reconciliation-concurrency-stress --workers=50` command verifying concurrent duplicate-match protection and idempotent finalization.
- Hardened matching date/currency validation, deterministic header-first lock ordering, and DB-level immutability triggers for finalized reconciliation headers/lines.
- Verified with `php artisan test` 213 total / 211 passed / 2 PostgreSQL-specific skipped, 1510 assertions; Phase 3 Slice 6 suite 11/11; Concurrency suite 7/7; PostgreSQL concurrency/accounting/allocation/cheque/bank-reconciliation stress commands; TypeScript typecheck; and Vite build.
### Added — Phase 3 Slice 6 bank reconciliation prompt
- Added `PHASE_3_SLICE_6_GEMINI_PROMPT.md` as the bounded execution contract for ledger-backed bank reconciliation, cash/bank book query foundations, strict reconciliation lifecycle, duplicate-match/finalize concurrency stress, Spatie-backed audit, and attachment/RBAC integration.
- Explicitly kept bank statement import, bank feed/OCR parsing, automatic bank adjustment posting, broad Slice 7 UI, Sales/Purchasing/Inventory, and full financial statements out of Slice 6.

### Added — Phase 3 Slice 5 cheque lifecycle
- Added `incoming_cheque` and `outgoing_cheque` records with pre-clear state machines for incoming receive/deposit/clear/bounce/return and outgoing issue/clear/return/cancel.
- Added configurable `cheques_under_collection` and `cheques_payable` accounting mappings without company, branch, or tenant dimensions.
- Routed cheque accounting effects through the existing PostingEngine and preserved AR/AP subledger effects for received/issued and bounced/returned/cancelled pre-clear cheques.
- Added idempotent cheque transition services, attachment entity registry entries, Spatie Activitylog audit writes through `AuditLogger`, and owner-decision guards for post-clear bounce/return workflows.
- Hardened cheque concurrency with `accounting:cheque-concurrency-stress --workers=50`, covering concurrent clear replay, incoming clear-vs-bounce races, and outgoing duplicate clear prevention.
- Verified with `php artisan test` 202 total / 200 passed / 2 PostgreSQL-specific skipped, 1464 assertions; Phase 3 Slice 5 suite 8/8; Concurrency suite 7/7; PostgreSQL concurrency/accounting/allocation/cheque stress commands; TypeScript typecheck; and Vite build.
- Added `PHASE_3_SLICE_5_GEMINI_PROMPT.md` as the historical bounded execution contract; bank reconciliation, reports, broad cheque register UI, Sales/Purchasing/Inventory, and post-clear cheque bounce/return semantics remain outside Slice 5.

### Added — Phase 3 Slice 4 allocation engine
- Added `receivable_allocation` and `payable_allocation` settlement records with restrict foreign keys and PostgreSQL row checks.
- Added CustomerReceipt-to-ReceivableEntry and SupplierPayment-to-PayableEntry allocation/reversal services without creating GL, journal, ledger, receivable, or payable posting rows.
- Preserved `allocated_minor + unapplied_minor = amount_minor` on receipts/payments while preventing AR/AP over-allocation.
- Hardened allocation concurrency with deterministic parent/target/allocation lock order, active allocation row locking before remaining-balance calculation, and idempotent create/reversal commands.
- Reworked `accounting:allocation-concurrency-stress --workers=50` to use true concurrent workers for AR and AP allocation pressure plus shared idempotency replay checks.
- Verified with `php artisan test` 194 total / 192 passed / 2 PostgreSQL-specific skipped, 1413 assertions; Phase 3 Slice 4 suite 7/7; Concurrency suite 7/7; PostgreSQL concurrency/accounting/allocation stress commands; TypeScript typecheck; and Vite build.

### Added — Phase 3 Slice 3 customer receipts and supplier payments
- Added Customer Receipt and Supplier Payment draft/post services using the existing Accounting PostingEngine only.
- Added global receipt/payment numbering with `REC-YYYY-XXXXX` and `PAY-YYYY-XXXXX`.
- Added AR/AP subledger effects and unapplied balance tracking for posted receipts/payments without implementing allocation behavior yet.
- Hardened receipt/payment integrity with linked GL currency validation, delete restriction for referenced customer/supplier rows, status checks, amount checks, `allocated + unapplied = amount`, and exactly-one CashAccount/BankAccount checks.
- Verified with `php artisan test` 187 total / 185 passed / 2 PostgreSQL-specific skipped, 1377 assertions; Concurrency suite 7/7; Phase 3 Slice 3 suite 14 total / 12 passed / 2 PostgreSQL-specific skipped; PostgreSQL stress commands; TypeScript typecheck; and Vite build.

### Added — Phase 3 Slice 2 AR/AP subledgers and opening balances
- Added Customer and Supplier opening-balance services that post through the existing Accounting PostingEngine and create durable `receivable_entry` / `payable_entry` subledger rows.
- Added global accounting account mappings for `ar_control`, `ap_control`, and `opening_balance_offset`, with account classification, active-account, and currency validation.
- Added PostgreSQL integrity hardening for active opening-balance uniqueness, source uniqueness, statuses, and positive/non-negative accounting amounts.
- Hardened Slice 2 validation so financial periods must belong to the selected fiscal year, duplicate active opening balances are rejected, non-unit FX is blocked until exact FX posting exists, and mapped account currencies must match the opening balance currency.
- Verified with `php artisan test` 173 tests / 1304 assertions, Phase 3 Slice 2 suite 14/14, Concurrency suite 7/7, PostgreSQL stress commands, TypeScript typecheck, and Vite build.
- Added `PHASE_3_SLICE_3_GEMINI_PROMPT.md` for the next bounded implementation slice: receipt/payment posting without allocations.

### Added — Phase 3 Slice 1 master data foundation
- Added Customer and Supplier master-data tables, models, and application services with globally unique codes, multilingual names, statuses, provenance fields, optimistic locking, and Spatie Activitylog audit writes through `AuditLogger`.
- Added CashAccount and BankAccount tables, models, and services linked to active GL accounts and system currencies, with optimistic locking and attachment entity registry entries.
- Hardened Slice 1 updates so nullable contact/bank fields can be cleared intentionally and `is_active=false` updates are preserved.
- Verified no `company_id`, `branch_id`, `tenant_id`, current-company/current-branch context, or Spatie Teams behavior was introduced.
- Verified with `php artisan test` 159 tests / 1243 assertions, Phase 3 Slice 1 suite 14/14, Concurrency suite 7/7, PostgreSQL stress commands, TypeScript typecheck, and Vite build.

### Corrected — Phase 3 planning contract
- Added `PHASE_3_AR_AP_CASH_BANK_CHEQUES.md` as the corrected Phase 3 scope/contract proposal.
- Added Bank Reconciliation to Phase 3 scope.
- Removed the unsupported generic manual AR/AP adjustment assumption; generic manual receivable/payable adjustment remains `UNDEFINED - DO NOT ASSUME`.
- Defined cheque accounting lifecycle requirements, cheque state-machine constraints, allocation concurrency/idempotency requirements, receipt/payment reversal owner-decision gates, and Phase 3 PostgreSQL stress-test expectations.
- Confirmed Phase 3 must not introduce company, branch, or tenant scope and must not start Sales, Purchasing, or Inventory.
- Clarified that Phase 3 audit uses the owner-approved Spatie Activitylog backend through the existing `AuditLogger`; legacy `audit_log` remains archive only.
- Added `PHASE_3_SLICE_1_GEMINI_PROMPT.md` for the first bounded implementation slice: Customer/Supplier and Cash/Bank master-data foundation only.
- Added `PHASE_3_SLICE_2_GEMINI_PROMPT.md` for the next bounded implementation slice: AR/AP subledger and customer/supplier opening balances only.

### Added — Laravel M10 Spatie Activitylog, audit viewer, scheduler, and jobs baseline
- Installed `spatie/laravel-activitylog` 4.12.3 and made Spatie `activity_log` the active audit backend.
- Kept `App\Domain\Audit\AuditLogger::record(...)` as the central application adapter while routing new writes through Spatie Activitylog.
- Preserved legacy `audit_log` as a read-only archive; no new application writes should target it.
- Added backward-compatible audit query aliases so the audit UI still receives `actor_id`, `actor_name`, `action`, `entity_type`, `entity_id`, `before_json`, `after_json`, `reason`, `request_id`, `ip`, `device`, and `at`.
- Added append-only DB triggers for both `activity_log` and legacy `audit_log` on PostgreSQL and SQLite.
- Added the read-only `/audit-log` Inertia page protected by `audit.view` or `settings.configure`.
- Registered `tokens:gc --batch=100` hourly with `withoutOverlapping()` and verified jobs/failed_jobs/job_batches baseline behavior.
- Verified with `php artisan test` 145 tests / 1185 assertions, Concurrency suite 7/7, PostgreSQL stress commands, TypeScript typecheck, and Vite build.

### Added — Laravel Phase 2 Accounting Core
- Implemented the Laravel accounting ledger spine: account categories, account types, account groups/accounts, FX rates, fiscal periods, manual journals, posting engine, immutable ledger entries, reversal workflow, opening balances, General Journal, General Ledger, and Trial Balance.
- Added database foreign keys for currency relationships across accounting tables.
- Added account type/category CRUD pages and control-account behavior.
- Added demo accounting seeder and polished empty states for accounting reports.
- Added PostgreSQL accounting stress verification through `php artisan accounting:concurrency-stress --workers=50`.
- Preserved the single-ERP context: no company/branch/tenant dimensions were introduced into accounting tables.

### Added — Laravel M9 attachments and notifications services
- Added attachment upload/list/download/delete service behavior with extension, MIME, and size validation.
- Added explicit allowlisted entity authorization for attachment entities and storage cleanup compensation on failure.
- Added reusable `AttachmentPanel` integration for supported entities.
- Added notification service creation/list/unread/mark-read/mark-all-read behavior with per-user dedupe.
- Triggered user notifications for role assign/revoke actions.

### Added — Laravel M8 settings/user actions
- Added real create/update actions for company profile, standalone branch references, numbering sequences, and role assignment/revocation.
- Hardened permissions so empty RBAC assignments do not grant management mutations.
- Preserved no-tenant/no-current-company behavior across settings actions.

### Corrected — Laravel fiscal-year single-ERP context
- Removed unsupported `fiscal_year.company_id`; fiscal years are now global to this ERP installation/business profile with global `year` uniqueness.
- Preserved `financial_period.fiscal_year_id` so financial periods belong to fiscal years without Company/Tenant semantics.
- Added a migration guard that blocks the correction if existing data contains duplicate fiscal years for the same global year.

### Corrected — Laravel bootstrap admin seeding
- Made local bootstrap admin role assignment explicit and config-controlled: `DatabaseSeeder` seeds RBAC before the bootstrap user, then assigns the configured global `SUPER_ADMIN` role without company, branch, tenant, or current-company scope.
- Added `FirstUserSuperAdminSeeder` so the first user in the installation receives `SUPER_ADMIN` explicitly.
- Added coverage for the default bootstrap admin permission path, disabling bootstrap role assignment, and first-user super-admin assignment.

### Corrected — Laravel post-audit security and documentation pass
- Removed the implicit settings/user-management authorization fallback: empty RBAC assignments now deny management mutations instead of granting bootstrap privileges.
- Added explicit allowlisted attachment entity authorization; unknown entity types and missing/unauthorized entities deny by default.
- Added attachment storage failure compensation so a metadata/audit persistence failure deletes the newly stored file.
- Renamed the misleading global `COMPANY_ADMIN` role template to `ERP_ADMIN` and added a migration path for existing development data.
- Reclassified `fiscal_year.company_id` as OWNER DECISION REQUIRED; later resolved by removing the column for single-ERP fiscal years.
- Corrected current documentation to prevent reintroducing Company/Branch tenancy, company-scoped RBAC, or company/branch numbering dimensions.

### Corrected — Laravel Company/Branch/User relationship assumptions
- Removed unsupported Company/User membership (`company_user`) from the Laravel target.
- Removed unsupported `branch.company_id`, Company-to-Branch Eloquent relationships, and per-company branch-code uniqueness.
- Removed Company and Branch dimensions from document numbering; numbering remains atomic and unique by sequence key.
- Removed unsupported `company_id`/`branch_id` scope columns from audit logs while preserving actor, entity, action, before/after, redaction, and append-only behavior.
- Removed unsupported `company_id` scope columns from attachments and notifications; attachments remain entity-linked and notifications remain user-targeted with per-user dedupe.
- Updated Laravel tests and documentation so future work treats undefined relationships as `UNDEFINED - DO NOT ASSUME`.

### Added — Laravel migration M7-M10 backend parity
- Ported Laravel core-kernel primitives for exact integer-minor-unit Money, currency exponents, double-entry accounting invariants, typed domain errors, and document number formatting/config.
- Added Laravel `tests/Invariants` coverage for money exactness/allocation, accounting balance/well-formed lines, and deterministic numbering.
- Added working settings actions for company create/update, branch create/update, numbering create/update, and role assign/revoke with explicit IDs and no current-company or tenant session.
- Added notification and attachment application services, attachment upload/download routes, notification dedupe/list/mark-read behavior, and service/feature tests.
- Added append-only audit logging with sensitive-field redaction and wired audit records to company/branch/numbering/attachment mutations without inventing organizational scope. This is now backed by Spatie Activitylog for new writes.
- Added an idempotent job runner/backoff primitive and scheduled `tokens:gc --batch=100` hourly with overlap protection.

### Added — Laravel migration M6 app pages
- Migrated the authenticated Laravel Inertia app shell and pages for dashboard, settings hub, companies, branches, numbering, users/roles, and notifications.
- Changed post-login flow to land on `/dashboard`; kept `/foundation` as the migration diagnostic page.
- Wired page props to real Laravel/PostgreSQL data only: company/branch records, number sequences, native users, Spatie roles/permissions, and user notifications.
- Added notification mark-read handling scoped to the signed-in user and shared unread notification counts.
- Added feature coverage for every migrated page and notification mark-read behavior.

### Added — Laravel concurrency hardening
- Added a Laravel concurrency audit at `docs/CONCURRENCY_AUDIT.md` covering current mutation surfaces, lock ordering, idempotency, retries, token cleanup, and future posting/job risks without reintroducing SaaS tenant assumptions.
- Added an `idempotency_keys` table, operation/key/scope uniqueness, status checks on PostgreSQL, and a database-backed idempotency store that never logs raw keys.
- Added optimistic locking primitives with `lock_version` columns on `company` and `branch`, localized conflict messages in EN/AR, and exception rendering for JSON/Inertia requests.
- Added PostgreSQL-safe number sequence allocation using `INSERT ... ON CONFLICT ... DO UPDATE RETURNING`.
- Added bounded authentication garbage collection for expired database sessions, password reset tokens, and idempotency keys via `php artisan tokens:gc`.
- Added notification dedupe-key schema protection and `php artisan concurrency:stress` for PostgreSQL stress verification.
- Added a dedicated Laravel `Concurrency` PHPUnit suite covering sequence allocation, idempotency replay/conflict behavior, stale optimistic updates, token GC, notification dedupe, and localization.

### Corrected — Laravel architecture review
- Added `DOMAIN_MODEL_REVIEW.md` to classify confirmed ERP relationships versus old multi-tenant implementation artifacts.
- Removed the Laravel tenant context, tenant middleware, first-run onboarding assumption, Inertia `tenant` shared prop, and Spatie Permission company/team scope.
- Corrected Laravel RBAC so role templates are global and authorization scope remains explicit `scope_json`, not company-owned Spatie roles.
- Historical Next.js entries below may mention tenant wording because they describe the existing reference app, not the corrected Laravel target.

### Added — Laravel migration M5 authentication schema
- Extended Laravel's native `users` table with locale, theme, active-account, and MFA status fields while preserving the existing session and password-reset tables.
- Added PostgreSQL constraints for the supported locales/themes and an index for active-user filtering.
- Made Argon2id the Laravel password-hashing default using the same memory/time/parallelism parameters as the verified Next.js reference.
- Added integration coverage for auth columns, defaults, casts, mass assignment, and Argon2id password hashing; applied the migration successfully to local PostgreSQL.
- Added Laravel session login/logout with CSRF, active-account checks, login throttling, session regeneration, logout invalidation, a protected Inertia foundation route, and a local bootstrap admin seeder.

### Added — Laravel migration M3 database foundation
- Added Laravel migrations for the ERP foundation tables around the native `users` table: company, branch, currency, exchange rates, fiscal years/periods, number sequences, audit log, attachments, and notifications.
- Added Spatie Translatable-backed Company, Branch, and Currency models with JSON multilingual `name` columns.
- Added permission module/action metadata, assignment scope JSON, and seeded the module/action catalog plus 9 global role templates without Spatie teams.
- Added Laravel integration tests for schema constraints, currency seeding, and RBAC template seeding; verified migrations/seeds against a temporary PostgreSQL database.

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
- Local PostgreSQL verification: `prisma generate` ✓ · `prisma db push` ✓ · `prisma seed` ✓ · `npm run ci` ✓ · `next build` ✓ · `playwright` smoke **5 passed / 0 skipped** ✓.
- Vitest: **17 files / 66 tests passed** with DB-backed integration enabled. Invariants: **4 files / 23 tests passed**.
- Onboarding transaction verified: company + branch + 9 roles + 458 permission links + owner membership + `COMPANY_ADMIN`; cross-company role leakage = 0.
- GitHub Actions CI run `32440676342` completed `success` for `develop`.

### Tests
- 66 Vitest tests pass with PostgreSQL. 5 Playwright smoke tests pass with PostgreSQL-backed auth/RBAC. Invariant suite remains blocking.
