import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, EmptyState, PageHeader } from '../../Components/Primitives';
import { formatDate } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { FiscalYearRow, PeriodRow, SharedPageProps } from '../../Types';

type PeriodsProps = SharedPageProps & {
  fiscalYears: FiscalYearRow[];
};

type BlockerItem = {
  entity_type: string;
  id: string;
  number_or_reference: string;
  status: string;
  date: string;
  reason_code: string;
};

type ReadinessPayload = {
  can_close: boolean;
  blockers: BlockerItem[];
};

export default function Periods({ locale, fiscalYears = [] }: PeriodsProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const actionsDict = dict.app.actions;
  type AccountingKey = keyof typeof accDict;
  type ActionKey = keyof typeof actionsDict;
  const tx = (key: AccountingKey): string => accDict[key] as string;
  const ax = (key: ActionKey): string => actionsDict[key] as string;
  const txDynamic = (key: string, fallback: AccountingKey): string => {
    const value = accDict[key as AccountingKey];

    return typeof value === 'string' ? value : tx(fallback);
  };

  const can = useCan();
  const canCreateFiscalYear = can('settings.configure');
  const canClose = can('close_period');
  const canReopen = can('reopen_period');

  const [showAddYear, setShowAddYear] = useState(false);
  const [activeModalPeriod, setActiveModalPeriod] = useState<PeriodRow | null>(null);
  const [modalMode, setModalMode] = useState<'close' | 'reopen' | null>(null);
  const [readiness, setReadiness] = useState<ReadinessPayload | null>(null);
  const [closeNote, setCloseNote] = useState('');
  const [loadingReadiness, setLoadingReadiness] = useState(false);

  const maxYear = fiscalYears.length > 0 ? Math.max(...fiscalYears.map((fy) => fy.year)) : new Date().getFullYear();
  const nextYear = maxYear + 1;

  const yearForm = useForm({
    year: nextYear,
    start_date: `${nextYear}-01-01`,
    end_date: `${nextYear}-12-31`,
  });

  const actionForm = useForm({
    close_note: '',
  });

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

  async function openCloseModal(p: PeriodRow) {
    setActiveModalPeriod(p);
    setModalMode('close');
    setCloseNote('');
    setLoadingReadiness(true);
    setReadiness(null);

    try {
      const res = await fetch(`/accounting/periods/${p.id}/close-readiness`);
      if (res.ok) {
        const data: ReadinessPayload = await res.json();
        setReadiness(data);
      }
    } catch {
      // fallback if network fails
    } finally {
      setLoadingReadiness(false);
    }
  }

  function openReopenModal(p: PeriodRow) {
    setActiveModalPeriod(p);
    setModalMode('reopen');
    setCloseNote('');
    setReadiness(null);
  }

  function submitCloseOrReopen(e: FormEvent) {
    e.preventDefault();
    if (!activeModalPeriod || !modalMode) return;

    actionForm.setData('close_note', closeNote);
    const endpoint = `/accounting/periods/${activeModalPeriod.id}/${modalMode}`;

    actionForm.post(endpoint, {
      onSuccess: () => {
        setActiveModalPeriod(null);
        setModalMode(null);
        setCloseNote('');
      },
    });
  }

  const getStatusBadge = (status: string) => {
    const s = status.toLowerCase();
    if (s === 'open') {
      return (
        <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold font-mono bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
          <span className="size-1.5 rounded-full bg-emerald-500 animate-pulse" />
          <span>{tx('statusOpen')}</span>
        </span>
      );
    }
    if (s === 'reopened') {
      return (
        <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold font-mono bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">
          <span className="size-1.5 rounded-full bg-indigo-500" />
          <span>{tx('statusReopened')}</span>
        </span>
      );
    }
    return (
      <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold font-mono bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20">
        <span className="size-1.5 rounded-full bg-red-500" />
        <span>{tx('statusClosed')}</span>
      </span>
    );
  };

  const getEntityLabel = (entityType: string) => {
    const key = `entity_${entityType}`;
    return txDynamic(key, 'entity_unknown');
  };

  const getBlockerStatusLabel = (status: string) => {
    const keyByStatus: Record<string, AccountingKey> = {
      draft: 'statusDraft',
      submitted: 'statusSubmitted',
      approved: 'statusApproved',
      posted: 'statusPosted',
      cancelled: 'statusCancelled',
      received: 'statusReceived',
      deposited: 'statusDeposited',
      issued: 'statusIssued',
      in_progress: 'statusInProgress',
    };

    return txDynamic(keyByStatus[status] ?? 'statusUnknown', 'statusUnknown');
  };

  return (
    <AppLayout active="accounting.periods">
      <Head title={tx('fiscalStructure')} />

      <PageHeader
        title={tx('fiscalStructure')}
        description={tx('fiscalStructureDesc')}
        actions={canCreateFiscalYear ? (
          <button
            type="button"
            onClick={() => setShowAddYear(!showAddYear)}
            className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-95 active:scale-95 transition-all cursor-pointer"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>{tx('createFiscalYear')}</span>
          </button>
        ) : null}
      />

      {showAddYear ? (
        <Card className="p-6 mb-6 border-2 border-[var(--primary)]/40 shadow-2xl bg-[var(--surface)]">
          <div className="flex items-center justify-between border-b border-[var(--border)] pb-3 mb-5">
            <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">
              {tx('createFiscalYearTitle')}
            </h3>
            <button
              type="button"
              onClick={() => setShowAddYear(false)}
              className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer shadow-sm"
            >
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span>{ax('cancel')}</span>
            </button>
          </div>

          <form onSubmit={submitYear} className="grid gap-4 sm:grid-cols-3">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {tx('fiscalYear')}
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
              <DatePicker
                label={tx('startDate')}
                value={yearForm.data.start_date}
                onChange={(val) => yearForm.setData('start_date', val || '')}
                error={yearForm.errors.start_date}
                required
              />
            </div>
            <div>
              <DatePicker
                label={tx('endDate')}
                value={yearForm.data.end_date}
                onChange={(val) => yearForm.setData('end_date', val || '')}
                error={yearForm.errors.end_date}
                required
              />
            </div>
            <div className="sm:col-span-3 flex justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={() => setShowAddYear(false)}
                className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4.5 py-2.5 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-colors cursor-pointer"
              >
                {ax('cancel')}
              </button>
              <button
                type="submit"
                disabled={yearForm.processing}
                className="rounded-xl bg-[var(--primary)] px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-90 disabled:opacity-50 transition-all cursor-pointer"
              >
                {tx('generate12Periods')}
              </button>
            </div>
          </form>
        </Card>
      ) : null}

      {fiscalYears.length === 0 && !showAddYear ? (
        <EmptyState
          title={tx('noFiscalYearsTitle')}
          description={tx('noFiscalYearsDesc')}
        />
      ) : null}

      {activeModalPeriod && modalMode ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
          <Card className="w-full max-w-xl p-6 bg-[var(--surface)] border border-[var(--border)] shadow-2xl space-y-4">
            <div className="flex items-center justify-between border-b border-[var(--border)] pb-3">
              <h3 className="text-sm font-bold text-[var(--text-primary)]">
                {modalMode === 'close'
                  ? `${tx('closePeriod')} (${tx('month')} ${activeModalPeriod.month})`
                  : `${tx('reopenPeriod')} (${tx('month')} ${activeModalPeriod.month})`}
              </h3>
              <button
                type="button"
                onClick={() => {
                  setActiveModalPeriod(null);
                  setModalMode(null);
                }}
                className="text-[var(--text-muted)] hover:text-[var(--text-primary)] cursor-pointer"
              >
                <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            {modalMode === 'close' ? (
              loadingReadiness ? (
                <div className="py-6 text-center text-xs text-[var(--text-muted)] font-mono animate-pulse">
                  {tx('checkingReadiness')}
                </div>
              ) : readiness && !readiness.can_close ? (
                <div className="space-y-3">
                  <div className="rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-red-700 dark:text-red-300 text-xs">
                    <h4 className="font-bold uppercase mb-1">
                      {tx('closeBlockersTitle')}
                    </h4>
                    <p>{tx('closeBlockersDesc')}</p>
                  </div>

                  <div className="max-h-48 overflow-y-auto space-y-1.5 pr-1">
                    {readiness.blockers.map((b, idx) => (
                      <div key={idx} className="flex items-center justify-between p-2 rounded-lg bg-[var(--background)] border border-[var(--border)] text-xs">
                        <div className="flex items-center gap-2">
                          <span className="font-bold text-[var(--primary)]">{getEntityLabel(b.entity_type)}</span>
                          <span className="font-mono text-[var(--text-secondary)]">{b.number_or_reference}</span>
                        </div>
                        <span className="font-mono uppercase text-[10px] px-2 py-0.5 rounded bg-[var(--surface-subtle)] border border-[var(--border)] font-bold">
                          {getBlockerStatusLabel(b.status)}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              ) : (
                <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-emerald-700 dark:text-emerald-300 text-xs">
                  {tx('periodReadyToClose')}
                </div>
              )
            ) : null}

            <form onSubmit={submitCloseOrReopen} className="space-y-4 pt-2">
              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {tx('closeNote')}
                </label>
                <textarea
                  rows={2}
                  value={closeNote}
                  onChange={(e) => setCloseNote(e.target.value)}
                  placeholder={tx('closeNotePlaceholder')}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] p-2.5 text-xs text-[var(--text-primary)]"
                />
              </div>

              <div className="flex justify-end gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => {
                    setActiveModalPeriod(null);
                    setModalMode(null);
                  }}
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--background)]"
                >
                  {ax('cancel')}
                </button>
                <button
                  type="submit"
                  disabled={actionForm.processing || (modalMode === 'close' && readiness !== null && !readiness.can_close)}
                  className={`rounded-xl px-5 py-2 text-xs font-bold text-white shadow-md disabled:opacity-50 ${
                    modalMode === 'close' ? 'bg-red-600 hover:bg-red-700' : 'bg-indigo-600 hover:bg-indigo-700'
                  }`}
                >
                  {modalMode === 'close' ? tx('closePeriod') : tx('reopenPeriod')}
                </button>
              </div>
            </form>
          </Card>
        </div>
      ) : null}

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
                      {tx('fiscalYear')} {fy.year}
                    </h3>
                    {getStatusBadge(fy.status)}
                  </div>
                  <div className="mt-1 flex items-center gap-2 text-xs font-mono text-[var(--text-muted)]">
                    <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>
                      {formatDate(fy.start_date)} {tx('to')} {formatDate(fy.end_date)}
                    </span>
                  </div>
                </div>

                <div className="flex items-center gap-4 bg-[var(--background)] p-3 rounded-2xl border border-[var(--border)]">
                  <div className="text-end">
                    <span className="block text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)]">
                      {tx('closedPeriods')}
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
                          {tx('month')} {p.month}
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
                          canReopen ? (
                            <button
                              type="button"
                              onClick={() => openReopenModal(p)}
                              className="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 hover:underline cursor-pointer transition-colors"
                            >
                              <svg className="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                              </svg>
                              <span>{tx('reopenPeriod')}</span>
                            </button>
                          ) : null
                        ) : (
                          canClose ? (
                            <button
                              type="button"
                              onClick={() => openCloseModal(p)}
                              className="inline-flex items-center gap-1 text-[11px] font-bold text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 hover:underline cursor-pointer transition-colors"
                            >
                              <svg className="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                              </svg>
                              <span>{tx('closePeriod')}</span>
                            </button>
                          ) : null
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
