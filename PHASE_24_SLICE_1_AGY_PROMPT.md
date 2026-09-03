# Phase 24 Slice 1 — Fix Translatable Field Rendering Across All Pages

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, `company_id`, `tenant_id`, Spatie Teams scope, or blanket `branch_id` scope. See root `NO_MULTI_TENANT_POLICY.md`.

**Status:** PREPARED — NOT IMPLEMENTED
**Contract:** `PHASE_24_TRANSLATABLE_DISPLAY_AUDIT.md` — read it first.

---

## 1. Task

29 Eloquent models use Spatie `HasTranslations`. Their translatable columns reach the React
pages as objects (`{"en":"Pieces","ar":"قطع"}`), never as strings.

Rendering such an object directly as a JSX child throws **React error #31**, which unmounts
the tree and shows the user a **blank page**.

Audit every page and component, and make every translatable value display through the
existing localization helper.

This is not hypothetical. `Catalog/UnitsOfMeasure.tsx:159` renders `{uom.name}` today while
the database holds `{"en":"Transfer Piece","ar":"قطعة تحويل"}` for that row. Verify this
yourself before you start — it calibrates everything else.

---

## 2. Read These First

**The already-correct reference implementations** (fixed in Phase 22 — imitate them exactly,
do not invent a new approach):

- `laravel/resources/js/Pages/Customers/Index.tsx`
- `laravel/resources/js/Pages/Suppliers/Index.tsx`
- `laravel/resources/js/Pages/Catalog/Products.tsx`

**The shared pieces:**

- `laravel/resources/js/lib/accountingHelpers.ts` → `getLocalizedName()`
- `laravel/resources/js/Types/index.ts` → `TranslatedName`
- `laravel/resources/js/Components/VatRegisterDataTable.tsx` → the DataTable slot pattern

```ts
// Types/index.ts
export type TranslatedName = Record<string, string> | string | null;

// lib/accountingHelpers.ts
getLocalizedName(nameObj?: Record<string, string> | string | null, locale: string = 'en'): string
// null/'' → '';  string → unchanged;  object → ar falls back to en, en falls back to ar
```

---

## 3. Which Fields Are Translatable

Only these model attributes. Anything else is a plain column — do not touch it.

```
Account: name                      FixedAsset: name
AccountCategory: name, code        FixedAssetCategory: name
AccountGroup: name                 FixedAssetLocation: name
AccountType: name                  PayrollComponent: name
BankAccount: name, bank_name       PayrollRunLineComponent: name
Budget: name                       Product: name, description
CashAccount: name                  ProductCategory: name, description
CostCenter: name                   Project: name
Customer: name                     RentableItem: name, description
Employee: name                     RentalContractLine: description
ExpenseCategory: name              StockLocation: name
FinancialStatementLine: name, code Supplier: name
                                   UnitOfMeasure: name
                                   Warehouse: name
```

`AccountCategory.code` and `FinancialStatementLine.code` **are** translatable. Do not assume
a field named `code` is a plain string.

Confirm this list against the models rather than trusting it:
`grep -rl "HasTranslations" laravel/app/Models/*.php`

---

## 4. Method

### 4.1 Build the inventory

Start from these measurements (verify them; they were taken on 2026-09-03):

- 40 pages declare `name: string;`
- 29 of those never call `getLocalizedName` — **highest risk**
- 22 pages declare `description: string;` or `bank_name: string;`
- Nested relation renders exist, e.g. `{s.supplier?.name}` in `Purchasing/PayableSettlements.tsx:341`
  and `Sales/ReceivableSettlements.tsx:341`

Search patterns that find real cases:

```bash
grep -rn "name: string;\|description: string;\|bank_name: string;" laravel/resources/js/Pages --include=*.tsx
grep -rnE "\{[a-zA-Z_]+\??\.(name|description|bank_name)\}" laravel/resources/js/Pages --include=*.tsx
grep -rnE "\{[a-zA-Z_]+\.[a-zA-Z_]+\??\.(name|description)\}" laravel/resources/js/Pages --include=*.tsx
```

For each hit, determine whether the value comes from one of the 29 translatable models.
**Read the controller or page-data service that supplies the prop** — do not guess from the
variable name. A `name` on a non-translatable model (`User`, `Role`, `FiscalYear`) is a plain
string and must be left alone.

### 4.2 Fix each confirmed case

1. Change the declared type to `TranslatedName` (import it from `../../Types`).
2. Route every display through `getLocalizedName(value, locale)`.
3. Convert before non-JSX uses too:
   - form prefill: `setData('name', getLocalizedName(row.name, locale))`
   - `confirm()` text and `.replace('{name}', …)`
   - template literals: `` `${getLocalizedName(x.name, locale)} (${x.code})` ``
   - `.sort()` / `.filter()` comparisons on the value
4. Add `locale` to the dependency array of any `useMemo`/`useCallback` that now reads it.
5. Re-run `npx tsc --noEmit` after each file. **Correcting the type routinely exposes further
   defects in the same file** — on `Products.tsx` it revealed four. Fix every one it surfaces;
   do not silence them with casts.

### 4.3 Forbidden shortcuts

- Never `as string`, `as any`, or `String(value)` to quiet the compiler. `String({en:'x'})`
  yields `"[object Object]"` — it hides the bug instead of fixing it.
- Never `value.en` or `value['ar']` directly. That ignores the active locale, which is the
  entire point of this slice.
- Never change a backend service, controller, page-data class, model, or migration.
- Never edit a CSV exporter. Exports emit raw values deliberately.
- Never introduce a new hardcoded visible string. Use the EN/AR dictionaries.

---

## 5. Regression Guard

Add `laravel/tests/Feature/TranslatableDisplayGuardTest.php`.

It must fail if a page renders a translatable field without the helper. Build it so it
**actually catches the current defects before you fix them** — write it first, watch it fail
on `Catalog/UnitsOfMeasure.tsx`, then fix pages until it passes. A guard that passes on
broken code is worthless.

Suggested shape:

1. Derive the translatable attribute list by reading `app/Models/*.php` for `HasTranslations`
   and its `$translatable` array, so the guard follows the models rather than a frozen copy.
2. Scan every `.tsx` under `resources/js/Pages` and `resources/js/Components`.
3. Flag any JSX render of a suspect field that is not wrapped in `getLocalizedName`.
4. Keep a small, explicitly commented allowlist for genuine non-translatable `name` props
   (`User`, `Role`, `FiscalYear`, dictionary labels). Each entry states why it is exempt.

State plainly in your report which defects the guard caught on its first run.

---

## 6. Browser Evidence

Static analysis alone is insufficient — a blank page still returns HTTP 200.

Playwright is installed and configured. Extend `laravel/tests/E2E/smoke.spec.ts` (or add a
spec beside it) covering the pages you fixed, and confirm each renders **with data present**
and reports no console errors. `collectPageErrors()` in `tests/E2E/support/session.ts`
already does the assertion.

Seed or create data first where a table would otherwise be empty — an empty table cannot
reproduce this defect and proves nothing.

Run:

```powershell
cd laravel
php artisan e2e:prepare-user --password="<choose-a-local-password>"
$env:E2E_EMAIL = "e2e-admin@mini-erp.test"
$env:E2E_PASSWORD = "<same-password>"
npm run build
npm run e2e
```

---

## 7. Verification Gate

Run from `laravel/`. Report the real result of each. A command counts as passed only after it
exits successfully — never report a command you did not run to completion.

```powershell
npx tsc --noEmit
vendor/bin/pint --test
node scripts/check-locale-parity.mjs
php artisan test --filter=TranslatableDisplayGuardTest
php artisan test --filter='(Phase15|Phase18|Phase19|Phase20)'
php artisan test --filter='(Phase3Slice7Ui|Phase4Slice1Catalog|Phase3Slice1MasterData)'
npm run build
npm run e2e
```

`Phase18ProductAcceptanceTest` bans `<select`, `<option`, `type="date"`, and
`window.location.href` in specific files — check it before editing those.

---

## 8. Review Gate

- A scan is clean only when it prints zero matches.
- No `as any` / `as string` / `String()` used to bypass a type error.
- No backend file modified.
- No new hardcoded visible text; EN/AR dictionaries only.
- No tenant/company scope introduced.
- Existing test coverage is not deleted or weakened to make a suite green.
- If a page cannot be fixed without a backend change, **stop and report it** rather than
  changing the backend or working around it in the UI.

---

## 9. Deliverable

Create `PHASE_24_SLICE_1_REPORT.md` containing:

- Every file changed, grouped by module.
- Every defect found, with file and line, and how it would have presented to the user
  (blank page vs `[object Object]` vs wrong-language text).
- Which defects the regression guard caught on its first run, before any fix.
- The exact result line of every verification command.
- Anything you could not complete, and precisely why.

Then update `IMPLEMENTATION_STATUS.md`, `NEXT_TASKS.md`, and `CONTINUE_HERE.md` with a
one-line status entry each, matching the existing style.
