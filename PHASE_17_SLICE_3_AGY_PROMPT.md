# Mini ERP - Phase 17 Slice 3 Agy Prompt

You are working in the existing Mini ERP Laravel repository.

Implement ONLY Phase 17 Slice 3: Password Policy and Session Safety Configuration Hardening.

## Objective

Make user password creation/update policy explicit, centralized, configurable, and tested. Verify the existing session login/logout safety behavior without changing login semantics.

Do not add MFA, external identity providers, password reset UI, email delivery, or deployment automation in this slice.

## Non-Negotiable Rules

- Expected migrations: 0.
- Do not add `company_id`, `tenant_id`, `currentCompany`, `currentTenant`, Spatie Teams, company-user membership, or tenant scope.
- Do not treat Branch as tenant, login context, user scope, or blanket security boundary.
- Do not change login credential validation rules beyond preserving current behavior.
- Do not use Laravel `Password::uncompromised()` or any external/network password reputation check.
- Do not add frontend UI unless absolutely required. If TSX changes are required, every visible string must use EN/AR dictionaries.
- Keep `UserSettingsController` thin. Move validation into FormRequest classes or a reusable support object.
- Do not print or commit real secrets.

## Required Implementation

1. Extend `config/security.php` with a `password_policy` block:
   - `min_length`: env `ERP_PASSWORD_MIN_LENGTH`, default `12`
   - `max_length`: env `ERP_PASSWORD_MAX_LENGTH`, default `128`
   - `mixed_case`: env `ERP_PASSWORD_REQUIRE_MIXED_CASE`, default `true`
   - `letters`: env `ERP_PASSWORD_REQUIRE_LETTERS`, default `true`
   - `numbers`: env `ERP_PASSWORD_REQUIRE_NUMBERS`, default `true`
   - `symbols`: env `ERP_PASSWORD_REQUIRE_SYMBOLS`, default `true`
2. Create a reusable password rule factory/support class, for example:
   - `App\Support\Security\PasswordPolicyRules`
   - returns Laravel validation rules using `Illuminate\Validation\Rules\Password`
   - includes max length and string rules
   - does not use network-based uncompromised checks
3. Refactor settings user validation:
   - create `App\Http\Requests\Settings\StoreUserRequest`
   - create `App\Http\Requests\Settings\UpdateUserRequest`
   - use `authorize()` or keep existing controller authorization, but do not weaken `users.configure`
   - apply password policy on user create
   - apply password policy on user update only when a password value is present
   - preserve optional password update behavior
4. Preserve `BootstrapUserSeeder` behavior. Its default `Password123!` must continue to pass the new default policy.
5. Add non-secret `.env.example` entries:
   - `ERP_PASSWORD_MIN_LENGTH=12`
   - `ERP_PASSWORD_MAX_LENGTH=128`
   - `ERP_PASSWORD_REQUIRE_MIXED_CASE=true`
   - `ERP_PASSWORD_REQUIRE_LETTERS=true`
   - `ERP_PASSWORD_REQUIRE_NUMBERS=true`
   - `ERP_PASSWORD_REQUIRE_SYMBOLS=true`
6. Update docs:
   - `spec/SECURITY.md`
   - `spec/ENVIRONMENT_CHECKLIST.md`
   - `PHASE_17_SECURITY_ACCESS_GOVERNANCE.md`
   - `IMPLEMENTATION_STATUS.md`
   - `NEXT_TASKS.md`
   - `CONTINUE_HERE.md`
   - `CHANGELOG.md`
7. Create `PHASE_17_SLICE_3_REPORT.md`.

## Required Tests

Add/extend tests in a focused way. Use existing feature tests where appropriate.

Minimum coverage:

- creating a user with a password shorter than configured min length is rejected
- creating a user without letters is rejected when `letters=true`
- creating a user without numbers is rejected when `numbers=true`
- creating a user without symbols is rejected when `symbols=true`
- creating a user with a strong default-compliant password succeeds
- updating a user with an empty password preserves the existing password hash
- updating a user with a weak provided password is rejected
- updating a user with a strong provided password changes the password hash
- `BootstrapUserSeeder` default password still passes policy and creates a usable login
- login still regenerates session
- logout still invalidates session
- password hashes are not stored as plaintext
- no tenant/company/branch security scope is introduced

## Required Verification Commands

Run from `laravel/` and report exact results:

```powershell
vendor/bin/pint --test
php artisan test --filter=AuthenticationTest --compact
php artisan test --filter=SecurityHardeningTest --compact
php artisan test --filter=SettingsActionsTest --compact
php artisan security:route-audit --strict
npm run typecheck
```

If frontend files are not changed, do not run `npm run build`. If frontend files are changed, `npm run build` is mandatory.

## Required Source Scans

Run and classify:

```powershell
rg -n "company_id|tenant_id|currentCompany|currentTenant|Spatie Teams" laravel/app laravel/config laravel/tests spec/SECURITY.md PHASE_17_SLICE_3_REPORT.md
rg -n "uncompromised|HaveIBeenPwned|pwned|api.pwnedpasswords|curl|Http::" laravel/app laravel/tests PHASE_17_SLICE_3_REPORT.md
rg -n "min:8|'password' => \\['required', 'string', 'min:8'\\]|\\\"password\\\" => \\[\\\"required\\\", \\\"string\\\", \\\"min:8\\\"\\]" laravel/app laravel/tests
```

The first scan may match policy text in documentation/tests only. It must not show an implementation dependency on tenant/company scope.

## Final Report

`PHASE_17_SLICE_3_REPORT.md` must include:

- files changed
- password policy config values and defaults
- validation refactor summary
- session safety verification
- exact test results
- source-scan classification
- whether frontend files changed
- remaining risks

Stop after this slice. Do not start Slice 4.
