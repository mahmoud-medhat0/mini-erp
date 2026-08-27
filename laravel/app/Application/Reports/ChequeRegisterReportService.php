<?php

namespace App\Application\Reports;

use App\Models\IncomingCheque;
use App\Models\OutgoingCheque;
use Illuminate\Support\Collection;

class ChequeRegisterReportService
{
    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    public function generate(
        string $direction = 'all',
        ?string $status = null,
        ?string $customerId = null,
        ?string $supplierId = null,
        ?string $bankAccountId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $currency = null
    ): array {
        $targetCurrency = $this->currencyResolver->resolve($currency);
        $items = new Collection;

        // Fetch Incoming Cheques if direction is 'all' or 'incoming'
        if ($direction === 'all' || $direction === 'incoming') {
            $query = IncomingCheque::query()
                ->with(['customer', 'depositBankAccount'])
                ->where('currency', $targetCurrency);

            if ($status) {
                $query->where('status', $status);
            }
            if ($customerId) {
                $query->where('customer_id', $customerId);
            }
            if ($bankAccountId) {
                $query->where('deposit_bank_account_id', $bankAccountId);
            }
            if ($dateFrom) {
                $query->where('due_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->where('due_date', '<=', $dateTo);
            }

            foreach ($query->get() as $chq) {
                $items->push([
                    'id' => $chq->id,
                    'direction' => 'incoming',
                    'cheque_number' => $chq->cheque_number,
                    'party_name' => $chq->customer?->name ?? '—',
                    'party_code' => $chq->customer?->code ?? '—',
                    'bank_account_name' => $chq->depositBankAccount?->name ?? $chq->drawer_bank_name ?? '—',
                    'due_date' => $chq->due_date ?? $chq->received_date,
                    'currency' => $chq->currency,
                    'amount_minor' => (int) $chq->amount_minor,
                    'status' => $chq->status,
                    'notes' => $chq->description ?? null,
                    'created_at' => $chq->created_at,
                ]);
            }
        }

        // Fetch Outgoing Cheques if direction is 'all' or 'outgoing'
        if ($direction === 'all' || $direction === 'outgoing') {
            $query = OutgoingCheque::query()
                ->with(['supplier', 'bankAccount'])
                ->where('currency', $targetCurrency);

            if ($status) {
                $query->where('status', $status);
            }
            if ($supplierId) {
                $query->where('supplier_id', $supplierId);
            }
            if ($bankAccountId) {
                $query->where('bank_account_id', $bankAccountId);
            }
            if ($dateFrom) {
                $query->where('due_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->where('due_date', '<=', $dateTo);
            }

            foreach ($query->get() as $chq) {
                $items->push([
                    'id' => $chq->id,
                    'direction' => 'outgoing',
                    'cheque_number' => $chq->cheque_number,
                    'party_name' => $chq->supplier?->name ?? $chq->payee_name ?? '—',
                    'party_code' => $chq->supplier?->code ?? '—',
                    'bank_account_name' => $chq->bankAccount?->name ?? '—',
                    'due_date' => $chq->due_date ?? $chq->issued_date,
                    'currency' => $chq->currency,
                    'amount_minor' => (int) $chq->amount_minor,
                    'status' => $chq->status,
                    'notes' => $chq->description ?? null,
                    'created_at' => $chq->created_at,
                ]);
            }
        }

        $sorted = $items->sortBy(fn ($i) => $i['due_date'].' '.$i['created_at'])->values();

        $totalAmount = $sorted->sum('amount_minor');
        $incomingTotal = $sorted->where('direction', 'incoming')->sum('amount_minor');
        $outgoingTotal = $sorted->where('direction', 'outgoing')->sum('amount_minor');

        return [
            'direction' => $direction,
            'filters' => [
                'status' => $status,
                'customer_id' => $customerId,
                'supplier_id' => $supplierId,
                'bank_account_id' => $bankAccountId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'currency' => $targetCurrency,
            ],
            'items' => $sorted->all(),
            'total_amount_minor' => $totalAmount,
            'incoming_total_minor' => $incomingTotal,
            'outgoing_total_minor' => $outgoingTotal,
            'total_count' => $sorted->count(),
        ];
    }
}
