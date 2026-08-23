<?php

namespace App\Application\Taxes;

use App\Domain\Audit\AuditLogger;
use App\Models\TaxCode;
use App\Models\TaxRate;
use Illuminate\Validation\ValidationException;

class TaxMasterDataService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function createTaxCode(array $data, int|string|null $actorId = null): TaxCode
    {
        $code = strtoupper(trim($data['code']));

        if (TaxCode::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => ["Tax code [{$code}] already exists."],
            ]);
        }

        $taxCode = TaxCode::query()->create([
            'code' => $code,
            'name' => $data['name'],
            'tax_type' => $data['tax_type'] ?? 'vat',
            'calculation_mode' => $data['calculation_mode'] ?? 'exclusive',
            'recoverability_mode' => $data['recoverability_mode'] ?? 'full',
            'is_system' => $data['is_system'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->auditLogger->record(
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            action: 'tax_code.create',
            entityType: 'tax_code',
            entityId: (string) $taxCode->id,
            before: null,
            after: $taxCode->toArray()
        );

        return $taxCode;
    }

    public function updateTaxCode(string $id, array $data, int|string|null $actorId = null): TaxCode
    {
        /** @var TaxCode $taxCode */
        $taxCode = TaxCode::query()->findOrFail($id);
        $before = $taxCode->toArray();

        if (isset($data['code'])) {
            $newCode = strtoupper(trim($data['code']));
            if ($newCode !== $taxCode->code && TaxCode::query()->where('code', $newCode)->exists()) {
                throw ValidationException::withMessages([
                    'code' => ["Tax code [{$newCode}] already exists."],
                ]);
            }
            if ($taxCode->is_system && $newCode !== $taxCode->code) {
                throw ValidationException::withMessages([
                    'code' => ['System tax codes cannot have their code changed.'],
                ]);
            }
            $taxCode->code = $newCode;
        }

        if (isset($data['name'])) {
            $taxCode->name = $data['name'];
        }

        if (isset($data['calculation_mode'])) {
            if (! in_array($data['calculation_mode'], ['exclusive', 'inclusive', 'exempt'], true)) {
                throw ValidationException::withMessages([
                    'calculation_mode' => ['Invalid calculation mode.'],
                ]);
            }
            $taxCode->calculation_mode = $data['calculation_mode'];
        }

        if (isset($data['recoverability_mode'])) {
            if (! in_array($data['recoverability_mode'], ['full', 'none'], true)) {
                throw ValidationException::withMessages([
                    'recoverability_mode' => ['Invalid recoverability mode.'],
                ]);
            }
            $taxCode->recoverability_mode = $data['recoverability_mode'];
        }

        if (isset($data['is_active'])) {
            $taxCode->is_active = (bool) $data['is_active'];
        }

        $taxCode->save();

        $this->auditLogger->record(
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            action: 'tax_code.update',
            entityType: 'tax_code',
            entityId: (string) $taxCode->id,
            before: $before,
            after: $taxCode->toArray()
        );

        return $taxCode;
    }

    public function deleteTaxCode(string $id, int|string|null $actorId = null): void
    {
        /** @var TaxCode $taxCode */
        $taxCode = TaxCode::query()->findOrFail($id);

        if ($taxCode->is_system) {
            throw ValidationException::withMessages([
                'tax_code' => ['System tax codes cannot be deleted.'],
            ]);
        }

        if ($taxCode->rates()->exists()) {
            throw ValidationException::withMessages([
                'tax_code' => ['Cannot delete tax code that has rates configured.'],
            ]);
        }

        $before = $taxCode->toArray();
        $taxCode->delete();

        $this->auditLogger->record(
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            action: 'tax_code.delete',
            entityType: 'tax_code',
            entityId: (string) $id,
            before: $before,
            after: null
        );
    }

    public function createTaxRate(array $data, int|string|null $actorId = null): TaxRate
    {
        /** @var TaxCode $taxCode */
        $taxCode = TaxCode::query()->findOrFail($data['tax_code_id']);

        $rateBps = (int) $data['rate_bps'];
        if ($rateBps < 0) {
            throw ValidationException::withMessages([
                'rate_bps' => ['Tax rate basis points must be a non-negative integer.'],
            ]);
        }

        $from = $data['effective_from'];
        $to = $data['effective_to'] ?? null;

        if ($to && $to < $from) {
            throw ValidationException::withMessages([
                'effective_to' => ['Effective to date cannot be before effective from date.'],
            ]);
        }

        $taxRate = TaxRate::query()->create([
            'tax_code_id' => $taxCode->id,
            'rate_bps' => $rateBps,
            'effective_from' => $from,
            'effective_to' => $to,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->auditLogger->record(
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            action: 'tax_rate.create',
            entityType: 'tax_rate',
            entityId: (string) $taxRate->id,
            before: null,
            after: $taxRate->toArray()
        );

        return $taxRate;
    }

    public function updateTaxRate(string $id, array $data, int|string|null $actorId = null): TaxRate
    {
        /** @var TaxRate $taxRate */
        $taxRate = TaxRate::query()->findOrFail($id);
        $before = $taxRate->toArray();

        if (isset($data['rate_bps'])) {
            $rateBps = (int) $data['rate_bps'];
            if ($rateBps < 0) {
                throw ValidationException::withMessages([
                    'rate_bps' => ['Tax rate basis points must be a non-negative integer.'],
                ]);
            }
            $taxRate->rate_bps = $rateBps;
        }

        if (isset($data['effective_from'])) {
            $taxRate->effective_from = $data['effective_from'];
        }

        if (array_key_exists('effective_to', $data)) {
            $taxRate->effective_to = $data['effective_to'];
        }

        if ($taxRate->effective_to && $taxRate->effective_to < $taxRate->effective_from) {
            throw ValidationException::withMessages([
                'effective_to' => ['Effective to date cannot be before effective from date.'],
            ]);
        }

        if (isset($data['is_active'])) {
            $taxRate->is_active = (bool) $data['is_active'];
        }

        $taxRate->save();

        $this->auditLogger->record(
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            action: 'tax_rate.update',
            entityType: 'tax_rate',
            entityId: (string) $taxRate->id,
            before: $before,
            after: $taxRate->toArray()
        );

        return $taxRate;
    }

    public function deleteTaxRate(string $id, int|string|null $actorId = null): void
    {
        /** @var TaxRate $taxRate */
        $taxRate = TaxRate::query()->findOrFail($id);

        $before = $taxRate->toArray();
        $taxRate->delete();

        $this->auditLogger->record(
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            action: 'tax_rate.delete',
            entityType: 'tax_rate',
            entityId: (string) $id,
            before: $before,
            after: null
        );
    }
}
