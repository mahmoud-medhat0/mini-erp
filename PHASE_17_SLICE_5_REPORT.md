# Phase 17 Slice 5 Report: Attachment, Notification, and Private Delivery Safety Hardening

**Execution Date:** 2026-08-29  
**Status:** COMPLETE (Ready for Slice 6)  
**Track:** Defensive Security Pass Only  

---

## 1. Executive Summary

Phase 17 Slice 5 hardened file attachment delivery and user notifications against common web application vulnerabilities (path traversal, arbitrary file upload, extension/MIME spoofing, public direct file access, authorization bypass, and cross-user notification state mutation).

All safeguards were implemented strictly preserving the single-installation, entity-bound attachment model and session-isolated notification model:
- Attachments remain bound to `(entity_type, entity_id)` and authorized server-side via `AttachmentEntityAuthorizer`.
- Storage is strictly private on the local disk (`storage/app/private/attachments/...`) with direct local serving (`FILESYSTEM_LOCAL_SERVE`) disabled.
- Filename and path traversal protections normalize uploaded and display filenames, block path escape sequences (`..`), null bytes, and non-printable control characters.
- Strict extension and MIME compatibility allowlists reject unsupported or spoofed files.
- Safe download responses stream files with sanitized `Content-Disposition` filenames and `X-Content-Type-Options: nosniff`.
- Atomic deletion ensures authorization precedes any mutation, file and DB record are removed safely, and Spatie Activitylog audit evidence is recorded with complete metadata.
- Controller validation is extracted into dedicated FormRequests (`ListAttachmentRequest`, `StoreAttachmentRequest`), keeping `AttachmentController` thin.
- Notification handling enforces user isolation, normalizes notification types and target references, guarantees cross-user dedupe independence, and ignores unauthenticated/untrusted payload user IDs.

---

## 2. Exact Files Changed

### Backend & Service Implementation:
- `laravel/app/Application/Attachments/AttachmentService.php`: Added extension allowlist validation, strict extension-to-MIME compatibility map, filename and display name traversal sanitization, private storage path generation, path safety validation on download and delete, download `X-Content-Type-Options: nosniff` header, and atomic delete with Spatie Activitylog audit logging.
- `laravel/app/Application/Notifications/NotificationService.php`: Added user ID validation, type and target reference normalization and length capping, and user-scoped deterministic dedupe resolution.
- `laravel/app/Http/Controllers/AttachmentController.php`: Refactored `index` and `store` to use dedicated FormRequests, keeping controller thin and service-driven.
- `laravel/app/Http/Controllers/NotificationController.php`: Enforced session user identifier usage for `index`, `markRead`, and `markAllRead`, ignoring any request payload `user_id`.
- `laravel/app/Http/Requests/Attachments/ListAttachmentRequest.php`: FormRequest for attachment listing validation.
- `laravel/app/Http/Requests/Attachments/StoreAttachmentRequest.php`: FormRequest for attachment upload validation.

### Feature & Regression Tests:
- `laravel/tests/Feature/AttachmentAndNotificationTest.php`: Extended with 21 comprehensive feature and security hardening tests covering path traversal sanitization, extension/MIME rejection, private disk configuration, authorized download/delete, Spatie Activitylog audit evidence, cross-user notification read isolation, cross-user dedupe independence, and request user ID tampering prevention.
- `laravel/tests/Feature/SecurityHardeningTest.php`: Extended to 38 tests (969 assertions) verifying private attachment disk configuration, disabled local serving, and route authorization allowlist classification.

### Documentation:
- `PHASE_17_SECURITY_ACCESS_GOVERNANCE.md`: Updated Slice 5 status to COMPLETE.
- `IMPLEMENTATION_STATUS.md`: Updated Phase 17 status and summary.
- `NEXT_TASKS.md`: Updated active milestone and next steps.
- `CONTINUE_HERE.md`: Updated handoff context.
- `CHANGELOG.md`: Added Slice 5 entry.
- `PHASE_17_SLICE_5_REPORT.md`: Created Slice 5 report.

---

## 3. Attachment Hardening Changes

1. **Path Traversal & Filename Sanitization:**
   - Upload filenames and display names are sanitized: null bytes (`\0`), carriage returns, newlines, and control characters are stripped.
   - Directory separators (`/`, `\`) and traversal tokens (`..`) are removed.
   - Stored path structure is generated exclusively server-side: `attachments/{safeEntityType}/{safeEntityId}/{uuid}-{safeOriginalName}`.
   - Download response enforces `$this->validateSafePath($attachment->file_ref)` to prevent directory traversal or access outside the `attachments/` prefix.

2. **Extension & MIME Validation:**
   - Rejects empty extensions and unlisted extensions against `erp_attachments.allowed_extensions`.
   - Rejects MIME types outside `erp_attachments.allowed_mimes`.
   - Enforces an explicit `EXTENSION_MIME_MAP` to prevent extension/MIME mismatches (e.g. executable spoofing as PDF or image).

3. **Private Storage & Direct-Serve Prevention:**
   - Attachment disk is configured to `'local'` mapping to `storage/app/private`.
   - `FILESYSTEM_LOCAL_SERVE` defaults to `false`.
   - Attachments are never written to the `public` disk or served via direct public symlinks.

4. **Deletion Integrity & Audit Evidence:**
   - Authorizes deletion before any file or database record deletion.
   - Wrapped in a database transaction that deletes the database row, records Spatie Activitylog evidence (`attachment.delete` with `attachment_id`, `name`, `mime`, `size`, `entity_type`, `entity_id`, `actor_id`), and deletes the storage file.

5. **Controller Cleanliness:**
   - Validation extracted into `ListAttachmentRequest` and `StoreAttachmentRequest`.
   - `AttachmentController` stays thin and delegates all business logic to `AttachmentService`.

---

## 4. Notification Hardening Changes

1. **User Isolation & Payload Tampering Resistance:**
   - `NotificationController` retrieves the user ID exclusively from the authenticated session (`(int) $request->user()->getAuthIdentifier()`).
   - Request payload fields (such as `user_id`) are completely ignored.
   - `markRead` and `markAllRead` only mutate notifications belonging to the session user.

2. **Deduplication & Service Safety:**
   - `NotificationService::create` validates `$userId > 0`, normalizes and truncates `$type` (max 100 chars) and `$targetRef` (max 255 chars).
   - Dedupe keys are scoped deterministically per user; one user's dedupe key cannot suppress another user's notifications.

3. **Audit & Route Classification:**
   - Preserved service-authorized allowlist classification for notifications and attachments routes.
   - All audit evidence uses Spatie Activitylog without sensitive payloads.

---

## 5. Route Authorization Impact

The strict route authorization audit remains 100% green with 0 failing routes:
```
Mini ERP - Route Authorization Audit
Total routes scanned: 457

+----------------------------------+-------+
| Category                         | Count |
+----------------------------------+-------+
| Explicitly Authorized            | 441   |
| Service Authorized (Allowlisted) | 9     |
| Public                           | 5     |
| Guest                            | 2     |
| Failing                          | 0     |
+----------------------------------+-------+
```
Service-authorized allowlist routes:
- `attachments.index`
- `attachments.store`
- `attachments.show`
- `attachments.destroy`
- `notifications`
- `notifications.read_all`
- `notifications.read`
- `logout`
- `foundation`

---

## 6. Audit Evidence Behavior

- `attachment.upload`: Recorded with `attachment_id`, `name`, `mime`, `size`, `entity_type`, `entity_id`, and `actor_id`.
- `attachment.delete`: Recorded with `attachment_id`, `name`, `mime`, `size`, `entity_type`, `entity_id`, and `actor_id`.
- All audit entries are written to Spatie Activitylog (`activity_log` table) via `AuditLogger`.

---

## 7. Verification Results

| Command | Result | Details |
|---|---|---|
| `vendor/bin/pint --test` | PASSED | Clean formatting across all files. |
| `php artisan test --filter=AttachmentAndNotificationTest --compact` | PASSED | 21 tests, 75 assertions passed in 6.11s. |
| `php artisan test --filter=M9AttachmentsAndNotificationsTest --compact` | PASSED | 13 tests, 52 assertions passed in 19.37s. |
| `php artisan test --filter=SecurityHardeningTest --compact` | PASSED | 38 tests, 969 assertions passed in 30.51s. |
| `php artisan security:route-audit --strict` | PASSED | 457 routes scanned, 0 failing routes. |
| `npm run typecheck` | PASSED | TypeScript typecheck 0 errors. |
| `npm run build` | SKIPPED | Slice 5 stayed backend/test/docs only; no frontend files changed. |

---

## 8. No-Scope Scan Result

Automated scan across all Slice 5 files for forbidden scope terms:
- `company_id`: 0 matches
- `tenant_id`: 0 matches
- `currentCompany`: 0 matches
- `currentTenant`: 0 matches
- Spatie Teams: 0 matches

---

## 9. UI Scan Result

Frontend TSX files were not modified during Slice 5. `AttachmentPanel.tsx` was inspected and verified to already comply with all rules (dictionary-backed text, no native select/date inputs, no `dangerouslySetInnerHTML`, no `window.location.href`). Frontend build was skipped because Slice 5 remained backend/test/docs only.

---

## 10. Remaining Risks & Optional Integrations

- **Malware Scanning:** External malware scanning (e.g. ClamAV daemon) is not bundled and remains documented as an optional deployment-level infrastructure integration.
- **Next Step:** Ready for Phase 17 Slice 6 (Final Security Close-Out, Source Scans, and Documentation Sync). Stop here as required by prompt; do not start Slice 6.
