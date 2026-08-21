import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

type ReconciliationLine = {
  id: string;
  statement_date: string;
  reference?: string | null;
  description?: string | null;
  debit_minor: number;
  credit_minor: number;
  matched_ledger_entry_id?: string | null;
  matched_at?: string | null;
  matchedLedgerEntry?: {
    id: string;
    entry_date: string;
    description?: string | null;
    debit_minor: number;
    credit_minor: number;
    journalEntry?: { id: string; entry_number: string };
  } | null;
};

type CandidateEntry = {
  id: string;
  entry_date: string;
  description?: string | null;
  debit_minor: number;
  credit_minor: number;
  journalEntry?: { id: string; entry_number: string };
};

type BankReconciliationShowProps = SharedPageProps & {
  reconciliation: {
    id: string;
    bankAccount?: { id: string; code: string; name: string; currency: string };
    statement_reference?: string | null;
    date_from: string;
    date_to: string;
    statement_opening_balance_minor: number;
    statement_closing_balance_minor: number;
    status: 'draft' | 'finalized';
    lines: ReconciliationLine[];
  };
  summary: {
    statement_opening_balance_minor: number;
    statement_closing_balance_minor: number;
    ledger_opening_balance_minor: number;
    ledger_closing_balance_minor: number;
    unmatched_statement_lines_count: number;
    unmatched_ledger_entries_count: number;
    reconciled_difference_minor: number;
    is_reconciled: boolean;
  };
  candidates: CandidateEntry[];
};

export default function BankReconciliationShow({
  locale,
  reconciliation,
  summary,
  candidates = [],
}: BankReconciliationShowProps) {
  const isAr = locale === 'ar';
  const dict = getDictionary(locale);

  const [showAddLineModal, setShowAddLineModal] = useState(false);
  const [selectedLineForMatch, setSelectedLineForMatch] = useState<ReconciliationLine | null>(null);

  // Form for adding a statement line
  const addLineForm = useForm({
    statement_date: reconciliation.date_from,
    reference: '',
    description: '',
    debit: '0',
    credit: '0',
    debit_minor: 0,
    credit_minor: 0,
  });

  const submitAddLine = (e: FormEvent) => {
    e.preventDefault();
    const drVal = parseFloat(addLineForm.data.debit || '0');
    const crVal = parseFloat(addLineForm.data.credit || '0');

    addLineForm.transform((data) => ({
      ...data,
      debit_minor: Math.round(drVal * 100),
      credit_minor: Math.round(crVal * 100),
    }));

    addLineForm.post(`/bank-reconciliations/${reconciliation.id}/lines`, {
      onSuccess: () => {
        setShowAddLineModal(false);
        addLineForm.reset();
      },
    });
  };

  const handleDeleteLine = (lineId: string) => {
    if (confirm(isAr ? 'هل أنت تأكد من حذف هذا السطر من كشف البنك؟' : 'Are you sure you want to delete this statement line?')) {
      router.delete(`/bank-reconciliations/${reconciliation.id}/lines/${lineId}`);
    }
  };

  const handleMatch = (lineId: string, ledgerEntryId: string) => {
    router.post(`/bank-reconciliations/${reconciliation.id}/lines/${lineId}/match`, {
      ledger_entry_id: ledgerEntryId,
    }, {
      onSuccess: () => {
        setSelectedLineForMatch(null);
      },
    });
  };

  const handleUnmatch = (lineId: string) => {
    router.post(`/bank-reconciliations/${reconciliation.id}/lines/${lineId}/unmatch`);
  };

  const handleFinalize = () => {
    if (confirm(isAr ? 'هل أنت تأكد من إغلاق واكتمال تسوية البنك؟' : 'Are you sure you want to finalize this bank reconciliation?')) {
      router.post(`/bank-reconciliations/${reconciliation.id}/finalize`);
    }
  };

  const currency = reconciliation.bankAccount?.currency || 'EGP';

  return (
    <AppLayout active="bank-reconciliations.show">
      <Head title={isAr ? 'شاشة مطابقة كشف البنك - Mini ERP' : 'Bank Reconciliation Workspace - Mini ERP'} />

      <PageHeader
        title={isAr ? `مطابقة كشف البنك: ${reconciliation.bankAccount?.name || ''}` : `Bank Reconciliation Workspace: ${reconciliation.bankAccount?.name || ''}`}
        description={isAr ? `الفترة من ${reconciliation.date_from} إلى ${reconciliation.date_to}` : `Period from ${reconciliation.date_from} to ${reconciliation.date_to}`}
        actions={
          <div className="flex items-center gap-2">
            {reconciliation.status === 'draft' ? (
              <>
                <button
                  type="button"
                  onClick={() => setShowAddLineModal(true)}
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3.5 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer"
                >
                  {isAr ? '+ إضافة حظر/سطر كشف' : '+ Add Statement Line'}
                </button>
                <button
                  type="button"
                  onClick={handleFinalize}
                  disabled={!summary.is_reconciled}
                  className="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-emerald-700 transition-all cursor-pointer disabled:opacity-50"
                >
                  {isAr ? 'اعتماد وإغلاق التسوية' : 'Finalize Reconciliation'}
                </button>
              </>
            ) : (
              <StatusBadge tone="ok">{isAr ? 'مُعتمد ومُقفل' : 'Finalized'}</StatusBadge>
            )}
          </div>
        }
      />

      {/* KPI Cards Summary */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <Card className="p-4">
          <p className="text-[11px] font-bold text-[var(--text-secondary)] uppercase">{isAr ? 'رخص كشف البنك' : 'Statement Closing'}</p>
          <p className="text-base font-mono font-bold text-[var(--text-primary)] mt-1">
            {formatMoney(summary.statement_closing_balance_minor, currency)}
          </p>
        </Card>
        <Card className="p-4">
          <p className="text-[11px] font-bold text-[var(--text-secondary)] uppercase">{isAr ? 'رصيد الأستاذ العام' : 'GL Book Closing'}</p>
          <p className="text-base font-mono font-bold text-[var(--text-primary)] mt-1">
            {formatMoney(summary.ledger_closing_balance_minor, currency)}
          </p>
        </Card>
        <Card className="p-4">
          <p className="text-[11px] font-bold text-[var(--text-secondary)] uppercase">{isAr ? 'فرق التسوية' : 'Reconciled Difference'}</p>
          <p className={`text-base font-mono font-bold mt-1 ${summary.reconciled_difference_minor === 0 ? 'text-emerald-600' : 'text-amber-600'}`}>
            {formatMoney(summary.reconciled_difference_minor, currency)}
          </p>
        </Card>
        <Card className="p-4">
          <p className="text-[11px] font-bold text-[var(--text-secondary)] uppercase">{isAr ? 'حالة المطابقة' : 'Reconciliation Status'}</p>
          <div className="mt-1">
            <StatusBadge tone={summary.is_reconciled ? 'ok' : 'warning'}>
              {summary.is_reconciled ? (isAr ? 'متطابق بالكامل (0.00)' : 'Fully Balanced (0.00)') : (isAr ? 'غير متطابق' : 'Unbalanced')}
            </StatusBadge>
          </div>
        </Card>
      </div>

      {/* Reconciliation Main Workspace Table */}
      <Card className="p-5 mb-8">
        <h2 className="text-sm font-bold text-[var(--text-primary)] mb-4">
          {isAr ? 'سطور كشف البنك المسجلة والمطابقة' : 'Bank Statement Lines & Matching'}
        </h2>

        {reconciliation.lines.length === 0 ? (
          <EmptyState
            title={isAr ? 'لا يوجد سطور كشف' : 'No Statement Lines'}
            description={isAr ? 'أضف سطور كشف البنك للبدء في المطابقة مع الأستاذ العام.' : 'Add statement lines to start matching with GL entries.'}
          />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{isAr ? 'التاريخ' : 'Date'}</th>
                  <th className={tableClasses.th}>{isAr ? 'المرجع' : 'Reference'}</th>
                  <th className={tableClasses.th}>{isAr ? 'البيان' : 'Description'}</th>
                  <th className={tableClasses.th}>{isAr ? 'سحب (مدين)' : 'Debit (Out)'}</th>
                  <th className={tableClasses.th}>{isAr ? 'إيداع (دائن)' : 'Credit (In)'}</th>
                  <th className={tableClasses.th}>{isAr ? 'الحركة المطابقة بالأستاذ' : 'Matched GL Entry'}</th>
                  <th className={tableClasses.th}>{isAr ? 'إجراءات' : 'Actions'}</th>
                </tr>
              </thead>
              <tbody>
                {reconciliation.lines.map((line) => (
                  <tr key={line.id} className="hover:bg-[var(--background)]/50 transition-colors">
                    <td className={`${tableClasses.td} font-mono text-xs`}>{line.statement_date}</td>
                    <td className={`${tableClasses.td} font-mono text-xs`}>{line.reference || '—'}</td>
                    <td className={tableClasses.td}>{line.description || '—'}</td>
                    <td className={`${tableClasses.td} font-mono text-xs`}>
                      {line.debit_minor > 0 ? formatMoney(line.debit_minor, currency) : '—'}
                    </td>
                    <td className={`${tableClasses.td} font-mono text-xs`}>
                      {line.credit_minor > 0 ? formatMoney(line.credit_minor, currency) : '—'}
                    </td>
                    <td className={tableClasses.td}>
                      {line.matchedLedgerEntry ? (
                        <div className="flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
                          <span>
                            {line.matchedLedgerEntry.journalEntry?.entry_number || line.matchedLedgerEntry.id.substring(0, 8)} ({formatMoney(line.matchedLedgerEntry.debit_minor || line.matchedLedgerEntry.credit_minor, currency)})
                          </span>
                        </div>
                      ) : (
                        <span className="text-xs text-amber-600 italic font-medium">{isAr ? 'غير مطابقة' : 'Unmatched'}</span>
                      )}
                    </td>
                    <td className={tableClasses.td}>
                      {reconciliation.status === 'draft' ? (
                        <div className="flex items-center gap-2">
                          {line.matchedLedgerEntry ? (
                            <button
                              type="button"
                              onClick={() => handleUnmatch(line.id)}
                              className="text-xs font-bold text-amber-600 hover:underline cursor-pointer"
                            >
                              {isAr ? 'إلغاء المطابقة' : 'Unmatch'}
                            </button>
                          ) : (
                            <button
                              type="button"
                              onClick={() => setSelectedLineForMatch(line)}
                              className="text-xs font-bold text-[var(--primary)] hover:underline cursor-pointer"
                            >
                              {isAr ? 'مطابقة مع قيد' : 'Match GL'}
                            </button>
                          )}
                          {!line.matchedLedgerEntry ? (
                            <button
                              type="button"
                              onClick={() => handleDeleteLine(line.id)}
                              className="text-xs font-bold text-red-600 hover:underline cursor-pointer"
                            >
                              {isAr ? 'حذف' : 'Delete'}
                            </button>
                          ) : null}
                        </div>
                      ) : (
                        <span className="text-xs text-[var(--text-muted)] font-mono">—</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      {/* Add Line Modal */}
      {showAddLineModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
          <div className="w-full max-w-md rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <h2 className="text-base font-bold text-[var(--text-primary)] mb-4">
              {isAr ? 'إضافة سطر كشف بنك جديد' : 'Add Statement Line'}
            </h2>

            <form onSubmit={submitAddLine} className="space-y-4">
              <DatePicker
                label={isAr ? 'تاريخ السطر' : 'Statement Date'}
                value={addLineForm.data.statement_date}
                onChange={(val) => addLineForm.setData('statement_date', val || '')}
                required
              />

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {isAr ? 'سحب (مدين)' : 'Debit (Money Out)'}
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    value={addLineForm.data.debit}
                    onChange={(e) => addLineForm.setData('debit', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono font-bold text-[var(--text-primary)]"
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {isAr ? 'إيداع (دائن)' : 'Credit (Money In)'}
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    value={addLineForm.data.credit}
                    onChange={(e) => addLineForm.setData('credit', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono font-bold text-[var(--text-primary)]"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {isAr ? 'المرجع البنكي' : 'Reference'}
                </label>
                <input
                  type="text"
                  value={addLineForm.data.reference}
                  onChange={(e) => addLineForm.setData('reference', e.target.value)}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono text-[var(--text-primary)]"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {isAr ? 'البيان / الشرح' : 'Description'}
                </label>
                <input
                  type="text"
                  value={addLineForm.data.description}
                  onChange={(e) => addLineForm.setData('description', e.target.value)}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]"
                />
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={() => setShowAddLineModal(false)}
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] cursor-pointer"
                >
                  {isAr ? 'إلغاء' : 'Cancel'}
                </button>
                <button
                  type="submit"
                  disabled={addLineForm.processing}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs cursor-pointer disabled:opacity-50"
                >
                  {addLineForm.processing ? (isAr ? 'جاري الإضافة...' : 'Adding...') : (isAr ? 'إضافة السطر' : 'Add Line')}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}

      {/* Matching Candidates Modal */}
      {selectedLineForMatch ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
          <div className="w-full max-w-2xl rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <h2 className="text-base font-bold text-[var(--text-primary)] mb-2">
              {isAr ? 'اختر القيد البنكي المطابق لسطر الكشف' : 'Select Matching GL Entry'}
            </h2>
            <p className="text-xs text-[var(--text-secondary)] mb-4">
              {isAr ? `سطر الكشف: ${selectedLineForMatch.statement_date} - ${selectedLineForMatch.description || 'بدون بيان'} (مدين: ${formatMoney(selectedLineForMatch.debit_minor, currency)} / دائن: ${formatMoney(selectedLineForMatch.credit_minor, currency)})` : `Line: ${selectedLineForMatch.statement_date} - ${selectedLineForMatch.description || 'No desc'}`}
            </p>

            {candidates.length === 0 ? (
              <div className="py-8 text-center text-xs text-[var(--text-muted)] border border-dashed border-[var(--border)] rounded-xl">
                {isAr ? 'لا يوجد قيود أستاذ عام بنكية غير مطابقة في هذه الفترة.' : 'No candidate unmatched GL entries found for this period.'}
              </div>
            ) : (
              <div className="max-h-80 overflow-y-auto rounded-xl border border-[var(--border)] mb-4">
                <table className={tableClasses.table}>
                  <thead>
                    <tr>
                      <th className={tableClasses.th}>{isAr ? 'التاريخ' : 'Date'}</th>
                      <th className={tableClasses.th}>{isAr ? 'رقم القيد' : 'Entry No.'}</th>
                      <th className={tableClasses.th}>{isAr ? 'البيان' : 'Description'}</th>
                      <th className={tableClasses.th}>{isAr ? 'مدين / دائن' : 'Amount'}</th>
                      <th className={tableClasses.th}>{isAr ? 'مطابقة' : 'Select'}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {candidates.map((cand) => (
                      <tr key={cand.id} className="hover:bg-[var(--background)]/50">
                        <td className={`${tableClasses.td} font-mono text-xs`}>{cand.entry_date}</td>
                        <td className={`${tableClasses.td} font-mono text-xs font-bold`}>{cand.journalEntry?.entry_number || cand.id.substring(0, 8)}</td>
                        <td className={tableClasses.td}>{cand.description || '—'}</td>
                        <td className={`${tableClasses.td} font-mono text-xs font-bold`}>
                          {formatMoney(cand.debit_minor || cand.credit_minor, currency)}
                        </td>
                        <td className={tableClasses.td}>
                          <button
                            type="button"
                            onClick={() => handleMatch(selectedLineForMatch.id, cand.id)}
                            className="rounded-lg bg-[var(--primary)] px-3 py-1 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer"
                          >
                            {isAr ? 'اختر للمطابقة' : 'Match'}
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            <div className="flex justify-end pt-2">
              <button
                type="button"
                onClick={() => setSelectedLineForMatch(null)}
                className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] cursor-pointer"
              >
                {isAr ? 'إغلاق' : 'Close'}
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
