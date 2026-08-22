import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatDate } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { JournalRow, SharedPageProps } from '../../Types';

type GeneralJournalProps = SharedPageProps & {
  journals: {
    data: JournalRow[];
    links: any[];
  };
  periods: { id: string; month: number; status: string }[];
  filters: { status?: string; period_id?: string; start_date?: string; end_date?: string };
};

export default function GeneralJournal({ locale, journals, periods = [], filters }: GeneralJournalProps) {
  const dict = getDictionary(locale);
  const accDict = (dict.app as any).accounting || {};

  const getStatusLabel = (status: string) => {
    const s = status.toLowerCase();
    const map: Record<string, { en: string; ar: string }> = {
      draft: { en: 'DRAFT', ar: 'مسودة' },
      submitted: { en: 'SUBMITTED', ar: 'مقدم للمراجعة' },
      approved: { en: 'APPROVED', ar: 'معتمد' },
      posted: { en: 'POSTED', ar: 'رحل' },
      reversed: { en: 'REVERSED', ar: 'معكوس' },
    };
    if (!map[s]) return status.toUpperCase();
    return locale === 'ar' ? map[s].ar : map[s].en;
  };

  const statusFilterList = [
    { key: '', label: accDict.statusAll || 'ALL' },
    { key: 'draft', label: accDict.statusDraft || 'DRAFT' },
    { key: 'submitted', label: accDict.statusSubmitted || 'SUBMITTED' },
    { key: 'approved', label: accDict.statusApproved || 'APPROVED' },
    { key: 'posted', label: accDict.statusPosted || 'POSTED' },
    { key: 'reversed', label: accDict.statusReversed || 'REVERSED' },
  ];

  const [selectedJournal, setSelectedJournal] = useState<JournalRow | null>(null);

  return (
    <AppLayout active="accounting.journal">
      <Head title={accDict.journal || 'General Journal'} />

      <PageHeader
        title={accDict.journal || 'General Journal'}
        description={accDict.journalDesc || 'General Journal Vouchers stream across draft, submitted, approved, posted and reversed statuses.'}
        actions={
          <Link
            href="/accounting/journal/create"
            className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:opacity-95 active:scale-95 transition-all cursor-pointer"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>{accDict.createVoucher || 'Create Journal Voucher'}</span>
          </Link>
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
                    {selectedJournal.number || selectedJournal.entry_number || 'DRAFT'}
                  </span>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setSelectedJournal(null)}
                className="rounded-lg p-1.5 text-[var(--text-muted)] hover:bg-[var(--background)] hover:text-[var(--text-primary)] transition-colors"
              >
                <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div className="grid grid-cols-2 gap-3 mb-6 text-sm">
              <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
                <span className="block text-xs text-[var(--text-muted)] font-semibold mb-1">{accDict.entryDate || 'Entry Date'}</span>
                <span className="font-mono font-bold text-[var(--text-primary)]">{formatDate(selectedJournal.entry_date)}</span>
              </div>
              <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
                <span className="block text-xs text-[var(--text-muted)] font-semibold mb-1">{dict.app.fields.status}</span>
                <StatusBadge tone={selectedJournal.status === 'posted' ? 'ok' : selectedJournal.status === 'reversed' ? 'danger' : 'warning'}>
                  {getStatusLabel(selectedJournal.status)}
                </StatusBadge>
              </div>
              <div className="col-span-2 rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
                <span className="block text-xs text-[var(--text-muted)] font-semibold mb-1">{accDict.description || 'Description'}</span>
                <span className="font-bold text-[var(--text-primary)]">{selectedJournal.description || (accDict.manualJournal || 'Manual Journal')}</span>
              </div>
              <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
                <span className="block text-xs text-[var(--text-muted)] font-semibold mb-1">{accDict.reference || 'Reference'}</span>
                <span className="font-mono text-[var(--text-primary)]">{selectedJournal.reference || '-'}</span>
              </div>
              <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
                <span className="block text-xs text-[var(--text-muted)] font-semibold mb-1">{accDict.createdBy || 'Created By'}</span>
                <span className="text-[var(--text-primary)] font-semibold">{selectedJournal.createdBy?.name || '-'}</span>
              </div>
            </div>

            <div className="flex items-center justify-between pt-2 border-t border-[var(--border)]">
              <button
                type="button"
                onClick={() => setSelectedJournal(null)}
                className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-4 py-2 text-xs font-bold text-[var(--text-secondary)] hover:bg-[var(--surface)] hover:text-[var(--text-primary)] transition-colors"
              >
                {dict.app.actions.close}
              </button>
              <Link
                href={`/accounting/journal/${selectedJournal.id}`}
                className="inline-flex items-center gap-1.5 rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-md hover:bg-[var(--primary-hover)] transition-colors"
              >
                <span>{accDict.viewDetail || 'View Full Voucher'}</span>
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
            <span className="text-xs font-bold text-[var(--text-secondary)] uppercase">{accDict.filterStatus || 'Filter Status'}:</span>
            {statusFilterList.map((st) => (
              <Link
                key={st.key}
                href={`/accounting/journal?status=${st.key}`}
                className={`rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all ${
                  (filters.status ?? '') === st.key
                    ? 'bg-[var(--primary)] text-white shadow-md shadow-blue-500/20'
                    : 'bg-[var(--background)] text-[var(--text-secondary)] hover:bg-[var(--surface)] border border-[var(--border)]'
                }`}
              >
                {st.label}
              </Link>
            ))}
          </div>
        </div>
      </Card>

      {journals.data.length === 0 ? (
        <EmptyState
          title={accDict.noJournals || dict.app.pages.accountingGeneralJournal.noJournalVouchersYet}
          description={accDict.noJournalsDesc || dict.app.pages.accountingGeneralJournal.createANewJournalVoucherTo}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{accDict.voucherNumber || 'Voucher #'}</th>
                <th className={tableClasses.th}>{accDict.entryDate || 'Entry Date'}</th>
                <th className={tableClasses.th}>{accDict.description || 'Description'}</th>
                <th className={tableClasses.th}>{accDict.reference || 'Reference'}</th>
                <th className={tableClasses.th}>{dict.app.fields.status}</th>
                <th className={tableClasses.th}>{accDict.createdBy || 'Created By'}</th>
                <th className={tableClasses.th} />
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border)]">
              {journals.data.map((j) => (
                <tr key={j.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={tableClasses.td}>
                    <button
                      type="button"
                      onClick={() => setSelectedJournal(j)}
                      className="font-mono font-bold text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1 bg-blue-500/10 border border-blue-500/20 px-2.5 py-1 rounded-lg transition-colors cursor-pointer"
                      title={dict.app.actions.numberDetails}
                    >
                      <svg className="size-3 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                      <span>{j.number ? j.number : (accDict.draftBadge || 'DRAFT')}</span>
                    </button>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="font-mono text-xs text-[var(--text-primary)]">{formatDate(j.entry_date)}</span>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="font-bold text-xs text-[var(--text-primary)]">
                      {j.description || (accDict.manualJournal || 'Manual Journal')}
                    </span>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="font-mono text-xs text-[var(--text-secondary)]">{j.reference || '-'}</span>
                  </td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={j.status === 'posted' ? 'ok' : j.status === 'reversed' ? 'danger' : 'warning'}>
                      {getStatusLabel(j.status)}
                    </StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="text-xs text-[var(--text-secondary)]">{j.createdBy?.name || '-'}</span>
                  </td>
                  <td className={tableClasses.td}>
                    <Link
                      href={`/accounting/journal/${j.id}`}
                      className="rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-blue-600 dark:text-blue-400 hover:border-blue-500 hover:bg-[var(--background)] transition-colors inline-flex items-center gap-1"
                    >
                      <span>{accDict.viewDetail || 'View Detail'}</span>
                      <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                      </svg>
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </AppLayout>
  );
}
