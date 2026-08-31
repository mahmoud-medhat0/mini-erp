# Mini ERP - Phase 19 Slice 2 Agy Prompt

Execute ONLY Phase 19 Slice 2: End-to-End Accountant Workflow Acceptance Tests.

Stop after this slice. Do not start Slice 3.

## Scope

Use the Phase 19 acceptance data pack to prove the ERP behaves correctly across real accountant workflows:

- procure-to-pay
- inventory receipt/costing
- supplier bill and payment
- order-to-cash
- delivery and invoice posting
- sales return and customer credit note
- customer receipt and allocation/settlement
- VAT register/summary/reconciliation
- GL, subledger, stock, and financial report consistency

This slice may add tests and narrowly scoped bug fixes only when a failing acceptance test proves a defect.

## Non-Negotiable Rules

- No multi-tenant architecture and no company/tenant/security scope changes.
- Do not add blanket branch scope. Branch is operational/reporting only where current implementation already supports it.
- Do not change accepted accounting/tax/stock formulas unless a failing test exposes a clear defect.
- Do not bypass services by inserting posted business documents directly into tables.
- Do not write Telegram credentials, chat IDs, API keys, passwords, or production secrets to files.
- Keep controllers thin. Do not add workflow logic to controllers.
- Preserve append-only ledger and stock movement behavior.

## Required Review Before Editing

Inspect:

- `PHASE_19_SLICE_1_REPORT.md`
- `laravel/database/seeders/AccountantAcceptanceSeeder.php`
- `laravel/tests/Feature/Phase4Slice*.php`
- `laravel/tests/Feature/Phase7Slice*.php`
- `laravel/tests/Feature/Phase10*.php`
- services under `laravel/app/Application/Sales`, `Purchasing`, `Inventory`, `Accounting`, and `Taxes`.

## Required Tests

Extend `Phase19AccountantAcceptanceTest` or create a focused companion test proving representative end-to-end acceptance:

1. Acceptance seeder prepares all baseline records.
2. Purchase order can be created/submitted/confirmed for the acceptance supplier/product.
3. Goods receipt can be created/confirmed and creates stock quantity/value through the existing inventory service.
4. Supplier bill can be created/submitted/approved/posted using existing services and produces AP + VAT input + inventory/GRNI accounting according to current implementation.
5. Supplier payment can be posted and allocated/settled against the supplier bill.
6. Sales order can be created/submitted/confirmed for the acceptance customer/product.
7. Delivery note can be created/confirmed and creates COGS/inventory movement using moving weighted average.
8. Customer invoice can be created/submitted/approved/posted and produces AR + revenue + VAT output accounting.
9. Customer receipt can be posted and allocated/settled against the invoice.
10. Sales return and customer credit note scenario is exercised with partial returned quantity and correct AR/revenue/VAT/inventory effect.
11. VAT report services show acceptance transactions and reconcile to GL tax accounts.
12. General ledger, trial balance, and key reports remain balanced after the scenario.
13. Running the acceptance scenario twice is idempotent or uses stable scenario codes that prevent duplicate posted documents.

If an existing service does not support a specific scenario, document the gap in `PHASE_19_SLICE_2_REPORT.md` and add the narrowest safe test around the supported behavior instead of inventing an unsupported workflow.

## Optional Support Class

If the test setup becomes long, create a support class under `laravel/tests/Support` or an application acceptance service under `laravel/app/Application/Acceptance`. It must delegate to existing business services and must not become a parallel posting engine.

## Documentation

Create `PHASE_19_SLICE_2_REPORT.md` with:

- exact files changed
- scenario executed
- accounting evidence: journal count, ledger entry count, AR/AP entries, stock movements, VAT entries
- reports verified
- defects found/fixed or gaps documented
- no-scope scan result
- test results
- remaining risks

Update:

- `PHASE_19_ACCOUNTANT_ACCEPTANCE_EXECUTION.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

## Verification Commands

Run from `laravel/` and report exact results:

```powershell
vendor/bin/pint --test
php artisan test --filter=Phase19AccountantAcceptanceTest --compact
php artisan test --filter=Phase4Slice10ReturnsCreditNotesTest --compact
php artisan test --filter=Phase7Slice5VatReportsTest --compact
php artisan test --testsuite=Concurrency --compact
php artisan security:route-audit --strict
npm run typecheck
```

Run `npm run build` only if frontend files changed.

## Final Rule

Stop after Phase 19 Slice 2 and create the report. Do not start Slice 3.
