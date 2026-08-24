# equipment module

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


Modular-monolith boundary. Layers: application/ (use-cases, Zod, RBAC), domain/ (engines), db/ (repositories — only layer touching Prisma), ui/.
Status: scaffolded in Phase 1; implemented in its roadmap phase. Communicates via Application Services + domain events only.
