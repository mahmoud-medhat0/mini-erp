# Mini ERP — Full System & Accounting Verification Plan
# خطة التحقق الشامل من النظام ومن صحة العمليات المحاسبية

**Prepared:** 2026-09-08
**Trigger:** owner request to verify the whole system end-to-end, with emphasis on accounting correctness, after a burst of new feature work and a same-day responsive/UI-bug fix pass.

---

## 0. Why this plan is shaped the way it is

This is **not** a from-scratch test plan. The repository already carries a large, mature verification stack:

| Layer | What exists | Where |
|---|---|---|
| Automated backend | **1,230 test methods** across 111 Feature files, 3 Unit, 3 Integration, 3 Invariants, 1 Concurrency | `laravel/tests/` |
| Automated browser | 7 Playwright specs (auth, core-page smoke, DataTable pagination, alignment, translation guard) | `laravel/tests/E2E/` |
| Manual acceptance script | 15-step Procure-to-Pay + Order-to-Cash + Period-Close + RBAC walkthrough, bilingual, with named personas | `OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md` |
| Manual smoke matrix | 20-section bilingual sign-off matrix covering every module | `PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md` |
| Defect tracking | Running log with severity classification | `PRODUCT_ACCEPTANCE_DEFECT_LOG.md` |
| Prior audit | Bug history from the last full pass (2026-08-29/30) | `QA_PRE_PRODUCTION_AUDIT_REPORT.md` |

Re-deriving any of this would be waste. **This plan's job is to sequence what already exists, close the two concrete gaps below, and add the handful of things that are genuinely missing.**

### The two reasons a fresh pass is actually needed right now

1. **54 commits landed since the last full QA pass** (2026-08-29 → today), including entire modules the smoke matrix and acceptance script were written *before*: Bank Reconciliation reporting, Cheque Register report, Fixed Asset Disposal + Category management, Account/Statement Mappings, a rebuilt Chart of Accounts / General Journal / General Ledger, and the brand-new **Accounting Hub** index page and **AI assistant chat widget** — none of which appear anywhere in the existing matrix or script.
2. **This session's own edits**: 29 frontend files changed today — 24 mobile-responsive layout fixes (Customers, Suppliers, Cash/Bank Accounts, Incoming/Outgoing Cheques, Bank Reconciliations, Customer/Supplier Opening Balances, General Journal, Journal Detail, Financial Statement Mappings, Payroll Runs, Fixed Assets, Sales Credit Notes/Invoice Revision, Taxes, Settings Numbering, Login RTL) plus 5 files where a duplicate/conflicting pagination control was removed (Customers, Suppliers, Customer/Supplier Opening Balances, Notifications). These need a regression pass, not a re-audit of everything else.

So instead of "re-run all 20 matrix sections blind," this plan targets exactly what changed and proves the accounting kernel is still sound underneath it.

---

## 1. Phase 0 — Automated baseline (run this first; ~15–30 min; no human judgment required)

A red baseline makes every later manual sign-off meaningless, so this always runs first.

```bash
cd laravel

# 1. Full backend suite — runs against an isolated in-memory SQLite DB
#    (phpunit.xml pins DB_CONNECTION=sqlite, DB_DATABASE=:memory:), so this
#    is zero-risk to the real Postgres dev/prod database.
composer test           # = artisan config:clear + artisan test

# 2. Type safety
npm run typecheck

# 3. Production bundle actually builds (catches import/build breaks
#    typecheck alone won't, e.g. Vite asset resolution)
npm run build

# 4. Browser smoke suite (needs a live app + throwaway credentials)
php artisan e2e:prepare-user --password="<12+ char throwaway>"   # local/staging only, refuses to run in production
export E2E_EMAIL=e2e-admin@mini-erp.test E2E_PASSWORD="<same value>"
npm run e2e
```

**Exit criteria:** all four green. Anything red gets fixed before moving to Phase 1–4 below.

**Status — executed 2026-09-08:**
- `npm run typecheck` — ✅ clean, no errors (re-confirmed twice after today's 29-file edit pass).
- `npm run build` — ✅ production Vite build succeeds (`✓ built in 2.12s`, all chunks emitted).
- `composer test` — **1,230 tests, 1,190 passed, 37 failed, 3 skipped, 36,780 assertions, 25.7 min** (single-threaded — install `brianium/paratest` and use `php artisan test --parallel` to cut this to a few minutes on future runs).
- `npm run e2e` — not yet run this pass (needs a live app server + throwaway credentials).

**The 37 failures, categorized — none of them trace to today's 29-file responsive/pagination edit** (verified directly: the two failing assertions that touch files edited today, `IncomingCheques/Index.tsx` and `Sales/CustomerCreditNotes.tsx`, both check for an unrelated missing string — a `PaginationLink` type import and a `dict.app.pages.salesCustomerCreditNotes.settle` dictionary key — neither of which today's className/prop-only edits touched):

| Group | Count | What it means |
|---|---|---|
| **AI chat route not permission-gated** | 1 | `mini-erp-ai.chat` route only has `web\|auth\|throttle:30,1` middleware — the hardening test expects every state-changing route to carry `can:` / `permission.` or be on an explicit allowlist. Worth a look — it's the one item here with a security flavor. |
| **DataTable request-validation test drift** | 6 | `CashBankBookDataTableTest`, `ChequeRegisterDataTableTest`, `PartnerStatementDataTableTest`, `RentalOperationsDataTableTest`, `VatRegisterDataTableTest`, part of `Phase4Slice9OperationalReportsTest` — all fail the same way: they probe with an over-long dummy value expecting a specific validation error (e.g. on `date_to`/`direction`/`type`), but a shared length cap (`length` ≤ 100, `search.value` ≤ 150) now fires first. Looks like the shared DataTable request validator was tightened after these tests were written; the tests themselves need updating to match, not the validator relaxing. |
| **Phase15 "product hardening" static checks** | 17 | Accessible-name/ARIA checks, typed-pagination-link checks, "no loose `any`" checks, controller-thinness checks, one route-permission-matching check — all pattern/string assertions against page or controller source. Pre-existing gaps, not something introduced today. |
| **Phase18 acceptance checks** | 5 | Pagination-link typing + Inertia pagination shape for Projects/Cost Centers, a controller ≤150-line-limit check, one "known service-authorized controllers" check, one "no multi-tenant terms" grep. Pre-existing; none of today's edited files. |
| **Report content checks** | 2 | `Phase3Slice8ReportsTest` — customer/supplier statement report assertions. |
| **Misc** | 6 | `AccountingDemoSeederTest` (empty-state props shape), `TourGuideCoverageTest` (one Inertia page missing tour-guide coverage), 2 more `Phase4Slice9OperationalReportsTest` assertions. |

None of these are ledger-math or double-entry defects — they're either stale test expectations or code-quality/accessibility gaps. Worth logging in `PRODUCT_ACCEPTANCE_DEFECT_LOG.md` and clearing before the next release, but they don't block the accounting-correctness work below.

---

## 2. Phase 1 — Financial-correctness proofs (direct ledger evidence; ~30–45 min)

This is the literal "كل الحاجات المحاسبية شغالة صح" check. Rather than trusting the UI to reflect the ledger correctly, prove it directly against the database with read-only queries. All of these can run against the real dev DB safely — none of them write anything.

Run each via `php artisan tinker` (or wrap as a one-off `artisan` command if you want this repeatable):

The real schema (confirmed by inspection, not guessed): journal lines live directly on `ledger_entry` (one row per debit/credit line, `journal_entry_id` FK — there is no separate `journal_entry_line` table); the true AR/AP subledgers are `receivable_entry` / `payable_entry` (not `customer_invoice`/`supplier_bill` directly — those are just one of several `source_type`s that feed a `receivable_entry`/`payable_entry` row, alongside opening balances, cheques, credit notes, receipts and payments); control-account codes come from `accounting_account_mapping` (`key` = `ar_control`, `ap_control`, `output_tax_payable`, `input_tax_receivable`, `accumulated_depreciation`).

**I already ran the corrected version of this battery against the real dev database** (read-only; nothing written). Full script: eight checks — global ledger balance, per-journal balance, duplicate numbering (journal/invoice/bill), AR control vs. subledger, AP control vs. subledger, VAT GL vs. document totals, fixed-asset accumulated depreciation vs. posted schedule, and closed-period immutability.

**Results:**

| # | Check | Result |
|---|---|---|
| 1 | Global ledger balance (2,373 lines) | ❌ off by 2,500,000 minor units |
| 2 | Every journal individually balanced | ❌ 5 entries unbalanced |
| 3 | No duplicate document numbers (journal/invoice/bill) | ✅ clean |
| 4 | AR control account vs. `receivable_entry` subledger | ❌ mismatch |
| 5 | AP control account vs. `payable_entry` subledger | ❌ mismatch |
| 6 | VAT GL vs. posted document tax totals | — inconclusive (see below) |
| 7 | Accumulated depreciation vs. posted schedule | ❌ mismatch |
| 8 | No posting into an already-closed period | ✅ clean |

**Investigated further before drawing any conclusion — this is not a code defect, it's a dirty dataset:**

All five "unbalanced" journals are named `JV-RECON-*`, described `"Recon Stress Test Deposit"`, dated in a synthetic far-future year (8620–8624) — clearly a deliberately-injected one-sided fixture for exercising the Bank Reconciliation UI against an "item in transit" scenario, not a real transaction. And every AR/AP/depreciation "mismatch" traces to the same cause: this dev database is full of `*_STRESS_*` fixtures (`Allocation Stress Customer`, `Cheque Stress Customer`, `Settlement Stress Customer`, etc.) that were deliberately built with **their own isolated, per-fixture GL accounts** — e.g. `1100-AR-ALLOC-AWM12MEM "AR Allocation Stress Control"` — instead of posting through the real shared `1200`/`2100` control accounts. That's actually a *thoughtful* test-isolation design (stress fixtures can't corrupt the real trial balance), but it also means the real `ar_control`/`ap_control`/`accumulated_depreciation` accounts currently carry almost no activity in this environment, so a naive global reconciliation against them is not a meaningful signal here. `customer_invoice` and `supplier_bill` are both completely empty in this DB (0 rows) — check 6 is inconclusive for the same reason: there is nothing posted yet to reconcile against.

**Conclusion:** the check methodology is sound and checks 3 and 8 (the two that don't depend on dataset cleanliness) both passed cleanly. Checks 1, 2, 4, 5, 7 needed a **freshly seeded** database to produce a trustworthy answer.

**Re-run on a clean database — done.** With your go-ahead, the dev database was reset (`php artisan migrate:fresh --seed --force`) and all 8 checks re-run:

```
1. Global ledger balance ......................... PASS  (2 lines, 100,000 = 100,000)
2. Every journal individually balanced ........... PASS
3. No duplicate document numbers ................. PASS
4. AR control account vs. subledger .............. PASS  (both zero — no invoices posted yet)
5. AP control account vs. subledger .............. PASS  (both zero — no bills posted yet)
6. VAT GL vs. document tax totals ................. — inconclusive (both zero, nothing posted yet)
7. Accumulated depreciation vs. posted schedule ... PASS  (both zero — no assets posted yet)
8. No posting into an already-closed period ...... PASS
```

Everything holds on a clean baseline, as expected — the foundational seeders (currencies, RBAC, chart of accounts, tax codes, account categories/types, financial statement lines, one demo journal) don't touch AR, AP, VAT, or depreciation at all, so checks 4–7 are trivially true (both sides are zero) rather than proven under load. **The real proof only lands once Phase 2 below actually posts real invoices, bills, receipts, payments, and a depreciation run** — at which point re-running this exact script (saved at the path referenced above) turns those trivial zeros into a genuine tie-out. Re-run it again right after Phase 2's walkthrough.

⚠️ **Side effect you should know about:** resetting the database means the previous dev data (which was almost entirely synthetic `*_STRESS_*` fixtures from earlier feature-development sessions, per the investigation above) is gone. If any of that stress data was still needed for something else, it'll need to be regenerated from whatever command created it originally — nothing of business value was in there, but flagging it since it's a destructive action.

**Exit criteria (on a clean seed):** every check returns "balanced" / empty-set. Anything else is a P0 defect — log it in `PRODUCT_ACCEPTANCE_DEFECT_LOG.md` using its existing format before doing anything else.

---

## 3. Phase 2 — Owner/Accountant acceptance walkthrough (re-run as-is; ~2–3 hrs; needs a human)

Follow `OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md` steps 1–15 verbatim, with freshly seeded data:

```bash
php artisan migrate:fresh --seed   # or the project's documented seeding command — confirm which before running
```

This single script already covers, in order: dashboard truth, GL mapping review, fiscal period setup, full **Procure-to-Pay** (PO → GRN/WAC costing → supplier bill + 14% input VAT → payment/AP settlement), full **Order-to-Cash** (SO → delivery → tax invoice + 14% output VAT → return/credit note → receipt/AR settlement), **Trial Balance & period-close readiness**, **subledger-to-GL reconciliation**, **financial statements**, and **RBAC boundaries** — using the three defined personas. This is the accounting-correctness backbone; nothing in this plan replaces it, it only makes sure it's run against current code.

Capture evidence exactly as the script's own "Evidence to Capture for Sign-Off" section specifies, and use its Section 8 sign-off form — don't create a new one.

### Executed — Steps 4 through 13, driven through the real application code

Rather than a browser walkthrough, Steps 4–13 were executed by calling the same Application-layer services the controllers call (`PurchaseOrderService`, `GoodsReceiptService`, `SupplierBillService`, `SupplierPaymentService`, `PayableAllocationService`, `SalesOrderService`, `DeliveryNoteService`, `CustomerInvoiceService`, `SalesReturnService`, `CustomerCreditNoteService`, `ReceivableEntrySettlementService`, `CustomerReceiptService`, `ReceivableAllocationService`) with the exact entities, quantities, and prices the script specifies (`ACC-SUPP-001`, `ACC-CUST-001`, `ACC-PRD-STOCK-01`, `ACC-WH-MAIN`, `ACC-BANK-01`, `VAT_STD_14`) — after provisioning them with `php artisan db:seed --class=AccountantAcceptanceSeeder`, which the script itself calls for. This exercises the real business logic, validation, and ledger posting — the same code path an HTTP request from the UI would hit — without the fragility of scripting form selectors blind.

**Every single number and every single journal entry matched the script's specification exactly, to the minor unit:**

| Step | Action | Result |
|---|---|---|
| 4 | PO: 100 units @ 100.00 EGP | Subtotal/Total = 10,000.00 EGP ✅, status → `confirmed` ✅ |
| 5 | GRN: 100 units into `ACC-WH-MAIN` | Stock 0→100 ✅; `Dr 1400 10,000.00 / Cr 2300 10,000.00` ✅ exact |
| 6 | Supplier Bill, 14% input VAT | 10,000 + 1,400 = 11,400 ✅; `Dr 2300 10,000 / Dr 1300 1,400 / Cr 2100 11,400` ✅ exact |
| 7 | Supplier Payment, full settlement | `Dr 2100 11,400 / Cr 1110 11,400` ✅; open AP on the bill → 0 ✅ |
| 8 | SO 40 units @ 150.00 + Delivery | Stock 100→60 ✅; `Dr 5500 4,000.00 / Cr 1400 4,000.00` ✅ exact |
| 9 | Customer Invoice, 14% output VAT | 6,000 + 840 = 6,840 ✅; `Dr 1200 6,840 / Cr 4100 6,000 / Cr 2200 840` ✅ exact |
| 10a | Sales Return, 10 units | Stock 60→70 ✅ |
| 10b | Credit Note, 10 units @ 150 + 14% VAT, settled against the invoice | 1,500 + 210 = 1,710 ✅; `Dr 4200 1,500 / Dr 2200 210 / Cr 1200 1,710` ✅ exact; remaining invoice balance → 5,130.00 EGP ✅ exact |
| 11 | Customer Receipt, full settlement of the remainder | `Dr 1110 5,130 / Cr 1200 5,130` ✅; final open AR on the invoice → 0 ✅ |
| 12/13 | Re-ran the Phase 1 reconciliation battery against this real posted data | All 8 checks pass, including the two that were only trivially true before (checks 4–5, AR/AP control vs. subledger) — see below |

**Re-run of Phase 1 on real data — the actual proof, not the earlier trivial-zeros pass:**

```
1. Global ledger balance ......................... PASS  (21 lines, 5,248,000 = 5,248,000)
2. Every journal individually balanced ........... PASS
3. No duplicate document numbers ................. PASS
4. AR control account vs. subledger .............. PASS  (both 0 — invoice 6,840 debit, less credit-note settlement 1,710 credit, less receipt 5,130 credit)
5. AP control account vs. subledger .............. PASS  (both 0 — bill 11,400 credit, less payment allocation 11,400)
6. VAT GL vs. document tax totals ................. consistent (output: 840 invoice − 210 credit note = 630 net, matching GL; input: 1,400 matching GL exactly)
7. Accumulated depreciation vs. posted schedule ... PASS (0 = 0; no fixed-asset activity in this cycle — out of this script's scope)
8. No posting into an already-closed period ...... PASS
```

**One correction made along the way, worth recording:** the first version of checks 4–5 tried to reconstruct the AR/AP subledger balance as `invoices − allocations`. That formula silently ignored `ReceivableEntrySettlementService`/credit-note settlements, which post their own `receivable_entry` rows rather than mutating the invoice's row — so it flagged a false mismatch (171,000) the first time it ran against real data. The fix: sum `receivable_entry`/`payable_entry` directly (`SUM(debit − credit)`) rather than reconstructing from any one source document type. That formula is structurally correct by construction — every entry in those tables is created 1:1 with a GL posting — and is what the corrected script (saved at the scratch path referenced above) now does.

**Bottom line: the double-entry kernel, VAT calculation, WAC-based COGS, AR/AP subledger integrity, and credit-note settlement mechanics all held exactly through a full real Procure-to-Pay + Order-to-Cash cycle with no discrepancy of even one minor unit.** Steps 14 (financial statements) and 15 (RBAC persona boundaries) were not separately re-driven: financial statements are additive views over the same ledger already proven balanced above, and RBAC/persona enforcement is already covered by the passing `RbacCrudEnforcementTest` and `SecurityHardeningTest` suites confirmed green in Phase 0.

---

## 4. Phase 3 — Delta-focused smoke matrix pass (targeted, not all 20 sections; ~1–2 hrs)

Map of the 54 recent commits onto `PRODUCT_ACCEPTANCE_SMOKE_MATRIX.md` sections. **Re-run these sections**, because real feature work landed in each *after* the matrix's last sign-off:

| Matrix section | Why it needs a fresh pass |
|---|---|
| §4 Chart of Accounts, Categories, Types, Currencies, FX | COA rebuilt with server-side DataTable + account mappings module added |
| §6 General Ledger, Trial Balance, Financial Statements | General Journal + General Ledger pages rebuilt; new Journal Detail view |
| §7/§8 AR/AP, Receipts/Payments/Cheques/Bank Reconciliation | Bank Reconciliation *reporting* UI and Cheque Register report are entirely new |
| §13 Fixed Assets | Disposal module + Category management added since sign-off |
| *(not in the matrix at all yet)* **Accounting Hub** index page (`/accounting`) | Shipped this week — first-time coverage |
| *(not in the matrix at all yet)* **AI assistant widget** | Shipped this week — first-time coverage; confirm it degrades gracefully with no API key/offline and doesn't block any page's responsive layout |

**Sections safe to skip this round** (no commits touched them since the 2026-08-29 sign-off): §1 Auth/RBAC governance, §2 Dashboard baseline, §3 Company/Branch/Numbering settings, §9 Products/UoM/Warehouses/Stock, §10 Sales cycle, §11 Purchasing cycle, §12 VAT core, §14 Expenses, §15 Payroll, §16 Rentals, §17 Projects/Cost Centers/Budgets, §18 Attachments/Notifications/Audit, §19 Branch/Warehouse ops, §20 Security controls. (§18 and §20 touch two files edited today — Notifications and the RBAC-gated list pages — so a quick spot-check there is still worth the 10 minutes even though no *business logic* changed.)

---

## 5. Phase 4 — Responsive/UI regression for today's changes (~30 min, mostly automatable)

This formalizes the ad-hoc method used earlier today (Playwright + real login + full-page screenshots at 375px/1440px) into something repeatable instead of a one-off script:

1. Add a second Playwright project to `laravel/playwright.config.ts` using a phone viewport (e.g. `devices['iPhone 13']`), reusing the existing page list in `smoke.spec.ts` — this turns "does every core page 200 and mount without a React error" into a check that also runs at mobile width, permanently, via `npm run e2e`.
2. Visually re-check (screenshot) the exact 25 pages whose layout changed today, at 375px, focused on: the create/edit modals that moved from a bare 2-column grid to `grid-cols-1 sm:grid-cols-2` (Cash Accounts, Bank Accounts, Customers, Suppliers, Incoming/Outgoing Cheques, Bank Reconciliations, Customer/Supplier Opening Balances, Supplier Payments, Taxes Rates/Periods, Financial Statement Mappings), and the `InvoiceRevisionShow` totals table that gained a horizontal-scroll wrapper.
3. Add a permanent assertion (not just today's throwaway check) that exactly one pagination control renders on: `/customers`, `/suppliers`, `/supplier-opening-balances`, `/customer-opening-balances`, `/notifications` — i.e. `page.locator('[data-universal-pagination]')` count is `0` on all five (ServerDataTable's own pager is the only one that should show).

I can execute all three steps myself; none of them need business judgment.

---

## 6. Phase 5 — Security/RBAC spot-check (~15 min)

Already covered by `SecurityHardeningTest`, `RbacCrudEnforcementTest`, and matrix §20 — confirm both stay green in Phase 0, then re-walk §20's steps once, since two of today's edited pages (Customers, Notifications) sit behind permission gates.

---

## 7. Effort summary & who does what

| Phase | Est. time | Needs business judgment? | I can run it now |
|---|---|---|---|
| 0 — Automated baseline | 15–30 min | No | ✅ |
| 1 — Ledger-level correctness proofs | 30–45 min | No (pure arithmetic) | ✅ (once AR/AP/VAT account codes are confirmed) |
| 2 — Owner/accountant walkthrough | 2–3 hrs | **Yes** — this is the part only a human accountant can actually sign off | ❌ |
| 3 — Delta-focused smoke matrix | 1–2 hrs | Partly | Can drive the mechanics; business-correctness calls still need you |
| 4 — Responsive/UI regression | 30 min | No | ✅ |
| 5 — Security/RBAC spot-check | 15 min | Minimal | ✅ mostly |

**Total:** roughly 5–7 hours end-to-end, split between what I can execute unattended (Phases 0, 1, 4, most of 5 — call it 1.5–2 hrs) and what genuinely needs an accountant's eyes on real numbers (Phase 2, and the judgment calls inside Phase 3 — call it 3–5 hrs).

---

## 8. Sign-off & defect logging

- Use the existing **Owner & Accountant Sign-Off Form** (`OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md`, Section 8) — do not create a parallel one.
- Log anything found using the existing severity classification in `OWNER_ACCEPTANCE_EXECUTION_SCRIPT.md` §6 and record it in `PRODUCT_ACCEPTANCE_DEFECT_LOG.md`, matching that file's existing entry format.

---

## 9. Suggested next step

I can start immediately on the parts that don't need you: run Phase 0 in full, execute Phase 1's checks against the real account-mapping codes once you confirm them (or I read them from Settings → Account Mappings myself), and complete Phase 4. That gets the automatable ~2 hours done and hands you a short, concrete list of anything red — before you or an accountant spend the 3–5 hours on Phase 2's manual walkthrough.

---

## 10. Extended module verification — executed 2026-09-08 (continued session)

Following up on "what's still missing," every remaining gap from Section 9 was actually executed, the same way as Phase 2: by calling the real Application-layer services with real data and checking the results, not by inspection alone. **This pass found and fixed 3 real defects** — none of them cosmetic, one of them (§10.5) affecting the majority of the Sales and Rentals list pages in the app.

### 10.1 Fixed Assets — full lifecycle (create → capitalize → depreciate → dispose)

Executed via `FixedAssetRegisterService`, `FixedAssetCapitalizationService`, `FixedAssetDepreciationEngineService`, `FixedAssetDepreciationPostingService`, `FixedAssetDisposalPostingService`:

- Capitalization: `Dr 1600 (Fixed Asset Cost) 3,600,000 / Cr 1699 (Fixed Asset Clearing) 3,600,000` ✅ balanced
- Straight-line schedule generation (36 months, 1,000.00 EGP/month) ✅ correct
- Depreciation run posting for a period: ✅ posted, ledger matches the schedule exactly
- Disposal (sale, proceeds 3,000,000 against a 3,500,000 net book value): preview correctly computed a 500,000 loss; posted journal `Dr 1690 (Accum. Depr.) 100,000 / Dr 1699 (Clearing/Proceeds) 3,000,000 / Dr 5910 (Loss on Disposal) 500,000 / Cr 1600 (Cost) 3,600,000` — balanced exactly.

### 10.2 Payroll — full lifecycle

Used the pre-seeded `ACC-EMP-001` (base salary 1,500,000), assigned a transport allowance (+20,000) and a 10% deduction, ran `PayrollRunService`:

- Gross 1,520,000, deductions 150,000, net 1,370,000 — all correct.
- Posted journal: `Dr 5700 (Payroll Expense) 1,520,000 / Cr 2600 (Payroll Payable) 1,370,000 / Cr 2610 (Deductions Payable) 150,000` — balanced exactly.

### 10.3 Rentals — full lifecycle

Item → Contract (submit/approve) → Handover (confirm — correctly auto-activates the contract and flips the item to `rented`) → mixed Invoice (rent + deposit, 14% VAT) via `RentalContractService`/`RentalFulfillmentService`/`RentalInvoiceService`:

- Invoice: 50,000 rent + 10,000 deposit = 60,000 subtotal, 7,000 tax (14% of the rent line only, deposit correctly untaxed), 67,000 total.
- Posted journal: `Dr 1200 (AR) 67,000 / Cr 4300 (Rental Revenue) 50,000 / Cr 2620 (Deposit Liability) 10,000 / Cr 2200 (Output Tax) 7,000` — balanced exactly.

### 10.4 Period Close — readiness, closing, reopening, immutability — 2 real bugs found and fixed

Calling `PeriodService::checkCloseReadiness()` for real (Step 12 of the acceptance script, and the backing check for `/accounting/periods`) crashed immediately with a Postgres error: **`column "credit_note_date" does not exist`**. This is not an edge case — the query is unconditional, so it fails on *every* call, for *every* period, regardless of data. Investigating further (by checking every table/column the same function touches against the real schema) turned up a second, identical mistake:

| # | File | Bug | Fix |
|---|---|---|---|
| 1 | `app/Application/Accounting/PeriodService.php` | Queried `customer_credit_note.credit_note_date` — the real column is `credit_date` | Renamed both references |
| 2 | same file | Queried `supplier_adjustment_note.note_date` — the real column is `adjustment_date`; and the GL-level `opening_balance` check queried a nonexistent `financial_period_id`/`entry_date` (that table is scoped by `fiscal_year_id` only, with no per-row date at all) | Renamed `note_date` → `adjustment_date`; rewrote the opening-balance check to filter by `fiscal_year_id` and report `created_at` instead of a nonexistent date column |

After the fix: `checkCloseReadiness()` runs clean (`can_close: true`, zero blockers), `closePeriod()` closes the period, a new posting attempt into the closed period is correctly rejected (`"No open financial period covers date..."`), `reopenPeriod()` correctly reopens it, and re-closing afterward works. Full cycle confirmed. 36 targeted PHPUnit tests around this area (`Phase5Slice4PeriodCloseTest`, `Phase5Slice6FinalCloseOutTest`, `Phase13PayrollFoundationTest`, `Phase14RentalBillingTest`, `Phase10FixedAssetMovementTest`) still pass after the fix.

### 10.5 Bank Reconciliation — full cycle

Reopened the period (to test `reopenPeriod` too), created a draft reconciliation on `ACC-BANK-01`, added two statement lines matching the payment and receipt journal entries from Phase 2's Order-to-Cash cycle, matched both, and reconciled:

- Statement movement −627,000 = system movement −627,000 = matched movement −627,000, **difference 0** — perfectly reconciled.
- Status flow confirmed: `draft → in_progress → reconciled` (not `finalized` as this plan originally guessed — the real status name is `reconciled`).

### 10.6 Master reconciliation across every module exercised (P2P + O2C + Fixed Assets + Payroll + Rentals)

Re-ran the Phase 1 script one more time after all of the above: **44 ledger lines, 25,435,000 debit = 25,435,000 credit**, AR/AP control accounts tie to their subledgers exactly, accumulated depreciation ties to posted schedules (correctly excluding the disposed asset — see the note in the script), no duplicate numbering, no posting into a closed period. Every check passes on real, non-trivial, cross-module data.

### 10.7 RBAC persona boundaries — real HTTP checks, not inspection

Created one throwaway user per named persona (`SALES`, `PURCHASING`, `INVENTORY`, `AUDITOR`, `ACCOUNTANT` — all roles already existed in `RbacSeeder`) and hit real routes as each, checking actual HTTP status codes:

- SALES: allowed on Customers/Sales Orders/Delivery/Invoices; denied (403) on Payroll, Settings, Purchasing. ✅
- PURCHASING: allowed on Suppliers/POs/GRNs/Bills; denied on Payroll, Settings, Sales. ✅
- INVENTORY: allowed on Warehouses/Stock Balances; denied on Payroll, Settings, Accounting Journal. ✅
- AUDITOR: allowed on Reports/Ledger/Audit Log; denied on Payroll. One initial "mismatch" — AUDITOR can view `/settings` (200, not 403) — turned out to be **intentional**: `RbacSeeder` explicitly grants `AUDITOR` the `settings.view` permission (read-only audit visibility into company configuration is part of the role's designed scope), confirmed by inspecting the role's actual permission list. Not a defect.
- ACCOUNTANT: broad access confirmed (GL, Journal, Ledger, Trial Balance, AR/AP, Fixed Assets); denied on `/settings/users` specifically. ✅

### 10.8 Playwright E2E suite (`npm run e2e`) — 43 passed, 4 failed → all 4 explained, 1 was a real bug (now fixed)

| Failing spec | Root cause | Verdict |
|---|---|---|
| `datatable-alignment.spec.ts` (`/customer-opening-balances`) | Grid was empty on the freshly-seeded DB; DataTables' own "No data available" placeholder row (1 `<td colspan>`) doesn't satisfy the test's own empty-grid skip guard, which checks `tbody tr` count (the placeholder row counts as 1 row even though it holds no real data) | Test fragility on empty datasets, not an app defect |
| `account-mappings.spec.ts` (key label) | Same empty-grid-vs-skip-guard pattern | Test fragility, not an app defect |
| `statement-mappings.spec.ts` (collapsed panel) | Toggle button only renders when there are unmapped accounts to show; the fresh seed's chart of accounts happens to be fully mapped already | Test fragility (environment-dependent), not an app defect |
| `object-object-crawl.spec.ts` (`/rentals/items` renders `[object Object]`) | **Real bug** — see §10.9 below | **Fixed** |

### 10.9 The big one: `datatables.net-react` slot-targeting mismatch across 11 pages

Chasing the `[object Object]` failure down: `RentableItems.tsx`'s "Item" column displayed the literal string `[object Object]` instead of the item's translated name. Root cause, confirmed by reading `datatables.net-react`'s own source: a non-numeric `slots` key is turned into a DataTables column target of `` `${key}:name` `` — meaning **the slot key must match the column's `name` field, not its `data` field.** `RentableItems.tsx` declared its columns with a dot-qualified `name` (e.g. `rentable_item.name`, for server-side sort/filter against the joined table) but keyed its slots with the bare `data` value (`name`). The two never matched, so the custom React renderer silently never attached, and DataTables fell back to stringifying the raw cell object.

A quick static scan (regex-matching every `{ data: X, name: Y }` column pair against every slots key across the codebase) found the exact same pattern in **10 more files** — meaning `number` (document links), `total_minor`/`*_total_minor` (currency amounts), `status` (colored badges), and various date/type columns were silently rendering as raw, unformatted values on:

`Rentals/RentableItems.tsx`, `Rentals/Contracts.tsx`, `Rentals/Handovers.tsx`, `Rentals/Returns.tsx`, `Rentals/Invoices.tsx`, `Sales/SalesOrders.tsx`, `Sales/DeliveryNotes.tsx`, `Sales/CustomerInvoices.tsx`, `Sales/SalesReturns.tsx`, `Sales/CustomerCreditNotes.tsx`, `Sales/InvoiceRevisions.tsx`

All 11 fixed by renaming each affected slot key to the exact qualified `name` the column declares (verified with a second automated pass — zero mismatches remain). Confirmed live, before/after:

- Before: Sales Orders "Total Amount" column showed raw `600000`; Rentable Items "Item" column showed `[object Object]`.
- After: Sales Orders shows `6,000.00 EGP` in the accounting-amount style, with a green "Confirmed" status badge; Rentable Items shows "Acceptance Rental Generator" with its Active/Inactive sub-label.

`npm run typecheck` and `npm run build` both clean after the fix.

### 10.10 What's now verified vs. what still needs a human

With §10 complete, the only items from the original "what's missing" list that remain are the ones that were always going to need a human: Phase 2's business-judgment sign-off on real numbers by an actual accountant, and a visual walkthrough of the newly-shipped UI sections (Bank Reconciliation reporting, Cheque Register report, the Accounting Hub page) that Phase 3 called out — everything else that could be exercised programmatically now has been, and every defect found along the way has been fixed and re-verified.

---

## 11. Plan for the remaining modules

Everything below follows the exact method §10 already proved out: call the real `Application` service classes with real data (the same code path the controllers hit), assert the resulting status/totals, and dump the posted journal to confirm it balances and hits the accounts a professional would expect. Each subsection below already names the actual service classes and their real method signatures (confirmed against the source, not guessed), so execution is a matter of working out the exact field shapes per service the same way §10 did — not re-discovering the architecture.

| # | Module | Services (confirmed) | Scenario to run | Expected accounting shape |
|---|---|---|---|---|
| 11.1 | **Inventory operations** | `StockCountService` (create→submit→approve→post), `StockAdjustmentService` (create, or `createApprovedFromStockCount` to chain off a count), `StockTransferService` (create→submit→approve→issue→receive) | Physical count on `ACC-WH-MAIN` finds a variance on the stock the P2P cycle already put there (60 units); post an adjustment for the variance; transfer some units to `ACC-WH-ALX` | Adjustment posts `Dr/Cr` inventory-adjustment-gain/loss account (already confirmed mapped) against Inventory Asset (1400); transfer should be inventory-neutral overall (debits the destination warehouse's inventory, credits the source) with **no P&L impact** — worth asserting explicitly since a transfer accidentally hitting a gain/loss account would be a real bug |
| 11.2 | **Purchase Returns & Landed Costs** | `PurchaseReturnService` (create→submit→approve→post), `LandedCostAllocationService` (create→submit→approve→post) | Return a few units from the GRN already posted in §Phase 2; separately, allocate a landed-cost (freight/customs) amount across the GRN's lines and confirm it raises those lines' unit cost | Return: mirrors the Sales Return pattern already proved (`Dr GRNI or AP / Cr Inventory`, reversing the WAC cost taken in). Landed cost: total allocated must exactly equal the input amount (no rounding leakage across lines) and must raise `stock_balance.avg_unit_cost_e6` for the affected product |
| 11.3 | **Cheques** | `IncomingChequeService` (createDraft→receive→deposit→clear, or →bounceBeforeClear/returnBeforeClear), `OutgoingChequeService` (createDraft→issue→clear, or →returnBeforeClear/cancelBeforeClear) | Receive a cheque from `ACC-CUST-001` against the invoice's open balance, deposit it to `ACC-BANK-01`, clear it; separately issue a cheque to `ACC-SUPP-001`, clear it — then repeat with a **bounce** on one incoming cheque to confirm the reversal posts correctly | Receive: `Dr Cheques Under Collection (1050) / Cr AR (1200)`. Clear: moves it into the real bank balance. Bounce: must reverse *only* the collection leg and restore the AR balance — this is exactly the kind of one-sided-looking entry that produced the synthetic `JV-RECON-*` stress fixtures found in §Phase 1's first pass, so it's worth double-checking for real here |
| 11.4 | **Prepaid & Accrued Expenses** | `PrepaidScheduleService` (create→submit→approve, then `postRecognition` per period), `AccrualScheduleService` (create→submit→approve, then `postEntry` per period) | Create a 3-month prepaid schedule (e.g. insurance), post one month's recognition; create an accrual schedule, post one entry | Prepaid recognition: `Dr Expense / Cr Prepaid Expense Asset (mapped)`, reducing the asset by exactly one period's share. Accrual: `Dr Expense / Cr Accrued Expense Liability` |
| 11.5 | **Projects, Cost Centers & Budgets** | `ProjectService::create`, `CostCenterService::create`, `BudgetService` (create→`replaceLines`→submit→approve→activate) | Create a project + cost center, tag one of §Phase 2's journal lines to them (via the `project_id`/`cost_center_id` columns already confirmed on `ledger_entry`), create and activate a budget line for that cost center, then check `Reports/CostCenterActuals` reflects the actual vs. budget correctly | This is reporting-correctness, not a new posting path — the check is that the dimension tags on existing ledger lines roll up correctly, not a new journal shape |
| 11.6 | **Multi-currency / FX** | `PurchaseOrderService`/`SupplierBillService` etc. all already accept a `currency` + implicit `fx_rate_e6` — none of §10's scenarios used anything but EGP | Repeat a small supplier bill in USD (or whatever a second seeded currency is) and confirm the ledger posts in the account's home currency at the correct converted amount, with `debit_txn_minor`/`credit_txn_minor` preserving the original transaction currency (both columns already observed on `ledger_entry` in §Phase 1) | `debit_minor` (home currency) = `debit_txn_minor` (txn currency) × `fx_rate_e6` / 1,000,000, exactly, in integer minor units — the specific thing to break on with float math, so worth a dedicated check |
| 11.7 | **VAT filing / tax period close** | `TaxPeriodService::createPeriod`, `TaxReturnService::generateDraftReturn` → `fileReturn` | Generate a draft VAT return for the period covering §Phase 2's invoice (14% output) and bill (14% input), confirm the draft totals match the VAT GL balances already proved in §10.6, then file it | The return's output/input/net-payable totals should tie to `output_tax_payable`/`input_tax_receivable` GL movement for that period — a second, independent proof of the VAT reconciliation beyond §Phase 1 check 6 |
| 11.8 | **Visual UI confirmation pass** | — (Playwright, reusing the screenshot method from the responsive-fix pass) | Screenshot, logged in as the seeded admin: Trial Balance, Balance Sheet, Income Statement, AR/AP Aging, Bank Reconciliation reporting, Cheque Register report, Accounting Hub — now that §10.9's rendering bug is fixed | Confirms the *reports* (not just the underlying ledger) display the correct, formatted numbers to a real user — the one class of defect none of the DB-level checks in §Phase 1/§10.6 could have caught on their own |
| 11.9 | **Triage the 37 pre-existing Phase 0 failures** | — | Group by real risk: the AI-widget permission gap (explicitly parked, not touched), the 6 DataTable validation-drift tests (likely just need their probe payloads shortened to fit the newer length caps), the 22 Phase15/18 code-quality checks (accessibility labels, typed helpers, controller thinness — no urgency, but log each individually rather than leaving them as one lump), the 2 report-content failures, and the misc 6 | Not accounting-correctness work — this is a backlog-grooming pass, lowest priority of everything on this list |
| 11.10 | **Administrative** | — | Decide: commit today's fixes (2 real backend bugs in `PeriodService.php`, 11 frontend files for the DataTable slot bug, plus the earlier 24 responsive + 5 pagination files) and the regenerated `public/build` assets; log every defect found today in `PRODUCT_ACCEPTANCE_DEFECT_LOG.md` per its existing format | Purely your call — nothing here needs more testing, just a go-ahead to commit |

**Suggested order:** 11.3 (cheques) and 11.7 (VAT filing) first — both extend the exact P2P/O2C data already sitting in the DB from §Phase 2, so no new master data to provision. Then 11.1/11.2 (inventory + purchase returns/landed costs) since they also reuse the existing GRN/stock. 11.4–11.6 need a bit of new setup each but are self-contained. 11.8 (visual pass) is cheap and worth doing right after 11.7 while the VAT data is fresh. 11.9 and 11.10 are bookkeeping, not testing — do them last, or in parallel, whenever convenient.

**Estimated effort:** 11.1–11.7 at roughly 20–30 minutes each following the established method (~2.5–3 hrs total), 11.8 around 20 minutes, 11.9 is triage/logging only (~30 minutes), 11.10 is a conversation, not work.

---

## 12. Executing §11 — results

### 12.1 §11.3 Cheques — executed, zero defects

Full lifecycle run: incoming cheque (draft→receive→deposit→clear) and a second one taken to a pre-clear **bounce**, plus outgoing cheque (draft→issue→clear). Every journal matched exactly:

- Receive: `Dr 1500 (Cheques Under Collection) 30,000 / Cr 1200 (AR) 30,000`
- Deposit: correctly posts **no** journal (pure status change)
- Clear: `Dr 1110 (Bank) 30,000 / Cr 1500 30,000`
- Bounce (pre-clear): `Dr 1200 (AR restored) 15,000 / Cr 1500 (reversed) 15,000` — a clean, two-sided reversal, not a one-sided orphan entry like the synthetic `JV-RECON-*` fixtures §Phase 1 flagged early on. That's now confirmed for real rather than inferred.
- Outgoing issue: `Dr 2100 (AP) 20,000 / Cr 2400 (Cheques Payable) 20,000`; clear: `Dr 2400 20,000 / Cr 1110 (Bank) 20,000`

All balanced. **No defects.**

### 12.2 §11.7 VAT filing — one real schema bug found and fixed, plus one worth-knowing edge case (not fixed — a product judgment call)

**Bug found:** `TaxReturnService::generateDraftReturn()` / `fileReturn()` crashed immediately — `SQLSTATE[22P02]: invalid input syntax for type uuid: "1"`. Root cause: the migration `2026_08_23_110000_create_phase7_slice6_tax_period_tables` declared `tax_periods.filed_by`, `tax_returns.generated_by`, and `tax_returns.filed_by` as `uuid`, but every actor column in every other table in this schema (`created_by`, `posted_by`, etc.) is `foreignId → users.id`, which is `bigint`. This made VAT return generation/filing fail unconditionally for any real user.

**Fix:** corrected the column types in the original migration (so a fresh install gets it right), plus a new migration (`2026_09_08_155454_fix_tax_period_and_tax_return_actor_column_types`) that drops and recreates the three columns as proper `foreignId('...')->constrained('users')` on the already-migrated database — chosen specifically so today's accumulated test data (§10's entire P2P/O2C/Fixed-Assets/Payroll/Rentals chain) didn't have to be wiped again. Applied with `php artisan migrate`; confirmed clean.

One follow-up caught by running the actual PHPUnit suite for this area (`Phase7Slice6TaxFilingTest`, 9 tests): the corrective migration unconditionally tried to drop the same columns again on a *fresh* SQLite install (the test suite's in-memory DB), where the already-fixed source migration had created them correctly the first time — and SQLite's ALTER TABLE support rejects dropping a foreign-keyed column outright, unlike Postgres. Scoped the corrective migration to `pgsql` only (the sole environment that ever had the bad columns on disk) so it's a no-op everywhere else. All 9 tests pass after that adjustment.

**After the fix**, filing worked end-to-end (`draft → filed`, snapshot stored) — but the computed **output tax was 49,000, not the 70,000 the real GL `output_tax_payable` balance shows** for the same period. Traced this all the way through `TaxReturnService → VatSummaryReportService → VatRegisterReportService`:

The register pulls *five* independent output-VAT sources for the period: `customer_invoice` (+84,000), `customer_credit_note` (−21,000), `rental_invoice` (+7,000) — all three of which have a real, posted GL entry backing them — **plus a fourth, `sales_return` (−21,000), which does not.** `SalesReturnService` copies the tax rate/amount from the originating invoice line onto the return line *for reporting purposes*, but the return's own posted journal is inventory-only (`Dr 1400 / Cr 5500`, already confirmed in §10.3/Phase 2 — no `Dr 2200` anywhere near it). The actual GL tax reversal only happens once, when the linked Credit Note posts. `VatRegisterReportService`'s sales-return query (`SalesReturn::where('status','posted')->whereBetween('return_date', ...)`) has no check for whether a Credit Note already exists for that return — so a return *and* its credit note both contribute a tax-reduction line to the register, double-counting the same real-world event by exactly the return's share (−21,000 here, which is exactly why 70,000 − 21,000 = 49,000).

**Why this is flagged as "worth knowing" rather than filed as a confirmed defect:** `CustomerCreditNotes.tsx`'s form defaults `tax_mode` to `'none'` — the UI's own guided default is that a credit note linked to a return should *not* independently claim tax, presumably relying on the return's own figure as authoritative. My test explicitly overrode that default to `'manual_rate'` (to prove the credit note's own tax posting works in isolation, which it does, correctly) and never linked `sales_return_id` between the two documents — both deviations from the path the UI steers a real user down. So this may already be a non-issue in normal guided use. What I could **not** confirm either way: nothing at the service layer stops a caller (API, or a user who overrides the default) from linking `sales_return_id` *and* setting a non-`none` tax mode, which — per the trace above — would double-count regardless of linkage, since the register query never checks for an offsetting credit note at all. Whether that's worth a defensive fix (e.g., the register excluding a return's tax line when a posted credit note references it, or the backend rejecting `tax_mode !== 'none'` when `sales_return_id` is set) is a product call, not a typo — flagging it here rather than changing behavior unilaterally.

### 12.3 §11.1 Inventory operations — executed, zero defects

Physical stock count (found 65 against a system-recorded 70 — a 5-unit shrinkage), a direct +2 "found stock" adjustment, and a 10-unit transfer `ACC-WH-MAIN → ACC-WH-ALX`, all on top of the 57 units left over from §Phase 2 + §10. Traced every journal by `source_type` rather than trusting a `journal_entry_id` column (several of these tables don't expose one directly):

- Count-driven shrinkage (auto-creates and posts a linked `stock_adjustment`): `Dr 5600 (inventory-adjustment-loss) 50,000 / Cr 1400 (Inventory Asset) 50,000` — exactly 5 units × 100.00 EGP.
- Manual "found stock" adjustment: `Dr 1400 20,000 / Cr 4920 (inventory-adjustment-gain) 20,000` — exactly 2 units × 100.00 EGP.
- Transfer: posts **no GL journal at all** — confirmed correct and intentional, since `1400` is one consolidated balance-sheet account regardless of which warehouse holds the physical stock; a transfer changing *location* without changing *company-wide value* has nothing to post. Quantities moved exactly right (57→57-10=... wait: 65 after count, +2 = 67, −10 transferred = 57 at MAIN, 10 at ALX).

**No defects.**

### 12.4 §11.2 Purchase Returns & Landed Costs — executed, zero defects

Returned 5 of the original 100 units from `GRN-2026-00001` back to the supplier, then allocated a 500.00 EGP freight charge (+70.00 EGP VAT) across that same GRN:

- Purchase Return: `Dr 2300 (GRNI) 50,000 / Cr 1400 (Inventory Asset) 50,000` — exactly 5 units × 100.00 EGP reversed. Stock 57 → 52.
- Landed Cost: total allocated (50,000) matched the input exactly — no rounding leakage across lines. The value only actually applies to stock and posts a journal on `post()` (my first pass stopped at `approve()`, mirroring an existing test helper that does the same — not an app gap, just one lifecycle step short). Once posted: unit cost rose from 100.00 → 105.00 EGP, and the journal split the 50,000 freight cost between still-on-hand inventory and already-moved-on stock — `Dr 1400 26,000 / Dr 5500 (COGS) 24,000 / Dr 1300 (Input VAT) 7,000 / Cr 2100 (AP Control) 57,000` (570.00 EGP total owed to the supplier, cost + VAT, exactly). The 52%/48% inventory/COGS split lines up exactly with "52 of the original 100 units are still at the receiving warehouse" — a sensible allocation basis, given this test's unusual order of operations (a transfer *out* of the receiving warehouse happened before the landed cost posted).

**No defects.**

### 12.5 What's next

Remaining from §11: 11.4 (Prepaid/Accrued Expenses), 11.5 (Projects/Cost Centers/Budgets — reporting rollups, not new posting logic), 11.6 (Multi-currency), 11.8 (visual UI pass), 11.9 (triage the 37 pre-existing failures), 11.10 (commit decision). Everything that could hide a genuine posting-logic defect in the *core* modules (Purchasing, Sales, Rentals, Payroll, Fixed Assets, Cheques, VAT, Inventory, Period Close, Bank Reconciliation) has now been exercised end-to-end with real data, and every defect surfaced along the way (6 total across the full session: 2 responsive/pagination classes of bugs, 2 `PeriodService` column-name typos, 1 `tax_returns`/`tax_periods` UUID-vs-bigint schema bug, and the 11-file DataTable slot-targeting bug) has been fixed and re-verified.
