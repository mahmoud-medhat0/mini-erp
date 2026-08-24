# DISASTER RECOVERY

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


- **Backups:** automated daily full + WAL/PITR on managed Postgres; retention ≥ 30 days.
- **Targets:** RPO ≤ 15 minutes (WAL), RTO ≤ 1 hour. Both documented and drill-tested.
- **Restore drills:** scheduled restore into an isolated environment; verify row counts and that the **subledger↔GL reconciliation job reports zero drift** post-restore.
- **Financial safety:** posted data is append-only/immutable, so recovery is deterministic; nightly integrity snapshot makes corruption detectable.
- **Attachments/object storage:** versioned bucket with lifecycle + backup.
- **Infra:** infrastructure-as-code so the full stack is reproducible; documented runbook (restore steps, contacts, verification checklist).
