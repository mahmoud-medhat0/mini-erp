import { Head, Link } from '@inertiajs/react';
import { useMemo, useState, type ReactElement } from 'react';
import AppLayout from '../../Components/AppLayout';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { Card, PageHeader, StatusBadge } from '../../Components/Primitives';
import { formatDate } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

/**
 * Row shape the datatable feed actually emits. `createdBy()`'s snake-cased
 * relation key would collide with the real `created_by` FK column in
 * Eloquent's array serialization, so the backend exposes the name as a plain
 * `creator_name` string instead of a nested object.
 */
type JournalGridRow = {
  id: string;
  number: string | null;
  entry_date: string;
  description: string | null;
  reference: string | null;
  status: string;
  creator_name: string | null;
};

type GeneralJournalProps = SharedPageProps & {
  filters: { status?: string; period_id?: string; start_date?: string; end_date?: string };
};

export default function GeneralJournal({ locale, filters }: GeneralJournalProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const can = useCan();
  const canCreateVoucher = can('accounting.create');

  const getStatusLabel = (status: string) => {
    const s = status.toLowerCase();
    const map: Record<string, string> = {
      draft: accDict.statusDraft,
      submitted: accDict.statusSubmitted,
      approved: accDict.statusApproved,
      posted: accDict.statusPosted,
      reversed: accDict.statusReversed,
    };

    return map[s] ?? accDict.statusUnknown;
  };

  const statusFilterList = [
    { key: '', label: accDict.statusAll },
    { key: 'draft', label: accDict.statusDraft },
    { key: 'submitted', label: accDict.statusSubmitted },
    { key: 'approved', label: accDict.statusApproved },
    { key: 'posted', label: accDict.statusPosted },
    { key: 'reversed', label: accDict.statusReversed },
  ];

  const [selectedJournal, setSelectedJournal] = useState<JournalGridRow | null>(null);
  const [statusFilter, setStatusFilter] = useState(filters.status ?? '');

  // ── grid configuration ────────────────────────────────────────────────────
  const dtColumns = useMemo(() => [
    { data: 'number', name: 'number', title: accDict.voucherNumber },
    { data: 'entry_date', name: 'entry_date', title: accDict.entryDate, className: 'font-mono text-xs', width: '110px' },
    { data: 'description', name: 'description', title: accDict.description },
    { data: 'reference', name: 'reference', title: accDict.reference, className: 'font-mono text-xs' },
    { data: 'status', name: 'status', title: dict.app.fields.status, width: '120px' },
    { data: 'creator_name', name: 'creator_name', title: accDict.createdBy, orderable: false, width: '160px' },
    { data: 'id', name: 'id', title: '', orderable: false, width: '110px', className: 'text-end' },
  ], [accDict, dict]);

  const dtSlots = useMemo<DataTableSlots>(() => ({
    number: (data: string | null, _type: unknown, row: JournalGridRow): ReactElement => (
      <button
        type="button"
        onClick={() => setSelectedJournal(row)}
        title={dict.app.actions.numberDetails}
        aria-label={dict.app.actions.numberDetails}
        className="font-mono font-bold text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1 bg-blue-500/10 border border-blue-500/20 px-2.5 py-1 rounded-lg transition-colors cursor-pointer"
      >
        <svg className="size-3 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        </svg>
        <span>{data || accDict.draftBadge}</span>
      </button>
    ),
    entry_date: (data: string): ReactElement => (
      <span className="font-mono text-xs text-[var(--text-primary)]">{formatDate(data)}</span>
    ),
    description: (data: string | null): ReactElement => (
      <span className="font-bold text-xs text-[var(--text-primary)]">{data || accDict.manualJournal}</span>
    ),
    reference: (data: string | null): ReactElement => (
      <span className="font-mono text-xs text-[var(--text-secondary)]">{data || accDict.notAvailable}</span>
    ),
    status: (data: string): ReactElement => (
      <StatusBadge tone={data === 'posted' ? 'ok' : data === 'reversed' ? 'danger' : 'warning'}>
        {getStatusLabel(data)}
      </StatusBadge>
    ),
    creator_name: (data: string | null): ReactElement => (
      <span className="text-xs text-[var(--text-secondary)]">{data || accDict.systemActor}</span>
    ),
    id: (data: string): ReactElement => (
      <Link
        href={`/accounting/journal/${data}`}
        title={accDict.viewDetail}
        aria-label={accDict.viewDetail}
        className="rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-blue-600 dark:text-blue-400 hover:border-blue-500 hover:bg-[var(--background)] transition-colors inline-flex items-center gap-1"
      >
        <span>{accDict.viewDetail}</span>
        <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
        </svg>
      </Link>
    ),
  } as unknown as DataTableSlots), [accDict, dict]);

  const dtFilters = useMemo(() => ({ status: statusFilter }), [statusFilter]);

  return (
    <AppLayout active="accounting.journal">
      <Head title={accDict.journal} />

      <PageHeader
        title={accDict.journal}
        description={accDict.journalDesc}
        actions={
          canCreateVoucher ? (
            <Link
              href="/accounting/journal/create"
              title={accDict.createVoucher}
              aria-label={accDict.createVoucher}
              className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-95 active:scale-95 transition-all cursor-pointer"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{accDict.createVoucher}</span>
            </Link>
          ) : null
        }
      />

      {/* Journal Entry Number Details Modal */}
      {selectedJournal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4">
          <Card className="w-full max-w-lg border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between border-b border-[var(--border)] pb-4 mb-4">
              <div className="flex items-center gap-3">
                <div className="rounded-xl bg-blue-500/10 p-2.5 text-blue-500 border border-blue-500/20">
                  <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <div>
                  <h3 className="m-0 text-base font-bold text-[var(--text-primary)]">
                    {dict.app.actions.numberDetails}
                  </h3>
                  <span className="font-mono text-xs font-bold text-blue-600 dark:text-blue-400">
                    {selectedJournal.number || accDict.draftBadge}
                  </span>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setSelectedJournal(null)}
                title={dict.app.actions.close}
                aria-label={dict.app.actions.close}
                className="rounded-lg p-1.5 text-[var(--text-muted)] hover:bg-[var(--background)] hover:text-[var(--text-primary)] transition-colors"
              >
                <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div className="grid grid-cols-2 gap-3 mb-6 text-sm">
              <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
                <span className="block text-xs text-[var(--text-muted)] font-semibold mb-1">{accDict.entryDate}</span>
                <span className="font-mono font-bold text-[var(--text-primary)]">{formatDate(selectedJournal.entry_date)}</span>
              </div>
              <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
                <span className="block text-xs text-[var(--text-muted)] font-semibold mb-1">{dict.app.fields.status}</span>
                <StatusBadge tone={selectedJournal.status === 'posted' ? 'ok' : selectedJournal.status === 'reversed' ? 'danger' : 'warning'}>
                  {getStatusLabel(selectedJournal.status)}
                </StatusBadge>
              </div>
              <div className="col-span-2 rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
                <span className="block text-xs text-[var(--text-muted)] font-semibold mb-1">{accDict.description}</span>
                <span className="font-bold text-[var(--text-primary)]">{selectedJournal.description || accDict.manualJournal}</span>
              </div>
              <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
                <span className="block text-xs text-[var(--text-muted)] font-semibold mb-1">{accDict.reference}</span>
                <span className="font-mono text-[var(--text-primary)]">{selectedJournal.reference || accDict.notAvailable}</span>
              </div>
              <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
                <span className="block text-xs text-[var(--text-muted)] font-semibold mb-1">{accDict.createdBy}</span>
                <span className="text-[var(--text-primary)] font-semibold">{selectedJournal.creator_name || accDict.systemActor}</span>
              </div>
            </div>

            <div className="flex items-center justify-between pt-2 border-t border-[var(--border)]">
              <button
                type="button"
                onClick={() => setSelectedJournal(null)}
                title={dict.app.actions.close}
                aria-label={dict.app.actions.close}
                className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-4 py-2 text-xs font-bold text-[var(--text-secondary)] hover:bg-[var(--surface)] hover:text-[var(--text-primary)] transition-colors"
              >
                {dict.app.actions.close}
              </button>
              <Link
                href={`/accounting/journal/${selectedJournal.id}`}
                className="inline-flex items-center gap-1.5 rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-md hover:bg-[var(--primary-hover)] transition-colors"
              >
                <span>{accDict.viewFullVoucher}</span>
                <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                </svg>
              </Link>
            </div>
          </Card>
        </div>
      ) : null}

      <Card className="p-4 mb-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-xs font-bold text-[var(--text-secondary)] uppercase">{accDict.filterStatus}:</span>
            {statusFilterList.map((st) => (
              <button
                key={st.key}
                type="button"
                onClick={() => setStatusFilter(st.key)}
                title={st.label}
                aria-label={st.label}
                className={`rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all cursor-pointer ${
                  statusFilter === st.key
                    ? 'bg-[var(--primary)] text-white shadow-md shadow-blue-500/20'
                    : 'bg-[var(--background)] text-[var(--text-secondary)] hover:bg-[var(--surface)] border border-[var(--border)]'
                }`}
              >
                {st.label}
              </button>
            ))}
          </div>
        </div>
      </Card>

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/accounting/journal/data"
          columns={dtColumns}
          filters={dtFilters}
          locale={locale}
          order={[[1, 'desc']]}
          pageLength={20}
          slots={dtSlots}
          tableId="general-journal-table"
        />
      </Card>
    </AppLayout>
  );
}
