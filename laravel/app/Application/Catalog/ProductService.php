<?php

namespace App\Application\Catalog;

use App\Domain\Audit\AuditLogger;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Support\Concurrency\OptimisticLock;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public const ALLOWED_TYPES = ['stock', 'service', 'non_stock'];

    public const ALLOWED_STATUSES = ['active', 'inactive'];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OptimisticLock $optimisticLock,
    ) {}

    public function create(array $data, int|string|null $actorId = null): Product
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));

        if ($code === '') {
            throw ValidationException::withMessages([
                'code' => ['Product code / SKU is required.'],
            ]);
        }

        if (Product::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => ["Product code / SKU [{$code}] already exists."],
            ]);
        }

        $type = $data['type'] ?? 'stock';
        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            throw ValidationException::withMessages([
                'type' => ["Invalid product type [{$type}]. Allowed types: ".implode(', ', self::ALLOWED_TYPES)],
            ]);
        }

        $status = $data['status'] ?? 'active';
        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ["Invalid product status [{$status}]. Allowed statuses: ".implode(', ', self::ALLOWED_STATUSES)],
            ]);
        }

        $uomId = $data['unit_of_measure_id'] ?? null;
        if (! $uomId) {
            throw ValidationException::withMessages([
                'unit_of_measure_id' => ['Unit of Measure is required.'],
            ]);
        }

        /** @var UnitOfMeasure|null $uom */
        $uom = UnitOfMeasure::query()->find($uomId);
        if (! $uom || ! $uom->is_active) {
            throw ValidationException::withMessages([
                'unit_of_measure_id' => ['Selected Unit of Measure is invalid or inactive.'],
            ]);
        }

        $categoryId = $data['product_category_id'] ?? null;
        if ($categoryId) {
            /** @var ProductCategory|null $category */
            $category = ProductCategory::query()->find($categoryId);
            if (! $category || ! $category->is_active) {
                throw ValidationException::withMessages([
                    'product_category_id' => ['Selected Product Category is invalid or inactive.'],
                ]);
            }
        }

        $product = Product::query()->create([
            'code' => $code,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $type,
            'unit_of_measure_id' => $uomId,
            'product_category_id' => $categoryId,
            'status' => $status,
            'is_sales_enabled' => $data['is_sales_enabled'] ?? true,
            'is_purchase_enabled' => $data['is_purchase_enabled'] ?? true,
            'created_by' => $actorId,
            'updated_by' => $actorId,
            'lock_version' => 1,
        ]);

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'create',
            entityType: 'product',
            entityId: $product->id,
            before: null,
            after: $product->fresh(['unitOfMeasure', 'category'])->toArray(),
        );

        return $product;
    }

    public function update(string $id, array $data, int|string|null $actorId = null): Product
    {
        /** @var Product $product */
        $product = Product::query()->findOrFail($id);
        $before = $product->fresh(['unitOfMeasure', 'category'])->toArray();

        if (isset($data['lock_version'])) {
            $this->optimisticLock->verify($product, (int) $data['lock_version']);
        }

        if (isset($data['code'])) {
            $code = strtoupper(trim((string) $data['code']));
            if ($code !== $product->code && Product::query()->where('code', $code)->where('id', '!=', $id)->exists()) {
                throw ValidationException::withMessages([
                    'code' => ["Product code / SKU [{$code}] already exists."],
                ]);
            }
            $product->code = $code;
        }

        if (isset($data['name'])) {
            $product->name = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $product->description = $data['description'];
        }

        if (isset($data['type'])) {
            if (! in_array($data['type'], self::ALLOWED_TYPES, true)) {
                throw ValidationException::withMessages([
                    'type' => ["Invalid product type [{$data['type']}]. Allowed types: ".implode(', ', self::ALLOWED_TYPES)],
                ]);
            }
            $product->type = $data['type'];
        }

        if (isset($data['status'])) {
            if (! in_array($data['status'], self::ALLOWED_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'status' => ["Invalid product status [{$data['status']}]. Allowed statuses: ".implode(', ', self::ALLOWED_STATUSES)],
                ]);
            }
            $product->status = $data['status'];
        }

        if (isset($data['unit_of_measure_id'])) {
            /** @var UnitOfMeasure|null $uom */
            $uom = UnitOfMeasure::query()->find($data['unit_of_measure_id']);
            if (! $uom || ! $uom->is_active) {
                throw ValidationException::withMessages([
                    'unit_of_measure_id' => ['Selected Unit of Measure is invalid or inactive.'],
                ]);
            }
            $product->unit_of_measure_id = $data['unit_of_measure_id'];
        }

        if (array_key_exists('product_category_id', $data)) {
            $categoryId = $data['product_category_id'];
            if ($categoryId) {
                /** @var ProductCategory|null $category */
                $category = ProductCategory::query()->find($categoryId);
                if (! $category || ! $category->is_active) {
                    throw ValidationException::withMessages([
                        'product_category_id' => ['Selected Product Category is invalid or inactive.'],
                    ]);
                }
            }
            $product->product_category_id = $categoryId;
        }

        if (isset($data['is_sales_enabled'])) {
            $product->is_sales_enabled = (bool) $data['is_sales_enabled'];
        }

        if (isset($data['is_purchase_enabled'])) {
            $product->is_purchase_enabled = (bool) $data['is_purchase_enabled'];
        }

        $product->updated_by = $actorId;
        $product->lock_version = ((int) $product->lock_version) + 1;
        $product->save();

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'update',
            entityType: 'product',
            entityId: $product->id,
            before: $before,
            after: $product->fresh(['unitOfMeasure', 'category'])->toArray(),
        );

        return $product;
    }

    public function delete(string $id, int|string|null $actorId = null): void
    {
        /** @var Product $product */
        $product = Product::query()->findOrFail($id);
        $before = $product->fresh(['unitOfMeasure', 'category'])->toArray();

        $product->delete();

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'delete',
            entityType: 'product',
            entityId: $id,
            before: $before,
            after: null,
        );
    }
}
