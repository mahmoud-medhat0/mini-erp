# Phase 10 GL Branch Dimension & Branch Profitability Report

Status: COMPLETE & TARGET-VERIFIED  
Date: 2026-08-25  
Scope: Optional operational branch dimension on accounting journals/ledger plus ledger-backed branch profitability reporting.

## What Changed

- Added forward migration `2026_08_25_030000_add_operational_branch_dimension_to_accounting_ledger.php`.
- Added nullable operational `branch_id` to `journal_entry`, `journal_line`, and immutable `ledger_entry`.
- Added `branch()` relations to `JournalEntry`, `JournalLine`, and `LedgerEntry`.
- Updated `JournalDraftService`, `PostingEngine`, and `ReversalService` so branch tagging is preserved through draft creation, posting, ledger generation, and reversal.
- Updated treasury transfer posting so destination debit ledger rows carry destination branch and source credit ledger rows carry source branch.
- Updated inventory-generated journals from MWA stock receipt, issue, return, scrap, and adjustment to inherit branch from the operational warehouse.
- Added `/reports/branch-profitability` with `BranchProfitabilityReportService`, controller, Inertia page, reports hub card, sidebar nav item, and EN/AR dictionary text.
- Added protected CSV export at `/reports/branch-profitability/export`, requiring `reports.view`, `reports.export`, and `view_financials`.
- Added permission-aware export and print actions to the Branch Profitability Inertia page.
- Added General Ledger branch filter and branch display column.
- Updated Branch Operations report messaging from future profitability warning to link-ready operational coverage wording.

## Scope Guardrails

- No `company_id` was introduced.
- No `tenant_id` was introduced.
- No `currentCompany` or `currentBranch` context was introduced.
- No Spatie Teams scope was introduced.
- Branch is used only as an optional operational/reporting dimension, not as tenant, login scope, or security boundary.

## Verification

- `php artisan migrate --force`: passed; migration applied.
- `php artisan migrate:status`: passed; new migration is Ran.
- `vendor/bin/pint --test`: passed.
- `php artisan test tests/Feature/Phase10GlBranchProfitabilityTest.php --stop-on-failure`: 6 tests / 51 assertions passed after adding export coverage.
- `php artisan test tests/Feature/Phase10TreasuryTransferTest.php tests/Feature/Phase10GlBranchProfitabilityTest.php --stop-on-failure`: 9 tests / 75 assertions passed.
- `php artisan qa:verify-local --only-feature-files --filter=Phase10 --stop-on-failure --timeout=300`: passed all 6 Phase 10 feature files in 96,254 ms.
- `php artisan accounting:phase3-integrity-check`: passed.
- `php artisan route:list --path=reports/branch-profitability -v`: index route has `web`, `auth`, `can:reports.view`, and `can:view_financials`; export route has `web`, `auth`, `can:reports.view`, and `permission.all:reports.export,view_financials`.
- `php artisan test --filter=SecurityHardeningTest`: 6 tests / 357 assertions passed.
- `php artisan tokens:gc --batch=100`: passed, deleted zero records.
- `npm run typecheck`: passed.
- `npm run build`: passed with the existing Vite chunk-size warning only.
- `git diff --check`: passed with line-ending warnings only.

## Remaining Future Options

- Optional branch-specific GL mappings.
- Optional branch-specific approval rules.
- Optional branch defaults per user or document type.

These remain future product options and must not be implemented as tenant or security assumptions.
