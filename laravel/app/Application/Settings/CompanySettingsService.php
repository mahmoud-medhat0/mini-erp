<?php

namespace App\Application\Settings;

use App\Domain\Audit\AuditLogger;
use App\Models\Company;
use App\Models\Currency;
use App\Models\User;
use App\Support\Concurrency\OptimisticLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CompanySettingsService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OptimisticLock $optimisticLock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexData(?User $user): array
    {
        $locale = $this->locale($user);

        return [
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
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, int $actorId): string
    {
        if (Company::query()->exists()) {
            throw ValidationException::withMessages([
                'company' => __('The business profile is already configured. Edit the existing profile instead.'),
            ]);
        }

        $id = $this->insertCompany($validated);

        $this->auditLogger->record($actorId, 'company.create', 'company', $id, after: $validated);

        return $id;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{id: string, created: bool}
     */
    public function update(?string $companyId, array $validated, int $actorId): array
    {
        $companyRecord = $companyId
            ? DB::table('company')->where('id', $companyId)->first()
            : DB::table('company')->orderBy('created_at')->first();

        if (! $companyRecord) {
            return [
                'id' => $this->create($validated, $actorId),
                'created' => true,
            ];
        }

        $before = (array) $companyRecord;
        $companyId = (string) $companyRecord->id;
        $existingSettings = json_decode($companyRecord->settings_json ?? '{}', true) ?: [];
        $newSettings = array_merge($existingSettings, array_filter($this->settingsArray($validated), fn ($value) => $value !== null));
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

        $this->auditLogger->record($actorId, 'company.update', 'company', $companyId, before: $before, after: $validated);

        return [
            'id' => $companyId,
            'created' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function insertCompany(array $validated): string
    {
        $id = (string) Str::uuid();

        DB::table('company')->insert([
            'id' => $id,
            'name' => $this->companyNameJson($validated),
            'base_currency' => $validated['base_currency'],
            'settings_json' => json_encode($this->settingsArray($validated, includeDefaults: true), JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function settingsArray(array $validated, bool $includeDefaults = false): array
    {
        return [
            'legal_name' => $validated['legal_name'] ?? null,
            'tax_number' => $validated['tax_number'] ?? null,
            'registration_number' => $validated['registration_number'] ?? null,
            'fiscal_year_start_month' => $validated['fiscal_year_start_month'] ?? ($includeDefaults ? 1 : null),
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'website' => $validated['website'] ?? null,
            'address' => $validated['address'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function companyNameJson(array $validated): string
    {
        return json_encode(['en' => $validated['name_en'], 'ar' => $validated['name_ar']], JSON_THROW_ON_ERROR);
    }

    private function locale(?User $user): string
    {
        return $user?->locale === 'ar' || app()->getLocale() === 'ar' ? 'ar' : 'en';
    }

    private function modelTranslation(object $model, string $field, string $locale): string
    {
        if (method_exists($model, 'getTranslation')) {
            try {
                $value = $model->getTranslation($field, $locale, false);

                if (is_string($value) && $value !== '') {
                    return $value;
                }
            } catch (Throwable) {
                //
            }
        }

        return $this->translationFromJson($model->{$field} ?? null, $locale);
    }

    private function translationFromJson(mixed $value, string $locale): string
    {
        if (is_array($value)) {
            return (string) ($value[$locale] ?? $value['en'] ?? reset($value) ?: '');
        }

        if (! is_string($value) || $value === '') {
            return '';
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return (string) ($decoded[$locale] ?? $decoded['en'] ?? reset($decoded) ?: '');
        }

        return $value;
    }
}
