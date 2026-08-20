# DEPLOYMENT

- **Topology:** containerized Next.js (Node runtime) behind a reverse proxy; managed PostgreSQL; a worker container (same image, `WORKER=1`) for pg-boss jobs.
- **Environments:** local → staging → production. Config via 12-factor env vars; per-env secrets; base currency & locale seeded, not hardcoded.
- **Migrations:** `prisma migrate deploy` runs in CI before the app switches over; `prisma generate` in build.
- **Release:** CI = typecheck + lint + invariant/unit/integration tests (invariants blocking) → build → blue/green or rolling deploy. Incomplete modules are feature-flagged and render an explicit "Not available yet" state (never fake data).
- **Scaling:** stateless web tier scales horizontally; Postgres vertical + read replicas for reporting later; jobs scale by worker count.
- **Health:** readiness/liveness probes; migration gate; post-deploy smoke (auth + a trivial posting round-trip in staging).
