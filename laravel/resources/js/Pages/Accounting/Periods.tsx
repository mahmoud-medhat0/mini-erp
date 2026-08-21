import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader } from '../../Components/Primitives';
import { formatDate } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { FiscalYearRow, PeriodRow, SharedPageProps } from '../../Types';

type PeriodsProps = SharedPageProps & {
  fiscalYears: FiscalYearRow[];
};

export default function Periods({ locale, fiscalYears = [] }: PeriodsProps) {
  const dict = getDictionary(locale);
  const accDict = (dict.app as any).accounting || {};
  const actionsDict = dict.app.actions || {};

  const [showAddYear, setShowAddYear] = useState(false);

  const maxYear = fiscalYears.length > 0 ? Math.max(...fiscalYears.map((fy) => fy.year)) : new Date().getFullYear();
  const nextYear = maxYear + 1;

  const yearForm = useForm({
    year: nextYear,
    start_date: `${nextYear}-01-01`,
    end_date: `${nextYear}-12-31`,
  });

  const actionForm = useForm({});

  const handleYearChange = (newYear: number) => {
    yearForm.setData({
      year: newYear,
      start_date: `${newYear}-01-01`,
      end_date: `${newYear}-12-31`,
    });
  };

  function submitYear(e: FormEvent) {
    e.preventDefault();
    yearForm.post('/accounting/periods/fiscal-years', {
      onSuccess: () => {
        setShowAddYear(false);
      },
    });
  }

  function closePeriod(periodId: string) {
    if (confirm(accDict.closePeriodConfirm || 'Are you sure you want to close this financial period? New postings will be blocked.')) {
      actionForm.post(`/accounting/periods/${periodId}/close`);
    }
  }

  function reopenPeriod(periodId: string) {
    if (confirm(accDict.reopenPeriodConfirm || 'Reopen this financial period to allow journal postings?')) {
      actionForm.post(`/accounting/periods/${periodId}/reopen`);
    }
  }

  const getStatusBadge = (status: string) => {
    const s = status.toLowerCase();
    if (s === 'open') {
      return (
        <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold font-mono bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
          <span className="size-1.5 rounded-full bg-emerald-500 animate-pulse" />
          <span>{accDict.statusOpen || 'OPEN'}</span>
        </span>
      );
    }
    if (s === 'reopened') {
      return (
        <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold font-mono bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">
          <span className="size-1.5 rounded-full bg-indigo-500" />
          <span>{accDict.statusReopened || 'REOPENED'}</span>
        </span>
      );
    }
    return (
      <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold font-mono bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20">
        <span className="size-1.5 rounded-full bg-red-500" />
        <span>{accDict.statusClosed || 'CLOSED'}</span>
      </span>
    );
  };

  return (
    <AppLayout active="accounting.periods">
      <Head title={accDict.fiscalStructure || 'Fiscal Structure & Periods'} />

      <PageHeader
        title={accDict.fiscalStructure || 'Fiscal Structure & Periods'}
        description={accDict.fiscalStructureDesc || 'Single-ERP Fiscal Years and monthly Financial Periods closing control.'}
        actions={
          <button
            type="button"
            onClick={() => setShowAddYear(!showAddYear)}
            className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-95 active:scale-95 transition-all cursor-pointer"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>{accDict.createFiscalYear || 'Create Fiscal Year'}</span>
          </button>
        }
      />

      {/* Add Fiscal Year Modal */}
      {showAddYear ? (
        <Card className="p-6 mb-6 border-2 border-[var(--primary)]/40 shadow-2xl bg-[var(--surface)]">
          <div className="flex items-center justify-between border-b border-[var(--border)] pb-3 mb-5">
            <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">
              {accDict.createFiscalYearTitle || 'Create Fiscal Year & 12 Monthly Periods'}
            </h3>
            <button
              type="button"
              onClick={() => setShowAddYear(false)}
              className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer shadow-sm"
            >
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span>{actionsDict.cancel || 'Cancel'}</span>
            </button>
          </div>

          <form onSubmit={submitYear} className="grid gap-4 sm:grid-cols-3">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.fiscalYear || 'Fiscal Year'}
              </label>
              <input
                type="number"
                value={yearForm.data.year}
                onChange={(e) => handleYearChange(parseInt(e.target.value) || 2028)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)] font-mono"
                required
              />
              {yearForm.errors.year ? <p className="text-xs text-red-500 font-bold mt-1.5">{yearForm.errors.year}</p> : null}
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.startDate || 'Start Date'}
              </label>
              <input
                type="date"
                value={yearForm.data.start_date}
                onChange={(e) => yearForm.setData('start_date', e.target.value)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)] font-mono"
                required
              />
              {yearForm.errors.start_date ? <p className="text-xs text-red-500 font-bold mt-1.5">{yearForm.errors.start_date}</p> : null}
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {accDict.endDate || 'End Date'}
              </label>
              <input
                type="date"
                value={yearForm.data.end_date}
                onChange={(e) => yearForm.setData('end_date', e.target.value)}
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2.5 text-xs text-[var(--text-primary)] font-mono"
                required
              />
              {yearForm.errors.end_date ? <p className="text-xs text-red-500 font-bold mt-1.5">{yearForm.errors.end_date}</p> : null}
            </div>
            <div className="sm:col-span-3 flex justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={() => setShowAddYear(false)}
                className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4.5 py-2.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-colors cursor-pointer"
              >
                {actionsDict.cancel || 'Cancel'}
              </button>
              <button
                type="submit"
                disabled={yearForm.processing}
                className="rounded-xl bg-[var(--primary)] px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-90 disabled:opacity-50 transition-all cursor-pointer"
              >
                {accDict.generate12Periods || 'Generate 12 Periods'}
              </button>
            </div>
          </form>
        </Card>
      ) : null}

      {/* Fiscal Years List */}
      <div className="space-y-6">
        {fiscalYears.map((fy) => {
          const closedCount = fy.periods?.filter((p) => p.status === 'closed').length || 0;
          const totalCount = fy.periods?.length || 0;
          const progressPercent = totalCount > 0 ? Math.round((closedCount / totalCount) * 100) : 0;

          return (
            <Card key={fy.id} className="p-6 border border-[var(--border)] shadow-lg hover:border-blue-500/20 transition-all">
              <div className="flex flex-wrap items-center justify-between border-b border-[var(--border)] pb-4 mb-5 gap-4">
                <div>
                  <div className="flex items-center gap-3">
                    <h3 className="m-0 text-lg font-black font-mono text-[var(--text-primary)]">
                      {accDict.fiscalYear || 'Fiscal Year'} {fy.year}
                    </h3>
                    {getStatusBadge(fy.status)}
                  </div>
                  <div className="mt-1 flex items-center gap-2 text-xs font-mono text-[var(--text-muted)]">
                    <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>
                      {formatDate(fy.start_date)} {accDict.to || 'to'} {formatDate(fy.end_date)}
                    </span>
                  </div>
                </div>

                {/* Closing Progress Bar */}
                <div className="flex items-center gap-4 bg-[var(--background)] p-3 rounded-2xl border border-[var(--border)]">
                  <div className="text-end">
                    <span className="block text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
                      {accDict.closedPeriods || 'Closed Periods'}
                    </span>
                    <span className="font-mono font-bold text-xs text-[var(--text-primary)]">
                      {closedCount} / {totalCount} ({progressPercent}%)
                    </span>
                  </div>
                  <div className="w-24 bg-[var(--surface)] h-2.5 rounded-full overflow-hidden border border-[var(--border)]">
                    <div
                      className="bg-gradient-to-r from-blue-500 to-emerald-500 h-full rounded-full transition-all duration-300"
                      style={{ width: `${progressPercent}%` }}
                    />
                  </div>
                </div>
              </div>

              {/* 12 Monthly Period Grid */}
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {fy.periods?.map((p) => {
                  const isClosed = p.status === 'closed';
                  return (
                    <div
                      key={p.id}
                      className={`p-4 rounded-2xl border flex flex-col justify-between space-y-3 transition-all ${
                        isClosed
                          ? 'border-red-500/30 bg-red-500/5 hover:border-red-500/40'
                          : 'border-[var(--border)] bg-[var(--background)]/60 hover:border-blue-500/30 hover:bg-[var(--background)]'
                      }`}
                    >
                      <div className="flex items-center justify-between">
                        <span className="font-extrabold text-xs text-[var(--text-primary)]">
                          {accDict.month || 'Month'} {p.month}
                        </span>
                        {getStatusBadge(p.status)}
                      </div>

                      <div className="flex items-center gap-1.5 font-mono text-[11px] text-[var(--text-muted)] bg-[var(--surface)] px-2.5 py-1.5 rounded-xl border border-[var(--border)]">
                        <svg className="size-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span className="truncate">
                          {formatDate(p.start_date)} - {formatDate(p.end_date)}
                        </span>
                      </div>

                      <div className="pt-2 border-t border-[var(--border)] flex justify-end">
                        {isClosed ? (
                          <button
                            type="button"
                            onClick={() => reopenPeriod(p.id)}
                            className="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 hover:underline cursor-pointer transition-colors"
                          >
                            <svg className="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                              <path strokeLinecap="round" strokeLinejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                            </svg>
                            <span>{accDict.reopenPeriod || 'Reopen Period'}</span>
                          </button>
                        ) : (
                          <button
                            type="button"
                            onClick={() => closePeriod(p.id)}
                            className="inline-flex items-center gap-1 text-[11px] font-bold text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 hover:underline cursor-pointer transition-colors"
                          >
                            <svg className="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                              <path strokeLinecap="round" strokeLinejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span>{accDict.closePeriod || 'Close Period'}</span>
                          </button>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            </Card>
          );
        })}
      </div>
    </AppLayout>
  );
}
