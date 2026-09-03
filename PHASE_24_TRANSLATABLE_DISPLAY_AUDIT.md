# Phase 24 - Translatable Field Display Audit

> UI correctness only. No ERP business behavior changes. Deployment execution remains parked until the owner explicitly resumes cutover work.

## Status

PREPARED - NOT IMPLEMENTED. Execution prompt: `PHASE_24_SLICE_1_AGY_PROMPT.md`.

## The Problem, Measured

29 Eloquent models use Spatie `HasTranslations`. Their translatable columns arrive in the
Inertia payload as objects like `{"en":"Transfer Piece","ar":"قطعة تحويل"}` — never as
strings.

Rendering that object directly as a JSX child throws **React error #31**, which unmounts the
component tree and leaves the user a **blank page**. It is not a cosmetic defect.

This already happened. Phase 22's browser tests caught six such pages (Products, Customers,
Suppliers and others) crashing whenever those tables held data. Fixing the types on
`Products.tsx` alone immediately exposed four more defects hidden behind them.

### Why TypeScript did not catch it

The page prop types **lie**. They declare `name: string` while the backend sends an object.
TypeScript therefore sees a legal `{someString}` render and stays silent. The type is the
root cause, not the render.

### Current measured state

| Metric | Count |
|---|---|
| Models using `HasTranslations` | 29 |
| Pages declaring `name: string;` | 40 |
| Pages already using `getLocalizedName` | 57 |
| Pages already using `TranslatedName` | 28 |
| **Pages with `name: string` and no `getLocalizedName`** | **29** |
| Pages declaring `description: string;` / `bank_name: string;` | 22 |

A spot check confirms the defect is live, not theoretical:
`Catalog/UnitsOfMeasure.tsx:159` renders `{uom.name}` while
`DB::table('unit_of_measure')->value('name')` returns `{"en":"Transfer Piece","ar":"قطعة تحويل"}`.
That page is blank for any user the moment a unit of measure exists.

### Translatable attributes by model

```
Account: name                    FixedAsset: name
AccountCategory: name, code      FixedAssetCategory: name
AccountGroup: name               FixedAssetLocation: name
AccountType: name                PayrollComponent: name
BankAccount: name, bank_name     PayrollRunLineComponent: name
Budget: name                     Product: name, description
CashAccount: name                ProductCategory: name, description
CostCenter: name                 Project: name
Customer: name                   RentableItem: name, description
Employee: name                   RentalContractLine: description
ExpenseCategory: name            StockLocation: name
FinancialStatementLine: name, code  Supplier: name
                                 UnitOfMeasure: name
                                 Warehouse: name
```

Note `AccountCategory.code` and `FinancialStatementLine.code` are translatable too — `code`
is not automatically a plain string.

## The Established Fix

Already applied to `Customers/Index.tsx`, `Suppliers/Index.tsx`, and `Catalog/Products.tsx`
in Phase 22. This phase extends the same pattern; it does not invent one.

1. Type the field as `TranslatedName` (exported from `resources/js/Types/index.ts`):
   ```ts
   export type TranslatedName = Record<string, string> | string | null;
   ```
2. Render through the existing helper in `resources/js/lib/accountingHelpers.ts`:
   ```ts
   getLocalizedName(value, locale)  // string | object | null → string, ar falls back to en
   ```
3. When a value feeds a form field, a `confirm()` message, or string interpolation, convert
   it first — otherwise the user sees `[object Object]`.
4. Add `locale` to the dependency array of any `useMemo`/`useCallback` that now reads it.

## Scope

**In scope:** every `.tsx` under `resources/js/Pages` and `resources/js/Components` that
displays a translatable attribute of any of the 29 models — including nested relation reads
such as `{s.supplier?.name}` (found in `PayableSettlements.tsx:341` and
`ReceivableSettlements.tsx:341`).

**Out of scope:**

- Backend services, controllers, page-data services, migrations, models.
- CSV exporters (they intentionally emit raw values; changing them alters export contracts).
- DataTable components that already pass values through `translatableName()` server-side.
- Any dictionary/`dict.app.*` label — those are UI copy, not model data.
- Business logic, permissions, routing, and financial calculations.

## Slice Plan

| Slice | File | Status | Goal |
|---|---|---|---|
| 1 | `PHASE_24_SLICE_1_AGY_PROMPT.md` | PREPARED | Audit all pages, fix every unsafe translatable render, add a regression guard. |

## Required Close-Out Evidence

- A `PHASE_24_SLICE_1_REPORT.md` listing every file changed and every defect found.
- A regression guard that fails when a page renders a translatable field without the helper.
- Browser evidence: the affected pages render with data present, with no console errors.
- Real command output. A command counts as passed only after it exits successfully.

## Verification Gate

```powershell
cd laravel
npx tsc --noEmit
vendor/bin/pint --test
node scripts/check-locale-parity.mjs
php artisan test --filter=TranslatableDisplayGuardTest
php artisan test --filter='(Phase15|Phase18|Phase19|Phase20)'
npm run build
npm run e2e
```
