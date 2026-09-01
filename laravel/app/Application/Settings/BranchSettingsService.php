<?php

namespace App\Application\Settings;

use App\Domain\Audit\AuditLogger;
use App\Models\Branch;
use App\Models\User;
use App\Support\Concurrency\OptimisticLock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BranchSettingsService
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
            'branches' => Branch::query()
                ->orderBy('code')
                ->get()
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'code' => $branch->code,
                    'name' => $this->modelTranslation($branch, 'name', $locale),
                    'nameEn' => $this->modelTranslation($branch, 'name', 'en'),
                    'nameAr' => $this->modelTranslation($branch, 'name', 'ar'),
                    'isActive' => $branch->is_active,
                    'lockVersion' => (int) $branch->lock_version,
                ])
                ->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, bool $isActive, int $actorId): string
    {
        $id = (string) Str::uuid();
        $payload = [
            'id' => $id,
            'code' => $validated['code'],
            'name' => $this->branchNameJson($validated),
            'is_active' => $isActive,
            'lock_version' => 0,
        ];

        DB::table('branch')->insert($payload);

        $this->auditLogger->record($actorId, 'branch.create', 'branch', $id, after: $validated);

        return $id;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(string $branchId, array $validated, ?bool $isActive, int $actorId): void
    {
        $before = (array) DB::table('branch')->where('id', $branchId)->first();

        abort_if($before === [], 404);

        $payload = [
            'code' => $validated['code'],
            'name' => $this->branchNameJson($validated),
        ];

        if ($isActive !== null) {
            $payload['is_active'] = $isActive;
        }

        if (isset($validated['lock_version'])) {
            $this->optimisticLock->update('branch', ['id' => $branchId], (int) $validated['lock_version'], $payload);
        } else {
            DB::table('branch')->where('id', $branchId)->update($payload);
        }

        $this->auditLogger->record($actorId, 'branch.update', 'branch', $branchId, before: $before, after: $validated);
    }

    public function delete(string $branchId, int $actorId): void
    {
        $branch = DB::table('branch')->where('id', $branchId)->first();

        abort_if(! $branch, 404);

        try {
            DB::table('branch')->where('id', $branchId)->delete();
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'branch' => __('This branch is already used by operational or accounting records. Deactivate it instead of deleting it.'),
            ]);
        }

        $this->auditLogger->record($actorId, 'branch.delete', 'branch', $branchId, before: (array) $branch);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function branchNameJson(array $validated): string
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
