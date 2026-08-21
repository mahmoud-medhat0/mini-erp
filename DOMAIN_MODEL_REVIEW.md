# Domain Model Review - Laravel Relationship Correction

Date: 2026-08-21

## Purpose

This document corrects unsupported Company, Branch, and User relationship assumptions in the Laravel migration target.

Source-of-truth rule: use the original ERP requirements provided by the project owner. Previous AI-generated documents, Prisma schema, Laravel migrations, and existing tests are not proof of a business relationship.

If a relationship is not explicitly supported by the original requirements, classify it as:

`UNDEFINED - DO NOT ASSUME`

## Confirmed

| Item | Classification |
|---|---|
| User authentication | CONFIRMED |
| User -> Role/Permission through Spatie Permission | CONFIRMED |
| Server-side RBAC enforcement | CONFIRMED |
| Company settings/business configuration | CONFIRMED |
| Branch as a referenced business/reporting concept | REFERENCED |
| Automatic document numbering by document key/type | CONFIRMED |
| Audit trail linked to actor/causer and audited entity/event | CONFIRMED |
| Attachments linked to referenced business entity | CONFIRMED |
| Notifications targeted to users and business events/entities | CONFIRMED |
| FiscalYear is global to this single ERP installation/business profile | CONFIRMED BY OWNER DECISION |
| FinancialPeriod belongs to FiscalYear | CONFIRMED BY OWNER DECISION |

## Undefined Relationships

| Relationship | Classification |
|---|---|
| Company -> User | UNDEFINED - DO NOT ASSUME |
| User -> Company | UNDEFINED - DO NOT ASSUME |
| Company -> Branch | UNDEFINED - DO NOT ASSUME |
| Branch -> Company | UNDEFINED - DO NOT ASSUME |
| User -> Branch | UNDEFINED - DO NOT ASSUME |
| User -> Employee | UNDEFINED - DO NOT ASSUME |
| Company = Tenant | REMOVE |
| Branch = Tenant/security boundary | REMOVE |
| Login establishes current company | REMOVE |
| Login establishes current branch | REMOVE |
| Company-owned roles/permissions | REMOVE |
| Spatie teams/company scope | REMOVE |

## Laravel Correction Applied

The Laravel target must not create or depend on:

- `company_user`
- `user.company_id`
- `Company::users()`
- `User::companies()`
- `Branch.company_id`
- `Company::branches()`
- `Branch::company()`
- `currentCompany`
- `currentBranch`
- `number_sequence(company_id, key)`
- `number_sequence.include_branch`
- `fiscal_year.company_id`
- company-scoped audit/activity, attachment, or notification records unless a later explicit entity model requires it

## Preserved

- `company` table as business/company configuration.
- `branch` table as a standalone reference record until a precise model is explicitly defined.
- Spatie Permission with teams disabled.
- Global role templates and module/action permissions.
- Spatie Activitylog-backed audit append-only behavior, redaction, actor/causer link, entity type/id, action/event, before/after JSON, timestamp.
- Attachment metadata linked by `entity_type` / `entity_id` and uploaded actor.
- Notification targeting by `user_id`, `target_ref`, read state, and per-user dedupe key.
- Fiscal years by global `year`, with financial periods linked by `fiscal_year_id`.
- Atomic/concurrency-safe numbering by sequence key.
- Idempotency and token garbage collection.

## Remaining Company References

`company.id` remains as the primary key of the company configuration record.

FiscalYear ownership/context is resolved as `SINGLE-ERP CONTEXT`: no Company/Tenant relationship, no `company_id`, and global `year` uniqueness.

## Rule For Future Work

Do not replace removed assumptions with another guessed organizational scope. Add foreign keys, pivots, ownership, authorization scopes, or uniqueness constraints only when an explicit business requirement proves the relationship.
