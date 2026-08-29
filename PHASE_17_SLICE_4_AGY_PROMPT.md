# Mini ERP - Phase 17 Slice 4 Agy Prompt

Implement ONLY Phase 17 Slice 4: Sensitive Financial Action Confirmation and Audit Evidence Hardening.

Goal: ensure high-impact financial actions have explicit server-side confirmation, reason capture where appropriate, permission gates, and Spatie Activitylog evidence.

Do not change posting math or accounting results.

Targets to review:

- journal posting/reversal
- period close/reopen
- tax filing
- fixed asset depreciation/disposal posting/reversal
- payroll posting
- inventory stock adjustment/posting flows
- budget activation/archiving/cancellation

Rules:

- no migrations unless a missing audit reason field is genuinely required
- no tenant/company scope
- no Branch security scope
- backend is authoritative
- UI confirmation text must be dictionary-backed
- preserve idempotency and concurrency behavior
- final report: `PHASE_17_SLICE_4_REPORT.md`

Required verification:

```powershell
vendor/bin/pint --test
php artisan test --filter=Phase15ProductHardeningTest --compact
php artisan test --filter=SecurityHardeningTest --compact
php artisan test --testsuite=Concurrency --compact
npm run typecheck
npm run build
```

Stop after this slice.
