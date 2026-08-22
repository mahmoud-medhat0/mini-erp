import { Head, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader, SearchableSelect } from '../../Components/Primitives';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type AccountLineItem = {
  id: string;
  code: string;
  name: string | { en?: string; ar?: string };
  debit_minor: number;
  credit_minor: number;
  net_minor: number;
};

type StatementLineData = {
  id: string;
  code: string;
  name: string | { en?: string; ar?: string };
  section_code: string;
  normal_balance: string;
  total_minor: number;
  accounts: AccountLineItem[];
};

type SectionData = {
  code: string;
  lines: StatementLineData[];
  total_minor: number;
};

type UnmappedAccountItem = {
  id: string;
  code: string;
  name: string | { en?: string; ar?: string };
  type: string;
  debit_minor: number;
  credit_minor: number;
  net_minor: number;
};

type PeriodItem = {
  id: string;
  year?: number | null;
  month: number;
  start_date: string;
  end_date: string;
  status: string;
};

type IncomeStatementReportData = {
  from_date: string;
  to_date: string;
  period_id: string | null;
  sections: Record<string, SectionData>;
  summary: {
    total_revenue_minor: number;
    total_contra_revenue_minor: number;
    net_revenue_minor: number;
    total_cogs_minor: number;
    gross_profit_minor: number;
    total_operating_expenses_minor: number;
    operating_income_minor: number;
    total_other_income_minor: number;
    total_other_expenses_minor: number;
    net_income_minor: number;
  };
  unmapped_accounts: UnmappedAccountItem[];
  has_unmapped_warning: boolean;
};

type IncomeStatementProps = SharedPageProps & {
  report: IncomeStatementReportData;
  periods: PeriodItem[];
  filters: {
    from_date: string;
    to_date: string;
    period_id: string | null;
  };
};

function formatAmount(minor: number): string {
  const absolute = Math.abs(minor);
  const major = Math.floor(absolute / 100);
  const cents = String(absolute % 100).padStart(2, '0');
  const formatted = `${major.toLocaleString('en-US')}.${cents}`;

  return minor < 0 ? `(${formatted})` : formatted;
}

export default function IncomeStatement({ locale, report, periods = [], filters }: IncomeStatementProps) {
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
      '/reports/income-statement',
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
    window.location.href = `/reports/income-statement/export?${params.toString()}`;
  }

  const { sections, summary } = report;

  return (
    <AppLayout active="reports.income_statement">
      <Head title={accDict.incomeStatementMiniErp} />

      <div className="space-y-6 p-6">
        <PageHeader
          title={accDict.incomeStatement}
          description={accDict.incomeStatementDesc}
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
            <div className="min-w-[200px]">
              <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                {accDict.financialPeriod}
              </label>
              <SearchableSelect
                options={[
                  { value: '', label: accDict.customDateRange },
                  ...periods.map((p) => ({
                    value: p.id,
                    label: `${p.year ?? ''} - ${accDict.month} ${p.month} (${p.start_date.split('T')[0]} - ${p.end_date.split('T')[0]})`,
                  })),
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

        {report.has_unmapped_warning ? (
          <div className="rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4 text-amber-700 dark:text-amber-300 space-y-3">
            <div className="flex items-center gap-3">
              <svg className="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div>
                <h4 className="text-xs font-bold uppercase tracking-wider">
                  {accDict.unmappedAccounts}
                </h4>
                <p className="text-xs mt-0.5">
                  {accDict.unmappedAccountsDesc}
                </p>
              </div>
            </div>
            <div className="flex flex-wrap gap-2 pt-1">
              {report.unmapped_accounts.map((acc) => (
                <span key={acc.id} className="inline-flex items-center gap-1.5 rounded-lg bg-[var(--surface)] px-2.5 py-1 text-[11px] font-medium border border-[var(--border)] text-[var(--text-primary)]">
                  <span className="font-mono font-bold text-amber-600 dark:text-amber-400">{acc.code}</span>
                  <span>{getLocalizedName(acc.name, locale)}</span>
                  <span className="font-mono text-xs font-semibold">({formatAmount(acc.net_minor)})</span>
                </span>
              ))}
            </div>
          </div>
        ) : null}

        <Card className="p-6 space-y-6 border border-[var(--border)] max-w-4xl mx-auto">
          <div className="text-center border-b border-[var(--border)] pb-4">
            <h2 className="text-lg font-bold text-[var(--text-primary)] uppercase tracking-wide">
              {accDict.incomeStatement}
            </h2>
            <p className="text-xs text-[var(--text-muted)] font-mono mt-1">
              {report.from_date} &mdash; {report.to_date}
            </p>
          </div>

          <div className="space-y-3">
            <h3 className="text-xs font-extrabold uppercase tracking-wider text-[var(--primary)]">
              {accDict.operatingRevenue}
            </h3>
            {sections.revenue?.lines?.map((line) => (
              <div key={line.id} className="space-y-1">
                <div className="flex justify-between text-xs font-bold text-[var(--text-primary)]">
                  <span>{getLocalizedName(line.name, locale)}</span>
                  <span className="font-mono">{formatAmount(line.total_minor)}</span>
                </div>
                {line.accounts?.map((acc) => (
                  <div key={acc.id} className="flex justify-between text-[11px] text-[var(--text-secondary)] ps-4">
                    <span><code className="font-mono text-[var(--text-muted)] me-2">{acc.code}</code>{getLocalizedName(acc.name, locale)}</span>
                    <span className="font-mono">{formatAmount(acc.net_minor)}</span>
                  </div>
                ))}
              </div>
            ))}
            <div className="flex justify-between text-xs font-bold text-[var(--text-primary)] pt-1 border-t border-[var(--border)]">
              <span>{accDict.grossRevenue}</span>
              <span className="font-mono">{formatAmount(summary.total_revenue_minor)}</span>
            </div>

            {sections.contra_revenue?.lines && sections.contra_revenue.lines.length > 0 ? (
              <div className="space-y-1 pt-2">
                <div className="text-[11px] font-semibold text-[var(--text-muted)] uppercase">
                  {accDict.contraRevenue}
                </div>
                {sections.contra_revenue.lines.map((line) => (
                  <div key={line.id} className="flex justify-between text-xs text-red-500 ps-4">
                    <span>{getLocalizedName(line.name, locale)}</span>
                    <span className="font-mono">({formatAmount(line.total_minor)})</span>
                  </div>
                ))}
              </div>
            ) : null}

            <div className="flex justify-between text-xs font-extrabold text-[var(--text-primary)] bg-[var(--surface-subtle)] p-2.5 rounded-xl border border-[var(--border)]">
              <span>{accDict.netRevenue}</span>
              <span className="font-mono text-sm">{formatAmount(summary.net_revenue_minor)}</span>
            </div>
          </div>

          <div className="space-y-3 pt-3 border-t border-[var(--border)]">
            <h3 className="text-xs font-extrabold uppercase tracking-wider text-[var(--primary)]">
              {accDict.costOfGoodsSold}
            </h3>
            {sections.cogs?.lines?.map((line) => (
              <div key={line.id} className="space-y-1">
                <div className="flex justify-between text-xs font-bold text-[var(--text-primary)]">
                  <span>{getLocalizedName(line.name, locale)}</span>
                  <span className="font-mono">{formatAmount(line.total_minor)}</span>
                </div>
                {line.accounts?.map((acc) => (
                  <div key={acc.id} className="flex justify-between text-[11px] text-[var(--text-secondary)] ps-4">
                    <span><code className="font-mono text-[var(--text-muted)] me-2">{acc.code}</code>{getLocalizedName(acc.name, locale)}</span>
                    <span className="font-mono">{formatAmount(acc.net_minor)}</span>
                  </div>
                ))}
              </div>
            ))}
            <div className="flex justify-between text-xs font-extrabold text-[var(--text-primary)] bg-blue-500/10 p-2.5 rounded-xl border border-blue-500/20">
              <span>{accDict.grossProfit}</span>
              <span className="font-mono text-sm">{formatAmount(summary.gross_profit_minor)}</span>
            </div>
          </div>

          <div className="space-y-3 pt-3 border-t border-[var(--border)]">
            <h3 className="text-xs font-extrabold uppercase tracking-wider text-[var(--primary)]">
              {accDict.operatingExpenses}
            </h3>
            {sections.operating_expenses?.lines?.map((line) => (
              <div key={line.id} className="space-y-1">
                <div className="flex justify-between text-xs font-bold text-[var(--text-primary)]">
                  <span>{getLocalizedName(line.name, locale)}</span>
                  <span className="font-mono">{formatAmount(line.total_minor)}</span>
                </div>
                {line.accounts?.map((acc) => (
                  <div key={acc.id} className="flex justify-between text-[11px] text-[var(--text-secondary)] ps-4">
                    <span><code className="font-mono text-[var(--text-muted)] me-2">{acc.code}</code>{getLocalizedName(acc.name, locale)}</span>
                    <span className="font-mono">{formatAmount(acc.net_minor)}</span>
                  </div>
                ))}
              </div>
            ))}
            <div className="flex justify-between text-xs font-extrabold text-[var(--text-primary)] bg-purple-500/10 p-2.5 rounded-xl border border-purple-500/20">
              <span>{accDict.operatingIncome}</span>
              <span className="font-mono text-sm">{formatAmount(summary.operating_income_minor)}</span>
            </div>
          </div>

          <div className="space-y-3 pt-3 border-t border-[var(--border)]">
            <h3 className="text-xs font-extrabold uppercase tracking-wider text-[var(--primary)]">
              {accDict.otherIncomeAndExpenses}
            </h3>
            {sections.other_income?.lines?.map((line) => (
              <div key={line.id} className="flex justify-between text-xs text-emerald-600 dark:text-emerald-400">
                <span>{getLocalizedName(line.name, locale)}</span>
                <span className="font-mono">{formatAmount(line.total_minor)}</span>
              </div>
            ))}
            {sections.other_expenses?.lines?.map((line) => (
              <div key={line.id} className="flex justify-between text-xs text-red-500">
                <span>{getLocalizedName(line.name, locale)}</span>
                <span className="font-mono">({formatAmount(line.total_minor)})</span>
              </div>
            ))}
          </div>

          <div className={`p-4 rounded-2xl border text-white font-extrabold flex justify-between items-center text-sm shadow-md ${
            summary.net_income_minor >= 0 ? 'bg-emerald-600 border-emerald-500' : 'bg-red-600 border-red-500'
          }`}>
            <span>
              {summary.net_income_minor >= 0 ? accDict.netIncome : accDict.netLoss}
            </span>
            <span className="font-mono text-lg">{formatAmount(summary.net_income_minor)}</span>
          </div>
        </Card>
      </div>
    </AppLayout>
  );
}
