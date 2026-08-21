<?php

namespace App\Application\Catalog;

use App\Domain\Audit\AuditLogger;
use App\Models\ProductCategory;
use App\Support\Concurrency\OptimisticLock;
use Illuminate\Validation\ValidationException;

class ProductCategoryService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OptimisticLock $optimisticLock,
    ) {}

    public function create(array $data, int|string|null $actorId = null): ProductCategory
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));

        if ($code === '') {
            throw ValidationException::withMessages([
                'code' => ['Product category code is required.'],
            ]);
        }

        if (ProductCategory::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => ["Product category code [{$code}] already exists."],
            ]);
        }

        $category = ProductCategory::query()->create([
            'code' => $code,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $actorId,
            'updated_by' => $actorId,
            'lock_version' => 1,
        ]);

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'create',
            entityType: 'product_category',
            entityId: $category->id,
            before: null,
            after: $category->fresh()->toArray(),
        );

        return $category;
    }

    public function update(string $id, array $data, int|string|null $actorId = null): ProductCategory
    {
        /** @var ProductCategory $category */
        $category = ProductCategory::query()->findOrFail($id);
        $before = $category->toArray();

        if (isset($data['lock_version'])) {
            $this->optimisticLock->verify($category, (int) $data['lock_version']);
        }

        if (isset($data['code'])) {
            $code = strtoupper(trim((string) $data['code']));
            if ($code !== $category->code && ProductCategory::query()->where('code', $code)->where('id', '!=', $id)->exists()) {
                throw ValidationException::withMessages([
                    'code' => ["Product category code [{$code}] already exists."],
                ]);
            }
            $category->code = $code;
        }

        if (isset($data['name'])) {
            $category->name = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $category->description = $data['description'];
        }

        if (isset($data['is_active'])) {
            $category->is_active = (bool) $data['is_active'];
        }

        $category->updated_by = $actorId;
        $category->lock_version = ((int) $category->lock_version) + 1;
        $category->save();

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'update',
            entityType: 'product_category',
            entityId: $category->id,
            before: $before,
            after: $category->fresh()->toArray(),
        );

        return $category;
    }

    public function delete(string $id, int|string|null $actorId = null): void
    {
        /** @var ProductCategory $category */
        $category = ProductCategory::query()->findOrFail($id);

        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'id' => ["Cannot delete Product Category [{$category->code}] because it is referenced by existing products."],
            ]);
        }

        $before = $category->toArray();
        $category->delete();

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'delete',
            entityType: 'product_category',
            entityId: $id,
            before: $before,
            after: null,
        );
    }
}
