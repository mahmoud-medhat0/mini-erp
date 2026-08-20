# Changelog

All notable changes. Format: Keep a Changelog; SemVer per phase.

## [Unreleased] — Phase 1: Foundation (in progress)
### Added
- Project scaffold: Next.js (App Router) + TypeScript + Prisma + Zod + Tailwind, modular-monolith structure (24 modules + core kernel).
- Core kernel (tested): exact **Money** value object (BigInt minor units, exact allocation), **accounting-kernel** (`assertBalanced` Σdr=Σcr), concurrency-safe **numbering**, server-side **RBAC** with scope + tenant isolation, typed **errors**, **audit** types, **currency** registry (EGP seed, multi-currency).
- Prisma kernel schema (company, branch, user, role, permission, currency, exchange rate, fiscal year/period, number sequence, audit log, attachment, notification).
- i18n (EN/AR) + RTL/LTR + design tokens/theming wired into the App Router.
- CI workflow with a **blocking accounting-invariant job**.
- Documentation set: ARCHITECTURE, SECURITY, TESTING_STRATEGY, DEPLOYMENT, DISASTER_RECOVERY, PHASE1_STATUS, plus README/ROADMAP/IMPLEMENTATION_STATUS.
- Design system built in Figma ("Mini ERP — Design System & UI") + live style-guide.html.

### Tests
- 23/23 accounting-invariant + unit tests passing (money, accounting, numbering, rbac).

### Notes
- GitHub remote not yet connected — session token is repo-bound and no repo is enabled for this session (see IMPLEMENTATION_STATUS → Remote).
