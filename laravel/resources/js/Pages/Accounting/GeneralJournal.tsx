import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatDate } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types/page';

type JournalRow = {
  id: string;
  number?: string | null;
  entry_date: string;
  description?: string | null;
  reference?: string | null;
  currency: string;
  status: string;
  period?: { id: string; month: number } | null;
  createdBy?: { id: number; name: string } | null;
};

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
                  <span className="font-mono font-bold text-xs text-blue-600 dark:text-blue-400">
                    {j.number ? j.number : (accDict.draftBadge || 'DRAFT')}
                  </span>
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
    </AppLayout>
  );
}
