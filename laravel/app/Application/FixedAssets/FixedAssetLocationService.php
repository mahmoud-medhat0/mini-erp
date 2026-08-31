<?php

namespace App\Application\FixedAssets;

use App\Domain\Audit\AuditLogger;
use App\Models\Branch;
use App\Models\FixedAssetLocation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FixedAssetLocationService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return Collection<int, FixedAssetLocation>
     */
    public function listLocations(array $filters = []): Collection
    {
        $query = FixedAssetLocation::query()
            ->with('branch')
            ->withCount('assets')
            ->orderBy('code');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name->en', 'like', "%{$search}%")
                    ->orWhere('name->ar', 'like', "%{$search}%");
            });
        }

        if (array_key_exists('branch_id', $filters) && filled($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (array_key_exists('status', $filters) && $filters['status'] !== '') {
            $query->where('is_active', $filters['status'] === 'active');
        }

        return $query->get();
    }

    public function createLocation(array $data, ?int $actorId = null): FixedAssetLocation
    {
        $branchId = $this->normalizeBranchId($data['branch_id'] ?? null);

        return DB::transaction(function () use ($data, $branchId, $actorId): FixedAssetLocation {
            $location = FixedAssetLocation::query()->create([
                'id' => (string) Str::uuid(),
                'code' => strtoupper(trim((string) $data['code'])),
                'name' => $data['name'],
                'branch_id' => $branchId,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'lock_version' => 1,
            ]);

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'fixed_asset_location.create',
                entityType: 'fixed_asset_location',
                entityId: (string) $location->id,
                after: $location->toArray(),
            );

            return $location->load('branch');
        });
    }

    public function updateLocation(string $id, array $data, ?int $actorId = null): FixedAssetLocation
    {
        return DB::transaction(function () use ($id, $data, $actorId): FixedAssetLocation {
            /** @var FixedAssetLocation $location */
            $location = FixedAssetLocation::query()->lockForUpdate()->findOrFail($id);
            $before = $location->toArray();

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== (int) $location->lock_version) {
                throw ValidationException::withMessages([
                    'lock_version' => [__('The record has been modified by another user. Please refresh and try again.')],
                ]);
            }

            if (array_key_exists('code', $data)) {
                $location->code = strtoupper(trim((string) $data['code']));
            }

            if (array_key_exists('name', $data)) {
                $location->name = $data['name'];
            }

            if (array_key_exists('branch_id', $data)) {
                $location->branch_id = $this->normalizeBranchId($data['branch_id']);
            }

            if (array_key_exists('is_active', $data)) {
                $location->is_active = (bool) $data['is_active'];
            }

            $location->lock_version = ((int) $location->lock_version) + 1;
            $location->save();

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'fixed_asset_location.update',
                entityType: 'fixed_asset_location',
                entityId: (string) $location->id,
                before: $before,
                after: $location->toArray(),
            );

            return $location->load('branch');
        });
    }

    public function deleteLocation(string $id, ?int $actorId = null): void
    {
        DB::transaction(function () use ($id, $actorId): void {
            /** @var FixedAssetLocation $location */
            $location = FixedAssetLocation::query()->lockForUpdate()->findOrFail($id);

            if ($location->assets()->exists() || $location->inboundMovements()->exists() || $location->outboundMovements()->exists()) {
                throw ValidationException::withMessages([
                    'location' => [__('Locations used by assets or movement history cannot be deleted. Deactivate the location instead.')],
                ]);
            }

            $before = $location->toArray();
            $location->delete();

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'fixed_asset_location.delete',
                entityType: 'fixed_asset_location',
                entityId: $id,
                before: $before,
            );
        });
    }

    private function normalizeBranchId(mixed $branchId): ?string
    {
        $normalized = is_string($branchId) ? trim($branchId) : $branchId;

        if ($normalized === null || $normalized === '') {
            return null;
        }

        /** @var Branch|null $branch */
        $branch = Branch::query()->whereKey($normalized)->first();
        if (! $branch || ! $branch->is_active) {
            throw ValidationException::withMessages([
                'branch_id' => [__('Selected branch is inactive or missing.')],
            ]);
        }

        return (string) $branch->id;
    }
}
