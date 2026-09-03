# Phase 22 - CI Pipeline and Browser Smoke Coverage

> Verification infrastructure only. No ERP business behavior changes. Deployment execution remains parked until the owner explicitly resumes cutover work.

## Status

PARTIALLY SUPERSEDED - 2026-09-03.

The browser-coverage half of this phase is COMPLETE and in use. **The CI pipeline described
below was removed on 2026-09-03 by owner decision**, along with the dead `app/`-targeted
workflow it had replaced. `.github/` no longer exists and no pipeline is connected.

Everything else this phase added is retained and works standalone:

- 22 Playwright browser tests (`npm run e2e`)
- `scripts/check-locale-parity.mjs`
- `php artisan e2e:prepare-user`
- the DataTable component/request column-parity guard

The defects this phase found (Section "Defects Found By The New Coverage") were real and
their fixes remain in place. Read the CI sections below as historical record only.

## Purpose

Two long-standing entries sat in "Known Issues / Residual Risks":

1. *"No GitHub Actions workflow is connected for the Laravel migration track."*
2. *"Browser E2E coverage for the Laravel UI is not yet equivalent to the old Next.js Playwright smoke suite."*

Phase 22 closes both. It adds no business logic and changes no financial behavior.

## What Was Actually Wrong

### The CI workflow was worse than absent

`.github/workflows/ci.yml` existed, which made the project look covered. It was not.
Every job targeted `working-directory: app` — the abandoned Next.js/Prisma application —
and ran `prisma:generate`, `prisma:db-push`, and that app's Playwright suite. The active
Laravel ERP under `laravel/` was never built, linted, or tested by CI.

A workflow pointing at dead code is more dangerous than no workflow: it produces green
checkmarks that mean nothing.

### `tests/Browser/` was an empty promise

The directory contained only `.gitkeep`. Neither Dusk nor Playwright was installed for the
Laravel app. Every one of the ~1,070 existing tests runs server-side, which structurally
cannot observe whether a page renders in a browser.

That gap was not theoretical — see "Defects Found" below.

## What Was Implemented

### 1. A real CI pipeline (`.github/workflows/ci.yml`, rewritten)

Five jobs, all scoped to `laravel/`:

| Job | Covers |
|---|---|
| `quality` | Pint, locale JSON parse, **locale key parity**, TypeScript typecheck, Vite production build |
| `tests` | PHPUnit on SQLite, split into 7 matrix batches |
| `postgres` | Migrations, seeding, strict route audit, `ops:go-live-readiness`, Concurrency suite, six stress commands, integrity check, token GC |
| `e2e` | Playwright browser smoke against a real PostgreSQL-backed server |
| `source-scans` | Anti-tenancy, Spatie Teams disabled, no float money math |

**The test matrix has a catch-all batch.** Six positive batches name module prefixes; a
seventh uses a negative lookahead to run everything the others do not name. Without it,
13 existing test files — including `AuthenticationTest`, `RbacCrudEnforcementTest`, and
`FinancialPeriodIntegrityTest` — would have silently escaped CI, and any future test file
would too.

Two things were verified locally rather than assumed:

- The naive anti-tenancy scan produced **false positives**: the migrations that *removed*
  `company_id` must keep naming it, and `Phase3IntegrityCheckCommand` is the runtime guard
  asserting its absence. Both are excluded, with the reason recorded in the workflow.
- `--filter=/(Phase14|DataTable)/` matches nothing — PHPUnit rejects the surrounding
  slashes. Confirmed by running both forms; the positive batches use unslashed patterns.

### 2. Locale parity guard (`laravel/scripts/check-locale-parity.mjs`)

Compares the full key-path sets of `en.json` and `ar.json` and fails with a readable diff
if they diverge. A key present in only one dictionary renders `undefined` for those users.
Currently: **5,110 keys present in both.**

### 3. Browser test account (`php artisan e2e:prepare-user`)

The ERP deliberately ships no fixed bootstrap credentials, so browser tests need an account
provisioned at run time. The command refuses to run in production, requires an explicit
`--password` of at least 12 characters, and never defaults a credential. CI generates a
random password per run and masks it from the log.

### 4. Playwright browser smoke (`laravel/tests/E2E/`, 22 tests)

`playwright.config.ts`, `tests/E2E/support/session.ts`, `tests/E2E/smoke.spec.ts`.
Scope is deliberately narrow — business rules stay covered by PHPUnit and are not duplicated:

- **Authentication (3)** — unauthenticated redirect, invalid credentials create no session, valid sign-in reaches a protected page.
- **Page rendering (13)** — one per major module. Each asserts a non-error status, that `#app` is not empty, and that the browser reported no uncaught errors, console errors, or 5xx responses.
- **Server-side pagination (5)** — each Phase 21 DataTable initialises, settles its AJAX round-trip, renders its columns, and renders rows or an empty state. Assertions are data-independent so they hold in an empty environment.
- **Localization (1)** — switching to Arabic flips `dir` to `rtl` and `lang` to `ar`, then restores English.

## Defects Found By The New Coverage

These were live defects in the working tree, found by running the tests rather than by
reading the code.

### A. Six pages crashed in the browser whenever they held data

React error #31: a translatable `{en, ar}` object rendered directly as a JSX child, which
blanks the entire page. Affected Products, Customers, and Suppliers.

The root cause was **type declarations that lied**: rows were typed `name: string` while the
backend sends a Spatie Translatable object, so TypeScript never flagged the misuse.
Correcting the types to a shared `TranslatedName` immediately exposed four further defects
in `Products.tsx` alone — two more object renders, an `[object Object]` delete confirmation,
and an edit form prefilled with an object.

Fixes: added `TranslatedName` to `resources/js/Types/index.ts` (it had been copy-pasted
locally in two pages), corrected the row types, and routed every display through
`getLocalizedName(value, locale)`.

**No server-side test could have caught this.** Every affected route returned HTTP 200.

### B. The Rental Operations DataTable never loaded

`RentalOperationsDataTableRequest::allowedColumns()` omitted `latest_journal_number`, which
the table component requests. The endpoint answered **422** and the table sat on its loading
spinner forever.

The PHPUnit suite missed it because the test built its column list from the same allowlist
the request validates against — it could never disagree with itself. The browser used the
component's real column set and failed immediately.

Fixes: allowlisted the column, and added
`test_datatable_components_only_request_allowlisted_columns` to `ReportInputHardeningTest`,
which parses each DataTable component's `data:` keys and asserts every one is allowlisted by
its FormRequest. This now covers the rental, VAT, and cheque register tables.

### C. A pre-existing broken assertion

`Phase15ProductHardeningTest::test_remaining_accounting_master_data_controllers_delegate_page_data_to_services`
was failing before this phase began, on files this phase never touched. It asserted the
literal string `$this->pageData->indexData()`, but `ExchangeRateController` had since gained
a search parameter. The assertion's intent is that the controller delegates to the page-data
service, which it still does; the check now matches `$this->pageData->indexData(` so passing
an argument does not break it.

## Verification Performed

| Command | Result |
|---|---|
| `npx playwright test` | **22 passed** |
| `php artisan test --filter="Phase15\|Phase4Slice1Catalog\|Phase3Slice1MasterData\|Phase3Slice7Ui"` | 231 passed / 26,893 assertions |
| `php artisan test --filter="Security\|Phase8Slice4RouteSmoke\|DataTable\|Phase14"` | 117 passed / 2,248 assertions |
| `php artisan test --filter=ReportInputHardeningTest` | 7 passed / 132 assertions |
| CI batch `--testsuite=Invariants,Unit,Integration` | 28 passed / 607 assertions |
| CI batch `(AccountingCore\|AccountCategory\|AccountType\|Phase2\|Phase3\|Phase5)` | 205 passed, 2 skipped / 2,067 assertions |
| CI catch-all batch (negative lookahead) | 113 passed / 1,247 assertions |
| `php artisan security:route-audit --strict` | "All protected routes satisfy authorization requirements." |
| `vendor/bin/pint --test` | passed |
| `npx tsc --noEmit` | 0 errors |
| `node scripts/check-locale-parity.mjs` | 5,110 keys in both |
| `npm run build` | clean |
| CI source scans, simulated locally | all three pass |

## Invariants Preserved

- No tenant/company scope; no Spatie Teams.
- No float money math; integer minor units throughout.
- No business logic, GL posting, or financial calculation changed.
- No hardcoded credential committed; the browser account is provisioned per run.
- `storage/e2e` is gitignored so test artifacts never enter the repository.

## Running Browser Tests Locally

```powershell
cd laravel
php artisan migrate --force
npm run build
php artisan e2e:prepare-user --password="<choose-a-local-password>"
$env:E2E_EMAIL = "e2e-admin@mini-erp.test"
$env:E2E_PASSWORD = "<the-same-password>"
npm run e2e
```

Playwright starts `php artisan serve` automatically. Use `npm run e2e:ui` for the
interactive runner.

## Known Limitation

CI has been validated by executing every job's commands locally — the PHPUnit batches, the
source scans, the locale parity check, the route audit, and the full Playwright suite all
pass here. It has **not** yet run on GitHub Actions, because that requires a push to the
remote. Expect first-run environment adjustments (PHP extension availability, service
startup timing) rather than logic errors.
