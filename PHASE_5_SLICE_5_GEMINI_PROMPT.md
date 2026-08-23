# MINI ERP - PHASE 5 SLICE 5 YEAR-END CLOSE & RETAINED EARNINGS DECISION PACK

You are continuing the active Laravel + Inertia Mini ERP migration.

Execute only Phase 5 Slice 5.

This is a decision/documentation slice.

Do not implement retained earnings postings, automatic year-end close entries, migrations, models, services, routes, UI, composer/package changes, config changes, seeders, jobs, or commands in this pass. A general accounting best practice is not owner approval.

## Read First

Read and follow:

- `CONTINUE_HERE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `PHASE_5_FINANCIAL_STATEMENTS_PERIOD_CLOSE.md`
- `PHASE_5_SLICE_1_GEMINI_PROMPT.md`
- `PHASE_5_SLICE_2_GEMINI_PROMPT.md`
- `PHASE_5_SLICE_3_GEMINI_PROMPT.md`
- `PHASE_5_SLICE_4_GEMINI_PROMPT.md`

## Objective

Produce a bounded owner decision pack for year-end close and retained earnings handling.

The decision pack must explain safe alternatives and recommend a path for Mini ERP without changing posted history.
The output must be understandable to a business owner/client, not only developers/accountants.

## Non-Negotiable Rules

Do not introduce:

- tenant/company/branch scope
- Spatie Teams
- retained earnings posting without owner approval
- automatic mutation of revenue/expense accounts
- mutation of posted ledger entries
- fiscal_year company ownership
- retained earnings GL mapping
- year-end close command/controller/page
- hidden implementation under "optional" code
- status text that implies retained earnings/year-end close is implemented

Do not mark year-end close as implemented. Mark it as OWNER DECISION REQUIRED.
Documentation may say "decision pack complete"; it must not say "year-end close complete".

## Required Decision Topics

Explain and compare these options:

1. Soft year close only
   - lock all periods in the fiscal year
   - financial statements calculate retained earnings dynamically from historical net income
   - no closing journal

2. Closing journal to retained earnings
   - post a system-generated closing journal at fiscal year end
   - debit/credit all income statement accounts to zero them
   - offset to retained earnings equity account
   - requires explicit retained earnings GL mapping
   - requires reversal/reopen policy

3. Hybrid
   - soft close for now
   - add closing journal later after retained earnings account and owner workflow are confirmed

Recommend the safest option for current Mini ERP.

Recommendation requirement:

- Recommend Option 3 (Hybrid) unless the repository/current prompt contains an explicit owner decision selecting another option.
- Explain that Mini ERP can safely continue with locked periods and calculated statements now, then add a closing journal later after the retained earnings account and reopen/reversal policy are approved.
- Do not phrase the recommendation as if retained earnings posting is already approved.

## Required Output

Create:

- `PHASE_5_YEAR_END_CLOSE_DECISION.md`

Include:

- plain-language explanation for the owner/client
- Arabic owner-facing summary plus English technical notes
- accounting impact
- database impact
- audit impact
- reopen impact
- permissions needed
- implementation plan for the selected future slice
- exact owner question/decision statement
- risks and controls for each option
- what happens to Balance Sheet, Income Statement, Cash Flow, and comparative reports under each option
- what must be tested if/when implementation is later approved
- a clear "not implemented yet" section listing migrations/models/services/routes/pages intentionally not added

The exact owner decision statement must ask the owner to choose one of:

1. Hybrid soft close now, closing journal later after approval.
2. Soft close only, no closing journal.
3. Closing journal to retained earnings.

If choosing option 3 later, require the owner to also approve:

- retained earnings GL account/mapping
- closing journal date
- whether income statement accounts are zeroed physically by journal or only presented as closed
- reopen/reversal policy
- who can run the close

## Optional Safe Code

Allowed only if useful and non-invasive:

- add no-op documentation/status updates
- add tests that prove no retained earnings posting exists yet

Do not add migrations, models, services, or UI unless explicitly required by a current owner decision.
If any PHP/TS code is changed, clearly justify why it was necessary for a documentation slice.
Preferred result is docs-only. If you touch any file under `laravel/app`, `laravel/database`, `laravel/routes`, `laravel/resources/js`, or `laravel/tests`, treat that as an exception and explain it before reporting success.

## Permissions

The future implementation must use:

- `close_period`
- `reopen_period`
- `view_financials`
- exact future retained-earnings mapping permission, likely `accounting.mappings`

Do not use broad shortcuts.

## UI Text Rule

If any UI/docs-facing page is touched, no hardcoded user-facing text in TSX. Use EN/AR dictionaries.

## Required Verification

For documentation-only execution:

```powershell
git diff --stat
git diff --name-only
rg -n "retained earnings|retained_earnings|closing journal|year.end|year-end" laravel/database laravel/app laravel/routes laravel/resources/js laravel/tests
```

The `rg` command should prove no implementation was added. Because it intentionally scans only Laravel implementation/test paths, any non-empty output must be investigated and reported as either pre-existing reference or fixed.

If any code is changed, also run:

```powershell
vendor/bin/pint --test
php artisan test
npm run typecheck
npm run build
```

Report whether this was docs-only or code-changing.
Report explicitly that no migrations/models/services/routes/pages were added if it remains docs-only.
Do not report code tests as passed unless the commands actually completed synchronously. If a command times out or is skipped, say so.
