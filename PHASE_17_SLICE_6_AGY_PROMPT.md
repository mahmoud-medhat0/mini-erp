# Mini ERP - Phase 17 Slice 6 Agy Prompt

Implement ONLY Phase 17 Slice 6: Security Close-Out and Final Verification.

This is a close-out slice. Do not add new product features.

Required:

- create `PHASE_17_FINAL_VERIFICATION_REPORT.md`
- verify all Phase 17 reports exist and summarize each slice
- run source scans for no multi-tenant scope, no native controls if frontend changed, no unsafe redirects, no raw secret values in docs/templates
- run route authorization audit command if Slice 2 exists
- run targeted security/auth/attachment/Phase16 regression tests
- run Pint, TypeScript, and Vite build
- update `PHASE_17_SECURITY_ACCESS_GOVERNANCE.md`, `IMPLEMENTATION_STATUS.md`, `NEXT_TASKS.md`, `CONTINUE_HERE.md`, and `CHANGELOG.md`

Required verification:

```powershell
php artisan migrate:status
vendor/bin/pint --test
php artisan test --filter=AuthenticationTest --compact
php artisan test --filter=SecurityHardeningTest --compact
php artisan test --filter=AttachmentAndNotificationTest --compact
php artisan test --filter=Phase16 --compact
php artisan test --testsuite=Concurrency --compact
npm run typecheck
npm run build
git diff --check
```

Stop after the close-out report.
