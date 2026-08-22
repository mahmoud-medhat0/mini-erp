# MINI ERP - PHASE 5 SLICE 5 YEAR-END CLOSE & RETAINED EARNINGS DECISION PACK

You are continuing the active Laravel + Inertia Mini ERP migration.

Execute only Phase 5 Slice 5.

This is a decision/documentation slice unless the owner has already provided an explicit decision in writing.

Do not implement retained earnings postings or automatic year-end close entries in this pass unless a clear owner decision already exists in the repository or the current prompt.

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

## Non-Negotiable Rules

Do not introduce:

- tenant/company/branch scope
- Spatie Teams
- retained earnings posting without owner approval
- automatic mutation of revenue/expense accounts
- mutation of posted ledger entries
- fiscal_year company ownership

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

## Required Output

Create:

- `PHASE_5_YEAR_END_CLOSE_DECISION.md`

Include:

- plain-language explanation for the owner/client
- accounting impact
- database impact
- audit impact
- reopen impact
- permissions needed
- implementation plan for the selected future slice
- exact owner question/decision statement

## Optional Safe Code

Only if useful and non-invasive:

- add no-op documentation/status updates
- add tests that prove no retained earnings posting exists yet

Do not add migrations, models, services, or UI unless explicitly required by a current owner decision.

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
```

If any code is changed, also run:

```powershell
vendor/bin/pint --test
php artisan test
npm run typecheck
npm run build
```

Report whether this was docs-only or code-changing.
