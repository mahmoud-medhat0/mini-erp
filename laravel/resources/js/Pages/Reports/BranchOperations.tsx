import { Head, router } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, EmptyState, MetricCard, PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
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
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canPrint = can('reports.print') && can('view_financials');

  const [branchId, setBranchId] = useState(filters.branch_id || '');
  const [dateFrom, setDateFrom] = useState(filters.date_from || '');
  const [dateTo, setDateTo] = useState(filters.date_to || '');
  const [tableSearch, setTableSearch] = useState('');

  const hasActiveFilters = Boolean(branchId || dateFrom || dateTo);

  const branchOptions = useMemo(
    () => branches.map((branch) => ({
      value: branch.id,
      label: `${branch.code} - ${getLocalizedName(branch.name, locale)}`,
      sublabel: branch.is_active ? pageDict.active : pageDict.inactive,
    })),
    [branches, locale, pageDict.active, pageDict.inactive],
  );

  const filteredRows = useMemo(() => {
    if (!tableSearch.trim()) return reportData.rows;
    const q = tableSearch.toLowerCase().trim();
    return reportData.rows.filter((row) => {
      const code = (row.branch_code || '').toLowerCase();
      const name = getLocalizedName(row.branch_name, locale).toLowerCase();
      return code.includes(q) || name.includes(q);
    });
  }, [reportData.rows, tableSearch, locale]);

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
    router.get(
      '/reports/branch-operations',
      {
        branch_id: branchId || undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
      },
      { preserveState: true, replace: true },
    );
  }

  function handleReset() {
    setBranchId('');
    setDateFrom('');
    setDateTo('');
    router.get('/reports/branch-operations', {}, { preserveState: true, replace: true });
  }

  return (
    <AppLayout active="reports.branch-operations">
      <Head title={pageDict.headTitle} />

      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={
          canPrint ? (
            <Button variant="secondary" onClick={() => window.print()} className="gap-2">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
              </svg>
              {actionsDict.printReport}
            </Button>
          ) : undefined
        }
      />

      <div className="space-y-6">
        <Card className="p-4 border border-[var(--border-color)]">
          <form onSubmit={applyFilters} className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-[var(--border-color)] text-xs font-semibold text-[var(--text-secondary)]">
              <div className="flex items-center gap-2">
                <svg className="w-4 h-4 text-[var(--primary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                <span>{actionsDict.actionsTitle} / {pageDict.filtersTitle}</span>
              </div>
              {hasActiveFilters && (
                <button
                  type="button"
                  onClick={handleReset}
                  title={actionsDict.reset}
                  aria-label={actionsDict.reset}
                  className="text-xs text-[var(--primary)] hover:underline font-bold"
                >
                  {actionsDict.reset}
                </button>
              )}
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-4 md:items-end">
              <SearchableSelect
                label={pageDict.branch}
                options={branchOptions}
                value={branchId}
                onChange={(value) => setBranchId(value || '')}
                placeholder={pageDict.allBranches}
              />
              <DatePicker label={pageDict.dateFrom} value={dateFrom} onChange={(value) => setDateFrom(value || '')} />
              <DatePicker label={pageDict.dateTo} value={dateTo} onChange={(value) => setDateTo(value || '')} />
              <div className="flex items-center gap-2">
                <Button type="submit" className="flex-1 h-[42px]">
                  {pageDict.filter}
                </Button>
                {hasActiveFilters && (
                  <Button type="button" variant="secondary" onClick={handleReset} className="h-[42px]">
                    {actionsDict.reset}
                  </Button>
                )}
              </div>
            </div>
          </form>
        </Card>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5">
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
          <Card className="p-5 border-t-4 border-t-[var(--primary)]">
            <div className="space-y-4">
              <div>
                <h2 className="text-sm font-bold text-[var(--text-primary)] tracking-wide">{pageDict.profitabilityPendingTitle}</h2>
                <p className="mt-1.5 text-xs leading-relaxed text-[var(--text-secondary)]">{pageDict.profitabilityPendingDescription}</p>
              </div>
              <div className="flex flex-wrap gap-2 text-xs">
                <span className="rounded-lg border border-[var(--border-color)] bg-[var(--background)] px-3 py-1.5 font-semibold text-[var(--text-secondary)] shadow-sm">
                  {pageDict.baseCurrency}: <span className="font-mono text-[var(--text-primary)] font-bold">{reportData.base_currency}</span>
                </span>
                <span className="rounded-lg border border-[var(--border-color)] bg-[var(--background)] px-3 py-1.5 font-semibold text-[var(--text-secondary)] shadow-sm">
                  {pageDict.currenciesInScope}: <span className="font-mono text-[var(--text-primary)] font-bold">{reportData.readiness.currency_codes.join(', ') || pageDict.notAssigned}</span>
                </span>
              </div>
              {reportData.readiness.currency_codes.length > 1 ? (
                <div className="flex items-start gap-2.5 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3.5 text-xs leading-relaxed text-amber-700 dark:text-amber-300">
                  <svg className="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                  <span>{pageDict.mixedCurrencyWarning}</span>
                </div>
              ) : null}
            </div>
          </Card>

          <Card className="p-5">
            <div className="mb-4">
              <h2 className="text-sm font-bold text-[var(--text-primary)] tracking-wide">{pageDict.readinessTitle}</h2>
              <p className="mt-1 text-xs leading-relaxed text-[var(--text-secondary)]">{pageDict.readinessDescription}</p>
            </div>
            <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 xl:grid-cols-3">
              {readinessItems.map((item) => (
                <div key={item.label} className="rounded-lg border border-[var(--border-color)] bg-[var(--background)]/60 p-3 transition-colors hover:bg-[var(--background)]">
                  <div className="text-[11px] font-semibold text-[var(--text-secondary)] leading-snug">{item.label}</div>
                  <div className={`mt-1.5 font-mono text-sm font-bold tabular-nums ${item.tone === 'warning' ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'}`}>
                    {item.value}
                  </div>
                </div>
              ))}
            </div>
          </Card>
        </div>

        <Card className="p-0 overflow-hidden border border-[var(--border-color)] shadow-sm">
          <div className="p-4 bg-[var(--background)]/80 border-b border-[var(--border-color)] flex flex-wrap items-center justify-between gap-3">
            <div className="flex items-center gap-2">
              <h3 className="font-bold text-xs text-[var(--text-primary)] tracking-wide uppercase">{pageDict.branchColumn} {pageDict.operationsOverview}</h3>
              <span className="rounded-full bg-[var(--primary)]/10 text-[var(--primary)] px-2.5 py-0.5 text-[11px] font-bold font-mono">
                {filteredRows.length} {filteredRows.length === 1 ? pageDict.branchSingular : pageDict.branchPlural}
              </span>
            </div>
            <div className="relative min-w-[220px]">
              <input
                type="text"
                placeholder={pageDict.searchPlaceholder}
                value={tableSearch}
                onChange={(e) => setTableSearch(e.target.value)}
                className="w-full rounded-lg border border-[var(--border-color)] bg-[var(--card)] px-3 py-1.5 text-xs text-[var(--text-primary)] placeholder-[var(--text-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--primary)]/30 transition-all"
              />
              {tableSearch && (
                <button
                  type="button"
                  onClick={() => setTableSearch('')}
                  title={pageDict.clearSearch}
                  aria-label={pageDict.clearSearch}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-[var(--text-muted)] hover:text-[var(--text-primary)]"
                >
                  ✕
                </button>
              )}
            </div>
          </div>

          {filteredRows.length === 0 ? (
            <div className="p-8">
              <EmptyState title={pageDict.emptyTitle} description={pageDict.emptyDescription} />
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-start text-xs border-collapse">
                <thead>
                  <tr className="bg-[var(--background)] border-b border-[var(--border-color)] text-[var(--text-secondary)] font-semibold">
                    <th className="p-3 text-start">{pageDict.branchColumn}</th>
                    <th className="p-3 text-start">{pageDict.operationalScore}</th>
                    <th className="p-3 text-end">{pageDict.warehouseCount}</th>
                    <th className="p-3 text-end">{pageDict.stockRows}</th>
                    <th className="p-3 text-end">{pageDict.stockQty}</th>
                    <th className="p-3 text-end">{pageDict.stockValue}</th>
                    <th className="p-3 text-end">{pageDict.stockMovementValue}</th>
                    <th className="p-3 text-end">{pageDict.cashBalance}</th>
                    <th className="p-3 text-end">{pageDict.bankBalance}</th>
                    <th className="p-3 text-end">{pageDict.assetCost}</th>
                    <th className="p-3 text-end">{pageDict.assetMovements}</th>
                    <th className="p-3 text-end">{pageDict.treasuryIn}</th>
                    <th className="p-3 text-end">{pageDict.treasuryOut}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--border-color)]">
                  {filteredRows.map((row) => (
                    <tr key={row.branch_id} className="hover:bg-[var(--background)]/60 transition-colors">
                      <td className="p-3">
                        <div className="flex min-w-48 flex-col gap-1">
                          <div className="flex items-center gap-2">
                            <span className="font-mono text-xs font-bold px-2 py-0.5 rounded border border-[var(--border-color)] bg-[var(--background)] text-[var(--text-primary)]">
                              {row.branch_code}
                            </span>
                            <StatusBadge tone={row.is_active ? 'ok' : 'muted'} className="text-[10px] py-0 px-1.5">
                              {row.is_active ? pageDict.active : pageDict.inactive}
                            </StatusBadge>
                          </div>
                          <span className="text-xs font-medium text-[var(--text-primary)] mt-0.5">
                            {getLocalizedName(row.branch_name, locale)}
                          </span>
                        </div>
                      </td>
                      <td className="p-3 whitespace-nowrap">
                        <StatusBadge tone={scoreTone(row.operational_score)}>
                          {pageDict.statuses[row.operational_score]}
                        </StatusBadge>
                      </td>
                      <td className="p-3 text-end font-mono tabular-nums font-semibold">{row.warehouse_count.toLocaleString()}</td>
                      <td className="p-3 text-end font-mono tabular-nums">{row.stock_balance_rows.toLocaleString()}</td>
                      <td className="p-3 text-end font-mono tabular-nums">{formatQuantityE6(row.stock_quantity_e6)}</td>
                      <td className="p-3 text-end font-mono tabular-nums font-bold text-purple-600 dark:text-purple-400">
                        {formatMoney(row.stock_valuation_minor, reportData.base_currency)}
                      </td>
                      <td className="p-3 text-end font-mono tabular-nums">
                        {formatMoney(row.stock_movement_value_minor, reportData.base_currency)}
                      </td>
                      <td className="p-3 text-end font-mono tabular-nums">
                        <span className="block text-[10px] text-[var(--text-muted)] font-sans">
                          {row.cash_account_count.toLocaleString()} {pageDict.cashAccounts}
                        </span>
                        <span className="font-medium">{formatMoney(row.cash_balance_minor, reportData.base_currency)}</span>
                      </td>
                      <td className="p-3 text-end font-mono tabular-nums">
                        <span className="block text-[10px] text-[var(--text-muted)] font-sans">
                          {row.bank_account_count.toLocaleString()} {pageDict.bankAccounts}
                        </span>
                        <span className="font-medium">{formatMoney(row.bank_balance_minor, reportData.base_currency)}</span>
                      </td>
                      <td className="p-3 text-end font-mono tabular-nums">
                        <span className="block text-[10px] text-[var(--text-muted)] font-sans">
                          {row.fixed_asset_count.toLocaleString()} {pageDict.assetCount}
                        </span>
                        <span>{formatMoney(row.fixed_asset_cost_minor, reportData.base_currency)}</span>
                      </td>
                      <td className="p-3 text-end font-mono tabular-nums text-xs whitespace-nowrap">
                        <span className="text-emerald-600 dark:text-emerald-400 font-semibold">{pageDict.incoming}: {row.asset_movement_in_count.toLocaleString()}</span>
                        <span className="mx-1 text-[var(--text-muted)]">/</span>
                        <span className="text-rose-600 dark:text-rose-400 font-semibold">{pageDict.outgoing}: {row.asset_movement_out_count.toLocaleString()}</span>
                      </td>
                      <td className="p-3 text-end font-mono tabular-nums">
                        <span className="block text-[10px] text-[var(--text-muted)] font-sans">{row.treasury_in_count.toLocaleString()} {pageDict.moves}</span>
                        <span className="text-emerald-600 dark:text-emerald-400 font-medium">{formatMoney(row.treasury_in_minor, reportData.base_currency)}</span>
                      </td>
                      <td className="p-3 text-end font-mono tabular-nums">
                        <span className="block text-[10px] text-[var(--text-muted)] font-sans">{row.treasury_out_count.toLocaleString()} {pageDict.moves}</span>
                        <span className="text-rose-600 dark:text-rose-400 font-medium">{formatMoney(row.treasury_out_minor, reportData.base_currency)}</span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      </div>
    </AppLayout>
  );
}
