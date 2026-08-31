import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, EmptyState, MetricCard, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;

type BranchOption = {
  id: string;
  code: string;
  name: TranslatedName;
  is_active: boolean;
};

type BranchProfitabilityRow = {
  branch_id: string | null;
  branch_code: string;
  branch_name: TranslatedName;
  is_active: boolean;
  is_unassigned: boolean;
  ledger_row_count: number;
  revenue_minor: number;
  contra_revenue_minor: number;
  net_revenue_minor: number;
  cogs_minor: number;
  gross_profit_minor: number;
  operating_expense_minor: number;
  operating_income_minor: number;
  other_income_minor: number;
  other_expense_minor: number;
  net_income_minor: number;
  profit_margin_bps: number | null;
};

type BranchProfitabilityProps = SharedPageProps & {
  reportData: {
    from_date: string;
    to_date: string;
    branch_id: string | null;
    base_currency: string;
    currency_codes: string[];
    rows: BranchProfitabilityRow[];
    summary: {
      ledger_row_count: number;
      revenue_minor: number;
      contra_revenue_minor: number;
      net_revenue_minor: number;
      cogs_minor: number;
      gross_profit_minor: number;
      operating_expense_minor: number;
      operating_income_minor: number;
      other_income_minor: number;
      other_expense_minor: number;
      net_income_minor: number;
    };
    readiness: {
      branch_dimension_status: string;
      unassigned_pnl_row_count: number;
      unassigned_net_income_minor: number;
      has_unassigned_pnl: boolean;
    };
  };
  filters: {
    branch_id: string;
    date_from: string;
    date_to: string;
  };
  branches: BranchOption[];
};

function formatMargin(bps: number | null, notAvailable: string): string {
  if (bps === null) return notAvailable;
  return `${(bps / 100).toFixed(2)}%`;
}

function marginTone(bps: number | null): 'ok' | 'danger' | 'muted' {
  if (bps === null) return 'muted';
  return bps >= 0 ? 'ok' : 'danger';
}

export default function BranchProfitability({ locale, reportData, filters, branches }: BranchProfitabilityProps) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.branchProfitabilityReport;
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canExport = can('reports.export') && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');
  const [branchId, setBranchId] = useState(filters.branch_id || '');
  const [dateFrom, setDateFrom] = useState(filters.date_from || reportData.from_date);
  const [dateTo, setDateTo] = useState(filters.date_to || reportData.to_date);

  const branchOptions = useMemo(
    () => branches.map((branch) => ({
      value: branch.id,
      label: `${branch.code} - ${getLocalizedName(branch.name, locale)}`,
      sublabel: branch.is_active ? pageDict.active : pageDict.inactive,
    })),
    [branches, locale, pageDict.active, pageDict.inactive],
  );

  function applyFilters(event: FormEvent) {
    event.preventDefault();
    router.get('/reports/branch-profitability', {
      branch_id: branchId,
      date_from: dateFrom,
      date_to: dateTo,
    }, { preserveState: true, replace: true });
  }

  function exportHref(): string {
    const params = new URLSearchParams();
    if (branchId) params.set('branch_id', branchId);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);

    const query = params.toString();
    return `/reports/branch-profitability/export${query ? `?${query}` : ''}`;
  }

  const summary = reportData.summary;

  return (
    <AppLayout active="reports.branch-profitability">
      <Head title={pageDict.headTitle} />

      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={
          canExport || canPrint ? (
            <>
              {canExport ? (
                <a href={exportHref()} className="inline-flex items-center justify-center rounded-xl border border-transparent bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-2xs transition-all hover:opacity-90">
                  {actionsDict.exportCsv}
                </a>
              ) : null}
              {canPrint ? (
                <Button type="button" variant="secondary" onClick={() => window.print()}>
                  {actionsDict.printReport}
                </Button>
              ) : null}
            </>
          ) : null
        }
      />

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
          <MetricCard label={pageDict.netRevenue} value={formatMoney(summary.net_revenue_minor, reportData.base_currency)} tone="blue" />
          <MetricCard label={pageDict.cogs} value={formatMoney(summary.cogs_minor, reportData.base_currency)} tone="amber" />
          <MetricCard label={pageDict.grossProfit} value={formatMoney(summary.gross_profit_minor, reportData.base_currency)} tone="emerald" />
          <MetricCard label={pageDict.operatingExpenses} value={formatMoney(summary.operating_expense_minor, reportData.base_currency)} tone="purple" />
          <MetricCard label={pageDict.netIncome} value={formatMoney(summary.net_income_minor, reportData.base_currency)} tone={summary.net_income_minor >= 0 ? 'emerald' : 'danger'} />
        </div>

        <div className="grid grid-cols-1 gap-4 xl:grid-cols-[1.4fr_1fr]">
          <Card className="p-4">
            <div className="space-y-3">
              <div>
                <h2 className="text-sm font-bold text-[var(--text-primary)]">{pageDict.auditabilityTitle}</h2>
                <p className="mt-1 text-xs leading-5 text-[var(--text-secondary)]">{pageDict.auditabilityDescription}</p>
              </div>
              <div className="flex flex-wrap gap-2 text-xs">
                <span className="rounded-md border border-[var(--border)] bg-[var(--background)] px-2.5 py-1 font-semibold text-[var(--text-secondary)]">
                  {pageDict.baseCurrency}: {reportData.base_currency}
                </span>
                <span className="rounded-md border border-[var(--border)] bg-[var(--background)] px-2.5 py-1 font-semibold text-[var(--text-secondary)]">
                  {pageDict.currenciesInScope}: {reportData.currency_codes.join(', ') || pageDict.notAvailable}
                </span>
                <span className="rounded-md border border-[var(--border)] bg-[var(--background)] px-2.5 py-1 font-semibold text-[var(--text-secondary)]">
                  {pageDict.ledgerRows}: {summary.ledger_row_count.toLocaleString()}
                </span>
              </div>
              {reportData.currency_codes.length > 1 ? (
                <p className="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs leading-5 text-amber-700 dark:text-amber-300">
                  {pageDict.mixedCurrencyWarning}
                </p>
              ) : null}
            </div>
          </Card>

          <Card className="p-4">
            <h2 className="text-sm font-bold text-[var(--text-primary)]">{pageDict.unassignedTitle}</h2>
            <p className="mt-1 text-xs leading-5 text-[var(--text-secondary)]">{pageDict.unassignedDescription}</p>
            <div className="mt-4 grid grid-cols-2 gap-3">
              <div className="rounded-md border border-[var(--border)] bg-[var(--background)] p-3">
                <div className="text-xs font-semibold text-[var(--text-secondary)]">{pageDict.unassignedRows}</div>
                <div className={`mt-1 font-mono text-sm font-bold ${reportData.readiness.has_unassigned_pnl ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'}`}>
                  {reportData.readiness.unassigned_pnl_row_count.toLocaleString()}
                </div>
              </div>
              <div className="rounded-md border border-[var(--border)] bg-[var(--background)] p-3">
                <div className="text-xs font-semibold text-[var(--text-secondary)]">{pageDict.unassignedNetIncome}</div>
                <div className={`mt-1 font-mono text-sm font-bold ${reportData.readiness.has_unassigned_pnl ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'}`}>
                  {formatMoney(reportData.readiness.unassigned_net_income_minor, reportData.base_currency)}
                </div>
              </div>
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
                  <th className={`${tableClasses.th} text-end`}>{pageDict.netRevenue}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.cogs}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.grossProfit}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.operatingExpenses}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.otherNet}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.netIncome}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.margin}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.ledgerRows}</th>
                  <th className={tableClasses.th}>{pageDict.review}</th>
                </tr>
              </thead>
              <tbody>
                {reportData.rows.map((row) => (
                  <tr key={row.branch_id || row.branch_code} className="hover:bg-[var(--background)]">
                    <td className={tableClasses.td}>
                      <div className="flex min-w-52 flex-col gap-1">
                        <span className="font-mono text-xs font-bold">{row.is_unassigned ? pageDict.unassignedBranchCode : row.branch_code}</span>
                        <span className="text-xs text-[var(--text-secondary)]">
                          {row.is_unassigned ? pageDict.unassignedBranchName : getLocalizedName(row.branch_name, locale)}
                        </span>
                        <span className="text-[10px] font-semibold text-[var(--text-muted)]">
                          {row.is_unassigned ? pageDict.requiresReview : row.is_active ? pageDict.active : pageDict.inactive}
                        </span>
                      </div>
                    </td>
                    <td className={`${tableClasses.td} text-end font-mono`}>{formatMoney(row.net_revenue_minor, reportData.base_currency)}</td>
                    <td className={`${tableClasses.td} text-end font-mono`}>{formatMoney(row.cogs_minor, reportData.base_currency)}</td>
                    <td className={`${tableClasses.td} text-end font-mono`}>{formatMoney(row.gross_profit_minor, reportData.base_currency)}</td>
                    <td className={`${tableClasses.td} text-end font-mono`}>{formatMoney(row.operating_expense_minor, reportData.base_currency)}</td>
                    <td className={`${tableClasses.td} text-end font-mono`}>
                      {formatMoney(row.other_income_minor - row.other_expense_minor, reportData.base_currency)}
                    </td>
                    <td className={`${tableClasses.td} text-end font-mono font-bold ${row.net_income_minor >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'}`}>
                      {formatMoney(row.net_income_minor, reportData.base_currency)}
                    </td>
                    <td className={`${tableClasses.td} text-end`}>
                      <StatusBadge tone={marginTone(row.profit_margin_bps)}>
                        {formatMargin(row.profit_margin_bps, pageDict.notAvailable)}
                      </StatusBadge>
                    </td>
                    <td className={`${tableClasses.td} text-end font-mono`}>{row.ledger_row_count.toLocaleString()}</td>
                    <td className={tableClasses.td}>
                      <Link
                        href={`/accounting/ledger${row.branch_id ? `?branch_id=${row.branch_id}` : ''}`}
                        className="text-xs font-bold text-[var(--primary)] no-underline hover:underline"
                      >
                        {pageDict.openLedger}
                      </Link>
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
