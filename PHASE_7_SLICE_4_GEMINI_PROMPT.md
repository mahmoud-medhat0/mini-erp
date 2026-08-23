# MINI ERP - PHASE 7 SLICE 4 PURCHASING INPUT VAT INTEGRATION

Execute only Phase 7 Slice 4 after Slice 3 is complete and verified.

Do not implement tax filing, filed-period locks, withholding tax, landed cost, import VAT/customs, or online filing in this slice.

## Objective

Integrate owner-approved recoverable input VAT into purchasing-side documents:

- supplier bills
- supplier adjustment notes
- purchase returns where they generate tax-impacting supplier adjustments

The result must post input tax receivable through the existing PostingEngine while preserving current AP, purchase expense, GRNI/inventory, returns, and settlement behavior.

## Read First

Read and follow:

- `PHASE_7_TAX_VAT.md`
- `PHASE_7_TAX_VAT_POLICY_DECISION.md`
- `PHASE_7_SLICE_2_GEMINI_PROMPT.md`
- current `SupplierBillService`
- current `SupplierAdjustmentNoteService`
- current `PurchaseReturnService`
- current payable settlement services
- current inventory/GRNI behavior
- current `PostingEngine`, `PeriodGuard`, `AccountingAccountMappingService`
- actual purchasing-side migrations/models

## Required Implementation

Inspect actual purchasing-side schemas before changing anything.

Add only the minimum schema needed for input VAT:

- tax code/rate references on relevant purchase document lines or dedicated tax detail rows
- taxable base minor, tax minor, and gross totals as integer minor units
- immutable posted tax snapshot values so historical documents do not change when tax rates change

Rules:

- do not mutate posted supplier bills, adjustment notes, purchase returns, journal entries, or ledger entries
- corrections must use adjustment notes/returns/reversal paths already present
- tax calculation must use the effective tax rate as of the document date
- tax postings must use `input_tax_receivable`
- 100% recoverability only unless Slice 1 explicitly approved partial recovery
- no floats, no `(float)`, no `round()`, no JS floating-point division in new UI
- no hardcoded tax rates or labels in TSX
- no company/branch/tenant scopes

## Posting Rules

For supplier bills with recoverable input VAT:

- Dr purchase expense / inventory / GRNI-clearing behavior exactly as current source rules require
- Dr input tax receivable for recoverable tax amount
- Cr AP control for gross total

For supplier adjustment notes / purchase returns that reduce taxable purchases:

- Cr input tax receivable for reversed tax amount
- reverse/reduce AP according to current adjustment/return behavior

Preserve existing moving weighted average inventory and GRNI behavior from Phase 4.

## UI

Update purchasing-side pages only where needed:

- tax code selection sourced from backend active tax codes
- tax summary panels
- line/base/tax/gross display
- permission-aware controls
- dictionary-backed EN/AR visible text

Do not add hardcoded visible text or hardcoded tax options in TSX.

## Permissions

Preserve existing purchasing permissions for purchasing document lifecycle actions.

Tax configuration remains under `taxes.edit`; purchasing users must not be able to create/edit tax codes through purchasing pages unless they have tax permissions.

Posting still requires the existing purchasing posting permission plus `view_financials` where already required.

## Tests

Add tests covering:

- supplier bill draft tax calculation with integer exactness
- tax rate snapshot does not change after rate master data changes
- supplier bill posting creates balanced JV including input tax receivable
- adjustment note / purchase return reverses input tax correctly
- closed period blocks tax-affecting posting
- missing input tax mapping blocks posting
- invalid/inactive tax code rejection
- exempt/zero-rated purchase lines as approved
- duplicate/repeated posting remains idempotent
- inventory/GRNI behavior is not regressed
- permissions and UI props
- no unsupported scope columns

## Required Source Scans

Run and classify:

```powershell
rg -n "company_id|branch_id|tenant_id|currentCompany|currentBranch|Spatie Teams" laravel/app laravel/database laravel/routes laravel/resources/js laravel/tests
rg -n "\\/ 100|parseFloat|Number\\(|toFixed\\(|Math\\.round|\\(float\\)|round\\(" laravel/app laravel/resources/js laravel/tests
rg -n "created_at|updated_at" laravel/app/Application laravel/app/Http/Controllers laravel/tests/Feature
rg -n "VAT|Tax|ضريبة|tax code|tax rate" laravel/resources/js/Pages laravel/resources/js/Components
```

Non-empty scans require classification and fixes for unacceptable matches.

## Required Verification

Run from `laravel/` and wait for completion:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=Phase7Slice4
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=10
php artisan accounting:concurrency-stress --workers=50
php artisan accounting:settlement-concurrency-stress --workers=50
php artisan accounting:inventory-concurrency-stress --workers=50
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Add a purchase tax posting stress command if repeated/concurrent posting can race.

## Final Report

Report:

1. schema changes
2. tax calculation and posting rules implemented
3. purchasing documents affected
4. preserved existing purchasing/returns/inventory/accounting behavior
5. source scan classifications
6. tests and verification results
7. features intentionally not implemented
