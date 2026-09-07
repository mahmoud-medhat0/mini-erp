import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState, type FormEvent, type ReactElement } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, MetricCard, PageHeader, SearchableSelect, tableClasses } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;

type CostCenterOption = {
  id: string;
  code: string;
  name: TranslatedName;
  is_active: boolean;
};

type ProjectOption = {
  id: string;
  code: string;
  name: TranslatedName;
  status?: string;
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

type AccountBreakdownRow = {
  account_id: string;
  account_code: string;
  account_name: TranslatedName;
  account_type: string;
  account_nature: string;
  debit_minor: number;
  credit_minor: number;
  net_minor: number;
  ledger_row_count: number;
};

type CostCenterActualsRow = {
  cost_center_id: string | null;
  cost_center_code: string;
  cost_center_name: TranslatedName;
  cost_center_status: string | null;
  is_unassigned: boolean;
  currency: string;
  ledger_row_count: number;
  debit_minor: number;
  credit_minor: number;
  net_minor: number;
  accounts: AccountBreakdownRow[];
};

type CurrencySummary = {
  currency: string;
  ledger_row_count: number;
  debit_minor: number;
  credit_minor: number;
  net_minor: number;
};

type CostCenterActualsProps = SharedPageProps & {
  reportData: {
    from_date: string;
    to_date: string;
    period_id: string | null;
    cost_center_id: string | null;
    project_id: string | null;
    account_id: string | null;
    currency: string | null;
    base_currency: string;
    currency_codes: string[];
    has_mixed_currencies: boolean;
    summary_by_currency: Record<string, CurrencySummary>;
    readiness: {
      unassigned_row_count: number;
      has_unassigned: boolean;
    };
  };
  filters: {
    period_id: string;
    date_from: string;
    date_to: string;
    cost_center_id: string;
    project_id: string;
    account_id: string;
    currency: string;
  };
  costCenters: CostCenterOption[];
  projects: ProjectOption[];
  accounts: AccountOption[];
  currencies: CurrencyOption[];
  periods: PeriodOption[];
};

type CostCenterActualsDictionary = ReturnType<typeof getDictionary>['app']['pages']['costCenterActualsReport'];

function accountTypeLabel(type: string, pageDict: CostCenterActualsDictionary): string {
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

function accountNatureLabel(nature: string, pageDict: CostCenterActualsDictionary): string {
  const labels: Record<string, string> = {
    debit: pageDict.accountNatureDebit,
    credit: pageDict.accountNatureCredit,
  };

  return labels[nature] ?? nature;
}

export default function CostCenterActuals({
  locale,
  reportData,
  filters,
  costCenters,
  projects,
  accounts,
  currencies,
  periods,
}: CostCenterActualsProps) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.costCenterActualsReport;
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canExport = can('reports.export') && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  const [periodId, setPeriodId] = useState(filters.period_id || '');
  const [dateFrom, setDateFrom] = useState(filters.date_from || reportData.from_date);
  const [dateTo, setDateTo] = useState(filters.date_to || reportData.to_date);
  const [costCenterId, setCostCenterId] = useState(filters.cost_center_id || '');
  const [projectId, setProjectId] = useState(filters.project_id || '');
  const [accountId, setAccountId] = useState(filters.account_id || '');
  const [currency, setCurrency] = useState(filters.currency || '');
  const [expandedRows, setExpandedRows] = useState<Record<string, boolean>>({});

  const costCenterOptions = useMemo(
    () =>
      costCenters.map((cc) => ({
        value: cc.id,
        label: `${cc.code} - ${getLocalizedName(cc.name, locale)}`,
        sublabel: cc.is_active ? pageDict.active : pageDict.inactive,
      })),
    [costCenters, locale, pageDict.active, pageDict.inactive],
  );

  const projectOptions = useMemo(
    () =>
      projects.map((p) => ({
        value: p.id,
        label: `${p.code} - ${getLocalizedName(p.name, locale)}`,
        sublabel: p.is_active ? pageDict.active : pageDict.inactive,
      })),
    [projects, locale, pageDict.active, pageDict.inactive],
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
      '/reports/cost-center-actuals',
      {
        period_id: periodId,
        date_from: dateFrom,
        date_to: dateTo,
        cost_center_id: costCenterId,
        project_id: projectId,
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
    if (costCenterId) params.set('cost_center_id', costCenterId);
    if (projectId) params.set('project_id', projectId);
    if (accountId) params.set('account_id', accountId);
    if (currency) params.set('currency', currency);

    const query = params.toString();
    return `/reports/cost-center-actuals/export${query ? `?${query}` : ''}`;
  }

  function toggleExpand(rowKey: string) {
    setExpandedRows((prev) => ({
      ...prev,
      [rowKey]: !prev[rowKey],
    }));
  }

  const summaries = Object.values(reportData.summary_by_currency);
  const primarySummary = summaries[0] ?? null;
  const showPrimaryMetrics = primarySummary !== null && !reportData.has_mixed_currencies;
  const ledgerRowCount = summaries.reduce((total, summary) => total + summary.ledger_row_count, 0);

  const columns = useMemo(() => [
    { data: 'cost_center_code', name: 'cost_center_code', title: pageDict.costCenterColumn },
    { data: 'currency', name: 'currency', title: pageDict.currencyColumn },
    { data: 'debit_minor', name: 'debit_minor', title: pageDict.debit, className: 'text-end' },
    { data: 'credit_minor', name: 'credit_minor', title: pageDict.credit, className: 'text-end' },
    { data: 'net_minor', name: 'net_minor', title: pageDict.net, className: 'text-end' },
    { data: 'ledger_row_count', name: 'ledger_row_count', title: pageDict.ledgerRows, className: 'text-end' },
    { data: 'cost_center_id', name: 'cost_center_id', title: pageDict.review, orderable: false, searchable: false },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    cost_center_code: (_data: string, _type: unknown, row: CostCenterActualsRow): ReactElement => (
      <div className="flex min-w-52 flex-col gap-1">
        <span className="font-mono text-xs font-bold">
          {row.is_unassigned ? pageDict.unassignedCostCenterCode : row.cost_center_code}
        </span>
        <span className="text-xs text-[var(--text-secondary)]">
          {row.is_unassigned ? pageDict.unassignedCostCenterName : getLocalizedName(row.cost_center_name, locale)}
        </span>
        <span className="text-[10px] font-semibold text-[var(--text-muted)]">
          {row.is_unassigned ? pageDict.requiresReview : row.cost_center_status ?? pageDict.active}
        </span>
      </div>
    ),
    currency: (data: string): ReactElement => <span className="font-mono font-bold text-xs">{data}</span>,
    debit_minor: (_data: number, _type: unknown, row: CostCenterActualsRow): ReactElement => <span className="font-mono">{formatMoney(row.debit_minor, row.currency)}</span>,
    credit_minor: (_data: number, _type: unknown, row: CostCenterActualsRow): ReactElement => <span className="font-mono">{formatMoney(row.credit_minor, row.currency)}</span>,
    net_minor: (_data: number, _type: unknown, row: CostCenterActualsRow): ReactElement => <span className="font-mono font-bold">{formatMoney(row.net_minor, row.currency)}</span>,
    ledger_row_count: (data: number): ReactElement => <span className="font-mono">{Number(data).toLocaleString()}</span>,
    cost_center_id: (_data: string | null, _type: unknown, row: CostCenterActualsRow): ReactElement => {
      const rowKey = `${row.cost_center_id ?? 'unassigned'}__${row.currency}`;
      const isExpanded = !!expandedRows[rowKey];

      return (
        <div className="min-w-72 space-y-3">
          <div className="flex items-center gap-3">
            {row.accounts.length > 0 ? (
              <button
                type="button"
                onClick={() => toggleExpand(rowKey)}
                title={isExpanded ? pageDict.hideAccountBreakdown : pageDict.showAccountBreakdown}
                aria-label={isExpanded ? pageDict.hideAccountBreakdown : pageDict.showAccountBreakdown}
                className="text-xs font-bold text-[var(--primary)] no-underline hover:underline cursor-pointer"
              >
                {isExpanded ? pageDict.hideAccountBreakdown : pageDict.showAccountBreakdown} ({row.accounts.length})
              </button>
            ) : null}
            <Link
              href={`/accounting/ledger${row.cost_center_id ? `?cost_center_id=${row.cost_center_id}` : ''}`}
              className="text-xs font-bold text-[var(--text-secondary)] no-underline hover:underline"
            >
              {pageDict.openLedger}
            </Link>
          </div>

          {isExpanded && row.accounts.length > 0 ? (
            <div className="max-w-[70vw] overflow-x-auto rounded-lg border border-[var(--border)] bg-[var(--surface)] p-3">
              <h4 className="mb-2 text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
                {pageDict.showAccountBreakdown} - {row.is_unassigned ? pageDict.unassignedCostCenterCode : row.cost_center_code} ({row.currency})
              </h4>
              <table className="w-full border-collapse text-left text-xs">
                <thead>
                  <tr className="border-b border-[var(--border)]">
                    <th className="py-2 px-2 text-start font-semibold text-[var(--text-secondary)]">{pageDict.accountColumn}</th>
                    <th className="py-2 px-2 text-start font-semibold text-[var(--text-secondary)]">{pageDict.accountType}</th>
                    <th className="py-2 px-2 text-start font-semibold text-[var(--text-secondary)]">{pageDict.accountNature}</th>
                    <th className="py-2 px-2 text-end font-semibold text-[var(--text-secondary)]">{pageDict.debit}</th>
                    <th className="py-2 px-2 text-end font-semibold text-[var(--text-secondary)]">{pageDict.credit}</th>
                    <th className="py-2 px-2 text-end font-semibold text-[var(--text-secondary)]">{pageDict.net}</th>
                    <th className="py-2 px-2 text-end font-semibold text-[var(--text-secondary)]">{pageDict.ledgerRows}</th>
                  </tr>
                </thead>
                <tbody>
                  {row.accounts.map((account) => (
                    <tr key={account.account_id} className="border-b border-[var(--border)]/40 hover:bg-[var(--background)]">
                      <td className="py-2 px-2 font-mono"><span className="font-bold">{account.account_code}</span> - {getLocalizedName(account.account_name, locale)}</td>
                      <td className="py-2 px-2 text-[var(--text-secondary)]">{accountTypeLabel(account.account_type, pageDict)}</td>
                      <td className="py-2 px-2 text-[var(--text-secondary)]">{accountNatureLabel(account.account_nature, pageDict)}</td>
                      <td className="py-2 px-2 text-end font-mono">{formatMoney(account.debit_minor, row.currency)}</td>
                      <td className="py-2 px-2 text-end font-mono">{formatMoney(account.credit_minor, row.currency)}</td>
                      <td className="py-2 px-2 text-end font-mono font-semibold">{formatMoney(account.net_minor, row.currency)}</td>
                      <td className="py-2 px-2 text-end font-mono">{account.ledger_row_count.toLocaleString()}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : null}
        </div>
      );
    },
  } as unknown as DataTableSlots), [expandedRows, locale, pageDict]);

  return (
    <AppLayout active="reports.cost-center-actuals">
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
                label={pageDict.costCenter}
                options={costCenterOptions}
                value={costCenterId}
                onChange={(val) => setCostCenterId(val || '')}
                placeholder={pageDict.allCostCenters}
              />
              <SearchableSelect
                label={pageDict.project}
                options={projectOptions}
                value={projectId}
                onChange={(val) => setProjectId(val || '')}
                placeholder={pageDict.allProjects}
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
          <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
            <MetricCard
              label={`${pageDict.totalDebit} (${primarySummary.currency})`}
              value={formatMoney(primarySummary.debit_minor, primarySummary.currency)}
              tone="blue"
            />
            <MetricCard
              label={`${pageDict.totalCredit} (${primarySummary.currency})`}
              value={formatMoney(primarySummary.credit_minor, primarySummary.currency)}
              tone="purple"
            />
            <MetricCard
              label={`${pageDict.totalNet} (${primarySummary.currency})`}
              value={formatMoney(primarySummary.net_minor, primarySummary.currency)}
              tone="emerald"
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
                  {pageDict.ledgerRows}: {ledgerRowCount.toLocaleString()}
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
                    reportData.readiness.has_unassigned
                      ? 'text-amber-600 dark:text-amber-400'
                      : 'text-emerald-600 dark:text-emerald-400'
                  }`}
                >
                  {reportData.readiness.unassigned_row_count.toLocaleString()}
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
                    <th className={`${tableClasses.th} text-end`}>{pageDict.debit}</th>
                    <th className={`${tableClasses.th} text-end`}>{pageDict.credit}</th>
                    <th className={`${tableClasses.th} text-end`}>{pageDict.net}</th>
                    <th className={`${tableClasses.th} text-end`}>{pageDict.ledgerRows}</th>
                  </tr>
                </thead>
                <tbody>
                  {summaries.map((s) => (
                    <tr key={s.currency} className="hover:bg-[var(--background)]">
                      <td className={`${tableClasses.td} font-bold font-mono`}>{s.currency}</td>
                      <td className={`${tableClasses.td} text-end font-mono`}>{formatMoney(s.debit_minor, s.currency)}</td>
                      <td className={`${tableClasses.td} text-end font-mono`}>{formatMoney(s.credit_minor, s.currency)}</td>
                      <td className={`${tableClasses.td} text-end font-mono font-bold`}>{formatMoney(s.net_minor, s.currency)}</td>
                      <td className={`${tableClasses.td} text-end font-mono`}>{s.ledger_row_count.toLocaleString()}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>
        )}

        <Card className="overflow-hidden p-0">
          <ServerDataTable
            ajaxUrl="/reports/cost-center-actuals/data"
            columns={columns}
            filters={{
              period_id: filters.period_id || '',
              date_from: filters.date_from || '',
              date_to: filters.date_to || '',
              cost_center_id: filters.cost_center_id || '',
              project_id: filters.project_id || '',
              account_id: filters.account_id || '',
              currency: filters.currency || '',
            }}
            locale={locale}
            order={[[0, 'asc'], [1, 'asc']]}
            pageLength={25}
            slots={slots}
            tableId="cost-center-actuals-table"
          />
        </Card>
      </div>
    </AppLayout>
  );
}
