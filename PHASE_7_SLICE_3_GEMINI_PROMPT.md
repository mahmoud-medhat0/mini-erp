# MINI ERP - PHASE 7 SLICE 3 SALES OUTPUT VAT INTEGRATION

Execute only Phase 7 Slice 3 after Slice 2 is complete and verified.

Do not implement purchasing input VAT, tax filing, tax period locks, withholding tax, e-invoicing, or online filing in this slice.

## Objective

Integrate owner-approved output VAT into sales-side documents:

- customer invoices
- customer credit notes
- sales returns where they generate tax-impacting credits

The result must post output tax payable through the existing PostingEngine while preserving current AR, revenue, returns, stock, and settlement behavior.

## Read First

Read and follow:

- `PHASE_7_TAX_VAT.md`
- `PHASE_7_TAX_VAT_POLICY_DECISION.md`
- `PHASE_7_SLICE_2_GEMINI_PROMPT.md`
- current `CustomerInvoiceService`
- current `CustomerCreditNoteService`
- current `SalesReturnService`
- current receivable settlement services
- current `PostingEngine`, `PeriodGuard`, `AccountingAccountMappingService`
- actual sales-side migrations/models

## Required Implementation

Inspect actual sales-side schemas before changing anything.

Add only the minimum schema needed for sales output tax:

- tax code/rate references on relevant sales document lines or dedicated tax detail rows
- taxable base minor, tax minor, and gross totals as integer minor units
- immutable posted tax snapshot values so historical documents do not change when tax rates change

Rules:

- do not mutate posted customer invoices, posted credit notes, posted sales returns, journal entries, or ledger entries
- corrections must use credit notes/returns/reversal paths already present
- tax calculation must use the effective tax rate as of the document date
- tax postings must use `output_tax_payable`
- no floats, no `(float)`, no `round()`, no JS floating-point division in new UI
- no hardcoded tax rates or labels in TSX
- no company/branch/tenant scopes

## Posting Rules

For customer invoices with taxable lines:

- Dr AR control for gross total
- Cr sales revenue for taxable/net amount
- Cr output tax payable for tax amount

For customer credit notes / sales returns that reduce taxable sales:

- Dr output tax payable for credited tax amount
- Dr sales returns or relevant return mapping for net amount where current behavior requires it
- Cr AR/control or follow existing credit note settlement model

Preserve existing stock/COGS/inventory posting behavior from Phase 4.

## UI

Update sales-side pages only where needed:

- tax code selection sourced from backend active tax codes
- tax summary panels
- line/base/tax/gross display
- permission-aware controls
- dictionary-backed EN/AR visible text

Do not add hardcoded visible text or hardcoded tax options in TSX.

## Permissions

Preserve existing sales permissions for sales document lifecycle actions.

Tax configuration remains under `taxes.edit`; sales users must not be able to create/edit tax codes through sales pages unless they have tax permissions.

Posting still requires the existing sales posting permission plus `view_financials` where already required.

## Tests

Add tests covering:

- sales invoice draft tax calculation with integer exactness
- tax rate snapshot does not change after rate master data changes
- customer invoice posting creates balanced JV including output tax payable
- credit note / sales return reverses output tax correctly
- closed period blocks tax-affecting posting
- missing output tax mapping blocks posting
- invalid/inactive tax code rejection
- no tax on exempt/zero-rated lines as approved
- duplicate/repeated posting remains idempotent
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
php artisan test --filter=Phase7Slice3
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

Add a sales tax posting stress command if repeated/concurrent posting can race.

## Final Report

Report:

1. schema changes
2. tax calculation and posting rules implemented
3. sales documents affected
4. preserved existing sales/returns/accounting behavior
5. source scan classifications
6. tests and verification results
7. features intentionally not implemented
