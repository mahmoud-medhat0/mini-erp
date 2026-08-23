<?php

namespace App\Application\Taxes;

use App\Models\TaxPeriod;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class TaxPeriodService
{
    public function createPeriod(array $data): TaxPeriod
    {
        $label = trim($data['period_label'] ?? '');
        if ($label === '') {
            throw ValidationException::withMessages(['period_label' => ['Tax period label is required.']]);
        }

        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;

        if (! $startDate || ! $endDate) {
            throw ValidationException::withMessages(['start_date' => ['Start date and end date are required.']]);
        }

        if (Carbon::parse($startDate)->gt(Carbon::parse($endDate))) {
            throw ValidationException::withMessages(['end_date' => ['End date must be greater than or equal to start date.']]);
        }

        // Non-overlapping check
        $overlap = TaxPeriod::query()
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => ["Tax period dates ({$startDate} to {$endDate}) overlap with an existing tax period."]]);
        }

        return TaxPeriod::query()->create([
            'period_label' => $label,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'open',
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function listPeriods(): iterable
    {
        return TaxPeriod::query()
            ->with(['latestReturn'])
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function getPeriod(string $id): TaxPeriod
    {
        return TaxPeriod::query()
            ->with(['returns', 'latestReturn', 'filedReturn'])
            ->where('id', $id)
            ->firstOrFail();
    }
}
