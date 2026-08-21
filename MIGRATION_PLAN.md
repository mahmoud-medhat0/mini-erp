# Mini ERP Migration Plan

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
- Laravel queues, scheduler, notifications, policies, and gates
- Pest/PHPUnit and browser tests where appropriate

## Package Decisions

- `inertiajs/inertia-laravel`: required for Laravel-controlled page responses with React pages.
- `@inertiajs/react`, `react`, `react-dom`: required frontend runtime.
- `spatie/laravel-permission`: required RBAC foundation for M5.
- `spatie/laravel-translatable`: used from M3 for database-backed multilingual master data names, starting with company, branch, and currency.

## Schema Decision

M2 does not port the Prisma schema. M3 maps the Phase 1 foundation schema to Laravel migrations while keeping Laravel's native `users` table for authentication. ERP tenancy uses `company`, `branch`, and `company_user`; multilingual names use Spatie Translatable JSON columns; RBAC uses Spatie Permission tables with `company_id` team scope instead of duplicating the old Prisma `role`, `permission`, `role_permission`, and `user_role` tables. Constraints, indexes, tenant boundaries, and PostgreSQL semantics must be preserved unless a later migration explicitly documents a rename.

## Authentication Migration

M2 only boots Laravel. M5 replaces Auth.js with Laravel session authentication, Argon2id hashing, CSRF, login throttling, session regeneration, logout invalidation, generic credential errors, and tenant-aware session context.

M5 auth work has started: Laravel's native `users` table now carries `locale`, `theme`, `is_active`, and `mfa_enabled`; PostgreSQL constrains supported locale/theme values and indexes active accounts. Argon2id is the Laravel hashing default with the same parameters as the reference implementation. Laravel session login/logout is active with CSRF, active-account checks, throttling, session regeneration, logout invalidation, and a protected Inertia foundation route. Tenant-aware session context and authorization middleware remain pending and must be completed before M5 is marked complete.

## RBAC Migration

M2 installs Spatie Permission and prepares the `User` model with `HasRoles`. M3 turns on Spatie teams using `company_id`, extends permissions with `module`/`action`, extends role and assignment pivots with `scope_json`, and seeds the existing module/action catalog, sensitive capabilities, and 9 role templates. M5 will wire these seeded roles and permissions into gates, policies, and tenant-scoped services.

## Translation Migration

Static UI translations remain frontend resources. Database-backed multilingual domain content uses Spatie Translatable where the domain model requires stored EN/AR values.

## Frontend Migration

M2 creates one minimal Inertia page to verify the stack. M6 will move React components/pages from the existing Next app to `laravel/resources/js` and replace server actions/routes with Laravel controllers and Inertia forms.

## Cutover Plan

1. Keep `app/` running as reference.
2. Build Laravel foundation in `laravel/`.
3. Port schema, kernel, auth/RBAC, UI, and foundation flows incrementally.
4. Run both stacks in CI during parallel migration.
5. Remove Next runtime only after Laravel parity is tested and explicitly approved.

## Rollback Strategy

The Next.js app remains untouched during the migration. If a Laravel step fails, revert the Laravel-specific commit and continue using the existing Next app as the working baseline.
