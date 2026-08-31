# Phase 17 Slice 2 - Route Authorization Audit Command and Regression Guard Report

## 1. Overview & Objective

Phase 17 Slice 2 implements a read-only, defensive route authorization audit command (`security:route-audit`) and automated regression suite in Mini ERP.

The command inspects all registered application routes via `Route::getRoutes()` and classifies each route into exactly one categorization category:
1. `public`
2. `guest`
3. `explicitly_authorized`
4. `service_authorized_allowlist`
5. `failing`

This makes route authorization drift immediately visible to developers, CI pipelines, and operators without altering business logic or runtime authorization workflows.

---

## 2. Files Changed

| File | Change Type | Description |
|---|---|---|
| `laravel/app/Support/Security/RouteAuthorizationAuditor.php` | Created | Central route categorization engine with centralized service-authorized and public allowlists with documented reason strings. |
| `laravel/app/Console/Commands/SecurityRouteAuditCommand.php` | Created | Artisan command `security:route-audit` with `--strict` and `--json` support and human-readable summary tables. |
| `laravel/tests/Feature/SecurityHardeningTest.php` | Modified | Added 9 new regression tests covering standard run, strict mode, JSON schema, dynamic auth-only failing route detection, unlisted public route detection, strict non-zero exit codes, and allowlist reason completeness (15 tests / 636 assertions total). |
| `spec/SECURITY.md` | Modified | Documented route authorization audit command, classification categories, and allowlist rules. |
| `PHASE_17_SECURITY_ACCESS_GOVERNANCE.md` | Modified | Updated Slice 2 status to `COMPLETE` with link to report. |
| `IMPLEMENTATION_STATUS.md` | Modified | Updated Phase 17 status and verified command metrics. |
| `NEXT_TASKS.md` | Modified | Marked Slice 2 complete and prepared next steps. |
| `CONTINUE_HERE.md` | Modified | Updated handoff notes for Slice 2 completion. |
| `CHANGELOG.md` | Modified | Added Phase 17 Slice 2 changelog entry. |
| `PHASE_17_SLICE_2_REPORT.md` | Created | Final verification and summary report for Slice 2. |

---

## 3. Command Behavior & Options

### Signature & Description
```bash
php artisan security:route-audit {--strict : Return non-zero exit code if any route is classified as failing} {--json : Emit machine-readable JSON summary}
```

### Options

1. **Standard Human-Readable Output (default):**
   - Scans all registered routes (`Route::getRoutes()`).
   - Displays a summary table of route counts per category (`Explicitly Authorized`, `Service Authorized (Allowlisted)`, `Public`, `Guest`, `Failing`).
   - Displays detailed tables for public allowlisted routes and service-authorized allowlisted routes with route name, URI, HTTP methods, and rationale.
   - If failing routes exist, displays a failing routes table with route name, URI, HTTP methods, and gathered middleware, printing error diagnostics.
   - Returns exit code `0`.

2. **`--strict`:**
   - Evaluates whether any route is classified as `failing`.
   - Returns exit code `0` when `failing === 0`.
   - Returns exit code `1` (`SymfonyCommand::FAILURE`) when `failing > 0`.

3. **`--json`:**
   - Emits valid, machine-parsable JSON to standard output.
   - Includes top-level keys:
     - `total`: integer count of all scanned routes.
     - `counts`: dictionary mapping each category to its count.
     - `failures`: array of objects representing failing routes (`name`, `uri`, `methods`, `middleware`).
     - `allowlisted`: array of objects representing service-authorized allowlisted routes (`name`, `uri`, `methods`, `reason`).
     - `public_allowlisted`: array of objects representing explicitly public allowlisted routes (`name`, `uri`, `methods`, `reason`).
   - When combined with `--strict`, exits with code `1` if failures exist or `0` if clean.

---

## 4. Classification Model

Each route in `Route::getRoutes()` is classified into exactly one mutually exclusive category:

```
                  ┌────────────────────────┐
                  │   gatherMiddleware()   │
                  └───────────┬────────────┘
                              │
                    Has 'guest' middleware?
                    ├── YES ──> [ guest ]
                    │
                    Has 'auth' middleware?
                    ├── NO ───> [ public ]
                    │
           Has 'can:*', 'permission.any:*',
               or 'permission.all:*'?
                    ├── YES ──> [ explicitly_authorized ]
                    │
            Route name in central allowlist?
                    ├── YES ──> [ service_authorized_allowlist ]
                    │
                    └── NO ───> [ failing ]
```

### Classification Definitions

- **`guest`**: Public authentication entry points requiring guest state (`/login` GET/POST).
- **`public`**: Unauthenticated routes explicitly documented in the public allowlist (`/up`, `/health`, `/locale`, and local Inertia development tooling routes).
- **`explicitly_authorized`**: Authenticated routes protected by Spatie/Laravel route authorization middleware (`can:`, `permission.any:`, or `permission.all:`).
- **`service_authorized_allowlist`**: Authenticated routes intentionally allowed without route-level permission middleware because authorization is enforced internally by entity policies or user-scoped service authorizers.
- **`failing`**: Authenticated routes lacking explicit authorization middleware and not documented in the service-authorized allowlist, or unauthenticated routes not documented in the public allowlist.

---

## 5. Centralized Allowlists

### Public Allowlist

Unauthenticated routes are accepted only when they are documented in `RouteAuthorizationAuditor::PUBLIC_ALLOWLIST`:

| Key | Reason |
|---|---|
| `name:health` | Application health probe with no sensitive payload |
| `name:locale.update` | Locale preference update stores only a language choice in session |
| `uri:up` | Laravel framework uptime probe with no sensitive payload |
| `uri:_inertia/devtools/entries` | Inertia development tooling route with no ERP business payload |
| `uri:_inertia/devtools/entries/{id}` | Inertia development tooling route with no ERP business payload |

### Service-Authorized Allowlist

All allowlisted routes are centralized in `App\Support\Security\RouteAuthorizationAuditor::SERVICE_AUTHORIZED_ALLOWLIST` with non-empty reason strings:

| Route Name | URI | Methods | Authorization Reason |
|---|---|---|---|
| `foundation` | `//` | `GET\|HEAD\|POST\|PUT\|PATCH\|DELETE\|OPTIONS` | Redirects authenticated user to dashboard without tenant/company context |
| `logout` | `/logout` | `POST` | Standard authenticated session termination handler |
| `notifications` | `/notifications` | `GET\|HEAD` | User-scoped notification feed authorized by authenticated session user |
| `notifications.read_all` | `/notifications/read-all` | `POST` | User-scoped notification state update authorized by authenticated session user |
| `notifications.read` | `/notifications/{id}/read` | `POST` | User-scoped notification item update authorized by authenticated session user |
| `attachments.index` | `/attachments` | `GET\|HEAD` | Entity attachment access authorized internally by AttachmentService/model policy |
| `attachments.store` | `/attachments` | `POST` | Entity attachment creation authorized internally by AttachmentService/model policy |
| `attachments.show` | `/attachments/{id}` | `GET\|HEAD` | Entity attachment download authorized internally by AttachmentService/model policy |
| `attachments.destroy` | `/attachments/{id}` | `DELETE` | Entity attachment deletion authorized internally by AttachmentService/model policy |
---

## 6. Route Table Audit Scan Results

Executing `php artisan security:route-audit` against the current application route table produced:

- **Total routes scanned:** 457
- **Explicitly Authorized:** 441
- **Service Authorized (Allowlisted):** 9
- **Public:** 5 (`/_inertia/devtools/entries`, `/_inertia/devtools/entries/{id}`, `/up`, `/health`, `/locale`)
- **Guest:** 2 (`/login` GET, `/login` POST)
- **Failing:** 0

### JSON Output Sample (`php artisan security:route-audit --json`)
```json
{
  "total": 457,
  "counts": {
    "public": 5,
    "guest": 2,
    "explicitly_authorized": 441,
    "service_authorized_allowlist": 9,
    "failing": 0
  },
  "failures": [],
  "allowlisted": [
    {
      "name": "logout",
      "uri": "logout",
      "methods": [
        "POST"
      ],
      "reason": "Standard authenticated session termination handler"
    },
    {
      "name": "foundation",
      "uri": "/",
      "methods": [
        "GET",
        "HEAD",
        "POST",
        "PUT",
        "PATCH",
        "DELETE",
        "OPTIONS"
      ],
      "reason": "Redirects authenticated user to dashboard without tenant/company context"
    },
    {
      "name": "notifications",
      "uri": "notifications",
      "methods": [
        "GET",
        "HEAD"
      ],
      "reason": "User-scoped notification feed authorized by authenticated session user"
    },
    {
      "name": "notifications.read_all",
      "uri": "notifications/read-all",
      "methods": [
        "POST"
      ],
      "reason": "User-scoped notification state update authorized by authenticated session user"
    },
    {
      "name": "notifications.read",
      "uri": "notifications/{id}/read",
      "methods": [
        "POST"
      ],
      "reason": "User-scoped notification item update authorized by authenticated session user"
    },
    {
      "name": "attachments.index",
      "uri": "attachments",
      "methods": [
        "GET",
        "HEAD"
      ],
      "reason": "Entity attachment access authorized internally by AttachmentService/model policy"
    },
    {
      "name": "attachments.store",
      "uri": "attachments",
      "methods": [
        "POST"
      ],
      "reason": "Entity attachment creation authorized internally by AttachmentService/model policy"
    },
    {
      "name": "attachments.show",
      "uri": "attachments/{id}",
      "methods": [
        "GET",
        "HEAD"
      ],
      "reason": "Entity attachment download authorized internally by AttachmentService/model policy"
    },
    {
      "name": "attachments.destroy",
      "uri": "attachments/{id}",
      "methods": [
        "DELETE"
      ],
      "reason": "Entity attachment deletion authorized internally by AttachmentService/model policy"
    }
  ]
}
```

---

## 7. Test Results

All verification commands executed cleanly from `laravel/`:

### 7.1 Route Audit Artisan Command
```powershell
php artisan security:route-audit
```
- **Exit code:** 0
- **Result:** Scanned 457 routes; 0 failing.

### 7.2 Route Audit Strict Mode
```powershell
php artisan security:route-audit --strict
```
- **Exit code:** 0
- **Result:** Scanned 457 routes; 0 failing; strict mode exit code 0.

### 7.3 Route Audit JSON Mode
```powershell
php artisan security:route-audit --json
```
- **Exit code:** 0
- **Result:** Emitted valid JSON with keys `total`, `counts`, `failures`, `allowlisted`, and `public_allowlisted`.

### 7.4 Code Formatting (Laravel Pint)
```powershell
vendor/bin/pint --test
```
- **Result:** `{"tool":"pint","result":"passed"}`.

### 7.5 Security Hardening Feature Test Suite
```powershell
php artisan test --filter=SecurityHardeningTest --compact
```
- **Result:** `{"tool":"phpunit","result":"passed","tests":15,"passed":15,"assertions":636,"duration_ms":12278}`.
- **Coverage:**
  - `test_web_responses_include_baseline_security_headers`
  - `test_inactive_authenticated_user_is_logged_out_before_accessing_protected_pages`
  - `test_dashboard_requires_explicit_dashboard_view_permission`
  - `test_audit_log_requires_explicit_audit_view_permission`
  - `test_tax_filing_permission_is_seeded_as_sensitive_capability`
  - `test_authenticated_application_routes_have_explicit_authorization_or_documented_entity_authorizer`
  - `test_security_route_audit_command_succeeds_on_current_route_table`
  - `test_security_route_audit_command_strict_succeeds_on_current_route_table`
  - `test_security_route_audit_command_json_returns_valid_json_with_expected_keys`
  - `test_dynamically_registered_auth_only_route_without_authorization_is_classified_as_failing`
  - `test_strict_mode_returns_exit_code_1_when_dynamic_failing_route_exists`
  - `test_public_route_without_public_allowlist_is_classified_as_failing`
  - `test_all_service_authorized_allowlist_route_names_are_documented_with_non_empty_reason_strings`
  - `test_all_public_allowlist_entries_are_documented_with_non_empty_reason_strings`
  - `test_no_tenant_company_or_branch_security_scope_introduced`

### 7.6 Authentication Feature Test Suite
```powershell
php artisan test --filter=AuthenticationTest --compact
```
- **Result:** `{"tool":"phpunit","result":"passed","tests":15,"passed":15,"assertions":51,"duration_ms":3494}`.

### 7.7 TypeScript Typecheck
```powershell
npm run typecheck
```
- **Result:** Passed cleanly with 0 errors (`tsc --noEmit`).

---

## 8. Source Scan Classification

### Scan 1: Multi-Tenant / Company Scope Identifiers
```powershell
rg -n "company_id|tenant_id|currentCompany|currentTenant|Spatie Teams" laravel/app laravel/routes laravel/tests spec/SECURITY.md PHASE_17_SLICE_2_REPORT.md
```
- **Matches in `laravel/app`:** Prohibited column assertions in `Phase3IntegrityCheckCommand.php`.
- **Matches in `laravel/tests`:** Prohibited column regression tests ensuring schema does not contain multi-tenant columns.
- **Matches in `spec/SECURITY.md` & reports:** Policy text explicitly prohibiting multi-tenancy.
- **Classification:** Clean. Zero multi-tenant/company/teams logic or columns introduced.

### Scan 2: Authenticated Route Middleware Declarations
```powershell
rg -n "Route::.*middleware\('auth'\)|->middleware\('auth'\)" laravel/routes/web.php
```
- **Matches:**
  - `laravel/routes/web.php:132`: `logout` route.
  - `laravel/routes/web.php:135`: `Route::middleware('auth')->group(...)` protecting all authenticated ERP application routes.
- **Classification:** Clean. All protected routes are grouped under `auth` middleware and evaluated by `security:route-audit`.

### Scan 3: Route Audit Identifiers
```powershell
rg -n "security:route-audit|service_authorized_allowlist|explicitly_authorized|failing" laravel/app laravel/tests PHASE_17_SLICE_2_REPORT.md
```
- **Matches:**
  - `laravel/app/Support/Security/RouteAuthorizationAuditor.php`: Auditor engine & categorization keys.
  - `laravel/app/Console/Commands/SecurityRouteAuditCommand.php`: Command signature & option handlers.
  - `laravel/tests/Feature/SecurityHardeningTest.php`: Feature test suite and assertions.
  - `PHASE_17_SLICE_2_REPORT.md`: Slice report documentation.
- **Classification:** Clean. All occurrences directly relate to the Phase 17 Slice 2 route authorization audit command and test suite.

---

## 9. Route Guard Changes

- **Were any route guards modified?** No.
- The command confirmed that all 457 existing registered routes already satisfy authorization requirements (441 explicitly authorized, 9 allowlisted with entity/session policies, 5 public, 2 guest). No existing route guard needed modification.

---

## 10. Remaining Risks & Phase 17 Next Steps

1. **Password Policy & Session Safety (Slice 3):** Hardening password complexity validation, session lifetimes, and concurrent session restrictions (`PHASE_17_SLICE_3_AGY_PROMPT.md`).
2. **Sensitive Action Confirmation & Audit Evidence (Slice 4):** Standardizing pre-flight confirmations and actor evidence on irreversible financial mutations.
3. **Attachment & Notification Privacy (Slice 5):** Hardening storage delivery and notification privacy boundaries.
4. **Execution Policy:** Stop after Slice 2. Do not execute Slice 3 without user request.
