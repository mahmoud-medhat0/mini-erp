<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Concerns\AuthorizesSettingsManagement;
use App\Http\Controllers\Concerns\ResolvesLocalizedModelFields;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Currency;
use App\Support\Concurrency\OptimisticLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingsController extends Controller
{
    use AuthorizesSettingsManagement;
    use ResolvesLocalizedModelFields;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OptimisticLock $optimisticLock,
    ) {}

    public function index(Request $request): Response
    {
        $locale = $this->locale($request);

        return Inertia::render('Settings/Company', [
            'currencies' => Currency::query()
                ->orderBy('code')
                ->get()
                ->map(fn (Currency $currency): array => [
                    'code' => $currency->code,
                    'name' => $this->modelTranslation($currency, 'name', $locale),
                    'symbol' => $currency->symbol,
                ])
                ->values(),
            'companies' => Company::query()
                ->orderBy('created_at')
                ->get()
                ->map(fn (Company $company): array => [
                    'id' => $company->id,
                    'name' => $this->modelTranslation($company, 'name', $locale),
                    'nameEn' => $this->modelTranslation($company, 'name', 'en'),
                    'nameAr' => $this->modelTranslation($company, 'name', 'ar'),
                    'baseCurrency' => $company->base_currency,
                    'lockVersion' => (int) $company->lock_version,
                    'createdAt' => optional($company->created_at)->toIso8601String(),
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.company');

        $validated = $this->validateCompany($request);
        $id = $this->createCompany($validated);

        $this->auditLogger->record($request->user()->id, 'company.create', 'company', $id, after: $validated);

        return back()->with('success', __('Company saved.'));
    }

    public function update(Request $request, ?string $companyId = null): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.company');

        $validated = $this->validateCompany($request, includeLockVersion: true);
        $companyRecord = $companyId
            ? DB::table('company')->where('id', $companyId)->first()
            : DB::table('company')->orderBy('created_at')->first();

        if (! $companyRecord) {
            $companyId = $this->createCompany($validated);

            $this->auditLogger->record($request->user()->id, 'company.create', 'company', $companyId, after: $validated);

            return back()->with('success', __('Company created.'));
        }

        $before = (array) $companyRecord;
        $companyId = (string) $companyRecord->id;
        $existingSettings = json_decode($companyRecord->settings_json ?? '{}', true) ?: [];
        $newSettings = array_merge($existingSettings, array_filter($this->settingsArray($validated), fn ($val) => $val !== null));
        $payload = [
            'name' => $this->companyNameJson($validated),
            'base_currency' => $validated['base_currency'],
            'settings_json' => json_encode($newSettings, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ];

        if (isset($validated['lock_version'])) {
            $this->optimisticLock->update('company', ['id' => $companyId], (int) $validated['lock_version'], $payload);
        } else {
            DB::table('company')->where('id', $companyId)->update($payload);
        }

        $this->auditLogger->record($request->user()->id, 'company.update', 'company', $companyId, before: $before, after: $validated);

        return back()->with('success', __('Company saved.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCompany(Request $request, bool $includeLockVersion = false): array
    {
        $rules = [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'base_currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'fiscal_year_start_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ];

        if ($includeLockVersion) {
            $rules['lock_version'] = ['nullable', 'integer', 'min:0'];
        }

        return $request->validate($rules);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function settingsArray(array $validated): array
    {
        return [
            'legal_name' => $validated['legal_name'] ?? null,
            'tax_number' => $validated['tax_number'] ?? null,
            'registration_number' => $validated['registration_number'] ?? null,
            'fiscal_year_start_month' => $validated['fiscal_year_start_month'] ?? 1,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'website' => $validated['website'] ?? null,
            'address' => $validated['address'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createCompany(array $validated): string
    {
        $id = (string) Str::uuid();

        DB::table('company')->insert([
            'id' => $id,
            'name' => $this->companyNameJson($validated),
            'base_currency' => $validated['base_currency'],
            'settings_json' => $this->settingsJson($validated),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function settingsJson(array $validated): string
    {
        return json_encode($this->settingsArray($validated), JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function companyNameJson(array $validated): string
    {
        return json_encode(['en' => $validated['name_en'], 'ar' => $validated['name_ar']], JSON_THROW_ON_ERROR);
    }
}
