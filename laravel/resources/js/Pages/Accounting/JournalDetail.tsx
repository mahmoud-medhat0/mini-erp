import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import AttachmentPanel from '../../Components/AttachmentPanel';
import { Card, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatDate, formatMoney, formatPeriodLabel } from '../../lib/accountingHelpers';
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
    branch?: { id: string; code: string; name: Record<string, string> | string } | null;
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
  const accDict = dict.app.accounting;
  const branchReportDict = dict.app.pages.branchOperationsReport;
  const [showReverseModal, setShowReverseModal] = useState(false);
  const [showNumberModal, setShowNumberModal] = useState(false);
  const [reversalPeriodId, setReversalPeriodId] = useState(openPeriods[0]?.id ?? '');

  const submitForm = useForm({});
  const approveForm = useForm({});
  const postForm = useForm({ confirm_action: 'POST_JOURNAL_ENTRY' });
  const reverseForm = useForm({
    reversal_period_id: reversalPeriodId,
    confirm_action: 'REVERSE_JOURNAL_ENTRY',
    reason: '',
  });

  const totalDebit = journal.lines.reduce((s, l) => s + l.debit_minor, 0);
  const totalCredit = journal.lines.reduce((s, l) => s + l.credit_minor, 0);

  const handlePostJournal = () => {
    if (!confirm(accDict.confirmPostJournal)) return;

    postForm.post(`/accounting/journal/${journal.id}/post`, { preserveScroll: true });
  };

  const getName = (nameObj?: Record<string, string> | string | null) => {
    if (!nameObj) return '';
    if (typeof nameObj === 'string') return nameObj;
    return locale === 'ar' ? nameObj.ar || nameObj.en : nameObj.en || nameObj.ar;
  };

  const openPeriodOptions = openPeriods.map((p) => ({
    value: p.id,
    label: formatPeriodLabel(p, locale),
  }));

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

  return (
    <AppLayout active="accounting.journal">
      <Head title={`${accDict.journalVoucherPrefix}${journal.number || accDict.draftBadge}`} />

      <PageHeader
        title={`${accDict.journalVoucherPrefix}${journal.number || accDict.draftBadge}`}
        description={`${accDict.createdOn} ${formatDate(journal.entry_date)} - ${dict.app.fields.status}: ${getStatusLabel(journal.status)}`}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            {journal.status === 'draft' ? (
              <button
                type="button"
                onClick={() => submitForm.post(`/accounting/journal/${journal.id}/submit`, { preserveScroll: true })}
                disabled={submitForm.processing}
                title={accDict.submitForApproval}
                aria-label={accDict.submitForApproval}
                className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:border-[var(--primary)] transition-colors"
              >
                {accDict.submitForApproval}
              </button>
            ) : null}

            {inArray(journal.status, ['draft', 'submitted']) ? (
              <button
                type="button"
                onClick={() => approveForm.post(`/accounting/journal/${journal.id}/approve`, { preserveScroll: true })}
                disabled={approveForm.processing}
                title={accDict.approve}
                aria-label={accDict.approve}
                className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20 transition-colors"
              >
                {accDict.approve}
              </button>
            ) : null}

            {inArray(journal.status, ['draft', 'submitted', 'approved']) ? (
              <button
                type="button"
                onClick={handlePostJournal}
                disabled={postForm.processing}
                title={accDict.confirmPostJournal}
                aria-label={accDict.confirmPostJournal}
                className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:bg-[var(--primary-hover)] transition-colors"
              >
                {accDict.postToLedger}
              </button>
            ) : null}

            {journal.status === 'posted' && !journal.reversalEntry ? (
              <button
                type="button"
                onClick={() => setShowReverseModal(!showReverseModal)}
                title={accDict.reverseEntry}
                aria-label={accDict.reverseEntry}
                className="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-2 text-xs font-bold text-red-600 dark:text-red-400 hover:bg-red-500/20 transition-colors"
              >
                {accDict.reverseEntry}
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
                    {journal.number || accDict.unassignedDraft}
                  </span>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setShowNumberModal(false)}
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
                <span className="block text-xs text-[var(--text-muted)] font-semibold mb-1">{accDict.sequenceKey}</span>
                <span className="font-mono font-bold text-[var(--text-primary)]">journal.entry</span>
              </div>
              <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
                <span className="block text-xs text-[var(--text-muted)] font-semibold mb-1">{accDict.documentStatus}</span>
                <StatusBadge tone={journal.status === 'posted' ? 'ok' : journal.status === 'reversed' ? 'danger' : 'warning'}>
                  {getStatusLabel(journal.status)}
                </StatusBadge>
              </div>
              <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
                <span className="block text-xs text-[var(--text-muted)] font-semibold mb-1">{accDict.entryDate}</span>
                <span className="font-mono text-[var(--text-primary)]">{formatDate(journal.entry_date)}</span>
              </div>
              <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
                <span className="block text-xs text-[var(--text-muted)] font-semibold mb-1">{accDict.totalLines}</span>
                <span className="font-mono font-bold text-[var(--text-primary)]">{journal.lines.length}</span>
              </div>
            </div>

            <div className="flex justify-end pt-2 border-t border-[var(--border)]">
              <button
                type="button"
                onClick={() => setShowNumberModal(false)}
                title={dict.app.actions.close}
                aria-label={dict.app.actions.close}
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
          <h3 className="m-0 text-sm font-bold text-[var(--text-primary)] mb-3">{accDict.reverseJournalEntry}</h3>
          <p className="text-xs text-[var(--text-muted)] mb-4">
            {accDict.reverseEntryDescription}
          </p>

          <form
            onSubmit={(e) => {
              e.preventDefault();
              reverseForm.post(`/accounting/journal/${journal.id}/reverse`, { preserveScroll: true });
            }}
            className="flex flex-wrap items-center gap-3"
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

            <div className="flex-1 min-w-[200px]">
              <input
                type="text"
                value={reverseForm.data.reason}
                onChange={(e) => reverseForm.setData('reason', e.target.value)}
                placeholder={dict.app.sensitiveActions.reasonPlaceholder}
                required
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none transition-colors"
              />
            </div>

            <button
              type="submit"
              disabled={reverseForm.processing || (reverseForm.data.reason || '').trim().length < 3}
              title={accDict.reverseEntry}
              aria-label={accDict.reverseEntry}
              className="rounded-xl bg-red-600 px-5 py-2 text-xs font-bold text-white shadow-md shadow-red-500/20 hover:bg-red-700 disabled:opacity-50 transition-colors cursor-pointer"
            >
              {accDict.reverseEntry}
            </button>
          </form>
        </Card>
      ) : null}

      <div className="grid gap-6 lg:grid-cols-3 mb-6">
        <Card className="p-5 lg:col-span-2 space-y-3">
          <div className="flex items-center justify-between border-b border-[var(--border)] pb-3">
            <div>
              <span className="text-xs text-[var(--text-muted)] block uppercase font-bold">{accDict.voucherNumber}</span>
              <div className="flex items-center gap-2 mt-0.5">
                <span className="text-base font-extrabold text-[var(--text-primary)] font-mono">
                  {journal.number || accDict.unassignedDraft}
                </span>
                <button
                  type="button"
                  onClick={() => setShowNumberModal(true)}
                  title={dict.app.actions.numberDetails}
                  aria-label={dict.app.actions.numberDetails}
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
              {getStatusLabel(journal.status)}
            </StatusBadge>
          </div>

          <div className="grid sm:grid-cols-2 gap-4 text-xs">
            <div>
              <span className="text-[var(--text-muted)] block font-bold uppercase">{accDict.entryDate}</span>
              <span className="font-mono text-[var(--text-primary)]">{formatDate(journal.entry_date)}</span>
            </div>
            <div>
              <span className="text-[var(--text-muted)] block font-bold uppercase">{accDict.currency}</span>
              <span className="font-mono text-[var(--text-primary)]">{journal.currency}</span>
            </div>
            <div>
              <span className="text-[var(--text-muted)] block font-bold uppercase">{accDict.reference}</span>
              <span className="font-mono text-[var(--text-primary)]">{journal.reference || accDict.notAvailable}</span>
            </div>
            <div>
              <span className="text-[var(--text-muted)] block font-bold uppercase">{branchReportDict.branch}</span>
              <span className="font-mono text-[var(--text-primary)]">
                {journal.branch ? `${journal.branch.code} - ${getName(journal.branch.name)}` : branchReportDict.notAssigned}
              </span>
            </div>
            <div>
              <span className="text-[var(--text-muted)] block font-bold uppercase">{accDict.createdBy}</span>
              <span className="text-[var(--text-primary)]">{journal.createdBy?.name || accDict.systemActor}</span>
            </div>
          </div>

          {journal.description ? (
            <div className="pt-2 border-t border-[var(--border)] text-xs">
              <span className="text-[var(--text-muted)] block font-bold uppercase mb-0.5">{accDict.descriptionMemo}</span>
              <p className="m-0 text-[var(--text-primary)]">{journal.description}</p>
            </div>
          ) : null}
        </Card>

        <Card className="p-5 space-y-3">
          <h4 className="m-0 text-xs font-bold uppercase tracking-wider text-[var(--text-muted)] border-b border-[var(--border)] pb-2">
            {accDict.auditTrail}
          </h4>
          <div className="space-y-2 text-xs">
            {journal.posted_at ? (
              <div className="flex items-center justify-between">
                <span className="text-[var(--text-muted)]">{accDict.postedDate}:</span>
                <span className="font-mono text-[var(--text-primary)]">{formatDate(journal.posted_at)}</span>
              </div>
            ) : null}
            {journal.reversesEntry ? (
              <div className="flex items-center justify-between">
                <span className="text-[var(--text-muted)]">{accDict.reversesEntry}:</span>
                <span className="font-mono font-bold text-blue-500">{journal.reversesEntry.number || accDict.unassignedDraft}</span>
              </div>
            ) : null}
            {journal.reversalEntry ? (
              <div className="flex items-center justify-between">
                <span className="text-[var(--text-muted)]">{accDict.reversalEntry}:</span>
                <span className="font-mono font-bold text-red-500">{journal.reversalEntry.number || accDict.unassignedDraft}</span>
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
              <th className={tableClasses.th}>{accDict.accountCode}</th>
              <th className={tableClasses.th}>{accDict.accountName}</th>
              <th className={tableClasses.th}>{branchReportDict.branch}</th>
              <th className={tableClasses.th}>{accDict.project}</th>
              <th className={tableClasses.th}>{accDict.costCenter}</th>
              <th className={tableClasses.th}>{accDict.lineMemo}</th>
              <th className={`${tableClasses.th} text-right`}>{accDict.debitMinor}</th>
              <th className={`${tableClasses.th} text-right`}>{accDict.creditMinor}</th>
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
                  <span className="text-xs text-[var(--text-secondary)]">
                    {line.branch ? `${line.branch.code} - ${getName(line.branch.name)}` : branchReportDict.notAssigned}
                  </span>
                </td>
                <td className={tableClasses.td}>
                  <span className="text-xs text-[var(--text-secondary)]">
                    {line.project ? `${line.project.code} - ${getName(line.project.name)}` : accDict.notAvailable}
                  </span>
                </td>
                <td className={tableClasses.td}>
                  <span className="text-xs text-[var(--text-secondary)]">
                    {line.costCenter ? `${line.costCenter.code} - ${getName(line.costCenter.name)}` : accDict.notAvailable}
                  </span>
                </td>
                <td className={tableClasses.td}>
                  <span className="text-xs text-[var(--text-secondary)]">{line.memo || accDict.notAvailable}</span>
                </td>
                <td className={`${tableClasses.td} text-right font-mono text-xs font-bold text-blue-600 dark:text-blue-400`}>
                  {line.debit_minor > 0 ? formatMoney(line.debit_minor, journal.currency) : accDict.notAvailable}
                </td>
                <td className={`${tableClasses.td} text-right font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400`}>
                  {line.credit_minor > 0 ? formatMoney(line.credit_minor, journal.currency) : accDict.notAvailable}
                </td>
              </tr>
            ))}
          </tbody>
          <tfoot className="bg-[var(--background)] border-t border-[var(--border)] font-bold text-xs">
            <tr>
              <td colSpan={7} className="p-3 text-right">{accDict.totalLabel}</td>
              <td className="p-3 text-right font-mono text-blue-600 dark:text-blue-400">{formatMoney(totalDebit, journal.currency)}</td>
              <td className="p-3 text-right font-mono text-emerald-600 dark:text-emerald-400">{formatMoney(totalCredit, journal.currency)}</td>
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
