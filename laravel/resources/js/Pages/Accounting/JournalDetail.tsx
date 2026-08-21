import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import AttachmentPanel from '../../Components/AttachmentPanel';
import { Card, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatDate, formatPeriodLabel, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { JournalLineRow, SharedPageProps } from '../../Types';

type JournalDetailProps = SharedPageProps & {
  journal: {
    id: string;
    number?: string | null;
    entry_date: string;
    description?: string | null;
    reference?: string | null;
    currency: string;
    status: string;
    posted_at?: string | null;
    period?: { id: string; month: number } | null;
    createdBy?: { name: string } | null;
    postedBy?: { name: string } | null;
    lines: JournalLineRow[];
    reversesEntry?: { id: string; number?: string | null } | null;
    reversalEntry?: { id: string; number?: string | null } | null;
  };
  openPeriods?: { id: string; month: number; start_date: string }[];
};

export default function JournalDetail({ locale, journal, openPeriods = [] }: JournalDetailProps) {
  const dict = getDictionary(locale);
  const accDict = (dict.app as any).accounting || {};
  const [showReverseModal, setShowReverseModal] = useState(false);
  const [showNumberModal, setShowNumberModal] = useState(false);
  const [reversalPeriodId, setReversalPeriodId] = useState(openPeriods[0]?.id ?? '');

  const submitForm = useForm({});
  const approveForm = useForm({});
  const postForm = useForm({});
  const reverseForm = useForm({ reversal_period_id: reversalPeriodId });

  const totalDebit = journal.lines.reduce((s, l) => s + l.debit_minor, 0);
  const totalCredit = journal.lines.reduce((s, l) => s + l.credit_minor, 0);

  const getName = (nameObj?: Record<string, string> | string | null) => {
    if (!nameObj) return '';
    if (typeof nameObj === 'string') return nameObj;
    return locale === 'ar' ? nameObj.ar || nameObj.en : nameObj.en || nameObj.ar;
  };

  const openPeriodOptions = openPeriods.map((p) => ({
    value: p.id,
    label: formatPeriodLabel(p, locale),
  }));

  return (
    <AppLayout active="accounting.journal">
      <Head title={`Journal ${journal.number || 'Draft'}`} />

      <PageHeader
        title={`Journal Voucher: ${journal.number || 'DRAFT'}`}
        description={`${locale === 'ar' ? 'تم الإنشاء في' : 'Created on'} ${formatDate(journal.entry_date)} - ${dict.app.fields.status}: ${journal.status}`}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            {journal.status === 'draft' ? (
              <button
                type="button"
                onClick={() => submitForm.post(`/accounting/journal/${journal.id}/submit`)}
                disabled={submitForm.processing}
                className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:border-[var(--primary)] transition-colors"
              >
                Submit for Approval
              </button>
            ) : null}

            {inArray(journal.status, ['draft', 'submitted']) ? (
              <button
                type="button"
                onClick={() => approveForm.post(`/accounting/journal/${journal.id}/approve`)}
                disabled={approveForm.processing}
                className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20 transition-colors"
              >
                Approve
              </button>
            ) : null}

            {inArray(journal.status, ['draft', 'submitted', 'approved']) ? (
              <button
                type="button"
                onClick={() => postForm.post(`/accounting/journal/${journal.id}/post`)}
                disabled={postForm.processing}
                className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:bg-[var(--primary-hover)] transition-colors"
              >
                {accDict.postToLedger || 'Post to Ledger'}
              </button>
            ) : null}

            {journal.status === 'posted' && !journal.reversalEntry ? (
              <button
                type="button"
                onClick={() => setShowReverseModal(!showReverseModal)}
                className="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-2 text-xs font-bold text-red-600 dark:text-red-400 hover:bg-red-500/20 transition-colors"
              >
                {accDict.reverseEntry || 'Reverse Entry'}
              </button>
            ) : null}
          </div>
        }
      />

      {/* Number Details Modal */}
      {showNumberModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4">
          <Card className="w-full max-w-lg border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between border-b border-[var(--border)] pb-4 mb-4">
              <div className="flex items-center gap-3">
                <div className="rounded-xl bg-blue-500/10 p-2.5 text-blue-500 border border-blue-500/20">
                  <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                  </svg>
                </div>
                <div>
                  <h3 className="m-0 text-base font-bold text-[var(--text-primary)]">
                    {dict.app.actions.numberDetails}
                  </h3>
                  <span className="font-mono text-xs font-bold text-blue-600 dark:text-blue-400">
                    {journal.number || 'UNASSIGNED DRAFT'}
                  </span>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setShowNumberModal(false)}
                className="rounded-lg p-1.5 text-[var(--text-muted)] hover:bg-[var(--background)] hover:text-[var(--text-primary)] transition-colors"
              >
                <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div className="grid grid-cols-2 gap-3 mb-6 text-sm">
              <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
                <span className="block text-xs text-[var(--text-muted)] font-semibold mb-1">Sequence Key</span>
                <span className="font-mono font-bold text-[var(--text-primary)]">journal.entry</span>
              </div>
              <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
                <span className="block text-xs text-[var(--text-muted)] font-semibold mb-1">Document Status</span>
                <StatusBadge tone={journal.status === 'posted' ? 'ok' : journal.status === 'reversed' ? 'danger' : 'warning'}>
                  {journal.status.toUpperCase()}
                </StatusBadge>
              </div>
              <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
                <span className="block text-xs text-[var(--text-muted)] font-semibold mb-1">Entry Date</span>
                <span className="font-mono text-[var(--text-primary)]">{journal.entry_date}</span>
              </div>
              <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
                <span className="block text-xs text-[var(--text-muted)] font-semibold mb-1">Total Lines</span>
                <span className="font-mono font-bold text-[var(--text-primary)]">{journal.lines.length} lines</span>
              </div>
            </div>

            <div className="flex justify-end pt-2 border-t border-[var(--border)]">
              <button
                type="button"
                onClick={() => setShowNumberModal(false)}
                className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-md hover:bg-[var(--primary-hover)] transition-colors"
              >
                {dict.app.actions.close}
              </button>
            </div>
          </Card>
        </div>
      ) : null}

      {/* Reverse Modal */}
      {showReverseModal ? (
        <Card className="p-6 mb-6 border-red-500/30 shadow-xl">
          <h3 className="m-0 text-sm font-bold text-[var(--text-primary)] mb-3">{accDict.reverseEntry || 'Reverse Journal Entry'}</h3>
          <p className="text-xs text-[var(--text-muted)] mb-4">
            Reversing this posted entry will create an automatic mirror journal entry (swapping debit and credit lines) in the selected open period.
          </p>

          <form
            onSubmit={(e) => {
              e.preventDefault();
              reverseForm.post(`/accounting/journal/${journal.id}/reverse`);
            }}
            className="flex items-center gap-3"
          >
            <div className="w-64">
              <SearchableSelect
                options={openPeriodOptions}
                value={reverseForm.data.reversal_period_id}
                onChange={(val) => {
                  setReversalPeriodId(val || '');
                  reverseForm.setData('reversal_period_id', val || '');
                }}
                isClearable={false}
              />
            </div>

            <button
              type="submit"
              disabled={reverseForm.processing}
              className="rounded-xl bg-red-600 px-5 py-2 text-xs font-bold text-white shadow-md shadow-red-500/20 hover:bg-red-700 transition-colors"
            >
              Confirm Reversal
            </button>
          </form>
        </Card>
      ) : null}

      <div className="grid gap-6 lg:grid-cols-3 mb-6">
        <Card className="p-5 lg:col-span-2 space-y-3">
          <div className="flex items-center justify-between border-b border-[var(--border)] pb-3">
            <div>
              <span className="text-xs text-[var(--text-muted)] block uppercase font-bold">Voucher Number</span>
              <div className="flex items-center gap-2 mt-0.5">
                <span className="text-base font-extrabold text-[var(--text-primary)] font-mono">
                  {journal.number || 'UNASSIGNED DRAFT'}
                </span>
                <button
                  type="button"
                  onClick={() => setShowNumberModal(true)}
                  className="inline-flex items-center gap-1 rounded-lg bg-blue-500/10 border border-blue-500/20 px-2 py-0.5 text-xs font-bold text-blue-600 dark:text-blue-400 hover:bg-blue-500/20 transition-colors"
                >
                  <svg className="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                  <span>{dict.app.actions.numberDetails}</span>
                </button>
              </div>
            </div>
            <StatusBadge tone={journal.status === 'posted' ? 'ok' : journal.status === 'reversed' ? 'danger' : 'warning'}>
              {journal.status.toUpperCase()}
            </StatusBadge>
          </div>

          <div className="grid sm:grid-cols-2 gap-4 text-xs">
            <div>
              <span className="text-[var(--text-muted)] block font-bold uppercase">Entry Date</span>
              <span className="font-mono text-[var(--text-primary)]">{journal.entry_date}</span>
            </div>
            <div>
              <span className="text-[var(--text-muted)] block font-bold uppercase">Currency</span>
              <span className="font-mono text-[var(--text-primary)]">{journal.currency}</span>
            </div>
            <div>
              <span className="text-[var(--text-muted)] block font-bold uppercase">Reference</span>
              <span className="font-mono text-[var(--text-primary)]">{journal.reference || '-'}</span>
            </div>
            <div>
              <span className="text-[var(--text-muted)] block font-bold uppercase">Created By</span>
              <span className="text-[var(--text-primary)]">{journal.createdBy?.name || 'System'}</span>
            </div>
          </div>

          {journal.description ? (
            <div className="pt-2 border-t border-[var(--border)] text-xs">
              <span className="text-[var(--text-muted)] block font-bold uppercase mb-0.5">Description</span>
              <p className="m-0 text-[var(--text-primary)]">{journal.description}</p>
            </div>
          ) : null}
        </Card>

        <Card className="p-5 space-y-3">
          <h4 className="m-0 text-xs font-bold uppercase tracking-wider text-[var(--text-muted)] border-b border-[var(--border)] pb-2">
            Audit Trail
          </h4>
          <div className="space-y-2 text-xs">
            {journal.posted_at ? (
              <div className="flex items-center justify-between">
                <span className="text-[var(--text-muted)]">Posted Date:</span>
                <span className="font-mono text-[var(--text-primary)]">{journal.posted_at}</span>
              </div>
            ) : null}
            {journal.reversesEntry ? (
              <div className="flex items-center justify-between">
                <span className="text-[var(--text-muted)]">Reverses Entry:</span>
                <span className="font-mono font-bold text-blue-500">{journal.reversesEntry.number}</span>
              </div>
            ) : null}
            {journal.reversalEntry ? (
              <div className="flex items-center justify-between">
                <span className="text-[var(--text-muted)]">Reversal Entry:</span>
                <span className="font-mono font-bold text-red-500">{journal.reversalEntry.number}</span>
              </div>
            ) : null}
          </div>
        </Card>
      </div>

      {/* Journal Lines Table */}
      <div className={tableClasses.wrap}>
        <table className={tableClasses.table}>
          <thead>
            <tr>
              <th className={tableClasses.th}>#</th>
              <th className={tableClasses.th}>Account Code</th>
              <th className={tableClasses.th}>Account Name</th>
              <th className={tableClasses.th}>Memo</th>
              <th className={`${tableClasses.th} text-right`}>Debit (Minor)</th>
              <th className={`${tableClasses.th} text-right`}>Credit (Minor)</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--border)]">
            {journal.lines.map((line) => (
              <tr key={line.id} className="hover:bg-[var(--background)]/50 transition-colors">
                <td className={tableClasses.td}>
                  <span className="font-mono text-xs text-[var(--text-muted)]">{line.line_no}</span>
                </td>
                <td className={tableClasses.td}>
                  <span className="font-mono font-bold text-xs text-blue-600 dark:text-blue-400">
                    {line.account?.code}
                  </span>
                </td>
                <td className={tableClasses.td}>
                  <span className="font-bold text-xs text-[var(--text-primary)]">
                    {getName(line.account?.name)}
                  </span>
                </td>
                <td className={tableClasses.td}>
                  <span className="text-xs text-[var(--text-secondary)]">{line.memo || '-'}</span>
                </td>
                <td className={`${tableClasses.td} text-right font-mono text-xs font-bold text-blue-600 dark:text-blue-400`}>
                  {line.debit_minor > 0 ? line.debit_minor : '-'}
                </td>
                <td className={`${tableClasses.td} text-right font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400`}>
                  {line.credit_minor > 0 ? line.credit_minor : '-'}
                </td>
              </tr>
            ))}
          </tbody>
          <tfoot className="bg-[var(--background)] border-t border-[var(--border)] font-bold text-xs">
            <tr>
              <td colSpan={4} className="p-3 text-right">TOTAL:</td>
              <td className="p-3 text-right font-mono text-blue-600 dark:text-blue-400">{totalDebit}</td>
              <td className="p-3 text-right font-mono text-emerald-600 dark:text-emerald-400">{totalCredit}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      {/* Journal Entry Attachments Panel */}
      <div className="mt-6">
        <AttachmentPanel
          entityType="journal_entry"
          entityId={journal.id}
          locale={locale === 'ar' ? 'ar' : 'en'}
        />
      </div>
    </AppLayout>
  );
}

function inArray(val: string, arr: string[]) {
  return arr.includes(val);
}
