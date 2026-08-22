import { Head, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader, tableClasses } from '../../Components/Primitives';
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
  const absolute = Math.abs(minor);
  const major = Math.floor(absolute / 100);
  const cents = String(absolute % 100).padStart(2, '0');
  const formatted = `${major.toLocaleString('en-US')}.${cents}`;

  return minor < 0 ? `(${formatted})` : formatted;
}

export default function BalanceSheet({ locale, report, filters }: BalanceSheetProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const actionsDict = dict.app.actions;

  const can = useCan();
  const canExport = can('reports.export') && can('view_financials');

  const [asOfDate, setAsOfDate] = useState(filters.as_of_date || dateToday());

  function dateToday(): string {
    const d = new Date();
    return d.toISOString().split('T')[0];
  }

  function handleFilterSubmit(e: FormEvent) {
    e.preventDefault();
    router.get('/reports/balance-sheet', { as_of_date: asOfDate }, { preserveState: true });
  }

  function handleExportCsv() {
    window.location.href = `/reports/balance-sheet/export?as_of_date=${encodeURIComponent(asOfDate)}`;
  }

  const { sections, summary } = report;

  return (
    <AppLayout active="reports.balance_sheet">
      <Head title={accDict.balanceSheetMiniErp} />

      <div className="space-y-6 p-6">
        <PageHeader
          title={accDict.balanceSheet}
          description={accDict.balanceSheetDesc}
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
            <div>
              <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                {accDict.asOfDate}
              </label>
              <input
                type="date"
                value={asOfDate}
                onChange={(e) => setAsOfDate(e.target.value)}
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

        {!summary.is_balanced ? (
          <div className="rounded-2xl border border-red-500/40 bg-red-500/10 p-4 text-red-600 dark:text-red-400">
            <div className="flex items-center gap-3">
              <svg className="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <div>
                <h4 className="text-xs font-bold uppercase tracking-wider">
                  {accDict.balanceSheetUnbalancedTitle}
                </h4>
                <p className="text-xs mt-0.5">
                  {accDict.imbalanceWarningDesc}{' '}
                  {accDict.difference}: <span className="font-mono font-bold">{formatAmount(summary.imbalance_minor)}</span>
                </p>
              </div>
            </div>
          </div>
        ) : (
          <div className="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-emerald-600 dark:text-emerald-400 flex items-center gap-2">
            <svg className="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            <span className="text-xs font-semibold">
              {accDict.balanceSheetBalancedTitle}
            </span>
          </div>
        )}

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

        <div className="grid gap-6 md:grid-cols-2">
          <div className="space-y-4">
            <h3 className="text-sm font-extrabold uppercase tracking-wider text-[var(--primary)] border-b border-[var(--border)] pb-2">
              {accDict.assets}
            </h3>

            <Card className="overflow-hidden border border-[var(--border)]">
              <div className="bg-[var(--surface-subtle)] px-4 py-2.5 font-bold text-xs text-[var(--text-primary)] uppercase tracking-wider border-b border-[var(--border)] flex justify-between">
                <span>{accDict.currentAssets}</span>
                <span className="font-mono">{formatAmount(summary.total_current_assets_minor)}</span>
              </div>
              {sections.current_assets?.lines?.map((line) => (
                <div key={line.id} className="p-3 border-b border-[var(--border)] last:border-b-0 space-y-1.5">
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
            </Card>

            <Card className="overflow-hidden border border-[var(--border)]">
              <div className="bg-[var(--surface-subtle)] px-4 py-2.5 font-bold text-xs text-[var(--text-primary)] uppercase tracking-wider border-b border-[var(--border)] flex justify-between">
                <span>{accDict.nonCurrentAssets}</span>
                <span className="font-mono">{formatAmount(summary.total_non_current_assets_minor)}</span>
              </div>
              {sections.non_current_assets?.lines?.map((line) => (
                <div key={line.id} className="p-3 border-b border-[var(--border)] last:border-b-0 space-y-1.5">
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
            </Card>

            <Card className="p-4 bg-[var(--primary)] text-white font-bold flex justify-between items-center text-sm shadow-md">
              <span>{accDict.totalAssets}</span>
              <span className="font-mono text-base">{formatAmount(summary.total_assets_minor)}</span>
            </Card>
          </div>

          <div className="space-y-4">
            <h3 className="text-sm font-extrabold uppercase tracking-wider text-[var(--primary)] border-b border-[var(--border)] pb-2">
              {accDict.liabilitiesAndEquity}
            </h3>

            <Card className="overflow-hidden border border-[var(--border)]">
              <div className="bg-[var(--surface-subtle)] px-4 py-2.5 font-bold text-xs text-[var(--text-primary)] uppercase tracking-wider border-b border-[var(--border)] flex justify-between">
                <span>{accDict.currentLiabilities}</span>
                <span className="font-mono">{formatAmount(summary.total_current_liabilities_minor)}</span>
              </div>
              {sections.current_liabilities?.lines?.map((line) => (
                <div key={line.id} className="p-3 border-b border-[var(--border)] last:border-b-0 space-y-1.5">
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
            </Card>

            <Card className="overflow-hidden border border-[var(--border)]">
              <div className="bg-[var(--surface-subtle)] px-4 py-2.5 font-bold text-xs text-[var(--text-primary)] uppercase tracking-wider border-b border-[var(--border)] flex justify-between">
                <span>{accDict.nonCurrentLiabilities}</span>
                <span className="font-mono">{formatAmount(summary.total_non_current_liabilities_minor)}</span>
              </div>
              {sections.non_current_liabilities?.lines?.map((line) => (
                <div key={line.id} className="p-3 border-b border-[var(--border)] last:border-b-0 space-y-1.5">
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
            </Card>

            <Card className="overflow-hidden border border-[var(--border)]">
              <div className="bg-[var(--surface-subtle)] px-4 py-2.5 font-bold text-xs text-[var(--text-primary)] uppercase tracking-wider border-b border-[var(--border)] flex justify-between">
                <span>{accDict.equity}</span>
                <span className="font-mono">{formatAmount(summary.total_equity_including_net_income_minor)}</span>
              </div>
              {sections.equity?.lines?.map((line) => (
                <div key={line.id} className="p-3 border-b border-[var(--border)] space-y-1.5">
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
              <div className="p-3 bg-blue-500/5 dark:bg-blue-500/10 flex justify-between text-xs font-bold text-[var(--text-primary)]">
                <span>{accDict.currentPeriodNetIncome}</span>
                <span className="font-mono text-[var(--primary)]">{formatAmount(summary.current_period_net_income_minor)}</span>
              </div>
            </Card>

            <Card className="p-4 bg-[var(--primary)] text-white font-bold flex justify-between items-center text-sm shadow-md">
              <span>{accDict.totalLiabilitiesAndEquity}</span>
              <span className="font-mono text-base">{formatAmount(summary.total_liabilities_and_equity_minor)}</span>
            </Card>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
