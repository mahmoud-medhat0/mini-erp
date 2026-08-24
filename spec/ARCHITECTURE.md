# ARCHITECTURE

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


Authoritative summary; depth in `FINAL_ARCHITECTURE_REVIEW.md`.

**Style:** Modular Monolith. One Next.js deployable, partitioned into domain modules with service-shaped boundaries so any domain can later be extracted without rewrites.

**Layers (strict):** `UI → Application Services → Domain Engines → Repositories → PostgreSQL`. UI holds no business/accounting logic and computes no authoritative balances. Repositories are the only layer touching the DB. Ledger/stock/subledger rows are written only by domain engines, inside a DB transaction.

**Runtime:** Node.js server (never Edge for DB/accounting). Background worker (same image) for jobs via pg-boss (Postgres-backed).

**Module boundary:** `modules/<name>/{application,domain,db,ui}` + `permissions.ts`, `events.ts`, `validators.ts`. Cross-module calls go through Application Service interfaces + a typed in-process domain-event bus — never another module's repositories. **Accounting is a shared kernel**; it knows only `source_type/source_id`, never a module's internals.

**Dependency graph:** kernel ← Accounting ← (Sales, Purchasing, Cash/Bank, Expenses, Payroll, Assets, Rentals) ; Inventory used by Sales/Purchasing/Rentals ; Reports/Dashboard/Budgeting are read-only consumers of posted data. Acyclic; drives phase order.

**Money:** BigInt minor units + currency; decimal.js for intermediate ratios; multi-currency Day-One; realized/unrealized FX.

**Core kernel modules:** money, currency, numbering, rbac, audit, accounting-kernel, errors, auth, localization, notifications, jobs, attachments, workflow, permissions.
