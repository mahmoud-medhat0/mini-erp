<?php

namespace App\Application\Rentals;

use App\Application\Support\CurrencyInput;
use App\Domain\Audit\AuditLogger;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\FixedAsset;
use App\Models\Product;
use App\Models\RentableItem;
use App\Models\RentableItemStatusEvent;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RentableItemService
{
    public const ITEM_SOURCES = ['standalone', 'product', 'fixed_asset'];

    public const STATUSES = [
        'available',
        'reserved',
        'allocated',
        'rented',
        'return_pending',
        'returned',
        'damaged',
        'lost',
        'maintenance',
        'retired',
        'inactive',
    ];

    public const CONDITION_STATUSES = ['good', 'fair', 'damaged', 'lost', 'maintenance', 'retired'];

    private const DELETE_BLOCKING_STATUSES = ['reserved', 'allocated', 'rented', 'return_pending'];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function create(array $data, ?int $actorId = null): RentableItem
    {
        return DB::transaction(function () use ($data, $actorId): RentableItem {
            $payload = $this->validatedPayload($data);

            /** @var RentableItem $item */
            $item = RentableItem::query()->create([
                ...$payload,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->recordStatusEvent($item, null, $item->status, 'created', $data['reason'] ?? null, $actorId);
            $this->auditLogger->record($actorId, 'rentable_item.create', 'rentable_item', $item->id, after: $item->fresh($this->relations())->toArray());

            return $item->fresh($this->relations());
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): RentableItem
    {
        return DB::transaction(function () use ($id, $data, $actorId): RentableItem {
            /** @var RentableItem $item */
            $item = RentableItem::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== (int) $item->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The rentable item was modified by another user. Please refresh and try again.')]]);
            }

            $before = $item->fresh($this->relations())->toArray();
            $oldStatus = (string) $item->status;
            $payload = $this->validatedPayload([
                'code' => $data['code'] ?? $item->code,
                'name' => $data['name'] ?? $item->getTranslations('name'),
                'description' => array_key_exists('description', $data) ? $data['description'] : $item->getTranslations('description'),
                'item_source' => $data['item_source'] ?? $item->item_source,
                'product_id' => array_key_exists('product_id', $data) ? $data['product_id'] : $item->product_id,
                'fixed_asset_id' => array_key_exists('fixed_asset_id', $data) ? $data['fixed_asset_id'] : $item->fixed_asset_id,
                'branch_id' => array_key_exists('branch_id', $data) ? $data['branch_id'] : $item->branch_id,
                'warehouse_id' => array_key_exists('warehouse_id', $data) ? $data['warehouse_id'] : $item->warehouse_id,
                'status' => $data['status'] ?? $item->status,
                'condition_status' => $data['condition_status'] ?? $item->condition_status,
                'currency' => $data['currency'] ?? $item->currency,
                'serial_number' => array_key_exists('serial_number', $data) ? $data['serial_number'] : $item->serial_number,
                'replacement_value_minor' => $data['replacement_value_minor'] ?? $item->replacement_value_minor,
                'daily_rate_minor' => array_key_exists('daily_rate_minor', $data) ? $data['daily_rate_minor'] : $item->daily_rate_minor,
                'monthly_rate_minor' => array_key_exists('monthly_rate_minor', $data) ? $data['monthly_rate_minor'] : $item->monthly_rate_minor,
                'deposit_minor' => array_key_exists('deposit_minor', $data) ? $data['deposit_minor'] : $item->deposit_minor,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $item->notes,
                'is_active' => array_key_exists('is_active', $data) ? $data['is_active'] : $item->is_active,
            ], $item->id);

            $item->update([
                ...$payload,
                'updated_by' => $actorId,
                'lock_version' => ((int) $item->lock_version) + 1,
            ]);

            $eventType = $oldStatus !== $item->status ? 'status_changed' : 'details_updated';
            $this->recordStatusEvent($item, $oldStatus, $item->status, $eventType, $data['reason'] ?? null, $actorId);
            $this->auditLogger->record($actorId, 'rentable_item.update', 'rentable_item', $item->id, before: $before, after: $item->fresh($this->relations())->toArray());

            return $item->fresh($this->relations());
        });
    }

    public function delete(string $id, ?int $actorId = null): void
    {
        DB::transaction(function () use ($id, $actorId): void {
            /** @var RentableItem $item */
            $item = RentableItem::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if (in_array($item->status, self::DELETE_BLOCKING_STATUSES, true)) {
                throw ValidationException::withMessages(['rentable_item' => [__('Rentable items in active rental workflow states cannot be deleted.')]]);
            }

            $before = $item->fresh($this->relations())->toArray();
            $item->delete();
            $this->auditLogger->record($actorId, 'rentable_item.delete', 'rentable_item', $id, before: $before);
        });
    }

    private function validatedPayload(array $data, ?string $ignoreId = null): array
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '' || ! preg_match('/^[A-Z0-9._-]+$/', $code)) {
            throw ValidationException::withMessages(['code' => [__('Rentable item code is required and may contain letters, numbers, dots, underscores, or dashes.')]]);
        }

        $exists = RentableItem::query()
            ->where('code', $code)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['code' => [__('Rentable item code already exists.')]]);
        }

        $source = (string) ($data['item_source'] ?? 'standalone');
        if (! in_array($source, self::ITEM_SOURCES, true)) {
            throw ValidationException::withMessages(['item_source' => [__('Invalid rentable item source.')]]);
        }

        $status = (string) ($data['status'] ?? 'available');
        if (! in_array($status, self::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => [__('Invalid rentable item status.')]]);
        }

        $condition = (string) ($data['condition_status'] ?? 'good');
        if (! in_array($condition, self::CONDITION_STATUSES, true)) {
            throw ValidationException::withMessages(['condition_status' => [__('Invalid rentable item condition.')]]);
        }

        $currency = CurrencyInput::required($data['currency'] ?? null);
        if (! Currency::query()->where('code', $currency)->exists()) {
            throw ValidationException::withMessages(['currency' => [__('Selected currency is missing from the currency registry.')]]);
        }

        $productId = $this->nullableUuid($data['product_id'] ?? null, 'product_id');
        $fixedAssetId = $this->nullableUuid($data['fixed_asset_id'] ?? null, 'fixed_asset_id');
        $this->assertSourceReferenceIsValid($source, $productId, $fixedAssetId);

        if ($productId !== null && ! Product::query()->whereKey($productId)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['product_id' => [__('Selected product is inactive or missing.')]]);
        }

        if ($fixedAssetId !== null && FixedAsset::query()->whereKey($fixedAssetId)->where('status', 'disposed')->exists()) {
            throw ValidationException::withMessages(['fixed_asset_id' => [__('Disposed fixed assets cannot be used as rentable items.')]]);
        }
        if ($fixedAssetId !== null && ! FixedAsset::query()->whereKey($fixedAssetId)->exists()) {
            throw ValidationException::withMessages(['fixed_asset_id' => [__('Selected fixed asset is missing.')]]);
        }

        $branchId = $this->nullableUuid($data['branch_id'] ?? null, 'branch_id');
        if ($branchId !== null && ! Branch::query()->whereKey($branchId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['branch_id' => [__('Selected branch is inactive or missing.')]]);
        }

        $warehouseId = $this->nullableUuid($data['warehouse_id'] ?? null, 'warehouse_id');
        if ($warehouseId !== null) {
            /** @var Warehouse|null $warehouse */
            $warehouse = Warehouse::query()->whereKey($warehouseId)->where('is_active', true)->first();
            if (! $warehouse) {
                throw ValidationException::withMessages(['warehouse_id' => [__('Selected warehouse is inactive or missing.')]]);
            }

            if ($branchId !== null && $warehouse->branch_id !== null && $warehouse->branch_id !== $branchId) {
                throw ValidationException::withMessages(['warehouse_id' => [__('Selected warehouse belongs to a different operational branch.')]]);
            }
        }

        return [
            'code' => $code,
            'name' => $this->normalizeRequiredTranslations($data['name'] ?? []),
            'description' => $this->normalizeOptionalTranslations($data['description'] ?? null),
            'item_source' => $source,
            'product_id' => $productId,
            'fixed_asset_id' => $fixedAssetId,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'status' => $status,
            'condition_status' => $condition,
            'currency' => $currency,
            'serial_number' => $this->nullableString($data['serial_number'] ?? null),
            'replacement_value_minor' => $this->amountMinor($data['replacement_value_minor'] ?? 0, 'replacement_value_minor'),
            'daily_rate_minor' => $this->nullableAmountMinor($data['daily_rate_minor'] ?? null, 'daily_rate_minor'),
            'monthly_rate_minor' => $this->nullableAmountMinor($data['monthly_rate_minor'] ?? null, 'monthly_rate_minor'),
            'deposit_minor' => $this->nullableAmountMinor($data['deposit_minor'] ?? null, 'deposit_minor'),
            'notes' => $this->nullableString($data['notes'] ?? null),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function assertSourceReferenceIsValid(string $source, ?string $productId, ?string $fixedAssetId): void
    {
        if ($source === 'standalone' && ($productId !== null || $fixedAssetId !== null)) {
            throw ValidationException::withMessages(['item_source' => [__('Standalone rentable items cannot be linked to a product or fixed asset.')]]);
        }

        if ($source === 'product' && ($productId === null || $fixedAssetId !== null)) {
            throw ValidationException::withMessages(['product_id' => [__('Product-sourced rentable items must reference exactly one product.')]]);
        }

        if ($source === 'fixed_asset' && ($fixedAssetId === null || $productId !== null)) {
            throw ValidationException::withMessages(['fixed_asset_id' => [__('Fixed-asset-sourced rentable items must reference exactly one fixed asset.')]]);
        }
    }

    private function recordStatusEvent(
        RentableItem $item,
        ?string $fromStatus,
        string $toStatus,
        string $eventType,
        mixed $reason,
        ?int $actorId
    ): void {
        RentableItemStatusEvent::query()->create([
            'rentable_item_id' => $item->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'event_type' => $eventType,
            'reason' => $this->nullableString($reason),
            'actor_id' => $actorId,
        ]);
    }

    private function normalizeRequiredTranslations(mixed $value): array
    {
        $translations = is_array($value) ? $value : [];
        $en = trim((string) ($translations['en'] ?? $translations['name_en'] ?? ''));
        $ar = trim((string) ($translations['ar'] ?? $translations['name_ar'] ?? $en));

        if ($en === '') {
            throw ValidationException::withMessages(['name.en' => [__('English rentable item name is required.')]]);
        }

        return ['en' => $en, 'ar' => $ar === '' ? $en : $ar];
    }

    private function normalizeOptionalTranslations(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $en = trim((string) ($value['en'] ?? ''));
        $ar = trim((string) ($value['ar'] ?? ''));

        if ($en === '' && $ar === '') {
            return null;
        }

        return ['en' => $en === '' ? $ar : $en, 'ar' => $ar === '' ? $en : $ar];
    }

    private function nullableUuid(mixed $value, string $field): ?string
    {
        $value = $this->nullableString($value);
        if ($value !== null && ! Str::isUuid($value)) {
            throw ValidationException::withMessages([$field => [__('Invalid reference.')]]);
        }

        return $value;
    }

    private function amountMinor(mixed $value, string $field): int
    {
        $amount = (int) ($value ?? 0);
        if ($amount < 0) {
            throw ValidationException::withMessages([$field => [__('Amount cannot be negative.')]]);
        }

        return $amount;
    }

    private function nullableAmountMinor(mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->amountMinor($value, $field);
    }

    private function nullableString(mixed $value): ?string
    {
        $stringValue = is_string($value) ? trim($value) : (string) ($value ?? '');

        return $stringValue === '' ? null : $stringValue;
    }

    private function relations(): array
    {
        return ['product', 'fixedAsset', 'branch', 'warehouse', 'currencyRef', 'statusEvents'];
    }
}
