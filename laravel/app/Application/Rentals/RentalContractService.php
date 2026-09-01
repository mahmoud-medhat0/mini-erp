<?php

namespace App\Application\Rentals;

use App\Application\Support\CurrencyInput;
use App\Domain\Audit\AuditLogger;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\RentableItem;
use App\Models\RentableItemStatusEvent;
use App\Models\RentalContract;
use App\Models\RentalContractLine;
use App\Models\RentalContractStatusEvent;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RentalContractService
{
    public const STATUSES = ['draft', 'submitted', 'approved', 'active', 'completed', 'cancelled'];

    public const BILLING_CYCLES = ['daily', 'weekly', 'monthly', 'fixed'];

    public const RATE_TYPES = ['daily', 'weekly', 'monthly', 'fixed'];

    private const RESERVABLE_ITEM_STATUSES = ['available', 'returned'];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NumberSequenceAllocator $numberAllocator,
    ) {}

    public function create(array $data, ?int $actorId = null): RentalContract
    {
        return DB::transaction(function () use ($data, $actorId): RentalContract {
            $payload = $this->validatedPayload($data);

            /** @var RentalContract $contract */
            $contract = RentalContract::query()->create([
                ...$payload['header'],
                'status' => 'draft',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->replaceLines($contract, $payload['lines']);
            $this->recordContractEvent($contract, null, 'draft', 'created', $data['reason'] ?? null, $actorId);
            $this->auditLogger->record($actorId, 'rental_contract.create', 'rental_contract', $contract->id, after: $contract->fresh($this->relations())->toArray());

            return $contract->fresh($this->relations());
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): RentalContract
    {
        return DB::transaction(function () use ($id, $data, $actorId): RentalContract {
            /** @var RentalContract $contract */
            $contract = RentalContract::query()->with('lines')->whereKey($id)->lockForUpdate()->firstOrFail();

            if ((int) ($data['lock_version'] ?? 0) !== (int) $contract->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The rental contract was modified by another user. Please refresh and try again.')]]);
            }

            if ($contract->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft rental contracts can be updated.')]]);
            }

            $before = $contract->fresh($this->relations())->toArray();
            $payload = $this->validatedPayload([
                'customer_id' => $data['customer_id'] ?? $contract->customer_id,
                'branch_id' => array_key_exists('branch_id', $data) ? $data['branch_id'] : $contract->branch_id,
                'contract_date' => $data['contract_date'] ?? $contract->contract_date?->format('Y-m-d'),
                'start_date' => $data['start_date'] ?? $contract->start_date?->format('Y-m-d'),
                'expected_end_date' => $data['expected_end_date'] ?? $contract->expected_end_date?->format('Y-m-d'),
                'currency' => $data['currency'] ?? $contract->currency,
                'billing_cycle' => $data['billing_cycle'] ?? $contract->billing_cycle,
                'reference' => array_key_exists('reference', $data) ? $data['reference'] : $contract->reference,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $contract->notes,
                'lines' => $data['lines'] ?? $contract->lines->map(fn (RentalContractLine $line): array => [
                    'rentable_item_id' => $line->rentable_item_id,
                    'description' => $line->getTranslations('description'),
                    'start_date' => $line->start_date?->format('Y-m-d'),
                    'end_date' => $line->end_date?->format('Y-m-d'),
                    'rate_type' => $line->rate_type,
                    'rate_minor' => $line->rate_minor,
                    'estimated_units' => $line->estimated_units,
                    'deposit_minor' => $line->deposit_minor,
                    'notes' => $line->notes,
                ])->all(),
            ], $contract->id);

            $contract->update([
                ...$payload['header'],
                'updated_by' => $actorId,
                'lock_version' => ((int) $contract->lock_version) + 1,
            ]);
            $contract->lines()->delete();
            $this->replaceLines($contract, $payload['lines']);

            $this->recordContractEvent($contract, 'draft', 'draft', 'details_updated', $data['reason'] ?? null, $actorId);
            $this->auditLogger->record($actorId, 'rental_contract.update', 'rental_contract', $contract->id, before: $before, after: $contract->fresh($this->relations())->toArray());

            return $contract->fresh($this->relations());
        });
    }

    public function submit(string $id, ?int $actorId = null): RentalContract
    {
        return DB::transaction(function () use ($id, $actorId): RentalContract {
            $contract = $this->lockedContract($id);

            if (in_array($contract->status, ['submitted', 'approved', 'active'], true)) {
                return $contract->fresh($this->relations());
            }

            if ($contract->status !== 'draft') {
                throw ValidationException::withMessages(['status' => [__('Only draft rental contracts can be submitted.')]]);
            }

            $items = $this->lockedLineItems($contract);
            $this->assertItemsHaveStatuses($items, self::RESERVABLE_ITEM_STATUSES, 'rentable_item_id', __('Only available rental items can be reserved.'));

            $before = $contract->fresh($this->relations())->toArray();
            if (! $contract->number) {
                $contract->number = $this->numberAllocator->nextNumber('rental.contract', 'RENT', $contract->contract_date);
            }

            $contract->status = 'submitted';
            $contract->submitted_by = $actorId;
            $contract->submitted_at = now();
            $contract->updated_by = $actorId;
            $contract->lock_version = ((int) $contract->lock_version) + 1;
            $contract->save();

            $this->transitionItems($contract, $items, 'reserved', 'rental_contract.submit', $actorId);
            $this->recordContractEvent($contract, 'draft', 'submitted', 'submitted', null, $actorId);
            $this->auditLogger->record($actorId, 'rental_contract.submit', 'rental_contract', $contract->id, before: $before, after: $contract->fresh($this->relations())->toArray());

            return $contract->fresh($this->relations());
        });
    }

    public function approve(string $id, ?int $actorId = null): RentalContract
    {
        return DB::transaction(function () use ($id, $actorId): RentalContract {
            $contract = $this->lockedContract($id);

            if (in_array($contract->status, ['approved', 'active'], true)) {
                return $contract->fresh($this->relations());
            }

            if ($contract->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => [__('Only submitted rental contracts can be approved.')]]);
            }

            $items = $this->lockedLineItems($contract);
            $this->assertItemsHaveStatuses($items, ['reserved'], 'rentable_item_id', __('Only reserved rental items can be allocated.'));
            $before = $contract->fresh($this->relations())->toArray();

            $contract->status = 'approved';
            $contract->approved_by = $actorId;
            $contract->approved_at = now();
            $contract->updated_by = $actorId;
            $contract->lock_version = ((int) $contract->lock_version) + 1;
            $contract->save();

            $this->transitionItems($contract, $items, 'allocated', 'rental_contract.approve', $actorId);
            $this->recordContractEvent($contract, 'submitted', 'approved', 'approved', null, $actorId);
            $this->auditLogger->record($actorId, 'rental_contract.approve', 'rental_contract', $contract->id, before: $before, after: $contract->fresh($this->relations())->toArray());

            return $contract->fresh($this->relations());
        });
    }

    public function activate(string $id, ?int $actorId = null): RentalContract
    {
        return DB::transaction(function () use ($id, $actorId): RentalContract {
            $contract = $this->lockedContract($id);

            if ($contract->status === 'active') {
                return $contract->fresh($this->relations());
            }

            if ($contract->status !== 'approved') {
                throw ValidationException::withMessages(['status' => [__('Only approved rental contracts can be activated.')]]);
            }

            $items = $this->lockedLineItems($contract);
            $this->assertItemsHaveStatuses($items, ['allocated'], 'rentable_item_id', __('Only allocated rental items can be activated.'));
            $before = $contract->fresh($this->relations())->toArray();

            $contract->status = 'active';
            $contract->activated_by = $actorId;
            $contract->activated_at = now();
            $contract->updated_by = $actorId;
            $contract->lock_version = ((int) $contract->lock_version) + 1;
            $contract->save();

            $this->transitionItems($contract, $items, 'rented', 'rental_contract.activate', $actorId);
            $this->recordContractEvent($contract, 'approved', 'active', 'activated', null, $actorId);
            $this->auditLogger->record($actorId, 'rental_contract.activate', 'rental_contract', $contract->id, before: $before, after: $contract->fresh($this->relations())->toArray());

            return $contract->fresh($this->relations());
        });
    }

    public function cancel(string $id, ?int $actorId = null): RentalContract
    {
        return DB::transaction(function () use ($id, $actorId): RentalContract {
            $contract = $this->lockedContract($id);

            if ($contract->status === 'cancelled') {
                return $contract->fresh($this->relations());
            }

            if (in_array($contract->status, ['active', 'completed'], true)) {
                throw ValidationException::withMessages(['status' => [__('Active or completed rental contracts require the return workflow instead of cancellation.')]]);
            }

            $items = $this->lockedLineItems($contract);
            $before = $contract->fresh($this->relations())->toArray();
            $fromStatus = $contract->status;

            $contract->status = 'cancelled';
            $contract->cancelled_by = $actorId;
            $contract->cancelled_at = now();
            $contract->updated_by = $actorId;
            $contract->lock_version = ((int) $contract->lock_version) + 1;
            $contract->save();

            if (in_array($fromStatus, ['submitted', 'approved'], true)) {
                $this->transitionItems($contract, $items, 'available', 'rental_contract.cancel', $actorId, ['reserved', 'allocated']);
            }

            $this->recordContractEvent($contract, $fromStatus, 'cancelled', 'cancelled', null, $actorId);
            $this->auditLogger->record($actorId, 'rental_contract.cancel', 'rental_contract', $contract->id, before: $before, after: $contract->fresh($this->relations())->toArray());

            return $contract->fresh($this->relations());
        });
    }

    private function validatedPayload(array $data, ?string $ignoreId = null): array
    {
        $customerId = $this->requiredUuid($data['customer_id'] ?? null, 'customer_id');
        if (! Customer::query()->whereKey($customerId)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['customer_id' => [__('Selected customer is inactive or missing.')]]);
        }

        $branchId = $this->nullableUuid($data['branch_id'] ?? null, 'branch_id');
        if ($branchId !== null && ! Branch::query()->whereKey($branchId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['branch_id' => [__('Selected branch is inactive or missing.')]]);
        }

        $currency = CurrencyInput::required($data['currency'] ?? null);
        if (! Currency::query()->where('code', $currency)->exists()) {
            throw ValidationException::withMessages(['currency' => [__('Selected currency is missing from the currency registry.')]]);
        }

        $contractDate = $this->requiredDate($data['contract_date'] ?? null, 'contract_date');
        $startDate = $this->requiredDate($data['start_date'] ?? null, 'start_date');
        $expectedEndDate = $this->requiredDate($data['expected_end_date'] ?? null, 'expected_end_date');
        if ($expectedEndDate < $startDate) {
            throw ValidationException::withMessages(['expected_end_date' => [__('Expected end date cannot be before start date.')]]);
        }

        $billingCycle = (string) ($data['billing_cycle'] ?? 'monthly');
        if (! in_array($billingCycle, self::BILLING_CYCLES, true)) {
            throw ValidationException::withMessages(['billing_cycle' => [__('Invalid billing cycle.')]]);
        }

        $lines = $this->validatedLines($data['lines'] ?? [], $currency, $startDate, $expectedEndDate);
        $estimatedRentMinor = 0;
        $depositMinor = 0;
        foreach ($lines as $line) {
            $estimatedRentMinor = $this->safeAdd($estimatedRentMinor, $line['estimated_amount_minor'], 'lines');
            $depositMinor = $this->safeAdd($depositMinor, $line['deposit_minor'], 'lines');
        }

        return [
            'header' => [
                'customer_id' => $customerId,
                'branch_id' => $branchId,
                'contract_date' => $contractDate,
                'start_date' => $startDate,
                'expected_end_date' => $expectedEndDate,
                'actual_end_date' => null,
                'currency' => $currency,
                'billing_cycle' => $billingCycle,
                'estimated_rent_minor' => $estimatedRentMinor,
                'deposit_minor' => $depositMinor,
                'total_estimated_minor' => $this->safeAdd($estimatedRentMinor, $depositMinor, 'total_estimated_minor'),
                'reference' => $this->nullableString($data['reference'] ?? null),
                'notes' => $this->nullableString($data['notes'] ?? null),
            ],
            'lines' => $lines,
        ];
    }

    private function validatedLines(mixed $rawLines, string $currency, string $headerStartDate, string $headerEndDate): array
    {
        if (! is_array($rawLines) || count($rawLines) === 0) {
            throw ValidationException::withMessages(['lines' => [__('Rental contract must have at least one line.')]]);
        }

        $seenItems = [];
        $lines = [];

        foreach (array_values($rawLines) as $index => $line) {
            if (! is_array($line)) {
                throw ValidationException::withMessages(["lines.{$index}" => [__('Invalid rental contract line.')]]);
            }

            $itemId = $this->requiredUuid($line['rentable_item_id'] ?? null, "lines.{$index}.rentable_item_id");
            if (in_array($itemId, $seenItems, true)) {
                throw ValidationException::withMessages(["lines.{$index}.rentable_item_id" => [__('A rentable item can appear only once on the same contract.')]]);
            }
            $seenItems[] = $itemId;

            /** @var RentableItem|null $item */
            $item = RentableItem::query()->whereKey($itemId)->first();
            if (! $item || ! $item->is_active || ! in_array($item->status, self::RESERVABLE_ITEM_STATUSES, true)) {
                throw ValidationException::withMessages(["lines.{$index}.rentable_item_id" => [__('Selected rentable item is not available for reservation.')]]);
            }
            if ($item->currency !== $currency) {
                throw ValidationException::withMessages(["lines.{$index}.rentable_item_id" => [__('Rentable item currency must match contract currency.')]]);
            }

            $lineStartDate = $this->requiredDate($line['start_date'] ?? $headerStartDate, "lines.{$index}.start_date");
            $lineEndDate = $this->requiredDate($line['end_date'] ?? $headerEndDate, "lines.{$index}.end_date");
            if ($lineEndDate < $lineStartDate || $lineStartDate < $headerStartDate || $lineEndDate > $headerEndDate) {
                throw ValidationException::withMessages(["lines.{$index}.end_date" => [__('Line dates must be within the rental contract date range.')]]);
            }

            $rateType = (string) ($line['rate_type'] ?? 'monthly');
            if (! in_array($rateType, self::RATE_TYPES, true)) {
                throw ValidationException::withMessages(["lines.{$index}.rate_type" => [__('Invalid rental rate type.')]]);
            }

            $rateMinor = $this->amountMinor($line['rate_minor'] ?? 0, "lines.{$index}.rate_minor");
            $estimatedUnits = (int) ($line['estimated_units'] ?? 1);
            if ($estimatedUnits < 1) {
                throw ValidationException::withMessages(["lines.{$index}.estimated_units" => [__('Estimated units must be at least 1.')]]);
            }

            $depositMinor = $this->amountMinor($line['deposit_minor'] ?? 0, "lines.{$index}.deposit_minor");
            $estimatedAmountMinor = $this->safeMultiply($rateMinor, $estimatedUnits, "lines.{$index}.estimated_units");

            $lines[] = [
                'rentable_item_id' => $itemId,
                'description' => $this->normalizeOptionalTranslations($line['description'] ?? null),
                'start_date' => $lineStartDate,
                'end_date' => $lineEndDate,
                'rate_type' => $rateType,
                'rate_minor' => $rateMinor,
                'estimated_units' => $estimatedUnits,
                'estimated_amount_minor' => $estimatedAmountMinor,
                'deposit_minor' => $depositMinor,
                'notes' => $this->nullableString($line['notes'] ?? null),
            ];
        }

        return $lines;
    }

    private function replaceLines(RentalContract $contract, array $lines): void
    {
        $lineNo = 1;
        foreach ($lines as $line) {
            RentalContractLine::query()->create([
                'rental_contract_id' => $contract->id,
                'line_no' => $lineNo++,
                ...$line,
            ]);
        }
    }

    private function lockedContract(string $id): RentalContract
    {
        /** @var RentalContract $contract */
        $contract = RentalContract::query()->with('lines')->whereKey($id)->lockForUpdate()->firstOrFail();

        if ($contract->lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => [__('Rental contract must have at least one line.')]]);
        }

        return $contract;
    }

    /**
     * @return Collection<string, RentableItem>
     */
    private function lockedLineItems(RentalContract $contract): Collection
    {
        $ids = $contract->lines->pluck('rentable_item_id')->unique()->sort()->values();

        return RentableItem::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    private function assertItemsHaveStatuses(Collection $items, array $allowedStatuses, string $field, string $message): void
    {
        foreach ($items as $item) {
            if (! in_array($item->status, $allowedStatuses, true)) {
                throw ValidationException::withMessages([$field => [$message]]);
            }
        }
    }

    private function transitionItems(
        RentalContract $contract,
        Collection $items,
        string $toStatus,
        string $reason,
        ?int $actorId,
        ?array $onlyFromStatuses = null
    ): void {
        foreach ($items as $item) {
            if ($onlyFromStatuses !== null && ! in_array($item->status, $onlyFromStatuses, true)) {
                continue;
            }

            $fromStatus = $item->status;
            if ($fromStatus === $toStatus) {
                continue;
            }

            $before = $item->toArray();
            $item->status = $toStatus;
            $item->updated_by = $actorId;
            $item->lock_version = ((int) $item->lock_version) + 1;
            $item->save();

            RentableItemStatusEvent::query()->create([
                'rentable_item_id' => $item->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'event_type' => 'status_changed',
                'reason' => $reason.': '.($contract->number ?: $contract->id),
                'actor_id' => $actorId,
            ]);

            $this->auditLogger->record($actorId, 'rentable_item.status_changed', 'rentable_item', $item->id, before: $before, after: $item->fresh()->toArray());
        }
    }

    private function recordContractEvent(RentalContract $contract, ?string $fromStatus, string $toStatus, string $eventType, mixed $reason, ?int $actorId): void
    {
        RentalContractStatusEvent::query()->create([
            'rental_contract_id' => $contract->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'event_type' => $eventType,
            'reason' => $this->nullableString($reason),
            'actor_id' => $actorId,
        ]);
    }

    private function requiredUuid(mixed $value, string $field): string
    {
        $value = $this->nullableString($value);
        if ($value === null || ! Str::isUuid($value)) {
            throw ValidationException::withMessages([$field => [__('Invalid or missing reference.')]]);
        }

        return $value;
    }

    private function nullableUuid(mixed $value, string $field): ?string
    {
        $value = $this->nullableString($value);
        if ($value !== null && ! Str::isUuid($value)) {
            throw ValidationException::withMessages([$field => [__('Invalid reference.')]]);
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

    private function safeMultiply(int $amount, int $units, string $field): int
    {
        if ($amount > 0 && $units > intdiv(PHP_INT_MAX, $amount)) {
            throw ValidationException::withMessages([$field => [__('Calculated amount is too large.')]]);
        }

        return $amount * $units;
    }

    private function safeAdd(int $left, int $right, string $field): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            throw ValidationException::withMessages([$field => [__('Calculated total is too large.')]]);
        }

        return $left + $right;
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

    private function nullableString(mixed $value): ?string
    {
        $stringValue = is_string($value) ? trim($value) : (string) ($value ?? '');

        return $stringValue === '' ? null : $stringValue;
    }

    private function relations(): array
    {
        return ['customer', 'branch', 'currencyRef', 'lines.rentableItem.branch', 'lines.rentableItem.warehouse', 'statusEvents'];
    }
}
