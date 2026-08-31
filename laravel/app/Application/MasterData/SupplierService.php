<?php

namespace App\Application\MasterData;

use App\Domain\Audit\AuditLogger;
use App\Models\Supplier;
use App\Support\Concurrency\OptimisticLock;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OptimisticLock $optimisticLock,
    ) {}

    /**
     * @param  array{code: string, name: array<string, string>|string, status?: string, email?: string|null, phone?: string|null, address?: string|null, tax_number?: string|null}  $data
     */
    public function create(array $data, int|string|null $actorId = null): Supplier
    {
        $this->assertValidStatus($data['status'] ?? 'active');

        if (Supplier::query()->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages([
                'code' => [__('Supplier code [:code] already exists.', ['code' => $data['code']])],
            ]);
        }

        $supplier = Supplier::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'status' => $data['status'] ?? 'active',
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'tax_number' => $data['tax_number'] ?? null,
            'created_by' => $actorId,
            'updated_by' => $actorId,
            'lock_version' => 0,
        ]);

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'create',
            entityType: 'supplier',
            entityId: $supplier->id,
            before: null,
            after: $supplier->fresh()->toArray(),
        );

        return $supplier;
    }

    /**
     * @param  array{code?: string, name?: array<string, string>|string, status?: string, email?: string|null, phone?: string|null, address?: string|null, tax_number?: string|null}  $data
     */
    public function update(string $id, array $data, int $expectedVersion, int|string|null $actorId = null): Supplier
    {
        /** @var Supplier $supplier */
        $supplier = Supplier::query()->findOrFail($id);
        $before = $supplier->toArray();

        if (array_key_exists('status', $data)) {
            $this->assertValidStatus($data['status']);
        }

        if (isset($data['code']) && $data['code'] !== $supplier->code) {
            if (Supplier::query()->where('code', $data['code'])->where('id', '!=', $id)->exists()) {
                throw ValidationException::withMessages([
                    'code' => [__('Supplier code [:code] already exists.', ['code' => $data['code']])],
                ]);
            }
        }

        $updateValues = [];

        foreach (['code', 'status', 'email', 'phone', 'address', 'tax_number'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateValues[$field] = $data[$field];
            }
        }

        if (array_key_exists('name', $data)) {
            $updateValues['name'] = $this->encodeTranslatable($data['name']);
        }

        if ($actorId !== null) {
            $updateValues['updated_by'] = $actorId;
        }

        $updateValues['updated_at'] = now();

        $this->optimisticLock->update('supplier', ['id' => $id], $expectedVersion, $updateValues);

        $updatedSupplier = $supplier->fresh();

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'update',
            entityType: 'supplier',
            entityId: $id,
            before: $before,
            after: $updatedSupplier->toArray(),
        );

        return $updatedSupplier;
    }

    private function assertValidStatus(mixed $status): void
    {
        if (! is_string($status) || ! in_array($status, ['active', 'inactive'], true)) {
            throw ValidationException::withMessages([
                'status' => [__('Supplier status must be active or inactive.')],
            ]);
        }
    }

    /**
     * @param  array<string, string>|string  $value
     */
    private function encodeTranslatable(array|string $value): string
    {
        return is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value;
    }
}
