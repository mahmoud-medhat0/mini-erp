import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, EmptyState, PageHeader, SensitiveActionModal, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary, interpolate } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
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
  const accDict = dict.app.accounting;
  const can = useCan();
  const canReconcileBanks = can('banks.reconcile');

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
      preserveScroll: true,
      onSuccess: () => {
        setShowAddLineModal(false);
        addLineForm.reset();
      },
    });
  };

  const handleDeleteLine = (lineId: string) => {
    if (confirm(dict.app.pages.bankReconciliationsShow.areYouSureYouWantTo)) {
      router.delete(`/bank-reconciliations/${reconciliation.id}/lines/${lineId}`, { preserveScroll: true });
    }
  };

  const handleMatch = (lineId: string, ledgerEntryId: string) => {
    router.post(`/bank-reconciliations/${reconciliation.id}/lines/${lineId}/match`, {
      ledger_entry_id: ledgerEntryId,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        setSelectedLineForMatch(null);
      },
    });
  };

  const handleUnmatch = (lineId: string) => {
    router.post(`/bank-reconciliations/${reconciliation.id}/lines/${lineId}/unmatch`, {}, { preserveScroll: true });
  };

  const [showFinalizeModal, setShowFinalizeModal] = useState(false);

  const handleFinalize = () => {
    setShowFinalizeModal(true);
  };

  const currency = reconciliation.bankAccount?.currency;
  const formatReconciliationMoney = (amountMinor: number): string => (currency ? formatMoney(amountMinor, currency) : accDict.notAvailable);
  const canEditReconciliation = reconciliation.status === 'draft' && canReconcileBanks;
  const finalizeTitle = summary.is_reconciled
    ? dict.app.pages.bankReconciliationsShow.finalizeReconciliation
    : dict.app.pages.bankReconciliationsShow.unbalanced;
  const headerActionState = reconciliation.status === 'draft' && !canReconcileBanks ? dict.app.actions.restricted : null;
  const getStatementLineActionState = () => {
    if (canEditReconciliation) return null;

    return reconciliation.status === 'draft' ? dict.app.actions.restricted : dict.app.actions.noActions;
  };

  return (
    <AppLayout active="bank-reconciliations.show">
      <Head title={dict.app.pages.bankReconciliationsShow.bankReconciliationWorkspaceMiniErp} />

      <PageHeader
        title={interpolate(dict.app.pages.bankReconciliationsShow.workspaceTitle, { name: reconciliation.bankAccount?.name || '' })}
        description={interpolate(dict.app.pages.bankReconciliationsShow.periodRange, { from: reconciliation.date_from, to: reconciliation.date_to })}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            {canEditReconciliation ? (
              <>
                <button
                  type="button"
                  onClick={() => setShowAddLineModal(true)}
                  title={dict.app.pages.bankReconciliationsShow.addStatementLine}
                  aria-label={dict.app.pages.bankReconciliationsShow.addStatementLine}
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3.5 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all cursor-pointer"
                >
                  {dict.app.pages.bankReconciliationsShow.addStatementLine}
                </button>
                <button
                  type="button"
                  onClick={handleFinalize}
                  disabled={!summary.is_reconciled}
                  title={finalizeTitle}
                  aria-label={finalizeTitle}
                  className="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-emerald-700 transition-all cursor-pointer disabled:opacity-50"
                >
                  {dict.app.pages.bankReconciliationsShow.finalizeReconciliation}
                </button>
              </>
            ) : reconciliation.status !== 'draft' ? (
              <StatusBadge tone="ok">{dict.app.pages.bankReconciliationsShow.finalized}</StatusBadge>
            ) : null}
            {headerActionState ? <StatusBadge tone="muted">{headerActionState}</StatusBadge> : null}
          </div>
        }
      />

      {/* KPI Cards Summary */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <Card className="p-4">
          <p className="text-[11px] font-bold text-[var(--text-secondary)] uppercase">{dict.app.pages.bankReconciliationsShow.statementClosing}</p>
          <p className="text-base font-mono font-bold text-[var(--text-primary)] mt-1">
            {formatReconciliationMoney(summary.statement_closing_balance_minor)}
          </p>
        </Card>
        <Card className="p-4">
          <p className="text-[11px] font-bold text-[var(--text-secondary)] uppercase">{dict.app.pages.bankReconciliationsShow.glBookClosing}</p>
          <p className="text-base font-mono font-bold text-[var(--text-primary)] mt-1">
            {formatReconciliationMoney(summary.ledger_closing_balance_minor)}
          </p>
        </Card>
        <Card className="p-4">
          <p className="text-[11px] font-bold text-[var(--text-secondary)] uppercase">{dict.app.pages.bankReconciliationsShow.reconciledDifference}</p>
          <p className={`text-base font-mono font-bold mt-1 ${summary.reconciled_difference_minor === 0 ? 'text-emerald-600' : 'text-amber-600'}`}>
            {formatReconciliationMoney(summary.reconciled_difference_minor)}
          </p>
        </Card>
        <Card className="p-4">
          <p className="text-[11px] font-bold text-[var(--text-secondary)] uppercase">{dict.app.pages.bankReconciliationsShow.reconciliationStatus}</p>
          <div className="mt-1">
            <StatusBadge tone={summary.is_reconciled ? 'ok' : 'warning'}>
              {summary.is_reconciled ? dict.app.pages.bankReconciliationsShow.fullyBalanced000 : dict.app.pages.bankReconciliationsShow.unbalanced}
            </StatusBadge>
          </div>
        </Card>
      </div>

      {/* Reconciliation Main Workspace Table */}
      <Card className="p-5 mb-8">
        <h2 className="text-sm font-bold text-[var(--text-primary)] mb-4">
          {dict.app.pages.bankReconciliationsShow.bankStatementLinesMatching}
        </h2>

        {reconciliation.lines.length === 0 ? (
          <EmptyState
            title={dict.app.pages.bankReconciliationsShow.noStatementLines}
            description={dict.app.pages.bankReconciliationsShow.addStatementLinesToStartMatching}
          />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{dict.app.pages.bankReconciliationsShow.date}</th>
                  <th className={tableClasses.th}>{dict.app.pages.bankReconciliationsShow.reference}</th>
                  <th className={tableClasses.th}>{dict.app.pages.bankReconciliationsShow.description}</th>
                  <th className={tableClasses.th}>{dict.app.pages.bankReconciliationsShow.debitOut}</th>
                  <th className={tableClasses.th}>{dict.app.pages.bankReconciliationsShow.creditIn}</th>
                  <th className={tableClasses.th}>{dict.app.pages.bankReconciliationsShow.matchedGlEntry}</th>
                  <th className={tableClasses.th}>{dict.app.pages.bankReconciliationsShow.actions}</th>
                </tr>
              </thead>
              <tbody>
                {reconciliation.lines.map((line) => {
                  const actionState = getStatementLineActionState();

                  return (
                    <tr key={line.id} className="hover:bg-[var(--background)]/50 transition-colors">
                      <td className={`${tableClasses.td} font-mono text-xs`}>{line.statement_date}</td>
                      <td className={`${tableClasses.td} font-mono text-xs`}>{line.reference || accDict.notAvailable}</td>
                      <td className={tableClasses.td}>{line.description || accDict.notAvailable}</td>
                      <td className={`${tableClasses.td} font-mono text-xs`}>
                        {line.debit_minor > 0 ? formatReconciliationMoney(line.debit_minor) : accDict.notAvailable}
                      </td>
                      <td className={`${tableClasses.td} font-mono text-xs`}>
                        {line.credit_minor > 0 ? formatReconciliationMoney(line.credit_minor) : accDict.notAvailable}
                      </td>
                      <td className={tableClasses.td}>
                        {line.matchedLedgerEntry ? (
                          <div className="flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
                            <span>
                              {line.matchedLedgerEntry.journalEntry?.entry_number || line.matchedLedgerEntry.id.substring(0, 8)} ({formatReconciliationMoney(line.matchedLedgerEntry.debit_minor || line.matchedLedgerEntry.credit_minor)})
                            </span>
                          </div>
                        ) : (
                          <span className="text-xs text-amber-600 italic font-medium">{dict.app.pages.bankReconciliationsShow.unmatched}</span>
                        )}
                      </td>
                      <td className={tableClasses.td}>
                        <div className="flex flex-wrap items-center justify-end gap-2">
                          {canEditReconciliation && line.matchedLedgerEntry ? (
                            <button
                              type="button"
                              onClick={() => handleUnmatch(line.id)}
                              title={dict.app.pages.bankReconciliationsShow.unmatch}
                              aria-label={dict.app.pages.bankReconciliationsShow.unmatch}
                              className="inline-flex h-8 items-center rounded-md border border-amber-200 px-2.5 text-xs font-semibold text-amber-700 transition-colors hover:bg-amber-50 dark:border-amber-900/60 dark:text-amber-300 dark:hover:bg-amber-950/40"
                            >
                              {dict.app.pages.bankReconciliationsShow.unmatch}
                            </button>
                          ) : null}
                          {canEditReconciliation && !line.matchedLedgerEntry ? (
                            <button
                              type="button"
                              onClick={() => setSelectedLineForMatch(line)}
                              title={dict.app.pages.bankReconciliationsShow.matchGl}
                              aria-label={dict.app.pages.bankReconciliationsShow.matchGl}
                              className="inline-flex h-8 items-center rounded-md border border-blue-200 px-2.5 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-50 dark:border-blue-900/60 dark:text-blue-300 dark:hover:bg-blue-950/40"
                            >
                              {dict.app.pages.bankReconciliationsShow.matchGl}
                            </button>
                          ) : null}
                          {canEditReconciliation && !line.matchedLedgerEntry ? (
                            <button
                              type="button"
                              onClick={() => handleDeleteLine(line.id)}
                              title={dict.app.pages.bankReconciliationsShow.delete}
                              aria-label={dict.app.pages.bankReconciliationsShow.delete}
                              className="inline-flex h-8 items-center rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-950/40"
                            >
                              {dict.app.pages.bankReconciliationsShow.delete}
                            </button>
                          ) : null}
                          {actionState ? <StatusBadge tone="muted">{actionState}</StatusBadge> : null}
                        </div>
                      </td>
                    </tr>
                  );
                })}
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
              {dict.app.pages.bankReconciliationsShow.addStatementLine_2}
            </h2>

            <form onSubmit={submitAddLine} className="space-y-4">
              <DatePicker
                label={dict.app.pages.bankReconciliationsShow.statementDate}
                value={addLineForm.data.statement_date}
                onChange={(val) => addLineForm.setData('statement_date', val || '')}
                required
              />

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.bankReconciliationsShow.debitMoneyOut}
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
                    {dict.app.pages.bankReconciliationsShow.creditMoneyIn}
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
                  {dict.app.pages.bankReconciliationsShow.reference_2}
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
                  {dict.app.pages.bankReconciliationsShow.description_2}
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
                  title={dict.app.pages.bankReconciliationsShow.cancel}
                  aria-label={dict.app.pages.bankReconciliationsShow.cancel}
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] cursor-pointer"
                >
                  {dict.app.pages.bankReconciliationsShow.cancel}
                </button>
                <button
                  type="submit"
                  disabled={addLineForm.processing}
                  title={dict.app.pages.bankReconciliationsShow.addLine}
                  aria-label={dict.app.pages.bankReconciliationsShow.addLine}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs cursor-pointer disabled:opacity-50"
                >
                  {addLineForm.processing ? dict.app.pages.bankReconciliationsShow.adding : dict.app.pages.bankReconciliationsShow.addLine}
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
              {dict.app.pages.bankReconciliationsShow.selectMatchingGlEntry}
            </h2>
            <p className="text-xs text-[var(--text-secondary)] mb-4">
              {interpolate(dict.app.pages.bankReconciliationsShow.matchLineLabel, {
                date: selectedLineForMatch.statement_date,
                desc: selectedLineForMatch.description || dict.app.pages.bankReconciliationsShow.noDesc,
                debit: formatReconciliationMoney(selectedLineForMatch.debit_minor),
                credit: formatReconciliationMoney(selectedLineForMatch.credit_minor),
              })}
            </p>

            {candidates.length === 0 ? (
              <div className="py-8 text-center text-xs text-[var(--text-muted)] border border-dashed border-[var(--border)] rounded-xl">
                {dict.app.pages.bankReconciliationsShow.noCandidateUnmatchedGlEntriesFound}
              </div>
            ) : (
              <div className="max-h-80 overflow-y-auto rounded-xl border border-[var(--border)] mb-4">
                <table className={tableClasses.table}>
                  <thead>
                    <tr>
                      <th className={tableClasses.th}>{dict.app.pages.bankReconciliationsShow.date_2}</th>
                      <th className={tableClasses.th}>{dict.app.pages.bankReconciliationsShow.entryNo}</th>
                      <th className={tableClasses.th}>{dict.app.pages.bankReconciliationsShow.description_3}</th>
                      <th className={tableClasses.th}>{dict.app.pages.bankReconciliationsShow.amount}</th>
                      <th className={tableClasses.th}>{dict.app.pages.bankReconciliationsShow.select}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {candidates.map((cand) => (
                      <tr key={cand.id} className="hover:bg-[var(--background)]/50">
                        <td className={`${tableClasses.td} font-mono text-xs`}>{cand.entry_date}</td>
                        <td className={`${tableClasses.td} font-mono text-xs font-bold`}>{cand.journalEntry?.entry_number || cand.id.substring(0, 8)}</td>
                        <td className={tableClasses.td}>{cand.description || accDict.notAvailable}</td>
                        <td className={`${tableClasses.td} font-mono text-xs font-bold`}>
                          {formatReconciliationMoney(cand.debit_minor || cand.credit_minor)}
                        </td>
                        <td className={tableClasses.td}>
                          <button
                            type="button"
                            onClick={() => handleMatch(selectedLineForMatch.id, cand.id)}
                            title={dict.app.pages.bankReconciliationsShow.match}
                            aria-label={dict.app.pages.bankReconciliationsShow.match}
                            className="rounded-lg bg-[var(--primary)] px-3 py-1 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer"
                          >
                            {dict.app.pages.bankReconciliationsShow.match}
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
                title={dict.app.pages.bankReconciliationsShow.close}
                aria-label={dict.app.pages.bankReconciliationsShow.close}
                className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] cursor-pointer"
              >
                {dict.app.pages.bankReconciliationsShow.close}
              </button>
            </div>
          </div>
        </div>
      ) : null}

      <SensitiveActionModal
        isOpen={showFinalizeModal}
        onClose={() => setShowFinalizeModal(false)}
        onConfirm={(payload) => {
          router.post(`/bank-reconciliations/${reconciliation.id}/finalize`, payload, {
            preserveScroll: true,
            onSuccess: () => setShowFinalizeModal(false),
          });
        }}
        confirmCode="FINALIZE_BANK_RECONCILIATION"
        message={dict.app.pages.bankReconciliationsShow.confirmFinalizeReconciliation}
        reasonRequired={true}
        locale={locale}
      />
    </AppLayout>
  );
}
