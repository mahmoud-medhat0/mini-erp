<?php

namespace App\Application\Rentals;

use App\Domain\Audit\AuditLogger;
use App\Models\RentableItem;
use App\Models\RentableItemStatusEvent;
use App\Models\RentalContract;
use App\Models\RentalContractLine;
use App\Models\RentalContractStatusEvent;
use App\Models\RentalHandover;
use App\Models\RentalHandoverLine;
use App\Models\RentalReturn;
use App\Models\RentalReturnLine;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RentalFulfillmentService
{
    public const HANDOVER_STATUSES = ['draft', 'confirmed', 'cancelled'];

    public const RETURN_STATUSES = ['draft', 'submitted', 'completed', 'cancelled'];

    public const CONDITIONS_OUT = ['good', 'fair', 'damaged', 'maintenance'];

    public const CONDITIONS_IN = ['good', 'fair', 'damaged', 'lost', 'maintenance'];

    public const RETURN_OUTCOMES = ['returned', 'damaged', 'lost', 'maintenance'];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NumberSequenceAllocator $numberAllocator,
    ) {}

    public function createHandover(array $data, ?int $actorId = null): RentalHandover
    {
        return DB::transaction(function () use ($data, $actorId): RentalHandover {
            $contract = $this->lockedContract($data['rental_contract_id'] ?? null);
            if (! in_array($contract->status, ['approved', 'active'], true)) {
                throw ValidationException::withMessages(['rental_contract_id' => [__('Only approved or active rental contracts can be handed over.')]]);
            }

            $payload = $this->validatedHandoverPayload($contract, $data);

            /** @var RentalHandover $handover */
            $handover = RentalHandover::query()->create([
                'rental_contract_id' => $contract->id,
                'customer_id' => $contract->customer_id,
                'branch_id' => $contract->branch_id,
                'status' => 'draft',
                'handover_date' => $payload['handover_date'],
                'notes' => $payload['notes'],
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            foreach ($payload['lines'] as $line) {
                RentalHandoverLine::query()->create([
                    'rental_handover_id' => $handover->id,
                    ...$line,
                ]);
            }

            $this->auditLogger->record($actorId, 'rental_handover.create', 'rental_handover', $handover->id, after: $handover->fresh($this->handoverRelations())->toArray());

            return $handover->fresh($this->handoverRelations());
        });
    }

    public function confirmHandover(string $id, ?int $actorId = null): RentalHandover
    {
        return DB::transaction(function () use ($id, $actorId): RentalHandover {
            /** @var RentalHandover $handover */
            $handover = RentalHandover::query()->with('lines')->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($handover->status === 'confirmed') {
                return $handover->fresh($this->handoverRelations());
            }

            if ($handover->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft handovers can be confirmed.')]]);
            }

            $contract = $this->lockedContract($handover->rental_contract_id);
            if (! in_array($contract->status, ['approved', 'active'], true)) {
                throw ValidationException::withMessages(['rental_contract_id' => [__('Only approved or active rental contracts can be handed over.')]]);
            }

            $items = $this->lockedItems($handover->lines->pluck('rentable_item_id'));
            $this->assertItemStatuses($items, ['allocated', 'rented'], __('Only allocated rental items can be handed over.'));

            $before = $handover->fresh($this->handoverRelations())->toArray();
            if (! $handover->number) {
                $handover->number = $this->number('rental.handover', 'RH', $handover->handover_date);
            }

            $handover->status = 'confirmed';
            $handover->confirmed_by = $actorId;
            $handover->confirmed_at = now();
            $handover->updated_by = $actorId;
            $handover->lock_version = ((int) $handover->lock_version) + 1;
            $handover->save();

            $contractBefore = $contract->fresh()->toArray();
            if ($contract->status === 'approved') {
                $contract->status = 'active';
                $contract->activated_by = $actorId;
                $contract->activated_at = now();
                $contract->updated_by = $actorId;
                $contract->lock_version = ((int) $contract->lock_version) + 1;
                $contract->save();

                RentalContractStatusEvent::query()->create([
                    'rental_contract_id' => $contract->id,
                    'from_status' => 'approved',
                    'to_status' => 'active',
                    'event_type' => 'activated',
                    'reason' => 'rental_handover.confirm: '.($handover->number ?: $handover->id),
                    'actor_id' => $actorId,
                ]);
                $this->auditLogger->record($actorId, 'rental_contract.activate', 'rental_contract', $contract->id, before: $contractBefore, after: $contract->fresh()->toArray());
            }

            $this->transitionItems($items, 'rented', 'rental_handover.confirm: '.($handover->number ?: $handover->id), $actorId);
            $this->auditLogger->record($actorId, 'rental_handover.confirm', 'rental_handover', $handover->id, before: $before, after: $handover->fresh($this->handoverRelations())->toArray());

            return $handover->fresh($this->handoverRelations());
        });
    }

    public function cancelHandover(string $id, ?int $actorId = null): RentalHandover
    {
        return DB::transaction(function () use ($id, $actorId): RentalHandover {
            /** @var RentalHandover $handover */
            $handover = RentalHandover::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($handover->status === 'cancelled') {
                return $handover->fresh($this->handoverRelations());
            }

            if ($handover->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft handovers can be cancelled.')]]);
            }

            $before = $handover->fresh($this->handoverRelations())->toArray();
            $handover->status = 'cancelled';
            $handover->cancelled_by = $actorId;
            $handover->cancelled_at = now();
            $handover->updated_by = $actorId;
            $handover->lock_version = ((int) $handover->lock_version) + 1;
            $handover->save();

            $this->auditLogger->record($actorId, 'rental_handover.cancel', 'rental_handover', $handover->id, before: $before, after: $handover->fresh($this->handoverRelations())->toArray());

            return $handover->fresh($this->handoverRelations());
        });
    }

    public function createReturn(array $data, ?int $actorId = null): RentalReturn
    {
        return DB::transaction(function () use ($data, $actorId): RentalReturn {
            $contract = $this->lockedContract($data['rental_contract_id'] ?? null);
            if ($contract->status !== 'active') {
                throw ValidationException::withMessages(['rental_contract_id' => [__('Only active rental contracts can receive returns.')]]);
            }

            $payload = $this->validatedReturnPayload($contract, $data);

            /** @var RentalReturn $return */
            $return = RentalReturn::query()->create([
                'rental_contract_id' => $contract->id,
                'customer_id' => $contract->customer_id,
                'branch_id' => $contract->branch_id,
                'status' => 'draft',
                'return_date' => $payload['return_date'],
                'notes' => $payload['notes'],
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            foreach ($payload['lines'] as $line) {
                RentalReturnLine::query()->create([
                    'rental_return_id' => $return->id,
                    ...$line,
                ]);
            }

            $this->auditLogger->record($actorId, 'rental_return.create', 'rental_return', $return->id, after: $return->fresh($this->returnRelations())->toArray());

            return $return->fresh($this->returnRelations());
        });
    }

    public function submitReturn(string $id, ?int $actorId = null): RentalReturn
    {
        return DB::transaction(function () use ($id, $actorId): RentalReturn {
            /** @var RentalReturn $return */
            $return = RentalReturn::query()->with('lines')->whereKey($id)->lockForUpdate()->firstOrFail();

            if (in_array($return->status, ['submitted', 'completed'], true)) {
                return $return->fresh($this->returnRelations());
            }

            if ($return->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft returns can be submitted.')]]);
            }

            $contract = $this->lockedContract($return->rental_contract_id);
            if ($contract->status !== 'active') {
                throw ValidationException::withMessages(['rental_contract_id' => [__('Only active rental contracts can receive returns.')]]);
            }

            $items = $this->lockedItems($return->lines->pluck('rentable_item_id'));
            $this->assertItemStatuses($items, ['rented'], __('Only rented items can be submitted for return.'));

            $before = $return->fresh($this->returnRelations())->toArray();
            if (! $return->number) {
                $return->number = $this->number('rental.return', 'RR', $return->return_date);
            }

            $return->status = 'submitted';
            $return->submitted_by = $actorId;
            $return->submitted_at = now();
            $return->updated_by = $actorId;
            $return->lock_version = ((int) $return->lock_version) + 1;
            $return->save();

            $this->transitionItems($items, 'return_pending', 'rental_return.submit: '.($return->number ?: $return->id), $actorId);
            $this->auditLogger->record($actorId, 'rental_return.submit', 'rental_return', $return->id, before: $before, after: $return->fresh($this->returnRelations())->toArray());

            return $return->fresh($this->returnRelations());
        });
    }

    public function completeReturn(string $id, ?int $actorId = null): RentalReturn
    {
        return DB::transaction(function () use ($id, $actorId): RentalReturn {
            /** @var RentalReturn $return */
            $return = RentalReturn::query()->with('lines')->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($return->status === 'completed') {
                return $return->fresh($this->returnRelations());
            }

            if ($return->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => [__('Only submitted returns can be completed after inspection.')]]);
            }

            $contract = $this->lockedContract($return->rental_contract_id);
            if ($contract->status !== 'active') {
                throw ValidationException::withMessages(['rental_contract_id' => [__('Only active rental contracts can be completed through return inspection.')]]);
            }

            $items = $this->lockedItems($return->lines->pluck('rentable_item_id'));
            $this->assertItemStatuses($items, ['return_pending'], __('Only return-pending items can be inspected.'));

            $before = $return->fresh($this->returnRelations())->toArray();
            foreach ($return->lines as $line) {
                /** @var RentableItem $item */
                $item = $items->get($line->rentable_item_id);
                $this->transitionItem($item, $line->outcome, 'rental_return.complete: '.($return->number ?: $return->id), $actorId, $line->condition_in);
            }

            $return->status = 'completed';
            $return->completed_by = $actorId;
            $return->completed_at = now();
            $return->updated_by = $actorId;
            $return->lock_version = ((int) $return->lock_version) + 1;
            $return->save();

            $this->completeContractIfAllItemsClosed($contract, $return, $actorId);
            $this->auditLogger->record($actorId, 'rental_return.complete', 'rental_return', $return->id, before: $before, after: $return->fresh($this->returnRelations())->toArray());

            return $return->fresh($this->returnRelations());
        });
    }

    public function cancelReturn(string $id, ?int $actorId = null): RentalReturn
    {
        return DB::transaction(function () use ($id, $actorId): RentalReturn {
            /** @var RentalReturn $return */
            $return = RentalReturn::query()->with('lines')->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($return->status === 'cancelled') {
                return $return->fresh($this->returnRelations());
            }

            if ($return->status === 'completed') {
                throw ValidationException::withMessages(['status' => [__('Completed rental returns cannot be cancelled.')]]);
            }

            $before = $return->fresh($this->returnRelations())->toArray();
            if ($return->status === 'submitted') {
                $items = $this->lockedItems($return->lines->pluck('rentable_item_id'));
                $this->assertItemStatuses($items, ['return_pending'], __('Only return-pending items can be released from a cancelled return.'));
                $this->transitionItems($items, 'rented', 'rental_return.cancel: '.($return->number ?: $return->id), $actorId);
            }

            $return->status = 'cancelled';
            $return->cancelled_by = $actorId;
            $return->cancelled_at = now();
            $return->updated_by = $actorId;
            $return->lock_version = ((int) $return->lock_version) + 1;
            $return->save();

            $this->auditLogger->record($actorId, 'rental_return.cancel', 'rental_return', $return->id, before: $before, after: $return->fresh($this->returnRelations())->toArray());

            return $return->fresh($this->returnRelations());
        });
    }

    private function validatedHandoverPayload(RentalContract $contract, array $data): array
    {
        return [
            'handover_date' => $this->requiredDate($data['handover_date'] ?? null, 'handover_date'),
            'notes' => $this->nullableString($data['notes'] ?? null),
            'lines' => $this->validatedHandoverLines($contract, $data['lines'] ?? []),
        ];
    }

    private function validatedHandoverLines(RentalContract $contract, mixed $rawLines): array
    {
        if (! is_array($rawLines) || count($rawLines) === 0) {
            throw ValidationException::withMessages(['lines' => [__('Handover must have at least one line.')]]);
        }

        $contractLines = $contract->lines()->with('rentableItem')->get()->keyBy('id');
        $seen = [];
        $lines = [];

        foreach (array_values($rawLines) as $index => $line) {
            if (! is_array($line)) {
                throw ValidationException::withMessages(["lines.{$index}" => [__('Invalid handover line.')]]);
            }

            $contractLineId = $this->requiredUuid($line['rental_contract_line_id'] ?? null, "lines.{$index}.rental_contract_line_id");
            if (in_array($contractLineId, $seen, true)) {
                throw ValidationException::withMessages(["lines.{$index}.rental_contract_line_id" => [__('A contract line can be handed over only once per document.')]]);
            }
            $seen[] = $contractLineId;

            /** @var RentalContractLine|null $contractLine */
            $contractLine = $contractLines->get($contractLineId);
            if (! $contractLine) {
                throw ValidationException::withMessages(["lines.{$index}.rental_contract_line_id" => [__('Selected line does not belong to the rental contract.')]]);
            }

            if ($this->confirmedHandoverExists($contractLine->id)) {
                throw ValidationException::withMessages(["lines.{$index}.rental_contract_line_id" => [__('Selected line was already handed over.')]]);
            }

            if (! in_array($contractLine->rentableItem?->status, ['allocated', 'rented'], true)) {
                throw ValidationException::withMessages(["lines.{$index}.rental_contract_line_id" => [__('Only allocated or rented contract lines can be handed over.')]]);
            }

            $condition = (string) ($line['condition_out'] ?? 'good');
            if (! in_array($condition, self::CONDITIONS_OUT, true)) {
                throw ValidationException::withMessages(["lines.{$index}.condition_out" => [__('Invalid handover condition.')]]);
            }

            $lines[] = [
                'rental_contract_line_id' => $contractLine->id,
                'rentable_item_id' => $contractLine->rentable_item_id,
                'condition_out' => $condition,
                'accessories_out' => $this->normalizeTextList($line['accessories_out'] ?? null),
                'notes' => $this->nullableString($line['notes'] ?? null),
            ];
        }

        return $lines;
    }

    private function validatedReturnPayload(RentalContract $contract, array $data): array
    {
        return [
            'return_date' => $this->requiredDate($data['return_date'] ?? null, 'return_date'),
            'notes' => $this->nullableString($data['notes'] ?? null),
            'lines' => $this->validatedReturnLines($contract, $data['lines'] ?? []),
        ];
    }

    private function validatedReturnLines(RentalContract $contract, mixed $rawLines): array
    {
        if (! is_array($rawLines) || count($rawLines) === 0) {
            throw ValidationException::withMessages(['lines' => [__('Return must have at least one line.')]]);
        }

        $contractLines = $contract->lines()->with('rentableItem')->get()->keyBy('id');
        $seen = [];
        $lines = [];

        foreach (array_values($rawLines) as $index => $line) {
            if (! is_array($line)) {
                throw ValidationException::withMessages(["lines.{$index}" => [__('Invalid return line.')]]);
            }

            $contractLineId = $this->requiredUuid($line['rental_contract_line_id'] ?? null, "lines.{$index}.rental_contract_line_id");
            if (in_array($contractLineId, $seen, true)) {
                throw ValidationException::withMessages(["lines.{$index}.rental_contract_line_id" => [__('A contract line can be returned only once per document.')]]);
            }
            $seen[] = $contractLineId;

            /** @var RentalContractLine|null $contractLine */
            $contractLine = $contractLines->get($contractLineId);
            if (! $contractLine) {
                throw ValidationException::withMessages(["lines.{$index}.rental_contract_line_id" => [__('Selected line does not belong to the rental contract.')]]);
            }

            if ($this->openReturnExists($contractLine->id)) {
                throw ValidationException::withMessages(["lines.{$index}.rental_contract_line_id" => [__('Selected line is already on an open return document.')]]);
            }

            if ($contractLine->rentableItem?->status !== 'rented') {
                throw ValidationException::withMessages(["lines.{$index}.rental_contract_line_id" => [__('Only rented contract lines can be returned.')]]);
            }

            $condition = (string) ($line['condition_in'] ?? 'good');
            if (! in_array($condition, self::CONDITIONS_IN, true)) {
                throw ValidationException::withMessages(["lines.{$index}.condition_in" => [__('Invalid return condition.')]]);
            }

            $outcome = (string) ($line['outcome'] ?? 'returned');
            if (! in_array($outcome, self::RETURN_OUTCOMES, true)) {
                throw ValidationException::withMessages(["lines.{$index}.outcome" => [__('Invalid return outcome.')]]);
            }

            $lines[] = [
                'rental_contract_line_id' => $contractLine->id,
                'rentable_item_id' => $contractLine->rentable_item_id,
                'condition_in' => $condition,
                'outcome' => $outcome,
                'estimated_damage_charge_minor' => $this->amountMinor($line['estimated_damage_charge_minor'] ?? 0, "lines.{$index}.estimated_damage_charge_minor"),
                'accessories_in' => $this->normalizeTextList($line['accessories_in'] ?? null),
                'inspection_notes' => $this->nullableString($line['inspection_notes'] ?? null),
            ];
        }

        return $lines;
    }

    private function completeContractIfAllItemsClosed(RentalContract $contract, RentalReturn $return, ?int $actorId): void
    {
        $remainingOpen = RentableItem::query()
            ->whereIn('id', $contract->lines()->pluck('rentable_item_id'))
            ->whereIn('status', ['allocated', 'rented', 'return_pending'])
            ->exists();

        if ($remainingOpen) {
            return;
        }

        $before = $contract->fresh()->toArray();
        $contract->status = 'completed';
        $contract->actual_end_date = $return->return_date;
        $contract->updated_by = $actorId;
        $contract->lock_version = ((int) $contract->lock_version) + 1;
        $contract->save();

        RentalContractStatusEvent::query()->create([
            'rental_contract_id' => $contract->id,
            'from_status' => 'active',
            'to_status' => 'completed',
            'event_type' => 'completed',
            'reason' => 'rental_return.complete: '.($return->number ?: $return->id),
            'actor_id' => $actorId,
        ]);

        $this->auditLogger->record($actorId, 'rental_contract.complete', 'rental_contract', $contract->id, before: $before, after: $contract->fresh()->toArray());
    }

    private function lockedContract(mixed $id): RentalContract
    {
        $id = $this->requiredUuid($id, 'rental_contract_id');

        /** @var RentalContract $contract */
        $contract = RentalContract::query()->with('lines.rentableItem')->whereKey($id)->lockForUpdate()->firstOrFail();

        if ($contract->lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => [__('Rental contract must have at least one line.')]]);
        }

        return $contract;
    }

    /**
     * @param  Collection<int, string>|array<int, string>  $ids
     * @return Collection<string, RentableItem>
     */
    private function lockedItems(Collection|array $ids): Collection
    {
        return RentableItem::query()
            ->whereIn('id', collect($ids)->unique()->sort()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    private function assertItemStatuses(Collection $items, array $allowedStatuses, string $message): void
    {
        foreach ($items as $item) {
            if (! in_array($item->status, $allowedStatuses, true)) {
                throw ValidationException::withMessages(['rentable_item_id' => [$message]]);
            }
        }
    }

    private function transitionItems(Collection $items, string $toStatus, string $reason, ?int $actorId): void
    {
        foreach ($items as $item) {
            $this->transitionItem($item, $toStatus, $reason, $actorId);
        }
    }

    private function transitionItem(RentableItem $item, string $toStatus, string $reason, ?int $actorId, ?string $condition = null): void
    {
        $fromStatus = $item->status;
        if ($fromStatus === $toStatus && ($condition === null || $item->condition_status === $condition)) {
            return;
        }

        $before = $item->toArray();
        $item->status = $toStatus;
        if ($condition !== null) {
            $item->condition_status = $this->conditionForOutcome($toStatus, $condition);
        }
        $item->updated_by = $actorId;
        $item->lock_version = ((int) $item->lock_version) + 1;
        $item->save();

        RentableItemStatusEvent::query()->create([
            'rentable_item_id' => $item->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'event_type' => 'status_changed',
            'reason' => $reason,
            'actor_id' => $actorId,
        ]);

        $this->auditLogger->record($actorId, 'rentable_item.status_changed', 'rentable_item', $item->id, before: $before, after: $item->fresh()->toArray());
    }

    private function conditionForOutcome(string $outcome, string $condition): string
    {
        return match ($outcome) {
            'lost' => 'lost',
            'maintenance' => 'maintenance',
            'damaged' => 'damaged',
            default => $condition,
        };
    }

    private function confirmedHandoverExists(string $contractLineId): bool
    {
        return RentalHandoverLine::query()
            ->where('rental_contract_line_id', $contractLineId)
            ->whereHas('handover', fn ($query) => $query->where('status', 'confirmed'))
            ->exists();
    }

    private function openReturnExists(string $contractLineId): bool
    {
        return RentalReturnLine::query()
            ->where('rental_contract_line_id', $contractLineId)
            ->whereHas('rentalReturn', fn ($query) => $query->whereIn('status', ['draft', 'submitted']))
            ->exists();
    }

    private function number(string $key, string $prefix, mixed $date): string
    {
        return $this->numberAllocator->nextNumber($key, $prefix, $date);
    }

    private function requiredUuid(mixed $value, string $field): string
    {
        $value = $this->nullableString($value);
        if ($value === null || ! Str::isUuid($value)) {
            throw ValidationException::withMessages([$field => [__('Invalid or missing reference.')]]);
        }

        return $value;
    }

    private function requiredDate(mixed $value, string $field): string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            throw ValidationException::withMessages([$field => [__('Date is required.')]]);
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => [__('Invalid date.')]]);
        }
    }

    private function amountMinor(mixed $value, string $field): int
    {
        $amount = (int) ($value ?? 0);
        if ($amount < 0) {
            throw ValidationException::withMessages([$field => [__('Amount cannot be negative.')]]);
        }

        return $amount;
    }

    private function normalizeTextList(mixed $value): ?array
    {
        if (is_array($value)) {
            $items = array_values(array_filter(array_map(fn ($item) => trim((string) $item), $value), fn ($item) => $item !== ''));

            return $items === [] ? null : $items;
        }

        $string = $this->nullableString($value);
        if ($string === null) {
            return null;
        }

        $items = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', $string) ?: []), fn ($item) => $item !== ''));

        return $items === [] ? null : $items;
    }

    private function nullableString(mixed $value): ?string
    {
        $stringValue = is_string($value) ? trim($value) : (string) ($value ?? '');

        return $stringValue === '' ? null : $stringValue;
    }

    private function handoverRelations(): array
    {
        return ['contract.customer', 'customer', 'branch', 'lines.contractLine', 'lines.rentableItem'];
    }

    private function returnRelations(): array
    {
        return ['contract.customer', 'customer', 'branch', 'lines.contractLine', 'lines.rentableItem'];
    }
}
