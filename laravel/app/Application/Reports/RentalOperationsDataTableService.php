<?php

namespace App\Application\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use stdClass;
use Yajra\DataTables\Facades\DataTables;
use Yajra\DataTables\QueryDataTable;

class RentalOperationsDataTableService
{
    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    /** @param array<string, mixed> $filters */
    public function data(array $filters): JsonResponse
    {
        return $this->dataTable($this->rowsQuery($this->normalizeFilters($filters)))->toJson();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);

        $totals = DB::query()
            ->fromSub($this->rowsQuery($normalized), 'rental_summary')
            ->selectRaw('COUNT(*) AS contract_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) AS active_contract_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN due_state = 'overdue' THEN 1 ELSE 0 END), 0) AS overdue_contract_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN due_state = 'ending_soon' THEN 1 ELSE 0 END), 0) AS ending_soon_contract_count")
            ->selectRaw('COALESCE(SUM(open_item_count), 0) AS open_item_count')
            ->selectRaw('COALESCE(SUM(unbilled_line_count), 0) AS unbilled_line_count')
            ->selectRaw('COALESCE(SUM(open_invoice_count), 0) AS open_invoice_count')
            ->selectRaw('COALESCE(SUM(posted_invoice_count), 0) AS posted_invoice_count')
            ->selectRaw('COALESCE(SUM(rent_billed_minor), 0) AS rent_billed_minor')
            ->selectRaw('COALESCE(SUM(deposit_billed_minor), 0) AS deposit_billed_minor')
            ->selectRaw('COALESCE(SUM(charge_billed_minor), 0) AS charge_billed_minor')
            ->selectRaw('COALESCE(SUM(tax_billed_minor), 0) AS tax_billed_minor')
            ->selectRaw('COALESCE(SUM(total_billed_minor), 0) AS total_billed_minor')
            ->selectRaw('COALESCE(SUM(open_invoice_total_minor), 0) AS open_invoice_total_minor')
            ->selectRaw('COALESCE(SUM(pending_damage_minor), 0) AS pending_damage_minor')
            ->first();

        $currencyCodes = DB::query()
            ->fromSub($this->rowsQuery($normalized), 'rental_currencies')
            ->select('currency')
            ->whereNotNull('currency')
            ->where('currency', '<>', '')
            ->distinct()
            ->orderBy('currency')
            ->pluck('currency')
            ->all();

        $baseCurrency = $this->currencyResolver->resolve();

        return [
            'as_of_date' => $normalized['as_of_date'],
            'ending_soon_date' => $normalized['ending_soon_date'],
            'base_currency' => $baseCurrency,
            'currency_codes' => $currencyCodes,
            'single_currency' => count($currencyCodes) <= 1,
            'display_currency' => (string) ($currencyCodes[0] ?? $baseCurrency),
            'summary' => [
                'contract_count' => (int) ($totals->contract_count ?? 0),
                'active_contract_count' => (int) ($totals->active_contract_count ?? 0),
                'overdue_contract_count' => (int) ($totals->overdue_contract_count ?? 0),
                'ending_soon_contract_count' => (int) ($totals->ending_soon_contract_count ?? 0),
                'open_item_count' => (int) ($totals->open_item_count ?? 0),
                'unbilled_line_count' => (int) ($totals->unbilled_line_count ?? 0),
                'open_invoice_count' => (int) ($totals->open_invoice_count ?? 0),
                'posted_invoice_count' => (int) ($totals->posted_invoice_count ?? 0),
                'rent_billed_minor' => (int) ($totals->rent_billed_minor ?? 0),
                'deposit_billed_minor' => (int) ($totals->deposit_billed_minor ?? 0),
                'charge_billed_minor' => (int) ($totals->charge_billed_minor ?? 0),
                'tax_billed_minor' => (int) ($totals->tax_billed_minor ?? 0),
                'total_billed_minor' => (int) ($totals->total_billed_minor ?? 0),
                'open_invoice_total_minor' => (int) ($totals->open_invoice_total_minor ?? 0),
                'pending_damage_minor' => (int) ($totals->pending_damage_minor ?? 0),
            ],
            'readiness' => [
                'has_mixed_currency' => count($currencyCodes) > 1,
                'has_overdue_contracts' => (int) ($totals->overdue_contract_count ?? 0) > 0,
                'has_unbilled_lines' => (int) ($totals->unbilled_line_count ?? 0) > 0,
                'has_pending_damage' => (int) ($totals->pending_damage_minor ?? 0) > 0,
                'has_unposted_invoices' => (int) ($totals->open_invoice_count ?? 0) > 0,
            ],
        ];
    }

    private function dataTable(Builder $query): QueryDataTable
    {
        $dataTable = DataTables::query($query)->filter(function (Builder $builder): void {
            $search = trim((string) request()->input('search.value', ''));

            if ($search === '') {
                return;
            }

            $like = '%'.mb_strtolower($search).'%';

            $builder->where(function (Builder $nested) use ($like): void {
                foreach ([
                    'contract_number',
                    'reference',
                    'customer_code',
                    'customer_name',
                    'branch_code',
                    'status',
                    'due_state',
                    'currency',
                ] as $index => $column) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $nested->{$method}("LOWER(COALESCE(CAST(rental_rows.{$column} AS TEXT), '')) LIKE ?", [$like]);
                }
            });
        });

        foreach (['customer_name', 'branch_name'] as $column) {
            $dataTable->editColumn(
                $column,
                fn (stdClass $row): array|string|null => $this->translatableName($row->{$column}),
            );
        }

        foreach ([
            'line_count',
            'confirmed_handover_count',
            'returned_line_count',
            'open_item_count',
            'invoice_count',
            'posted_invoice_count',
            'open_invoice_count',
            'unbilled_line_count',
            'estimated_rent_minor',
            'deposit_minor',
            'rent_billed_minor',
            'deposit_billed_minor',
            'charge_billed_minor',
            'tax_billed_minor',
            'total_billed_minor',
            'open_invoice_total_minor',
            'pending_damage_minor',
            'active_invoice_line_count',
        ] as $column) {
            $dataTable->editColumn($column, fn (stdClass $row): int => (int) $row->{$column});
        }

        $dataTable->editColumn(
            'has_unposted_invoice',
            fn (stdClass $row): bool => (int) $row->open_invoice_count > 0,
        );

        foreach ([
            'contract_number',
            'customer_name',
            'branch_name',
            'status',
            'due_state',
            'start_date',
            'expected_end_date',
            'currency',
            'line_count',
            'open_item_count',
            'unbilled_line_count',
            'open_invoice_count',
            'total_billed_minor',
            'open_invoice_total_minor',
            'pending_damage_minor',
        ] as $column) {
            $dataTable->orderColumn(
                $column,
                "{$column} \$1, expected_end_date ASC, contract_number ASC",
            );
        }

        return $dataTable;
    }

    /** @param array<string, mixed> $filters */
    private function rowsQuery(array $filters): Builder
    {
        $asOfDate = $filters['as_of_date'];
        $endingSoonDate = $filters['ending_soon_date'];

        // Non-cancelled invoice lines of a given type, summed for the contract.
        $billedByType = static function (array $lineTypes): string {
            $types = implode(', ', array_map(static fn (string $type): string => "'".$type."'", $lineTypes));

            return "(SELECT COALESCE(SUM(ril.line_total_minor), 0)
                FROM rental_invoice_line ril
                INNER JOIN rental_invoice ri ON ri.id = ril.rental_invoice_id
                WHERE ri.rental_contract_id = rental_contract.id
                  AND ri.status = 'posted'
                  AND ril.line_type IN ({$types}))";
        };

        $lineCount = '(SELECT COUNT(*) FROM rental_contract_line rcl
            WHERE rcl.rental_contract_id = rental_contract.id)';

        $returnedLineCount = "(SELECT COUNT(DISTINCT rrl.rental_contract_line_id)
            FROM rental_return_line rrl
            INNER JOIN rental_return rr ON rr.id = rrl.rental_return_id
            WHERE rr.rental_contract_id = rental_contract.id
              AND rr.status = 'completed')";

        // Contract lines with no non-cancelled 'rent' invoice line.
        $unbilledLineCount = "(SELECT COUNT(*) FROM rental_contract_line rcl
            WHERE rcl.rental_contract_id = rental_contract.id
              AND NOT EXISTS (
                SELECT 1 FROM rental_invoice_line ril
                INNER JOIN rental_invoice ri ON ri.id = ril.rental_invoice_id
                WHERE ril.rental_contract_line_id = rcl.id
                  AND ril.line_type = 'rent'
                  AND ri.status <> 'cancelled'))";

        // max(0, estimate - billed) evaluated PER return line, then summed.
        $damageBilled = "COALESCE((
                SELECT SUM(ril.line_total_minor) FROM rental_invoice_line ril
                INNER JOIN rental_invoice ri ON ri.id = ril.rental_invoice_id
                WHERE ril.rental_return_line_id = rrl.id
                  AND ril.line_type = 'damage_charge'
                  AND ri.status <> 'cancelled'
            ), 0)";
        $pendingDamage = "(SELECT COALESCE(SUM(CASE
                WHEN rrl.estimated_damage_charge_minor - {$damageBilled} > 0
                THEN rrl.estimated_damage_charge_minor - {$damageBilled}
                ELSE 0 END), 0)
            FROM rental_return_line rrl
            INNER JOIN rental_return rr ON rr.id = rrl.rental_return_id
            WHERE rr.rental_contract_id = rental_contract.id
              AND rr.status = 'completed')";

        $postedInvoiceCount = "(SELECT COUNT(*) FROM rental_invoice ri
            WHERE ri.rental_contract_id = rental_contract.id AND ri.status = 'posted')";

        $openInvoiceCount = "(SELECT COUNT(*) FROM rental_invoice ri
            WHERE ri.rental_contract_id = rental_contract.id
              AND ri.status IN ('draft', 'submitted', 'approved'))";

        $activeInvoiceCount = "(SELECT COUNT(*) FROM rental_invoice ri
            WHERE ri.rental_contract_id = rental_contract.id AND ri.status <> 'cancelled')";

        $activeInvoiceLineCount = "(SELECT COUNT(*) FROM rental_invoice_line ril
            INNER JOIN rental_invoice ri ON ri.id = ril.rental_invoice_id
            WHERE ri.rental_contract_id = rental_contract.id AND ri.status <> 'cancelled')";

        $confirmedHandoverCount = "(SELECT COUNT(*) FROM rental_handover rh
            WHERE rh.rental_contract_id = rental_contract.id AND rh.status = 'confirmed')";

        $taxBilled = "(SELECT COALESCE(SUM(ri.tax_amount_minor), 0) FROM rental_invoice ri
            WHERE ri.rental_contract_id = rental_contract.id AND ri.status = 'posted')";

        $totalBilled = "(SELECT COALESCE(SUM(ri.total_minor), 0) FROM rental_invoice ri
            WHERE ri.rental_contract_id = rental_contract.id AND ri.status = 'posted')";

        $openInvoiceTotal = "(SELECT COALESCE(SUM(ri.total_minor), 0) FROM rental_invoice ri
            WHERE ri.rental_contract_id = rental_contract.id
              AND ri.status IN ('draft', 'submitted', 'approved'))";

        // Mirrors $postedInvoices->first()?->journalEntry?->number under the
        // service's eager-load ordering (rental_invoice primary key order).
        $latestJournalNumber = "(SELECT COALESCE(je.number, '') FROM rental_invoice ri
            LEFT JOIN journal_entry je ON je.id = ri.journal_entry_id
            WHERE ri.rental_contract_id = rental_contract.id AND ri.status = 'posted'
            ORDER BY ri.id ASC LIMIT 1)";

        // as_of_date / ending_soon_date are normalized to Y-m-d by normalizeFilters(),
        // so they are safe to inline and keep this expression binding-free. That matters:
        // selectRaw bindings would otherwise interleave incorrectly with later addSelect
        // calls in the same builder.
        $dueState = "CASE
            WHEN rental_contract.status = 'cancelled' THEN 'cancelled'
            WHEN rental_contract.status = 'completed' THEN 'completed'
            WHEN rental_contract.status <> 'active' THEN 'not_active'
            WHEN SUBSTR(CAST(rental_contract.expected_end_date AS TEXT), 1, 10) < '{$asOfDate}' THEN 'overdue'
            WHEN SUBSTR(CAST(rental_contract.expected_end_date AS TEXT), 1, 10) <= '{$endingSoonDate}' THEN 'ending_soon'
            ELSE 'on_track' END";

        $inner = DB::table('rental_contract')
            ->leftJoin('customer', 'customer.id', '=', 'rental_contract.customer_id')
            ->leftJoin('branch', 'branch.id', '=', 'rental_contract.branch_id')
            ->when(
                $filters['branch_id'],
                fn (Builder $query, string $branchId): Builder => $query->where('rental_contract.branch_id', $branchId),
            )
            ->when(
                $filters['customer_id'],
                fn (Builder $query, string $customerId): Builder => $query->where('rental_contract.customer_id', $customerId),
            )
            ->when(
                $filters['status'],
                fn (Builder $query, string $status): Builder => $query->where('rental_contract.status', $status),
            )
            ->when(
                $filters['currency'],
                fn (Builder $query, string $currency): Builder => $query->where('rental_contract.currency', strtoupper($currency)),
            )
            ->when(
                $filters['date_from'],
                fn (Builder $query, string $dateFrom): Builder => $query->where('rental_contract.expected_end_date', '>=', $dateFrom),
            )
            ->when(
                $filters['date_to'],
                fn (Builder $query, string $dateTo): Builder => $query->where('rental_contract.start_date', '<=', $dateTo),
            )
            ->selectRaw('CAST(rental_contract.id AS TEXT) AS contract_id')
            ->selectRaw("COALESCE(rental_contract.number, '') AS contract_number")
            ->selectRaw('CAST(rental_contract.customer_id AS TEXT) AS customer_id')
            ->selectRaw("COALESCE(customer.code, '') AS customer_code")
            ->selectRaw('CAST(customer.name AS TEXT) AS customer_name')
            ->selectRaw('CAST(rental_contract.branch_id AS TEXT) AS branch_id')
            ->selectRaw("COALESCE(branch.code, '') AS branch_code")
            ->selectRaw('CAST(branch.name AS TEXT) AS branch_name')
            ->addSelect('rental_contract.status')
            ->selectRaw("({$dueState}) AS due_state")
            ->addSelect(
                'rental_contract.contract_date',
                'rental_contract.start_date',
                'rental_contract.expected_end_date',
                'rental_contract.actual_end_date',
                'rental_contract.billing_cycle',
                'rental_contract.reference',
            )
            ->selectRaw("COALESCE(rental_contract.currency, '') AS currency")
            ->selectRaw("({$lineCount}) AS line_count")
            ->selectRaw("({$confirmedHandoverCount}) AS confirmed_handover_count")
            ->selectRaw("({$returnedLineCount}) AS returned_line_count")
            ->selectRaw("(CASE WHEN ({$lineCount}) - ({$returnedLineCount}) > 0
                THEN ({$lineCount}) - ({$returnedLineCount}) ELSE 0 END) AS open_item_count")
            ->selectRaw("({$activeInvoiceCount}) AS invoice_count")
            ->selectRaw("({$postedInvoiceCount}) AS posted_invoice_count")
            ->selectRaw("({$openInvoiceCount}) AS open_invoice_count")
            ->selectRaw('COALESCE(rental_contract.estimated_rent_minor, 0) AS estimated_rent_minor')
            ->selectRaw('COALESCE(rental_contract.deposit_minor, 0) AS deposit_minor')
            ->selectRaw($billedByType(['rent']).' AS rent_billed_minor')
            ->selectRaw($billedByType(['deposit']).' AS deposit_billed_minor')
            ->selectRaw($billedByType(['damage_charge', 'late_fee', 'other_charge']).' AS charge_billed_minor')
            ->selectRaw("({$taxBilled}) AS tax_billed_minor")
            ->selectRaw("({$totalBilled}) AS total_billed_minor")
            ->selectRaw("({$openInvoiceTotal}) AS open_invoice_total_minor")
            ->selectRaw("({$unbilledLineCount}) AS unbilled_line_count")
            ->selectRaw("({$pendingDamage}) AS pending_damage_minor")
            ->selectRaw("COALESCE(({$latestJournalNumber}), '') AS latest_journal_number")
            ->selectRaw("({$activeInvoiceLineCount}) AS active_invoice_line_count");

        return DB::query()->fromSub($inner, 'rental_rows')->select('rental_rows.*');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|null>
     */
    private function normalizeFilters(array $filters): array
    {
        $asOfDate = CarbonImmutable::parse($filters['as_of_date'] ?? now()->toDateString())->toDateString();

        return [
            'as_of_date' => $asOfDate,
            'ending_soon_date' => CarbonImmutable::parse($asOfDate)->addDays(14)->toDateString(),
            'branch_id' => $filters['branch_id'] ?? null,
            'customer_id' => $filters['customer_id'] ?? null,
            'status' => $filters['status'] ?? null,
            'currency' => $filters['currency'] ?? null,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
        ];
    }

    private function translatableName(mixed $name): array|string|null
    {
        if (! is_string($name)) {
            return is_array($name) ? $name : ($name === null ? null : (string) $name);
        }

        $decoded = json_decode($name, true);

        return is_array($decoded) ? $decoded : $name;
    }
}
