import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';
import { getDictionary, interpolate } from '../../lib/i18n';

type BankReconciliationDetailProps = SharedPageProps & {
  detail: {
    reconciliation: {
      id: string;
      bank_account: { id: string; code: string; name: string; currency: string };
      statement_reference: string;
      date_from: string;
      date_to: string;
      statement_opening_balance_minor: number;
      statement_closing_balance_minor: number;
      status: string;
      finalized_at: string | null;
      lines: Array<{
        id: string;
        statement_date: string;
        reference: string;
        description: string;
        debit_minor: number;
        credit_minor: number;
        matched_ledger_entry_id: string | null;
        matched_at: string | null;
        matched_ledger_entry: {
          id: string;
          entry_date: string;
          description: string;
          debit_minor: number;
          credit_minor: number;
          journal_number?: string;
        } | null;
      }>;
    };
    summary: {
      statement_movement_minor: number;
      system_movement_minor: number;
      matched_movement_minor: number;
      difference_minor: number;
      unmatched_statement_lines_count: number;
      matched_statement_lines_count: number;
      total_statement_lines_count: number;
    };
  };
};

export default function BankReconciliationDetail({ locale, detail }: BankReconciliationDetailProps) {
  const isAr = locale === 'ar';
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const can = useCan();
  const canPrint = can('reports.print') && can('view_financials');
  const { reconciliation, summary } = detail;

  return (
    <AppLayout active="reports.bank-reconciliations">
      <Head title={interpolate(dict.app.pages.bankReconciliationDetail.headTitle, { ref: reconciliation.statement_reference })} />

      <PageHeader
        title={interpolate(dict.app.pages.bankReconciliationDetail.reportTitle, { ref: reconciliation.statement_reference })}
        description={`${reconciliation.bank_account.code} - ${reconciliation.bank_account.name} (${reconciliation.date_from} → ${reconciliation.date_to})`}
        actions={
          <div className="flex items-center gap-3">
            {canPrint ? (
              <Button variant="secondary" onClick={() => window.print()}>
                {dict.app.actions.printReport}
              </Button>
            ) : null}
            <Link href="/reports/bank-reconciliations" className="inline-flex items-center text-xs font-bold text-[var(--primary)] hover:underline">
              {dict.app.pages.reportsBankReconciliationDetail.backToList}
            </Link>
          </div>
        }
      />

      <div className="space-y-6">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsBankReconciliationDetail.statementMovement}</div>
            <div className="text-sm font-bold text-[var(--text-primary)]">
              {formatMoney(summary.statement_movement_minor, reconciliation.bank_account.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsBankReconciliationDetail.matchedSystemMovement}</div>
            <div className="text-sm font-bold text-blue-600">
              {formatMoney(summary.matched_movement_minor, reconciliation.bank_account.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsBankReconciliationDetail.reconciliationDifference}</div>
            <div className={`text-sm font-bold ${summary.difference_minor === 0 ? 'text-emerald-600' : 'text-rose-600'}`}>
              {formatMoney(summary.difference_minor, reconciliation.bank_account.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsBankReconciliationDetail.reconStatus}</div>
            <div className="text-sm font-bold text-[var(--text-primary)]">
              <span className={`px-2 py-0.5 text-[10px] font-bold rounded-full ${
                reconciliation.status === 'reconciled'
                  ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                  : 'bg-amber-100 text-amber-800 border border-amber-300'
              }`}>
                {reconciliation.status.toUpperCase()}
              </span>
            </div>
          </div>
        </div>

        <Card className="overflow-hidden p-0">
          <div className="p-3 bg-[var(--background)] font-bold text-xs border-b border-[var(--border-color)]">
            {dict.app.pages.reportsBankReconciliationDetail.bankStatementLinesMatchedSystemEntries}
          </div>
          <table className="w-full text-start text-xs">
            <thead className="bg-[var(--background)]/50 border-b border-[var(--border-color)]">
              <tr>
                <th className="p-3 font-semibold text-start text-[var(--text-secondary)]">{dict.app.pages.reportsBankReconciliationDetail.statementDate}</th>
                <th className="p-3 font-semibold text-start text-[var(--text-secondary)]">{dict.app.pages.reportsBankReconciliationDetail.refDescription}</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsBankReconciliationDetail.statementAmount}</th>
                <th className="p-3 font-semibold text-start text-[var(--text-secondary)]">{dict.app.pages.reportsBankReconciliationDetail.matchedGlEntry}</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsBankReconciliationDetail.glAmount}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border-color)]">
              {reconciliation.lines.map((line) => {
                const stmtNet = line.debit_minor - line.credit_minor;
                const matchedNet = line.matched_ledger_entry ? line.matched_ledger_entry.debit_minor - line.matched_ledger_entry.credit_minor : 0;

                return (
                  <tr key={line.id} className="hover:bg-[var(--background)]/30">
                    <td className="p-3 font-mono">{line.statement_date}</td>
                    <td className="p-3">
                      <div className="font-semibold">{line.reference}</div>
                      <div className="text-[var(--text-secondary)] text-[11px]">{line.description}</div>
                    </td>
                    <td className="p-3 text-end font-mono font-bold">
                      {formatMoney(stmtNet, reconciliation.bank_account.currency)}
                    </td>
                    <td className="p-3">
                      {line.matched_ledger_entry ? (
                        <div>
                          <span className="font-mono font-bold text-blue-600">
                            {line.matched_ledger_entry.journal_number || dict.app.pages.reportsBankReconciliationDetail.missingGlJournalReference}
                          </span>
                          <span className="text-[var(--text-secondary)] ms-2">({line.matched_ledger_entry.entry_date})</span>
                        </div>
                      ) : (
                        <span className="text-slate-400 italic">{dict.app.pages.reportsBankReconciliationDetail.unmatched}</span>
                      )}
                    </td>
                    <td className="p-3 text-end font-mono">
                      {line.matched_ledger_entry ? formatMoney(matchedNet, reconciliation.bank_account.currency) : accDict.notAvailable}
                    </td>
                  </tr>
                );
              })}
              {reconciliation.lines.length === 0 ? (
                <tr>
                  <td colSpan={5} className="p-6 text-center text-[var(--text-muted)]">
                    {dict.app.pages.reportsBankReconciliationDetail.noStatementLinesInThisReconciliation}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </Card>
      </div>
    </AppLayout>
  );
}
