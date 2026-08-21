<?php

namespace App\Application\MasterData;

use App\Domain\Audit\AuditLogger;
use App\Models\Customer;
use App\Support\Concurrency\OptimisticLock;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OptimisticLock $optimisticLock,
    ) {}

    /**
     * @param  array{code: string, name: array<string, string>|string, status?: string, email?: string|null, phone?: string|null, address?: string|null, tax_number?: string|null}  $data
     */
    public function create(array $data, int|string|null $actorId = null): Customer
    {
        $this->assertValidStatus($data['status'] ?? 'active');

        if (Customer::query()->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages([
                'code' => ["Customer code [{$data['code']}] already exists."],
            ]);
        }

        $customer = Customer::query()->create([
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
            entityType: 'customer',
            entityId: $customer->id,
            before: null,
            after: $customer->fresh()->toArray(),
        );

        return $customer;
    }

    /**
     * @param  array{code?: string, name?: array<string, string>|string, status?: string, email?: string|null, phone?: string|null, address?: string|null, tax_number?: string|null}  $data
     */
    public function update(string $id, array $data, int $expectedVersion, int|string|null $actorId = null): Customer
    {
        /** @var Customer $customer */
        $customer = Customer::query()->findOrFail($id);
        $before = $customer->toArray();

        if (array_key_exists('status', $data)) {
            $this->assertValidStatus($data['status']);
        }

        if (isset($data['code']) && $data['code'] !== $customer->code) {
            if (Customer::query()->where('code', $data['code'])->where('id', '!=', $id)->exists()) {
                throw ValidationException::withMessages([
                    'code' => ["Customer code [{$data['code']}] already exists."],
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

        $this->optimisticLock->update('customer', ['id' => $id], $expectedVersion, $updateValues);

        $updatedCustomer = $customer->fresh();

        $this->auditLogger->record(
            actorId: $actorId,
            action: 'update',
            entityType: 'customer',
            entityId: $id,
            before: $before,
            after: $updatedCustomer->toArray(),
        );

        return $updatedCustomer;
    }

    private function assertValidStatus(mixed $status): void
    {
        if (! is_string($status) || ! in_array($status, ['active', 'inactive'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Customer status must be active or inactive.'],
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
