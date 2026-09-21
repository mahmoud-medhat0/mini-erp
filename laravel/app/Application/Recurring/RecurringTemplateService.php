<?php

namespace App\Application\Recurring;

use App\Application\Expenses\ExpenseService;
use App\Domain\Audit\AuditLogger;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\RecurringTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 31 - Recurring transactions engine (PHASE_25_GAP_CLOSURE_DECISION_PACK.md
 * §10, decisions 3-5): a generic standalone template scoped to direct
 * expenses only in this first slice, generating a DRAFT expense for human
 * review each time it comes due - never auto-posted. Generation reuses
 * ExpenseService::create() end to end so every accounting/tax rule the
 * manual expense form enforces (account currency, active category, GRNI-style
 * validation) applies identically to generated drafts; this service never
 * duplicates that logic.
 */
class RecurringTemplateService
{
    public const DOCUMENT_TYPES = ['expense'];

    public const FREQUENCIES = ['weekly', 'monthly', 'quarterly', 'yearly'];

    public function __construct(
        private readonly ExpenseService $expenseService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(array $data, ?int $actorId = null): RecurringTemplate
    {
        return DB::transaction(function () use ($data, $actorId): RecurringTemplate {
            $payload = $this->validatedPayload($data);

            /** @var RecurringTemplate $template */
            $template = RecurringTemplate::query()->create([
                ...$payload,
                'next_run_date' => $payload['start_date'],
                'status' => 'active',
                'generated_count' => 0,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->auditLogger->record($actorId, 'recurring_template.create', 'recurring_template', $template->id, after: $template->fresh()->toArray());

            return $template->fresh();
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): RecurringTemplate
    {
        return DB::transaction(function () use ($id, $data, $actorId): RecurringTemplate {
            /** @var RecurringTemplate $template */
            $template = RecurringTemplate::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if (! in_array($template->status, ['active', 'paused'], true)) {
                throw ValidationException::withMessages(['status' => [__('Only active or paused templates can be edited.')]]);
            }

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== (int) $template->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The template was modified by another user. Please refresh and try again.')]]);
            }

            $payload = $this->validatedPayload([
                'code' => $data['code'] ?? $template->code,
                'name' => $data['name'] ?? $template->getTranslations('name'),
                'document_type' => $data['document_type'] ?? $template->document_type,
                'frequency' => $data['frequency'] ?? $template->frequency,
                'interval_count' => $data['interval_count'] ?? $template->interval_count,
                'start_date' => $data['start_date'] ?? $template->start_date?->format('Y-m-d'),
                'end_date' => array_key_exists('end_date', $data) ? $data['end_date'] : $template->end_date?->format('Y-m-d'),
                'template_payload' => $data['template_payload'] ?? $template->template_payload,
            ], $template->id);

            $before = $template->toArray();
            $template->update([
                ...$payload,
                'updated_by' => $actorId,
                'lock_version' => ((int) $template->lock_version) + 1,
            ]);

            $this->auditLogger->record($actorId, 'recurring_template.update', 'recurring_template', $template->id, before: $before, after: $template->fresh()->toArray());

            return $template->fresh();
        });
    }

    public function pause(string $id, ?int $actorId = null): RecurringTemplate
    {
        return $this->transition($id, 'active', 'paused', 'recurring_template.pause', $actorId);
    }

    public function resume(string $id, ?int $actorId = null): RecurringTemplate
    {
        return DB::transaction(function () use ($id, $actorId): RecurringTemplate {
            /** @var RecurringTemplate $template */
            $template = RecurringTemplate::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($template->status !== 'paused') {
                throw ValidationException::withMessages(['status' => [__('Only paused templates can be resumed.')]]);
            }

            $today = Carbon::now()->toDateString();
            $before = $template->toArray();
            $template->update([
                'status' => 'active',
                'last_error' => null,
                'next_run_date' => $template->next_run_date && $template->next_run_date->toDateString() >= $today
                    ? $template->next_run_date
                    : $today,
                'updated_by' => $actorId,
                'lock_version' => $template->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'recurring_template.resume', 'recurring_template', $template->id, before: $before, after: $template->fresh()->toArray());

            return $template->fresh();
        });
    }

    public function cancel(string $id, ?int $actorId = null): RecurringTemplate
    {
        return DB::transaction(function () use ($id, $actorId): RecurringTemplate {
            /** @var RecurringTemplate $template */
            $template = RecurringTemplate::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if (! in_array($template->status, ['active', 'paused'], true)) {
                throw ValidationException::withMessages(['status' => [__('Only active or paused templates can be cancelled.')]]);
            }

            $before = $template->toArray();
            $template->update([
                'status' => 'cancelled',
                'updated_by' => $actorId,
                'lock_version' => $template->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, 'recurring_template.cancel', 'recurring_template', $template->id, before: $before, after: $template->fresh()->toArray());

            return $template->fresh();
        });
    }

    public function delete(string $id, ?int $actorId = null): void
    {
        DB::transaction(function () use ($id, $actorId): void {
            /** @var RecurringTemplate $template */
            $template = RecurringTemplate::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($template->generated_count > 0) {
                throw ValidationException::withMessages(['template' => [__('Templates that have already generated documents cannot be deleted. Cancel it instead.')]]);
            }

            $before = $template->toArray();
            $template->delete();
            $this->auditLogger->record($actorId, 'recurring_template.delete', 'recurring_template', $id, before: $before);
        });
    }

    /**
     * Generates a draft document for every active template due on or before
     * $asOf. Each occurrence is generated in its own transaction so one
     * template's failure never blocks the rest of the batch. A failure
     * pauses that template (with the error recorded) rather than retrying
     * silently forever or skipping the occurrence unnoticed.
     *
     * @return array{generated: int, paused: int, template_ids: list<string>}
     */
    public function generateDue(?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? Carbon::now())->toDateString();

        $dueTemplateIds = RecurringTemplate::query()
            ->where('status', 'active')
            ->where('next_run_date', '<=', $asOf)
            ->orderBy('next_run_date')
            ->pluck('id');

        $generated = 0;
        $paused = 0;
        $generatedTemplateIds = [];

        foreach ($dueTemplateIds as $templateId) {
            try {
                $result = DB::transaction(function () use ($templateId): string {
                    /** @var RecurringTemplate $template */
                    $template = RecurringTemplate::query()->whereKey($templateId)->lockForUpdate()->firstOrFail();

                    if ($template->status !== 'active') {
                        return 'skipped';
                    }

                    $occurrenceDate = $template->next_run_date->format('Y-m-d');
                    $expense = $this->generateExpenseDraft($template, $occurrenceDate);

                    $nextRunDate = $this->nextOccurrence(Carbon::parse($occurrenceDate), $template->frequency, $template->interval_count);
                    $completed = $template->end_date && $nextRunDate->toDateString() > $template->end_date->format('Y-m-d');

                    $template->update([
                        'last_generated_date' => $occurrenceDate,
                        'next_run_date' => $nextRunDate->toDateString(),
                        'generated_count' => $template->generated_count + 1,
                        'status' => $completed ? 'completed' : 'active',
                        'last_error' => null,
                    ]);

                    $this->auditLogger->record(null, 'recurring_template.generate', 'recurring_template', $template->id, after: ['expense_id' => $expense->id, 'occurrence_date' => $occurrenceDate]);

                    return 'generated';
                });

                if ($result === 'generated') {
                    $generated++;
                    $generatedTemplateIds[] = $templateId;
                }
            } catch (\Throwable $exception) {
                $paused++;
                RecurringTemplate::query()->whereKey($templateId)->update([
                    'status' => 'paused',
                    'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                ]);
                $this->auditLogger->record(null, 'recurring_template.generation_failed', 'recurring_template', $templateId, after: ['error' => $exception->getMessage()]);
            }
        }

        return ['generated' => $generated, 'paused' => $paused, 'template_ids' => $generatedTemplateIds];
    }

    private function generateExpenseDraft(RecurringTemplate $template, string $occurrenceDate): Expense
    {
        $payload = $template->template_payload;

        $expense = $this->expenseService->create([
            'expense_date' => $occurrenceDate,
            'branch_id' => $payload['branch_id'] ?? null,
            'settlement_method' => $payload['settlement_method'],
            'cash_account_id' => $payload['cash_account_id'] ?? null,
            'bank_account_id' => $payload['bank_account_id'] ?? null,
            'supplier_id' => $payload['supplier_id'] ?? null,
            'payee_name' => $payload['payee_name'] ?? null,
            'currency' => $payload['currency'],
            'reference' => $payload['reference'] ?? null,
            'description' => $payload['description'] ?? null,
            'lines' => [[
                'expense_category_id' => $payload['expense_category_id'],
                'expense_account_id' => $payload['expense_account_id'] ?? null,
                'description' => $payload['description'] ?? null,
                'quantity_e6' => 1_000_000,
                'unit_amount_minor' => $payload['unit_amount_minor'],
                'tax_code_id' => $payload['tax_code_id'] ?? null,
            ]],
        ], null);

        Expense::query()->whereKey($expense->id)->update(['recurring_template_id' => $template->id]);

        return $expense;
    }

    private function nextOccurrence(Carbon $date, string $frequency, int $intervalCount): Carbon
    {
        return match ($frequency) {
            'weekly' => $date->copy()->addWeeks($intervalCount),
            'monthly' => $date->copy()->addMonthsNoOverflow($intervalCount),
            'quarterly' => $date->copy()->addMonthsNoOverflow($intervalCount * 3),
            'yearly' => $date->copy()->addYearsNoOverflow($intervalCount),
            default => throw ValidationException::withMessages(['frequency' => [__('Invalid recurrence frequency.')]]),
        };
    }

    private function transition(string $id, string $fromStatus, string $toStatus, string $auditAction, ?int $actorId): RecurringTemplate
    {
        return DB::transaction(function () use ($id, $fromStatus, $toStatus, $auditAction, $actorId): RecurringTemplate {
            /** @var RecurringTemplate $template */
            $template = RecurringTemplate::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($template->status !== $fromStatus) {
                throw ValidationException::withMessages(['status' => [__('Template must be :status for this action.', ['status' => $fromStatus])]]);
            }

            $before = $template->toArray();
            $template->update([
                'status' => $toStatus,
                'updated_by' => $actorId,
                'lock_version' => $template->lock_version + 1,
            ]);

            $this->auditLogger->record($actorId, $auditAction, 'recurring_template', $template->id, before: $before, after: $template->fresh()->toArray());

            return $template->fresh();
        });
    }

    private function validatedPayload(array $data, ?string $ignoreId = null): array
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '' || ! preg_match('/^[A-Z0-9._-]+$/', $code)) {
            throw ValidationException::withMessages(['code' => [__('Template code is required and may contain letters, numbers, dots, underscores, or dashes.')]]);
        }

        $exists = RecurringTemplate::query()
            ->where('code', $code)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['code' => [__('Template code already exists.')]]);
        }

        $translations = is_array($data['name'] ?? null) ? $data['name'] : [];
        $en = trim((string) ($translations['en'] ?? ''));
        $ar = trim((string) ($translations['ar'] ?? $en));
        if ($en === '') {
            throw ValidationException::withMessages(['name.en' => [__('English template name is required.')]]);
        }

        $documentType = (string) ($data['document_type'] ?? 'expense');
        if (! in_array($documentType, self::DOCUMENT_TYPES, true)) {
            throw ValidationException::withMessages(['document_type' => [__('Only expense templates are supported in this release.')]]);
        }

        $frequency = (string) ($data['frequency'] ?? '');
        if (! in_array($frequency, self::FREQUENCIES, true)) {
            throw ValidationException::withMessages(['frequency' => [__('Invalid recurrence frequency.')]]);
        }

        $intervalCount = (int) ($data['interval_count'] ?? 1);
        if ($intervalCount < 1) {
            throw ValidationException::withMessages(['interval_count' => [__('Recurrence interval must be at least 1.')]]);
        }

        $startDate = (string) ($data['start_date'] ?? '');
        if ($startDate === '') {
            throw ValidationException::withMessages(['start_date' => [__('Start date is required.')]]);
        }

        $endDate = $data['end_date'] ?? null;
        if ($endDate !== null && $endDate !== '' && $endDate < $startDate) {
            throw ValidationException::withMessages(['end_date' => [__('End date must be on or after the start date.')]]);
        }

        $templatePayload = $this->validateTemplatePayload($data['template_payload'] ?? []);

        return [
            'code' => $code,
            'name' => ['en' => $en, 'ar' => $ar === '' ? $en : $ar],
            'document_type' => $documentType,
            'frequency' => $frequency,
            'interval_count' => $intervalCount,
            'start_date' => $startDate,
            'end_date' => ($endDate === '' ? null : $endDate),
            'template_payload' => $templatePayload,
        ];
    }

    private function validateTemplatePayload(array $payload): array
    {
        $settlementMethod = (string) ($payload['settlement_method'] ?? '');
        if (! in_array($settlementMethod, ['payable', 'cash', 'bank'], true)) {
            throw ValidationException::withMessages(['template_payload.settlement_method' => [__('Settlement method must be payable, cash, or bank.')]]);
        }

        if ($settlementMethod === 'payable' && empty($payload['supplier_id'])) {
            throw ValidationException::withMessages(['template_payload.supplier_id' => [__('Payable templates require a supplier.')]]);
        }
        if ($settlementMethod === 'cash' && empty($payload['cash_account_id'])) {
            throw ValidationException::withMessages(['template_payload.cash_account_id' => [__('Cash templates require a cash account.')]]);
        }
        if ($settlementMethod === 'bank' && empty($payload['bank_account_id'])) {
            throw ValidationException::withMessages(['template_payload.bank_account_id' => [__('Bank templates require a bank account.')]]);
        }

        $currency = (string) ($payload['currency'] ?? '');
        if ($currency === '') {
            throw ValidationException::withMessages(['template_payload.currency' => [__('Currency is required.')]]);
        }

        $categoryId = (string) ($payload['expense_category_id'] ?? '');
        if (! ExpenseCategory::query()->whereKey($categoryId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['template_payload.expense_category_id' => [__('Selected expense category is inactive or missing.')]]);
        }

        $unitAmountMinor = (int) ($payload['unit_amount_minor'] ?? 0);
        if ($unitAmountMinor <= 0) {
            throw ValidationException::withMessages(['template_payload.unit_amount_minor' => [__('Amount must be greater than zero.')]]);
        }

        return [
            'branch_id' => $payload['branch_id'] ?? null,
            'settlement_method' => $settlementMethod,
            'supplier_id' => $payload['supplier_id'] ?? null,
            'cash_account_id' => $payload['cash_account_id'] ?? null,
            'bank_account_id' => $payload['bank_account_id'] ?? null,
            'payee_name' => $payload['payee_name'] ?? null,
            'currency' => $currency,
            'reference' => $payload['reference'] ?? null,
            'description' => $payload['description'] ?? null,
            'expense_category_id' => $categoryId,
            'expense_account_id' => $payload['expense_account_id'] ?? null,
            'unit_amount_minor' => $unitAmountMinor,
            'tax_code_id' => $payload['tax_code_id'] ?? null,
        ];
    }
}
