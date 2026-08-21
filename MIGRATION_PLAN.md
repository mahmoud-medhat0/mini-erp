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
- `spatie/laravel-translatable`: deferred until database/domain entities with multilingual stored values are ported.

## Schema Decision

M2 does not port the Prisma schema. M3 will map `app/prisma/schema.prisma` to Laravel migrations. Existing column names, constraints, indexes, tenant boundaries, and PostgreSQL semantics must be preserved unless a later migration explicitly documents a rename.

## Authentication Migration

M2 only boots Laravel. M5 will replace Auth.js with Laravel session authentication, Argon2id hashing, CSRF, login throttling, session regeneration, logout invalidation, generic credential errors, and tenant-aware session context.

## RBAC Migration

M2 installs Spatie Permission and prepares the `User` model with `HasRoles`. M5 will map the existing 24 module/action catalog, sensitive capabilities, scopes, and 9 role templates into Spatie roles, permissions, gates, policies, and tenant-scoped services.

## Translation Migration

Static UI translations remain frontend resources. Database-backed multilingual domain content will use Spatie Translatable only where the domain model requires stored EN/AR values.

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
