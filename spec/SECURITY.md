# SECURITY

- **AuthN:** Auth.js sessions; passwords hashed with argon2; optional MFA for privileged roles (Admin, Accountant with post/close).
- **AuthZ:** central `PermissionSet.requirePermission` in every Application Service; deny-by-default; UI hiding is cosmetic only. Actions: view/create/edit/delete/submit/approve/reject/post/cancel/reverse/export/print/configure. Sensitive flags: view_financials, view_payroll, override_credit_limit, override_negative_stock, close_period, reopen_period.
- **Tenancy:** every query enforces `company_id` (and branch/warehouse/project/cost-center/doc-type scope) server-side; `company_id` is never trusted from the browser. Enforced in `core/rbac` (`can()` cannot widen beyond the request company) — covered by `tests/invariants/rbac.test.ts`.
- **Data:** TLS in transit; encryption at rest (managed Postgres); secrets via env/vault, never in repo; payroll/financial fields permission-gated.
- **Integrity:** all money writes transactional; posted rows immutable; period locks; concurrency-safe numbering.
- **App hardening:** Zod validation at every boundary; CSRF protection on mutations; rate limiting on auth; output encoding; secure headers; least-privilege DB role; audit of privileged actions.
- **Audit:** append-only `audit_log` with actor/action/entity/before/after/reason/ip; financial audit immutable.
