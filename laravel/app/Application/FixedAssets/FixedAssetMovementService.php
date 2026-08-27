<?php

namespace App\Application\FixedAssets;

use App\Domain\Audit\AuditLogger;
use App\Models\Branch;
use App\Models\FixedAsset;
use App\Models\FixedAssetLocation;
use App\Models\FixedAssetMovement;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FixedAssetMovementService
{
    public function __construct(
        private readonly NumberSequenceAllocator $numberSequenceAllocator,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function move(string $assetId, array $data, ?int $actorId = null): FixedAsset
    {
        return DB::transaction(function () use ($assetId, $data, $actorId): FixedAsset {
            /** @var FixedAsset $asset */
            $asset = FixedAsset::query()
                ->with(['branch', 'location'])
                ->lockForUpdate()
                ->findOrFail($assetId);

            if ($asset->status === 'disposed') {
                throw ValidationException::withMessages([
                    'asset' => [__('Disposed assets cannot be moved.')],
                ]);
            }

            $movementDate = $this->requiredDate($data['movement_date'] ?? null, 'movement_date');
            [$toBranch, $toLocation] = $this->resolveTarget($data['to_branch_id'] ?? null, $data['to_location_id'] ?? null);

            $fromBranchId = $asset->branch_id ? (string) $asset->branch_id : null;
            $fromLocationId = $asset->fixed_asset_location_id ? (string) $asset->fixed_asset_location_id : null;
            $toBranchId = $toBranch?->id ? (string) $toBranch->id : null;
            $toLocationId = $toLocation?->id ? (string) $toLocation->id : null;

            if ($fromBranchId === $toBranchId && $fromLocationId === $toLocationId) {
                throw ValidationException::withMessages([
                    'movement' => [__('The selected destination matches the current asset position.')],
                ]);
            }

            $before = $asset->toArray();
            $sequence = $this->numberSequenceAllocator->nextValue('fixed_asset_movement');
            $number = sprintf('FAM-%s-%05d', Carbon::parse($movementDate)->format('Y'), $sequence);

            /** @var FixedAssetMovement $movement */
            $movement = FixedAssetMovement::query()->create([
                'id' => (string) Str::uuid(),
                'number' => $number,
                'fixed_asset_id' => $asset->id,
                'movement_date' => $movementDate,
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $toBranchId,
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'from_snapshot_json' => $this->snapshot($asset->branch, $asset->location),
                'to_snapshot_json' => $this->snapshot($toBranch, $toLocation),
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
                'created_at' => now(),
            ]);

            $asset->branch_id = $toBranchId;
            $asset->fixed_asset_location_id = $toLocationId;
            $asset->lock_version = ((int) $asset->lock_version) + 1;
            $asset->updated_by = $actorId;
            $asset->save();

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'fixed_asset.move',
                entityType: 'fixed_asset',
                entityId: (string) $asset->id,
                before: $before,
                after: [
                    ...$asset->toArray(),
                    'movement_id' => $movement->id,
                    'movement_number' => $movement->number,
                ],
            );

            return $asset->load([
                'category',
                'currencyModel',
                'branch',
                'location.branch',
                'movements.fromBranch',
                'movements.toBranch',
                'movements.fromLocation',
                'movements.toLocation',
                'movements.creator',
            ]);
        });
    }

    /**
     * @return array{0: Branch|null, 1: FixedAssetLocation|null}
     */
    private function resolveTarget(mixed $branchId, mixed $locationId): array
    {
        $normalizedBranchId = $this->normalizeNullableId($branchId);
        $normalizedLocationId = $this->normalizeNullableId($locationId);

        $location = null;
        if ($normalizedLocationId !== null) {
            /** @var FixedAssetLocation|null $location */
            $location = FixedAssetLocation::query()->with('branch')->whereKey($normalizedLocationId)->first();
            if (! $location || ! $location->is_active) {
                throw ValidationException::withMessages([
                    'to_location_id' => [__('Selected location is inactive or missing.')],
                ]);
            }

            if ($location->branch_id && $normalizedBranchId === null) {
                $normalizedBranchId = (string) $location->branch_id;
            }
        }

        $branch = null;
        if ($normalizedBranchId !== null) {
            /** @var Branch|null $branch */
            $branch = Branch::query()->whereKey($normalizedBranchId)->first();
            if (! $branch || ! $branch->is_active) {
                throw ValidationException::withMessages([
                    'to_branch_id' => [__('Selected branch is inactive or missing.')],
                ]);
            }
        }

        if ($location?->branch_id && $branch && (string) $location->branch_id !== (string) $branch->id) {
            throw ValidationException::withMessages([
                'to_location_id' => [__('Selected location belongs to a different branch.')],
            ]);
        }

        return [$branch, $location];
    }

    private function requiredDate(mixed $value, string $field): string
    {
        if ($value === null || $value === '') {
            throw ValidationException::withMessages([$field => [__('Date is required.')]]);
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => [__('Invalid date.')]]);
        }
    }

    private function normalizeNullableId(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function snapshot(?Branch $branch, ?FixedAssetLocation $location): array
    {
        return [
            'branch' => $branch ? [
                'id' => (string) $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
            ] : null,
            'location' => $location ? [
                'id' => (string) $location->id,
                'code' => $location->code,
                'name' => $location->name,
                'branch_id' => $location->branch_id,
            ] : null,
        ];
    }
}
