<?php

namespace App\Application\Accounting;

use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class FinancialStatementMappingPageData
{
    private const STATEMENT_TYPES = [
        ['value' => 'balance_sheet'],
        ['value' => 'income_statement'],
    ];

    private const SECTION_OPTIONS = [
        ['value' => 'current_assets'],
        ['value' => 'non_current_assets'],
        ['value' => 'current_liabilities'],
        ['value' => 'non_current_liabilities'],
        ['value' => 'equity'],
        ['value' => 'revenue'],
        ['value' => 'contra_revenue'],
        ['value' => 'cogs'],
        ['value' => 'operating_expenses'],
        ['value' => 'other_income'],
        ['value' => 'other_expenses'],
    ];

    private const NORMAL_BALANCES = [
        ['value' => 'debit'],
        ['value' => 'credit'],
    ];

    private const CASH_FLOW_ACTIVITIES = [
        ['value' => 'operating'],
        ['value' => 'investing'],
        ['value' => 'financing'],
    ];

    public function __construct(
        private readonly FinancialStatementMappingService $mappingService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexData(): array
    {
        $data = $this->mappingService->getMappingData();

        return [
            'lines' => $data['lines'],
            'unmappedAccounts' => $data['unmapped_accounts'],
            'statementTypes' => self::STATEMENT_TYPES,
            'sectionOptions' => self::SECTION_OPTIONS,
            'normalBalances' => self::NORMAL_BALANCES,
            'cashFlowActivities' => self::CASH_FLOW_ACTIVITIES,
        ];
    }

    /**
     * Server-side DataTables feed for financial statement mappings.
     *
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $statementType = (string) ($filters['statement_type'] ?? '');
        $mappingStatus = (string) ($filters['mapping_status'] ?? '');
        $cashFlowActivity = (string) ($filters['cash_flow_activity'] ?? '');
        $sectionCode = (string) ($filters['section_code'] ?? '');

        $query = Account::query()
            ->leftJoin('financial_statement_line', 'financial_statement_line.id', '=', 'account.financial_statement_line_id')
            ->leftJoin('account_type', 'account_type.id', '=', 'account.account_type_id')
            ->leftJoin('account_group', 'account_group.id', '=', 'account.account_group_id')
            ->select([
                'account.id',
                'account.code',
                'account.name',
                'account.type',
                'account.nature',
                'account.currency',
                'account.financial_statement_line_id',
                'account.cash_flow_activity as account_cash_flow_activity',
                'account.account_type_id',
                'account.account_group_id',
                'financial_statement_line.code as line_code',
                'financial_statement_line.name as line_name',
                'financial_statement_line.statement_type',
                'financial_statement_line.section_code',
                'financial_statement_line.cash_flow_activity as line_cash_flow_activity',
                'account_type.name as account_type_name',
                'account_group.name as group_name',
            ]);

        if ($statementType !== '') {
            $query->where('financial_statement_line.statement_type', $statementType);
        }

        if ($mappingStatus === 'mapped') {
            $query->whereNotNull('account.financial_statement_line_id');
        } elseif ($mappingStatus === 'unmapped') {
            $query->whereNull('account.financial_statement_line_id');
        }

        if ($cashFlowActivity !== '') {
            $query->where(function ($q) use ($cashFlowActivity): void {
                $q->where('account.cash_flow_activity', $cashFlowActivity)
                    ->orWhere(function ($q2) use ($cashFlowActivity): void {
                        $q2->whereNull('account.cash_flow_activity')
                            ->where('financial_statement_line.cash_flow_activity', $cashFlowActivity);
                    });
            });
        }

        if ($sectionCode !== '') {
            $query->where('financial_statement_line.section_code', $sectionCode);
        }

        return DataTables::eloquent($query)
            ->filterColumn('code', fn ($q, $kw) => $q->where('account.code', 'like', "%{$kw}%"))
            ->filterColumn('name', function ($q, $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $q->whereRaw('LOWER(CAST(account.name AS TEXT)) LIKE ?', [$needle]);
            })
            ->filterColumn('line_code', fn ($q, $kw) => $q->where('financial_statement_line.code', 'like', "%{$kw}%"))
            ->filterColumn('line_name', function ($q, $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $q->whereRaw('LOWER(CAST(financial_statement_line.name AS TEXT)) LIKE ?', [$needle]);
            })
            ->filterColumn('account_type_name', function ($q, $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $q->whereRaw('LOWER(CAST(account_type.name AS TEXT)) LIKE ?', [$needle]);
            })
            ->orderColumn('code', 'account.code $1')
            ->orderColumn('name', 'account.name $1')
            ->orderColumn('line_code', 'financial_statement_line.code $1')
            ->orderColumn('line_name', 'financial_statement_line.name $1')
            ->editColumn('name', fn ($row) => $this->decodeTranslations($row->name))
            ->editColumn('line_name', fn ($row) => $this->decodeTranslations($row->line_name))
            ->editColumn('group_name', fn ($row) => $this->decodeTranslations($row->group_name))
            ->editColumn('account_type_name', fn ($row) => $this->decodeTranslations($row->account_type_name))
            ->toJson();
    }

    private function decodeTranslations(mixed $val): mixed
    {
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $val;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function createLinePayload(array $validated): array
    {
        return [
            'code' => $validated['code'],
            'statement_type' => $validated['statement_type'],
            'cash_flow_activity' => $validated['cash_flow_activity'] ?? null,
            'section_code' => $validated['section_code'],
            'name' => $this->localizedName($validated['name_en'], $validated['name_ar'] ?? null),
            'normal_balance' => $validated['normal_balance'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function updateLinePayload(array $validated): array
    {
        $payload = [];

        foreach (['code', 'statement_type', 'cash_flow_activity', 'section_code', 'normal_balance'] as $key) {
            if (array_key_exists($key, $validated) && $validated[$key] !== '') {
                $payload[$key] = $validated[$key];
            }
        }

        if (array_key_exists('cash_flow_activity', $validated) && $validated['cash_flow_activity'] === '') {
            $payload['cash_flow_activity'] = null;
        }

        if (isset($validated['sort_order'])) {
            $payload['sort_order'] = (int) $validated['sort_order'];
        }

        if (isset($validated['is_active'])) {
            $payload['is_active'] = (bool) $validated['is_active'];
        }

        if (! empty($validated['name_en'])) {
            $payload['name'] = $this->localizedName($validated['name_en'], $validated['name_ar'] ?? null);
        }

        return $payload;
    }

    /**
     * @return array{en: string, ar: string}
     */
    private function localizedName(string $nameEn, ?string $nameAr): array
    {
        return [
            'en' => $nameEn,
            'ar' => $nameAr ?: $nameEn,
        ];
    }
}
