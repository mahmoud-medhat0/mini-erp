# Mini ERP - Phase 20 Slice 2 Agy Prompt

Execute ONLY Phase 20 Slice 2: Accountant-Facing UX Friction Cleanup.

Stop after this slice. Do not start Slice 3.

## Scope

Inspect the most-used accountant-facing pages and remove practical friction that would slow down a financial controller during hands-on acceptance.

This slice is allowed to make narrow UI/UX changes only when they improve an existing workflow. It must not redesign the application and must not start a new ERP module.

## Non-Negotiable Rules

- No multi-tenant architecture and no company/tenant/security scope changes.
- Branch remains operational/reporting only where already implemented.
- Deployment remains parked.
- Do not change accounting math, tax math, stock costing, posting, numbering, idempotency, workflow status transitions, immutable ledgers, or PeriodGuard behavior.
- Do not store Telegram credentials, chat IDs, API keys, passwords, or production secrets in files.
- No hardcoded visible strings in React pages. Add EN/AR dictionary keys for every visible label/message.
- Do not use native `<select>`, `<option>`, `type="date"`, `dangerouslySetInnerHTML`, or `window.location.href`.
- Keep controllers thin. If data composition is needed, use existing page-data/query service patterns.
- If no real UX defect is found on a page, leave it unchanged and document that decision.

## Required Review Before Editing

Inspect:

- `PRODUCT_ACCEPTANCE_DEFECT_LOG.md`
- `OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md`
- `PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md`
- `laravel/resources/js/Pages/Accounting/GeneralLedger.tsx`
- `laravel/resources/js/Pages/Accounting/GeneralJournal.tsx`
- `laravel/resources/js/Pages/Accounting/TrialBalance.tsx`
- `laravel/resources/js/Pages/Reports/BalanceSheet.tsx`
- `laravel/resources/js/Pages/Reports/IncomeStatement.tsx`
- `laravel/resources/js/Pages/Reports/CashFlow.tsx`
- `laravel/resources/js/Pages/Accounting/ChartOfAccounts.tsx`
- `laravel/resources/js/Pages/Accounting/JournalForm.tsx`
- Shared UI primitives in `laravel/resources/js/Components`
- `laravel/resources/js/i18n/en.json`
- `laravel/resources/js/i18n/ar.json`
- Existing Phase 15/18/19 tests.

## Required Implementation

Prioritize accountant usability on the pages used in the 15-step walkthrough:

1. Filters must have clear labels, reset behavior, and preserve selected values where appropriate.
2. Totals and balance indicators must be visible, readable, and localized.
3. Empty states must explain what is missing and the next operational action without marketing text.
4. Export/print buttons must be permission-aware and visually consistent where pages already support export/print.
5. Tables must remain scannable on RTL and LTR.
6. Form pages must surface validation errors near the relevant fields or in an existing error summary pattern.
7. Do not add tutorial text, hero marketing sections, or nested cards.

Add or extend `Phase20HandsOnAcceptanceTest` to verify any changed pages remain:

- dictionary-backed
- free of unsafe UI patterns
- accessible to the expected persona
- explicit about totals/empty state/action availability when browserless assertions are possible

## Documentation

Create `PHASE_20_SLICE_2_REPORT.md` with:

- exact UX issues found
- exact fixes made
- pages intentionally left unchanged and why
- tests added/changed
- no-scope scan result
- UI unsafe-control scan result
- verification command results
- remaining risks or deferred items for Slice 3

Update:

- `PHASE_20_HANDS_ON_ACCEPTANCE_DEFECT_CLOSURE.md`
- `PRODUCT_ACCEPTANCE_DEFECT_LOG.md` if a real issue was found/fixed
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

## Verification Commands

Run from `laravel/` and report exact results:

```powershell
vendor/bin/pint --test
php artisan test --filter=Phase20HandsOnAcceptanceTest --compact
php artisan test --filter=Phase18ProductAcceptanceTest --compact
php artisan test --filter=Phase15ProductHardeningTest --compact
php artisan security:route-audit --strict
npm run typecheck
npm run build
```

Run and classify:

```powershell
rg -n "dangerouslySetInnerHTML|<select|<option|type=\"date\"|window\\.location\\.href" laravel/resources/js/Pages laravel/resources/js/Components
rg -n "company_id|tenant_id|currentCompany|currentTenant|Spatie Teams" PHASE_20*.md PRODUCT_ACCEPTANCE_DEFECT_LOG.md laravel/app laravel/routes laravel/resources/js laravel/tests/Feature/Phase20HandsOnAcceptanceTest.php
```

Also run a secret scan for actual credentials/tokens across touched files. Report whether any real secret values were found.

## Final Rule

Stop after Phase 20 Slice 2. Do not start Slice 3.
