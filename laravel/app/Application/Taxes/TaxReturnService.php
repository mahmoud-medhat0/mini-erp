<?php

namespace App\Application\Taxes;

use App\Application\Reports\VatSummaryReportService;
use App\Domain\Audit\AuditLogger;
use App\Models\TaxPeriod;
use App\Models\TaxReturn;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaxReturnService
{
    public function __construct(
        private readonly VatSummaryReportService $vatSummaryReportService,
        private readonly NumberSequenceAllocator $numberAllocator,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function generateDraftReturn(string $taxPeriodId, ?string $actorId = null): TaxReturn
    {
        return DB::transaction(function () use ($taxPeriodId, $actorId): TaxReturn {
            /** @var TaxPeriod $period */
            $period = TaxPeriod::query()->where('id', $taxPeriodId)->lockForUpdate()->firstOrFail();

            if ($period->status === 'filed') {
                throw ValidationException::withMessages(['tax_period' => [__('Cannot generate draft tax return for a filed tax period.')]]);
            }

            $startDateStr = is_string($period->start_date) ? $period->start_date : $period->start_date->format('Y-m-d');
            $endDateStr = is_string($period->end_date) ? $period->end_date : $period->end_date->format('Y-m-d');

            $summaryData = $this->vatSummaryReportService->generate([
                'from_date' => $startDateStr,
                'to_date' => $endDateStr,
            ]);

            /** @var TaxReturn|null $existingDraft */
            $existingDraft = TaxReturn::query()
                ->where('tax_period_id', $period->id)
                ->where('status', 'draft')
                ->first();

            $seq = $this->numberAllocator->nextValue('tax_return');
            $number = $existingDraft?->number ?? ('TRN-'.date('Y').'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT));

            $outputTaxMinor = (int) $summaryData['summary']['total_output_tax_minor'];
            $inputTaxMinor = (int) $summaryData['summary']['total_input_tax_minor'];
            $netPayableMinor = (int) $summaryData['summary']['net_vat_payable_minor'];

            $taxReturn = TaxReturn::query()->updateOrCreate(
                [
                    'tax_period_id' => $period->id,
                    'status' => 'draft',
                ],
                [
                    'number' => $number,
                    'output_tax_minor' => $outputTaxMinor,
                    'input_tax_minor' => $inputTaxMinor,
                    'net_payable_minor' => $netPayableMinor,
                    'snapshot' => $summaryData,
                    'generated_at' => Carbon::now(),
                    'generated_by' => $actorId,
                ]
            );

            if ($period->status === 'open') {
                $period->update(['status' => 'draft_return']);
            }

            $this->auditLogger->record(
                actorId: (int) $actorId,
                action: 'tax_return.generate_draft',
                entityType: 'tax_return',
                entityId: $taxReturn->id,
                before: null,
                after: $taxReturn->toArray(),
            );

            return $taxReturn;
        });
    }

    public function fileReturn(string $taxReturnId, ?string $actorId = null, ?string $notes = null): TaxReturn
    {
        return DB::transaction(function () use ($taxReturnId, $actorId, $notes): TaxReturn {
            /** @var TaxReturn $taxReturn */
            $taxReturn = TaxReturn::query()->where('id', $taxReturnId)->lockForUpdate()->firstOrFail();

            /** @var TaxPeriod $period */
            $period = TaxPeriod::query()->where('id', $taxReturn->tax_period_id)->lockForUpdate()->firstOrFail();

            // Idempotent retry: if already filed, return existing filed return
            if ($taxReturn->status === 'filed') {
                return $taxReturn;
            }

            if ($period->status === 'filed') {
                $alreadyFiledReturn = TaxReturn::query()
                    ->where('tax_period_id', $period->id)
                    ->where('status', 'filed')
                    ->first();
                if ($alreadyFiledReturn !== null) {
                    return $alreadyFiledReturn;
                }
                throw ValidationException::withMessages(['tax_period' => [__('Tax period is already filed.')]]);
            }

            $beforeReturn = $taxReturn->toArray();
            $beforePeriod = $period->toArray();

            $now = Carbon::now();

            $taxReturn->update([
                'status' => 'filed',
                'filed_at' => $now,
                'filed_by' => $actorId,
            ]);

            $period->update([
                'status' => 'filed',
                'filed_at' => $now,
                'filed_by' => $actorId,
                'file_reference' => $taxReturn->number,
                'notes' => $notes ?: $period->notes,
            ]);

            $this->auditLogger->record(
                actorId: (int) $actorId,
                action: 'tax_return.file',
                entityType: 'tax_return',
                entityId: $taxReturn->id,
                before: $beforeReturn,
                after: $taxReturn->toArray(),
            );

            return $taxReturn->fresh(['period']);
        });
    }

    public function getReturn(string $id): TaxReturn
    {
        return TaxReturn::query()
            ->with(['period'])
            ->where('id', $id)
            ->firstOrFail();
    }
}
