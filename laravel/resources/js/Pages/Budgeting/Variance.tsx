import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, EmptyState, MetricCard, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type {
  BudgetVarianceCurrencySummary,
  BudgetVarianceRow,
  BudgetVarianceWarningCode,
  SharedPageProps,
} from '../../Types';

type TranslatedName = Record<string, string> | string | null;

type BudgetOption = {
  id: string;
  fiscal_year_id: string;
  code: string;
  version_code: string;
  name: TranslatedName;
  status: string;
  default_currency: string;
  fiscal_year?: {
    id: string;
    year: number;
  };
};

type FiscalYearOption = {
  id: string;
  year: number;
  start_date: string | null;
  end_date: string | null;
  status: string;
  periods?: Array<{
    id: string;
    month: number;
    start_date: string | null;
    end_date: string | null;
    status: string;
  }>;
};

type PeriodOption = {
  id: string;
  fiscal_year_id: string;
  month: number;
  start_date: string | null;
  end_date: string | null;
  status: string;
  fiscal_year?: {
    id: string;
    year: number;
  };
};

type AccountOption = {
  id: string;
  code: string;
  name: TranslatedName;
  type: string;
  nature: string;
  currency: string;
  is_active: boolean;
};

type ProjectOption = {
  id: string;
  code: string;
  name: TranslatedName;
  status: string;
  is_active: boolean;
};

type CostCenterOption = {
  id: string;
  code: string;
  name: TranslatedName;
  category: string | null;
  is_active: boolean;
};

type CurrencyOption = {
  code: string;
  name: TranslatedName;
  symbol: string;
};

type BudgetVarianceProps = SharedPageProps & {
  report: {
    selected_budget: {
      id: string;
      code: string;
      version_code: string;
      name: TranslatedName;
      description: string | null;
      status: string;
      default_currency: string;
      fiscal_year_id: string;
      fiscal_year: number | null;
    } | null;
    filters: {
      budget_id: string | null;
      fiscal_year_id: string | null;
      period_id: string | null;
      from_date: string | null;
      to_date: string | null;
      account_id: string | null;
      project_id: string | null;
      cost_center_id: string | null;
      currency: string | null;
    };
    periods: Array<{
      id: string;
      month: number;
      start_date: string | null;
      end_date: string | null;
    }>;
    rows: BudgetVarianceRow[];
    summary_by_currency: Record<string, BudgetVarianceCurrencySummary>;
    warning_codes: BudgetVarianceWarningCode[];
    has_warnings: boolean;
  };
  filters: {
    budget_id: string;
    fiscal_year_id: string;
    period_id: string;
    from_date: string;
    to_date: string;
    account_id: string;
    project_id: string;
    cost_center_id: string;
    currency: string;
  };
  options: {
    budgets: BudgetOption[];
    fiscalYears: FiscalYearOption[];
    financialPeriods: PeriodOption[];
    accounts: AccountOption[];
    projects: ProjectOption[];
    costCenters: CostCenterOption[];
    currencies: CurrencyOption[];
  };
};

export default function Variance({
  auth,
  report,
  filters,
  options,
}: BudgetVarianceProps) {
  const can = useCan();
  const locale = auth?.user?.locale || 'en';
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.budgetVarianceReport;

  const [budgetId, setBudgetId] = useState<string>(filters.budget_id || '');
  const [fiscalYearId, setFiscalYearId] = useState<string>(filters.fiscal_year_id || '');
  const [periodId, setPeriodId] = useState<string>(filters.period_id || '');
  const [fromDate, setFromDate] = useState<string>(filters.from_date || '');
  const [toDate, setToDate] = useState<string>(filters.to_date || '');
  const [accountId, setAccountId] = useState<string>(filters.account_id || '');
  const [projectId, setProjectId] = useState<string>(filters.project_id || '');
  const [costCenterId, setCostCenterId] = useState<string>(filters.cost_center_id || '');
  const [currency, setCurrency] = useState<string>(filters.currency || '');

  const canExport = can('budgeting.export') && can('reports.export') && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  function budgetStatusLabel(status: string): string {
    const map: Record<string, string> = {
      draft: pageDict.statusDraft,
      submitted: pageDict.statusSubmitted,
      approved: pageDict.statusApproved,
      active: pageDict.statusActive,
      archived: pageDict.statusArchived,
      cancelled: pageDict.statusCancelled,
    };

    return map[status] ?? pageDict.notAvailable;
  }

  function fiscalYearStatusLabel(status: string): string {
    const map: Record<string, string> = {
      open: pageDict.statusOpen,
      closed: pageDict.statusClosed,
    };

    return map[status] ?? pageDict.notAvailable;
  }

  function projectStatusLabel(status: string): string {
    const map: Record<string, string> = {
      active: pageDict.projectStatusActive,
      on_hold: pageDict.projectStatusOnHold,
      completed: pageDict.projectStatusCompleted,
      cancelled: pageDict.projectStatusCancelled,
    };

    return map[status] ?? pageDict.notAvailable;
  }

  function costCenterCategoryLabel(category: string | null): string | undefined {
    if (!category) return undefined;

    const map: Record<string, string> = {
      administrative: pageDict.costCenterCategoryAdministrative,
      sales: pageDict.costCenterCategorySales,
      operations: pageDict.costCenterCategoryOperations,
      finance: pageDict.costCenterCategoryFinance,
      other: pageDict.costCenterCategoryOther,
    };

    return map[category] ?? pageDict.notAvailable;
  }

  function accountTypeLabel(type: string): string {
    const map: Record<string, string> = {
      asset: pageDict.accountTypeAsset,
      liability: pageDict.accountTypeLiability,
      equity: pageDict.accountTypeEquity,
      revenue: pageDict.accountTypeRevenue,
      expense: pageDict.accountTypeExpense,
      cogs: pageDict.accountTypeCogs,
      contra_asset: pageDict.accountTypeContraAsset,
      contra_liability: pageDict.accountTypeContraLiability,
      contra_revenue: pageDict.accountTypeContraRevenue,
    };

    return map[type] ?? pageDict.notAvailable;
  }

  function accountNatureLabel(nature: string): string {
    const map: Record<string, string> = {
      debit: pageDict.accountNatureDebit,
      credit: pageDict.accountNatureCredit,
    };

    return map[nature] ?? pageDict.notAvailable;
  }

  function formatBasisPoints(bps: number | null): string {
    if (bps === null) return pageDict.notAvailable;

    const sign = bps < 0 ? '-' : '';
    const absoluteBps = Math.abs(bps);
    const whole = Math.trunc(absoluteBps / 100);
    const fraction = String(absoluteBps % 100).padStart(2, '0');

    return `${sign}${whole}.${fraction}%`;
  }

  const budgetOptions = useMemo(
    () => [
      { value: '', label: pageDict.allBudgets },
      ...options.budgets.map((b) => ({
        value: b.id,
        label: `${b.code} (${b.version_code}) - ${getLocalizedName(b.name, locale)}`,
        sublabel: `${budgetStatusLabel(b.status)} - ${b.default_currency}`,
      })),
    ],
    [options.budgets, locale, pageDict.allBudgets, pageDict.notAvailable, pageDict.statusActive, pageDict.statusApproved, pageDict.statusArchived, pageDict.statusCancelled, pageDict.statusDraft, pageDict.statusSubmitted],
  );

  const fiscalYearOptions = useMemo(
    () => [
      { value: '', label: pageDict.allFiscalYears },
      ...options.fiscalYears.map((fy) => ({
        value: fy.id,
        label: `${fy.year} (${fiscalYearStatusLabel(fy.status)})`,
      })),
    ],
    [options.fiscalYears, pageDict.allFiscalYears, pageDict.notAvailable, pageDict.statusClosed, pageDict.statusOpen],
  );

  const filteredPeriods = useMemo(() => {
    if (budgetId) {
      const selectedB = options.budgets.find((b) => b.id === budgetId);
      if (selectedB) {
        return options.financialPeriods.filter((p) => p.fiscal_year_id === selectedB.fiscal_year_id);
      }
    }
    if (fiscalYearId) {
      return options.financialPeriods.filter((p) => p.fiscal_year_id === fiscalYearId);
    }
    return options.financialPeriods;
  }, [options.financialPeriods, options.budgets, budgetId, fiscalYearId]);

  const periodOptions = useMemo(
    () => [
      { value: '', label: pageDict.allPeriods },
      ...filteredPeriods.map((p) => ({
        value: p.id,
        label: `${p.fiscal_year?.year ?? ''} - ${pageDict.monthLabel} ${p.month} (${p.start_date ?? ''} - ${p.end_date ?? ''})`,
        sublabel: fiscalYearStatusLabel(p.status),
      })),
    ],
    [filteredPeriods, pageDict.allPeriods, pageDict.monthLabel, pageDict.notAvailable, pageDict.statusClosed, pageDict.statusOpen],
  );

  const accountOptions = useMemo(
    () => [
      { value: '', label: pageDict.allAccounts },
      ...options.accounts.map((acc) => ({
        value: acc.id,
        label: `${acc.code} - ${getLocalizedName(acc.name, locale)}`,
        sublabel: `${accountTypeLabel(acc.type)} - ${accountNatureLabel(acc.nature)}`,
      })),
    ],
    [options.accounts, locale, pageDict.accountNatureCredit, pageDict.accountNatureDebit, pageDict.accountTypeAsset, pageDict.accountTypeCogs, pageDict.accountTypeContraAsset, pageDict.accountTypeContraLiability, pageDict.accountTypeContraRevenue, pageDict.accountTypeEquity, pageDict.accountTypeExpense, pageDict.accountTypeLiability, pageDict.accountTypeRevenue, pageDict.allAccounts, pageDict.notAvailable],
  );

  const projectOptions = useMemo(
    () => [
      { value: '', label: pageDict.allProjects },
      ...options.projects.map((prj) => ({
        value: prj.id,
        label: `${prj.code} - ${getLocalizedName(prj.name, locale)}`,
        sublabel: projectStatusLabel(prj.status),
      })),
    ],
    [options.projects, locale, pageDict.allProjects, pageDict.notAvailable, pageDict.projectStatusActive, pageDict.projectStatusCancelled, pageDict.projectStatusCompleted, pageDict.projectStatusOnHold],
  );

  const costCenterOptions = useMemo(
    () => [
      { value: '', label: pageDict.allCostCenters },
      ...options.costCenters.map((cc) => ({
        value: cc.id,
        label: `${cc.code} - ${getLocalizedName(cc.name, locale)}`,
        sublabel: costCenterCategoryLabel(cc.category),
      })),
    ],
    [options.costCenters, locale, pageDict.allCostCenters, pageDict.costCenterCategoryAdministrative, pageDict.costCenterCategoryFinance, pageDict.costCenterCategoryOperations, pageDict.costCenterCategoryOther, pageDict.costCenterCategorySales, pageDict.notAvailable],
  );

  const currencyOptions = useMemo(
    () => [
      { value: '', label: pageDict.allCurrencies },
      ...options.currencies.map((c) => ({
        value: c.code,
        label: `${c.code} - ${getLocalizedName(c.name, locale)} (${c.symbol})`,
      })),
    ],
    [options.currencies, locale, pageDict.allCurrencies],
  );

  function handlePeriodChange(selectedPeriodId: string) {
    setPeriodId(selectedPeriodId);
    if (selectedPeriodId) {
      const selected = options.financialPeriods.find((p) => p.id === selectedPeriodId);
      if (selected && selected.start_date && selected.end_date) {
        setFromDate(selected.start_date);
        setToDate(selected.end_date);
      }
    }
  }

  function handleBudgetChange(selectedBudgetId: string) {
    setBudgetId(selectedBudgetId);
    if (selectedBudgetId) {
      const b = options.budgets.find((item) => item.id === selectedBudgetId);
      if (b) {
        setFiscalYearId(b.fiscal_year_id);
      }
    }
  }

  function applyFilters(event: FormEvent) {
    event.preventDefault();
    router.get(
      '/budgeting/variance',
      {
        budget_id: budgetId || undefined,
        fiscal_year_id: fiscalYearId || undefined,
        period_id: periodId || undefined,
        from_date: fromDate || undefined,
        to_date: toDate || undefined,
        account_id: accountId || undefined,
        project_id: projectId || undefined,
        cost_center_id: costCenterId || undefined,
        currency: currency || undefined,
      },
      { preserveState: true, replace: true },
    );
  }

  function clearFilters() {
    setBudgetId('');
    setFiscalYearId('');
    setPeriodId('');
    setFromDate('');
    setToDate('');
    setAccountId('');
    setProjectId('');
    setCostCenterId('');
    setCurrency('');
    router.get('/budgeting/variance', {}, { preserveState: false });
  }

  function exportHref(): string {
    const params = new URLSearchParams();
    if (budgetId) params.set('budget_id', budgetId);
    if (fiscalYearId) params.set('fiscal_year_id', fiscalYearId);
    if (periodId) params.set('period_id', periodId);
    if (fromDate) params.set('from_date', fromDate);
    if (toDate) params.set('to_date', toDate);
    if (accountId) params.set('account_id', accountId);
    if (projectId) params.set('project_id', projectId);
    if (costCenterId) params.set('cost_center_id', costCenterId);
    if (currency) params.set('currency', currency);

    const query = params.toString();
    return `/budgeting/variance/export${query ? `?${query}` : ''}`;
  }

  function renderRowTypeBadge(rowType: string) {
    if (rowType === 'matched') {
      return <StatusBadge tone="ok">{pageDict.matched}</StatusBadge>;
    }
    if (rowType === 'budget_only') {
      return <StatusBadge tone="info">{pageDict.budgetOnly}</StatusBadge>;
    }
    return <StatusBadge tone="warning">{pageDict.actualOnly}</StatusBadge>;
  }

  function getVarianceToneClass(row: BudgetVarianceRow): string {
    if (row.variance_minor === 0) {
      return 'text-[var(--text-secondary)] font-medium';
    }

    const type = row.account_type?.toLowerCase() || '';
    if (type === 'expense' || type === 'cogs') {
      // Over budget is unfavorable for expense
      return row.variance_minor > 0
        ? 'text-red-600 dark:text-red-400 font-semibold'
        : 'text-emerald-600 dark:text-emerald-400 font-semibold';
    }
    if (type === 'revenue') {
      // Below budget is unfavorable for revenue
      return row.variance_minor < 0
        ? 'text-red-600 dark:text-red-400 font-semibold'
        : 'text-emerald-600 dark:text-emerald-400 font-semibold';
    }

    return 'text-[var(--text-primary)] font-semibold';
  }

  const currencySummaries = useMemo(
    () => Object.values(report.summary_by_currency || {}),
    [report.summary_by_currency],
  );

  const selectedBudgetTone = report.selected_budget?.status === 'active' ? 'ok' : 'info';

  return (
    <AppLayout active="budgeting.variance">
      <Head title={pageDict.headTitle} />

      <div className="space-y-6">
        <PageHeader
          title={pageDict.title}
          description={pageDict.description}
          actions={
            <div className="flex items-center gap-2">
              {canPrint ? (
                <Button variant="secondary" onClick={() => window.print()} title={pageDict.print} aria-label={pageDict.print}>
                  {pageDict.print}
                </Button>
              ) : null}
              {canExport ? (
                <a
                  href={exportHref()}
                  title={pageDict.exportCsv}
                  aria-label={pageDict.exportCsv}
                  className="inline-flex items-center justify-center rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] no-underline transition-all shadow-2xs"
                >
                  {pageDict.exportCsv}
                </a>
              ) : null}
            </div>
          }
        />

        {/* Selected Budget Info Banner */}
        {report.selected_budget ? (
          <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-blue-500/20 bg-blue-500/5 px-4 py-3 text-sm">
            <div className="flex flex-wrap items-center gap-3">
              <span className="font-bold text-[var(--text-primary)]">
                {pageDict.selectedBudgetTitle}:
              </span>
              <span className="font-semibold text-blue-600 dark:text-blue-400">
                {report.selected_budget.code}
              </span>
              <span className="text-xs text-[var(--text-secondary)]">
                ({pageDict.version}: {report.selected_budget.version_code})
              </span>
              <span className="text-xs text-[var(--text-secondary)]">
                {getLocalizedName(report.selected_budget.name, locale)}
              </span>
            </div>
            <div className="flex items-center gap-2">
              <span className="text-xs text-[var(--text-secondary)]">
                {pageDict.fiscalYear}: {report.selected_budget.fiscal_year}
              </span>
              <StatusBadge tone={selectedBudgetTone}>
                {budgetStatusLabel(report.selected_budget.status)}
              </StatusBadge>
              <span className="rounded bg-[var(--surface)] px-2 py-0.5 text-xs font-mono font-bold text-[var(--text-secondary)] border border-[var(--border)]">
                {report.selected_budget.default_currency}
              </span>
            </div>
          </div>
        ) : null}

        {/* Warnings Banner */}
        {report.has_warnings ? (
          <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-300">
            <div className="font-semibold mb-1">{pageDict.warningsTitle}</div>
            <ul className="list-disc list-inside space-y-1 text-xs">
              {report.warning_codes.map((code) => {
                const message = pageDict.warnings?.[code] || code;
                return <li key={code}>{message}</li>;
              })}
            </ul>
          </div>
        ) : null}

        {/* Filters Card */}
        <Card className="p-4">
          <form onSubmit={applyFilters} className="space-y-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
              <div>
                <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
                  {pageDict.budget}
                </label>
                <SearchableSelect
                  value={budgetId}
                  onChange={(val) => handleBudgetChange(val || '')}
                  options={budgetOptions}
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
                  {pageDict.fiscalYear}
                </label>
                <SearchableSelect
                  value={fiscalYearId}
                  onChange={(val) => setFiscalYearId(val || '')}
                  options={fiscalYearOptions}
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
                  {pageDict.period}
                </label>
                <SearchableSelect
                  value={periodId}
                  onChange={(val) => handlePeriodChange(val || '')}
                  options={periodOptions}
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
                  {pageDict.account}
                </label>
                <SearchableSelect
                  value={accountId}
                  onChange={(val) => setAccountId(val || '')}
                  options={accountOptions}
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
                  {pageDict.project}
                </label>
                <SearchableSelect
                  value={projectId}
                  onChange={(val) => setProjectId(val || '')}
                  options={projectOptions}
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
                  {pageDict.costCenter}
                </label>
                <SearchableSelect
                  value={costCenterId}
                  onChange={(val) => setCostCenterId(val || '')}
                  options={costCenterOptions}
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
                  {pageDict.currency}
                </label>
                <SearchableSelect
                  value={currency}
                  onChange={(val) => setCurrency(val || '')}
                  options={currencyOptions}
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
                  {pageDict.dateFrom}
                </label>
                <DatePicker
                  value={fromDate}
                  onChange={(val) => setFromDate(val || '')}
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">
                  {pageDict.dateTo}
                </label>
                <DatePicker
                  value={toDate}
                  onChange={(val) => setToDate(val || '')}
                />
              </div>
            </div>

            <div className="flex items-center justify-end gap-2 pt-2 border-t border-[var(--border)]">
              <Button variant="secondary" type="button" onClick={clearFilters}>
                {pageDict.clearFilter}
              </Button>
              <Button variant="primary" type="submit">
                {pageDict.applyFilter}
              </Button>
            </div>
          </form>
        </Card>

        {/* Currency Summary Metric Cards */}
        {currencySummaries.length > 0 ? (
          <div className="space-y-4">
            <h3 className="text-sm font-bold text-[var(--text-primary)]">
              {pageDict.summaryCards}
            </h3>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
              {currencySummaries.map((summary) => (
                <MetricCard
                  key={summary.currency}
                  label={`${summary.currency} (${summary.row_count} ${pageDict.rowCount})`}
                  value={formatMoney(summary.variance_minor, summary.currency)}
                  hint={`${pageDict.budgetMinor}: ${formatMoney(summary.budget_minor, summary.currency)} | ${pageDict.actualMinor}: ${formatMoney(summary.actual_minor, summary.currency)}`}
                  tone={summary.variance_minor >= 0 ? 'emerald' : 'amber'}
                />
              ))}
            </div>
          </div>
        ) : null}

        {/* Variance Report Table */}
        <Card className="overflow-hidden">
          {report.rows.length === 0 ? (
            <div className="p-8">
              <EmptyState
                title={pageDict.emptyTitle}
                description={pageDict.emptyDescription}
              />
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className={tableClasses.table}>
                <thead>
                  <tr>
                    <th className={tableClasses.th}>{pageDict.periodColumn}</th>
                    <th className={tableClasses.th}>{pageDict.accountColumn}</th>
                    <th className={tableClasses.th}>{pageDict.projectColumn}</th>
                    <th className={tableClasses.th}>{pageDict.costCenterColumn}</th>
                    <th className={tableClasses.th}>{pageDict.currencyColumn}</th>
                    <th className={`${tableClasses.th} text-end`}>{pageDict.budgetMinor}</th>
                    <th className={`${tableClasses.th} text-end`}>{pageDict.actualMinor}</th>
                    <th className={`${tableClasses.th} text-end`}>{pageDict.varianceMinor}</th>
                    <th className={`${tableClasses.th} text-end`}>{pageDict.variancePercent}</th>
                    <th className={`${tableClasses.th} text-center`}>{pageDict.rowType}</th>
                    <th className={`${tableClasses.th} text-center`}>{pageDict.ledgerRows}</th>
                  </tr>
                </thead>
                <tbody>
                  {report.rows.map((row) => {
                    const rowKey = `${row.financial_period_id}_${row.account_id}_${row.project_id ?? ''}_${row.cost_center_id ?? ''}_${row.currency}`;
                    const formattedVariancePercent = formatBasisPoints(row.variance_percent_bps);

                    return (
                      <tr key={rowKey} className="hover:bg-[var(--background)]/50 transition-colors">
                        <td className={`${tableClasses.td} font-medium`}>
                          {pageDict.monthLabel} {row.period_month}
                        </td>
                        <td className={tableClasses.td}>
                          <div className="font-semibold text-[var(--text-primary)]">
                            {row.account_code} - {getLocalizedName(row.account_name, locale)}
                          </div>
                          <div className="text-xs text-[var(--text-secondary)]">
                            {accountTypeLabel(row.account_type)} - {accountNatureLabel(row.account_nature)}
                          </div>
                        </td>
                        <td className={tableClasses.td}>
                          {row.project_code ? (
                            <div>
                              <span className="font-medium text-[var(--text-primary)]">
                                {row.project_code}
                              </span>
                              <span className="text-xs text-[var(--text-secondary)] ms-1">
                                ({getLocalizedName(row.project_name, locale)})
                              </span>
                            </div>
                          ) : (
                            <span className="text-xs text-[var(--text-secondary)]">
                              {pageDict.none}
                            </span>
                          )}
                        </td>
                        <td className={tableClasses.td}>
                          {row.cost_center_code ? (
                            <div>
                              <span className="font-medium text-[var(--text-primary)]">
                                {row.cost_center_code}
                              </span>
                              <span className="text-xs text-[var(--text-secondary)] ms-1">
                                ({getLocalizedName(row.cost_center_name, locale)})
                              </span>
                            </div>
                          ) : (
                            <span className="text-xs text-[var(--text-secondary)]">
                              {pageDict.none}
                            </span>
                          )}
                        </td>
                        <td className={`${tableClasses.td} font-mono text-xs font-bold`}>
                          {row.currency}
                        </td>
                        <td className={`${tableClasses.td} text-end font-mono`}>
                          {formatMoney(row.budget_minor, row.currency)}
                        </td>
                        <td className={`${tableClasses.td} text-end font-mono font-semibold`}>
                          {formatMoney(row.actual_minor, row.currency)}
                        </td>
                        <td className={`${tableClasses.td} text-end font-mono ${getVarianceToneClass(row)}`}>
                          {formatMoney(row.variance_minor, row.currency)}
                        </td>
                        <td className={`${tableClasses.td} text-end font-mono text-xs font-semibold`}>
                          {formattedVariancePercent}
                        </td>
                        <td className={`${tableClasses.td} text-center`}>
                          {renderRowTypeBadge(row.row_type)}
                        </td>
                        <td className={`${tableClasses.td} text-center`}>
                          {row.ledger_row_count > 0 ? (
                            <Link
                              href={`/accounting/ledger?account_id=${row.account_id}&period_id=${row.financial_period_id}`}
                              className="inline-flex items-center gap-1 rounded px-2 py-0.5 text-xs font-semibold text-blue-600 hover:bg-blue-500/10 dark:text-blue-400 no-underline"
                              title={pageDict.openLedger}
                              aria-label={pageDict.openLedger}
                            >
                              {row.ledger_row_count}
                            </Link>
                          ) : (
                            <span className="text-xs text-[var(--text-secondary)]">0</span>
                          )}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      </div>
    </AppLayout>
  );
}
