# MINI ERP - PHASE 9 SLICE 1 CUTOVER DECISION PACK

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company ownership, branch tenancy/security ownership, currentCompany/currentBranch context, company_id, tenant_id, Spatie Teams scope, or blanket branch_id scope. Explicit branch operational references are allowed only by bounded owner-approved slices. See root `NO_MULTI_TENANT_POLICY.md`.


Execute only this slice.

This is a docs-only slice. Do not modify Laravel application code, migrations, routes, models, services, React pages, tests, or configuration behavior.

## Objective

Create an owner/operator decision pack for staging and production cutover.

Create:

- `PHASE_9_CUTOVER_DECISION_PACK.md`

Update only if needed:

- `NEXT_TASKS.md`
- `IMPLEMENTATION_STATUS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

## Read First

- `PHASE_9_STAGING_PRODUCTION_CUTOVER.md`
- `PHASE_8_FINAL_OPERATIONAL_READINESS_REPORT.md`
- `PHASE_8_OPERATIONAL_READINESS_DECISION.md`
- `spec/DEPLOYMENT.md`
- `README.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `laravel/.env.example`

## Required Content

The decision pack must explain in Arabic and English:

- what staging cutover means
- what production cutover means
- current Laravel stack
- deployment responsibilities by role
- decisions still required from the owner/operator
- go/no-go criteria
- rollback approval criteria
- minimum smoke acceptance criteria

## Required Decision Matrix

Include pending owner/operator decisions for:

- hosting target
- PostgreSQL hosting and backup owner
- domain and HTTPS ownership
- scheduler trigger mechanism
- queue worker process manager
- file storage location
- mail provider/mode
- log retention and review owner
- restore drill frequency
- staging availability
- cutover window
- rollback approver

Use checkboxes. Do not select choices for the owner unless a prior owner decision already exists in repository docs.

## Prohibited

- no real environment values
- no passwords, tokens, keys, or connection strings
- no provider account setup
- no GitHub Actions requirement
- no production command execution
- no new ERP business module
- no tenant/company/branch assumptions

## Verification

Run docs-safe checks only:

```powershell
git diff --stat
rg -n "DB_PASSWORD=|APP_KEY=base64|SECRET|TOKEN|PASSWORD|DATABASE_URL" PHASE_9_CUTOVER_DECISION_PACK.md
rg -n "company_id|branch_id|tenant_id|currentCompany|currentBranch|Spatie Teams" PHASE_9_CUTOVER_DECISION_PACK.md
```

Classify any matches. Do not mark complete if real values are present.

## Final Report

Report:

1. files created/updated
2. pending owner decisions
3. confirmation that no implementation code changed
4. sensitive-value scan result
5. tenant/company/branch assumption scan result

