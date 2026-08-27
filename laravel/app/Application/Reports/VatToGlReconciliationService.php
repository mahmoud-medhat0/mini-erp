<?php

namespace App\Application\Reports;

use App\Models\AccountingAccountMapping;
use App\Models\LedgerEntry;

class VatToGlReconciliationService
{
    public function __construct(
        private readonly VatRegisterReportService $registerService,
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    public function generate(array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        $currency = $this->currencyResolver->resolve($filters['currency'] ?? null);

        $registerData = $this->registerService->generate([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'type' => 'all',
        ]);

        $warnings = [];

        // 1. Fetch Output Tax Account Mapping
        $outputMapping = AccountingAccountMapping::query()
            ->with('account')
            ->where('key', 'output_tax_payable')
            ->first();

        $outputAccountMapped = $outputMapping !== null && $outputMapping->account !== null;
        if (! $outputAccountMapped) {
            $warnings[] = [
                'code' => 'ERR_OUTPUT_TAX_ACCOUNT_NOT_MAPPED',
                'message_key' => 'taxes.warnings.output_tax_not_mapped',
            ];
        }

        // 2. Fetch Input Tax Account Mapping
        $inputMapping = AccountingAccountMapping::query()
            ->with('account')
            ->where('key', 'input_tax_receivable')
            ->first();

        $inputAccountMapped = $inputMapping !== null && $inputMapping->account !== null;
        if (! $inputAccountMapped) {
            $warnings[] = [
                'code' => 'ERR_INPUT_TAX_ACCOUNT_NOT_MAPPED',
                'message_key' => 'taxes.warnings.input_tax_not_mapped',
            ];
        }

        $effectiveFromDate = $registerData['from_date'];
        $effectiveToDate = $registerData['to_date'];

        // 3. Register Totals
        $registerOutputTaxMinor = (int) $registerData['summary']['total_output_tax_minor'];
        $registerInputTaxMinor = (int) $registerData['summary']['total_input_tax_minor'];
        $registerNetVatMinor = $registerOutputTaxMinor - $registerInputTaxMinor;

        // 4. GL Movements
        // Output VAT: credits minus debits
        $glOutputTaxMinor = 0;
        if ($outputAccountMapped) {
            $outputQuery = LedgerEntry::query()
                ->where('account_id', $outputMapping->account_id)
                ->where('currency', $currency)
                ->whereDate('entry_date', '>=', $effectiveFromDate)
                ->whereDate('entry_date', '<=', $effectiveToDate);

            $glOutputTaxMinor = (int) $outputQuery->sum('credit_minor') - (int) $outputQuery->sum('debit_minor');
        }

        // Input VAT: debits minus credits
        $glInputTaxMinor = 0;
        if ($inputAccountMapped) {
            $inputQuery = LedgerEntry::query()
                ->where('account_id', $inputMapping->account_id)
                ->where('currency', $currency)
                ->whereDate('entry_date', '>=', $effectiveFromDate)
                ->whereDate('entry_date', '<=', $effectiveToDate);

            $glInputTaxMinor = (int) $inputQuery->sum('debit_minor') - (int) $inputQuery->sum('credit_minor');
        }

        $glNetVatMinor = $glOutputTaxMinor - $glInputTaxMinor;

        // 5. Differences (register - gl)
        $outputTaxDifferenceMinor = $registerOutputTaxMinor - $glOutputTaxMinor;
        $inputTaxDifferenceMinor = $registerInputTaxMinor - $glInputTaxMinor;
        $netVatDifferenceMinor = $registerNetVatMinor - $glNetVatMinor;

        $isReconciled = $outputAccountMapped && $inputAccountMapped
            && $outputTaxDifferenceMinor === 0
            && $inputTaxDifferenceMinor === 0;

        if ($outputAccountMapped && $inputAccountMapped && ! $isReconciled) {
            $warnings[] = [
                'code' => 'WARN_VAT_GL_MISMATCH',
                'message_key' => 'taxes.warnings.vat_gl_mismatch',
            ];
        }

        return [
            'from_date' => $effectiveFromDate,
            'to_date' => $effectiveToDate,
            'currency' => $currency,
            'output_tax_account' => $outputAccountMapped ? [
                'id' => (string) $outputMapping->account->id,
                'code' => $outputMapping->account->code,
                'name' => $outputMapping->account->name,
            ] : null,
            'input_tax_account' => $inputAccountMapped ? [
                'id' => (string) $inputMapping->account->id,
                'code' => $inputMapping->account->code,
                'name' => $inputMapping->account->name,
            ] : null,
            'register_output_tax_minor' => $registerOutputTaxMinor,
            'gl_output_tax_minor' => $glOutputTaxMinor,
            'output_tax_difference_minor' => $outputTaxDifferenceMinor,
            'register_input_tax_minor' => $registerInputTaxMinor,
            'gl_input_tax_minor' => $glInputTaxMinor,
            'input_tax_difference_minor' => $inputTaxDifferenceMinor,
            'register_net_vat_minor' => $registerNetVatMinor,
            'gl_net_vat_minor' => $glNetVatMinor,
            'net_vat_difference_minor' => $netVatDifferenceMinor,
            'is_reconciled' => $isReconciled,
            'warnings' => $warnings,
        ];
    }
}
