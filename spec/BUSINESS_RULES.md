# BUSINESS RULES & INVARIANTS

These are enforced in the **service/DB layer**, not merely in the UI. Each has an ID for traceability and tests.

## Accounting integrity
- **BR-A1** Every posted journal entry balances: `Σ debit_base = Σ credit_base`. Reject post otherwise.
- **BR-A2** Control accounts (AR, AP, Inventory, Tax, Fixed Assets, Payroll clearing, Cheque clearing) are posted **only** by their subledger events; manual JV to them is blocked.
- **BR-A3** No posting into a **closed** period; reopening requires `Reopen` permission and is audited.
- **BR-A4** Posted entries are **immutable** — no edit/delete; correction is a linked **reversing** entry (original preserved).
- **BR-A5** Financial statements & dashboard KPIs derive **only** from posted data via shared report queries (no independent recomputation).
- **BR-A6** Balance Sheet must satisfy Assets = Liabilities + Equity for any period.
- **BR-A7** Year-end close rolls net P&L to Retained Earnings and carries balances forward via an opening JV.
- **BR-A8** Monetary math uses integer minor units + decimal; **never IEEE-754 float**. Rounding half-up to minor unit; residue to rounding account.

## Subledger & reconciliation
- **BR-S1** AR subledger total = AR control GL balance at all times.
- **BR-S2** AP subledger total = AP control GL balance.
- **BR-S3** Inventory valuation (stock ledger) = Inventory control GL balance.
- **BR-S4** Fixed-asset NBV register = (Fixed Asset − Accumulated Depreciation) GL.
- **BR-S5** A scheduled integrity job flags any subledger↔GL divergence; divergence is a blocking alert.

## Documents & lifecycle
- **BR-D1** Document numbers are unique and concurrency-safe; never duplicated (per numbering policy).
- **BR-D2** Posted documents cannot be silently edited; changes require reversal/credit-debit note.
- **BR-D3** Valid status transitions only (WORKFLOW_CATALOG state machines); illegal transitions rejected.
- **BR-D4** Approval required before posting where the doc type's flow mandates it; approver ≠ (optionally) creator per config.
- **BR-D5** Cancellation allowed pre-post; post-post uses reversal.

## Inventory
- **BR-I1** Every stock movement has a `source_type/source_id`.
- **BR-I2** Cannot issue/transfer more than available unless the warehouse's negative-stock policy allows it (DECISION REQUIRED default = block).
- **BR-I3** COGS uses the product's costing method (WAVG/FIFO) consistently; valuation reconciles (BR-S3).
- **BR-I4** Stock count variance posts an adjustment JV; counted stock cannot be edited after posting.

## AR / AP / payments
- **BR-P1** A receipt/payment cannot allocate more than the invoice's remaining balance (no negative remaining); excess → advance.
- **BR-P2** Customer/supplier balances stay consistent after every allocation (recomputed atomically).
- **BR-P3** Credit-limit breach on a credit sale is blocked or requires override permission (config).
- **BR-P4** Aging buckets: Current, 1-30, 31-60, 61-90, 90+ (by due date).

## Tax
- **BR-T1** Tax rates/kinds are configuration; nothing hardcoded in modules.
- **BR-T2** Input/Output/Withholding tracked to distinct accounts; net VAT = Output − Input; reviewable before filing.

## Multi-currency
- **BR-C1** Foreign-currency docs capture `fx_rate` at document date; GL stores base + txn amounts.
- **BR-C2** Realized FX gain/loss posts on settlement; unrealized via period-end revaluation job.

## Rentals / Equipment
- **BR-R1** Equipment must be `Available` to allocate to a rental; rental drives `Rented` status.
- **BR-R2** Deposit held as liability until contract close; applied or refunded, never silently dropped.
- **BR-R3** Late/damage/extra charges auto-computed and added to the final invoice.

## Security & audit
- **BR-X1** All writes authorized server-side against the user's permission + scope; UI shows permission-denied state.
- **BR-X2** Every create/modify/approve/post/reverse/cancel/delete is audited; financial audit is append-only/immutable.
- **BR-X3** Destructive/financial actions (post, reverse, close period, delete, config change) require explicit confirmation showing consequences.
