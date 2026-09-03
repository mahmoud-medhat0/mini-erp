import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import AttachmentPanel from '../../Components/AttachmentPanel';
import { Card, SearchableSelect, SensitiveActionModal, StatusBadge } from '../../Components/Primitives';
import { formatDate, formatMoney, formatPeriodLabel, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
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
  const can = useCan();

  const canPrintVoucher = can('reports.print');
  const canSubmitJournal = can('accounting.submit');
  const canApproveJournal = can('accounting.approve');
  const canPostJournal = can('accounting.post') && can('view_financials');
  const canReverseJournal = (can('accounting.reverse') || can('settings.configure')) && can('view_financials');

  const [showReverseModal, setShowReverseModal] = useState(false);
  const [showNumberModal, setShowNumberModal] = useState(false);
  const [showPostConfirmation, setShowPostConfirmation] = useState(false);
  const [reversalPeriodId, setReversalPeriodId] = useState(openPeriods[0]?.id ?? '');
  const [copiedField, setCopiedField] = useState<string | null>(null);

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
  const isBalanced = totalDebit === totalCredit;

  const handlePostJournal = () => {
    setShowPostConfirmation(true);
  };

  const handleCopy = (text: string, field: string) => {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text);
      setCopiedField(field);
      setTimeout(() => setCopiedField(null), 2000);
    }
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

      {/* Top Breadcrumb Navigation */}
      <div className="mb-4 flex items-center justify-between">
        <Link
          href="/accounting/journal"
          className="inline-flex items-center gap-2 text-xs font-bold text-[var(--text-secondary)] hover:text-[var(--primary)] transition-colors cursor-pointer"
        >
          <svg className="size-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
          </svg>
          <span>{accDict.journal}</span>
        </Link>

        <div className="flex items-center gap-1.5 font-mono text-xs text-[var(--text-muted)] bg-[var(--background)] px-2.5 py-1 rounded-lg border border-[var(--border)]">
          <span>ID: {journal.id}</span>
          <button
            type="button"
            onClick={() => handleCopy(journal.id, 'id')}
            title={copiedField === 'id' ? (locale === 'ar' ? 'تم النسخ' : 'Copied!') : (locale === 'ar' ? 'نسخ' : 'Copy')}
            aria-label={locale === 'ar' ? 'نسخ' : 'Copy'}
            className="text-[var(--text-muted)] hover:text-[var(--text-primary)] transition-colors cursor-pointer"
          >
            {copiedField === 'id' ? (
              <svg className="size-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            ) : (
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            )}
          </button>
        </div>
      </div>

      {/* Main Page Header & Actions */}
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-[var(--border)] pb-5">
        <div className="flex items-center gap-3.5">
          <div className="flex size-12 items-center justify-center rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 shadow-xs">
            <svg className="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <div>
            <div className="flex items-center gap-2.5">
              <h1 className="m-0 text-xl font-extrabold text-[var(--text-primary)] font-mono tracking-tight">
                {journal.number || accDict.draftBadge}
              </h1>
              {journal.number && (
                <button
                  type="button"
                  onClick={() => handleCopy(journal.number!, 'number')}
                  title={copiedField === 'number' ? (locale === 'ar' ? 'تم النسخ' : 'Copied!') : (locale === 'ar' ? 'نسخ' : 'Copy')}
                  aria-label={locale === 'ar' ? 'نسخ' : 'Copy'}
                  className="rounded-lg p-1 text-[var(--text-muted)] hover:bg-[var(--background)] hover:text-[var(--text-primary)] transition-colors cursor-pointer"
                >
                  {copiedField === 'number' ? (
                    <svg className="size-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                  ) : (
                    <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                  )}
                </button>
              )}
              <StatusBadge tone={journal.status === 'posted' ? 'ok' : journal.status === 'reversed' ? 'danger' : 'warning'}>
                {getStatusLabel(journal.status)}
              </StatusBadge>
            </div>
            <p className="mt-1 text-xs text-[var(--text-secondary)]">
              {accDict.createdOn} <span className="font-mono font-medium">{formatDate(journal.entry_date)}</span>
            </p>
          </div>
        </div>

        {/* Action Buttons Toolbar */}
        <div className="flex flex-wrap items-center gap-2">
          {canPrintVoucher ? (
            <button
              type="button"
              onClick={() => window.print()}
              title={dict.app.actions.printVoucher}
              aria-label={dict.app.actions.printVoucher}
              className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer inline-flex items-center gap-1.5 shadow-2xs"
            >
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
              </svg>
              <span>{dict.app.actions.printVoucher}</span>
            </button>
          ) : null}

          {journal.status === 'draft' && canSubmitJournal ? (
            <button
              type="button"
              onClick={() => submitForm.post(`/accounting/journal/${journal.id}/submit`, { preserveScroll: true })}
              disabled={submitForm.processing}
              title={accDict.submitForApproval}
              aria-label={accDict.submitForApproval}
              className="rounded-xl border border-blue-500/30 bg-blue-500/10 px-4 py-2 text-xs font-bold text-blue-600 dark:text-blue-400 hover:bg-blue-500/20 transition-all cursor-pointer inline-flex items-center gap-1.5 shadow-2xs"
            >
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
              </svg>
              <span>{accDict.submitForApproval}</span>
            </button>
          ) : null}

          {inArray(journal.status, ['draft', 'submitted']) && canApproveJournal ? (
            <button
              type="button"
              onClick={() => approveForm.post(`/accounting/journal/${journal.id}/approve`, { preserveScroll: true })}
              disabled={approveForm.processing}
              title={accDict.approve}
              aria-label={accDict.approve}
              className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20 transition-all cursor-pointer inline-flex items-center gap-1.5 shadow-2xs"
            >
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
              </svg>
              <span>{accDict.approve}</span>
            </button>
          ) : null}

          {inArray(journal.status, ['draft', 'submitted', 'approved']) && canPostJournal ? (
            <button
              type="button"
              onClick={handlePostJournal}
              disabled={postForm.processing}
              title={accDict.confirmPostJournal}
              aria-label={accDict.confirmPostJournal}
              className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:bg-[var(--primary-hover)] active:scale-95 transition-all cursor-pointer inline-flex items-center gap-1.5"
            >
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
              <span>{accDict.postToLedger}</span>
            </button>
          ) : null}

          {journal.status === 'posted' && !journal.reversalEntry && canReverseJournal ? (
            <button
              type="button"
              onClick={() => setShowReverseModal(true)}
              title={accDict.reverseEntry}
              aria-label={accDict.reverseEntry}
              className="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-2 text-xs font-bold text-red-600 dark:text-red-400 hover:bg-red-500/20 transition-all cursor-pointer inline-flex items-center gap-1.5 shadow-2xs"
            >
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
              </svg>
              <span>{accDict.reverseEntry}</span>
            </button>
          ) : null}
        </div>
      </div>

      {/* Metrics Summary Overview Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <Card className="p-4 border-s-4 border-s-blue-500 bg-gradient-to-br from-blue-500/5 to-transparent shadow-xs">
          <span className="block text-xs font-bold uppercase text-[var(--text-muted)] tracking-wider">{accDict.debitMinor}</span>
          <span className="mt-1.5 block text-lg font-mono font-extrabold text-blue-600 dark:text-blue-400">
            {formatMoney(totalDebit, journal.currency)}
          </span>
        </Card>

        <Card className="p-4 border-s-4 border-s-emerald-500 bg-gradient-to-br from-emerald-500/5 to-transparent shadow-xs">
          <span className="block text-xs font-bold uppercase text-[var(--text-muted)] tracking-wider">{accDict.creditMinor}</span>
          <span className="mt-1.5 block text-lg font-mono font-extrabold text-emerald-600 dark:text-emerald-400">
            {formatMoney(totalCredit, journal.currency)}
          </span>
        </Card>

        <Card className={`p-4 border-s-4 shadow-xs ${isBalanced ? 'border-s-emerald-500 bg-emerald-500/5' : 'border-s-red-500 bg-red-500/5'}`}>
          <span className="block text-xs font-bold uppercase text-[var(--text-muted)] tracking-wider">
            {locale === 'ar' ? 'توازن القيد' : 'Balance Status'}
          </span>
          <div className="mt-1.5 flex items-center gap-2">
            {isBalanced ? (
              <span className="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-2.5 py-1 rounded-full border border-emerald-500/20">
                <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>{locale === 'ar' ? 'متوازن (0.00)' : 'Balanced (0.00)'}</span>
              </span>
            ) : (
              <span className="inline-flex items-center gap-1.5 text-xs font-bold text-red-600 dark:text-red-400 bg-red-500/10 px-2.5 py-1 rounded-full border border-red-500/20">
                <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>{formatMoney(Math.abs(totalDebit - totalCredit), journal.currency)}</span>
              </span>
            )}
          </div>
        </Card>

        <Card className="p-4 border-s-4 border-s-purple-500 bg-gradient-to-br from-purple-500/5 to-transparent shadow-xs">
          <span className="block text-xs font-bold uppercase text-[var(--text-muted)] tracking-wider">
            {locale === 'ar' ? 'عدد البنود' : 'Total Lines'}
          </span>
          <span className="mt-1.5 block text-lg font-mono font-extrabold text-[var(--text-primary)]">
            {journal.lines.length}
          </span>
        </Card>
      </div>

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
                className="rounded-lg p-1.5 text-[var(--text-muted)] hover:bg-[var(--background)] hover:text-[var(--text-primary)] transition-colors cursor-pointer"
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
                className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-md hover:bg-[var(--primary-hover)] transition-colors cursor-pointer"
              >
                {dict.app.actions.close}
              </button>
            </div>
          </Card>
        </div>
      ) : null}

      {/* Reverse Modal Panel */}
      {showReverseModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4">
          <Card className="w-full max-w-lg border border-red-500/30 bg-[var(--surface)] p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between border-b border-[var(--border)] pb-4 mb-4">
              <div className="flex items-center gap-3">
                <div className="rounded-xl bg-red-500/10 p-2.5 text-red-500 border border-red-500/20">
                  <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                  </svg>
                </div>
                <div>
                  <h3 className="m-0 text-base font-bold text-[var(--text-primary)]">
                    {accDict.reverseJournalEntry}
                  </h3>
                  <span className="font-mono text-xs text-[var(--text-muted)]">
                    {journal.number || accDict.draftBadge}
                  </span>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setShowReverseModal(false)}
                title={dict.app.actions.close}
                aria-label={dict.app.actions.close}
                className="rounded-lg p-1.5 text-[var(--text-muted)] hover:bg-[var(--background)] hover:text-[var(--text-primary)] transition-colors cursor-pointer"
              >
                <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <p className="text-xs leading-relaxed text-[var(--text-secondary)] mb-4">
              {accDict.reverseEntryDescription}
            </p>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                reverseForm.post(`/accounting/journal/${journal.id}/reverse`, {
                  preserveScroll: true,
                  onSuccess: () => setShowReverseModal(false),
                });
              }}
              className="space-y-4"
            >
              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] mb-1 uppercase">
                  {locale === 'ar' ? 'الفترة المالية العكسية' : 'Reversal Financial Period'}
                </label>
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

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] mb-1 uppercase">
                  {dict.app.sensitiveActions.reasonPlaceholder || (locale === 'ar' ? 'سبب العكس' : 'Reversal Reason')}
                </label>
                <textarea
                  value={reverseForm.data.reason}
                  onChange={(e) => reverseForm.setData('reason', e.target.value)}
                  placeholder={dict.app.sensitiveActions.reasonPlaceholder}
                  required
                  rows={3}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)] focus:border-red-500 focus:outline-none transition-colors"
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-3 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={() => setShowReverseModal(false)}
                  title={dict.app.actions.cancel || 'Cancel'}
                  aria-label={dict.app.actions.cancel || 'Cancel'}
                  className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-4 py-2 text-xs font-bold text-[var(--text-secondary)] hover:bg-[var(--surface)] transition-colors cursor-pointer"
                >
                  {dict.app.actions.cancel || 'Cancel'}
                </button>
                <button
                  type="submit"
                  disabled={reverseForm.processing || (reverseForm.data.reason || '').trim().length < 3}
                  title={accDict.reverseEntry}
                  aria-label={accDict.reverseEntry}
                  className="rounded-xl bg-red-600 px-5 py-2 text-xs font-bold text-white shadow-md shadow-red-500/20 hover:bg-red-700 disabled:opacity-50 transition-colors cursor-pointer"
                >
                  {accDict.reverseEntry}
                </button>
              </div>
            </form>
          </Card>
        </div>
      ) : null}

      {/* Main Details & Audit Trail Layout Grid */}
      <div className="grid gap-6 lg:grid-cols-3 items-start mb-6">
        {/* Voucher Metadata Overview */}
        <Card className="p-5 lg:col-span-2 space-y-4 shadow-sm">
          <div className="flex items-center justify-between border-b border-[var(--border)] pb-3">
            <h3 className="m-0 text-xs font-extrabold text-[var(--text-primary)] uppercase tracking-wider flex items-center gap-2">
              <svg className="size-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>{dict.app.actions.numberDetails}</span>
            </h3>
            <button
              type="button"
              onClick={() => setShowNumberModal(true)}
              title={dict.app.actions.numberDetails}
              aria-label={dict.app.actions.numberDetails}
              className="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-1 cursor-pointer"
            >
              <span>{dict.app.actions.numberDetails}</span>
              <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>

          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-3.5 text-xs">
            <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
              <span className="text-[var(--text-muted)] block font-bold uppercase mb-1 text-[11px]">{accDict.entryDate}</span>
              <span className="font-mono font-bold text-[var(--text-primary)] text-sm">{formatDate(journal.entry_date)}</span>
            </div>

            <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
              <span className="text-[var(--text-muted)] block font-bold uppercase mb-1 text-[11px]">{accDict.currency}</span>
              <span className="font-mono font-bold text-[var(--text-primary)] text-sm">{journal.currency}</span>
            </div>

            <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
              <span className="text-[var(--text-muted)] block font-bold uppercase mb-1 text-[11px]">{accDict.reference}</span>
              <div className="flex items-center justify-between">
                <span className="font-mono text-[var(--text-primary)] font-medium">
                  {journal.reference || accDict.notAvailable}
                </span>
                {journal.reference && (
                  <button
                    type="button"
                    onClick={() => handleCopy(journal.reference!, 'ref')}
                    title={copiedField === 'ref' ? (locale === 'ar' ? 'تم النسخ' : 'Copied!') : (locale === 'ar' ? 'نسخ' : 'Copy')}
                    aria-label={locale === 'ar' ? 'نسخ' : 'Copy'}
                    className="text-[var(--text-muted)] hover:text-[var(--text-primary)] cursor-pointer"
                  >
                    {copiedField === 'ref' ? (
                      <svg className="size-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                      </svg>
                    ) : (
                      <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                      </svg>
                    )}
                  </button>
                )}
              </div>
            </div>

            <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
              <span className="text-[var(--text-muted)] block font-bold uppercase mb-1 text-[11px]">{branchReportDict.branch}</span>
              <span className="font-medium text-[var(--text-primary)]">
                {journal.branch ? `${journal.branch.code} - ${getLocalizedName(journal.branch.name, locale)}` : branchReportDict.notAssigned}
              </span>
            </div>

            <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
              <span className="text-[var(--text-muted)] block font-bold uppercase mb-1 text-[11px]">{accDict.createdBy}</span>
              <span className="font-semibold text-[var(--text-primary)]">{journal.createdBy?.name || accDict.systemActor}</span>
            </div>

            <div className="rounded-xl bg-[var(--background)] p-3 border border-[var(--border)]">
              <span className="text-[var(--text-muted)] block font-bold uppercase mb-1 text-[11px]">{dict.app.fields.status}</span>
              <StatusBadge tone={journal.status === 'posted' ? 'ok' : journal.status === 'reversed' ? 'danger' : 'warning'}>
                {getStatusLabel(journal.status)}
              </StatusBadge>
            </div>
          </div>

          {journal.description ? (
            <div className="rounded-xl bg-blue-500/5 border border-blue-500/10 p-3.5 text-xs">
              <span className="text-blue-600 dark:text-blue-400 block font-bold uppercase mb-1 text-[11px]">{accDict.descriptionMemo}</span>
              <p className="m-0 text-[var(--text-primary)] leading-relaxed font-medium">{journal.description}</p>
            </div>
          ) : null}

          {journal.reversesEntry || journal.reversalEntry ? (
            <div className="flex flex-wrap items-center gap-3 pt-3 border-t border-[var(--border)] text-xs">
              {journal.reversesEntry ? (
                <div className="inline-flex items-center gap-1.5 rounded-lg bg-blue-500/10 px-3 py-1.5 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                  <span className="font-semibold">{accDict.reversesEntry}:</span>
                  <Link href={`/accounting/journal/${journal.reversesEntry.id}`} className="font-mono font-bold hover:underline">
                    {journal.reversesEntry.number || accDict.unassignedDraft}
                  </Link>
                </div>
              ) : null}
              {journal.reversalEntry ? (
                <div className="inline-flex items-center gap-1.5 rounded-lg bg-red-500/10 px-3 py-1.5 text-red-600 dark:text-red-400 border border-red-500/20">
                  <span className="font-semibold">{accDict.reversalEntry}:</span>
                  <Link href={`/accounting/journal/${journal.reversalEntry.id}`} className="font-mono font-bold hover:underline">
                    {journal.reversalEntry.number || accDict.unassignedDraft}
                  </Link>
                </div>
              ) : null}
            </div>
          ) : null}
        </Card>

        {/* Audit Trail & Governance Panel */}
        <Card className="p-5 space-y-4 shadow-sm">
          <h4 className="m-0 text-xs font-extrabold uppercase tracking-wider text-[var(--text-muted)] border-b border-[var(--border)] pb-2.5 flex items-center gap-2">
            <svg className="size-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <span>{accDict.auditTrail}</span>
          </h4>

          <div className="pt-1 text-xs">
            {/* Step 1: Created */}
            <div className="flex items-stretch gap-3 group">
              <div className="relative flex flex-col items-center shrink-0 w-6">
                <div className="w-0.5 absolute top-0 bottom-0 bg-[var(--border)] group-first:top-3 group-last:bottom-auto group-last:h-3" />
                <div className="relative z-10 flex size-6 items-center justify-center rounded-full bg-blue-500/10 text-blue-500 border border-blue-500/30 bg-[var(--surface)] shrink-0 mt-0.5">
                  <span className="size-2 rounded-full bg-blue-500" />
                </div>
              </div>
              <div className="flex-1 rounded-xl bg-[var(--background)] p-3 border border-[var(--border)] group-last:mb-0 mb-3">
                <div className="flex items-center justify-between gap-2">
                  <p className="font-bold text-[var(--text-primary)] m-0">{locale === 'ar' ? 'أنشئ القيد' : 'Voucher Created'}</p>
                  <span className="font-mono text-[11px] text-[var(--text-muted)]">{formatDate(journal.entry_date)}</span>
                </div>
                <span className="text-[var(--text-secondary)] text-[11px] block mt-1 font-medium">
                  {journal.createdBy?.name || accDict.systemActor}
                </span>
              </div>
            </div>

            {/* Step 2: Posted or Pending */}
            <div className="flex items-stretch gap-3 group">
              <div className="relative flex flex-col items-center shrink-0 w-6">
                <div className="w-0.5 absolute top-0 bottom-0 bg-[var(--border)] group-first:top-3 group-last:bottom-auto group-last:h-3" />
                {journal.posted_at ? (
                  <div className="relative z-10 flex size-6 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-500 border border-emerald-500/30 bg-[var(--surface)] shrink-0 mt-0.5">
                    <span className="size-2 rounded-full bg-emerald-500" />
                  </div>
                ) : (
                  <div className="relative z-10 flex size-6 items-center justify-center rounded-full bg-slate-500/10 text-slate-400 border border-[var(--border)] bg-[var(--surface)] shrink-0 mt-0.5">
                    <span className="size-2 rounded-full bg-slate-400" />
                  </div>
                )}
              </div>
              <div className="flex-1 rounded-xl bg-[var(--background)] p-3 border border-[var(--border)] group-last:mb-0 mb-3">
                {journal.posted_at ? (
                  <>
                    <div className="flex items-center justify-between gap-2">
                      <p className="font-bold text-[var(--text-primary)] m-0">{accDict.postedDate}</p>
                      <span className="font-mono text-[11px] text-[var(--text-muted)]">{formatDate(journal.posted_at)}</span>
                    </div>
                    <span className="text-[var(--text-secondary)] text-[11px] block mt-1 font-medium">
                      {journal.postedBy?.name || accDict.systemActor}
                    </span>
                  </>
                ) : (
                  <>
                    <div className="flex items-center justify-between gap-2">
                      <p className="font-semibold text-[var(--text-muted)] m-0">
                        {locale === 'ar' ? 'بانتظار الترحيل' : 'Pending Posting'}
                      </p>
                      <span className="text-[11px] text-[var(--text-muted)] font-mono">—</span>
                    </div>
                    <span className="text-[var(--text-muted)] text-[11px] block mt-1 font-medium">
                      {locale === 'ar' ? 'لم يتم الترحيل بعد' : 'Not posted yet'}
                    </span>
                  </>
                )}
              </div>
            </div>

            {/* Step 3: Reversal (if reversed) */}
            {journal.reversalEntry ? (
              <div className="flex items-stretch gap-3 group">
                <div className="relative flex flex-col items-center shrink-0 w-6">
                  <div className="w-0.5 absolute top-0 bottom-0 bg-[var(--border)] group-first:top-3 group-last:bottom-auto group-last:h-3" />
                  <div className="relative z-10 flex size-6 items-center justify-center rounded-full bg-red-500/10 text-red-500 border border-red-500/30 bg-[var(--surface)] shrink-0 mt-0.5">
                    <span className="size-2 rounded-full bg-red-500" />
                  </div>
                </div>
                <div className="flex-1 rounded-xl bg-red-500/5 p-3 border border-red-500/20 group-last:mb-0 mb-3">
                  <div className="flex items-center justify-between gap-2">
                    <p className="font-bold text-red-600 dark:text-red-400 m-0">{accDict.statusReversed}</p>
                    <Link href={`/accounting/journal/${journal.reversalEntry.id}`} className="font-mono text-[11px] font-bold text-red-500 hover:underline">
                      {journal.reversalEntry.number || accDict.unassignedDraft}
                    </Link>
                  </div>
                  <span className="text-red-600/80 dark:text-red-400/80 text-[11px] block mt-1 font-medium">
                    {accDict.reversalEntry}
                  </span>
                </div>
              </div>
            ) : null}
          </div>
        </Card>
      </div>

      {/* Journal Lines Table Card */}
      <Card className="overflow-hidden p-0 mb-6 border-[var(--border)] shadow-sm">
        <div className="flex items-center justify-between border-b border-[var(--border)] bg-[var(--background)] px-5 py-3.5">
          <div className="flex items-center gap-2">
            <h3 className="m-0 text-xs font-extrabold text-[var(--text-primary)] uppercase tracking-wider">
              {locale === 'ar' ? 'بنود القيد المحاسبي' : 'Journal Entry Lines'}
            </h3>
            <span className="rounded-full bg-blue-500/10 border border-blue-500/20 px-2.5 py-0.5 text-xs font-extrabold text-blue-600 dark:text-blue-400 font-mono">
              {journal.lines.length}
            </span>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-[var(--border)] text-xs">
            <thead className="bg-[var(--background)]">
              <tr>
                <th className="px-4 py-3 text-start font-bold uppercase text-[var(--text-muted)] w-12">#</th>
                <th className="px-4 py-3 text-start font-bold uppercase text-[var(--text-muted)]">{accDict.accountCode}</th>
                <th className="px-4 py-3 text-start font-bold uppercase text-[var(--text-muted)]">{accDict.accountName}</th>
                <th className="px-4 py-3 text-start font-bold uppercase text-[var(--text-muted)]">{branchReportDict.branch}</th>
                <th className="px-4 py-3 text-start font-bold uppercase text-[var(--text-muted)]">{accDict.project}</th>
                <th className="px-4 py-3 text-start font-bold uppercase text-[var(--text-muted)]">{accDict.costCenter}</th>
                <th className="px-4 py-3 text-start font-bold uppercase text-[var(--text-muted)]">{accDict.lineMemo}</th>
                <th className="px-4 py-3 text-end font-bold uppercase text-[var(--text-muted)]">{accDict.debitMinor}</th>
                <th className="px-4 py-3 text-end font-bold uppercase text-[var(--text-muted)]">{accDict.creditMinor}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border)] bg-[var(--surface)]">
              {journal.lines.map((line) => (
                <tr key={line.id || line.line_no} className="hover:bg-[var(--background)]/60 transition-colors">
                  <td className="px-4 py-3 font-mono text-[var(--text-muted)]">{line.line_no}</td>
                  <td className="px-4 py-3">
                    <span className="inline-block rounded-md bg-blue-500/10 border border-blue-500/20 px-2 py-0.5 font-mono font-bold text-blue-600 dark:text-blue-400">
                      {line.account?.code}
                    </span>
                  </td>
                  <td className="px-4 py-3 font-bold text-[var(--text-primary)]">
                    {getLocalizedName(line.account?.name, locale)}
                  </td>
                  <td className="px-4 py-3 text-[var(--text-secondary)]">
                    {line.branch ? (
                      <span className="inline-flex items-center gap-1 rounded bg-[var(--background)] px-2 py-0.5 border border-[var(--border)]">
                        {line.branch.code} - {getLocalizedName(line.branch.name, locale)}
                      </span>
                    ) : (
                      <span className="text-[var(--text-muted)]">—</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-[var(--text-secondary)]">
                    {line.project ? (
                      <span className="inline-flex items-center gap-1 rounded bg-[var(--background)] px-2 py-0.5 border border-[var(--border)]">
                        {line.project.code} - {getLocalizedName(line.project.name, locale)}
                      </span>
                    ) : (
                      <span className="text-[var(--text-muted)]">—</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-[var(--text-secondary)]">
                    {line.costCenter ? (
                      <span className="inline-flex items-center gap-1 rounded bg-[var(--background)] px-2 py-0.5 border border-[var(--border)]">
                        {line.costCenter.code} - {getLocalizedName(line.costCenter.name, locale)}
                      </span>
                    ) : (
                      <span className="text-[var(--text-muted)]">—</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-[var(--text-secondary)] italic">
                    {line.memo || <span className="text-[var(--text-muted)] font-normal not-italic">—</span>}
                  </td>
                  <td className="px-4 py-3 text-end font-mono font-bold text-blue-600 dark:text-blue-400 text-sm">
                    {line.debit_minor > 0 ? formatMoney(line.debit_minor, journal.currency) : <span className="text-[var(--text-muted)] font-normal">—</span>}
                  </td>
                  <td className="px-4 py-3 text-end font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm">
                    {line.credit_minor > 0 ? formatMoney(line.credit_minor, journal.currency) : <span className="text-[var(--text-muted)] font-normal">—</span>}
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot className="bg-[var(--background)] border-t-2 border-[var(--border)] font-bold text-xs">
              <tr>
                <td colSpan={7} className="px-4 py-3.5 text-end text-[var(--text-primary)] uppercase tracking-wider">
                  {accDict.totalLabel}
                </td>
                <td className="px-4 py-3.5 text-end font-mono text-sm text-blue-600 dark:text-blue-400">
                  {formatMoney(totalDebit, journal.currency)}
                </td>
                <td className="px-4 py-3.5 text-end font-mono text-sm text-emerald-600 dark:text-emerald-400">
                  {formatMoney(totalCredit, journal.currency)}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </Card>

      {/* Attachments Section */}
      <div className="mt-6">
        <AttachmentPanel
          entityType="journal_entry"
          entityId={journal.id}
          locale={locale === 'ar' ? 'ar' : 'en'}
        />
      </div>

      <SensitiveActionModal
        isOpen={showPostConfirmation}
        onClose={() => setShowPostConfirmation(false)}
        onConfirm={(payload) => {
          postForm.setData('confirm_action', payload.confirm_action);
          postForm.post(`/accounting/journal/${journal.id}/post`, {
            preserveScroll: true,
            onSuccess: () => setShowPostConfirmation(false),
          });
        }}
        confirmCode="POST_JOURNAL_ENTRY"
        message={accDict.confirmPostJournal}
        isProcessing={postForm.processing}
        locale={locale}
      />
    </AppLayout>
  );
}

function inArray(val: string, arr: string[]) {
  return arr.includes(val);
}
