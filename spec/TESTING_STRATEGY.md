# TESTING STRATEGY

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


Testing is part of implementation. CI (`.github/workflows/ci.yml`) runs typecheck + lint + a **blocking invariant job** + full tests.

**Levels:** unit (domain math), integration (posting→ledger→trial balance, allocation, valuation, numbering-under-concurrency), permission, workflow (legal/illegal transitions), E2E (Playwright, acceptance scenario in 4 QA combos EN/AR × light/dark, desktop+mobile), DB constraint/trigger tests, concurrency.

**Blocking accounting invariants (CI fails if any fail):**
Σ Debit = Σ Credit · AR sub = AR GL · AP sub = AP GL · Inventory valuation = stock ledger · Posted immutable · Closed period rejects posting · Unique doc numbers under concurrency · Reversal preserves original · Balance Sheet balances · No duplicate recurring · Jobs idempotent · Company/branch isolation.

**Implemented now (23 passing, verified):**
- `money.test.ts` — exact minor-unit parse/add/subtract, no-float, exact `allocate` (incl. 500-case property test), formatting, cross-currency block.
- `accounting.test.ts` — `assertBalanced` accepts balanced / rejects unbalanced / rejects mixed-side & single-line lines; deterministic idempotency key.
- `numbering.test.ts` — format (INV-2026-00001, branch, reset buckets) + **1000 concurrent allocations → unique & contiguous**.
- `rbac.test.ts` — allowed in-scope, denied out-of-scope, **company isolation**, missing-permission denial, `requirePermission` throws.

**Acceptance scenario (E2E, Phase 10 gate):** customer → product → purchase+receive → post purchase invoice → partial supplier payment → sale → post sales invoice → partial receipt → sales return → expense → rental+invoice → depreciation run → close period → reports; then assert Cash/Bank/AR/AP/Inventory/Revenue/COGS/Expenses/Profit/VAT/Assets/Depreciation/Equity all reconcile.
