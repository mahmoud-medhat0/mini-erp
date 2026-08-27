<?php

namespace App\Application\Accounting;

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
