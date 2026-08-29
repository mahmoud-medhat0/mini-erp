# Mini ERP - Phase 17 Slice 5 Agy Prompt

Implement ONLY Phase 17 Slice 5: Attachment, Notification, and Private Delivery Safety Hardening.

Goal: strengthen private attachment delivery, safe filenames, MIME/extension checks, owner authorization through entity authorizers, notification user isolation, and audit evidence.

Scope:

- review `AttachmentService`, `AttachmentEntityAuthorizer`, `AttachmentController`, `NotificationService`, `NotificationController`
- add tests for path traversal rejection, unsupported extension rejection, private storage direct-serve policy, cross-user notification access denial, attachment deletion audit, and safe content-disposition behavior
- preserve entity_type/entity_id model
- do not add company/tenant/branch ownership
- no public storage exposure
- no external malware scanning provider in this slice; document as future optional deployment integration
- final report: `PHASE_17_SLICE_5_REPORT.md`

Required verification:

```powershell
vendor/bin/pint --test
php artisan test --filter=AttachmentAndNotificationTest --compact
php artisan test --filter=M9AttachmentsAndNotificationsTest --compact
php artisan test --filter=SecurityHardeningTest --compact
npm run typecheck
```

Stop after this slice.
