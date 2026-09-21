<?php

namespace App\Application\Equipment;

use App\Domain\Audit\AuditLogger;
use App\Models\ToolCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class ToolCategoryService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');

        $query = ToolCategory::query()
            ->withCount('tools')
            ->select('tool_category.*')
            ->when($status === 'active', fn ($q) => $q->where('tool_category.is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('tool_category.is_active', false));

        return DataTables::of($query)
            ->addColumn('name_text', fn (ToolCategory $row) => is_array($row->name) ? ($row->name['en'] ?? '') : (string) $row->name)
            ->filterColumn('name_text', fn ($q, $kw) => $q->where(function ($q2) use ($kw): void {
                $q2->where('tool_category.name->en', 'like', "%{$kw}%")
                    ->orWhere('tool_category.name->ar', 'like', "%{$kw}%");
            }))
            ->make(true);
    }

    public function create(array $data, ?int $actorId = null): ToolCategory
    {
        return DB::transaction(function () use ($data, $actorId): ToolCategory {
            $payload = $this->validatedPayload($data);

            /** @var ToolCategory $category */
            $category = ToolCategory::query()->create([
                ...$payload,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->auditLogger->record($actorId, 'tool_category.create', 'tool_category', $category->id, after: $category->fresh()->toArray());

            return $category;
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): ToolCategory
    {
        return DB::transaction(function () use ($id, $data, $actorId): ToolCategory {
            /** @var ToolCategory $category */
            $category = ToolCategory::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== (int) $category->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The tool category was modified by another user. Please refresh and try again.')]]);
            }

            $before = $category->fresh()->toArray();
            $payload = $this->validatedPayload([
                'code' => $data['code'] ?? $category->code,
                'name' => $data['name'] ?? $category->getTranslations('name'),
                'is_active' => array_key_exists('is_active', $data) ? $data['is_active'] : $category->is_active,
            ], $category->id);

            $category->update([
                ...$payload,
                'updated_by' => $actorId,
                'lock_version' => ((int) $category->lock_version) + 1,
            ]);

            $this->auditLogger->record($actorId, 'tool_category.update', 'tool_category', $category->id, before: $before, after: $category->fresh()->toArray());

            return $category->fresh();
        });
    }

    public function delete(string $id, ?int $actorId = null): void
    {
        DB::transaction(function () use ($id, $actorId): void {
            /** @var ToolCategory $category */
            $category = ToolCategory::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($category->tools()->exists()) {
                throw ValidationException::withMessages(['tool_category' => [__('Tool categories with linked tools cannot be deleted.')]]);
            }

            $before = $category->toArray();
            $category->delete();
            $this->auditLogger->record($actorId, 'tool_category.delete', 'tool_category', $id, before: $before);
        });
    }

    private function validatedPayload(array $data, ?string $ignoreId = null): array
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '' || ! preg_match('/^[A-Z0-9._-]+$/', $code)) {
            throw ValidationException::withMessages(['code' => [__('Tool category code is required and may contain letters, numbers, dots, underscores, or dashes.')]]);
        }

        $exists = ToolCategory::query()
            ->where('code', $code)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['code' => [__('Tool category code already exists.')]]);
        }

        $translations = is_array($data['name'] ?? null) ? $data['name'] : [];
        $en = trim((string) ($translations['en'] ?? ''));
        $ar = trim((string) ($translations['ar'] ?? $en));
        if ($en === '') {
            throw ValidationException::withMessages(['name.en' => [__('English tool category name is required.')]]);
        }

        return [
            'code' => $code,
            'name' => ['en' => $en, 'ar' => $ar === '' ? $en : $ar],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
