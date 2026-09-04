import { Head, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, MetricCard, PageHeader, StatusBadge } from '../../Components/Primitives';
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

type BalanceSheetReportData = {
  as_of_date: string;
  sections: Record<string, SectionData>;
  summary: {
    total_current_assets_minor: number;
    total_non_current_assets_minor: number;
    total_assets_minor: number;
    total_current_liabilities_minor: number;
    total_non_current_liabilities_minor: number;
    total_liabilities_minor: number;
    total_equity_minor: number;
    current_period_net_income_minor: number;
    total_equity_including_net_income_minor: number;
    total_liabilities_and_equity_minor: number;
    is_balanced: boolean;
    imbalance_minor: number;
  };
  unmapped_accounts: UnmappedAccountItem[];
  has_unmapped_warning: boolean;
};

type BalanceSheetProps = SharedPageProps & {
  report: BalanceSheetReportData;
  filters: {
    as_of_date: string;
  };
};

function formatAmount(minor: number): string {
  const digits = String(Math.abs(minor)).padStart(3, '0');
  const major = digits.slice(0, -2) || '0';
  const cents = digits.slice(-2);
  const formatted = `${major.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${cents}`;

  return minor < 0 ? `(${formatted})` : formatted;
}

export default function BalanceSheet({ locale, report, filters }: BalanceSheetProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const actionsDict = dict.app.actions;

  const can = useCan();
  const canExport = can('reports.export') && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  const [asOfDate, setAsOfDate] = useState(filters.as_of_date || dateToday());

  function dateToday(): string {
    const d = new Date();
    return d.toISOString().split('T')[0];
  }

  function handleFilterSubmit(e: FormEvent) {
    e.preventDefault();
    router.get('/reports/balance-sheet', { as_of_date: asOfDate }, { preserveState: true, preserveScroll: true });
  }

  function handlePrint() {
    window.print();
  }

  const exportUrl = `/reports/balance-sheet/export?as_of_date=${encodeURIComponent(asOfDate)}`;
  const { sections, summary } = report;

  return (
    <AppLayout active="reports.balance_sheet">
      <Head title={accDict.balanceSheetMiniErp} />

      <PageHeader
        title={accDict.balanceSheet}
        description={accDict.balanceSheetDesc}
        actions={
          canPrint || canExport ? (
            <div className="flex items-center gap-2">
              {canPrint ? (
                <Button variant="secondary" onClick={handlePrint} className="gap-2">
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                  </svg>
                  {actionsDict.printReport}
                </Button>
              ) : null}
              {canExport ? (
                <a href={exportUrl}>
                  <Button variant="secondary" className="gap-2">
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    {actionsDict.exportCsv}
                  </Button>
                </a>
              ) : null}
            </div>
          ) : undefined
        }
      />

      <div className="space-y-6">
        {/* Filter Card */}
        <Card className="p-4 border border-[var(--border-color)]">
          <form onSubmit={handleFilterSubmit} className="flex flex-wrap items-end justify-between gap-4">
            <div className="flex items-end gap-3 flex-1 min-w-[280px]">
              <DatePicker label={accDict.asOfDate} value={asOfDate} onChange={(value) => setAsOfDate(value || '')} />
              <Button type="submit" className="h-[42px] px-5">
                {accDict.applyFilter}
              </Button>
            </div>
            <div className="text-xs font-semibold text-[var(--text-secondary)] bg-[var(--background)] px-3 py-2 rounded-lg border border-[var(--border-color)]">
              {accDict.asOfDate}: <span className="font-mono text-[var(--text-primary)] font-bold ms-1">{asOfDate}</span>
            </div>
          </form>
        </Card>

        {/* Audit Status Banner */}
        {!summary.is_balanced ? (
          <div className="rounded-xl border border-rose-500/40 bg-rose-500/10 p-4 text-rose-700 dark:text-rose-300 shadow-sm">
            <div className="flex items-center justify-between flex-wrap gap-3">
              <div className="flex items-center gap-3">
                <svg className="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                  <h4 className="text-xs font-bold uppercase tracking-wider">
                    {accDict.balanceSheetUnbalancedTitle}
                  </h4>
                  <p className="text-xs mt-0.5 leading-relaxed">
                    {accDict.imbalanceWarningDesc}{' '}
                    {accDict.difference}: <span className="font-mono font-bold">{formatAmount(summary.imbalance_minor)}</span>
                  </p>
                </div>
              </div>
              <StatusBadge tone="danger">{accDict.difference}: {formatAmount(summary.imbalance_minor)}</StatusBadge>
            </div>
          </div>
        ) : (
          <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3.5 text-emerald-700 dark:text-emerald-300 flex items-center justify-between flex-wrap gap-3 shadow-sm">
            <div className="flex items-center gap-2.5">
              <svg className="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span className="text-xs font-bold tracking-wide">
                {accDict.balanceSheetBalancedTitle}
              </span>
            </div>
            <StatusBadge tone="ok">Assets = Liabilities + Equity</StatusBadge>
          </div>
        )}

        {/* Top KPI Metrics Overview */}
        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
          <MetricCard
            label={accDict.totalAssets}
            value={formatAmount(summary.total_assets_minor)}
            tone="blue"
          />
          <MetricCard
            label={accDict.currentLiabilities}
            value={formatAmount(summary.total_liabilities_minor)}
            tone="amber"
          />
          <MetricCard
            label={accDict.equity}
            value={formatAmount(summary.total_equity_including_net_income_minor)}
            tone="purple"
          />
          <MetricCard
            label={accDict.currentPeriodNetIncome}
            value={formatAmount(summary.current_period_net_income_minor)}
            tone={summary.current_period_net_income_minor >= 0 ? 'emerald' : 'muted'}
          />
        </div>

        {/* Unmapped Accounts Warning */}
        {report.has_unmapped_warning ? (
          <div className="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-amber-800 dark:text-amber-300 space-y-3 shadow-sm">
            <div className="flex items-center justify-between flex-wrap gap-2">
              <div className="flex items-center gap-2.5">
                <svg className="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
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
              <span className="rounded-full bg-amber-500/20 text-amber-800 dark:text-amber-200 px-2.5 py-0.5 text-xs font-bold font-mono">
                {report.unmapped_accounts.length} unmapped
              </span>
            </div>
            <div className="flex flex-wrap gap-2 pt-1">
              {report.unmapped_accounts.map((acc) => (
                <span key={acc.id} className="inline-flex items-center gap-1.5 rounded-lg bg-[var(--card)] px-3 py-1.5 text-xs font-medium border border-amber-500/30 text-[var(--text-primary)] shadow-sm">
                  <span className="font-mono font-bold text-amber-600 dark:text-amber-400">{acc.code}</span>
                  <span>{getLocalizedName(acc.name, locale)}</span>
                  <span className="font-mono text-xs font-bold tabular-nums">({formatAmount(acc.net_minor)})</span>
                </span>
              ))}
            </div>
          </div>
        ) : null}

        {/* Balance Sheet Sections: Assets vs Liabilities & Equity */}
        <div className="grid gap-6 md:grid-cols-2">
          {/* LEFT COLUMN: ASSETS */}
          <div className="space-y-4">
            <div className="flex items-center justify-between border-b-2 border-blue-500 pb-2">
              <h3 className="text-sm font-extrabold uppercase tracking-wider text-blue-600 dark:text-blue-400 flex items-center gap-2">
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {accDict.assets}
              </h3>
              <span className="font-mono text-xs font-bold text-[var(--text-secondary)]">
                {formatAmount(summary.total_assets_minor)}
              </span>
            </div>

            {/* Current Assets */}
            <Card className="overflow-hidden border border-[var(--border-color)] shadow-sm p-0">
              <div className="bg-[var(--background)] px-4 py-3 font-bold text-xs text-[var(--text-primary)] uppercase tracking-wider border-b border-[var(--border-color)] flex justify-between items-center">
                <span>{accDict.currentAssets}</span>
                <span className="font-mono text-sm tabular-nums font-extrabold">{formatAmount(summary.total_current_assets_minor)}</span>
              </div>
              {sections.current_assets?.lines?.map((line) => (
                <div key={line.id} className="p-3.5 border-b border-[var(--border-color)] last:border-b-0 space-y-2 hover:bg-[var(--background)]/40 transition-colors">
                  <div className="flex justify-between text-xs font-bold text-[var(--text-primary)]">
                    <span>{getLocalizedName(line.name, locale)}</span>
                    <span className="font-mono tabular-nums">{formatAmount(line.total_minor)}</span>
                  </div>
                  {line.accounts?.map((acc) => (
                    <div key={acc.id} className="flex justify-between text-[11px] text-[var(--text-secondary)] ps-4 pe-1">
                      <span className="flex items-center gap-2">
                        <code className="font-mono text-[10px] font-bold text-[var(--primary)] bg-[var(--background)] px-1.5 py-0.5 rounded border border-[var(--border-color)]">{acc.code}</code>
                        <span>{getLocalizedName(acc.name, locale)}</span>
                      </span>
                      <span className="font-mono tabular-nums">{formatAmount(acc.net_minor)}</span>
                    </div>
                  ))}
                </div>
              ))}
            </Card>

            {/* Non-Current Assets */}
            <Card className="overflow-hidden border border-[var(--border-color)] shadow-sm p-0">
              <div className="bg-[var(--background)] px-4 py-3 font-bold text-xs text-[var(--text-primary)] uppercase tracking-wider border-b border-[var(--border-color)] flex justify-between items-center">
                <span>{accDict.nonCurrentAssets}</span>
                <span className="font-mono text-sm tabular-nums font-extrabold">{formatAmount(summary.total_non_current_assets_minor)}</span>
              </div>
              {sections.non_current_assets?.lines?.map((line) => (
                <div key={line.id} className="p-3.5 border-b border-[var(--border-color)] last:border-b-0 space-y-2 hover:bg-[var(--background)]/40 transition-colors">
                  <div className="flex justify-between text-xs font-bold text-[var(--text-primary)]">
                    <span>{getLocalizedName(line.name, locale)}</span>
                    <span className="font-mono tabular-nums">{formatAmount(line.total_minor)}</span>
                  </div>
                  {line.accounts?.map((acc) => (
                    <div key={acc.id} className="flex justify-between text-[11px] text-[var(--text-secondary)] ps-4 pe-1">
                      <span className="flex items-center gap-2">
                        <code className="font-mono text-[10px] font-bold text-[var(--primary)] bg-[var(--background)] px-1.5 py-0.5 rounded border border-[var(--border-color)]">{acc.code}</code>
                        <span>{getLocalizedName(acc.name, locale)}</span>
                      </span>
                      <span className="font-mono tabular-nums">{formatAmount(acc.net_minor)}</span>
                    </div>
                  ))}
                </div>
              ))}
            </Card>

            {/* Total Assets Summary Card */}
            <div className="p-4 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-bold flex justify-between items-center text-sm shadow-md">
              <span className="uppercase tracking-wider text-xs">{accDict.totalAssets}</span>
              <span className="font-mono text-lg tabular-nums">{formatAmount(summary.total_assets_minor)}</span>
            </div>
          </div>

          {/* RIGHT COLUMN: LIABILITIES & EQUITY */}
          <div className="space-y-4">
            <div className="flex items-center justify-between border-b-2 border-amber-500 pb-2">
              <h3 className="text-sm font-extrabold uppercase tracking-wider text-amber-600 dark:text-amber-400 flex items-center gap-2">
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                </svg>
                {accDict.liabilitiesAndEquity}
              </h3>
              <span className="font-mono text-xs font-bold text-[var(--text-secondary)]">
                {formatAmount(summary.total_liabilities_and_equity_minor)}
              </span>
            </div>

            {/* Current Liabilities */}
            <Card className="overflow-hidden border border-[var(--border-color)] shadow-sm p-0">
              <div className="bg-[var(--background)] px-4 py-3 font-bold text-xs text-[var(--text-primary)] uppercase tracking-wider border-b border-[var(--border-color)] flex justify-between items-center">
                <span>{accDict.currentLiabilities}</span>
                <span className="font-mono text-sm tabular-nums font-extrabold">{formatAmount(summary.total_current_liabilities_minor)}</span>
              </div>
              {sections.current_liabilities?.lines?.map((line) => (
                <div key={line.id} className="p-3.5 border-b border-[var(--border-color)] last:border-b-0 space-y-2 hover:bg-[var(--background)]/40 transition-colors">
                  <div className="flex justify-between text-xs font-bold text-[var(--text-primary)]">
                    <span>{getLocalizedName(line.name, locale)}</span>
                    <span className="font-mono tabular-nums">{formatAmount(line.total_minor)}</span>
                  </div>
                  {line.accounts?.map((acc) => (
                    <div key={acc.id} className="flex justify-between text-[11px] text-[var(--text-secondary)] ps-4 pe-1">
                      <span className="flex items-center gap-2">
                        <code className="font-mono text-[10px] font-bold text-amber-600 dark:text-amber-400 bg-[var(--background)] px-1.5 py-0.5 rounded border border-[var(--border-color)]">{acc.code}</code>
                        <span>{getLocalizedName(acc.name, locale)}</span>
                      </span>
                      <span className="font-mono tabular-nums">{formatAmount(acc.net_minor)}</span>
                    </div>
                  ))}
                </div>
              ))}
            </Card>

            {/* Non-Current Liabilities */}
            <Card className="overflow-hidden border border-[var(--border-color)] shadow-sm p-0">
              <div className="bg-[var(--background)] px-4 py-3 font-bold text-xs text-[var(--text-primary)] uppercase tracking-wider border-b border-[var(--border-color)] flex justify-between items-center">
                <span>{accDict.nonCurrentLiabilities}</span>
                <span className="font-mono text-sm tabular-nums font-extrabold">{formatAmount(summary.total_non_current_liabilities_minor)}</span>
              </div>
              {sections.non_current_liabilities?.lines?.map((line) => (
                <div key={line.id} className="p-3.5 border-b border-[var(--border-color)] last:border-b-0 space-y-2 hover:bg-[var(--background)]/40 transition-colors">
                  <div className="flex justify-between text-xs font-bold text-[var(--text-primary)]">
                    <span>{getLocalizedName(line.name, locale)}</span>
                    <span className="font-mono tabular-nums">{formatAmount(line.total_minor)}</span>
                  </div>
                  {line.accounts?.map((acc) => (
                    <div key={acc.id} className="flex justify-between text-[11px] text-[var(--text-secondary)] ps-4 pe-1">
                      <span className="flex items-center gap-2">
                        <code className="font-mono text-[10px] font-bold text-amber-600 dark:text-amber-400 bg-[var(--background)] px-1.5 py-0.5 rounded border border-[var(--border-color)]">{acc.code}</code>
                        <span>{getLocalizedName(acc.name, locale)}</span>
                      </span>
                      <span className="font-mono tabular-nums">{formatAmount(acc.net_minor)}</span>
                    </div>
                  ))}
                </div>
              ))}
            </Card>

            {/* Equity */}
            <Card className="overflow-hidden border border-[var(--border-color)] shadow-sm p-0">
              <div className="bg-[var(--background)] px-4 py-3 font-bold text-xs text-[var(--text-primary)] uppercase tracking-wider border-b border-[var(--border-color)] flex justify-between items-center">
                <span>{accDict.equity}</span>
                <span className="font-mono text-sm tabular-nums font-extrabold">{formatAmount(summary.total_equity_including_net_income_minor)}</span>
              </div>
              {sections.equity?.lines?.map((line) => (
                <div key={line.id} className="p-3.5 border-b border-[var(--border-color)] space-y-2 hover:bg-[var(--background)]/40 transition-colors">
                  <div className="flex justify-between text-xs font-bold text-[var(--text-primary)]">
                    <span>{getLocalizedName(line.name, locale)}</span>
                    <span className="font-mono tabular-nums">{formatAmount(line.total_minor)}</span>
                  </div>
                  {line.accounts?.map((acc) => (
                    <div key={acc.id} className="flex justify-between text-[11px] text-[var(--text-secondary)] ps-4 pe-1">
                      <span className="flex items-center gap-2">
                        <code className="font-mono text-[10px] font-bold text-purple-600 dark:text-purple-400 bg-[var(--background)] px-1.5 py-0.5 rounded border border-[var(--border-color)]">{acc.code}</code>
                        <span>{getLocalizedName(acc.name, locale)}</span>
                      </span>
                      <span className="font-mono tabular-nums">{formatAmount(acc.net_minor)}</span>
                    </div>
                  ))}
                </div>
              ))}
              <div className="p-3.5 bg-emerald-500/10 border-t border-emerald-500/20 flex justify-between text-xs font-bold text-emerald-800 dark:text-emerald-300">
                <span>{accDict.currentPeriodNetIncome}</span>
                <span className="font-mono tabular-nums text-sm font-extrabold">{formatAmount(summary.current_period_net_income_minor)}</span>
              </div>
            </Card>

            {/* Total Liabilities & Equity Summary Card */}
            <div className="p-4 rounded-xl bg-gradient-to-r from-amber-600 to-orange-600 text-white font-bold flex justify-between items-center text-sm shadow-md">
              <span className="uppercase tracking-wider text-xs">{accDict.totalLiabilitiesAndEquity}</span>
              <span className="font-mono text-lg tabular-nums">{formatAmount(summary.total_liabilities_and_equity_minor)}</span>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}

