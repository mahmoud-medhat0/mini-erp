<?php

namespace App\Application\Settings;

use App\Domain\Audit\AuditLogger;
use App\Domain\Numbering\DocumentNumberFormatter;
use App\Domain\Numbering\NumberSequenceConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NumberingSettingsService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DocumentNumberFormatter $numberFormatter,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function sequences(): Collection
    {
        return DB::table('number_sequence')
            ->select([
                'number_sequence.id',
                'number_sequence.key',
                'number_sequence.doc_type',
                'number_sequence.prefix',
                'number_sequence.include_year',
                'number_sequence.padding',
                'number_sequence.reset_policy',
                'number_sequence.last_reset_period',
                'number_sequence.next_value',
            ])
            ->orderBy('number_sequence.doc_type')
            ->get()
            ->map(fn (object $sequence): array => [
                'id' => $sequence->id,
                'key' => $sequence->key,
                'docType' => $sequence->doc_type,
                'prefix' => $sequence->prefix,
                'includeYear' => (bool) $sequence->include_year,
                'padding' => (int) $sequence->padding,
                'resetPolicy' => $sequence->reset_policy,
                'nextValue' => $this->effectiveNextValue($sequence),
                'preview' => $this->previewNumber($sequence),
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, bool $includeYear, int $actorId): string
    {
        if (DB::table('number_sequence')->where('key', $validated['key'])->exists()) {
            throw ValidationException::withMessages(['key' => __('The numbering key already exists.')]);
        }

        $id = (string) Str::uuid();

        DB::table('number_sequence')->insert([
            'id' => $id,
            ...$this->numberingPayload($validated, $includeYear),
        ]);

        $this->auditLogger->record($actorId, 'number_sequence.create', 'number_sequence', $id, after: $validated);

        return $id;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(string $sequenceId, array $validated, bool $includeYear, int $actorId): void
    {
        DB::transaction(function () use ($sequenceId, $validated, $includeYear, $actorId): void {
            $sequence = DB::table('number_sequence')
                ->where('id', $sequenceId)
                ->lockForUpdate()
                ->first();

            abort_if(! $sequence, 404);

            if ($validated['key'] !== $sequence->key) {
                throw ValidationException::withMessages(['key' => __('Numbering keys cannot be changed after creation.')]);
            }

            if ((int) $validated['next_value'] < $this->effectiveNextValue($sequence)) {
                throw ValidationException::withMessages(['next_value' => __('Cannot reduce next number below current sequence state.')]);
            }

            DB::table('number_sequence')
                ->where('id', $sequenceId)
                ->update($this->numberingPayload($validated, $includeYear));

            $this->auditLogger->record($actorId, 'number_sequence.update', 'number_sequence', $sequenceId, before: (array) $sequence, after: $validated);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function numberingPayload(array $validated, bool $includeYear): array
    {
        return [
            'key' => $validated['key'],
            'doc_type' => $validated['doc_type'],
            'prefix' => $validated['prefix'],
            'include_year' => $includeYear,
            'padding' => (int) $validated['padding'],
            'reset_policy' => $validated['reset_policy'],
            'last_reset_period' => $this->resetPeriod((string) $validated['reset_policy']),
            'next_value' => (int) $validated['next_value'],
        ];
    }

    private function previewNumber(object $sequence): string
    {
        return $this->numberFormatter->format(
            new NumberSequenceConfig(
                docType: (string) $sequence->doc_type,
                prefix: (string) $sequence->prefix,
                includeYear: (bool) $sequence->include_year,
                padding: max(1, (int) $sequence->padding),
                resetPolicy: (string) $sequence->reset_policy,
            ),
            $this->effectiveNextValue($sequence),
            ['year' => now()->year, 'month' => now()->month],
        );
    }

    private function resetPeriod(string $resetPolicy): string
    {
        return match ($resetPolicy) {
            'monthly' => now()->format('Y-m'),
            'yearly' => now()->format('Y'),
            default => 'never',
        };
    }

    private function effectiveNextValue(object $sequence): int
    {
        $currentPeriod = $this->resetPeriod((string) $sequence->reset_policy);

        if ($sequence->last_reset_period !== null && $sequence->last_reset_period !== $currentPeriod) {
            return 1;
        }

        return max(1, (int) $sequence->next_value);
    }
}
