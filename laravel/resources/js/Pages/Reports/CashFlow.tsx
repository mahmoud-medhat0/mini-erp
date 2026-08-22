import { Head, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader, SearchableSelect } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type ActivitySummary = {
  inflows_minor: number;
  outflows_minor: number;
  net_minor: number;
};

type UnclassifiedWarningItem = {
  journal_id: string;
  entry_number: string;
  entry_date: string;
  cash_net_minor: number;
  reason_code: string;
};

type ConfigWarningItem = {
  type: string;
  account_code: string;
};

type CashFlowReportData = {
  from_date: string;
  to_date: string;
  period_id: string | null;
  opening_cash_minor: number;
  closing_cash_minor: number;
  period_cash_delta_minor: number;
  operating: ActivitySummary;
  investing: ActivitySummary;
  financing: ActivitySummary;
  unclassified: ActivitySummary;
  net_cash_change_minor: number;
  reconciled_closing_cash_minor: number;
  is_reconciled: boolean;
  config_warnings: ConfigWarningItem[];
  unclassified_warnings: UnclassifiedWarningItem[];
  has_config_warning: boolean;
  has_unclassified_warning: boolean;
};

type PeriodItem = {
  id: string;
  year?: number | null;
  month: number;
  start_date: string;
  end_date: string;
  status: string;
  fiscalYear?: { year: number } | null;
};

type CashFlowProps = SharedPageProps & {
  report: CashFlowReportData;
  periods: PeriodItem[];
  filters: {
    from_date: string;
    to_date: string;
    period_id: string | null;
  };
};

function formatAmount(minor: number): string {
  const isNegative = minor < 0;
  const digits = String(Math.abs(minor)).padStart(3, '0');
  const major = digits.slice(0, -2) || '0';
  const cents = digits.slice(-2);
  const formattedMajor = major.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  const formatted = `${formattedMajor}.${cents}`;
  return isNegative ? `(${formatted})` : formatted;
}

export default function CashFlow({ locale, report, periods = [], filters }: CashFlowProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const actionsDict = dict.app.actions;

  const can = useCan();
  const canExport = can('reports.export') && can('view_financials');

  const [fromDate, setFromDate] = useState(filters.from_date || '');
  const [toDate, setToDate] = useState(filters.to_date || '');
  const [selectedPeriodId, setSelectedPeriodId] = useState(filters.period_id || '');

  function handleFilterSubmit(e: FormEvent) {
    e.preventDefault();
    router.get(
      '/reports/cash-flow',
      {
        from_date: fromDate,
        to_date: toDate,
        period_id: selectedPeriodId || undefined,
      },
      { preserveState: true }
    );
  }

  function handlePeriodChange(periodId: string) {
    setSelectedPeriodId(periodId);
    if (!periodId) return;
    const period = periods.find((p) => p.id === periodId);
    if (period) {
      setFromDate(period.start_date.split('T')[0]);
      setToDate(period.end_date.split('T')[0]);
    }
  }

  function handleExportCsv() {
    const params = new URLSearchParams();
    if (fromDate) params.append('from_date', fromDate);
    if (toDate) params.append('to_date', toDate);
    if (selectedPeriodId) params.append('period_id', selectedPeriodId);
    window.location.href = `/reports/cash-flow/export?${params.toString()}`;
  }

  function configWarningLabel(warning: ConfigWarningItem): string {
    if (warning.type === 'cash_account_missing_gl') {
      return accDict.cashAccountMissingGl.replace(':code', warning.account_code);
    }

    if (warning.type === 'bank_account_missing_gl') {
      return accDict.bankAccountMissingGl.replace(':code', warning.account_code);
    }

    return accDict.cashFlowConfigurationIssue.replace(':code', warning.account_code);
  }

  function unclassifiedReasonLabel(reasonCode: string): string {
    if (reasonCode === 'unclassified_non_cash_accounts') {
      return accDict.unclassifiedReasonMissing;
    }

    if (reasonCode === 'mixed_cash_flow_activities') {
      return accDict.unclassifiedReasonMixed;
    }

    return accDict.unclassifiedReasonGeneric;
  }

  const { operating, investing, financing, unclassified } = report;

  return (
    <AppLayout active="reports.cash_flow">
      <Head title={accDict.cashFlowStatementMiniErp} />

      <div className="space-y-6 p-6">
        <PageHeader
          title={accDict.cashFlowStatement}
          description={accDict.cashFlowStatementDesc}
          actions={
            canExport ? (
              <button
                type="button"
                onClick={handleExportCsv}
                className="inline-flex items-center gap-2 rounded-xl bg-[var(--surface-subtle)] border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all"
              >
                <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                {actionsDict.exportCsv}
              </button>
            ) : null
          }
        />

        <Card className="p-4 bg-[var(--surface)] border border-[var(--border)]">
          <form onSubmit={handleFilterSubmit} className="flex flex-wrap items-end gap-4">
            <div className="min-w-[220px]">
              <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                {accDict.financialPeriod}
              </label>
              <SearchableSelect
                options={[
                  { value: '', label: accDict.customDateRange },
                  ...periods.map((p) => {
                    return {
                      value: p.id,
                      label: `${p.year ?? ''} - ${accDict.month} ${p.month} (${p.start_date.split('T')[0]} - ${p.end_date.split('T')[0]})`,
                    };
                  }),
                ]}
                value={selectedPeriodId}
                onChange={(val) => handlePeriodChange(val || '')}
                placeholder={accDict.selectPeriod}
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                {accDict.fromDate}
              </label>
              <input
                type="date"
                value={fromDate}
                onChange={(e) => {
                  setFromDate(e.target.value);
                  setSelectedPeriodId('');
                }}
                className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)] font-mono"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                {accDict.toDate}
              </label>
              <input
                type="date"
                value={toDate}
                onChange={(e) => {
                  setToDate(e.target.value);
                  setSelectedPeriodId('');
                }}
                className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)] font-mono"
              />
            </div>

            <button
              type="submit"
              className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-semibold text-white shadow-md shadow-blue-500/20 hover:bg-blue-600"
            >
              {accDict.applyFilter}
            </button>
          </form>
        </Card>

        {report.config_warnings.length > 0 ? (
          <div className="rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4 text-amber-700 dark:text-amber-300 space-y-2">
            <div className="flex items-center gap-2">
              <svg className="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <h4 className="text-xs font-bold uppercase tracking-wider">
                {accDict.cashAccountConfigWarning}
              </h4>
            </div>
            <ul className="list-disc list-inside text-xs space-y-1">
              {report.config_warnings.map((warning, idx) => (
                <li key={idx}>{configWarningLabel(warning)}</li>
              ))}
            </ul>
          </div>
        ) : null}

        {report.unclassified_warnings.length > 0 ? (
          <div className="rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4 text-amber-700 dark:text-amber-300 space-y-3">
            <div className="flex items-center gap-3">
              <svg className="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div>
                <h4 className="text-xs font-bold uppercase tracking-wider">
                  {accDict.unclassifiedCashMovements}
                </h4>
                <p className="text-xs mt-0.5">
                  {accDict.unclassifiedCashDesc}
                </p>
              </div>
            </div>
            <div className="space-y-1.5 pt-1">
              {report.unclassified_warnings.map((w, idx) => (
                <div key={idx} className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-[var(--surface)] p-2.5 text-xs border border-[var(--border)] text-[var(--text-primary)]">
                  <div className="flex items-center gap-2">
                    <span className="font-mono font-bold text-amber-600 dark:text-amber-400">{w.entry_number}</span>
                    <span className="text-[var(--text-muted)]">({w.entry_date})</span>
                    <span>- {unclassifiedReasonLabel(w.reason_code)}</span>
                  </div>
                  <span className="font-mono font-semibold">{formatAmount(w.cash_net_minor)}</span>
                </div>
              ))}
            </div>
          </div>
        ) : null}

        <Card className="p-6 space-y-6 border border-[var(--border)] max-w-4xl mx-auto">
          <div className="text-center border-b border-[var(--border)] pb-4">
            <h2 className="text-lg font-bold text-[var(--text-primary)] uppercase tracking-wide">
              {accDict.cashFlowStatement}
            </h2>
            <p className="text-xs text-[var(--text-muted)] font-mono mt-1">
              {report.from_date} - {report.to_date}
            </p>
          </div>

          <div className="flex justify-between items-center bg-[var(--surface-subtle)] p-3 rounded-xl border border-[var(--border)] text-xs font-bold text-[var(--text-primary)]">
            <span>{accDict.openingCashBalance}</span>
            <span className="font-mono text-sm">{formatAmount(report.opening_cash_minor)}</span>
          </div>

          <div className="space-y-2 pt-2">
            <h3 className="text-xs font-extrabold uppercase tracking-wider text-[var(--primary)]">
              {accDict.operatingActivities}
            </h3>
            <div className="flex justify-between text-xs text-[var(--text-secondary)] ps-4">
              <span>{accDict.cashInflows}</span>
              <span className="font-mono text-emerald-600 dark:text-emerald-400">{formatAmount(operating.inflows_minor)}</span>
            </div>
            <div className="flex justify-between text-xs text-[var(--text-secondary)] ps-4">
              <span>{accDict.cashOutflows}</span>
              <span className="font-mono text-red-500">({formatAmount(operating.outflows_minor)})</span>
            </div>
            <div className="flex justify-between text-xs font-bold text-[var(--text-primary)] pt-1 border-t border-[var(--border)] ps-2">
              <span>{accDict.netOperatingCash}</span>
              <span className="font-mono text-sm">{formatAmount(operating.net_minor)}</span>
            </div>
          </div>

          <div className="space-y-2 pt-3 border-t border-[var(--border)]">
            <h3 className="text-xs font-extrabold uppercase tracking-wider text-[var(--primary)]">
              {accDict.investingActivities}
            </h3>
            <div className="flex justify-between text-xs text-[var(--text-secondary)] ps-4">
              <span>{accDict.cashInflows}</span>
              <span className="font-mono text-emerald-600 dark:text-emerald-400">{formatAmount(investing.inflows_minor)}</span>
            </div>
            <div className="flex justify-between text-xs text-[var(--text-secondary)] ps-4">
              <span>{accDict.cashOutflows}</span>
              <span className="font-mono text-red-500">({formatAmount(investing.outflows_minor)})</span>
            </div>
            <div className="flex justify-between text-xs font-bold text-[var(--text-primary)] pt-1 border-t border-[var(--border)] ps-2">
              <span>{accDict.netInvestingCash}</span>
              <span className="font-mono text-sm">{formatAmount(investing.net_minor)}</span>
            </div>
          </div>

          <div className="space-y-2 pt-3 border-t border-[var(--border)]">
            <h3 className="text-xs font-extrabold uppercase tracking-wider text-[var(--primary)]">
              {accDict.financingActivities}
            </h3>
            <div className="flex justify-between text-xs text-[var(--text-secondary)] ps-4">
              <span>{accDict.cashInflows}</span>
              <span className="font-mono text-emerald-600 dark:text-emerald-400">{formatAmount(financing.inflows_minor)}</span>
            </div>
            <div className="flex justify-between text-xs text-[var(--text-secondary)] ps-4">
              <span>{accDict.cashOutflows}</span>
              <span className="font-mono text-red-500">({formatAmount(financing.outflows_minor)})</span>
            </div>
            <div className="flex justify-between text-xs font-bold text-[var(--text-primary)] pt-1 border-t border-[var(--border)] ps-2">
              <span>{accDict.netFinancingCash}</span>
              <span className="font-mono text-sm">{formatAmount(financing.net_minor)}</span>
            </div>
          </div>

          {unclassified.net_minor !== 0 || unclassified.inflows_minor > 0 || unclassified.outflows_minor > 0 ? (
            <div className="space-y-2 pt-3 border-t border-amber-500/30 bg-amber-500/5 p-3 rounded-xl">
              <h3 className="text-xs font-extrabold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                {accDict.unclassifiedActivities}
              </h3>
              <div className="flex justify-between text-xs text-[var(--text-secondary)] ps-4">
                <span>{accDict.cashInflows}</span>
                <span className="font-mono">{formatAmount(unclassified.inflows_minor)}</span>
              </div>
              <div className="flex justify-between text-xs text-[var(--text-secondary)] ps-4">
                <span>{accDict.cashOutflows}</span>
                <span className="font-mono">({formatAmount(unclassified.outflows_minor)})</span>
              </div>
              <div className="flex justify-between text-xs font-bold text-[var(--text-primary)] pt-1 border-t border-[var(--border)] ps-2">
                <span>{accDict.netUnclassifiedCash}</span>
                <span className="font-mono text-sm">{formatAmount(unclassified.net_minor)}</span>
              </div>
            </div>
          ) : null}

          <div className="flex justify-between items-center bg-blue-500/10 p-3 rounded-xl border border-blue-500/20 text-xs font-extrabold text-[var(--text-primary)]">
            <span>{accDict.netCashChange}</span>
            <span className="font-mono text-base text-[var(--primary)]">{formatAmount(report.net_cash_change_minor)}</span>
          </div>

          <div className={`p-4 rounded-2xl border flex justify-between items-center text-sm font-extrabold text-white shadow-md ${
            report.is_reconciled ? 'bg-emerald-600 border-emerald-500' : 'bg-red-600 border-red-500'
          }`}>
            <div className="flex items-center gap-2">
              <svg className="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                {report.is_reconciled ? (
                  <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                ) : (
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                )}
              </svg>
              <span>{accDict.closingCashBalance}</span>
            </div>
            <span className="font-mono text-lg">{formatAmount(report.closing_cash_minor)}</span>
          </div>
        </Card>
      </div>
    </AppLayout>
  );
}
