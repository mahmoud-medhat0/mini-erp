import { Head, router } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, EmptyState, MetricCard, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type OperationalScore = 'ready' | 'partial' | 'not_configured';

type BranchOption = {
  id: string;
  code: string;
  name: TranslatedName;
  is_active: boolean;
};

type BranchOperationalRow = {
  branch_id: string;
  branch_code: string;
  branch_name: TranslatedName;
  is_active: boolean;
  warehouse_count: number;
  stock_balance_rows: number;
  stock_quantity_e6: number;
  stock_valuation_minor: number;
  stock_movement_count: number;
  stock_movement_value_minor: number;
  cash_account_count: number;
  cash_balance_minor: number;
  bank_account_count: number;
  bank_balance_minor: number;
  fixed_asset_count: number;
  fixed_asset_cost_minor: number;
  asset_movement_in_count: number;
  asset_movement_out_count: number;
  treasury_in_count: number;
  treasury_out_count: number;
  treasury_in_minor: number;
  treasury_out_minor: number;
  operational_score: OperationalScore;
};

type BranchOperationsProps = SharedPageProps & {
  reportData: {
    base_currency: string;
    rows: BranchOperationalRow[];
    summary: {
      branch_count: number;
      warehouse_count: number;
      stock_quantity_e6: number;
      stock_valuation_minor: number;
      stock_movement_value_minor: number;
      cash_balance_minor: number;
      bank_balance_minor: number;
      fixed_asset_count: number;
      fixed_asset_cost_minor: number;
      treasury_in_minor: number;
      treasury_out_minor: number;
    };
    readiness: {
      branch_profitability_status: string;
      currency_codes: string[];
      unassigned_warehouse_count: number;
      unassigned_stock_balance_rows: number;
      unassigned_stock_valuation_minor: number;
      unassigned_cash_account_count: number;
      unassigned_bank_account_count: number;
      unassigned_fixed_asset_count: number;
    };
  };
  filters: {
    branch_id: string;
    date_from: string;
    date_to: string;
  };
  branches: BranchOption[];
};

function formatQuantityE6(quantityE6: number): string {
  const sign = quantityE6 < 0 ? '-' : '';
  const absolute = Math.abs(Math.trunc(quantityE6));
  const whole = Math.floor(absolute / 1000000).toLocaleString();
  const fraction = String(absolute % 1000000).padStart(6, '0').replace(/0+$/, '');

  return `${sign}${whole}${fraction ? `.${fraction}` : ''}`;
}

function scoreTone(score: OperationalScore): 'ok' | 'muted' | 'warning' {
  if (score === 'ready') return 'ok';
  if (score === 'partial') return 'warning';
  return 'muted';
}

export default function BranchOperations({ locale, reportData, filters, branches }: BranchOperationsProps) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.branchOperationsReport;
  const [branchId, setBranchId] = useState(filters.branch_id || '');
  const [dateFrom, setDateFrom] = useState(filters.date_from || '');
  const [dateTo, setDateTo] = useState(filters.date_to || '');

  const branchOptions = useMemo(
    () => branches.map((branch) => ({
      value: branch.id,
      label: `${branch.code} - ${getLocalizedName(branch.name, locale)}`,
      sublabel: branch.is_active ? pageDict.active : pageDict.inactive,
    })),
    [branches, locale, pageDict.active, pageDict.inactive],
  );

  const readinessItems = [
    {
      label: pageDict.unassignedWarehouseCount,
      value: reportData.readiness.unassigned_warehouse_count.toLocaleString(),
      tone: reportData.readiness.unassigned_warehouse_count > 0 ? 'warning' : 'ok',
    },
    {
      label: pageDict.unassignedStockBalanceRows,
      value: reportData.readiness.unassigned_stock_balance_rows.toLocaleString(),
      tone: reportData.readiness.unassigned_stock_balance_rows > 0 ? 'warning' : 'ok',
    },
    {
      label: pageDict.unassignedStockValuation,
      value: formatMoney(reportData.readiness.unassigned_stock_valuation_minor, reportData.base_currency),
      tone: reportData.readiness.unassigned_stock_valuation_minor > 0 ? 'warning' : 'ok',
    },
    {
      label: pageDict.unassignedCashAccounts,
      value: reportData.readiness.unassigned_cash_account_count.toLocaleString(),
      tone: reportData.readiness.unassigned_cash_account_count > 0 ? 'warning' : 'ok',
    },
    {
      label: pageDict.unassignedBankAccounts,
      value: reportData.readiness.unassigned_bank_account_count.toLocaleString(),
      tone: reportData.readiness.unassigned_bank_account_count > 0 ? 'warning' : 'ok',
    },
    {
      label: pageDict.unassignedFixedAssets,
      value: reportData.readiness.unassigned_fixed_asset_count.toLocaleString(),
      tone: reportData.readiness.unassigned_fixed_asset_count > 0 ? 'warning' : 'ok',
    },
  ] as const;

  function applyFilters(event: FormEvent) {
    event.preventDefault();
    router.get('/reports/branch-operations', {
      branch_id: branchId,
      date_from: dateFrom,
      date_to: dateTo,
    }, { preserveState: true, replace: true });
  }

  return (
    <AppLayout active="reports.branch-operations">
      <Head title={pageDict.headTitle} />

      <PageHeader title={pageDict.title} description={pageDict.description} />

      <div className="space-y-6">
        <Card className="p-4">
          <form onSubmit={applyFilters} className="grid grid-cols-1 gap-4 md:grid-cols-4 md:items-end">
            <SearchableSelect
              label={pageDict.branch}
              options={branchOptions}
              value={branchId}
              onChange={(value) => setBranchId(value || '')}
              placeholder={pageDict.allBranches}
            />
            <DatePicker label={pageDict.dateFrom} value={dateFrom} onChange={(value) => setDateFrom(value || '')} />
            <DatePicker label={pageDict.dateTo} value={dateTo} onChange={(value) => setDateTo(value || '')} />
            <Button type="submit" className="h-[42px]">
              {pageDict.filter}
            </Button>
          </form>
        </Card>

        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
          <MetricCard label={pageDict.summaryBranches} value={reportData.summary.branch_count.toLocaleString()} tone="blue" />
          <MetricCard label={pageDict.summaryWarehouses} value={reportData.summary.warehouse_count.toLocaleString()} tone="emerald" />
          <MetricCard label={pageDict.summaryStockValue} value={formatMoney(reportData.summary.stock_valuation_minor, reportData.base_currency)} tone="purple" />
          <MetricCard
            label={pageDict.summaryCashBank}
            value={formatMoney(reportData.summary.cash_balance_minor + reportData.summary.bank_balance_minor, reportData.base_currency)}
            tone="amber"
          />
          <MetricCard
            label={pageDict.summaryAssets}
            value={reportData.summary.fixed_asset_count.toLocaleString()}
            hint={formatMoney(reportData.summary.fixed_asset_cost_minor, reportData.base_currency)}
            tone="muted"
          />
        </div>

        <div className="grid grid-cols-1 gap-4 xl:grid-cols-[1.2fr_2fr]">
          <Card className="p-4">
            <div className="space-y-3">
              <div>
                <h2 className="text-sm font-bold text-[var(--text-primary)]">{pageDict.profitabilityPendingTitle}</h2>
                <p className="mt-1 text-xs leading-5 text-[var(--text-secondary)]">{pageDict.profitabilityPendingDescription}</p>
              </div>
              <div className="flex flex-wrap gap-2 text-xs">
                <span className="rounded-md border border-[var(--border)] bg-[var(--background)] px-2.5 py-1 font-semibold text-[var(--text-secondary)]">
                  {pageDict.baseCurrency}: {reportData.base_currency}
                </span>
                <span className="rounded-md border border-[var(--border)] bg-[var(--background)] px-2.5 py-1 font-semibold text-[var(--text-secondary)]">
                  {pageDict.currenciesInScope}: {reportData.readiness.currency_codes.join(', ') || pageDict.notAssigned}
                </span>
              </div>
              {reportData.readiness.currency_codes.length > 1 ? (
                <p className="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs leading-5 text-amber-700 dark:text-amber-300">
                  {pageDict.mixedCurrencyWarning}
                </p>
              ) : null}
            </div>
          </Card>

          <Card className="p-4">
            <div className="mb-3">
              <h2 className="text-sm font-bold text-[var(--text-primary)]">{pageDict.readinessTitle}</h2>
              <p className="mt-1 text-xs leading-5 text-[var(--text-secondary)]">{pageDict.readinessDescription}</p>
            </div>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
              {readinessItems.map((item) => (
                <div key={item.label} className="rounded-md border border-[var(--border)] bg-[var(--background)] p-3">
                  <div className="text-xs font-semibold text-[var(--text-secondary)]">{item.label}</div>
                  <div className={`mt-1 font-mono text-sm font-bold ${item.tone === 'warning' ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'}`}>
                    {item.value}
                  </div>
                </div>
              ))}
            </div>
          </Card>
        </div>

        {reportData.rows.length === 0 ? (
          <EmptyState title={pageDict.emptyTitle} description={pageDict.emptyDescription} />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{pageDict.branchColumn}</th>
                  <th className={tableClasses.th}>{pageDict.operationalScore}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.warehouseCount}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.stockRows}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.stockQty}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.stockValue}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.stockMovementValue}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.cashBalance}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.bankBalance}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.assetCost}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.assetMovements}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.treasuryIn}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.treasuryOut}</th>
                </tr>
              </thead>
              <tbody>
                {reportData.rows.map((row) => (
                  <tr key={row.branch_id} className="hover:bg-[var(--background)]">
                    <td className={tableClasses.td}>
                      <div className="flex min-w-52 flex-col gap-1">
                        <span className="font-mono text-xs font-bold">{row.branch_code}</span>
                        <span className="text-xs text-[var(--text-secondary)]">{getLocalizedName(row.branch_name, locale)}</span>
                        <span className="text-[10px] font-semibold text-[var(--text-muted)]">
                          {row.is_active ? pageDict.active : pageDict.inactive}
                        </span>
                      </div>
                    </td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={scoreTone(row.operational_score)}>
                        {pageDict.statuses[row.operational_score]}
                      </StatusBadge>
                    </td>
                    <td className={`${tableClasses.td} text-end font-mono`}>{row.warehouse_count.toLocaleString()}</td>
                    <td className={`${tableClasses.td} text-end font-mono`}>{row.stock_balance_rows.toLocaleString()}</td>
                    <td className={`${tableClasses.td} text-end font-mono`}>{formatQuantityE6(row.stock_quantity_e6)}</td>
                    <td className={`${tableClasses.td} text-end font-mono`}>{formatMoney(row.stock_valuation_minor, reportData.base_currency)}</td>
                    <td className={`${tableClasses.td} text-end font-mono`}>{formatMoney(row.stock_movement_value_minor, reportData.base_currency)}</td>
                    <td className={`${tableClasses.td} text-end font-mono`}>
                      <span className="block text-[10px] text-[var(--text-muted)]">{row.cash_account_count.toLocaleString()} {pageDict.cashAccounts}</span>
                      {formatMoney(row.cash_balance_minor, reportData.base_currency)}
                    </td>
                    <td className={`${tableClasses.td} text-end font-mono`}>
                      <span className="block text-[10px] text-[var(--text-muted)]">{row.bank_account_count.toLocaleString()} {pageDict.bankAccounts}</span>
                      {formatMoney(row.bank_balance_minor, reportData.base_currency)}
                    </td>
                    <td className={`${tableClasses.td} text-end font-mono`}>
                      <span className="block text-[10px] text-[var(--text-muted)]">{row.fixed_asset_count.toLocaleString()} {pageDict.assetCount}</span>
                      {formatMoney(row.fixed_asset_cost_minor, reportData.base_currency)}
                    </td>
                    <td className={`${tableClasses.td} text-end font-mono text-xs`}>
                      {pageDict.incoming}: {row.asset_movement_in_count.toLocaleString()}
                      <span className="mx-1 text-[var(--text-muted)]">/</span>
                      {pageDict.outgoing}: {row.asset_movement_out_count.toLocaleString()}
                    </td>
                    <td className={`${tableClasses.td} text-end font-mono`}>
                      <span className="block text-[10px] text-[var(--text-muted)]">{row.treasury_in_count.toLocaleString()}</span>
                      {formatMoney(row.treasury_in_minor, reportData.base_currency)}
                    </td>
                    <td className={`${tableClasses.td} text-end font-mono`}>
                      <span className="block text-[10px] text-[var(--text-muted)]">{row.treasury_out_count.toLocaleString()}</span>
                      {formatMoney(row.treasury_out_minor, reportData.base_currency)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
