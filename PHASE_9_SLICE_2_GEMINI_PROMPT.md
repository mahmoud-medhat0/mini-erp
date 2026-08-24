# MINI ERP - PHASE 9 SLICE 2 ENVIRONMENT & SECRETS CHECKLIST

Execute only this slice.

This is a documentation and template-audit slice. Do not print or request private values.

## Objective

Create a deployment environment checklist using variable names only and audit `.env.example` for completeness.

Create:

- `spec/ENVIRONMENT_CHECKLIST.md`

Update only if required:

- `laravel/.env.example`
- `spec/DEPLOYMENT.md`
- `NEXT_TASKS.md`
- `IMPLEMENTATION_STATUS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

## Read First

- `PHASE_9_STAGING_PRODUCTION_CUTOVER.md`
- `PHASE_9_CUTOVER_DECISION_PACK.md` if it exists
- `PHASE_8_FINAL_OPERATIONAL_READINESS_REPORT.md`
- `spec/DEPLOYMENT.md`
- `laravel/.env.example`
- `laravel/config/app.php`
- `laravel/config/database.php`
- `laravel/config/queue.php`
- `laravel/config/filesystems.php`
- `laravel/config/mail.php`
- `laravel/config/logging.php`

## Required Content

`spec/ENVIRONMENT_CHECKLIST.md` must include:

- variable name
- purpose
- required in local/staging/production
- acceptable category of value, not the actual value
- owner/operator notes
- validation method

Cover at least:

- app identity and URL
- debug mode
- app key presence
- locale fallback
- PostgreSQL connection
- session/cache/queue drivers
- filesystem disk
- mail mode
- log channel and level
- Vite app name

## `.env.example` Rules

Only update `.env.example` when a required variable name is missing.

Allowed:

- add missing variable names with safe placeholder values
- add comments explaining purpose

Prohibited:

- real credentials
- real hostnames not already public/example values
- real tokens
- production passwords
- provider-specific private values

## Verification

Run:

```powershell
git diff --stat
rg -n "DB_PASSWORD=.+|APP_KEY=base64:.+|SECRET=.+|TOKEN=.+|PASSWORD=.+|DATABASE_URL=.+" spec/ENVIRONMENT_CHECKLIST.md laravel/.env.example spec/DEPLOYMENT.md
rg -n "Next.js|Prisma|pg-boss|PGBOSS|prisma migrate" spec/ENVIRONMENT_CHECKLIST.md spec/DEPLOYMENT.md README.md
```

Classify historical matches. Do not mark complete if private-looking values were added.

## Final Report

Report:

1. files created/updated
2. variables documented
3. `.env.example` changes, if any
4. sensitive-value scan result
5. historical reference classifications

