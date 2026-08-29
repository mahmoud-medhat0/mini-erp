# Mini ERP - Phase 17 Slice 3 Agy Prompt

Implement ONLY Phase 17 Slice 3: Password Policy and Session Safety Configuration Hardening.

Goal: make password creation/update rules and session safety settings explicit, configurable, tested, and documented without adding MFA or external identity providers.

Scope:

- add a small `config/erp_security.php` or extend existing `config/security.php`
- centralize password rules in a reusable request/helper
- apply to user creation/update flows
- keep login behavior stable
- document session cookie expectations and config sanity checks
- add tests for minimum length, common weak password rejection if implemented, confirmation behavior if existing UI supports it, session regeneration on login, logout invalidation, and no plaintext password exposure
- no UI visible text unless dictionary-backed
- no tenant/company/branch scope
- final report: `PHASE_17_SLICE_3_REPORT.md`

Required verification:

```powershell
vendor/bin/pint --test
php artisan test --filter=AuthenticationTest --compact
php artisan test --filter=SecurityHardeningTest --compact
php artisan test --filter=SettingsActionsTest --compact
npm run typecheck
npm run build
```

Stop after this slice.
