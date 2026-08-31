# PHASE 2 - ACCOUNTING CORE IMPLEMENTATION CONTRACT

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


> Current status update, 2026-08-21: Phase 2 Accounting Core has been implemented and locally verified in the Laravel target. Treat this file as the historical implementation contract and regression checklist, not as an instruction to rebuild Phase 2 from scratch. Current status lives in `IMPLEMENTATION_STATUS.md`; next tasks live in `NEXT_TASKS.md`.

Audience: Gemini Flash 3.6 executing inside this repository.

Purpose: implement Phase 2 Accounting Core only, using the current Laravel target and the latest owner corrections. This is an execution contract, not a brainstorming document. Follow it directly. Do not reconstruct architecture from older generated specs.

Current date/context: 2026-08-21. The Laravel foundation is implemented and locally verified. No GitHub Actions pipeline is connected for this Laravel migration track.

## 1. Mandatory Pre-Flight Inspection

Before making any schema or code change, inspect the current repository state again. Do not rely on this document alone if files changed after it was written.

Read these current-source documents first:

- `README.md`
- `IMPLEMENTATION_STATUS.md`
- `DOMAIN_MODEL_REVIEW.md`
- `DOMAIN_RELATIONSHIP_AUDIT.md`
- `SCHEMA_ASSUMPTION_AUDIT.md`
- `PROJECT_LOGIC_AUDIT.md`
- `docs/CONCURRENCY_AUDIT.md`
- `spec/MASTER_ERP_SPEC.md`
- `spec/DATABASE_DESIGN.md`
- `spec/SECURITY.md`
- `spec/BUSINESS_RULES.md`
- `spec/ACCOUNTING_EVENT_MAP.md`
- `spec/WORKFLOW_CATALOG.md`
- `spec/PERMISSION_MATRIX.md`

Then inspect the actual Laravel implementation:

- `laravel/app`
- `laravel/config`
- `laravel/database`
- `laravel/routes`
- `laravel/resources/js`
- `laravel/tests`
- `laravel/composer.json`
- `laravel/package.json`

Use the actual Laravel code as the implementation source of truth. Treat old Next.js, Prisma, and generated planning documents as historical unless they agree with owner corrections and current Laravel implementation.

## 2. Current Architecture Truth

The target stack is:

- Laravel + Inertia.js + React + TypeScript + Tailwind + PostgreSQL.
- PHP 8.3+.
- Laravel framework 13.x.
- Inertia Laravel 3.x.
- React 19.x.
- Tailwind 4.x.
- Spatie Laravel Permission with teams disabled.
- Spatie Laravel Translatable for multilingual master data where useful.

Current foundation already includes:

- Laravel session authentication with throttling and active-user checks.
- Global RBAC role templates and permissions through Spatie Permission.
- First-user `SUPER_ADMIN` seeding.
- Company profile configuration only.
- Standalone Branch reference records only.
- FiscalYear finalized as global single-ERP context.
- FinancialPeriod belongs to FiscalYear.
- Exact Money value object.
- Accounting draft-entry invariant kernel.
- Currency registry.
- Number sequence allocator with PostgreSQL `INSERT ... ON CONFLICT ... DO UPDATE RETURNING`.
- Idempotency store.
- Optimistic locking currently used for `company` and `branch`.
- Spatie Activitylog-backed audit service with append-only database enforcement.
- Attachment and notification service foundations.
- Token garbage collection.
- PostgreSQL concurrency stress command for existing numbering/idempotency primitives.

## 3. Non-Negotiable Domain Corrections

The ERP is NOT a multi-tenant SaaS.

Never introduce or restore:

- tenant context
- `tenant_id`
- tenant middleware
- `company_user`
- `users.company_id`
- `branch.company_id`
- `fiscal_year.company_id`
- `currentCompany`
- `currentBranch`
- Company-owned roles
- Company-owned permissions
- Spatie Teams
- company query scopes
- branch query scopes
- company-scoped document numbering
- branch-scoped document numbering
- Company/Branch locks in accounting posting
- Company/Branch authorization boundaries

If a relationship is not explicitly supported by current owner decisions, classify it as:

`UNDEFINED — DO NOT ASSUME`

Resolved relationships and decisions:

- FiscalYear is finalized as `SINGLE-ERP CONTEXT`.
- `fiscal_year.year` is globally unique.
- `financial_period.fiscal_year_id` remains the valid relationship.
- Branch exact semantics remain unresolved; do not introduce Branch into accounting tables.
- Project and CostCenter remain future concepts; do not introduce them unless this phase explicitly defines only nullable placeholders with owner approval. Default for this phase: do not add project/cost-center columns to accounting tables.

Do not treat `docs/PROJECT_MAP.md` as source of truth. It is legacy Next.js planning material.

Do not restore:

- Prisma schema as target architecture
- Auth.js
- Next.js backend patterns
- Zod as backend authority
- decimal.js as backend authority
- pg-boss as target queue architecture
- old tenant/session-derived company logic

## 4. Phase Boundary

Phase 2 is Accounting Core only.

Implement:

1. Account Groups
2. Chart of Accounts
3. FiscalYear / FinancialPeriod management
4. Exchange Rates
5. JournalEntry
6. JournalLine
7. Manual Journal Voucher
8. Posting Engine
9. Ledger
10. General Journal
11. General Ledger
12. Trial Balance
13. Reversal
14. Closed-period enforcement
15. Opening Balances
16. Accounting RBAC
17. Accounting audit trail
18. Inertia/React accounting UI
19. PostgreSQL constraints
20. idempotency
21. concurrency/race-condition handling
22. posting-vs-period-close protection
23. duplicate-post protection
24. duplicate-reversal protection
25. tests
26. documentation updates
27. final verification commands
28. explicit definition of done

Do NOT implement unrelated modules:

- Sales operational workflows
- Purchasing operational workflows
- Inventory operational workflows
- AR/AP subledger workflows
- Cash module
- Banks module
- Cheques module
- Payroll
- Rentals
- Fixed Assets
- Taxes module beyond exchange-rate/future account placeholders
- Full Financial Statements
- Dashboard KPI accounting rollups beyond minimal links/counts needed for navigation
- Approval workflow engine beyond the journal statuses required below
- Project/CostCenter module
- Branch/Department dimensions

Phase 2 may create accounting rows that future modules will post into, but it must not implement those modules.

## 5. Reuse Existing Primitives

Reuse and extend carefully instead of duplicating:

- `App\Domain\Money\Money`
- `App\Domain\Accounting\AccountingKernel`
- `App\Domain\Accounting\DraftEntry`
- `App\Domain\Accounting\DraftLine`
- `App\Domain\Currency\CurrencyRegistry`
- `App\Domain\Numbering\DocumentNumberFormatter`
- `App\Domain\Numbering\NumberSequenceConfig`
- `App\Support\Numbering\NumberSequenceAllocator`
- `App\Support\Concurrency\DatabaseIdempotencyStore`
- `App\Support\Concurrency\TransactionRetrier`
- `App\Support\Concurrency\OptimisticLock` only if consciously generalized with tests; currently it allows only `company` and `branch`
- `App\Domain\Audit\AuditLogger`
- Existing RBAC catalog in `config/erp_rbac.php`
- Existing Inertia app shell, locale dictionaries, and UI primitives
- Existing token GC and `concurrency:stress` patterns

Do not add a second money implementation, second audit logger, second idempotency table, second numbering allocator, or duplicate RBAC system.

## 6. Accounting Rules To Encode

Mandatory rules:

- Use exact integer minor-unit money only.
- No float/double accounting math.
- Do not use PHP floats for monetary calculation, FX conversion, ledger totals, or Trial Balance totals.
- Store monetary amounts in integer minor units.
- Store FX rates as scaled integers, using the existing `rate_e6` / `fx_rate_e6` convention unless a stronger existing convention is found during inspection.
- Debit total must equal credit total before posting.
- Invalid debit/credit lines are rejected:
  - negative debit/credit rejected
  - both debit and credit on one line rejected
  - neither debit nor credit on one line rejected
  - fewer than two lines rejected
- Manual journals cannot post directly to control accounts.
- Posting must be atomic in PostgreSQL.
- Posting must be idempotent.
- Posting must be concurrency-safe.
- Posted journal data is immutable.
- Corrections are by reversal, not edit/delete.
- Ledger derives only from posted journals.
- General Journal derives only from journal entries/lines.
- General Ledger derives only from posted ledger entries.
- Trial Balance derives only from posted ledger entries.
- Period close must race safely against posting.
- Do not lock Company or Branch rows during accounting posting.
- Lock only the smallest rows needed for the invariant.
- No external side effects inside the DB transaction.
- Server-side authorization is authoritative.
- Browser-provided source type is never trusted for posting.
- Browser can submit manual JV draft data; server decides trusted `source_type`, `source_id`, status transitions, posting actor, and number allocation.

## 7. Recommended Implementation Slices

Work in small verified slices. After each slice, run the listed targeted tests before moving on.

### Slice 0 - Reinspect And Baseline

Tasks:

- Run `git status --short --untracked-files=all`.
- Read the mandatory docs and implementation files.
- Confirm no active `company_id` / `branch_id` runtime columns exist except cleanup migrations/tests.
- Confirm `permission.teams` is false.
- Confirm existing tests pass before changing schema if practical.

Stop if the worktree has unrelated user edits in the files you need to change and you cannot safely work around them.

### Slice 1 - Accounting Schema

Add Laravel migrations for Phase 2 accounting tables and indexes only.

Do not edit old migrations unless a fresh-install migration is already current project style and you fully preserve already-applied migration compatibility. Prefer new migrations for new schema.

Required tables:

- `account_group`
- `account`
- `journal_entry`
- `journal_line`
- `ledger_entry`
- `opening_balance`

Existing tables to reuse:

- `fiscal_year`
- `financial_period`
- `exchange_rate`
- `number_sequence`
- `activity_log` through `AuditLogger`
- legacy `audit_log` archive
- `idempotency_keys`
- `users`
- Spatie permission tables

Add fields to existing tables only when required and justified:

- `financial_period`: add `lock_version`, `closed_at`, `closed_by`, `reopened_at`, `reopened_by` if absent.
- `fiscal_year`: add lifecycle timestamps only if required for UI/period management; never add `company_id`.
- `exchange_rate`: keep existing unique `(currency, date)` and `rate_e6`.

### Slice 2 - Models / Domain / Services

Add Eloquent models or query-layer service classes consistent with current Laravel style.

Recommended namespaces:

- `App\Models\AccountGroup`
- `App\Models\Account`
- `App\Models\JournalEntry`
- `App\Models\JournalLine`
- `App\Models\LedgerEntry`
- `App\Models\OpeningBalance`
- `App\Application\Accounting\JournalDraftService`
- `App\Application\Accounting\PostingService`
- `App\Application\Accounting\ReversalService`
- `App\Application\Accounting\PeriodService`
- `App\Application\Accounting\TrialBalanceService`
- `App\Application\Accounting\GeneralLedgerService`

Use `AccountingKernel::assertBalanced()` for draft/post validation.

### Slice 3 - RBAC And Audit

Extend current global RBAC only if the permission does not already exist.

Current `config/erp_rbac.php` already includes:

- `accounting.view`
- `accounting.create`
- `accounting.edit`
- `accounting.submit`
- `accounting.approve`
- `accounting.post`
- `accounting.reverse`
- `accounting.export`
- `accounting.print`
- `close_period`
- `reopen_period`
- `view_financials`

If extra permissions are necessary, add only explicit accounting permissions. Do not add company/branch scoped permissions.

Audit every important action:

- `account_group.create`
- `account_group.update`
- `account.create`
- `account.update`
- `fiscal_year.create`
- `fiscal_year.update`
- `financial_period.create`
- `financial_period.close`
- `financial_period.reopen`
- `exchange_rate.create`
- `exchange_rate.update`
- `journal_entry.create`
- `journal_entry.update`
- `journal_entry.submit`
- `journal_entry.approve`
- `journal_entry.post`
- `journal_entry.reverse`
- `opening_balance.create`
- `opening_balance.post`

Use `AuditLogger`, which is backed by Spatie Activitylog in the current Laravel target. Do not invent company/branch audit fields.

### Slice 4 - Posting Engine

Implement posting for manual Journal Vouchers and opening-balance journals only.

Posting must:

- require `accounting.post`
- optionally require `accounting.approve` or approved status depending on final workflow implementation
- require open or reopened financial period
- reject closed periods
- reject locked fiscal years
- use `AccountingKernel::assertBalanced`
- reject manual posting to control accounts
- allocate a JV number through existing numbering primitives if number is not already assigned
- create ledger entries from journal lines
- change `journal_entry.status` to `posted`
- set `posted_at` and `posted_by`
- record audit
- complete inside one DB transaction
- run through `DatabaseIdempotencyStore`

No notifications, emails, file writes, queues, or external effects inside the posting transaction.

### Slice 5 - Reversal

Implement reversal for posted journal entries.

Reversal must:

- require `accounting.reverse`
- require target reversal period open/reopened
- be idempotent
- reject duplicate reversal
- lock original entry and target period
- create a new reversing journal entry with mirrored debit/credit lines
- link original and reversal entries both ways where possible
- mark original as `reversed` only after reversal entry is posted
- record audit
- preserve the original posted journal and ledger rows
- never update/delete original lines or ledger entries

### Slice 6 - Reports

Implement read-only Phase 2 accounting reports:

- General Journal
- General Ledger
- Trial Balance

All must read only posted data where financial totals are shown.

Do not implement:

- Income Statement
- Balance Sheet
- Cash Flow
- Equity Statement
- AR/AP aging
- Inventory valuation
- Dashboard financial KPIs

### Slice 7 - Inertia UI

Implement accounting UI pages using existing app shell and primitives.

Routes should be Laravel/Inertia routes under auth middleware:

- `GET /accounting`
- `GET /accounting/coa`
- `GET /accounting/journal`
- `GET /accounting/journal/create`
- `GET /accounting/journal/{journalEntry}`
- `POST /accounting/journal`
- `PATCH /accounting/journal/{journalEntry}`
- `POST /accounting/journal/{journalEntry}/submit`
- `POST /accounting/journal/{journalEntry}/approve`
- `POST /accounting/journal/{journalEntry}/post`
- `POST /accounting/journal/{journalEntry}/reverse`
- `GET /accounting/ledger`
- `GET /accounting/trial-balance`
- `GET /accounting/periods`
- `POST /accounting/periods`
- `POST /accounting/periods/{period}/close`
- `POST /accounting/periods/{period}/reopen`
- `GET /accounting/opening-balances`
- `POST /accounting/opening-balances`
- `GET /accounting/fx-rates`
- `POST /accounting/fx-rates`

UI requirements:

- Use existing `AppLayout`.
- Use existing locale/direction shared props.
- Use existing dictionaries or add entries under `resources/js/locales/en.json` and `ar.json`.
- Provide permission-denied states instead of dead buttons.
- Include loading/empty/error states.
- Keep Phase 2 screens operational, not marketing pages.
- Use integer minor units in payloads or validated decimal strings converted server-side; never trust frontend math for posted totals.
- The journal line editor may show a live balance preview, but server validation remains authoritative.
- Do not use browser-provided totals as trusted posting totals.

## 8. Detailed Schema Contract

Use PostgreSQL-compatible constraints. SQLite test compatibility is useful, but PostgreSQL correctness wins.

Use UUID primary keys for new accounting business tables unless current project style changes before implementation. Existing `users` remains integer id.

Use JSON/JSONB multilingual `name` columns consistently with existing `company`, `branch`, and `currency`.

### `account_group`

Required columns:

- `id` uuid primary key
- `code` string
- `name` json/jsonb multilingual
- `type` string check: `asset`, `liability`, `equity`, `revenue`, `expense`
- `statement_section` string nullable
- `parent_id` uuid nullable FK to `account_group.id` restrict/null depending chosen tree rules
- `sort_order` integer default 0
- `is_active` boolean default true
- `created_at`, `updated_at`

Constraints/indexes:

- global unique `code`
- index `type`
- index `parent_id`
- no `company_id`
- no `branch_id`

Stop if account-group hierarchy semantics materially conflict with current owner expectations.

### `account`

Required columns:

- `id` uuid primary key
- `code` string
- `name` json/jsonb multilingual
- `type` string check: `asset`, `liability`, `equity`, `revenue`, `expense`
- `nature` string check: `debit`, `credit`
- `account_group_id` uuid nullable FK to `account_group.id`
- `parent_id` uuid nullable FK to `account.id`
- `currency` char(3) nullable FK/reference to `currency.code` if practical
- `is_control` boolean default false
- `allow_manual_posting` boolean default true
- `is_active` boolean default true
- `lock_version` unsigned integer default 0 if optimistic editing is implemented
- `created_at`, `updated_at`

Constraints/indexes:

- global unique `code`
- index `type`
- index `nature`
- index `account_group_id`
- index `parent_id`
- check `allow_manual_posting = false` when `is_control = true`, or enforce in service if check is too awkward cross-DB
- no `company_id`
- no `branch_id`

Recommended Phase 2 decision: account codes are globally unique in the single ERP context. Stop only if requirements demand another scope or the user says account code uniqueness is not global.

### `journal_entry`

Required columns:

- `id` uuid primary key
- `number` string nullable until posting or required when approved; unique when present
- `entry_date` date
- `financial_period_id` uuid FK to `financial_period.id`
- `source_type` string controlled server-side
- `source_id` string controlled server-side
- `description` text nullable
- `reference` string nullable
- `currency` char(3)
- `fx_rate_e6` big integer default 1000000
- `status` string check: `draft`, `submitted`, `approved`, `posted`, `reversed`, `cancelled`
- `created_by` FK users nullable
- `updated_by` FK users nullable
- `submitted_by`, `submitted_at` nullable
- `approved_by`, `approved_at` nullable
- `posted_by`, `posted_at` nullable
- `reversed_by`, `reversed_at` nullable
- `reverses_entry_id` uuid nullable FK to `journal_entry.id`
- `reversal_entry_id` uuid nullable FK to `journal_entry.id`
- `lock_version` unsigned integer default 0
- `created_at`, `updated_at`

Constraints/indexes:

- unique `number` where not null
- index `financial_period_id`
- index `entry_date`
- index `status`
- index `(source_type, source_id)`
- unique posted source guard where applicable: prevent more than one posted journal for the same trusted source/action
- no `company_id`
- no `branch_id`

Source rules:

- For manual JV, server sets `source_type = manual_journal`.
- Browser must not be allowed to choose trusted posting source type.
- For opening balance, server sets `source_type = opening_balance`.
- Future modules may use their own source types later, but not in Phase 2.

### `journal_line`

Required columns:

- `id` uuid primary key
- `journal_entry_id` uuid FK to `journal_entry.id` cascade on draft delete, restrict/guard after posted
- `line_no` unsigned integer
- `account_id` uuid FK to `account.id`
- `memo` text nullable
- `debit_minor` big integer default 0
- `credit_minor` big integer default 0
- `currency` char(3)
- `fx_rate_e6` big integer default 1000000
- `debit_txn_minor` big integer default 0
- `credit_txn_minor` big integer default 0
- `created_at`, `updated_at`

Constraints/indexes:

- unique `(journal_entry_id, line_no)`
- index `account_id`
- check debit/credit values are non-negative
- check exactly one side is positive
- no `company_id`
- no `branch_id`
- no unapproved dimension columns

### `ledger_entry`

Required columns:

- `id` uuid primary key
- `journal_entry_id` uuid FK to `journal_entry.id`
- `journal_line_id` uuid FK to `journal_line.id`
- `account_id` uuid FK to `account.id`
- `financial_period_id` uuid FK to `financial_period.id`
- `entry_date` date
- `debit_minor` big integer default 0
- `credit_minor` big integer default 0
- `currency` char(3)
- `fx_rate_e6` big integer default 1000000
- `debit_txn_minor` big integer default 0
- `credit_txn_minor` big integer default 0
- `created_at`

Constraints/indexes:

- unique `journal_line_id` to prevent duplicate ledger rows
- index `(account_id, entry_date)`
- index `(financial_period_id, account_id)`
- index `journal_entry_id`
- immutable by service; consider DB trigger/rule if implemented safely
- no `company_id`
- no `branch_id`

Ledger rows are derived from posted journal lines only. Do not manually edit ledger rows.

### `opening_balance`

Required columns:

- `id` uuid primary key
- `fiscal_year_id` uuid FK to `fiscal_year.id`
- `account_id` uuid FK to `account.id`
- `debit_minor` big integer default 0
- `credit_minor` big integer default 0
- `currency` char(3)
- `fx_rate_e6` big integer default 1000000
- `journal_entry_id` uuid nullable FK to `journal_entry.id`
- `status` string check: `draft`, `posted`, `cancelled`
- `created_by`, `posted_by` nullable user FKs
- `posted_at` nullable
- `created_at`, `updated_at`

Constraints/indexes:

- unique `(fiscal_year_id, account_id)` for Phase 2 account-level opening balance
- check exactly one side is positive unless zero opening balances are explicitly allowed
- no party/customer/supplier opening balances in Phase 2
- no inventory opening balances in Phase 2
- no `company_id`
- no `branch_id`

Stop if owner requires party-level opening balances, inventory openings, or retained earnings carry-forward semantics before account-level opening balances can be safely implemented.

### Existing `fiscal_year`

Keep:

- `id`
- `year`
- `start_date`
- `end_date`
- `status`
- global unique `year`

Never add `company_id`.

Valid statuses from current foundation: `open`, `closed`, `locked`.

### Existing `financial_period`

Keep:

- `id`
- `fiscal_year_id`
- `month`
- `start_date`
- `end_date`
- `status`
- unique `(fiscal_year_id, month)`
- FK to `fiscal_year.id`

May add:

- `lock_version`
- `closed_by`, `closed_at`
- `reopened_by`, `reopened_at`

Valid statuses from current foundation: `open`, `closed`, `reopened`.

Postable statuses: `open`, `reopened`.

Closed status blocks posting.

### Existing `exchange_rate`

Keep:

- `id`
- `currency`
- `date`
- `rate_e6`
- unique `(currency, date)`

Do not use floats. Validate rates as scaled integers or decimal strings converted server-side to `rate_e6`.

Stop if FX rounding rules are insufficient for a required conversion path. Do not guess beyond `rate_e6` and integer-minor arithmetic.

## 9. Posting Transaction Contract

Posting must be one short PostgreSQL transaction inside an idempotency envelope.

Recommended flow:

1. Build idempotency key:
   - use `AccountingKernel::postingIdempotencyKey($sourceType, $sourceId, 'post')`
   - for manual JV: source type is server-controlled `manual_journal`, source id is the journal entry id
2. Call `DatabaseIdempotencyStore::run('accounting.post', $key, callback, actorId, requestFingerprint, expiresAt)`.
3. Inside callback, open `DB::transaction`.
4. Acquire locks in the required order below.
5. Validate permissions before mutation.
6. Validate period status.
7. Validate journal status.
8. Load lines and accounts.
9. Reject control accounts for manual JV.
10. Build `DraftEntry` and `DraftLine` values.
11. Call `AccountingKernel::assertBalanced`.
12. Allocate number if required.
13. Insert ledger rows.
14. Update journal status to `posted`.
15. Audit post action.
16. Commit.
17. Run post-commit non-critical side effects only after commit.

Do not send notifications, emails, file writes, or queue jobs inside the transaction.

## 10. Deterministic Lock Order

Use this lock order for accounting posting and reversal.

1. `financial_period` row with `SELECT ... FOR UPDATE`.
2. Source `journal_entry` row or trusted source document row with `SELECT ... FOR UPDATE`.
3. `number_sequence` row if a number must be allocated. Prefer existing `NumberSequenceAllocator`; if it must be called inside the transaction, do so after period and journal locks.
4. Accounting rows requiring locks:
   - journal lines for the locked journal
   - accounts only if their mutable state is changed
   - original journal entry for reversal
5. Insert-only rows:
   - ledger entries
   - audit log rows
6. Post-commit side effects.

Never lock Company or Branch rows for accounting posting.

Avoid reverse order. Avoid long transactions. Avoid user-facing sleeps or external calls inside transactions.

## 11. Period Close Contract

Period close must race safely with posting.

Close flow:

1. Require `close_period`.
2. Open a DB transaction.
3. Lock `financial_period` row `FOR UPDATE`.
4. Verify no currently in-progress posting can commit into the period after close:
   - the posting transaction must lock the same period row first
   - closing and posting serialize on that row
5. Update status to `closed`.
6. Set `closed_by`, `closed_at` if columns exist.
7. Audit `financial_period.close`.
8. Commit.

Reopen flow:

1. Require `reopen_period`.
2. Lock period row.
3. Update status to `reopened`.
4. Set `reopened_by`, `reopened_at`.
5. Audit `financial_period.reopen`.

Do not use Company/Branch context in period close.

## 12. Reversal Contract

Reversal flow:

1. Require `accounting.reverse`.
2. Use idempotency key `AccountingKernel::postingIdempotencyKey('manual_journal', $originalEntryId, 'reverse')` or equivalent trusted source/action tuple.
3. Lock target reversal `financial_period`.
4. Lock original `journal_entry`.
5. Reject if original is not `posted`.
6. Reject if original already has `reversal_entry_id`.
7. Create reversal journal with:
   - `source_type = reversal`
   - `source_id = original journal id`
   - `reverses_entry_id = original journal id`
8. Copy lines with debit and credit swapped.
9. Validate balanced entry.
10. Post reversal entry atomically.
11. Set original status to `reversed` and `reversal_entry_id`.
12. Audit original and reversal.

Do not delete original ledger rows.

## 13. Manual Journal Voucher Contract

Manual JV statuses:

- `draft`
- `submitted`
- `approved`
- `posted`
- `reversed`
- `cancelled`

Allowed transitions:

- draft -> submitted
- submitted -> approved
- submitted -> draft (reject)
- approved -> posted
- draft/submitted/approved -> cancelled
- posted -> reversed through reversal service

Editing:

- allowed only before posted/reversed/cancelled
- must use optimistic locking or equivalent stale-update protection
- stale draft update vs posting must be tested

Posting:

- allowed only from `approved` unless owner explicitly approves direct draft posting
- if direct posting is temporarily allowed for `SUPER_ADMIN`, document it and test it; preferred contract is approved-before-posted

Manual JV cannot post to `is_control = true` accounts or accounts with `allow_manual_posting = false`.

## 14. General Journal, Ledger, Trial Balance

General Journal:

- Reads journal entries and lines.
- Can show draft and posted entries, but financial totals/reporting sections must clearly distinguish status.

General Ledger:

- Reads `ledger_entry` only.
- Filters by account, period, date range.
- Shows debit, credit, net movement, and running balance if implemented.
- Running balances must be computed from posted ledger rows only.

Trial Balance:

- Reads `ledger_entry` only.
- Groups by account.
- Debit balance / credit balance computed from posted ledger rows only.
- Must assert total debit equals total credit for selected period/range.
- Do not include draft journals.

Full financial statements are out of scope.

## 15. Numbering Contract

Use existing `number_sequence` and `NumberSequenceAllocator`.

Recommended sequence key:

- `accounting.journal`

Recommended display prefix:

- `JV`

Required warning:

- Final JV numbering reset policy is an owner decision if materially ambiguous.
- Do not add company/branch dimensions.
- Do not add `include_branch`.
- Do not add `company_id`.
- Do not infer legal invoice numbering semantics from JV numbering.

If the current database has no configured `accounting.journal` sequence and posting cannot safely proceed without choosing reset behavior, STOP and ask owner for JV numbering policy. Do not silently invent a legal numbering scheme.

## 16. FX / Multi-Currency Contract

Phase 2 must support the existing exchange-rate table and enough fields for journals:

- base minor amounts
- transaction minor amounts
- currency code
- `fx_rate_e6`

Rules:

- Do not use floats.
- Convert decimal input strings server-side.
- Store rate as scaled integer.
- Reject missing exchange rate for non-base currency unless user explicitly supplies a validated rate.
- Use base currency from company profile only as configuration; do not treat Company as owner/tenant.
- Do not implement realized/unrealized FX jobs in Phase 2 unless explicitly requested later.

Stop if required FX rounding behavior cannot be encoded from current specs and owner decisions.

## 17. Opening Balances Contract

Phase 2 opening balances are account-level only.

Allowed:

- Opening balance draft rows per fiscal year and account.
- Posting opening balances into a JournalEntry with `source_type = opening_balance`.
- Balanced opening JV.
- Audit and idempotency.

Not allowed in Phase 2:

- Customer opening balances
- Supplier opening balances
- Inventory opening balances
- Fixed asset opening balances
- Payroll opening balances
- Automated retained-earnings year-end close

Stop if owner expects opening balances to include subledgers or inventory in this phase.

## 18. PostgreSQL Constraints And DB Integrity

Use DB constraints wherever practical:

- enum-like check constraints for statuses and account types.
- non-negative debit/credit checks.
- exactly-one-side debit/credit checks.
- unique journal number.
- unique ledger row per journal line.
- unique opening balance per fiscal year/account.
- FK from lines to accounts and entries.
- FK from ledger to entries/lines/accounts/periods.
- FK from entries to periods and users.
- indexes for report access patterns.

Immutable posted data:

- Enforce in service layer at minimum.
- Add DB-level immutability only if it can be tested and does not break migrations.
- If adding triggers/rules, include tests proving posted journal lines and ledger entries cannot be updated/deleted.

## 19. Authorization Contract

Use server-side authorization only.

Permissions:

- viewing accounting pages: `accounting.view` or `view_financials` depending sensitivity
- create draft JV: `accounting.create`
- edit draft/submitted/approved JV: `accounting.edit`
- submit JV: `accounting.submit`
- approve JV: `accounting.approve`
- post JV/opening balance: `accounting.post`
- reverse posted JV: `accounting.reverse`
- view reports: `reports.view` plus `view_financials` for financial totals
- export/print: `accounting.export`, `accounting.print`, `reports.export`, `reports.print`
- close period: `close_period`
- reopen period: `reopen_period`
- configure account groups/accounts/fiscal periods/exchange rates: use `settings.configure` only if treating as settings, or `accounting.create/edit` if treating as accounting master data; choose one consistently and document it.

Do not interpret `scope_json` as Company/Branch scope.

## 20. Required Tests

Add focused tests. Keep names clear.

### Schema / Integration Tests

Add or extend integration tests for:

- accounting tables exist
- no new `company_id`
- no new `branch_id`
- account code global uniqueness
- fiscal year remains no `company_id`
- financial period FK remains valid
- journal line debit/credit constraints
- ledger uniqueness per journal line
- status check constraints

### Unit / Invariant Tests

Add tests for:

- balanced manual JV accepted
- unbalanced manual JV rejected
- line with both debit/credit rejected
- line with neither debit/credit rejected
- manual JV to control account rejected
- posted journal immutable in service
- reversal mirrors debit/credit
- Trial Balance totals balance from ledger rows
- FX rate parsing to integer `rate_e6`
- no float math in services

### Feature Tests

Add tests for:

- unauthorized user cannot access accounting pages/actions
- authorized accountant can create/edit/submit/approve/post manual JV
- posting creates ledger entries
- posting writes audit log
- posting to closed period is rejected
- reversing posted JV creates linked reversal and audit
- opening balance posting creates journal and ledger
- General Journal page renders
- General Ledger page renders
- Trial Balance page renders
- permission-denied state appears where appropriate

### PostgreSQL Concurrency Tests

Real PostgreSQL tests are required for these cases:

- two concurrent posts of the same journal
- posting vs period close
- concurrent reversal of the same journal
- stale draft update vs posting
- duplicate idempotency key
- concurrent sequence allocation for JV numbers

Because `phpunit.xml` currently defaults to SQLite in-memory, do not pretend SQLite tests satisfy this requirement. Add a PostgreSQL-backed stress command or a clearly PostgreSQL-only concurrency test path. Recommended:

- Add `php artisan accounting:concurrency-stress --workers=50`
- Require it to fail fast unless `DB::connection()->getDriverName() === 'pgsql'`
- Cover all accounting concurrency cases above
- Clean up stress data in `finally`

Keep the existing `php artisan concurrency:stress --workers=100` command and tests working.

## 21. Documentation Updates Required

After implementation, update:

- `README.md`
- `IMPLEMENTATION_STATUS.md`
- `CHANGELOG.md`
- `DOMAIN_MODEL_REVIEW.md` only if new confirmed relationships are added
- `DOMAIN_RELATIONSHIP_AUDIT.md` only if new confirmed relationships are added
- `SCHEMA_ASSUMPTION_AUDIT.md`
- `PROJECT_LOGIC_AUDIT.md` if audit status changes
- `docs/CONCURRENCY_AUDIT.md`
- `spec/DATABASE_DESIGN.md`
- `spec/SECURITY.md`
- `spec/BUSINESS_RULES.md`

Docs must state:

- Phase 2 Accounting Core implemented/not implemented details.
- No tenant/company/branch scope introduced.
- FiscalYear remains single-ERP global.
- Ledger and Trial Balance derive from posted data only.
- Full financial statements remain out of scope unless implemented later.

## 22. Stop Conditions

Stop and ask the owner before proceeding if any of these block a safe implementation:

- final JV numbering reset policy is required and not defined
- account-code uniqueness becomes materially ambiguous
- current monthly `financial_period` model cannot support required fiscal calendar dates
- FX rounding rules are insufficient for required conversion behavior
- opening-balance semantics require party, inventory, fixed asset, payroll, or retained-earnings workflows
- owner asks for Branch/Department accounting dimensions
- owner asks for Project/CostCenter dimensions before their models are defined
- owner asks for Company-owned fiscal calendars or multi-company support
- owner asks to post Sales/Purchasing/Inventory/Cash/Bank/Payroll/Rentals in Phase 2
- a migration would destroy existing real data without an explicit migration plan
- PostgreSQL concurrency tests reveal non-deterministic duplicate posting or close/post races

Do NOT stop for already resolved matters:

- FiscalYear ownership/context: resolved as `SINGLE-ERP CONTEXT`
- Company/Branch tenancy: forbidden
- Spatie Teams: forbidden
- currentCompany/currentBranch: forbidden

## 23. Definition Of Done

Phase 2 Accounting Core is done only when:

- account groups can be created/edited with server-side authorization and audit
- accounts can be created/edited with server-side authorization and audit
- fiscal years remain global and financial periods are manageable
- periods can close/reopen with race-safe locking and audit
- exchange rates can be managed using integer-scaled rates
- manual Journal Voucher drafts can be created/edited/submitted/approved
- posting a balanced approved manual JV creates immutable posted journal data and ledger entries
- posting an unbalanced or invalid journal is rejected
- posting to a closed period is rejected
- posting is idempotent
- duplicate concurrent posting is impossible
- reversal creates a linked reversing journal and preserves original data
- duplicate concurrent reversal is impossible
- opening balances can post through the same journal/ledger path
- General Journal, General Ledger, and Trial Balance pages read real DB data
- Trial Balance reads posted ledger entries only
- permissions are enforced server-side
- audit records exist for all critical changes
- no `company_id`/`branch_id`/tenant scope was added
- real PostgreSQL accounting concurrency tests pass
- the full Laravel test suite passes
- frontend typecheck and build pass
- documentation is updated

## 24. Final Verification Commands

Run from `laravel/` unless noted otherwise.

Minimum required commands:

```powershell
php artisan migrate --force
php artisan migrate:status
vendor/bin/pint --test
php artisan test
php artisan test --testsuite=Concurrency
php artisan concurrency:stress --workers=100
php artisan tokens:gc --batch=100
npm run typecheck
npm run build
```

Additional Phase 2 required command if implemented as recommended:

```powershell
php artisan accounting:concurrency-stress --workers=50
```

If `accounting:concurrency-stress` is not added, provide the exact alternative command that proves the required real PostgreSQL accounting concurrency cases.

Also run targeted commands during development:

```powershell
php artisan test tests/Invariants
php artisan test tests/Integration
php artisan test tests/Feature
```

## 25. Final Report Required From Gemini

After implementation, report:

1. Files changed.
2. Migrations added/applied.
3. Schema diff summary.
4. Confirmation that no tenant/company/branch scope was introduced.
5. Remaining `company_id`/`branch_id` occurrences and why each is non-runtime cleanup/test/doc text.
6. Accounting features implemented.
7. Accounting features explicitly not implemented.
8. Stop conditions encountered, if any.
9. Test results.
10. PostgreSQL accounting concurrency stress results.
11. Frontend typecheck/build results.
12. Any residual risk.

## 26. Strict Non-Goals Reminder

Do not start:

- Sales
- Purchasing
- Inventory
- AR/AP
- Cash
- Banks
- Cheques
- Payroll
- Rentals
- Fixed Assets
- full Financial Statements
- Dashboard KPI accounting rollout
- tenant/company/branch architecture

Phase 2 is the ledger spine only.
