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

type ProjectOption = {
  id: string;
  code: string;
  name: TranslatedName;
  status?: string;
  is_active: boolean;
};

type CostCenterOption = {
  id: string;
  code: string;
  name: TranslatedName;
  is_active: boolean;
};

type AccountOption = {
  id: string;
  code: string;
  name: TranslatedName;
  type: string;
  nature: string;
  is_active: boolean;
};

type CurrencyOption = {
  code: string;
  name: TranslatedName;
  symbol: string;
};

type PeriodOption = {
  id: string;
  year: number | null;
  month: number;
  start_date: string | null;
  end_date: string | null;
  status: string;
};

type ProjectProfitabilityRow = {
  project_id: string | null;
  project_code: string;
  project_name: TranslatedName;
  project_status: string | null;
  is_unassigned: boolean;
  currency: string;
  ledger_row_count: number;
  debit_minor: number;
  credit_minor: number;
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

type CurrencySummary = {
  currency: string;
  ledger_row_count: number;
  debit_minor: number;
  credit_minor: number;
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

type ProjectProfitabilityProps = SharedPageProps & {
  reportData: {
    from_date: string;
    to_date: string;
    period_id: string | null;
    project_id: string | null;
    cost_center_id: string | null;
    account_id: string | null;
    currency: string | null;
    base_currency: string;
    currency_codes: string[];
    has_mixed_currencies: boolean;
    rows: ProjectProfitabilityRow[];
    summary_by_currency: Record<string, CurrencySummary>;
    readiness: {
      unassigned_pnl_row_count: number;
      has_unassigned_pnl: boolean;
    };
  };
  filters: {
    period_id: string;
    date_from: string;
    date_to: string;
    project_id: string;
    cost_center_id: string;
    account_id: string;
    currency: string;
  };
  projects: ProjectOption[];
  costCenters: CostCenterOption[];
  accounts: AccountOption[];
  currencies: CurrencyOption[];
  periods: PeriodOption[];
};

function formatMargin(bps: number | null, notAvailable: string): string {
  if (bps === null) return notAvailable;
  return `${(bps / 100).toFixed(2)}%`;
}

function marginTone(bps: number | null): 'ok' | 'danger' | 'muted' {
  if (bps === null) return 'muted';
  return bps >= 0 ? 'ok' : 'danger';
}

type ProjectProfitabilityDictionary = ReturnType<typeof getDictionary>['app']['pages']['projectProfitabilityReport'];

function accountTypeLabel(type: string, pageDict: ProjectProfitabilityDictionary): string {
  const labels: Record<string, string> = {
    asset: pageDict.accountTypeAsset,
    liability: pageDict.accountTypeLiability,
    equity: pageDict.accountTypeEquity,
    revenue: pageDict.accountTypeRevenue,
    expense: pageDict.accountTypeExpense,
    contra_asset: pageDict.accountTypeContraAsset,
    contra_liability: pageDict.accountTypeContraLiability,
    contra_revenue: pageDict.accountTypeContraRevenue,
  };

  return labels[type] ?? type;
}

function accountNatureLabel(nature: string, pageDict: ReturnType<typeof getDictionary>['app']['pages']['projectProfitabilityReport']): string {
  const labels: Record<string, string> = {
    debit: pageDict.accountNatureDebit,
    credit: pageDict.accountNatureCredit,
  };

  return labels[nature] ?? nature;
}

export default function ProjectProfitability({
  locale,
  reportData,
  filters,
  projects,
  costCenters,
  accounts,
  currencies,
  periods,
}: ProjectProfitabilityProps) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.projectProfitabilityReport;
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canExport = can('reports.export') && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  const [periodId, setPeriodId] = useState(filters.period_id || '');
  const [dateFrom, setDateFrom] = useState(filters.date_from || reportData.from_date);
  const [dateTo, setDateTo] = useState(filters.date_to || reportData.to_date);
  const [projectId, setProjectId] = useState(filters.project_id || '');
  const [costCenterId, setCostCenterId] = useState(filters.cost_center_id || '');
  const [accountId, setAccountId] = useState(filters.account_id || '');
  const [currency, setCurrency] = useState(filters.currency || '');

  const projectOptions = useMemo(
    () =>
      projects.map((p) => ({
        value: p.id,
        label: `${p.code} - ${getLocalizedName(p.name, locale)}`,
        sublabel: p.is_active ? pageDict.active : pageDict.inactive,
      })),
    [projects, locale, pageDict.active, pageDict.inactive],
  );

  const costCenterOptions = useMemo(
    () =>
      costCenters.map((cc) => ({
        value: cc.id,
        label: `${cc.code} - ${getLocalizedName(cc.name, locale)}`,
        sublabel: cc.is_active ? pageDict.active : pageDict.inactive,
      })),
    [costCenters, locale, pageDict.active, pageDict.inactive],
  );

  const accountOptions = useMemo(
    () =>
      accounts.map((acc) => ({
        value: acc.id,
        label: `${acc.code} - ${getLocalizedName(acc.name, locale)}`,
        sublabel: `${accountTypeLabel(acc.type, pageDict)} (${accountNatureLabel(acc.nature, pageDict)})`,
      })),
    [accounts, locale, pageDict],
  );

  const currencyOptions = useMemo(
    () =>
      currencies.map((c) => ({
        value: c.code,
        label: `${c.code} - ${getLocalizedName(c.name, locale)}`,
      })),
    [currencies, locale],
  );

  const periodOptions = useMemo(
    () =>
      periods.map((p) => ({
        value: p.id,
        label: `${p.year ?? ''} - ${pageDict.monthLabel} ${p.month} (${p.start_date ?? ''} - ${p.end_date ?? ''})`,
        sublabel: p.status,
      })),
    [periods, pageDict.monthLabel],
  );

  function handlePeriodChange(selectedPeriodId: string) {
    setPeriodId(selectedPeriodId);
    if (selectedPeriodId) {
      const selected = periods.find((p) => p.id === selectedPeriodId);
      if (selected && selected.start_date && selected.end_date) {
        setDateFrom(selected.start_date);
        setDateTo(selected.end_date);
      }
    }
  }

  function applyFilters(event: FormEvent) {
    event.preventDefault();
    router.get(
      '/reports/project-profitability',
      {
        period_id: periodId,
        date_from: dateFrom,
        date_to: dateTo,
        project_id: projectId,
        cost_center_id: costCenterId,
        account_id: accountId,
        currency: currency,
      },
      { preserveState: true, replace: true },
    );
  }

  function exportHref(): string {
    const params = new URLSearchParams();
    if (periodId) params.set('period_id', periodId);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);
    if (projectId) params.set('project_id', projectId);
    if (costCenterId) params.set('cost_center_id', costCenterId);
    if (accountId) params.set('account_id', accountId);
    if (currency) params.set('currency', currency);

    const query = params.toString();
    return `/reports/project-profitability/export${query ? `?${query}` : ''}`;
  }

  const summaries = Object.values(reportData.summary_by_currency);
  const primarySummary = summaries[0] ?? null;
  const showPrimaryMetrics = primarySummary !== null && !reportData.has_mixed_currencies;

  return (
    <AppLayout active="reports.project-profitability">
      <Head title={pageDict.headTitle} />

      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={
          canExport || canPrint ? (
            <>
              {canExport ? (
                <a
                  href={exportHref()}
                  className="inline-flex items-center justify-center rounded-xl border border-transparent bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-2xs transition-all hover:opacity-90"
                >
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
          <form onSubmit={applyFilters} className="space-y-4">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
              <SearchableSelect
                label={pageDict.period}
                options={periodOptions}
                value={periodId}
                onChange={(val) => handlePeriodChange(val || '')}
                placeholder={pageDict.allPeriods}
              />
              <DatePicker label={pageDict.dateFrom} value={dateFrom} onChange={(val) => setDateFrom(val || '')} />
              <DatePicker label={pageDict.dateTo} value={dateTo} onChange={(val) => setDateTo(val || '')} />
              <SearchableSelect
                label={pageDict.currency}
                options={currencyOptions}
                value={currency}
                onChange={(val) => setCurrency(val || '')}
                placeholder={pageDict.allCurrencies}
              />
            </div>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-4 md:items-end">
              <SearchableSelect
                label={pageDict.project}
                options={projectOptions}
                value={projectId}
                onChange={(val) => setProjectId(val || '')}
                placeholder={pageDict.allProjects}
              />
              <SearchableSelect
                label={pageDict.costCenter}
                options={costCenterOptions}
                value={costCenterId}
                onChange={(val) => setCostCenterId(val || '')}
                placeholder={pageDict.allCostCenters}
              />
              <SearchableSelect
                label={pageDict.account}
                options={accountOptions}
                value={accountId}
                onChange={(val) => setAccountId(val || '')}
                placeholder={pageDict.allAccounts}
              />
              <Button type="submit" className="h-[42px]">
                {pageDict.filter}
              </Button>
            </div>
          </form>
        </Card>

        {showPrimaryMetrics && (
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <MetricCard
              label={`${pageDict.netRevenue} (${primarySummary.currency})`}
              value={formatMoney(primarySummary.net_revenue_minor, primarySummary.currency)}
              tone="blue"
            />
            <MetricCard
              label={`${pageDict.cogs} (${primarySummary.currency})`}
              value={formatMoney(primarySummary.cogs_minor, primarySummary.currency)}
              tone="amber"
            />
            <MetricCard
              label={`${pageDict.grossProfit} (${primarySummary.currency})`}
              value={formatMoney(primarySummary.gross_profit_minor, primarySummary.currency)}
              tone="emerald"
            />
            <MetricCard
              label={`${pageDict.operatingExpenses} (${primarySummary.currency})`}
              value={formatMoney(primarySummary.operating_expense_minor, primarySummary.currency)}
              tone="purple"
            />
            <MetricCard
              label={`${pageDict.netIncome} (${primarySummary.currency})`}
              value={formatMoney(primarySummary.net_income_minor, primarySummary.currency)}
              tone={primarySummary.net_income_minor >= 0 ? 'emerald' : 'danger'}
            />
          </div>
        )}

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
                  {pageDict.ledgerRows}: {reportData.rows.reduce((acc, r) => acc + r.ledger_row_count, 0).toLocaleString()}
                </span>
              </div>
              {reportData.has_mixed_currencies ? (
                <p className="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs leading-5 text-amber-700 dark:text-amber-300">
                  {pageDict.mixedCurrencyWarning}
                </p>
              ) : null}
            </div>
          </Card>

          <Card className="p-4">
            <h2 className="text-sm font-bold text-[var(--text-primary)]">{pageDict.unassignedTitle}</h2>
            <p className="mt-1 text-xs leading-5 text-[var(--text-secondary)]">{pageDict.unassignedDescription}</p>
            <div className="mt-4 grid grid-cols-1 gap-3">
              <div className="rounded-md border border-[var(--border)] bg-[var(--background)] p-3">
                <div className="text-xs font-semibold text-[var(--text-secondary)]">{pageDict.unassignedRows}</div>
                <div
                  className={`mt-1 font-mono text-sm font-bold ${
                    reportData.readiness.has_unassigned_pnl
                      ? 'text-amber-600 dark:text-amber-400'
                      : 'text-emerald-600 dark:text-emerald-400'
                  }`}
                >
                  {reportData.readiness.unassigned_pnl_row_count.toLocaleString()}
                </div>
              </div>
            </div>
          </Card>
        </div>

        {reportData.has_mixed_currencies && summaries.length > 1 && (
          <Card className="p-4">
            <h2 className="text-sm font-bold text-[var(--text-primary)] mb-3">{pageDict.summaryByCurrency}</h2>
            <div className={tableClasses.wrap}>
              <table className={tableClasses.table}>
                <thead>
                  <tr>
                    <th className={tableClasses.th}>{pageDict.currencyColumn}</th>
                    <th className={`${tableClasses.th} text-end`}>{pageDict.netRevenue}</th>
                    <th className={`${tableClasses.th} text-end`}>{pageDict.cogs}</th>
                    <th className={`${tableClasses.th} text-end`}>{pageDict.grossProfit}</th>
                    <th className={`${tableClasses.th} text-end`}>{pageDict.operatingExpenses}</th>
                    <th className={`${tableClasses.th} text-end`}>{pageDict.netIncome}</th>
                    <th className={`${tableClasses.th} text-end`}>{pageDict.margin}</th>
                    <th className={`${tableClasses.th} text-end`}>{pageDict.ledgerRows}</th>
                  </tr>
                </thead>
                <tbody>
                  {summaries.map((s) => (
                    <tr key={s.currency} className="hover:bg-[var(--background)]">
                      <td className={`${tableClasses.td} font-bold font-mono`}>{s.currency}</td>
                      <td className={`${tableClasses.td} text-end font-mono`}>{formatMoney(s.net_revenue_minor, s.currency)}</td>
                      <td className={`${tableClasses.td} text-end font-mono`}>{formatMoney(s.cogs_minor, s.currency)}</td>
                      <td className={`${tableClasses.td} text-end font-mono`}>{formatMoney(s.gross_profit_minor, s.currency)}</td>
                      <td className={`${tableClasses.td} text-end font-mono`}>{formatMoney(s.operating_expense_minor, s.currency)}</td>
                      <td
                        className={`${tableClasses.td} text-end font-mono font-bold ${
                          s.net_income_minor >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'
                        }`}
                      >
                        {formatMoney(s.net_income_minor, s.currency)}
                      </td>
                      <td className={`${tableClasses.td} text-end`}>
                        <StatusBadge tone={marginTone(s.profit_margin_bps)}>
                          {formatMargin(s.profit_margin_bps, pageDict.notAvailable)}
                        </StatusBadge>
                      </td>
                      <td className={`${tableClasses.td} text-end font-mono`}>{s.ledger_row_count.toLocaleString()}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>
        )}

        {reportData.rows.length === 0 ? (
          <EmptyState title={pageDict.emptyTitle} description={pageDict.emptyDescription} />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{pageDict.projectColumn}</th>
                  <th className={tableClasses.th}>{pageDict.currencyColumn}</th>
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
                  <tr key={`${row.project_id ?? 'unassigned'}__${row.currency}`} className="hover:bg-[var(--background)]">
                    <td className={tableClasses.td}>
                      <div className="flex min-w-52 flex-col gap-1">
                        <span className="font-mono text-xs font-bold">
                          {row.is_unassigned ? pageDict.unassignedProjectCode : row.project_code}
                        </span>
                        <span className="text-xs text-[var(--text-secondary)]">
                          {row.is_unassigned ? pageDict.unassignedProjectName : getLocalizedName(row.project_name, locale)}
                        </span>
                        <span className="text-[10px] font-semibold text-[var(--text-muted)]">
                          {row.is_unassigned ? pageDict.requiresReview : row.project_status ?? pageDict.active}
                        </span>
                      </div>
                    </td>
                    <td className={`${tableClasses.td} font-mono font-bold text-xs`}>{row.currency}</td>
                    <td className={`${tableClasses.td} text-end font-mono`}>{formatMoney(row.net_revenue_minor, row.currency)}</td>
                    <td className={`${tableClasses.td} text-end font-mono`}>{formatMoney(row.cogs_minor, row.currency)}</td>
                    <td className={`${tableClasses.td} text-end font-mono`}>{formatMoney(row.gross_profit_minor, row.currency)}</td>
                    <td className={`${tableClasses.td} text-end font-mono`}>{formatMoney(row.operating_expense_minor, row.currency)}</td>
                    <td className={`${tableClasses.td} text-end font-mono`}>
                      {formatMoney(row.other_income_minor - row.other_expense_minor, row.currency)}
                    </td>
                    <td
                      className={`${tableClasses.td} text-end font-mono font-bold ${
                        row.net_income_minor >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'
                      }`}
                    >
                      {formatMoney(row.net_income_minor, row.currency)}
                    </td>
                    <td className={`${tableClasses.td} text-end`}>
                      <StatusBadge tone={marginTone(row.profit_margin_bps)}>
                        {formatMargin(row.profit_margin_bps, pageDict.notAvailable)}
                      </StatusBadge>
                    </td>
                    <td className={`${tableClasses.td} text-end font-mono`}>{row.ledger_row_count.toLocaleString()}</td>
                    <td className={tableClasses.td}>
                      <Link
                        href={`/accounting/ledger${row.project_id ? `?project_id=${row.project_id}` : ''}`}
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
