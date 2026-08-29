# Mini ERP - Phase 17 Slice 5 Agy Prompt

Execute ONLY Phase 17 Slice 5: Attachment, Notification, and Private Delivery Safety Hardening.

You are operating in an existing Laravel 13 + Inertia + React Mini ERP. This is a defensive security pass only. Stop after this slice. Do not start Slice 6 or any business module.

## Non-Negotiable System Rules

- No multi-tenant architecture.
- Do not add `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, company-user membership, or Spatie Teams.
- Branch is an operational/reporting dimension only where already implemented. Do not make Branch a security scope, login context, user ownership scope, or blanket attachment/notification scope.
- Do not change accounting math, posting behavior, stock costing, tax, payroll, period close, document numbering, idempotency, or locks.
- Spatie Activitylog remains the active audit backend. Do not revive legacy `audit_log` for new writes.
- Controllers must stay thin. Put security and validation behavior in FormRequests, services, authorizers, policies, middleware, or support classes.
- UI changes, if any, must use dictionary-backed EN/AR visible text only.
- Do not use native `<select>`, `<option>`, `type="date"`, `dangerouslySetInnerHTML`, or `window.location.href`.
- Do not make files publicly reachable through `/storage` or public disks.

## Existing Baseline To Inspect

Review before changing:

- `laravel/app/Application/Attachments/AttachmentService.php`
- `laravel/app/Application/Attachments/AttachmentEntityAuthorizer.php`
- `laravel/app/Http/Controllers/AttachmentController.php`
- `laravel/config/erp_attachments.php`
- `laravel/resources/js/Components/AttachmentPanel.tsx`
- `laravel/app/Application/Notifications/NotificationService.php`
- `laravel/app/Http/Controllers/NotificationController.php`
- `laravel/routes/web.php`
- `laravel/app/Support/Security/RouteAuthorizationAuditor.php`
- `laravel/tests/Feature/AttachmentAndNotificationTest.php`
- `laravel/tests/Feature/M9AttachmentsAndNotificationsTest.php`
- `laravel/tests/Feature/SecurityHardeningTest.php`

## Objective

Harden attachment and notification handling against common web application failures while preserving the current entity-based attachment model and user-scoped notifications:

- Attachments remain bound to `entity_type` and `entity_id`.
- Authorization must come from `AttachmentEntityAuthorizer` and server-side entity permissions/existence checks.
- Notifications remain strictly target-user scoped.
- No invented company/tenant/branch ownership is allowed.
- Attachment storage remains private and served only through authenticated controller/service flows.

## Required Attachment Hardening

Implement only missing safeguards. Preserve existing safe behavior.

1. Filename and path safety:
   - Prevent path traversal in uploaded filenames and display names.
   - Stored paths must be generated server-side under the configured private attachment root.
   - Reject or normalize suspicious names containing path separators, control characters, null bytes, dot-only names, and excessively long names.
   - Download response must use a safe `Content-Disposition` filename. Do not reflect raw attacker-controlled filenames.

2. Extension and MIME validation:
   - Keep extension allowlist from config.
   - Keep MIME allowlist from config when configured.
   - Prevent obvious extension/MIME mismatches where Laravel/Symfony can reliably detect them.
   - Do not add any external malware scanning provider. Document malware scanning as optional future deployment integration only.

3. Private storage:
   - Confirm attachment disk is private by default.
   - Add tests/config guard proving `FILESYSTEM_LOCAL_SERVE` stays `false` in templates/docs where relevant.
   - Do not move attachments to `public` disk.
   - Do not create symlink/public direct-serving behavior.

4. Delete integrity:
   - Keep DB/file cleanup behavior safe.
   - Ensure deletion authorization is checked before deleting file or DB row.
   - Ensure deletion audit evidence is written with attachment ID, entity type, entity ID, name, MIME, and size.
   - Do not weaken append-only Spatie activity behavior.

5. Controller cleanliness:
   - If request validation grows, extract `StoreAttachmentRequest`, `ListAttachmentRequest`, or equivalent.
   - Keep `AttachmentController` thin and service-driven.

## Required Notification Hardening

Implement only missing safeguards. Preserve existing user isolation.

1. User isolation:
   - A user must not be able to mark another user's notification as read.
   - `markRead` and `markAllRead` must only affect the authenticated user's notifications.
   - `NotificationController` must never trust `user_id` from the request payload.

2. Dedupe and payload safety:
   - Preserve deterministic dedupe behavior.
   - Do not allow one user's dedupe key to suppress another user's notification.
   - Validate/normalize notification `type` and `target_ref` lengths at service boundaries if missing.

3. Audit and route authorization:
   - Preserve route-audit allowlist classification for attachments/notifications because service-level authorization is intentional.
   - If notification read state changes are audited, use Spatie Activitylog and do not include sensitive payloads.
   - Do not add broad new permissions unless strictly required by existing behavior.

## Tests Required

Add or extend focused tests. Required coverage:

1. Path traversal upload names cannot escape the private attachment directory.
2. Path traversal display names are sanitized before storage and download.
3. Unsupported extension upload is rejected.
4. Unsupported MIME upload is rejected when MIME allowlist is configured.
5. Private storage policy is preserved; attachment files are not placed on a public disk/path.
6. Download requires authenticated and entity-authorized access.
7. Delete requires authorization before file/DB mutation.
8. Delete writes Spatie Activitylog evidence with attachment metadata.
9. Cross-user notification `markRead` does not update another user's notification.
10. Cross-user notification dedupe does not suppress notifications for a different user.
11. `NotificationController` ignores any payload `user_id` and uses the session user only.
12. Route audit remains green for attachment and notification routes.
13. No-scope scan confirms no new `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, or Spatie Teams references in Slice 5 files.
14. If TSX changed, source scan confirms no hardcoded visible strings and no forbidden native controls/unsafe APIs.

Prefer extending:

- `AttachmentAndNotificationTest`
- `M9AttachmentsAndNotificationsTest`
- `SecurityHardeningTest`

Create a new focused test only if it keeps the suite clearer.

## Verification Commands

Run from `laravel/` and report exact results:

```powershell
vendor/bin/pint --test
php artisan test --filter=AttachmentAndNotificationTest --compact
php artisan test --filter=M9AttachmentsAndNotificationsTest --compact
php artisan test --filter=SecurityHardeningTest --compact
php artisan security:route-audit --strict
npm run typecheck
```

Run `npm run build` only if frontend files changed. If no frontend files changed, explicitly say build was skipped because Slice 5 stayed backend/test/docs only.

## Final Report

Create `PHASE_17_SLICE_5_REPORT.md` with:

- exact files changed
- attachment hardening changes
- notification hardening changes
- route authorization impact
- audit evidence behavior
- tests added/changed
- verification results
- no-scope scan result
- UI scan result if TSX changed
- remaining risks

Update:

- `PHASE_17_SECURITY_ACCESS_GOVERNANCE.md`
- `IMPLEMENTATION_STATUS.md`
- `NEXT_TASKS.md`
- `CONTINUE_HERE.md`
- `CHANGELOG.md`

Stop after Phase 17 Slice 5. Do not start Slice 6.
