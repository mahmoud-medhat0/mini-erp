# Mini ERP - Phase 17 Slice 2 Agy Prompt

Implement ONLY Phase 17 Slice 2: Route Authorization Audit Command and Regression Guard.

Build a defensive, read-only route authorization audit that helps operators and developers verify that protected routes have explicit authorization middleware or a documented service-level entity authorizer.

Do not change business behavior unless a missing route guard is proven and covered by tests.

Key requirements:

- no migrations unless strictly required, expected migrations: 0
- no tenant/company scope
- no Branch security scope
- create a read-only Artisan command such as `security:route-audit`
- inspect Laravel route collection
- classify public, guest, auth-only-allowed, explicitly-authorized, and failing routes
- failing routes must return non-zero exit code in strict mode
- add tests covering command output, strict failure behavior, and current route allowlist
- update `spec/SECURITY.md`, `IMPLEMENTATION_STATUS.md`, `NEXT_TASKS.md`, `CONTINUE_HERE.md`, and `CHANGELOG.md`
- final report: `PHASE_17_SLICE_2_REPORT.md`

Required verification:

```powershell
php artisan security:route-audit
php artisan security:route-audit --strict
vendor/bin/pint --test
php artisan test --filter=SecurityHardeningTest --compact
npm run typecheck
```

Stop after this slice.
