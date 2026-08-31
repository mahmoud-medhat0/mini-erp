# Mini ERP Migration Plan

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


## Scope

This repository is migrating in parallel from the verified Next.js foundation to a Laravel + Inertia + React architecture. The existing `app/` Next.js application remains the regression reference until Laravel reaches verified parity.

## Current Architecture

- Next.js App Router + TypeScript
- Prisma + PostgreSQL
- Auth.js / NextAuth
- next-intl
- Vitest + Playwright
- pg-boss jobs

## Target Architecture

- Laravel + PHP 8.3+
- PostgreSQL
- Inertia.js + React + TypeScript
- Tailwind
- Laravel session auth + CSRF
- Spatie Laravel Permission
- Spatie Laravel Activitylog
- Laravel queues, scheduler, notifications, policies, and gates
- Pest/PHPUnit and browser tests where appropriate

## Package Decisions

- `inertiajs/inertia-laravel`: required for Laravel-controlled page responses with React pages.
- `@inertiajs/react`, `react`, `react-dom`: required frontend runtime.
- `spatie/laravel-permission`: required RBAC foundation for M5.
- `spatie/laravel-translatable`: used from M3 for database-backed multilingual master data names, starting with company, branch, and currency.
- `spatie/laravel-activitylog`: active audit backend from M10; legacy `audit_log` remains an archive.

## Schema Decision

M2 does not port the Prisma schema. The Laravel target must not copy the old company-as-tenant assumption. `company` remains business configuration, while `branch` remains a standalone referenced/reporting concept until a precise relationship is explicitly required. Multilingual names may use Spatie Translatable JSON columns. RBAC uses Spatie Permission without teams; role/permission scope is represented by explicit domain authorization rules and `scope_json`, not by `company_id` on Spatie role tables.

## Authentication Migration

M2 only boots Laravel. M5 replaces Auth.js with Laravel session authentication, Argon2id hashing, CSRF, login throttling, session regeneration, logout invalidation, and generic credential errors.

M5 auth backend includes Laravel's native `users` profile fields (`locale`, `theme`, `is_active`, `mfa_enabled`), Argon2id hashing, session login/logout, CSRF, active-account checks, throttling, session regeneration, logout invalidation, and a protected Inertia foundation route. Authentication must not establish a current tenant/current company/current branch unless a later domain review explicitly defines that workflow.

## Accounting Migration

Phase 2 Accounting Core has been implemented in Laravel as the current ledger spine: account categories/types, chart of accounts, FX rates, fiscal years/periods, manual journals, posting, immutable ledger entries, reversal, opening balances, General Journal, General Ledger, Trial Balance, demo data, and accounting stress tests.

This does not implement later operational modules such as AR/AP workflows, Cash/Bank/Cheques, Sales, Purchasing, Inventory, Payroll, Rentals, or full financial statements.

## Audit Migration

M10 switches active audit writes to Spatie Activitylog.

- `activity_log` is the active audit table.
- `audit_log` is retained as a legacy archive.
- `AuditLogger::record(...)` remains the central application API and writes through Spatie.
- `AuditLogQueryService` maps Spatie records back to the existing audit viewer field aliases.
- PostgreSQL/SQLite triggers enforce append-only behavior on both `activity_log` and legacy `audit_log`.

## RBAC Migration

M2 installs Spatie Permission and prepares the `User` model with `HasRoles`. Laravel RBAC must not use Spatie teams for company tenancy. Permissions carry `module`/`action`; role and assignment pivots may carry `scope_json` for explicit business authorization scopes; seeders create global role templates and the module/action catalog. M5+ will wire these into gates, policies, and domain authorization services.

## Translation Migration

Static UI translations remain frontend resources. Database-backed multilingual domain content uses Spatie Translatable where the domain model requires stored EN/AR values.

## Frontend Migration

M2 creates one minimal Inertia page to verify the stack. M6 will move React components/pages from the existing Next app to `laravel/resources/js` and replace server actions/routes with Laravel controllers and Inertia forms.

## Cutover Plan

1. Keep `app/` running as reference.
2. Build Laravel foundation in `laravel/`.
3. Port schema, kernel, auth/RBAC, UI, and foundation flows incrementally.
4. Run Laravel verification locally until a Laravel CI pipeline is explicitly connected.
5. Remove Next runtime only after Laravel parity is tested and explicitly approved.

## Rollback Strategy

The Next.js app remains untouched during the migration. If a Laravel step fails, revert the Laravel-specific commit and continue using the existing Next app as the working baseline.
